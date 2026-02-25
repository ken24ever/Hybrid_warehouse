<?php
// login_process.php
// VERSION: CONTEXT-AWARE RESPONSE
session_start();
include('connection.php');

header('Content-Type: application/json');

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

// 1. Fetch User Details
$query = "SELECT u.*, r.role_name 
          FROM users u
          JOIN roles r ON u.role_id = r.role_id
          WHERE u.username = :username";

$stmt = $conn->prepare($query);
$stmt->bindValue(':username', $username, SQLITE3_TEXT);
$result = $stmt->execute();

if ($row = $result->fetchArray(SQLITE3_ASSOC)) { 
    if (password_verify($password, $row['password'])) {
        
        // 2. SET SESSION
        $_SESSION['user_id']  = $row['user_id'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['role']     = $row['role_name'];
        
        // 3. RESOLVE BRANCH CONTEXT
        $branch_code = !empty($row['branch_code']) ? $row['branch_code'] : 'Unknown Branch';
        $_SESSION['branch_code'] = $branch_code;
        
        // Fetch Friendly Name
        $branch_name = $branch_code; // Fallback
        $checkTable = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='branches'");
        if ($checkTable->fetchArray()) {
            $bStmt = $conn->prepare("SELECT branch_name FROM branches WHERE branch_code = :code");
            if ($bStmt) {
                $bStmt->bindValue(':code', $branch_code, SQLITE3_TEXT);
                $bRes = $bStmt->execute();
                if ($bRow = $bRes->fetchArray(SQLITE3_ASSOC)) {
                    $branch_name = $bRow['branch_name'];
                }
            }
        }
        $_SESSION['branch_name'] = $branch_name;

        // 4. AUDIT LOG
        $action = "Logged-in at " . date('Y-m-d H:i:s');
        $logStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, timestamp, sync_status, branch_code) VALUES (:u, :a, datetime('now'), 0, :b)");
        $logStmt->bindValue(':u', $row['user_id'], SQLITE3_INTEGER);
        $logStmt->bindValue(':a', $action, SQLITE3_TEXT);
        $logStmt->bindValue(':b', $branch_code, SQLITE3_TEXT);
        $logStmt->execute();

        // 5. RETURN SUCCESS + CONTEXT
        echo json_encode([
            'success' => true,
            'role' => $row['role_name'],
            'branch_name' => $branch_name, // Send this to JS for the Toast
            'message' => "Welcome back, {$row['username']}!"
        ]);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid password']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'User not found']);
}
?>