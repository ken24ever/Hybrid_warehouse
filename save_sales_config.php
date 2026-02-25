<?php
// save_sales_config.php

require_once 'connection.php';  // Include your database connection file
session_start(); // Start the session to track the logged-in user

header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize inputs
    $price_type = $_POST['price_type'] ?? 'Retail';
    $receipt_footer = trim($_POST['receipt_footer'] ?? '');
    $receipt_disclaimer = trim($_POST['receipt_disclaimer'] ?? '');

    try {
        // Check if settings record exists and if the business_name field is populated
        $checkQuery = "SELECT business_name FROM settings WHERE setting_id = 1 LIMIT 1";
        $result = $conn->query($checkQuery);
        $row = $result->fetchArray(SQLITE3_ASSOC);

        if (!$row) {
            // No record exists, create an initial entry with a placeholder business name
            $defaultBusinessName = "Your Business Name"; // Change this to your default name
            $insertStmt = $conn->prepare("INSERT INTO settings (setting_id, business_name, price_type, receipt_footer, receipt_disclaimer) VALUES (1, ?, ?, ?, ?)");
            $insertStmt->bindParam(1, $defaultBusinessName, SQLITE3_TEXT);
            $insertStmt->bindParam(2, $price_type, SQLITE3_TEXT);
            $insertStmt->bindParam(3, $receipt_footer, SQLITE3_TEXT);
            $insertStmt->bindParam(4, $receipt_disclaimer, SQLITE3_TEXT);
            $insertStmt->execute();
        } elseif (empty($row['business_name'])) {
            // Business name exists but is empty
            echo json_encode(['status' => 'error', 'message' => "You must first create a business name before you can save/edit/update this setting."]);
            exit;
        }

        // Now update the settings safely
        $stmt = $conn->prepare("
            UPDATE settings 
            SET price_type = ?, receipt_footer = ?, receipt_disclaimer = ? 
            WHERE setting_id = 1
        ");
        $stmt->bindParam(1, $price_type, SQLITE3_TEXT);
        $stmt->bindParam(2, $receipt_footer, SQLITE3_TEXT);
        $stmt->bindParam(3, $receipt_disclaimer, SQLITE3_TEXT);

        if ($stmt->execute()) {
            // Get the logged-in user's ID from the session
            $logged_in_user_id = $_SESSION['user_id'] ?? 0;

            // Fetch the username and role name of the logged-in user
            $userQuery = "SELECT u.username, r.role_name FROM users u LEFT JOIN roles r ON u.role_id = r.role_id WHERE u.user_id = ?";
            $userStmt = $conn->prepare($userQuery);
            $userStmt->bindParam(1, $logged_in_user_id, SQLITE3_INTEGER);
            $userResult = $userStmt->execute();
            $user_row = $userResult->fetchArray(SQLITE3_ASSOC);

            $username = $user_row['username'] ?? '';
            $role_name = $user_row['role_name'] ?? '';

            // Log the action in the audit_logs table
            $action = "Updated sales configuration - Price Type: $price_type, Receipt Footer: $receipt_footer, Receipt Disclaimer: $receipt_disclaimer";

            $logStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (?, ?, datetime('now', 'localtime'))");
            $logStmt->bindParam(1, $logged_in_user_id, SQLITE3_INTEGER);
            $logStmt->bindParam(2, $action, SQLITE3_TEXT);
            $logStmt->execute();

            // Return success response
            $response = ['status' => 'success', 'message' => 'Sales configuration updated successfully.'];
        } else {
            $response['message'] = "Database error: " . $conn->lastErrorMsg();
        }
    } catch (Exception $e) {
        $response['message'] = "Exception: " . $e->getMessage();
    }
}

echo json_encode($response);
$conn->close();
?>
