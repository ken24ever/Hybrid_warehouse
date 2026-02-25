<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Include the database connection
include('connection.php');

// Get the selected transaction IDs from the POST request
$transactionIds = isset($_POST['transactionIds']) ? $_POST['transactionIds'] : [];

if (empty($transactionIds)) {
    echo json_encode(['error' => 'No transaction IDs provided']);
    exit;
}

// Ensure IDs are properly formatted for SQLite's IN clause
$placeholders = implode(',', array_fill(0, count($transactionIds), '?'));

// Fetch the selected transactions from the database
$query = "SELECT t.transaction_id, t.transaction_date, t.profit_loss, t.item_id, 
                 i.item_name, i.item_description, i.purchase_price, i.wholesale, 
                 i.retail, i.quantity_in_stock, t.transaction_type, t.quantity, 
                 t.sold_at, t.total_amount
          FROM transactions t
          JOIN items i ON t.item_id = i.item_id
          WHERE t.transaction_id IN ($placeholders)";

$stmt = $conn->prepare($query);

// Bind the parameters dynamically
foreach ($transactionIds as $index => $id) {
    $stmt->bindValue($index + 1, $id, SQLITE3_INTEGER);
}

// Execute the query
$result = $stmt->execute();

// Create a new Spreadsheet object
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set the column headers
$headers = [
    'A1' => 'Transaction ID', 'B1' => 'Transaction Date', 'C1' => 'Profit/Loss', 
    'D1' => 'Item ID', 'E1' => 'Item Name', 'F1' => 'Item Description', 
    'G1' => 'Purchase Price', 'H1' => 'Sold At', 'I1' => 'Quantity in Stock', 
    'J1' => 'Transaction Type', 'K1' => 'Quantity', 'L1' => 'Total Amount'
];

foreach ($headers as $cell => $header) {
    $sheet->setCellValue($cell, $header);
}

// Populate the rows with transaction data
$row = 2;
while ($row_data = $result->fetchArray(SQLITE3_ASSOC)) {
    $sheet->setCellValue('A' . $row, $row_data['transaction_id']);
    $sheet->setCellValue('B' . $row, $row_data['transaction_date']);
    $sheet->setCellValue('C' . $row, $row_data['profit_loss']);
    $sheet->setCellValue('D' . $row, $row_data['item_id']);
    $sheet->setCellValue('E' . $row, $row_data['item_name']);
    $sheet->setCellValue('F' . $row, $row_data['item_description']);
    $sheet->setCellValue('G' . $row, $row_data['purchase_price']);
    $sheet->setCellValue('H' . $row, $row_data['sold_at']);
    $sheet->setCellValue('I' . $row, $row_data['quantity_in_stock']);
    $sheet->setCellValue('J' . $row, $row_data['transaction_type']);
    $sheet->setCellValue('K' . $row, $row_data['quantity']);
    $sheet->setCellValue('L' . $row, $row_data['total_amount']);
    $row++;
}

// Create a new instance of the Xlsx Writer
$writer = new Xlsx($spreadsheet);

// Ensure the directory exists
$dirPath = 'excelFiles/';
if (!file_exists($dirPath)) {
    mkdir($dirPath, 0777, true);
}

// Save the spreadsheet to a file
$filePath = $dirPath . 'transactions.xlsx';
$writer->save($filePath);

// Create the JSON response with the file URL
$response = array('fileUrl' => $filePath);

// Send the JSON response
header('Content-Type: application/json');
echo json_encode($response);

// Close the database connection
$conn->close();
?>
