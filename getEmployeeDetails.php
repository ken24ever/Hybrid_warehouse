<?php
// Include the database connection
include('connection.php');

if (isset($_GET['id'])) {
    $employeeId = intval($_GET['id']);

    // Prepare and execute the query
    $stmt = $conn->prepare("SELECT * FROM employees WHERE Employee_ID = :id");
    $stmt->bindValue(':id', $employeeId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    // Fetch the employee details
    $employee = $result->fetchArray(SQLITE3_ASSOC);

    if ($employee) {
        echo json_encode(['success' => true, 'data' => $employee]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Employee not found.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
}
?>
