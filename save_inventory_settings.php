<?php
require_once 'connection.php'; // Include database connection
session_start(); // Start session to track logged-in user

header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Retrieve and sanitize inputs
        $low_stock_threshold = isset($_POST['low_stock_threshold']) ? intval($_POST['low_stock_threshold']) : 0;
        $enable_low_stock_alert = isset($_POST['enable_low_stock_alert']) ? intval($_POST['enable_low_stock_alert']) : 0;
         // ADD THIS LINE
        $allow_expired_items_sale = isset($_POST['allow_expired_items_sale']) ? intval($_POST['allow_expired_items_sale']) : 0;

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
            // MODIFIED PREPARED STATEMENT
            $stmt = $conn->prepare("UPDATE settings SET low_stock_threshold = ?, enable_low_stock_alert = ?, allow_expired_items_sale = ?, updated_at = datetime('now', 'localtime') WHERE setting_id = ?");
            $stmt->bindParam(1, $low_stock_threshold, SQLITE3_INTEGER);
            $stmt->bindParam(2, $enable_low_stock_alert, SQLITE3_INTEGER);
            $stmt->bindParam(3, $allow_expired_items_sale, SQLITE3_INTEGER); // New
            $stmt->bindParam(4, $row['setting_id'], SQLITE3_INTEGER);
        } else {
            // Insert new settings record
            // MODIFIED PREPARED STATEMENT
            $stmt = $conn->prepare("INSERT INTO settings (low_stock_threshold, enable_low_stock_alert, allow_expired_items_sale, created_at, updated_at) VALUES (?, ?, ?, datetime('now', 'localtime'), datetime('now', 'localtime'))");
            $stmt->bindParam(1, $low_stock_threshold, SQLITE3_INTEGER);
            $stmt->bindParam(2, $enable_low_stock_alert, SQLITE3_INTEGER);
            $stmt->bindParam(3, $allow_expired_items_sale, SQLITE3_INTEGER); // New
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

            // MODIFIED ACTION STRING
            $action = "Updated inventory settings - Low Stock Threshold: $low_stock_threshold, Low Stock Alerts: " . ($enable_low_stock_alert ? 'Enabled' : 'Disabled') . ", Allow Expired Sale: " . ($allow_expired_items_sale ? 'Allowed' : 'Disallowed');

            // Insert audit log entry
            $logStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (?, ?, datetime('now', 'localtime'))");
            $logStmt->bindParam(1, $logged_in_user_id, SQLITE3_INTEGER);
            $logStmt->bindParam(2, $action, SQLITE3_TEXT);
            $logStmt->execute();

            // Commit transaction 
            $conn->exec("COMMIT");

            // Success response
            $response = ['status' => 'success', 'message' => 'Inventory settings updated successfully.'];
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
