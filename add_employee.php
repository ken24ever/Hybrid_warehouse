<?php
session_start(); // Start session to track the logged-in user

header('Content-Type: application/json');

// Include DB connection
include('connection.php');

// Capture form data
$firstName = $_POST['First_Name'];
$lastName = $_POST['Last_Name'];
$email = $_POST['Email'];
$phoneNumber = $_POST['Phone_Number'];
$address = $_POST['Address'];
$dob = $_POST['Date_Of_Birth'];
$dateOfHire = $_POST['Date_Of_Hire'];
$jobTitle = $_POST['Job_Title'];
$department = $_POST['Department'];
$status = $_POST['Employee_Status'];

try {
    // Insert data into database
    $stmt = $conn->prepare("INSERT INTO employees (First_Name, Last_Name, Email, Phone_Number, Address, Date_Of_Birth, Date_Of_Hire, Job_Title, Department, Employee_Status) 
                            VALUES (:firstName, :lastName, :email, :phoneNumber, :address, :dob, :dateOfHire, :jobTitle, :department, :status)");

    $stmt->bindValue(':firstName', $firstName, SQLITE3_TEXT);
    $stmt->bindValue(':lastName', $lastName, SQLITE3_TEXT);
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $stmt->bindValue(':phoneNumber', $phoneNumber, SQLITE3_TEXT);
    $stmt->bindValue(':address', $address, SQLITE3_TEXT);
    $stmt->bindValue(':dob', $dob, SQLITE3_TEXT);
    $stmt->bindValue(':dateOfHire', $dateOfHire, SQLITE3_TEXT);
    $stmt->bindValue(':jobTitle', $jobTitle, SQLITE3_TEXT);
    $stmt->bindValue(':department', $department, SQLITE3_TEXT);
    $stmt->bindValue(':status', $status, SQLITE3_TEXT);

    if ($stmt->execute()) {
        // Log the activity in the audit logs table
        $logged_in_user_id = $_SESSION['user_id']; // Get the logged-in user's ID
        $action = "Added new employee - Name: $firstName $lastName, Job Title: $jobTitle, Department: $department";

        // Insert audit log entry with explicit timestamp
        $logStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (:user_id, :action, datetime('now', 'localtime'))");
        $logStmt->bindValue(':user_id', $logged_in_user_id, SQLITE3_INTEGER);
        $logStmt->bindValue(':action', $action, SQLITE3_TEXT);
        $logStmt->execute();

        // Return success response
        echo json_encode(['success' => true, 'message' => 'Employee added successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add employee.']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}

$conn->close();
?>
