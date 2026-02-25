<?php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// [FIX] Start Session to access user context
session_start();

// Include the database connection
include('connection.php');

// Get the filter values from the request
$category = isset($_GET['category']) ? $_GET['category'] : '';
$start_Date = isset($_GET['start_Date']) ? $_GET['start_Date'] : '';
$end_Date = isset($_GET['end_Date']) ? $_GET['end_Date'] : '';

// [FIX] ROBUST BRANCH CONTEXT RESOLUTION
// Priority: URL Param (Super Admin) > Session (Local User) > Default
$target_branch = '';
if (isset($_GET['branch_code']) && !empty($_GET['branch_code'])) {
    $target_branch = trim($_GET['branch_code']);
} elseif (isset($_SESSION['branch_code'])) {
    $target_branch = $_SESSION['branch_code'];
} 
// Prepare the filter conditions 
$categoryCondition = '';
$dateCondition = '';
$branchCondition = '';
$params = [];

// 1. Filter by Category
if (!empty($category)) {
    $categoryCondition = ' AND t.transaction_type = ? ';
    $params[] = $category;
}

// 2. Filter by Date Range
if (!empty($start_Date) && !empty($end_Date)) {
    $dateCondition = ' AND t.transaction_date BETWEEN ? AND ?';
    $params[] = $start_Date;
    $params[] = $end_Date;
}

// 3. Filter by Branch (Context)
// We now strictly use the resolved $target_branch
if (!empty($target_branch)) {
    $branchCondition = ' AND t.branch_code = ? ';
    $params[] = $target_branch;
}

// --- SALES QUERY ---
// [FIX] Ensure 't.status = 0' is present to exclude removed items
$salesQuery = "SELECT strftime('%Y-%m', t.transaction_date) AS transaction_month, SUM(t.total_amount) AS total_sales 
               FROM transactions t
               JOIN items i ON t.item_id = i.item_id
               WHERE 1=1" . $categoryCondition . $dateCondition . $branchCondition . " AND t.status = 0
               GROUP BY transaction_month";

$salesStmt = $conn->prepare($salesQuery);

// Bind parameters dynamically for Sales
if (!empty($params)) {
    foreach ($params as $index => $param) {
        $salesStmt->bindValue($index + 1, $param, SQLITE3_TEXT);
    }
}

$salesResult = $salesStmt->execute();
$salesData = ['categories' => [], 'sales' => []];

while ($row = $salesResult->fetchArray(SQLITE3_ASSOC)) {
    $salesData['categories'][] = $row['transaction_month'];
    $salesData['sales'][] = floatval($row['total_sales']);
}

// --- INVENTORY QUERY ---
// [FIX] Ensure 't.status = 0' is applied here as well
$inventoryQuery = "SELECT strftime('%Y-%m', t.transaction_date) AS transaction_month, SUM(i.quantity_in_stock) AS total_quantity
                   FROM transactions t
                   JOIN items i ON t.item_id = i.item_id
                   WHERE t.transaction_id IN (
                     SELECT MAX(transaction_id)
                     FROM transactions
                     GROUP BY item_id, strftime('%Y-%m', transaction_date) 
                   ) AND t.status = 0";

// Append conditions (Order matches $params array logic: Cat -> Date -> Branch)
if (!empty($categoryCondition)) {
    $inventoryQuery .= $categoryCondition;
}
if (!empty($dateCondition)) {
    $inventoryQuery .= $dateCondition;
}
if (!empty($branchCondition)) {
    $inventoryQuery .= $branchCondition;
}

$inventoryQuery .= " GROUP BY transaction_month";

$inventoryStmt = $conn->prepare($inventoryQuery);

// Bind parameters dynamically for Inventory
if (!empty($params)) {
    foreach ($params as $index => $param) {
        $inventoryStmt->bindValue($index + 1, $param, SQLITE3_TEXT);
    }
}

$inventoryResult = $inventoryStmt->execute();
$inventoryData = ['categories' => [], 'quantity' => []];

while ($row = $inventoryResult->fetchArray(SQLITE3_ASSOC)) {
    $inventoryData['categories'][] = $row['transaction_month'];
    $inventoryData['quantity'][] = intval($row['total_quantity']);
}

// Prepare the JSON response
$response = [
    'salesData' => $salesData,
    'inventoryData' => $inventoryData,
    'debug_branch' => $target_branch // Optional: helps verify context in frontend console
];

// Send the JSON response
header('Content-Type: application/json');
echo json_encode($response);

// Close the database connection
$conn->close();

?>