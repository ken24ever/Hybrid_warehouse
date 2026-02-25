<?php
session_start(); // Start session to track the logged-in user

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include DB connection
include('connection.php');

// Ensure JSON response
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

// Validate and sanitize input data
$employeeId = isset($_POST['employeeId']) ? intval($_POST['employeeId']) : 0;
$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phoneNumber = trim($_POST['phone_number'] ?? '');
$department = trim($_POST['department'] ?? '');
$jobTitle = trim($_POST['job_title'] ?? '');
$status = trim($_POST['Employee_Status'] ?? '');
$logged_in_user_id = $_SESSION['user_id'] ?? 0;

// Validate required fields
if ($employeeId === 0 || empty($firstName) || empty($lastName) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit();
}

// Retrieve current employee details before update
$stmt = $conn->prepare("SELECT First_Name, Last_Name, Email, Phone_Number, Department, Job_Title, Employee_Status FROM employees WHERE Employee_ID = :id");
$stmt->bindValue(':id', $employeeId, SQLITE3_INTEGER);
$result = $stmt->execute();

// Normalize column names to lowercase to prevent case-sensitivity issues
$currentEmployee = array_change_key_case($result->fetchArray(SQLITE3_ASSOC) ?: [], CASE_LOWER);

if (empty($currentEmployee)) {
    echo json_encode(['success' => false, 'message' => 'Employee not found in database.', 'employeeId' => $employeeId]);
    exit();
}

// Track changes
$changes = [];

if ($firstName !== $currentEmployee['first_name']) {
    $changes[] = "Changed First Name from '{$currentEmployee['first_name']}' to '$firstName'";
}
if ($lastName !== $currentEmployee['last_name']) {
    $changes[] = "Changed Last Name from '{$currentEmployee['last_name']}' to '$lastName'";
}
if ($email !== $currentEmployee['email']) {
    $changes[] = "Changed Email from '{$currentEmployee['email']}' to '$email'";
}
if ($phoneNumber !== $currentEmployee['phone_number']) {
    $changes[] = "Changed Phone Number from '{$currentEmployee['phone_number']}' to '$phoneNumber'";
}
if ($department !== $currentEmployee['department']) {
    $changes[] = "Changed Department from '{$currentEmployee['department']}' to '$department'";
}
if ($jobTitle !== $currentEmployee['job_title']) {
    $changes[] = "Changed Job Title from '{$currentEmployee['job_title']}' to '$jobTitle'";
}
if ($status !== $currentEmployee['employee_status']) {
    $changes[] = "Changed Employee Status from '{$currentEmployee['employee_status']}' to '$status'";
}

// Only proceed if there are changes
if (!empty($changes)) {
    // Update employee record
    $updateStmt = $conn->prepare("
        UPDATE employees 
        SET First_Name = :first_name, 
            Last_Name = :last_name, 
            Email = :email, 
            Phone_Number = :phone_number, 
            Department = :department, 
            Job_Title = :job_title, 
            Employee_Status = :status 
        WHERE Employee_ID = :id
    ");
    
    $updateStmt->bindValue(':first_name', $firstName, SQLITE3_TEXT);
    $updateStmt->bindValue(':last_name', $lastName, SQLITE3_TEXT);
    $updateStmt->bindValue(':email', $email, SQLITE3_TEXT);
    $updateStmt->bindValue(':phone_number', $phoneNumber, SQLITE3_TEXT);
    $updateStmt->bindValue(':department', $department, SQLITE3_TEXT);
    $updateStmt->bindValue(':job_title', $jobTitle, SQLITE3_TEXT);
    $updateStmt->bindValue(':status', $status, SQLITE3_TEXT);
    $updateStmt->bindValue(':id', $employeeId, SQLITE3_INTEGER);

    if ($updateStmt->execute()) {
        // Insert each change into audit_logs
        $logStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (:user_id, :action, datetime('now', 'localtime'))");

        foreach ($changes as $change) {
            $logStmt->bindValue(':user_id', $logged_in_user_id, SQLITE3_INTEGER);
            $logStmt->bindValue(':action', $change, SQLITE3_TEXT);
            $logStmt->execute();
        }

        echo json_encode([
            'success' => true,
            'message' => 'Employee updated successfully.',
            'changes' => $changes
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update employee.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No changes detected.']);
}

exit();
?>
