<?php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include the database connection
include('connection.php');

// Get transaction group ID
$transactionGroupID = $_GET['transactionGroupID'];

// Query to fetch items for the given transaction group ID
$query = "SELECT 
             i.item_name, 
             t.transaction_date, 
             t.transaction_type, 
             t.quantity, 
             t.total_amount, 
             i.purchase_price, 
             t.profit_loss
          FROM transactions t
          JOIN items i ON t.item_id = i.item_id
          WHERE t.transaction_group_id = :transactionGroupID";

$stmt = $conn->prepare($query);
$stmt->bindValue(':transactionGroupID', $transactionGroupID, SQLITE3_INTEGER);
$result = $stmt->execute();

// Initialize response array
$items = array();

while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $items[] = $row;
}

// Send the JSON response
header('Content-Type: application/json');
echo json_encode(['items' => $items]);

// Close the database connection
$conn->close();

?>
