<?php
// sync_pull.php
// VERSION: ROBUST ID MATCHING (Fixes Remote Sale Overwrite Bug)
session_start();
header('Content-Type: application/json');

ini_set('display_errors', 0);
error_reporting(E_ALL);

$API_URL = "https://jemeraldstores.com/jemerald_api/serve_updates.php"; 
$API_KEY = "JEMERALD_SECURE_2025";
$SYNC_STATE_FILE = 'last_pull_state.json';

// 1. GET BRANCH CONTEXT
$CURRENT_BRANCH_CODE = $_SESSION['branch_code'] ?? 'HEAD_OFFICE';

// 2. READ LAST SYNC CURSORS
$cursors = ['last_change_id' => 0];

if (isset($_GET['reset']) && $_GET['reset'] == 1) {
    if (file_exists($SYNC_STATE_FILE)) unlink($SYNC_STATE_FILE);
    $cursors['last_change_id'] = 0;
} 
elseif (file_exists($SYNC_STATE_FILE)) {
    $stateData = json_decode(file_get_contents($SYNC_STATE_FILE), true);
    if (isset($stateData[$CURRENT_BRANCH_CODE])) {
        $cursors = $stateData[$CURRENT_BRANCH_CODE];
    }
}

// 3. REQUEST UPDATES FROM CLOUD
$postData = json_encode([
    'key' => $API_KEY,
    'branch_code' => $CURRENT_BRANCH_CODE,
    'last_change_id' => $cursors['last_change_id']
]);

$ch = curl_init($API_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$response) {
    die(json_encode(['status' => 'error', 'message' => "Cloud unreachable (HTTP $httpCode)"]));
}

$result = json_decode($response, true);
if (!isset($result['status']) || $result['status'] !== 'success') {
    die(json_encode(['status' => 'error', 'message' => $result['message'] ?? 'Unknown API error']));
}

// 4. PROCESS UPDATES LOCALLY
$db_path = 'warehouse_v2.0.db';
try {
    $db = new PDO("sqlite:$db_path");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("PRAGMA busy_timeout = 5000;");
} catch (Exception $e) {
    die(json_encode(["status" => "error", "msg" => "Local DB Error"]));
}

$stats = [
    'items_inserted' => 0, 
    'items_updated' => 0, 
    'categories' => 0, 
    'transactions' => 0, 
    'audit_logs' => 0, 
    'items_deleted' => 0, 
    'total_processed' => 0
];

$db->beginTransaction();

// =================================================================
    // [NEW] PROCESS DELETIONS (Handle "Tombstone" Records)
    // =================================================================
    if (!empty($result['data']['deleted_records'])) {
        // We prepare to delete by Barcode (Safest Method)
        $delStmt = $db->prepare("DELETE FROM items WHERE item_unique_no = ?");
        
        foreach ($result['data']['deleted_records'] as $delRec) {
            if ($delRec['table_name'] === 'items') {
                // If serve_updates.php provides the barcode (virtual_barcode), use it.
                // This is the most reliable way to delete the correct item locally.
                if (!empty($delRec['virtual_barcode'])) {
                    $delStmt->execute([$delRec['virtual_barcode']]);
                    if ($delStmt->rowCount() > 0) {
                        $stats['items_deleted']++;
                    }
                }
            }
        }
    }
    // =================================================================

try {
    // A. Process Categories
    if (!empty($result['data']['categories'])) {
        foreach ($result['data']['categories'] as $cat) {
            $stmtCheck = $db->prepare("SELECT category_id FROM item_categories WHERE category_name = ?");
            $stmtCheck->execute([$cat['category_name']]);
            if (!$stmtCheck->fetch()) {
                $stmt = $db->prepare("INSERT INTO item_categories (category_name, branch_code, sync_status) VALUES (?, ?, 1)");
                $stmt->execute([$cat['category_name'], $cat['branch_code']]);
                $stats['categories']++;
            }
        }
    }

    // B. Process Items
    if (!empty($result['data']['items'])) {
        foreach ($result['data']['items'] as $item) {
            $expDate = !empty($item['expiration_date']) ? $item['expiration_date'] : NULL;

            $check = $db->prepare("SELECT item_id FROM items WHERE item_unique_no = ? AND branch_code = ?");
            $check->execute([$item['item_unique_no'], $CURRENT_BRANCH_CODE]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $stmt = $db->prepare("UPDATE items SET 
                    item_name=?, item_description=?, purchase_price=?, wholesale=?, retail=?, 
                    quantity_in_stock=?, category=?, expiration_date=?, sync_status=1 
                    WHERE item_id=?");
                $stmt->execute([
                    $item['item_name'], $item['item_description'], $item['purchase_price'], 
                    $item['wholesale_price'], $item['retail_price'], $item['quantity_in_stock'], 
                    $item['category_name'], $expDate, $existing['item_id']
                ]);
                $stats['items_updated']++;
            } else {
                $stmt = $db->prepare("INSERT INTO items (
                    item_unique_no, item_name, item_description, purchase_price, wholesale, retail, 
                    quantity_in_stock, category, expiration_date, branch_code, sync_status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([
                    $item['item_unique_no'], $item['item_name'], $item['item_description'], 
                    $item['purchase_price'], $item['wholesale_price'], $item['retail_price'], 
                    $item['quantity_in_stock'], $item['category_name'], $expDate, 
                    $CURRENT_BRANCH_CODE
                ]);
                $stats['items_inserted']++; 
            }
        }
    }

    // C. PROCESS TRANSACTIONS (Fixed Logic)
    if (!empty($result['data']['transactions'])) {
        foreach ($result['data']['transactions'] as $t) {
            
            $cloud_local_id = intval($t['local_id'] ?? 0);
            $group_id = $t['transaction_group_id'] ?? '';
            $cloud_item_id = $t['item_id'] ?? 0;
            $status = $t['status'] ?? 0; 

            // ------------------------------------------------------------------
            // [PROFESSIONAL FIX] Resolve Local Item ID via Barcode Mapping
            // Cloud ID often doesn't match Local ID. We must translate it to safely
            // deduct stock from the exact right local item.
            // ------------------------------------------------------------------
            $local_item_id = 0;
            if (!empty($t['virtual_barcode'])) {
                $findItem = $db->prepare("SELECT item_id FROM items WHERE item_unique_no = ? AND branch_code = ?");
                $findItem->execute([$t['virtual_barcode'], $CURRENT_BRANCH_CODE]);
                if ($r = $findItem->fetch(PDO::FETCH_ASSOC)) {
                    $local_item_id = $r['item_id'];
                }
            }
            
            // Fallback just in case barcode mapping fails
            if ($local_item_id === 0) {
                $local_item_id = $cloud_item_id; 
            }
            // ------------------------------------------------------------------

            $found_local_id = 0;

            // Attempt 1: Match by ID (Only if > 0, meaning it originated in THIS local DB)
            if ($cloud_local_id > 0) {
                $check = $db->prepare("SELECT transaction_id FROM transactions WHERE transaction_id = ?");
                $check->execute([$cloud_local_id]);
                if ($row = $check->fetch(PDO::FETCH_ASSOC)) {
                    $found_local_id = $row['transaction_id'];
                }
            }

            // Attempt 2: Fallback to Group ID + Item ID 
            if ($found_local_id === 0 && !empty($group_id)) {
                // [FIX] We now use $local_item_id here instead of the cloud's item_id
                $checkGrp = $db->prepare("SELECT transaction_id FROM transactions WHERE transaction_group_id = ? AND item_id = ? AND branch_code = ?");
                $checkGrp->execute([$group_id, $local_item_id, $CURRENT_BRANCH_CODE]);
                if ($rowGrp = $checkGrp->fetch(PDO::FETCH_ASSOC)) {
                    $found_local_id = $rowGrp['transaction_id'];
                }
            }

            if ($found_local_id > 0) {
                // UPDATE EXISTING
                $upd = $db->prepare("UPDATE transactions SET 
                                    status = ?, 
                                    profit_loss = ?, 
                                    total_amount = ?,
                                    quantity = ?, 
                                    sync_status = 1 
                                    WHERE transaction_id = ?");
                $upd->execute([
                    $status, 
                    $t['profit_loss'], 
                    $t['total_amount'], 
                    $t['quantity'], 
                    $found_local_id
                ]);
                $stats['transactions']++; 
            } else {
                // INSERT NEW (Remote Transaction synced down)
                $cols = "modeOfPayment, user_id, item_id, transaction_date, transaction_type, quantity, total_amount, sold_at, profit_loss, transaction_group_id, status, sync_status, branch_code, profit, fixed_price_at_sale";
                $vals = "?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?";
                
                $local_user_id = $t['local_user_id'] ?? 0;

                $ins = $db->prepare("INSERT OR IGNORE INTO transactions ($cols) VALUES ($vals)");
                $ins->execute([
                    $t['modeOfPayment'], $local_user_id, $local_item_id, $t['transaction_date'], 'sale', // [FIX] Insert mapped local_item_id
                    $t['quantity'], $t['total_amount'], $t['sold_at'], $t['profit_loss'], 
                    $group_id, $status, $t['branch_code'], 
                    $t['profit'], $t['fixed_price_at_sale']
                ]);

                // Fallback: Deduct local stock if this is a brand new active sale
              // [PROFESSIONAL FIX] Removed Fallback Deduction
                // The exact new stock is now synced reliably via the direct 'items' payload (Section B).
                // Calculating deductions here would result in a double-deduction.

                $stats['transactions']++;
            }
        }
    }
    // D. Process Audit Logs
    if (!empty($result['data']['audit_logs'])) {
        foreach ($result['data']['audit_logs'] as $log) {
            
            // --- CHECK FOR DELETION COMMAND ---
            if (strpos($log['action'], 'CMD:') === 0) {
                $jsonStr = substr($log['action'], 4); 
                $cmd = json_decode($jsonStr, true);
    
                if ($cmd && isset($cmd['type']) && $cmd['type'] === 'SYNC_DELETE_COMMAND') {
                    if (isset($cmd['items']) && is_array($cmd['items'])) {
                        foreach ($cmd['items'] as $delItem) {
                            if (!empty($delItem['barcode'])) {
                                $stmtDel = $db->prepare("DELETE FROM items WHERE item_unique_no = ?");
                                $stmtDel->execute([$delItem['barcode']]);
                                if ($stmtDel->rowCount() > 0) $stats['items_deleted']++;
                            }
                        }
                    }
                }
                continue; 
            }
            
            $checkLog = $db->prepare("SELECT rowid FROM audit_logs WHERE action = ? AND timestamp = ? AND branch_code = ?");
            $checkLog->execute([$log['action'], $log['timestamp'], $CURRENT_BRANCH_CODE]);

            if (!$checkLog->fetch()) {
                 $resolved_local_item_id = 0;
                 if (!empty($log['virtual_barcode'])) {
                     $findItem = $db->prepare("SELECT item_id FROM items WHERE item_unique_no = ? AND branch_code = ?");
                     $findItem->execute([$log['virtual_barcode'], $CURRENT_BRANCH_CODE]);
                     if ($r = $findItem->fetch(PDO::FETCH_ASSOC)) $resolved_local_item_id = $r['item_id'];
                 } else {
                     $resolved_local_item_id = $log['item_id'];
                 }

                 $stmtL = $db->prepare("INSERT INTO audit_logs (user_id, action, item_id, timestamp, branch_code, sync_status) VALUES (?, ?, ?, ?, ?, 1)");
                 $stmtL->execute([
                     $log['local_user_id'], $log['action'], $resolved_local_item_id, 
                     $log['timestamp'], $CURRENT_BRANCH_CODE
                 ]);
                 $stats['audit_logs']++;
            }
        }
    }

    $db->commit();

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    die(json_encode(["status" => "error", "message" => "Sync Failed: " . $e->getMessage()]));
}

$stats['total_processed'] = $stats['items_inserted'] + $stats['items_updated'] + $stats['categories'] + $stats['transactions'] + $stats['audit_logs'];

if (isset($result['cursors'])) {
    if (file_exists($SYNC_STATE_FILE)) {
        $fullState = json_decode(file_get_contents($SYNC_STATE_FILE), true);
    } else {
        $fullState = [];
    }
    $fullState[$CURRENT_BRANCH_CODE] = $result['cursors'];
    file_put_contents($SYNC_STATE_FILE, json_encode($fullState));
}

echo json_encode([
    'status' => 'success', 
    'stats' => $stats,
    'debug_cursor' => $cursors['last_change_id']
]);
?>