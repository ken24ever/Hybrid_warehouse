<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

  // Include the database connection 
    include('connection.php');

// Get the category name from the POST request
$categoryName = $_POST['categoryName'];

// Sanitize the input to prevent SQL injection (use prepared statements)
$categoryName = SQLite3::escapeString($categoryName);

// Use a prepared statement
$query = $conn->prepare("SELECT COUNT(*) FROM item_categories WHERE category_name = :categoryName");
$query->bindValue(':categoryName', $categoryName, SQLITE3_TEXT);

// Execute the query
$result = $query->execute();

if (!$result) {
    // Handle query execution error
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Query execution failed: ' . $conn->lastErrorMsg()]);
    exit;
}

// Fetch the result
$row = $result->fetchArray(SQLITE3_ASSOC);
$count = $row['COUNT(*)'];

// Return a JSON response
header('Content-Type: application/json');
echo json_encode(['exists' => ($count > 0)]);

// Close the database connection
$conn->close();
?>