<?php
require 'connection.php'; // Ensure database connection is established

// Fetch roles
$rolesQuery = "SELECT role_id, role_name FROM roles";
$rolesResult = $conn->query($rolesQuery);
$roles = [];

while ($row = $rolesResult->fetchArray(SQLITE3_ASSOC)) {
    $roles[] = [
        'id' => $row['role_id'],
        'name' => $row['role_name']
    ];
}

// Fetch users
$usersQuery = "SELECT user_id, username FROM users";
$usersResult = $conn->query($usersQuery);
$users = [];

while ($row = $usersResult->fetchArray(SQLITE3_ASSOC)) {
    $users[] = [
        'id' => $row['user_id'],
        'name' => $row['username']
    ];
}

// Return proper JSON response
echo json_encode([
    'status' => 'success',
    'roles' => $roles,
    'users' => $users
]);

$conn->close();
?>
