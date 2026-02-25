<?php
// serve_updates.php (Hostinger Cloud)
// VERSION: ADDED TRANSACTION SYNC SUPPORT
header("Content-Type: application/json");
error_reporting(0); 

$SECRET_KEY = "JEMERALD_SECURE_2025"; 

try {
    $inputJSON = file_get_contents("php://input");
    $input = json_decode($inputJSON, true);

    if (!isset($input['key']) || $input['key'] !== $SECRET_KEY) throw new Exception("Invalid API Key");

    $host = "localhost"; $db_name = "u106033383_jemerald_cloud";
    $user = "u106033383_jemerald1234"; $pass = "Wearelive_1234";
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $user, $pass);

    $requesting_branch = $input['branch_code'] ?? 'UNKNOWN';
    $last_change_id    = isset($input['last_change_id']) ? (int)$input['last_change_id'] : 0;
    $max_change_id     = $last_change_id;

 // [NEW] HEARTBEAT UPDATE
    // Ensure the branch appears 'Online' even if it is only PULLING data (not pushing)
    if (!empty($requesting_branch) && $requesting_branch !== 'UNKNOWN') {
        // [FIX] Added DATE_ADD(..., INTERVAL 1 HOUR) to match Nigeria Time (WAT)
        $hbStmt = $pdo->prepare("UPDATE branches SET last_active_at = DATE_ADD(NOW(), INTERVAL 1 HOUR), status = 'online' WHERE branch_code = ?");
        $hbStmt->execute([$requesting_branch]);
    }

    // Fetch Changes
    $sqlChanges = "SELECT * FROM cloud_change_log WHERE change_id > ? ORDER BY change_id ASC LIMIT 500";
    $stmt = $pdo->prepare($sqlChanges);
    $stmt->execute([$last_change_id]);
    
$data = [
        'items' => [], 
        'categories' => [], 
        'transactions' => [],
        'audit_logs' => [],     // Explicitly define this
        'deleted_records' => [] // [FIX] New Container for Deletions
    ];

while ($change = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($change['change_id'] > $max_change_id) $max_change_id = $change['change_id'];
        
        $table = $change['table_name'];
        $rec_id = $change['record_id'];
        $log_branch = $change['branch_code'] ?? 'ALL';
        $action_type = $change['action_type'] ?? 'UPDATE'; // [FIX] Capture Action Type
        
        $is_match = ($log_branch === 'ALL' || $log_branch === $requesting_branch);

        // ------------------------------------------------------------------
        // [CRITICAL FIX] HANDLE DELETIONS (TOMBSTONING)
        // If action is DELETE, the row is gone. We must send a notification 
        // without trying to fetch the row data.
        // ------------------------------------------------------------------
        if ($action_type === 'DELETE' && $is_match) {
            $data['deleted_records'][] = [
                'table_name' => $table,
                'record_id'  => $rec_id,
                // Try to find the barcode from audit logs if possible, otherwise rely on ID
                'virtual_barcode' => null 
            ];
            continue; // Skip the rest of the loop (fetching)
        }

        // 1. ITEMS
        if ($table === 'items') {
            $itm = $pdo->query("SELECT * FROM items WHERE id = $rec_id")->fetch(PDO::FETCH_ASSOC);
            if ($itm && ($is_match || $itm['branch_code'] === $requesting_branch)) {
                $data['items'][] = $itm;
            }
        }
        // 2. CATEGORIES
        elseif ($table === 'item_categories') {
            $cat = $pdo->query("SELECT * FROM item_categories WHERE id = $rec_id")->fetch(PDO::FETCH_ASSOC);
            if ($cat) $data['categories'][] = $cat;
        }
       // 3. TRANSACTIONS (With Data Mapping)
        elseif ($table === 'transactions') {
            $txn = $pdo->query("SELECT * FROM transactions WHERE id = $rec_id")->fetch(PDO::FETCH_ASSOC);
            
            if ($txn && ($txn['branch_code'] === $requesting_branch)) {
                
                // [FIX] Attach Virtual Fields for ID Resolution
                // Get Barcode
                if (!empty($txn['item_id'])) {
                    $iStmt = $pdo->query("SELECT item_unique_no FROM items WHERE id = " . intval($txn['item_id']));
                    if ($iRow = $iStmt->fetch(PDO::FETCH_ASSOC)) {
                        $txn['virtual_barcode'] = $iRow['item_unique_no'];
                    }
                }

                // Get Username
                if (!empty($txn['user_id'])) {
                    $uStmt = $pdo->query("SELECT username FROM users WHERE id = " . intval($txn['user_id'])); // Assuming Cloud users has 'id'
                    if ($uRow = $uStmt->fetch(PDO::FETCH_ASSOC)) {
                        $txn['virtual_username'] = $uRow['username'];
                    }
                }

                $data['transactions'][] = $txn;
            }
        }

 // 4. AUDIT LOGS (Remote Actions Only) with BARCODE LOOKUP
        elseif ($table === 'audit_logs') {
            $log = $pdo->query("SELECT * FROM audit_logs WHERE id = $rec_id")->fetch(PDO::FETCH_ASSOC);
            
            if ($log && $log['branch_code'] === $requesting_branch && (int)$log['local_id'] < 0) {
                // [CRITICAL FIX] Fetch the Item Unique No (Barcode) for this log
                // This acts as the "Universal Link" between Cloud ID and Local ID
                $virtual_barcode = null;
                if (!empty($log['item_id'])) {
                    $itmStmt = $pdo->query("SELECT item_unique_no FROM items WHERE id = " . intval($log['item_id']));
                    $itmRow = $itmStmt->fetch(PDO::FETCH_ASSOC);
                    if ($itmRow) {
                        $virtual_barcode = $itmRow['item_unique_no'];
                    }
                }
                
                // Attach the barcode to the log object (Virtual Field)
                $log['virtual_barcode'] = $virtual_barcode;
                
                $data['audit_logs'][] = $log;
            }
        }
    }

    echo json_encode([
        "status" => "success",
        "cursors" => [
            "last_change_id" => $max_change_id
        ],
        "data" => $data
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>