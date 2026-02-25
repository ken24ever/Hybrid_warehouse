<?php
session_start();
include('connection.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get user details from the form submission
    $user_id = $_POST['user_id'];
    $new_username = $_POST['username'];
    $new_password = $_POST['password'];
    $new_role_id = $_POST['role'];
    $logged_in_user_id = $_SESSION['user_id'] ?? 0; // Logged-in user

    try {
        // Start transaction
        $conn->exec("BEGIN TRANSACTION");

        // Fetch current user details before updating
        $stmt = $conn->prepare("SELECT username, password, role_id FROM users WHERE user_id = :user_id");
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $current_user = $result->fetchArray(SQLITE3_ASSOC);

        if (!$current_user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit();
        }

        $current_username = $current_user['username'];
        $current_password = $current_user['password'];
        $current_role_id = $current_user['role_id'];

        // Track changes for audit logs
        $changes = [];
        if ($new_username !== $current_username) {
            $changes[] = "Changed username from '$current_username' to '$new_username'";
        }
        if ($new_role_id != $current_role_id) {
            $changes[] = "Changed role ID from '$current_role_id' to '$new_role_id'";
        }
        if (!password_verify($new_password, $current_password)) {
            $changes[] = "Updated password for user '$new_username'";
            $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
        } else {
            $hashedPassword = $current_password;
        }

        // Only proceed if there are changes
        if (!empty($changes)) {
            // Update user details
            $stmt = $conn->prepare("UPDATE users SET username = :username, password = :password, role_id = :role_id WHERE user_id = :user_id");
            $stmt->bindValue(':username', $new_username, SQLITE3_TEXT);
            $stmt->bindValue(':password', $hashedPassword, SQLITE3_TEXT);
            $stmt->bindValue(':role_id', $new_role_id, SQLITE3_INTEGER);
            $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);

            if ($stmt->execute()) {
                // Insert changes into audit_logs
                foreach ($changes as $change) {
                    $logStmt = $conn->prepare("
                        INSERT INTO audit_logs (user_id, action, timestamp) 
                        VALUES (:user_id, :action, datetime('now', 'localtime'))
                    ");
                    $logStmt->bindValue(':user_id', $logged_in_user_id, SQLITE3_INTEGER);
                    $logStmt->bindValue(':action', $change, SQLITE3_TEXT);
                    $logStmt->execute();
                }

                // Commit transaction
                $conn->exec("COMMIT");

                $response = ['success' => true, 'message' => 'User updated successfully', 'changes' => $changes];
            } else {
                throw new Exception("Failed to update user");
            }
        } else {
            $response = ['success' => false, 'message' => 'No changes detected'];
        }
    } catch (Exception $e) {
        $conn->exec("ROLLBACK");
        $response = ['success' => false, 'message' => "Error: " . $e->getMessage()];
    }

    // Close connection
    $conn->close();

    // Send JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>
