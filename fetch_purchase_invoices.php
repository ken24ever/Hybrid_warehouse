<?php
// 1. Database Connection
include('connection.php');

// 2. Determine Action (Summary vs Details)
$action = isset($_GET['action']) ? $_GET['action'] : 'summary';
$invoice_number = isset($_GET['invoice_number']) ? $_GET['invoice_number'] : '';

if ($action === 'details' && !empty($invoice_number)) {
    // --- DETAILS MODE: Fetch specific items for one invoice ---
    $sql = "SELECT
                invoice_number,
                date_purchased,
                supplier_info,
                item_name,
                item_description,
                quantity_in_stock,
                purchase_price,
                (quantity_in_stock * purchase_price) as total_line_cost
            FROM items
            WHERE invoice_number = :invoice_number
            ORDER BY item_name";
            
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':invoice_number', $invoice_number, SQLITE3_TEXT);

} else {
    // --- SUMMARY MODE: Group by Invoice Number ---
    // Calculates the sum total of purchase price * quantity for the whole invoice
    $sql = "SELECT
                invoice_number,
                date_purchased,
                supplier_info,
                COUNT(*) as item_count,
                SUM(purchase_price * quantity_in_stock) as invoice_total
            FROM items
            GROUP BY invoice_number
            ORDER BY date_purchased DESC";
            
    $stmt = $conn->prepare($sql);
}

if (!$stmt) {
    http_response_code(500);
    echo json_encode(array("error" => "Failed to prepare statement: " . $conn->lastErrorMsg()));
    $conn->close();
    exit;
}

$result = $stmt->execute();

if (!$result) {
    http_response_code(500);
    echo json_encode(array("error" => "Query failed: " . $conn->lastErrorMsg()));
    $conn->close();
    exit;
}

// 3. Format Data as JSON
$data = array();
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    // Format numbers for display if needed, or handle in JS
    if(isset($row['invoice_total'])) {
        $row['invoice_total'] = number_format($row['invoice_total'], 2, '.', '');
    }
    $data[] = $row;
}

// 4. Set Content Type and Echo JSON
header('Content-Type: application/json; charset=UTF-8');
echo json_encode(array("data" => $data));

// 5. Close Connection
$conn->close();
?>