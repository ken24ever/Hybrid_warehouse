<?php
// Include the database connection
include('connection.php');

// Get the item category breakdown
$query = "SELECT category, COUNT(*) AS count FROM items GROUP BY category";
$result = $conn->query($query);

// Create an array to hold the data
$data = array();

// Loop through the result and add data to the array
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $data[] = array(
        'category' => $row['category'],
        'count' => $row['count']
    );
}

// Return the data as JSON
echo json_encode($data);
?>