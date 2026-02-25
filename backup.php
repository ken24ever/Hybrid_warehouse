<?php
include('connection.php');
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
 
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Super Admin') {
    die(json_encode(["success" => false, "message" => "Unauthorized access."]));
}

// 🔹 Set Backup File Path
$backupDir = __DIR__ . "/documentation/backups/";
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0777, true);
}
$backupFile = $backupDir . "backup.xlsx";

// 🔹 Load existing file or create a new one
if (file_exists($backupFile)) {
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($backupFile);
} else {
    $spreadsheet = new Spreadsheet();
}

// 🔹 Remove all sheets to avoid duplicates
while ($spreadsheet->getSheetCount() > 0) {
    $spreadsheet->removeSheetByIndex(0);
}

////////////////////////////////////
// 🔸 1. Backup Transactions Table
////////////////////////////////////
$transactionsSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, "Transactions");
$spreadsheet->addSheet($transactionsSheet, 0);
$spreadsheet->setActiveSheetIndex(0);

$transactionHeaders = [
    'Transaction ID', 'Payment Mode', 'User ID', 'Item ID', 'Transaction Date',
    'Transaction Type', 'Quantity', 'Total Amount', 'Sold At', 'Profit/Loss',
    'Adjustment Time', 'Purchase Time', 'Transaction Group ID', 'Status'
];
$transactionsSheet->fromArray([$transactionHeaders], NULL, 'A1');

// Fetch Transactions
$transactionQuery = "SELECT transaction_id, modeOfPayment, user_id, item_id, transaction_date, transaction_type, quantity, total_amount, sold_at, profit_loss, modified_adjustment_time, modified_purchase_time, transaction_group_id, status FROM transactions";
$transactionResult = $conn->query($transactionQuery);
$rowIndex = 2;
$transactionCount = 0;
while ($row = $transactionResult->fetchArray(SQLITE3_ASSOC)) {
    $transactionsSheet->fromArray(array_values($row), NULL, "A$rowIndex");
    $rowIndex++;
    $transactionCount++;
}

///////////////////////////////
// 🔸 2. Backup Items Table
///////////////////////////////
$itemsSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, "Items");
$spreadsheet->addSheet($itemsSheet, 1);
$spreadsheet->setActiveSheetIndex(1);

// FULL item schema headers
$itemHeaders = [
    'Item ID', 'Status', 'Unique No.', 'Item Name', 'Description',
    'Purchase Price', 'Wholesale Price', 'Retail Price', 'Category',
    'Quantity in Stock', 'Expiration Date', 'Supplier Info',
    'Invoice Number', 'Date Purchased'
];
$itemsSheet->fromArray([$itemHeaders], NULL, 'A1');

// Fetch full items
$itemQuery = "SELECT item_id, status, item_unique_no, item_name, item_description, purchase_price, wholesale, retail, category, quantity_in_stock, expiration_date, supplier_info, invoice_number, date_purchased FROM items";
$itemResult = $conn->query($itemQuery);
$rowIndex = 2;
$itemCount = 0;
while ($row = $itemResult->fetchArray(SQLITE3_ASSOC)) {
    $itemsSheet->fromArray(array_values($row), NULL, "A$rowIndex");
    $rowIndex++;
    $itemCount++;
}

// 🔹 Save File
$writer = new Xlsx($spreadsheet);
$writer->save($backupFile);

// 🔹 Return response
echo json_encode([
    "success" => true,
    "message" => "Backup completed successfully!",
    "transactions_backed_up" => $transactionCount,
    "items_backed_up" => $itemCount,
    "file" => "documentation/backups/backup.xlsx"
]);
?>
