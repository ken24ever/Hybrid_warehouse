<?php
// Include the database connection
include('connection.php');

// Get the top selling items data
$query = "SELECT t.item_id, i.item_name, SUM(t.quantity) AS totalSold
          FROM transactions t
          JOIN items i ON t.item_id = i.item_id
          WHERE t.transaction_type = 'sale'
          GROUP BY t.item_id
          ORDER BY totalSold DESC
          LIMIT 5";
$result = $conn->query($query);

// Create an array to hold the data
$data = array();

// Loop through the result and add data to the array
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $data[] = array(
        'itemId' => $row['item_id'],
        'itemName' => $row['item_name'],
        'totalSold' => $row['totalSold']
    );
}

// Return the data as JSON
echo json_encode($data);
?>
