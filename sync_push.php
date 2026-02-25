<?php
// sync_push.php (Local)
// VERSION: FIXED SUPPLIER PK MAPPING
session_start();
header('Content-Type: application/json');

$API_URL = "https://jemeraldstores.com/jemerald_api/receive.php";  
$API_KEY = "JEMERALD_SECURE_2025";
$CURRENT_BRANCH_CODE = $_SESSION['branch_code'] ?? "HEAD_OFFICE"; 

$db_path = 'warehouse_v2.0.db';
try {
    $db = new PDO("sqlite:$db_path");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("PRAGMA busy_timeout = 5000;");
} catch (Exception $e) {
    die(json_encode(["status" => "error", "msg" => "Local DB Error: " . $e->getMessage()]));
}

// 1. DEFINE TABLES & PRIMARY KEYS
// [CRITICAL FIX]: 'suppliers' now points to 'supplier_id', matching your schema.
$tables_to_sync = [
    'items'           => 'item_id',
    'transactions'    => 'transaction_id', 
    'users'           => 'user_id',
    'item_categories' => 'category_id',
    'audit_logs'      => 'rowid', 
    'employees'       => 'Employee_ID',
    'suppliers'       => 'supplier_id' 
];

$payload = [];
$hasData = false;

function sanitizeDate($dateStr) {
    if (empty($dateStr)) return null;
    return str_replace('T', ' ', $dateStr);
}

// 2. PREPARE PAYLOAD
foreach ($tables_to_sync as $table => $pk_col) {
    try {
        $cols = "*";
        if ($pk_col === 'rowid') $cols = "*, rowid AS rowid"; 

        // Fetch unsynced records
        $stmt = @$db->prepare("SELECT $cols FROM $table WHERE sync_status = 0 LIMIT 500");
        if (!$stmt) continue; 
        
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($rows)) {
            $hasData = true;
            $mapped_rows = [];

            foreach ($rows as $row) {
                // Resolve ID
                $actual_pk_val = null;
                
                // Check if the defined PK column exists in the row
                if (isset($row[$pk_col])) {
                    $actual_pk_val = $row[$pk_col];
                } 
                // Fallback for tricky tables (like suppliers if schema varies)
                elseif ($table === 'suppliers' && isset($row['id'])) {
                    $actual_pk_val = $row['id'];
                }

                if ($actual_pk_val === null) continue; // Skip if ID not found

                // Standardize for Cloud
                $row['local_id'] = $actual_pk_val;

                // --- DATA CLEANING ---
                if ($table === 'items') {
                    if (isset($row['wholesale'])) { $row['wholesale_price'] = $row['wholesale']; unset($row['wholesale']); }
                    if (isset($row['retail']))    { $row['retail_price'] = $row['retail'];       unset($row['retail']); }
                    if (isset($row['category']))  { $row['category_name'] = $row['category'];    unset($row['category']); }
                    $row['expiration_date'] = sanitizeDate($row['expiration_date'] ?? null);
                    $row['date_purchased']  = sanitizeDate($row['date_purchased'] ?? null);
                    $row['invoice_number']  = $row['invoice_number'] ?? null;
                    $row['supplier_info']   = $row['supplier_info'] ?? null;
                }
                
                if ($table === 'transactions') {
                    if (isset($row['user_id'])) { $row['local_user_id'] = $row['user_id']; unset($row['user_id']); }
                    if (isset($row['transaction_date'])) { $row['transaction_date'] = sanitizeDate($row['transaction_date']); }
                    // [FIX] Unconditionally remove these (Don't check if isset)
                    unset($row['modified_adjustment_time']);
                    unset($row['modified_purchase_time']);
                    unset($row['uuid']); 
                }
                
                if ($table === 'audit_logs') {
                    if (isset($row['user_id'])) { $row['local_user_id'] = $row['user_id']; unset($row['user_id']); }
                    if (isset($row['timestamp'])) { $row['timestamp'] = sanitizeDate($row['timestamp']); }
                }

                // [FIX] Clean Suppliers Date
                if ($table === 'suppliers') {
                    if (isset($row['date_added'])) {
                        $row['date_added'] = sanitizeDate($row['date_added']);
                    }
                     unset($row['supplier_id']); 
                }

                $mapped_rows[] = $row;
            }

            if (!empty($mapped_rows)) {
                $payload[$table] = $mapped_rows;
            }
        }
    } catch (Exception $e) { continue; }
}

if (!$hasData) {
    echo json_encode(["status" => "success", "msg" => "Nothing to sync", "synced_counts" => []]);
    exit;
}

// 3. SEND TO CLOUD
$ch = curl_init($API_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'key' => $API_KEY,
    'branch_code' => $CURRENT_BRANCH_CODE,
    'payload' => $payload
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    die(json_encode(["status" => "error", "msg" => "Curl Error: " . curl_error($ch)]));
}

$result = json_decode($response, true);

// 4. PROCESS CONFIRMATIONS
if ($http_code == 200 && isset($result['status']) && $result['status'] === 'success') {
    
    $acked_ids = $result['acked_ids'] ?? [];
    $cloud_warnings = $result['first_error'] ?? null;
    
    $db->beginTransaction();
    $total_marked = 0;
    $real_updates = 0;

    try {
        foreach ($acked_ids as $table => $ids) {
            if (empty($ids)) continue;

            // Determine PK Column securely
            $pk_name = 'id';
            if (isset($tables_to_sync[$table])) {
                $pk_name = ($tables_to_sync[$table] === 'rowid') ? 'rowid' : $tables_to_sync[$table];
            } 
            // Fallback for safety
            elseif ($table === 'suppliers') {
                $pk_name = 'supplier_id';
            }

            $clean_ids = array_map('intval', $ids);
            $inQuery = implode(',', array_fill(0, count($clean_ids), '?'));
            
            $sql = "UPDATE $table SET sync_status = 1 WHERE $pk_name IN ($inQuery)";
            $stmt = $db->prepare($sql);
            $stmt->execute($clean_ids);
            
            $total_marked += count($clean_ids);
            $real_updates += $stmt->rowCount(); 
        }
        $db->commit();
        
        $msg = "Synced successfully ($real_updates items updated locally)";
        if ($cloud_warnings && $real_updates == 0) {
            $msg .= " - Warning: Cloud rejected items. Reason: $cloud_warnings";
        }
        
        echo json_encode([
            "status" => "success", 
            "msg" => $msg, 
            "synced_counts" => array_map('count', $acked_ids) 
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(["status" => "error", "msg" => "Local Ack Failed: " . $e->getMessage()]);
    }
} else {
    $cloudMsg = $result['message'] ?? $response;
    echo json_encode([
        "status" => "error", 
        "msg" => "Cloud Error: " . $cloudMsg 
    ]);
}
?>