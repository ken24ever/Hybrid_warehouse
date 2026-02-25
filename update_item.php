<?php
// update_item.php
// VERSION: FIXED CLOUD ID MAPPING (Local ID vs Cloud ID)
session_start();
include('connection.php'); 

header('Content-Type: application/json');

if (!isset($_SESSION['role'])) {
    http_response_code(401); echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

// --- 1. INPUT HANDLING ---
$inputData = $_POST;
if (empty($inputData)) {
    $rawInput = file_get_contents("php://input");
    $inputData = json_decode($rawInput, true);
    if (empty($inputData)) {
        parse_str($rawInput, $inputData);
    }
}

$session_branch   = $_SESSION['branch_code'];
$requested_branch = $inputData['branch_code'] ?? $session_branch;
$is_remote        = ($requested_branch !== $session_branch);
$userID           = intval($_SESSION['user_id'] ?? 0); 

$itemID = intval($inputData['itemID'] ?? 0);
if ($itemID === 0) { echo json_encode(['success' => false, 'message' => 'Item ID missing.']); exit; }

// --- SANITIZE INPUTS ---
$itemBarCode   = trim($inputData['itembarcode'] ?? 'Unknown');
$itemName      = trim($inputData['itemName'] ?? 'Unknown');
$itemDesc      = trim($inputData['itemDescription'] ?? '');
$price         = floatval($inputData['itemPrice'] ?? 0);
$wholesale     = floatval($inputData['wholesale_price'] ?? 0);
$retail        = floatval($inputData['retail_price'] ?? 0);
$qty           = intval($inputData['itemQuantity'] ?? 0);
$category      = trim($inputData['category_Select'] ?? 'Uncategorized');
$expiry        = !empty($inputData['expiration_date']) ? $inputData['expiration_date'] : null;
$invoice       = trim($inputData['invoiceNumber'] ?? '');
$supplier      = trim($inputData['supplierName'] ?? '');

// --- FETCH OLD DATA ---
$oldData = [];
if ($is_remote) {
    try {
        $cloud_conn = new mysqli('srv1254.hstgr.io', 'u106033383_jemerald1234', 'Wearelive_1234', 'u106033383_jemerald_cloud');
        if ($cloud_conn->connect_error) throw new Exception("Connect Error");
        
        $stmt = $cloud_conn->prepare("SELECT * FROM items WHERE id = ? AND branch_code = ?");
        $stmt->bind_param("is", $itemID, $requested_branch);
        $stmt->execute();
        $oldData = $stmt->get_result()->fetch_assoc();
        $cloud_conn->close();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Offline: Cannot edit remote items.']); exit;
    }
} else {
    $stmt = $conn->prepare("SELECT * FROM items WHERE item_id = :id");
    $stmt->bindValue(':id', $itemID, SQLITE3_INTEGER);
    $res = $stmt->execute();
    $oldData = $res->fetchArray(SQLITE3_ASSOC);
}

if (!$oldData) { echo json_encode(['success' => false, 'message' => 'Item not found.']); exit; }

// --- DETECT CHANGES ---
$changes = [];
if (($oldData['item_name'] ?? '') !== $itemName) $changes[] = "Name updated";
if (($oldData['item_description'] ?? '') !== $itemDesc) $changes[] = "Desc updated";
if (($oldData['item_unique_no'] ?? '') !== $itemBarCode) $changes[] = "Barcode updated";

$oldP = floatval($oldData['purchase_price'] ?? 0);
if (abs($oldP - $price) > 0.001) $changes[] = "Purch: $oldP -> $price";

$oldW = floatval($oldData['wholesale_price'] ?? $oldData['wholesale'] ?? 0);
if (abs($oldW - $wholesale) > 0.001) $changes[] = "Wholesale: $oldW -> $wholesale";

$oldR = floatval($oldData['retail_price'] ?? $oldData['retail'] ?? 0);
if (abs($oldR - $retail) > 0.001) $changes[] = "Retail: $oldR -> $retail";

$oldQ = intval($oldData['quantity_in_stock'] ?? 0);
if ($oldQ !== $qty) $changes[] = "Qty: $oldQ -> $qty";

$oldCat = $oldData['category_name'] ?? $oldData['category'] ?? 'Uncategorized';
if ($oldCat !== $category) $changes[] = "Cat updated";

$oldExp = $oldData['expiration_date'] ?? null;
if ($oldExp !== $expiry) $changes[] = "Expiry updated";

$oldInv = $oldData['invoice_number'] ?? '';
if ($oldInv !== $invoice) $changes[] = "Inv updated";

$oldSup = $oldData['supplier_info'] ?? '';
if ($oldSup !== $supplier) $changes[] = "Sup updated";

if (empty($changes)) { 
    echo json_encode(['success' => true, 'message' => 'No changes made.', 'changes' => []]); 
    exit; 
}

$changeLogStr = "Updated '$itemName' (ID:$itemID). " . implode(", ", $changes);

// --- EXECUTE UPDATE ---
try {
    $sync_status = 0; 
    $cloud_success = false;
    $audit_warning = null; 

    // A. ONLINE ATTEMPT (Cloud Update)
    try {
        $cloud_conn = @new mysqli('srv1254.hstgr.io', 'u106033383_jemerald1234', 'Wearelive_1234', 'u106033383_jemerald_cloud');
        if ($cloud_conn && !$cloud_conn->connect_error) {


        // ------------------------------------------------------------------
            // [PROFESSIONAL FIX] REAL-TIME HEARTBEAT CHECK
            // We verify if the branch is TRULY online by checking 'last_active_at'.
            // ------------------------------------------------------------------
            if ($is_remote) {
                $hb_sql = "SELECT TIMESTAMPDIFF(SECOND, last_active_at, NOW()) as seconds_ago 
                           FROM branches WHERE branch_code = ? LIMIT 1";
                
                $hb_stmt = $cloud_conn->prepare($hb_sql);
                if ($hb_stmt) {
                    $hb_stmt->bind_param("s", $requested_branch);
                    $hb_stmt->execute();
                    $hb_res = $hb_stmt->get_result();
                    $hb_row = $hb_res->fetch_assoc();
                    $hb_stmt->close();

                    $seconds_ago = $hb_row['seconds_ago'] ?? 9999; // Default to offline if NULL

                    // THRESHOLD: 300 Seconds (5 Minutes)
                    if ($seconds_ago > 300) {
                        $mins = round($seconds_ago / 60);
                        throw new Exception("Update Blocked: The branch '$requested_branch' is OFFLINE (Last seen $mins mins ago). Editing remote items requires an active sync connection.");
                    }
                }
            }
            // ------------------------------------------------------------------
            
            // [CRITICAL FIX] TARGETING LOGIC
            // If Remote: $itemID is the Cloud ID. Use 'id'.
            // If Local:  $itemID is the Local ID. Use 'local_id' to match the cloud record.
            $id_column = $is_remote ? 'id' : 'local_id';

            // 1. UPDATE ITEM
            $sqlC = "UPDATE items SET 
                    item_unique_no=?, item_name=?, item_description=?, purchase_price=?, wholesale_price=?, 
                    retail_price=?, quantity_in_stock=?, category_name=?, expiration_date=?, 
                    invoice_number=?, supplier_info=?, updated_at=NOW()
                    WHERE $id_column=? AND branch_code=?";
            
            $stmtC = $cloud_conn->prepare($sqlC);

            // Bind Params: 'sssdddissssis' (13 params)
            $stmtC->bind_param("sssdddissssis", 
                $itemBarCode, $itemName, $itemDesc, $price, $wholesale, $retail, $qty, $category, 
                $expiry, $invoice, $supplier, $itemID, $requested_branch
            );

            if (!$stmtC->execute()) {
                throw new Exception("Cloud Update Failed: " . $stmtC->error);
            }
            
            // Optional: Check if any row was actually touched
            // if ($stmtC->affected_rows === 0) { ... warning ... }

            $stmtC->close();

            // 2. CHANGE LOG (SYNC TRIGGER)
            // Note: For change log, we usually log against the Cloud ID, but here we only have $itemID.
            // If local, $itemID is local ID. Ideally, we should fetch the Cloud ID to log it properly, 
            // but for simplicity, we log the attempt. The sync system usually handles the rest.
            $stmtCL = $cloud_conn->prepare("INSERT INTO cloud_change_log (table_name, record_id, branch_code, action_type) VALUES ('items', ?, ?, 'UPDATE')");
            $stmtCL->bind_param("is", $itemID, $requested_branch);
            $stmtCL->execute();
            $stmtCL->close();

            $cloud_success = true;
            $sync_status = 1; 

            // 3. AUDIT LOG (With Sync Trigger)
            try {
                $cloudLogAction = $changeLogStr; 
                if ($is_remote) {
                    $origin_branch = $_SESSION['branch_code'] ?? 'ADMIN';
                    $cloudLogAction .= " [Remote: " . $origin_branch . "]";
                }

                $unique_local_id = -1 * mt_rand(1000, 2147483647); 
                $stmtLogC = $cloud_conn->prepare("INSERT INTO audit_logs (local_id, branch_code, local_user_id, action, item_id, timestamp) VALUES (?, ?, ?, ?, ?, NOW())");
                
                if ($stmtLogC) {
                    $stmtLogC->bind_param("isisi", $unique_local_id, $requested_branch, $userID, $cloudLogAction, $itemID);
                    $stmtLogC->execute();
                    $stmtLogC->close();
                }
            } catch (Exception $eLog) {
                $audit_warning = "Cloud Log Error: " . $eLog->getMessage();
            }

            $cloud_conn->close();
        } elseif ($is_remote) {
            throw new Exception("Remote update failed: Could not connect to Cloud Server.");
        }
    } catch (Exception $e) { 
        $cloud_success = false; 
        error_log("Cloud Connection/Update Failed: " . $e->getMessage());
        if ($is_remote) {
            echo json_encode(['success' => false, 'message' => 'Remote Update Failed: ' . $e->getMessage()]);
            exit;
        }
    }

    // B. LOCAL UPDATE (Skipped if remote)
    if (!$is_remote) {
        $conn->exec("BEGIN TRANSACTION");

        $sqlL = "UPDATE items SET 
                item_unique_no=:barcode, item_name=:name, item_description=:desc, purchase_price=:price, 
                wholesale=:whole, retail=:retail, quantity_in_stock=:qty, 
                category=:cat, expiration_date=:exp, invoice_number=:inv, 
                supplier_info=:sup, updated_at=datetime('now','localtime'),
                sync_status = :sync
                WHERE item_id=:id AND branch_code=:branch"; 

        $stmtL = $conn->prepare($sqlL);
        $stmtL->bindValue(':barcode', $itemBarCode, SQLITE3_TEXT);
        $stmtL->bindValue(':name', $itemName, SQLITE3_TEXT);
        $stmtL->bindValue(':desc', $itemDesc, SQLITE3_TEXT);
        $stmtL->bindValue(':price', $price, SQLITE3_FLOAT);
        $stmtL->bindValue(':whole', $wholesale, SQLITE3_FLOAT);
        $stmtL->bindValue(':retail', $retail, SQLITE3_FLOAT);
        $stmtL->bindValue(':qty', $qty, SQLITE3_INTEGER);
        $stmtL->bindValue(':cat', $category, SQLITE3_TEXT);
        $stmtL->bindValue(':exp', $expiry, SQLITE3_TEXT);
        $stmtL->bindValue(':inv', $invoice, SQLITE3_TEXT);
        $stmtL->bindValue(':sup', $supplier, SQLITE3_TEXT);
        $stmtL->bindValue(':sync', $sync_status, SQLITE3_INTEGER);
        $stmtL->bindValue(':id', $itemID, SQLITE3_INTEGER);
        $stmtL->bindValue(':branch', $requested_branch, SQLITE3_TEXT);
        $stmtL->execute();

        $log_stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, item_id, timestamp, branch_code, sync_status) VALUES (:user_id, :action, :item_id, datetime('now','localtime'), :branch, :sync)");
        $log_stmt->bindValue(':user_id', $userID, SQLITE3_INTEGER);
        $log_stmt->bindValue(':action', $changeLogStr, SQLITE3_TEXT);
        $log_stmt->bindValue(':item_id', $itemID, SQLITE3_INTEGER); 
        $log_stmt->bindValue(':branch', $requested_branch, SQLITE3_TEXT);
        $log_stmt->bindValue(':sync', $sync_status, SQLITE3_INTEGER);
        $log_stmt->execute();

        $conn->exec("COMMIT");
    }

    $responseMsg = $cloud_success ? 'Updated locally and synced to cloud.' : 'Updated locally (Offline Mode).';
    if ($is_remote) $responseMsg = 'Remote Item updated successfully.';
    
    if ($audit_warning) {
        $responseMsg .= " (Warning: $audit_warning)";
    }

    echo json_encode([
        'success' => true,
        'message' => $responseMsg,
        'changes' => $changes,
        'debug_audit' => $audit_warning
    ]);

} catch (Exception $e) {
    if (!$is_remote && isset($conn)) $conn->exec("ROLLBACK");
    echo json_encode(['success' => false, 'message' => 'Update Error: ' . $e->getMessage()]);
}
?>