<?php
include('connection.php');
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

function sanitizeInt($value, $default = 0) {
    return is_numeric($value) ? (int)$value : $default; 
}

function sanitizeFloat($value, $default = 0.00) {
    return is_numeric($value) ? (float)$value : $default;
}

function sanitizeText($value, $default = null) {
    return isset($value) && trim($value) !== '' ? $value : $default;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['backupFile'])) {
    try {
        $file = $_FILES['backupFile']['tmp_name'];
        $spreadsheet = IOFactory::load($file);

        $conn->exec("PRAGMA foreign_keys = OFF;");
        $conn->exec("BEGIN TRANSACTION");

        // 🔹 Restore Transactions Table
        $transactionsSheet = $spreadsheet->getSheetByName('Transactions');
        $transactionsRestored = 0;

        if ($transactionsSheet) {
            $transactionsData = $transactionsSheet->toArray();
            if (count($transactionsData) > 1) {
                array_shift($transactionsData); // Remove header row
                $conn->exec("DELETE FROM transactions");

                $stmt = $conn->prepare("INSERT INTO transactions 
                    (transaction_id, modeOfPayment, user_id, item_id, transaction_date, transaction_type, 
                    quantity, total_amount, sold_at, profit_loss, modified_adjustment_time, 
                    modified_purchase_time, transaction_group_id, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                foreach ($transactionsData as $row) {
                    $stmt->bindValue(1, sanitizeInt($row[0]), SQLITE3_INTEGER); // transaction_id
                    $stmt->bindValue(2, sanitizeText($row[1]), SQLITE3_TEXT); // modeOfPayment
                    $stmt->bindValue(3, sanitizeInt($row[2], null), SQLITE3_INTEGER); // user_id
                    $stmt->bindValue(4, sanitizeInt($row[3], null), SQLITE3_INTEGER); // item_id
                    $stmt->bindValue(5, sanitizeText($row[4]), SQLITE3_TEXT); // transaction_date
                    $stmt->bindValue(6, sanitizeText($row[5]), SQLITE3_TEXT); // transaction_type
                    $stmt->bindValue(7, sanitizeInt($row[6]), SQLITE3_INTEGER); // quantity
                    $stmt->bindValue(8, sanitizeFloat($row[7]), SQLITE3_FLOAT); // total_amount
                    $stmt->bindValue(9, sanitizeInt($row[8]), SQLITE3_INTEGER); // sold_at
                    $stmt->bindValue(10, sanitizeFloat($row[9]), SQLITE3_FLOAT); // profit_loss
                    $stmt->bindValue(11, sanitizeText($row[10]), SQLITE3_TEXT); // modified_adjustment_time
                    $stmt->bindValue(12, sanitizeText($row[11]), SQLITE3_TEXT); // modified_purchase_time
                    $stmt->bindValue(13, sanitizeText($row[12]), SQLITE3_TEXT); // transaction_group_id
                    $stmt->bindValue(14, sanitizeInt($row[13]), SQLITE3_INTEGER); // status

                    $stmt->execute();
                    $transactionsRestored++;
                }
            }
        }

      
  // 🔹 Restore Items Table
$itemsSheet = $spreadsheet->getSheetByName('Items');
$itemsRestored = 0;

if ($itemsSheet) {
    $itemsData = $itemsSheet->toArray();
    if (count($itemsData) > 1) {
        array_shift($itemsData); // Remove header row
        $conn->exec("DELETE FROM items");

        $stmt = $conn->prepare("INSERT INTO items 
            (item_id, status, item_unique_no, item_name, item_description, purchase_price, 
             wholesale, retail, category, quantity_in_stock, expiration_date, 
             supplier_info, invoice_number, date_purchased) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($itemsData as $row) {
            $stmt->bindValue(1, sanitizeInt($row[0]), SQLITE3_INTEGER); // item_id
            $stmt->bindValue(2, sanitizeText($row[1], 'active'), SQLITE3_TEXT); // status
            $stmt->bindValue(3, sanitizeText($row[2]), SQLITE3_TEXT); // item_unique_no
            $stmt->bindValue(4, sanitizeText($row[3]), SQLITE3_TEXT); // item_name
            $stmt->bindValue(5, sanitizeText($row[4]), SQLITE3_TEXT); // item_description
            $stmt->bindValue(6, sanitizeFloat($row[5]), SQLITE3_FLOAT); // purchase_price
            $stmt->bindValue(7, sanitizeFloat($row[6]), SQLITE3_FLOAT); // wholesale
            $stmt->bindValue(8, sanitizeFloat($row[7]), SQLITE3_FLOAT); // retail
            $stmt->bindValue(9, sanitizeText($row[8]), SQLITE3_TEXT); // category
            $stmt->bindValue(10, sanitizeInt($row[9]), SQLITE3_INTEGER); // quantity_in_stock
            $stmt->bindValue(11, sanitizeText($row[10]), SQLITE3_TEXT); // expiration_date
            $stmt->bindValue(12, sanitizeText($row[11]), SQLITE3_TEXT); // supplier_info
            $stmt->bindValue(13, sanitizeText($row[12]), SQLITE3_TEXT); // invoice_number
            $stmt->bindValue(14, sanitizeText($row[13]), SQLITE3_TEXT); // date_purchased

            $stmt->execute();
            $itemsRestored++;
        }
    }
}


        $conn->exec("COMMIT");
        $conn->exec("PRAGMA foreign_keys = ON;");

        echo json_encode([
            "success" => true,
            "message" => "Backup restored successfully!",
            "transactions" => $transactionsRestored,
            "items" => $itemsRestored
        ]);
    } catch (Exception $e) {
        $conn->exec("ROLLBACK");
        echo json_encode(["success" => false, "message" => "Error restoring backup: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "No backup file uploaded."]);
}
?>
