<?php
require_once 'connection.php'; // Include database connection
session_start(); // Start session to track logged-in user

header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Retrieve and sanitize inputs
        $dark_mode = isset($_POST['dark_mode']) ? intval($_POST['dark_mode']) : 0;
        $language = isset($_POST['language']) ? trim($_POST['language']) : 'English';
        $custom_language = isset($_POST['custom_language']) ? trim($_POST['custom_language']) : '';

        // Validate session user
        if (!isset($_SESSION['user_id'])) {
            throw new Exception("Unauthorized access.");
        }
        $logged_in_user_id = $_SESSION['user_id'];

        // Start transaction
        $conn->exec("BEGIN TRANSACTION");

        // Check if a settings record exists (only one record is maintained)
        $query = "SELECT setting_id FROM settings LIMIT 1";
        $result = $conn->query($query);
        $row = $result->fetchArray(SQLITE3_ASSOC);

        if ($row) {
            // Update existing settings
            $stmt = $conn->prepare("UPDATE settings SET dark_mode = ?, language = ?, custom_language = ?, updated_at = datetime('now', 'localtime') WHERE setting_id = ?");
            $stmt->bindParam(1, $dark_mode, SQLITE3_INTEGER);
            $stmt->bindParam(2, $language, SQLITE3_TEXT);
            $stmt->bindParam(3, $custom_language, SQLITE3_TEXT);
            $stmt->bindParam(4, $row['setting_id'], SQLITE3_INTEGER);
        } else {
            // Insert new settings record
            $stmt = $conn->prepare("INSERT INTO settings (dark_mode, language, custom_language, created_at, updated_at) VALUES (?, ?, ?, datetime('now', 'localtime'), datetime('now', 'localtime'))");
            $stmt->bindParam(1, $dark_mode, SQLITE3_INTEGER);
            $stmt->bindParam(2, $language, SQLITE3_TEXT);
            $stmt->bindParam(3, $custom_language, SQLITE3_TEXT);
        }

        if ($stmt->execute()) {
            // Fetch the username and role name of the logged-in user
            $userQuery = "SELECT u.username, r.role_name FROM users u LEFT JOIN roles r ON u.role_id = r.role_id WHERE u.user_id = ?";
            $userStmt = $conn->prepare($userQuery);
            $userStmt->bindParam(1, $logged_in_user_id, SQLITE3_INTEGER);
            $userResult = $userStmt->execute();
            $user_row = $userResult->fetchArray(SQLITE3_ASSOC);

            $username = $user_row['username'] ?? 'Unknown';
            $role_name = $user_row['role_name'] ?? 'Unknown';

            // Log the action in the audit_logs table
            $action = "Updated settings - Dark Mode: $dark_mode, Language: $language, Custom Language: $custom_language";
            $logStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (?, ?, datetime('now', 'localtime'))");
            $logStmt->bindParam(1, $logged_in_user_id, SQLITE3_INTEGER);
            $logStmt->bindParam(2, $action, SQLITE3_TEXT);
            $logStmt->execute();

            // Commit transaction
            $conn->exec("COMMIT");

            // Success response
            $response = ['status' => 'success', 'message' => 'Settings updated successfully.'];
        } else {
            throw new Exception("Database error: " . $conn->lastErrorMsg());
        }
    } catch (Exception $e) {
        $conn->exec("ROLLBACK"); // Rollback in case of failure
        $response['message'] = "Error: " . $e->getMessage();
    }
}

echo json_encode($response);
$conn->close();
?>
