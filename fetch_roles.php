<?php
session_start(); // Ensure session is started

// Check if the user is logged in and has a role in session
if (isset($_SESSION['role'])) {
    $user_role = $_SESSION['role'];

    // Include the database connection
    include('connection.php');

    // Define the roles to show based on the user role
    if ($user_role === 'Super Admin') {
        // Super Admin sees all roles
        $query = "SELECT * FROM roles";
    } else {
        // Other roles only see Admin and Sales Manager
        $query = "SELECT * FROM roles WHERE role_name IN ('Admin', 'Sales Manager')";
    }

    // Retrieve role data from the database
    $result = $conn->query($query);

    // Fetch the role records
    $roles = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $roles[] = $row;
    }

    // Send the role data as JSON response
    header('Content-Type: application/json');
    echo json_encode($roles);
    exit();
} else {
    // If the session is not valid or user is not logged in, redirect to 404 or login page
    header("Location:404.php");
    exit(); // Stop further script execution
}
?>
