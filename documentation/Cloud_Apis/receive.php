<?php
// receive.php (Hostinger Cloud)
// VERSION: FIXED COLUMN MISMATCH (Removes sync_status)
header("Content-Type: application/json");
error_reporting(0); 

$SECRET_KEY = "JEMERALD_SECURE_2025";
$inputJSON = file_get_contents("php://input");
$input = json_decode($inputJSON, true);

if (!isset($input['key']) || $input['key'] !== $SECRET_KEY) {
    http_response_code(403); echo json_encode(["status" => "error", "message" => "Invalid API Key"]); exit;
}

$host = "localhost"; 
$db_name = "u106033383_jemerald_cloud";
$user = "u106033383_jemerald1234";
$pass = "Wearelive_1234";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500); echo json_encode(["status" => "error", "message" => "Cloud DB Connection Failed"]); exit;
}

$payload = $input['payload'] ?? [];
$branch_code = $input['branch_code'] ?? 'UNKNOWN';

$stats = ['inserted' => 0, 'updated' => 0, 'errors' => 0];
$processed_ids = [];
$first_error_msg = null;

$pdo->beginTransaction();

try {
    foreach ($payload as $table => $rows) {
        $processed_ids[$table] = [];
        
        foreach ($rows as $row) {
            try {
                $local_pk_value = $row['local_id'] ?? null;
                
                // --- [CRITICAL FIX] REMOVE LOCAL-ONLY COLUMNS ---
                // These columns exist in SQLite but NOT in MySQL Cloud
                unset($row['rowid']);        // SQLite internal ID
                unset($row['sync_status']);  // Local sync flag
                // ------------------------------------------------
                // [FIX] Cloud 'users' table does not have 'role_id, 'trial_start_date', 'created_by_user_id', 'origin_branch', 'uuid'.
                // We remove it to prevent "Unknown column" SQL errors.
                if ($table === 'users') {
                    unset($row['role_id']); 
                     unset($row['trial_start_date']); 
                      unset($row['created_by_user_id']); 
                      unset($row['origin_branch']); 
                      unset($row['uuid']);
                }

                if ($table === 'users') {
                    // [NEW] MAP ROLE ID (Local) -> ROLE NAME (Cloud)
                    // Since Cloud DB doesn't have role_id, we translate it here.
                    if (isset($row['role_id'])) {
                        switch ((int)$row['role_id']) {
                            case 1: $row['role_name'] = 'Super Admin'; break; // The Cloud User
                            case 2: $row['role_name'] = 'Admin Manager'; break;
                            case 3: $row['role_name'] = 'Sale Manager'; break;
                            default: $row['role_name'] = 'User';
                        }
                    }
                    
                    // Now safe to remove the columns Cloud doesn't support
                    unset($row['role_id']); 
                    unset($row['trial_start_date']); 
                    unset($row['created_by_user_id']); 
                    unset($row['origin_branch']); 
                    unset($row['uuid']);
                }

                // [CRITICAL FIX] TRANSLATE LOCAL IDs -> CLOUD IDs
                if ($table === 'transactions') {

                    // [NEW] FORCE REMOVE LOCAL-ONLY COLUMNS (Prevents Error 1054)
                    unset($row['modified_adjustment_time']);
                    unset($row['modified_purchase_time']);
                    unset($row['uuid']);
                    
                    // 1. Map Item ID (Using Barcode)
                    // We assume the Local DB sent the raw local_id. We need the Cloud ID.
                    // Note: This requires the Local DB to ideally send the barcode, 
                    // but if not, we do a reverse lookup on the Cloud 'items' table using the local_id + branch.
                    $local_item_id = $row['item_id'] ?? 0;
                    if ($local_item_id) {
                        $mapStmt = $pdo->prepare("SELECT id FROM items WHERE local_id = ? AND branch_code = ?");
                        $mapStmt->execute([$local_item_id, $branch_code]);
                        $cloudItem = $mapStmt->fetch(PDO::FETCH_ASSOC);
                        if ($cloudItem) {
                            $row['item_id'] = $cloudItem['id']; // UPDATE payload to use Cloud ID
                        }
                    }

                    // 2. Map User ID
                    // Similar logic: Map Local User ID -> Cloud User ID
                    // (Assuming users are synced and have local_id mapped in cloud)
                    $local_user_id = $row['user_id'] ?? 0;
                    if ($local_user_id) {
                         // Fallback: If we can't map, we keep it as is, but populate local_user_id column
                         $row['local_user_id'] = $local_user_id;
                         
                         // Try to find Cloud User ID (if you have a mechanism for this)
                         // For now, ensuring local_user_id is saved allows us to trace it back
                    }
                }

          
                // Ensure branch_code is set (Security & Consistency)
                if ($table !== 'branches') {
                    $row['branch_code'] = $branch_code;
                }

                // Prepare Column Names and Values
                $cols = array_keys($row);
                $vals = array_values($row);
                
                $cols_sql = implode("`, `", $cols);
                $placeholders = implode(", ", array_fill(0, count($cols), "?"));

                // Dynamic Upsert Construction
                $update_parts = [];
                foreach ($cols as $col) {
                    // Update all columns on duplicate key, except ID/Branch usually
                    $update_parts[] = "`$col` = VALUES(`$col`)";
                }
                $update_sql_clause = implode(", ", $update_parts);

                // Execute Upsert
                $sqlUpsert = "INSERT INTO `$table` (`$cols_sql`) VALUES ($placeholders) 
                              ON DUPLICATE KEY UPDATE $update_sql_clause";
                
                $stmt = $pdo->prepare($sqlUpsert);
                $stmt->execute($vals);

                $rowCount = $stmt->rowCount();
                if ($rowCount == 1) $stats['inserted']++;
                elseif ($rowCount == 2) $stats['updated']++;
                else $stats['updated']++; // No change counts as update logic

                // Logging Logic
                $record_id = 0;
                if ($table === 'items') {
                    // Fetch the actual Cloud ID using the Unique Key
                    $uNo = $row['item_unique_no'] ?? '';
                    $fetchId = $pdo->prepare("SELECT id FROM items WHERE item_unique_no = ? AND branch_code = ?");
                    $fetchId->execute([$uNo, $branch_code]);
                    $cloudIdRow = $fetchId->fetch(PDO::FETCH_ASSOC);
                    
                    // Fallback to lastInsertId if fetch failed (rare)
                    $record_id = $cloudIdRow['id'] ?? $pdo->lastInsertId();

                    $actionType = ($rowCount == 1) ? 'INSERT' : 'UPDATE';
                    $pdo->query("INSERT INTO cloud_change_log (table_name, record_id, branch_code, action_type) VALUES ('items', $record_id, '$branch_code', '$actionType')");
                }

                // Add to Ack List
                if ($local_pk_value !== null) {
                    $processed_ids[$table][] = $local_pk_value;
                }

           

            } catch (Exception $rowEx) {
                $stats['errors']++;
                if ($first_error_msg === null) $first_error_msg = "Table $table: " . $rowEx->getMessage();
            }
        }
    }

           // [NEW] HEARTBEAT UPDATE
    // Update the branch status to 'online' whenever it syncs
    if (!empty($branch_code) && $branch_code !== 'UNKNOWN') {
        // [FIX] Added DATE_ADD(..., INTERVAL 1 HOUR) to match Nigeria Time (WAT)
        $hbStmt = $pdo->prepare("UPDATE branches SET last_active_at = DATE_ADD(NOW(), INTERVAL 1 HOUR), status = 'online' WHERE branch_code = ?");
        $hbStmt->execute([$branch_code]);
    }

    $pdo->commit();
    
    echo json_encode([
        "status" => "success", 
        "message" => "Sync processed",
        "stats" => $stats,
        "acked_ids" => $processed_ids,
        "first_error" => $first_error_msg 
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500); 
    echo json_encode(["status" => "error", "message" => "Transaction Failed: " . $e->getMessage()]);
}
?>