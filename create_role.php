<?php
// Start session to track logged-in user 
session_start();

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the role name from the form submission
    $roleName = trim($_POST['roleName']);

    // Include the database connection
    include('connection.php');

    // Ensure the user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
        echo json_encode(array('success' => false, 'message' => 'User session not found.'));
        exit;
    }

    $logged_in_user_id = $_SESSION['user_id']; // Logged-in user's ID
    $logged_in_username = $_SESSION['username']; // Logged-in user's username

    // Perform validation on the role name
    if (empty($roleName)) {
        $response = array('success' => false, 'message' => 'Role name cannot be empty');
    } else {
        // Check if role already exists
        $checkStmt = $conn->prepare("SELECT COUNT(*) AS role_count FROM roles WHERE role_name = ?");
        $checkStmt->bindParam(1, $roleName, SQLITE3_TEXT);
        $result = $checkStmt->execute();
        $roleRow = $result->fetchArray(SQLITE3_ASSOC);
        $roleCount = $roleRow['role_count'] ?? 0;

        if ($roleCount > 0) {
            $response = array('success' => false, 'message' => 'Role already exists');
        } else {
            // Prepare and execute SQL statement to insert new role
            $stmt = $conn->prepare("INSERT INTO roles (role_name) VALUES (?)");
            $stmt->bindParam(1, $roleName, SQLITE3_TEXT);
            if ($stmt->execute()) {
                $lastInsertedRoleId = $conn->lastInsertRowID();
                
                // Log the role creation action into audit_logs
                $action = "User '$logged_in_username' (ID: $logged_in_user_id) added role '$roleName' (ID: $lastInsertedRoleId) on " . date('Y-m-d H:i:s');
                $logStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?, ?)");
                $logStmt->bindParam(1, $logged_in_user_id, SQLITE3_INTEGER);
                $logStmt->bindParam(2, $action, SQLITE3_TEXT);
                $logStmt->execute();

                $response = array('success' => true, 'message' => 'Role created successfully');
            } else {
                $response = array('success' => false, 'message' => 'Error creating role');
            }
        }
    }

    // Send the JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>
