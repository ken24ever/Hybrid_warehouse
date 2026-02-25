<?php
// Enable error reporting for debugging (optional, disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Include the SQLite database connection
include('connection.php');

// Capture the Branch Context
$branch_code = isset($_GET['branch_code']) ? $_GET['branch_code'] : '';

// Start building the query
// We select users who have specific roles
$query = "SELECT username FROM users 
          WHERE role_id IN (
              SELECT role_id FROM roles 
              WHERE role_name IN ('Sales Manager', 'Super Admin', 'Admin')
          )";

// Apply Branch Filter if a specific branch is selected
if (!empty($branch_code)) {
    // Sanitize input to prevent SQL injection
    $safeBranchCode = SQLite3::escapeString($branch_code);
    
    // Append the branch condition
    // Assuming the 'users' table has a 'branch_code' column
    $query .= " AND branch_code = '$safeBranchCode'";
}

$result = $conn->query($query);

$users = array();
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $users[] = $row;
}

// Send the JSON response
header('Content-Type: application/json');
echo json_encode(array(
    'users' => $users,
    'debug_branch' => $branch_code // Helpful for verifying context
));

// Close the database connection
$conn->close();
?>