<?php 
// removeTransItems.php
// VERSION: FULLY ROBUST DUAL-WRITE + ID TRANSLATION
session_start();
header('Content-Type: application/json'); 
include('connection.php'); 

// 1. SECURITY & SESSION CHECK
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Unauthorized: You must be logged in."]);
    exit;
}

// 2. INPUT & CONTEXT VALIDATION
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['removeTransactions'])) {
    echo json_encode(["success" => false, "message" => "Invalid Request"]);
    exit;
}

$transactionIds = $_POST['transactionIds']; 
$branch_code    = $_POST['branch_code'] ?? $_SESSION['branch_code']; 
$session_branch = $_SESSION['branch_code'];
$is_remote      = ($branch_code !== $session_branch);

if (empty($transactionIds) || !is_array($transactionIds)) {
    echo json_encode(["success" => false, "message" => "No transactions selected."]);
    exit;
}

// Check Role
if (($_SESSION['role'] ?? '') !== 'Super Admin') {
    echo json_encode(["success" => false, "message" => "Permission Denied: Only 'Super Admin' can remove transactions."]); 
    exit; 
}

// 3. CLOUD CONNECTION & TARGET STATUS CHECK
$cloud_host = 'srv1254.hstgr.io';
$cloud_user = 'u106033383_jemerald1234';
$cloud_pass = 'Wearelive_1234';
$cloud_name = 'u106033383_jemerald_cloud';

$cloud_conn = @new mysqli($cloud_host, $cloud_user, $cloud_pass, $cloud_name);

if ($cloud_conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Network Error: Cannot connect to Cloud. Both branches must be online."]);
    exit;
}

// Verify Target Branch Online
$statusStmt = $cloud_conn->prepare("SELECT status, last_active_at FROM branches WHERE branch_code = ?");
$statusStmt->bind_param("s", $branch_code);
$statusStmt->execute();
$statusRes = $statusStmt->get_result()->fetch_assoc();
$statusStmt->close();

$is_target_online = false;
if ($statusRes) {
    $lastActive = strtotime($statusRes['last_active_at']);
    if ($statusRes['status'] === 'online' && (time() - $lastActive) < 300) {
        $is_target_online = true;
    }
}

if (!$is_target_online) {
    $cloud_conn->close();
    echo json_encode(["success" => false, "message" => "Target Branch ($branch_code) is OFFLINE. Cannot remove transactions."]);
    exit;
}

$isTransactionActive = false;  

try {
    // =========================================================
    // MODE A: REMOTE REMOVAL (Deleting data from a Remote Branch)
    // =========================================================
    // In this mode, we ONLY update the Cloud. The Remote Local Branch 
    // will sync these changes when it performs its own "Cloud Pull" or sync.
    if ($is_remote) {
        $ids_safe = implode(',', array_map('intval', $transactionIds));
        
        // 1. Fetch Item Quantities to Reverse (From Cloud)
        $fetchSql = "SELECT item_id, quantity FROM transactions WHERE id IN ($ids_safe) AND branch_code = '$branch_code'";
        $res = $cloud_conn->query($fetchSql);
        
        $itemsToUpdate = [];
        while ($row = $res->fetch_assoc()) {
            $iid = $row['item_id'];
            $itemsToUpdate[$iid] = ($itemsToUpdate[$iid] ?? 0) + $row['quantity'];
        }

        if (empty($itemsToUpdate)) throw new Exception("No transactions found in remote branch (Cloud).");

// 2. Restore Stock (Cloud)
        foreach ($itemsToUpdate as $itemId => $qty) {
            $upd = $cloud_conn->prepare("UPDATE items SET quantity_in_stock = quantity_in_stock + ? WHERE id = ?");
            $upd->bind_param("ii", $qty, $itemId);
            $upd->execute();
            
            // [CRITICAL FIX] Notify Sync System about Item Stock Change
            $cloud_conn->query("INSERT INTO cloud_change_log (table_name, record_id, branch_code, action_type) VALUES ('items', $itemId, '$branch_code', 'UPDATE')");
        }

        // 3. Mark Transactions as Removed (Cloud)
        $cloud_conn->query("UPDATE transactions SET status = 1 WHERE id IN ($ids_safe) AND branch_code = '$branch_code'");

        // [CRITICAL FIX] Notify Sync System about Transaction Status Change
        foreach ($transactionIds as $tId) {
            $safeTId = intval($tId);
            $cloud_conn->query("INSERT INTO cloud_change_log (table_name, record_id, branch_code, action_type) VALUES ('transactions', $safeTId, '$branch_code', 'UPDATE')");
        }
        
   // 4. Log Action (FIXED UNIQUE ID)
        $logMsg = "Remote Removal: " . count($transactionIds) . " transactions reversed by " . $_SESSION['username']. ', from ('. $_SESSION['branch_code'] .')';
        
        // Generate a random unique negative ID to prevent "Duplicate Entry" errors
        // We use negative numbers to indicate "Remote/Admin" actions that didn't originate locally
        $unique_pseudo_id = -1 * mt_rand(100000, 9999999); 

        $auditSql = "INSERT INTO audit_logs (branch_code, action, timestamp, local_id) VALUES (?, ?, NOW(), ?)";
        $stmt = $cloud_conn->prepare($auditSql);
        
        // Bind parameters: s=string, s=string, i=integer
        $stmt->bind_param("ssi", $branch_code, $logMsg, $unique_pseudo_id);
        
        if (!$stmt->execute()) {
            // Optional: Retry once with a new ID if purely coincidental collision occurs
            $unique_pseudo_id = -1 * mt_rand(100000, 9999999); 
            $stmt->bind_param("ssi", $branch_code, $logMsg, $unique_pseudo_id);
            $stmt->execute();
        }

    } 
    // =========================================================
    // MODE B: LOCAL REMOVAL (Atomic Dual-Write)
    // =========================================================
    else {
        
        // --- STEP 1: ID RESOLUTION (THE FIX) ---
        // We first try to find these IDs in the Local DB.
        // If they aren't found, we assume they are Cloud IDs (Super Admin View) 
        // and map them to Local IDs using the Cloud DB.

        $placeholders = implode(',', array_fill(0, count($transactionIds), '?'));
        
        // Attempt 1: Direct Local Lookup
        $query = "SELECT transaction_id, item_id, quantity FROM transactions WHERE transaction_id IN ($placeholders)";
        $stmt = $conn->prepare($query);
        foreach ($transactionIds as $index => $id) $stmt->bindValue($index + 1, $id, SQLITE3_INTEGER);
        $result = $stmt->execute();

        $itemsToUpdate = [];
        $localTransIds = []; // This will hold the validated LOCAL IDs
        
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $localTransIds[] = $row['transaction_id'];
            $itemsToUpdate[$row['item_id']] = ($itemsToUpdate[$row['item_id']] ?? 0) + $row['quantity'];
        }

        // [FIX] If Direct Lookup failed (Count mismatch or empty), try Translation
        if (count($localTransIds) < count($transactionIds)) {
            
            // Query Cloud to find 'local_id' for these Cloud IDs
            $cloud_ids_safe = implode(',', array_map('intval', $transactionIds));
            
            // Note: We select local_id where ID is in our list AND branch matches
            $mapSql = "SELECT local_id FROM transactions WHERE id IN ($cloud_ids_safe) AND branch_code = '$branch_code'";
            $mapRes = $cloud_conn->query($mapSql);
            
            $translatedIds = [];
            while ($row = $mapRes->fetch_assoc()) {
                if (!empty($row['local_id'])) {
                    // local_id might be stored as negative for remote syncs, ensure absolute value
                    $translatedIds[] = abs($row['local_id']);
                }
            }

            // If we found translated IDs, let's look THEM up locally
            if (!empty($translatedIds)) {
                // Reset arrays to fill with correct data
                $itemsToUpdate = [];
                $localTransIds = [];

                $placeholders = implode(',', array_fill(0, count($translatedIds), '?'));
                $query = "SELECT transaction_id, item_id, quantity FROM transactions WHERE transaction_id IN ($placeholders)";
                $stmt = $conn->prepare($query);
                foreach ($translatedIds as $index => $id) $stmt->bindValue($index + 1, $id, SQLITE3_INTEGER);
                $result = $stmt->execute();

                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    $localTransIds[] = $row['transaction_id'];
                    $itemsToUpdate[$row['item_id']] = ($itemsToUpdate[$row['item_id']] ?? 0) + $row['quantity'];
                }
            }
        }

        if (empty($localTransIds)) {
            throw new Exception("Transactions not found locally. Please refresh and try again.");
        }

        // --- STEP 2: ATOMIC TRANSACTION START ---
        $conn->exec("BEGIN TRANSACTION");
        $isTransactionActive = true; 

        // 3. Perform Cloud Removal First (Gatekeeper)
        // We use the LOCAL IDs we found to update the Cloud records via 'local_id' column
        $local_ids_string = implode(',', $localTransIds);
        
        $cloudSql = "UPDATE transactions SET status = 1 WHERE local_id IN ($local_ids_string) AND branch_code = '$branch_code'";
        if (!$cloud_conn->query($cloudSql)) {
            throw new Exception("Cloud Sync Failed: " . $cloud_conn->error);
        }
        
        // Reverse Stock in Cloud
        // (For Cloud Stock, we must look up the item via local_id to find its Cloud ID)
        foreach ($itemsToUpdate as $localItemId => $qty) {
            $cItemRes = $cloud_conn->query("SELECT id FROM items WHERE local_id = $localItemId AND branch_code = '$branch_code'");
            if ($r = $cItemRes->fetch_assoc()) {
                $cId = $r['id'];
                $cloud_conn->query("UPDATE items SET quantity_in_stock = quantity_in_stock + $qty WHERE id = $cId");
            }
        }

        // 4. Perform Local Removal (Using Verified Local IDs)
        $placeholders = implode(',', array_fill(0, count($localTransIds), '?'));
        $updTrans = $conn->prepare("UPDATE transactions SET status = 1 WHERE transaction_id IN ($placeholders)");
        foreach ($localTransIds as $index => $id) $updTrans->bindValue($index + 1, $id, SQLITE3_INTEGER);
        $updTrans->execute();

        // 5. Restore Local Stock
        foreach ($itemsToUpdate as $itemId => $qty) {
            $updStock = $conn->prepare("UPDATE items SET quantity_in_stock = quantity_in_stock + :qty WHERE item_id = :id");
            $updStock->bindValue(':qty', $qty, SQLITE3_INTEGER);
            $updStock->bindValue(':id', $itemId, SQLITE3_INTEGER);
            $updStock->execute();
        }

        $conn->exec("COMMIT");
    }

    $cloud_conn->close();
    echo json_encode(["success" => true, "message" => "Transactions removed and stock restored successfully."]);

} catch (Exception $e) {
    // Only rollback if we actually started a transaction locally
    if (!$is_remote && isset($conn) && $isTransactionActive) {
        $conn->exec("ROLLBACK");
    }
    if (isset($cloud_conn)) $cloud_conn->close();
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>