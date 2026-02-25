<?php
// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the user ID from the form submission
    $user_id = $_POST['user_id'] ?? null;

    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
        exit();
    }

    // Include the database connection
    require_once 'connection.php';
    session_start();

    try {
        // Start transaction
        $conn->exec("BEGIN TRANSACTION");

        // Fetch the username before deleting
        $fetchStmt = $conn->prepare("SELECT username FROM users WHERE user_id = ?");
        $fetchStmt->bindParam(1, $user_id, SQLITE3_INTEGER);
        $fetchResult = $fetchStmt->execute();
        $user = $fetchResult->fetchArray(SQLITE3_ASSOC);

        if (!$user) {
            throw new Exception("User not found.");
        }

        $deletedUsername = $user['username'];

        // Delete the user
        $deleteStmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $deleteStmt->bindParam(1, $user_id, SQLITE3_INTEGER);
        $deleteResult = $deleteStmt->execute();

        if (!$deleteResult) {
            throw new Exception("Error deleting user.");
        }

        // Log the action in the audit_logs table
        $logged_in_user_id = $_SESSION['user_id'] ?? 0;
        $action = "Deleted user: $deletedUsername (ID: $user_id)";

        $logStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (?, ?, datetime('now', 'localtime'))");
        $logStmt->bindParam(1, $logged_in_user_id, SQLITE3_INTEGER);
        $logStmt->bindParam(2, $action, SQLITE3_TEXT);
        $logStmt->execute();

        // Commit transaction
        $conn->exec("COMMIT");

        // Send success response
        echo json_encode(['success' => true, 'message' => "User '$deletedUsername' deleted successfully"]);
    } catch (Exception $e) {
        $conn->exec("ROLLBACK");
        echo json_encode(['success' => false, 'message' => "Error: " . $e->getMessage()]);
    }

    $conn->close();
    exit();
}
?>
