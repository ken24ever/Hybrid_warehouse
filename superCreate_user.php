<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the user details from the form submission
    $user = $_POST['username'];
    $pass = $_POST['password'];
    $role_id = $_POST['role'];
    $logged_in_user_id = $_SESSION['user_id']; // User performing this action

    // Include the database connection
    include('connection.php');

    try {
        // Start transaction
        $conn->exec("BEGIN TRANSACTION");

        // Hash and salt the password securely
        $hashedPassword = password_hash($pass, PASSWORD_DEFAULT);

        // Insert new user into the users table
        $stmt = $conn->prepare("INSERT INTO users (username, password, role_id) VALUES (:username, :password, :role_id)");
        $stmt->bindValue(':username', $user, SQLITE3_TEXT);
        $stmt->bindValue(':password', $hashedPassword, SQLITE3_TEXT);
        $stmt->bindValue(':role_id', $role_id, SQLITE3_INTEGER);

        if (!$stmt->execute()) {
            throw new Exception("Error creating user");
        }

        // Log the action into audit_logs with timestamp
        $logStmt = $conn->prepare("
            INSERT INTO audit_logs (user_id, action, timestamp) 
            VALUES (:user_id, :action, datetime('now', 'localtime'))
        ");
        $action = "Created new user: $user with role ID: $role_id";
        $logStmt->bindValue(':user_id', $logged_in_user_id, SQLITE3_INTEGER);
        $logStmt->bindValue(':action', $action, SQLITE3_TEXT);
        $logStmt->execute();

        // Commit transaction
        $conn->exec("COMMIT");

        $response = ['success' => true, 'message' => 'User created successfully'];
    } catch (Exception $e) {
        $conn->exec("ROLLBACK");
        $response = ['success' => false, 'message' => "Error: " . $e->getMessage()];
    }

    // Close the connection
    $conn->close();

    // Send JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>
