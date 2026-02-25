<?php
// create_user.php
// VERSION: ROBUST INPUT (JSON/POST) + DYNAMIC BRANCH + AUDIT LOG
session_start();
header('Content-Type: application/json');

// Disable default error display to prevent HTML breaking JSON response
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    include('connection.php');
    $transactionStarted = false; 

    // --- 1. AUTHENTICATION CHECK ---
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Unauthorized access.");
    }
    $logged_in_user_id = $_SESSION['user_id'];

    // --- 2. ROBUST INPUT HANDLING (JSON vs POST) ---
    // This fixes the "NOT NULL" error by finding the data wherever it is hidden.
    $inputData = $_POST;
    if (empty($inputData)) {
        $rawInput = file_get_contents("php://input");
        $inputData = json_decode($rawInput, true);
        if (empty($inputData)) {
            parse_str($rawInput, $inputData);
        }
    }

    // --- 3. INPUT VALIDATION & SANITIZATION ---
    // We check $inputData (not $_POST) to be safe
    if (empty($inputData['username']) || empty($inputData['password']) || empty($inputData['role_id'])) {
        throw new Exception("All fields (Username, Password, Role) are required.");
    }

    $username = trim($inputData['username']);
    $password = $inputData['password'];
    $role_id  = intval($inputData['role_id']);

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    // --- 4. CAPTURE BRANCH SELECTION (Dynamic) ---
    $userRole = $_SESSION['role'] ?? '';
    $sessionBranch = $_SESSION['branch_code'] ?? '';

    if ($userRole === 'Super Admin') {
        // Use user selection, OR fallback to the admin's current active branch
        $branch_code = !empty($inputData['branch_code']) ? $inputData['branch_code'] : $sessionBranch;
    } else {
        // Strict enforcement for non-admins
        $branch_code = $sessionBranch;
    }

    if (empty($branch_code)) {
        throw new Exception("Branch context is missing. Please log out and log in again.");
    }

    // --- 5. CHECK FOR EXISTING USERNAME ---
    $checkStmt = $conn->prepare("SELECT user_id FROM users WHERE username = :username AND branch_code = :branch");
    $checkStmt->bindValue(':username', $username, SQLITE3_TEXT);
    $checkStmt->bindValue(':branch', $branch_code, SQLITE3_TEXT);
    $result = $checkStmt->execute();
    
    if ($result->fetchArray()) {
        throw new Exception("Username '$username' already exists in this branch.");
    }

    // --- 6. INSERT NEW USER ---
    $conn->exec("BEGIN TRANSACTION");
    $transactionStarted = true;

    // Note: We removed 'created_by' as it doesn't exist in the schema
    $stmt = $conn->prepare("INSERT INTO users (username, password, role_id, branch_code) VALUES (:username, :password, :role, :branch)");
    
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $stmt->bindValue(':password', $hashed_password, SQLITE3_TEXT);
    $stmt->bindValue(':role', $role_id, SQLITE3_INTEGER);
    $stmt->bindValue(':branch', $branch_code, SQLITE3_TEXT);

    if ($stmt->execute()) {
        $new_user_id = $conn->lastInsertRowID();

        // 7. 📝 LOG ACTION
        $action = "Created User - ID: $new_user_id, Username: $username, Branch: $branch_code";
        $logStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, timestamp, branch_code) VALUES (:user_id, :action, datetime('now', 'localtime'), :branch_code)");
        $logStmt->bindValue(':user_id', $logged_in_user_id, SQLITE3_INTEGER);
        $logStmt->bindValue(':action', $action, SQLITE3_TEXT);
        $logStmt->bindValue(':branch_code', $branch_code, SQLITE3_TEXT);
        $logStmt->execute();

        // 8. ✅ COMMIT
        $conn->exec("COMMIT");
        $transactionStarted = false;

        echo json_encode(['success' => true, 'message' => "User created successfully for branch: $branch_code"]);
    } else {
        // Pass the actual DB error for debugging
        throw new Exception("Database error: " . $conn->lastErrorMsg());
    }

} catch (Exception $e) {
    if (isset($conn) && isset($transactionStarted) && $transactionStarted) {
        $conn->exec("ROLLBACK");
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>