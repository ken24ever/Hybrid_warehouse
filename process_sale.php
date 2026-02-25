<?php
// process_sale.php
// VERSION: HYBRID COST FETCH + STOCK VALIDATION + MATH FIX + STRICT ISOLATION
error_reporting(0);
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json'); 

include('connection.php'); 

// --- 1. DETERMINE CONTEXT ---
if (!isset($_SESSION['branch_code'])) {
    die(json_encode(['success' => false, 'message' => 'Session Error: User context not found. Please relogin.']));
}

$session_branch = $_SESSION['branch_code']; 
$userID         = $_SESSION['user_id'] ?? 0;
$target_branch  = $session_branch; 

if (isset($_POST['target_branch_code']) && !empty($_POST['target_branch_code'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'Super Admin') {
        $target_branch = $_POST['target_branch_code'];
    }
}

$is_remote_sale = ($target_branch !== $session_branch);
$cartItems = json_decode($_POST['cartItems'], true);

$modeOfPayment = $_POST['modeOfPayment'] ?? '';
if (empty($modeOfPayment) && !empty($cartItems) && isset($cartItems[0]['modeOfPayment'])) {
    $modeOfPayment = $cartItems[0]['modeOfPayment'];
}
if (empty($modeOfPayment)) {
    die(json_encode(['success' => false, 'message' => 'Critical Error: Mode of Payment is missing.']));
}

// --- 2. CLOUD CONNECTION ---
$cloud_host = 'srv1254.hstgr.io';
$cloud_user = 'u106033383_jemerald1234';
$cloud_pass = 'Wearelive_1234';
$cloud_name = 'u106033383_jemerald_cloud';

$cloud_conn = null;
$cloudUserID = 0;

try {
    $cloud_conn = @new mysqli($cloud_host, $cloud_user, $cloud_pass, $cloud_name);
    if ($cloud_conn->connect_error) {
        if ($is_remote_sale) throw new Exception("Cloud connection failed: " . $cloud_conn->connect_error);
        $cloud_conn = null;
    }

    // ------------------------------------------------------------------
            // [PROFESSIONAL FIX] REAL-TIME HEARTBEAT CHECK
            // We verify if the branch is TRULY online by checking 'last_active_at'.
            // ------------------------------------------------------------------
            $heartbeat_sql = "SELECT TIMESTAMPDIFF(SECOND, last_active_at, NOW()) as seconds_ago 
                              FROM branches WHERE branch_code = ? LIMIT 1";
            
            $hb_stmt = $cloud_conn->prepare($heartbeat_sql);
            if ($hb_stmt) {
                $hb_stmt->bind_param("s", $target_branch);
                $hb_stmt->execute();
                $hb_res = $hb_stmt->get_result();
                $hb_row = $hb_res->fetch_assoc();
                $hb_stmt->close();

                $seconds_ago = $hb_row['seconds_ago']; 

                // THRESHOLD: 300 Seconds (5 Minutes)
                // If it returns NULL (never active) or > 300s, the branch is Offline.
                if ($seconds_ago === null || $seconds_ago > 300) {
                    $mins = ($seconds_ago) ? round($seconds_ago / 60) : 'N/A';
                    throw new Exception("Transaction Blocked: The branch '$target_branch' is OFFLINE (Last seen $mins mins ago). Remote sales require an active connection to sync stock.");
                }
            } else {
                throw new Exception("System Error: Could not verify branch status.");
            }
            // ------------------------------------------------------------------

} catch (Exception $e) {
    if ($is_remote_sale) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
    
    $cloud_conn = null;
}

// --- 3. RESOLVE CLOUD IDENTITY ---
if ($is_remote_sale && $cloud_conn) {
    $uStmt = $cloud_conn->prepare("SELECT id FROM users WHERE local_id = ? AND branch_code = ?");
    if ($uStmt) {
        $uStmt->bind_param("is", $userID, $session_branch);
        $uStmt->execute();
        $res = $uStmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $cloudUserID = $row['id'];
        }
        $uStmt->close();
    }

    if ($cloudUserID === 0 && isset($_SESSION['username'])) {
        $uStmt2 = $cloud_conn->prepare("SELECT id FROM users WHERE username = ? AND branch_code = ?");
        if ($uStmt2) {
            $username = $_SESSION['username'];
            $uStmt2->bind_param("ss", $username, $session_branch);
            $uStmt2->execute();
            $res2 = $uStmt2->get_result();
            if ($row2 = $res2->fetch_assoc()) {
                $cloudUserID = $row2['id'];
            }
            $uStmt2->close();
        }
    }
}

// --- 4. PROCESS SALE ---
$transactionGroupID = 1000; 

if ($is_remote_sale && $cloud_conn) {
    $grpQuery = $cloud_conn->query("SELECT transaction_group_id FROM transactions ORDER BY id DESC LIMIT 1");
    if ($row = $grpQuery->fetch_assoc()) {
        $lastGrp = intval($row['transaction_group_id']);
        if ($lastGrp >= 1000 && $lastGrp < 9999) {
            $transactionGroupID = $lastGrp + 1;
        }
    }
} else {
    $grpQuery = $conn->query("SELECT transaction_group_id FROM transactions ORDER BY transaction_id DESC LIMIT 1");
    if ($row = $grpQuery->fetchArray(SQLITE3_ASSOC)) {
        $lastGrp = intval($row['transaction_group_id']);
        if ($lastGrp >= 1000 && $lastGrp < 9999) {
            $transactionGroupID = $lastGrp + 1;
        }
    }
}


// --- DATE RESOLUTION LOGIC ---
// Default to NOW
$transactionDate = date('Y-m-d H:i:s'); 

// Check if a custom backdate was sent in the cart data
if (!empty($cartItems) && isset($cartItems[0]['EditTransactionDate']) && !empty($cartItems[0]['EditTransactionDate'])) {
    // Format the incoming HTML datetime-local (Y-m-d\TH:i) to MySQL format (Y-m-d H:i:s)
    $transactionDate = date('Y-m-d H:i:s', strtotime($cartItems[0]['EditTransactionDate']));
}

$conn->exec("BEGIN TRANSACTION");

try {
    foreach ($cartItems as $item) {
        $item_id = $item['id'] ?? 0;
        if (empty($item_id)) throw new Exception("Critical: Item ID missing.");

        $quantity    = $item['quantity'];
        $unitPrice   = $item['salePrice'] ?? $item['fixedPrice'] ?? 0; 
        $totalAmount = $item['totalItemSale'] ?? ($unitPrice * $quantity); 
        
        $itemName = 'Item #' . $item_id;
        $purchasePrice = 0.00;
        $currentStock = 0; 

        if ($is_remote_sale && $cloud_conn) {
            $p_stmt = $cloud_conn->prepare("SELECT item_name, purchase_price, quantity_in_stock FROM items WHERE id = ?");
            if ($p_stmt) {
                $p_stmt->bind_param("i", $item_id);
                $p_stmt->execute();
                $res = $p_stmt->get_result();
                if ($cloudRow = $res->fetch_assoc()) {
                    $itemName = $cloudRow['item_name'];
                    $purchasePrice = floatval($cloudRow['purchase_price']);
                    $currentStock = intval($cloudRow['quantity_in_stock']); 
                }
                $p_stmt->close();
            }
        } else {
            $stmt = $conn->prepare("SELECT item_name, purchase_price, quantity_in_stock FROM items WHERE item_id = :id");
            $stmt->bindValue(':id', $item_id, SQLITE3_INTEGER);
            $res = $stmt->execute();
            if ($localRow = $res->fetchArray(SQLITE3_ASSOC)) {
                $itemName = $localRow['item_name'];
                $purchasePrice = floatval($localRow['purchase_price']);
                $currentStock = intval($localRow['quantity_in_stock']); 
            }
        }

        if ($currentStock <= 0) {
            throw new Exception("Error: '$itemName' is Out of Stock ($currentStock available). Sale blocked.");
        }

        if ($quantity > $currentStock) {
            throw new Exception("Error: Insufficient stock for '$itemName'. Requested: $quantity, Available: $currentStock.");
        }

        if ($purchasePrice <= 0) {
            throw new Exception("Error: Item '$itemName' has a Purchase Price of 0. Please update Inventory.");
        }

        if ($quantity > 0) {
            $unitPrice = $totalAmount / $quantity;
        }
        
        $profit = $totalAmount - ($purchasePrice * $quantity);

        // ==========================================================
        // STRICT CONTEXT ISOLATION: LOCAL VS REMOTE SALE
        // ==========================================================
        if (!$is_remote_sale) {
            
            $sql = "INSERT INTO transactions (
                        modeOfPayment, user_id, item_id, transaction_date, transaction_type, 
                        quantity, total_amount, sold_at, profit_loss, 
                        transaction_group_id, status, sync_status, branch_code, 
                        profit, fixed_price_at_sale
                    ) VALUES (
                        :mode, :user, :item, :date, 'sale', 
                        :qty, :total, :sold_at, :profit, 
                        :group, 0, 0, :branch, 
                        :profit_val, :fixed_price
                    )";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':mode', $modeOfPayment, SQLITE3_TEXT);
            $stmt->bindValue(':user', $userID, SQLITE3_INTEGER);
            $stmt->bindValue(':item', $item_id, SQLITE3_INTEGER);
            $stmt->bindValue(':date', $transactionDate, SQLITE3_TEXT);
            $stmt->bindValue(':qty', $quantity, SQLITE3_INTEGER);
            $stmt->bindValue(':total', $totalAmount, SQLITE3_FLOAT);
            $stmt->bindValue(':sold_at', $unitPrice, SQLITE3_FLOAT); 
            $stmt->bindValue(':profit', $profit, SQLITE3_FLOAT);
            $stmt->bindValue(':group', $transactionGroupID, SQLITE3_TEXT);
            $stmt->bindValue(':branch', $target_branch, SQLITE3_TEXT);
            $stmt->bindValue(':profit_val', $profit, SQLITE3_FLOAT);
            $stmt->bindValue(':fixed_price', $unitPrice, SQLITE3_FLOAT);
            
            if (!$stmt->execute()) {
                throw new Exception("Local Transaction Insert Failed: " . $conn->lastErrorMsg());
            }

            $conn->exec("UPDATE items SET quantity_in_stock = quantity_in_stock - $quantity, sync_status = 0 WHERE item_id = $item_id");

            $actionDetails = "Sold $quantity x $itemName (Ref: #$transactionGroupID)";
            $logSql = "INSERT INTO audit_logs (user_id, action, item_id, branch_code, sync_status) VALUES (:uid, :act, :iid, :br, 0)";
            $logStmt = $conn->prepare($logSql);
            $logStmt->bindValue(':uid', $userID, SQLITE3_INTEGER);
            $logStmt->bindValue(':act', $actionDetails, SQLITE3_TEXT);
            $logStmt->bindValue(':iid', $item_id, SQLITE3_INTEGER);
            $logStmt->bindValue(':br', $target_branch, SQLITE3_TEXT);
            
            if (!$logStmt->execute()) {
                throw new Exception("Local Audit Log Failed: " . $conn->lastErrorMsg());
            }

        } else {
            
            if ($cloud_conn) {
                $cloud_sql = "INSERT IGNORE INTO transactions (
                                modeOfPayment, item_id, transaction_date, quantity, sold_at, 
                                profit_loss, total_amount, profit, user_id, local_user_id, 
                                fixed_price_at_sale, branch_code, local_id, transaction_group_id, transaction_type
                              ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'sale')";
                
                $c_stmt = $cloud_conn->prepare($cloud_sql);
                if ($c_stmt) {
                    $finalCloudID = ($cloudUserID > 0) ? $cloudUserID : 0;
                    $remoteLocalID = -1 * mt_rand(100000, 9999999); 

                    $c_stmt->bind_param("sisiddddiidsis", 
                        $modeOfPayment, $item_id, $transactionDate, $quantity, $unitPrice, 
                        $profit, $totalAmount, $profit, $finalCloudID, $userID, 
                        $unitPrice, $target_branch, $remoteLocalID, $transactionGroupID
                    );
                    
                    $c_stmt->execute();
                    
                    $new_id = $c_stmt->insert_id;
                    if ($new_id == 0) {
                        $getIdStmt = $cloud_conn->query("SELECT id FROM transactions WHERE transaction_group_id = '$transactionGroupID' AND item_id = $item_id AND branch_code = '$target_branch' ORDER BY id DESC LIMIT 1");
                        if ($row = $getIdStmt->fetch_assoc()) $new_id = $row['id'];
                    }
                    
                  if ($new_id > 0) { 
                        $cloud_conn->query("INSERT INTO cloud_change_log (table_name, record_id, branch_code, action_type) VALUES ('transactions', $new_id, '$target_branch', 'INSERT')");
                    
                        $updateStockSql = "UPDATE items SET quantity_in_stock = quantity_in_stock - ? WHERE id = ?";
                        $stockStmt = $cloud_conn->prepare($updateStockSql);
                        if ($stockStmt) {
                            $stockStmt->bind_param("ii", $quantity, $item_id);
                            $stockStmt->execute();
                            $stockStmt->close();

                            // [PROFESSIONAL FIX] Broadcast the exact Item Stock update
                            $cloud_conn->query("INSERT INTO cloud_change_log (table_name, record_id, branch_code, action_type, created_at) VALUES ('items', $item_id, '$target_branch', 'UPDATE', NOW())");
                        }
                    }

                    $remoteAction = "Sold $quantity x $itemName [Remote: $session_branch]";
                    $audit_sql = "INSERT INTO audit_logs (local_id, user_id, action, item_id, branch_code, local_user_id, timestamp) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $a_stmt = $cloud_conn->prepare($audit_sql);
                    
                    if ($a_stmt) {
                        $remote_log_id = -1 * mt_rand(100000, 9999999); 
                        $a_stmt->bind_param("iisisis", $remote_log_id, $finalCloudID, $remoteAction, $item_id, $target_branch, $userID, $transactionDate);
                        $a_stmt->execute();
                        $a_stmt->close();
                    }
                    $c_stmt->close();
                }
            } else {
                 throw new Exception("Error: Remote Sale requires an active Cloud Connection.");
            }
        }
    } 

    $conn->exec("COMMIT");
    if ($cloud_conn) $cloud_conn->close(); 
    
    echo json_encode([
        'success' => true, 
        'transaction_group_id' => $transactionGroupID, 
        'transactionDate' => $transactionDate, 
        'mode' => $is_remote_sale ? 'remote' : 'local'
    ]);

} catch (Exception $e) {
    $conn->exec("ROLLBACK");
    if ($cloud_conn) $cloud_conn->close();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>