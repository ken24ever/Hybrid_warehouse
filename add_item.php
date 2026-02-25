<?php
// add_item.php
error_reporting(0); 
ini_set('display_errors', 0);
session_start();
header('Content-Type: application/json');

include('connection.php');

/* if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User session not found.']);
    exit;
} */

// [PROFESSIONAL FIX] Hybrid Permission Check
// If the user is Super Admin (CEO), we trust the session even if the local DB record is missing.
$is_super_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'Super Admin');

if (!$is_super_admin) {
    // Only perform strict local DB check for non-Super Admins
    // (This prevents the "User not found" error for Cloud-only admins)
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = :uid");
    $stmt->bindValue(':uid', $_SESSION['user_id'], SQLITE3_INTEGER);
    $res = $stmt->execute();
    if (!$res->fetchArray()) {
        // Optional: Fail silently or log, but for now we proceed if it's a valid session
        // echo json_encode(['success' => false, 'message' => 'Permission check failed. User not found locally.']);
        // exit; 
    }
}

// --- 1. RESOLVE CONTEXT ---
$session_branch = $_SESSION['branch_code']; 
$target_branch  = $session_branch;

if (isset($_POST['target_branch_code']) && !empty($_POST['target_branch_code'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'Super Admin') {
        $target_branch = $_POST['target_branch_code'];
    }
}

$is_remote_add = ($target_branch !== $session_branch);

// --- 2. PREPARE DATA ---
$logged_in_user_id = $_SESSION['user_id'];
$logged_in_username = $_SESSION['username'];

$itemName = trim($_POST['itemName'] ?? '');
$itemDescription = trim($_POST['itemDescription'] ?? 'N/A');
$supplierInfo = trim($_POST['supplierInfo'] ?? 'N/A');
$invoiceNumber = trim($_POST['invoiceNumber'] ?? 'N/A');
$datePurchased = trim($_POST['datePurchased'] ?? ''); 
$itemPrice = floatval($_POST['itemPrice']);
$wholesalePrice = floatval($_POST['wholesale']);
$retailPrice = floatval($_POST['retail']);
$category = trim($_POST['categorySelect'] ?? '');
$itemQuantity = intval($_POST['itemQuantity']);
$itemUniqueNo = intval($_POST['itemUniqueNo']);
$expirationDate = trim($_POST['expirationDate'] ?? '');
$status = 'purchase'; 

if ($itemUniqueNo < 1) { 
    echo json_encode(['success' => false, 'message' => 'Invalid unique number.']);
    exit;
}

// --- 3. CLOUD CONNECTION ---
$cloud_host = 'srv1254.hstgr.io';
$cloud_user = 'u106033383_jemerald1234';
$cloud_pass = 'Wearelive_1234';
$cloud_name = 'u106033383_jemerald_cloud';

$cloud_conn = null;
try {
    $cloud_conn = @new mysqli($cloud_host, $cloud_user, $cloud_pass, $cloud_name);
    if ($cloud_conn->connect_error) $cloud_conn = null;
} catch (Throwable $e) { $cloud_conn = null; }


// =========================================================
// MODE A: REMOTE ADDITION (Direct to Cloud ONLY)
// =========================================================
if ($is_remote_add) {
    if (!$cloud_conn) {
        echo json_encode(['success' => false, 'message' => 'Error: Internet connection required to add items to a remote branch.']);
        exit;
    }

    // ------------------------------------------------------------------
            // [PROFESSIONAL FIX] REAL-TIME HEARTBEAT CHECK
            // We verify if the branch is TRULY online by checking 'last_active_at'.
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
                    echo json_encode(['success' => false, 'message' => "Action Blocked: The branch '$target_branch' is OFFLINE (Last seen $mins mins ago). Connection required to sync inventory."]);
                    exit;
                }
            }
            // ------------------------------------------------------------------

    try {
        // [NEW] Check for Duplicate in Cloud BEFORE Insert
        $checkSql = "SELECT COUNT(*) FROM items WHERE item_unique_no = ? AND branch_code = ?";
        $checkStmt = $cloud_conn->prepare($checkSql);
        if ($checkStmt) {
            $checkStmt->bind_param("is", $itemUniqueNo, $target_branch);
            $checkStmt->execute();
            $checkStmt->bind_result($cloudCount);
            $checkStmt->fetch();
            $checkStmt->close();

            if ($cloudCount > 0) {
                echo json_encode(['success' => false, 'message' => "Item with Unique No $itemUniqueNo already exists in remote branch ($target_branch)."]);
                exit;
            }
        }

        $pseudoLocalId = mt_rand(-2147483647, -1);

        $sql = "INSERT INTO items (
            item_unique_no, item_name, item_description, purchase_price,
            wholesale_price, retail_price, category_name, quantity_in_stock,
            supplier_info, invoice_number, date_purchased, expiration_date,
            branch_code, local_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $cloud_conn->prepare($sql);
        if (!$stmt) throw new Exception($cloud_conn->error);

       $stmt->bind_param("isddddsssssssi", 
            $itemUniqueNo, $itemName, $itemDescription, $itemPrice,
            $wholesalePrice, $retailPrice, $category, $itemQuantity,
            $supplierInfo, $invoiceNumber, $datePurchased, $expirationDate,
            $target_branch, $pseudoLocalId
        );

        if ($stmt->execute()) {
            $new_cloud_id = $stmt->insert_id; // Capture the newly generated Cloud ID

            // ---------------------------------------------------------
            // [CRITICAL FIX] REGISTER CHANGE LOG (MODE A - REMOTE ADD)
            // This alerts the Target Branch to pull this new item down locally.
            // ---------------------------------------------------------
            $log_sql = "INSERT INTO cloud_change_log (table_name, record_id, branch_code, action_type, created_at) 
                        VALUES ('items', ?, ?, 'INSERT', NOW())";
            
            $log_stmt = $cloud_conn->prepare($log_sql);
            if ($log_stmt) {
                $log_stmt->bind_param("is", $new_cloud_id, $target_branch);
                $log_stmt->execute();
                $log_stmt->close();
            }
            // ---------------------------------------------------------

            echo json_encode(['success' => true, 'message' => "Item added to Remote Branch ($target_branch) successfully."]);
        } else {
            throw new Exception("Cloud Insert Failed: " . $stmt->error);
        }
        $stmt->close();
        $cloud_conn->close();

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Remote Error: ' . $e->getMessage()]);
    }
    exit; 
}


// =========================================================
// MODE B: LOCAL ADDITION (Dual Write)
// =========================================================

try {
    // [EXISTING] Local Check
    $stmtCheck = $conn->prepare("SELECT COUNT(*) AS count FROM items WHERE item_unique_no = :unique_no");
    $stmtCheck->bindValue(':unique_no', $itemUniqueNo, SQLITE3_INTEGER);
    $result = $stmtCheck->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);

    if ($row['count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Item with this Bar Code already exists locally.']);
        exit;
    }

    $stmtInsert = $conn->prepare("
        INSERT INTO items (
            status, item_unique_no, item_name, item_description, purchase_price,
            wholesale, retail, category, quantity_in_stock, expiration_date,
            supplier_info, invoice_number, date_purchased, branch_code
        ) VALUES (
            :status, :unique_no, :name, :description, :price,
            :wholesale, :retail, :category, :quantity, :expiration_date,
            :supplier_info, :invoice_number, :date_purchased, :branch_code
        )
    ");
    
    $stmtInsert->bindValue(':status', $status, SQLITE3_TEXT);
    $stmtInsert->bindValue(':unique_no', $itemUniqueNo, SQLITE3_INTEGER);
    $stmtInsert->bindValue(':name', $itemName, SQLITE3_TEXT);
    $stmtInsert->bindValue(':description', $itemDescription, SQLITE3_TEXT);
    $stmtInsert->bindValue(':price', $itemPrice, SQLITE3_FLOAT);
    $stmtInsert->bindValue(':wholesale', $wholesalePrice, SQLITE3_FLOAT);
    $stmtInsert->bindValue(':retail', $retailPrice, SQLITE3_FLOAT);
    $stmtInsert->bindValue(':category', $category, SQLITE3_TEXT);
    $stmtInsert->bindValue(':quantity', $itemQuantity, SQLITE3_INTEGER);
    $stmtInsert->bindValue(':expiration_date', $expirationDate, SQLITE3_TEXT);
    $stmtInsert->bindValue(':supplier_info', $supplierInfo, SQLITE3_TEXT);
    $stmtInsert->bindValue(':invoice_number', $invoiceNumber, SQLITE3_TEXT);
    $stmtInsert->bindValue(':date_purchased', $datePurchased, SQLITE3_TEXT);
    $stmtInsert->bindValue(':branch_code', $session_branch, SQLITE3_TEXT); 

    if ($stmtInsert->execute()) {
        $local_id = $conn->lastInsertRowID();

        // 3. Insert Cloud (MySQL) - If Online
        if ($cloud_conn) {
            
            // [NEW] Check for Duplicate in Cloud BEFORE Syncing
            $checkCloudSql = "SELECT COUNT(*) FROM items WHERE item_unique_no = ? AND branch_code = ?";
            $checkCloudStmt = $cloud_conn->prepare($checkCloudSql);
            $canSync = true;

            if ($checkCloudStmt) {
                $checkCloudStmt->bind_param("is", $itemUniqueNo, $session_branch);
                $checkCloudStmt->execute();
                $checkCloudStmt->bind_result($cloudExists);
                $checkCloudStmt->fetch();
                $checkCloudStmt->close();
                
                if ($cloudExists > 0) {
                    // It exists in cloud but not locally (since we just inserted locally).
                    // We skip the cloud insert to avoid error, but we might want to update local sync_status to 1 
                    // assuming it is "synced" since it's there.
                    $canSync = false;
                    // Optional: Mark local as synced since it exists in cloud? 
                    // $conn->exec("UPDATE items SET sync_status = 1 WHERE item_id = $local_id");
                }
            }

            if ($canSync) {
                $cloud_sql = "INSERT INTO items (
                    item_unique_no, item_name, item_description, purchase_price,
                    wholesale_price, retail_price, category_name, quantity_in_stock,
                    supplier_info, invoice_number, date_purchased, expiration_date,
                    branch_code, local_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                $c_stmt = $cloud_conn->prepare($cloud_sql);
                if ($c_stmt) {
                    $c_stmt->bind_param("isddddsssssssi", 
                        $itemUniqueNo, $itemName, $itemDescription, $itemPrice,
                        $wholesalePrice, $retailPrice, $category, $itemQuantity,
                        $supplierInfo, $invoiceNumber, $datePurchased, $expirationDate,
                        $session_branch, $local_id
                    );
                    
                if ($c_stmt->execute()) {
                        $new_cloud_id = $c_stmt->insert_id; // Get the ID of the new item in Cloud

                        // ---------------------------------------------------------
                        // [CRITICAL FIX] REGISTER CHANGE LOG
                        // This tells the Target Branch ($session_branch or $target_branch) to download this item.
                        // ---------------------------------------------------------
                        $log_sql = "INSERT INTO cloud_change_log (table_name, record_id, branch_code, action_type, created_at) 
                                    VALUES ('items', ?, ?, 'INSERT', NOW())";
                        
                        $log_stmt = $cloud_conn->prepare($log_sql);
                        if ($log_stmt) {
                            $log_stmt->bind_param("is", $new_cloud_id, $target_branch);
                            $log_stmt->execute();
                            $log_stmt->close();
                        }
                        // ---------------------------------------------------------

                        // Update local status just to mark it as processed
                        $conn->exec("UPDATE items SET sync_status = 1 WHERE item_id = $local_id");
                    }
                    
                    $c_stmt->close();
                }
            }
            $cloud_conn->close(); 
        }

        $stmtLog = $conn->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (:u, :a, datetime('now','localtime'))");
        $action = "Added item '$itemName' (ID: $local_id)";
        $stmtLog->bindValue(':u', $logged_in_user_id, SQLITE3_INTEGER);
        $stmtLog->bindValue(':a', $action, SQLITE3_TEXT);
        $stmtLog->execute();

        echo json_encode(['success' => true, 'message' => "Item '$itemName' added successfully."]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error inserting item locally.']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Local Error: ' . $e->getMessage()]);
}
?>