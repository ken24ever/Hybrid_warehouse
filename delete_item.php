<?php
// Start session to track logged-in user
session_start();
header('Content-Type: application/json');

// Start time measurement
$startTime = microtime(true);

// Include database connection
include('connection.php');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    echo json_encode(["success" => false, "message" => "Unauthorized: User session not found."]);
    exit;
}

$logged_in_user_id = $_SESSION['user_id'];
$logged_in_username = $_SESSION['username'];
$session_branch = $_SESSION['branch_code'] ?? 'UNKNOWN';

// ==================================================================
// [PROFESSIONAL FIX] HYBRID PERMISSION CHECK
// Trust the 'Super Admin' session natively since CEO accounts 
// may only exist in the Cloud DB and lack local SQLite permissions.
// ==================================================================
$is_super_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'Super Admin');

if (!$is_super_admin) {
    $permCheck = $conn->prepare("SELECT can_edit_settings FROM user_roles WHERE user_id = :user_id LIMIT 1");
    $permCheck->bindValue(':user_id', $logged_in_user_id, SQLITE3_INTEGER);
    $permResult = $permCheck->execute();
    $permRow = $permResult->fetchArray(SQLITE3_ASSOC);

    if (!$permRow || $permRow['can_edit_settings'] != 1) {
        echo json_encode(["success" => false, "message" => "Permission denied: You are not authorized to delete items."]);
        exit;
    }
}
// ==================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['itemIds']) && is_array($_POST['itemIds'])) {
    $itemIds = $_POST['itemIds'];

    if (empty($itemIds)) {
        echo json_encode(["success" => false, "message" => "No items selected for deletion."]);
        exit;
    }

    try {
        // [PROFESSIONAL FIX] Resolve Context (Remote vs Local Deletion)
        $target_branch = $_POST['branch_code'] ?? $session_branch;
        $is_remote = ($target_branch !== $session_branch);
        $itemsToDelete = [];
        $totalDeleted = 0;

       if ($is_remote) {
            // =========================================================
            // MODE A: REMOTE DELETION (Super Admin -> Other Branch)
            // =========================================================
            $cloud_conn = @new mysqli('srv1254.hstgr.io', 'u106033383_jemerald1234', 'Wearelive_1234', 'u106033383_jemerald_cloud');
            if ($cloud_conn->connect_error) {
                throw new Exception("Offline: Cannot delete remote items without an internet connection.");
            }

            // ------------------------------------------------------------------
            // [PROFESSIONAL FIX] REAL-TIME HEARTBEAT CHECK
            // Verify if the target branch is online before executing the deletion
            // ------------------------------------------------------------------
            $hb_sql = "SELECT TIMESTAMPDIFF(SECOND, last_active_at, NOW()) as seconds_ago 
                       FROM branches WHERE branch_code = ? LIMIT 1";
            $hb_stmt = $cloud_conn->prepare($hb_sql);
            if ($hb_stmt) {
                $hb_stmt->bind_param("s", $target_branch);
                $hb_stmt->execute();
                $hb_res = $hb_stmt->get_result();
                $hb_row = $hb_res->fetch_assoc();
                $hb_stmt->close();

                $seconds_ago = $hb_row['seconds_ago'] ?? 9999; 

                // THRESHOLD: 300 Seconds (5 Minutes)
                if ($seconds_ago > 300) {
                    $mins = round($seconds_ago / 60);
                    $cloud_conn->close();
                    throw new Exception("Action Blocked: The branch '$target_branch' is OFFLINE (Last seen $mins mins ago). Active connection required to sync deletions safely.");
                }
            }
            // ------------------------------------------------------------------

            $ids_safe = implode(',', array_map('intval', $itemIds));

        
            // 1. Fetch Items directly from the Cloud DB
            $fetchSql = "SELECT id as item_id, item_name, item_unique_no FROM items WHERE id IN ($ids_safe) AND branch_code = '$target_branch'";
            $res = $cloud_conn->query($fetchSql);
            
            while ($row = $res->fetch_assoc()) {
                $itemsToDelete[] = $row;
            }

            if (empty($itemsToDelete)) {
                $cloud_conn->close();
                throw new Exception("No matching items found in the remote database ($target_branch).");
            }

            // 2. Execute Cloud Delete
            $delSql = "DELETE FROM items WHERE id IN ($ids_safe) AND branch_code = '$target_branch'";
            if (!$cloud_conn->query($delSql)) {
                throw new Exception("Cloud Delete Failed.");
            }

            $totalDeleted = $cloud_conn->affected_rows;

            // 3. Log Deletions in Cloud Change Log (Triggers Target Branch to delete it locally)
            $clStmt = $cloud_conn->prepare("INSERT INTO cloud_change_log (table_name, record_id, branch_code, action_type, created_at) VALUES ('items', ?, ?, 'DELETE', NOW())");
            
            $cmdItems = [];
            foreach ($itemsToDelete as $item) {
                $deletedId = $item['item_id'];
                if ($clStmt) {
                    $clStmt->bind_param("is", $deletedId, $target_branch);
                    $clStmt->execute();
                }
                $cmdItems[] = ['id' => $deletedId, 'barcode' => $item['item_unique_no']];
            }
            if ($clStmt) $clStmt->close();

            // 4. Insert Remote Command Log (Robust Barcode Payload)
            $syncPayload = json_encode(['type' => 'SYNC_DELETE_COMMAND', 'initiator' => $logged_in_username, 'items' => $cmdItems]);
            $cmdMessage = "CMD:" . $syncPayload;
            $uniqueCmdId = mt_rand(-2147483648, -1000000);
            $cmdItemId = 0; 
            
            $cmdStmt = $cloud_conn->prepare("INSERT INTO audit_logs (branch_code, local_user_id, action, timestamp, local_id, item_id) VALUES (?, ?, ?, NOW(), ?, ?)");
            if ($cmdStmt) {
                $cmdStmt->bind_param("sisii", $target_branch, $logged_in_user_id, $cmdMessage, $uniqueCmdId, $cmdItemId);
                if ($cmdStmt->execute()) {
                    $newCmdId = $cmdStmt->insert_id;
                    $cloud_conn->query("INSERT INTO cloud_change_log (table_name, record_id, branch_code, action_type, created_at) VALUES ('audit_logs', $newCmdId, '$target_branch', 'INSERT', NOW())");
                }
                $cmdStmt->close();
            }

            $cloud_conn->close();

        } else {
            // =========================================================
            // MODE B: LOCAL DELETION (Dual-Write for Uniformity)
            // =========================================================
            
            // Begin transaction
            $conn->exec("BEGIN TRANSACTION");

            // Retrieve item_unique_no (Barcode) so we can locate it in the cloud later
            $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
            $selectQuery = "SELECT item_id, item_name, item_unique_no FROM items WHERE item_id IN ($placeholders)";
            $stmt = $conn->prepare($selectQuery);

            foreach ($itemIds as $index => $id) {
                $stmt->bindValue($index + 1, (int) $id, SQLITE3_INTEGER);
            }

            $result = $stmt->execute();

            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $itemsToDelete[] = $row;
            }

            if (empty($itemsToDelete)) {
                throw new Exception("No matching items found in the local database.");
            }

            // Proceed to delete the items LOCALLY
            $deleteQuery = "DELETE FROM items WHERE item_id IN ($placeholders)";
            $stmt = $conn->prepare($deleteQuery);

            foreach ($itemIds as $index => $id) {
                $stmt->bindValue($index + 1, (int) $id, SQLITE3_INTEGER);
            }

            if (!$stmt->execute()) {
                throw new Exception("Failed to delete selected items locally.");
            }

            // Prepare audit log entry
            $totalDeleted = count($itemsToDelete);
            $deletedItemNames = array_column($itemsToDelete, 'item_name');

            if ($totalDeleted <= 5) {
                $logMessage = "User '$logged_in_username' (ID: $logged_in_user_id) deleted the following items: " . implode(", ", $deletedItemNames);
            } else {
                $logMessage = "User '$logged_in_username' (ID: $logged_in_user_id) deleted $totalDeleted items, including: " . implode(", ", array_slice($deletedItemNames, 0, 4)) . " and others.";
            }

            // Insert into audit logs (LOCAL)
            $logQuery = "INSERT INTO audit_logs (user_id, action, timestamp) VALUES (:user_id, :action, datetime('now', 'localtime'))";
            $logStmt = $conn->prepare($logQuery);
            $logStmt->bindValue(':user_id', $logged_in_user_id, SQLITE3_INTEGER);
            $logStmt->bindValue(':action', $logMessage, SQLITE3_TEXT);
            $logStmt->execute();

            // Commit local transaction
            $conn->exec("COMMIT");

            // ------------------------------------------------------------------
            // DUAL-WRITE: DELETE FROM CLOUD & REGISTER SYNC
            // Ensures uniformity: When an item is deleted locally, it is instantly
            // wiped from the cloud and logged so other branches see the deletion.
            // ------------------------------------------------------------------
            try {
                $cloud_conn = @new mysqli('srv1254.hstgr.io', 'u106033383_jemerald1234', 'Wearelive_1234', 'u106033383_jemerald_cloud');
                
                if ($cloud_conn && !$cloud_conn->connect_error) {
                    
                    $cloudDelStmt = $cloud_conn->prepare("DELETE FROM items WHERE item_unique_no = ? AND branch_code = ?");
                    $clStmt = $cloud_conn->prepare("INSERT INTO cloud_change_log (table_name, record_id, branch_code, action_type, created_at) VALUES ('items', ?, ?, 'DELETE', NOW())");

                    foreach ($itemsToDelete as $dItem) {
                        $barcode = $dItem['item_unique_no'] ?? null;
                        
                        if ($barcode) {
                            // 1. Fetch Cloud ID BEFORE deleting (Needed for the Change Log)
                            $c_id = null;
                            $findStmt = $cloud_conn->prepare("SELECT id FROM items WHERE item_unique_no = ? AND branch_code = ?");
                            if ($findStmt) {
                                $findStmt->bind_param("is", $barcode, $session_branch);
                                $findStmt->execute();
                                $res = $findStmt->get_result();
                                if ($row = $res->fetch_assoc()) {
                                    $c_id = $row['id'];
                                }
                                $findStmt->close();
                            }

                            // 2. Delete the item directly from the Cloud Database
                            if ($cloudDelStmt) {
                                $cloudDelStmt->bind_param("is", $barcode, $session_branch);
                                $cloudDelStmt->execute();
                            }

                            // 3. Register the Deletion in the sync log
                            $log_id = $c_id ? $c_id : $dItem['item_id'];
                            if ($clStmt) {
                                $clStmt->bind_param("is", $log_id, $session_branch);
                                $clStmt->execute();
                            }
                        }
                    }

                    if ($cloudDelStmt) $cloudDelStmt->close();
                    if ($clStmt) $clStmt->close();
                    $cloud_conn->close();
                }
            } catch (Exception $e) {
                // Silently continue: If internet is down, local deletion still completes.
            }
        }

        $endTime = microtime(true);
        $executionTime = round(($endTime - $startTime) * 1000, 2);

        echo json_encode([
            "success" => true,
            "message" => "$totalDeleted item(s) deleted successfully.",
            "execution_time" => $executionTime . " ms"
        ]);
        
    } catch (Exception $e) {
        
        // Suppress rollback warning if transaction hasn't started or doesn't apply (Mode A)
        if (isset($conn) && $conn && !$is_remote) {
            @$conn->exec("ROLLBACK");
        }

        $endTime = microtime(true);
        $executionTime = round(($endTime - $startTime) * 1000, 2);

        echo json_encode([
            "success" => false,
            "message" => $e->getMessage(),
            "execution_time" => $executionTime . " ms"
        ]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Invalid request."]);
}
?>