<?php
// Include the database connection
include('connection.php');

// Initialize variables to store the data
$totalItems = 0;
$totalValue = 0.00;

// Get the total number of items in stock
$query = "SELECT COUNT(*) AS total_items FROM items";
$result = $conn->query($query);

if ($result) {
    $row = $result->fetchArray(SQLITE3_ASSOC);
    if ($row) {
        $totalItems = $row['total_items'];
    }
}

// Get the total value of the inventory
$query = "SELECT SUM(quantity_in_stock * purchase_price) AS total_value FROM items";
$result = $conn->query($query);

if ($result) {
    $row = $result->fetchArray(SQLITE3_ASSOC);
    if ($row) {
        $totalValue = $row['total_value'];
    }
}

// Close the database connection
$conn->close();

// Return the data as JSON
echo json_encode(array(
    'totalItems' => $totalItems,
    'totalValue' => $totalValue
));
?>
