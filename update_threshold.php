<?php
session_start();
include('connection.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newThreshold = isset($_POST['lowStockThreshold']) ? (int)$_POST['lowStockThreshold'] : null;
    $logged_in_user_id = $_SESSION['user_id'] ?? 0; // Logged-in user

    if ($newThreshold !== null && $newThreshold > 0) {
        try {
            // Start transaction
            $conn->exec("BEGIN TRANSACTION");

            // Retrieve current threshold value before updating
            $oldThreshold = null;
            $result = $conn->query("SELECT setting_value FROM settings WHERE setting_name = 'low_stock_threshold' LIMIT 1");
            if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $oldThreshold = (int)$row['setting_value'];
            }

            // Update the threshold
            $query = "UPDATE settings SET setting_value = :newThreshold WHERE setting_name = 'low_stock_threshold'";
            $stmt = $conn->prepare($query);
            $stmt->bindValue(':newThreshold', $newThreshold, SQLITE3_INTEGER);

            if ($stmt->execute()) {
                // Log the action in audit_logs table
                $logAction = "Updated low stock threshold from $oldThreshold to $newThreshold";
                $logStmt = $conn->prepare("
                    INSERT INTO audit_logs (user_id, action, timestamp) 
                    VALUES (:user_id, :action, datetime('now', 'localtime'))
                ");
                $logStmt->bindValue(':user_id', $logged_in_user_id, SQLITE3_INTEGER);
                $logStmt->bindValue(':action', $logAction, SQLITE3_TEXT);
                $logStmt->execute();

                // Commit transaction
                $conn->exec("COMMIT");

                echo json_encode(['success' => true, 'message' => 'Threshold updated successfully.']);
            } else {
                throw new Exception("Failed to update threshold.");
            }
        } catch (Exception $e) {
            $conn->exec("ROLLBACK");
            echo json_encode(['success' => false, 'message' => "Error: " . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid threshold value.']);
    }
}

$conn->close();
?>
