<?php
// add_category.php
// VERSION: BRANCH-AWARE & HYBRID SYNC SUPPORT
error_reporting(0);
ini_set('display_errors', 0);

session_start();
include('connection.php'); // $conn (SQLite)

header('Content-Type: application/json');

// --- 1. SECURITY & AUTHENTICATION ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['success' => false, 'message' => 'Invalid request method.']); exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401); echo json_encode(['success' => false, 'message' => 'Unauthorized session.']); exit;
}

// --- 2. RESOLVE CONTEXT ---
// Priority: POST (Super Admin Override) -> SESSION (Logged-in Branch) -> Default
$session_branch = $_SESSION['branch_code'] ?? 'HEAD_OFFICE';
$target_branch  = $_POST['branch_code'] ?? $session_branch;
$userID         = $_SESSION['user_id'];

// Inputs
$categoryName = trim($_POST['categoryName'] ?? '');
$categoryDesc = trim($_POST['categoryDescription'] ?? '');

if (empty($categoryName)) {
    echo json_encode(['success' => false, 'message' => 'Category Name is required.']); exit;
}

try {
    // --- 3. LOCAL DUPLICATE CHECK ---
    // Prevents creating the same category twice in the same branch
    $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM item_categories WHERE category_name = :name AND branch_code = :branch");
    $checkStmt->bindValue(':name', $categoryName, SQLITE3_TEXT);
    $checkStmt->bindValue(':branch', $target_branch, SQLITE3_TEXT);
    $resCheck = $checkStmt->execute()->fetchArray(SQLITE3_ASSOC);

    if ($resCheck['count'] > 0) {
        echo json_encode(['success' => false, 'message' => "Category '$categoryName' already exists in this branch."]); 
        exit;
    }

    // --- 4. INSERT LOCAL (Default sync_status = 0) ---
    $conn->exec("BEGIN TRANSACTION");

    $sql = "INSERT INTO item_categories (category_name, category_description, branch_code, sync_status) 
            VALUES (:name, :desc, :branch, 0)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':name', $categoryName, SQLITE3_TEXT);
    $stmt->bindValue(':desc', $categoryDesc, SQLITE3_TEXT);
    $stmt->bindValue(':branch', $target_branch, SQLITE3_TEXT);
    $stmt->execute();
    
    $local_id = $conn->lastInsertRowID();

    // Local Audit Log
    $logAction = "Added Category: $categoryName";
    $timestamp = date('Y-m-d H:i:s');
    
    // Ensure you have an audit_logs table locally
    $stmtLog = $conn->prepare("INSERT INTO audit_logs (user_id, action, timestamp, branch_code, sync_status) VALUES (:u, :a, :t, :b, 0)");
    $stmtLog->bindValue(':u', $userID, SQLITE3_INTEGER);
    $stmtLog->bindValue(':a', $logAction, SQLITE3_TEXT);
    $stmtLog->bindValue(':t', $timestamp, SQLITE3_TEXT);
    $stmtLog->bindValue(':b', $target_branch, SQLITE3_TEXT);
    $stmtLog->execute();

    $conn->exec("COMMIT");

    // --- 5. HYBRID CLOUD SYNC (Try to push immediately) ---
    $cloud_synced = false;
    
    try {
        $cloud_conn = @new mysqli('srv1254.hstgr.io', 'u106033383_jemerald1234', 'Wearelive_1234', 'u106033383_jemerald_cloud');
        
        if ($cloud_conn && !$cloud_conn->connect_error) {
            
            // Check Cloud Duplicates
            $cCheck = $cloud_conn->prepare("SELECT category_id FROM item_categories WHERE category_name = ? AND branch_code = ?");
            $cCheck->bind_param("ss", $categoryName, $target_branch);
            $cCheck->execute();
            $cCheck->store_result();

            if ($cCheck->num_rows == 0) {
                // Insert to Cloud
                // Note: We map 'local_id' to trace back to the SQLite ID
                $cSql = "INSERT INTO item_categories (category_name, category_description, branch_code, local_id) VALUES (?, ?, ?, ?)";
                $cStmt = $cloud_conn->prepare($cSql);
                $cStmt->bind_param("sssi", $categoryName, $categoryDesc, $target_branch, $local_id);
                
                if ($cStmt->execute()) {
                    // Mark Local as Synced
                    // Assuming PK is 'category_id'
                    $conn->exec("UPDATE item_categories SET sync_status = 1 WHERE category_id = $local_id");
                    
                    // Cloud Change Log (For other branches to pull down)
                    $new_cloud_id = $cloud_conn->insert_id;
                    $cloud_conn->query("INSERT INTO cloud_change_log (table_name, record_id, branch_code, action_type) VALUES ('item_categories', $new_cloud_id, '$target_branch', 'INSERT')");
                    
                    $cloud_synced = true;
                }
                $cStmt->close();
            }
            $cCheck->close();
            $cloud_conn->close();
        }
    } catch (Exception $e) {
        // Silent Fail: Application remains usable offline
        $cloud_synced = false;
    }

    // --- 6. FINAL RESPONSE ---
    echo json_encode([
        'success' => true, 
        'message' => "Category '$categoryName' added successfully" . ($cloud_synced ? " (Synced)" : " (Offline Mode)"),
        'id' => $local_id
    ]);

} catch (Exception $e) {
    if ($conn) $conn->exec("ROLLBACK");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>