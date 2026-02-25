<?php
// Include the database connection
include('connection.php');

try {
    // Fetch all employees
    $query = "SELECT * FROM employees";
    $result = $conn->query($query);

    $employees = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $employees[] = $row;
    }

    // Return the employee data with success status
    echo json_encode(['success' => true, 'data' => $employees]);
} catch (Exception $e) {
    // Handle errors
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>