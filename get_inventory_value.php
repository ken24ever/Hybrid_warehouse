<?php
// Include the database connection
include('connection.php');

// Define the price ranges
$priceRanges = array(
    '0-50' => 0,
    '50-100' => 0,
    '100-200' => 0,
    '200-500' => 0,
    '500+' => 0
);

// Get the inventory data
$query = "SELECT purchase_price, quantity_in_stock FROM items";
$result = $conn->query($query);

// Loop through the items and calculate the value for each price range
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $price = $row['purchase_price'];
    $quantity = $row['quantity_in_stock'];

    if ($price <= 50) {
        $priceRanges['0-50'] += $price * $quantity;
    } elseif ($price <= 100) {
        $priceRanges['50-100'] += $price * $quantity;
    } elseif ($price <= 200) {
        $priceRanges['100-200'] += $price * $quantity;
    } elseif ($price <= 500) {
        $priceRanges['200-500'] += $price * $quantity;
    } else {
        $priceRanges['500+'] += $price * $quantity;
    }
}

// Return the data as JSON
echo json_encode($priceRanges);
?>
