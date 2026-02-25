<?php
// upload_items.php

// --- 1. CONFIGURATION & ERROR HANDLING ---
ini_set('display_errors', 0); 
ini_set('log_errors', 1);
error_reporting(E_ALL);

ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300);

header('Content-Type: application/json');
ob_start(); 

session_start();

function jsonExceptionHandler($e) {
    ob_clean();
    echo json_encode([
        'success' => false, 
        'message' => 'Critical Error: ' . $e->getMessage(), 
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
    exit;
}
set_exception_handler('jsonExceptionHandler');

try {
    include('connection.php');

    if (file_exists('vendor/autoload.php')) {
        require 'vendor/autoload.php';
    } elseif (file_exists('../vendor/autoload.php')) {
        require '../vendor/autoload.php';
    } else {
        throw new Exception("Vendor folder missing. Run 'composer install'.");
    }

    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User session not found.');
    }

    // --- CONTEXT RESOLUTION ---
    $session_branch = $_SESSION['branch_code'];
    $target_branch  = $session_branch; // Default to local

    // [FIX] 1. Capture the Requested Target Branch FIRST (Before checking status)
    if (isset($_POST['target_branch_code']) && !empty($_POST['target_branch_code'])) {
        // Security: Only Super Admin can switch context
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'Super Admin') {
            $target_branch = $_POST['target_branch_code'];
        }
    }

    // [FIX] 2. NOW Determine Remote Status
    $is_remote = ($target_branch !== $session_branch);

    // [FIX] 3. Perform Heartbeat Check (Only if Remote)
    if ($is_remote) {
        // Connect to Cloud to verify Target Status
        $cloud_conn_check = new mysqli('srv1254.hstgr.io', 'u106033383_jemerald1234', 'Wearelive_1234', 'u106033383_jemerald_cloud');
        
        if ($cloud_conn_check->connect_error) {
            throw new Exception("Offline: Cannot upload to remote branch without internet.");
        }

        // ------------------------------------------------------------------
        // [PROFESSIONAL FIX] REAL-TIME HEARTBEAT CHECK
        // ------------------------------------------------------------------
        $hb_sql = "SELECT TIMESTAMPDIFF(SECOND, last_active_at, NOW()) as seconds_ago 
                   FROM branches WHERE branch_code = ? LIMIT 1";
        
        $hb_stmt = $cloud_conn_check->prepare($hb_sql);
        $hb_stmt->bind_param("s", $target_branch);
        $hb_stmt->execute();
        $hb_res = $hb_stmt->get_result();
        $hb_row = $hb_res->fetch_assoc();
        $hb_stmt->close();
        
        $seconds_ago = $hb_row['seconds_ago'] ?? 9999;

        if ($seconds_ago > 300) { // 5 Minute Threshold
            $cloud_conn_check->close();
            $mins = round($seconds_ago / 60);
            throw new Exception("Upload Failed: Target branch '$target_branch' is OFFLINE (Last seen $mins mins ago).");
        }
        
        $cloud_conn_check->close(); 
    }


    $is_remote_add = ($target_branch !== $session_branch);
    $logged_in_user_id = $_SESSION['user_id'];

    // --- PERMISSIONS ---
    if (!$conn) throw new Exception("Local Database Connection Failed.");
    
    // ------------------------------------------------------------------
    // [PROFESSIONAL FIX] HYBRID PERMISSION CHECK
    // Issue: CEO/Super Admin accounts might exist in Cloud but not Locally.
    // Fix: If Session Role is 'Super Admin', we BYPASS the local DB check.
    // ------------------------------------------------------------------
    
    $session_role = $_SESSION['role'] ?? '';

    if ($session_role === 'Super Admin') {
        // TRUST THE SESSION for Super Admins
        $canEdit = 1;
        $canCreate = 1;
        $role = 'super admin';
    } else {
        // STRICT CHECK for Local Staff
        $stmt = $conn->prepare("SELECT r.role_name, ur.can_edit_settings, ur.can_create_items FROM users u JOIN roles r ON u.role_id = r.role_id LEFT JOIN user_roles ur ON u.user_id = ur.user_id WHERE u.user_id = :user_id");
        $stmt->bindValue(':user_id', $logged_in_user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);

        if (!$row) {
            // [Optional Fallback] If not found locally, check Cloud? 
            // For now, fail safe for local users.
            throw new Exception('Permission check failed. User not found locally.');
        }

        $role = strtolower(trim($row['role_name']));
        $canEdit = (int)$row['can_edit_settings'];
        $canCreate = (int)$row['can_create_items'];

        if ($role !== 'super admin' && $canEdit !== 1 && !($canEdit === 0 && $canCreate === 1)) {
            throw new Exception('Permission denied.');
        }
    }
    // ------------------------------------------------------------------

    // --- CLOUD CONNECTION ---
    $cloud_host = 'srv1254.hstgr.io';
    $cloud_user = 'u106033383_jemerald1234';
    $cloud_pass = 'Wearelive_1234';
    $cloud_name = 'u106033383_jemerald_cloud';

    $cloud_conn = null;
    try {
        $cloud_conn = @new mysqli($cloud_host, $cloud_user, $cloud_pass, $cloud_name);
        if ($cloud_conn->connect_error) $cloud_conn = null;
    } catch (Throwable $e) { $cloud_conn = null; }

    // --- PROCESSING ---
    $startTime = microtime(true);

    if (isset($_FILES['excelFile']) && !empty($_FILES['excelFile']['tmp_name'])) { 
        $file = $_FILES['excelFile']['tmp_name'];

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
        } catch (Exception $e) {
            throw new Exception("Error loading Excel: " . $e->getMessage());
        }
        
        $data = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        $insertedCount = 0;
        $errorRows = [];
        $isFirstRow = true;

        $stmtCloud = null;
        $stmtInsert = null;
        $stmtCheck = null;
        $stmtCloudSync = null;
        $stmtCloudCheck = null; 

        // Prepare statements based on Mode
        if ($cloud_conn) {
             // [NEW] Check for duplicate in Cloud
             $stmtCloudCheck = $cloud_conn->prepare("SELECT COUNT(*) FROM items WHERE item_unique_no = ? AND branch_code = ?");
        }

        if ($is_remote_add) {
            // MODE A: REMOTE
            if (!$cloud_conn) throw new Exception("Internet required for Remote Upload to $target_branch");
            
            $cloud_sql = "INSERT INTO items (
                item_unique_no, item_name, item_description, purchase_price, 
                wholesale_price, retail_price, category_name, quantity_in_stock, 
                supplier_info, invoice_number, date_purchased, expiration_date, 
                branch_code, local_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmtCloud = $cloud_conn->prepare($cloud_sql);
            if (!$stmtCloud) throw new Exception("Cloud Prepare Failed: " . $cloud_conn->error);

        } else {
            // MODE B: LOCAL
            $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM items WHERE item_unique_no = :item_unique_no");
            $stmtInsert = $conn->prepare("INSERT INTO items (status, item_unique_no, item_name, item_description, purchase_price, wholesale, retail, category, quantity_in_stock, expiration_date, invoice_number, supplier_info, date_purchased, branch_code) VALUES (:status, :item_unique_no, :item_name, :item_description, :purchase_price, :wholesale, :retail, :category, :quantity_in_stock, :expiration_date, :invoice_number, :supplier_info, :date_purchased, :branch_code)");
            
           if ($cloud_conn) {
                $cloud_sync_sql = "INSERT INTO items (
                    item_unique_no, item_name, item_description, purchase_price, 
                    wholesale_price, retail_price, category_name, quantity_in_stock, 
                    supplier_info, invoice_number, date_purchased, expiration_date, 
                    branch_code, local_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmtCloudSync = $cloud_conn->prepare($cloud_sync_sql);
            }
        }

        foreach ($data as $index => $row) {
            if ($isFirstRow) { $isFirstRow = false; continue; }
            if (empty(array_filter($row))) continue;

            $itemName = trim($row['A'] ?? '');
            $itemDescription = trim($row['B'] ?? 'N/A');
            $purchasePrice = is_numeric($row['C']) ? floatval($row['C']) : 0.00;
            $wholesale = is_numeric($row['D']) ? floatval($row['D']) : 0;
            $retail = is_numeric($row['E']) ? floatval($row['E']) : 0;
            $quantityInStock = is_numeric($row['F']) ? intval($row['F']) : 0;
            $itemUniqueNo = trim($row['G'] ?? '');
            $expirationDateRaw = trim($row['H'] ?? '');
            $invoiceNumber = trim($row['I'] ?? 'N/A');
            $supplierInfo = trim($row['J'] ?? 'N/A');
            $category = trim($row['K'] ?? '');
            $datePurchasedInput = trim($row['L'] ?? '');

            if (empty($itemName) || empty($itemUniqueNo) || empty($category)) {
                $errorRows[] = "Row $index: Missing Name, Barcode, or Category.";
                continue;
            }
            if (!ctype_digit($itemUniqueNo)) {
                $errorRows[] = "Row $index: Invalid Barcode (Must be digits).";
                continue;
            }

            // Safe Date Parsing
            $datePurchased = null;
            if (!empty($datePurchasedInput)) {
                try {
                    $dateObj = new DateTime($datePurchasedInput);
                    $datePurchased = $dateObj->format('Y-m-d');
                } catch (Exception $e) {
                    if (is_numeric($datePurchasedInput)) {
                        try { $datePurchased = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($datePurchasedInput)->format('Y-m-d'); } catch (Exception $ex) {}
                    }
                }
            }
            
            $expirationDate = null;
            if (!empty($expirationDateRaw)) {
                try {
                    $dateObj = new DateTime($expirationDateRaw);
                    $expirationDate = $dateObj->format('Y-m-d');
                } catch (Exception $e) {
                    if (is_numeric($expirationDateRaw)) {
                        try { $expirationDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($expirationDateRaw)->format('Y-m-d'); } catch (Exception $ex) {}
                    }
                }
            }

            // --- EXECUTION ---
            if ($is_remote_add) {
                // *** MODE A: REMOTE INSERT ***
                
                // [NEW] Cloud Duplicate Check
                if ($stmtCloudCheck) {
                    $stmtCloudCheck->bind_param("is", $itemUniqueNo, $target_branch);
                    $stmtCloudCheck->execute();
                    $stmtCloudCheck->bind_result($cloudCount);
                    $stmtCloudCheck->fetch();
                    $stmtCloudCheck->free_result(); // [FIX] Free result to prevent Sync Error
                    
                    if ($cloudCount > 0) {
                        $errorRows[] = "Row $index: Cloud Duplicate Barcode ($itemUniqueNo).";
                        continue; // Skip Insert
                    }
                }

                $pseudoLocalId = mt_rand(-2147483647, -1);

                if ($stmtCloud) {
                    $stmtCloud->bind_param("isddddsssssssi", 
                        $itemUniqueNo, $itemName, $itemDescription, $purchasePrice, 
                        $wholesale, $retail, $category, $quantityInStock, 
                        $supplierInfo, $invoiceNumber, $datePurchased, $expirationDate,
                        $target_branch, $pseudoLocalId
                    );
                    if ($stmtCloud->execute()) {
                        $insertedCount++;
                        // --- CLOUD AUDIT LOG (INSERT HERE) --- 
                        $new_cloud_id = $stmtCloud->insert_id;

                      // ---------------------------------------------------------
                        // [CRITICAL FIX] REGISTER CHANGE LOG (BULK)
                        // [FIX] Changed 'timestamp' to 'created_at' to match DB Schema
                        // ---------------------------------------------------------
                        $change_sql = "INSERT INTO cloud_change_log (table_name, record_id, branch_code, action_type, created_at) 
                                       VALUES ('items', ?, ?, 'INSERT', NOW())";
                                       
                        $change_stmt = $cloud_conn->prepare($change_sql);
                        if ($change_stmt) {
                            $change_stmt->bind_param("is", $new_cloud_id, $target_branch);
                            $change_stmt->execute();
                            $change_stmt->close();
                        }
                        // ---------------------------------------------------------

                        $pseudoLogId = -1 * mt_rand(100000, 9999999);
                        $action = "Uploaded item '$itemName' (Barcode: $itemUniqueNo) [Remote: $session_branch]";

                        $stmtLogC = $cloud_conn->prepare("INSERT INTO audit_logs (local_id, branch_code, local_user_id, action, item_id, timestamp) VALUES (?, ?, ?, ?, ?, NOW())");
                        if ($stmtLogC) {
                            $stmtLogC->bind_param("isisi", $pseudoLogId, $target_branch, $logged_in_user_id, $action, $new_cloud_id);
                            $stmtLogC->execute();
                            $stmtLogC->close();
                        }
                        // -------------------------------------


                    } else {
                        $errorRows[] = "Row $index: Cloud Error - " . $stmtCloud->error;
                    }
                }

            } else {
                // *** MODE B: LOCAL INSERT ***
                
                // [EXISTING] Local Check
                $stmtCheck->bindValue(':item_unique_no', $itemUniqueNo, SQLITE3_INTEGER);
                $res = $stmtCheck->execute();
                if (($res->fetchArray(SQLITE3_NUM)[0] ?? 0) > 0) {
                    $errorRows[] = "Row $index: Local Duplicate Barcode.";
                    continue;
                }

                $status = 'purchase';
                $stmtInsert->bindValue(':status', $status, SQLITE3_TEXT);
                $stmtInsert->bindValue(':item_unique_no', $itemUniqueNo, SQLITE3_INTEGER);
                $stmtInsert->bindValue(':item_name', $itemName, SQLITE3_TEXT);
                $stmtInsert->bindValue(':item_description', $itemDescription, SQLITE3_TEXT);
                $stmtInsert->bindValue(':purchase_price', $purchasePrice, SQLITE3_FLOAT);
                $stmtInsert->bindValue(':wholesale', $wholesale, SQLITE3_FLOAT);
                $stmtInsert->bindValue(':retail', $retail, SQLITE3_FLOAT);
                $stmtInsert->bindValue(':category', $category, SQLITE3_TEXT);
                $stmtInsert->bindValue(':quantity_in_stock', $quantityInStock, SQLITE3_INTEGER);
                $stmtInsert->bindValue(':expiration_date', $expirationDate, SQLITE3_TEXT);
                $stmtInsert->bindValue(':invoice_number', $invoiceNumber, SQLITE3_TEXT);
                $stmtInsert->bindValue(':supplier_info', $supplierInfo, SQLITE3_TEXT);
                $stmtInsert->bindValue(':date_purchased', $datePurchased, SQLITE3_TEXT);
                $stmtInsert->bindValue(':branch_code', $session_branch, SQLITE3_TEXT);

                if ($stmtInsert->execute()) {
                    $insertedCount++;
                    $local_id = $conn->lastInsertRowID();

                    // --- LOCAL AUDIT LOG (INSERT HERE) ---
                    $action = "Uploaded item '$itemName' (ID: $local_id)";
                    $stmtLogL = $conn->prepare("INSERT INTO audit_logs (user_id, action, item_id, timestamp, branch_code, sync_status) VALUES (:uid, :act, :iid, datetime('now','localtime'), :br, 0)");
                    $stmtLogL->bindValue(':uid', $logged_in_user_id, SQLITE3_INTEGER);
                    $stmtLogL->bindValue(':act', $action, SQLITE3_TEXT);
                    $stmtLogL->bindValue(':iid', $local_id, SQLITE3_INTEGER);
                    $stmtLogL->bindValue(':br', $session_branch, SQLITE3_TEXT);
                    $stmtLogL->execute();
                    // -------------------------------------

                    // [NEW] Cloud Sync Logic with Check
                    if ($cloud_conn && $stmtCloudSync && $stmtCloudCheck) {
                        
                        // Check duplicate before sync
                        $stmtCloudCheck->bind_param("is", $itemUniqueNo, $session_branch);
                        $stmtCloudCheck->execute();
                        $stmtCloudCheck->bind_result($cloudCount);
                        $stmtCloudCheck->fetch();
                        $stmtCloudCheck->free_result(); // [FIX] Free result to prevent Sync Error

                        if ($cloudCount == 0) {
                             $stmtCloudSync->bind_param("isddddsssssssi", 
                                $itemUniqueNo, $itemName, $itemDescription, $purchasePrice, 
                                $wholesale, $retail, $category, $quantityInStock, 
                                $supplierInfo, $invoiceNumber, $datePurchased, $expirationDate,
                                $session_branch, $local_id
                            );
                            if ($stmtCloudSync->execute()) {
                                $conn->exec("UPDATE items SET sync_status = 1 WHERE item_id = $local_id");
                            }
                        }
                    }
                } else {
                    $errorRows[] = "Row $index: Local Insert Failed.";
                }
            }
        } 

        $executionTime = round(microtime(true) - $startTime, 3);
        
        ob_clean(); 
        echo json_encode([
            'success' => true,
            'message' => "$insertedCount items processed.",
            'execution_time' => $executionTime,
            'errors' => $errorRows
        ]);

        if ($stmtCloud) $stmtCloud->close();
        if ($stmtCloudSync) $stmtCloudSync->close();
        if ($stmtCloudCheck) $stmtCloudCheck->close();
        if ($cloud_conn) $cloud_conn->close();

    } else {
        throw new Exception('No file uploaded.');
    }

} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
$conn->close();
ob_end_flush();
?>