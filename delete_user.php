<?php
// delete_user.php
// VERSION: DUAL-WRITE + UNIQUE LOG ID FIX
session_start();
header('Content-Type: application/json');

// 1. SECURITY & INPUT VALIDATION
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit; 
}

include('connection.php'); // Local SQLite

$logged_in_user_id = $_SESSION['user_id'];
$target_id = intval($_POST['user_id']);
$current_branch = $_SESSION['branch_code'] ?? 'UNKNOWN';

// Prevent Self-Deletion
if ($target_id === $logged_in_user_id) {
    echo json_encode(['success' => false, 'message' => 'You cannot delete your own account.']);
    exit;
}

// 2. CLOUD CONNECTION SETUP
$cloud_host = 'srv1254.hstgr.io';
$cloud_user = 'u106033383_jemerald1234';
$cloud_pass = 'Wearelive_1234';
$cloud_name = 'u106033383_jemerald_cloud';

$cloud_conn = null;
$is_cloud_reachable = false;

try {
    $cloud_conn = @new mysqli($cloud_host, $cloud_user, $cloud_pass, $cloud_name);
    if ($cloud_conn && !$cloud_conn->connect_error) {
        $is_cloud_reachable = true;
    }
} catch (Exception $e) {
    $is_cloud_reachable = false;
}

// 3. IDENTIFY TARGET USER (Context Resolution)
$user_context = 'LOCAL'; // Default
$target_user_data = null;

// A. Search Local DB First
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = :id");
$stmt->bindValue(':id', $target_id, SQLITE3_INTEGER);
$res = $stmt->execute();
$local_user = $res->fetchArray(SQLITE3_ASSOC);

if ($local_user) {
    $target_user_data = $local_user;
    $target_user_data['source_id'] = $local_user['user_id']; // Local ID
    $user_context = 'LOCAL';
} else {
    // B. If not found locally, try Cloud (Remote View Scenario)
    if ($is_cloud_reachable) {
        $c_stmt = $cloud_conn->prepare("SELECT * FROM users WHERE id = ?");
        $c_stmt->bind_param("i", $target_id);
        $c_stmt->execute();
        $cloud_res = $c_stmt->get_result();
        $cloud_user = $cloud_res->fetch_assoc();

        if ($cloud_user) {
            $target_user_data = $cloud_user;
            $target_user_data['source_id'] = $cloud_user['id']; // Cloud ID
            $target_user_data['user_id'] = $cloud_user['local_id']; // Map for reference
            $user_context = 'REMOTE';
        }
    }
}

if (!$target_user_data) {
    echo json_encode(['success' => false, 'message' => 'User not found in any active context.']);
    if ($cloud_conn) $cloud_conn->close();
    exit;
}

// 4. UNIFORMITY CHECK (The "Online" Gatekeeper)
if (!$is_cloud_reachable) {
    echo json_encode(['success' => false, 'message' => 'Error: Cloud Server is unreachable. Delete failed to enforce data uniformity.']);
    exit;
}

// 5. EXECUTE DELETE (Dual-Write)
try {
    $conn->exec("BEGIN TRANSACTION"); // Start Local Transaction
    $cloud_conn->begin_transaction(); // Start Cloud Transaction

    $target_username = $target_user_data['username'];
    $target_branch   = $target_user_data['branch_code'];

    // --- STEP A: DELETE FROM CLOUD ---
    $cloud_delete_success = false;
    
    if ($user_context === 'REMOTE') {
        // Direct Cloud ID deletion
        $del_c = $cloud_conn->prepare("DELETE FROM users WHERE id = ?");
        $del_c->bind_param("i", $target_id);
        $cloud_delete_success = $del_c->execute();
    } else {
        // Local Context: Delete from cloud using mapping
        $del_c = $cloud_conn->prepare("DELETE FROM users WHERE local_id = ? AND branch_code = ?");
        $del_c->bind_param("is", $target_id, $current_branch);
        $cloud_delete_success = $del_c->execute();
    }

    if (!$cloud_delete_success) {
        throw new Exception("Failed to delete user from Cloud Server: " . $cloud_conn->error);
    }

    // --- STEP B: DELETE FROM LOCAL (If applicable) ---
    if ($user_context === 'LOCAL') {
        $del_l = $conn->prepare("DELETE FROM users WHERE user_id = :id");
        $del_l->bindValue(':id', $target_id, SQLITE3_INTEGER);
        
        if (!$del_l->execute()) {
            throw new Exception("Failed to delete user from Local Database.");
        }
    }

    // --- STEP C: AUDIT LOGGING (Dual) ---
    $log_action = "Deleted User: $target_username ($target_branch)";
    $timestamp = date('Y-m-d H:i:s');

    // 1. Insert LOCAL Log
    $log_l = $conn->prepare("INSERT INTO audit_logs (user_id, action, timestamp, branch_code, sync_status) VALUES (:u, :a, :t, :b, 1)");
    $log_l->bindValue(':u', $logged_in_user_id, SQLITE3_INTEGER);
    $log_l->bindValue(':a', $log_action, SQLITE3_TEXT);
    $log_l->bindValue(':t', $timestamp, SQLITE3_TEXT);
    $log_l->bindValue(':b', $current_branch, SQLITE3_TEXT);
    $log_l->execute();
    
    // [FIX] Capture the Local ID to prevent "Duplicate entry ... -0" error
    $new_local_log_id = $conn->lastInsertRowID(); 

    // 2. Insert CLOUD Log (With Explicit Local ID)
    $cloud_logger_id = 0; 
    if (isset($_SESSION['cloud_user_id'])) $cloud_logger_id = $_SESSION['cloud_user_id'];

    // [FIX] Added 'local_id' to query
    $log_c = $cloud_conn->prepare("INSERT INTO audit_logs (local_id, user_id, action, timestamp, branch_code, local_user_id) VALUES (?, ?, ?, ?, ?, ?)");
    // [FIX] Added integer type 'i' for local_id
    $log_c->bind_param("iisssi", $new_local_log_id, $cloud_logger_id, $log_action, $timestamp, $current_branch, $logged_in_user_id);
    $log_c->execute();

    // --- COMMIT ALL ---
    $conn->exec("COMMIT");
    $cloud_conn->commit();

    echo json_encode([
        'success' => true, 
        'message' => "User '$target_username' deleted successfully from $target_branch (Cloud & Local)."
    ]);

} catch (Exception $e) {
    // Rollback both if possible
    if (isset($conn)) $conn->exec("ROLLBACK");
    if (isset($cloud_conn)) $cloud_conn->rollback();
    
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Cleanup
if (isset($cloud_conn)) $cloud_conn->close();
if (isset($conn)) $conn->close();
?>