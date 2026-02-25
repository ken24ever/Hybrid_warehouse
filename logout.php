<?php
session_start();

// Include the database connection
include('connection.php');

if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'];

    // Capture logout action with the username and timestamp
    $action = "$username Logged-out at " . date('Y-m-d H:i:s');
    
    // Insert the log into the audit_logs table
    $logStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, sync_status, branch_code) VALUES (:user_id, :action, :sync_status, :branch_code)");
    $logStmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $logStmt->bindValue(':action', $action, SQLITE3_TEXT);
    $logStmt->bindValue(':sync_status', 0, SQLITE3_TEXT);
    $logStmt->bindValue(':branch_code', $_SESSION['branch_code'], SQLITE3_TEXT);
    $logStmt->execute();
}

// Remove all session variables
session_unset();

// Destroy the session
session_destroy(); 

// Invalidate the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Redirect to the index page
header("location:index.php");
exit();
?>
