<?php
session_start();
header('Content-Type: application/json'); // Ensure JSON response format

// Enable error reporting for debugging
/* error_reporting(E_ALL);
ini_set('display_errors', 0); */

// Initialize variables
$employeeId = 0;
$logged_in_user_id = 0;
$firstName = $lastName = $email = $phoneNumber = $department = $jobTitle = "";
$action = "";

try {
    // Include DB connection
    include('connection.php');

    // Validate session
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Unauthorized access.");
    }

    // Assign session user ID
    $logged_in_user_id = $_SESSION['user_id'];

    // Get employee ID from the request
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        $employeeId = intval($_GET['id']);
    }

    if ($employeeId <= 0) {
        throw new Exception("Invalid employee ID.");
    }

    // Retrieve employee details before deletion
    $stmt = $conn->prepare("
        SELECT First_Name, Last_Name, Email, Phone_Number, Job_Title, Department 
        FROM employees 
        WHERE Employee_ID = :id
    ");
    $stmt->bindValue(':id', $employeeId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $employee = $result->fetchArray(SQLITE3_ASSOC);

    if (!$employee) {
        throw new Exception("Employee not found.");
    }

    // Assign employee details (handling NULL values)
    $firstName = !empty($employee['First_Name']) ? $employee['First_Name'] : "[Unknown]";
    $lastName = !empty($employee['Last_Name']) ? $employee['Last_Name'] : "[Unknown]";
    $email = !empty($employee['Email']) ? $employee['Email'] : "[Unknown]";
    $phoneNumber = !empty($employee['Phone_Number']) ? $employee['Phone_Number'] : "[Unknown]";
    $jobTitle = !empty($employee['Job_Title']) ? $employee['Job_Title'] : "[Unknown]";
    $department = !empty($employee['Department']) ? $employee['Department'] : "[Unknown]";

    // Start transaction
    $conn->exec("BEGIN TRANSACTION");

    // Log the deletion in audit_logs with timestamp
    $action = "Deleted employee - Name: $firstName $lastName, Email: $email, Job Title: $jobTitle, Department: $department";
    $logStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (:user_id, :action, datetime('now', 'localtime'))");
    $logStmt->bindValue(':user_id', $logged_in_user_id, SQLITE3_INTEGER);
    $logStmt->bindValue(':action', $action, SQLITE3_TEXT);
    $logStmt->execute();

    // Proceed with deleting the employee
    $deleteStmt = $conn->prepare("DELETE FROM employees WHERE Employee_ID = :id");
    $deleteStmt->bindValue(':id', $employeeId, SQLITE3_INTEGER);
    
    if (!$deleteStmt->execute()) {
        throw new Exception("Failed to delete employee.");
    }

    // Commit transaction
    $conn->exec("COMMIT");

    // Return success response
    echo json_encode(['success' => true, 'message' => "Employee '$firstName $lastName' deleted successfully."]);

} catch (Exception $e) {
    // Rollback in case of failure
    $conn->exec("ROLLBACK");
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit();
?>
