<?php
require 'vendor/autoload.php'; // Load PhpSpreadsheet
require 'connection.php'; // Database connection

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Fetch audit logs
$query = "SELECT audit_logs.log_id, users.username AS performed_by, audit_logs.action, audit_logs.timestamp 
          FROM audit_logs 
          JOIN users ON audit_logs.user_id = users.user_id 
          ORDER BY audit_logs.timestamp DESC";
$result = $conn->query($query);

// Create a new Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set Header Row
$headers = ['Log ID', 'Performed By', 'Action', 'Timestamp'];
$columnIndex = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($columnIndex . '1', $header);
    $columnIndex++;
}

// Populate Data Rows
$rowNumber = 2;
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $sheet->setCellValue('A' . $rowNumber, $row['log_id']);
    $sheet->setCellValue('B' . $rowNumber, $row['performed_by']);
    $sheet->setCellValue('C' . $rowNumber, $row['action']);
    $sheet->setCellValue('D' . $rowNumber, $row['timestamp']);
    $rowNumber++;
}

// Set Auto Column Widths (Optional, but recommended)
foreach (range('A', 'D') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Set Headers for File Download
$filename = 'audit_logs_' . date('Y-m-d_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Write Excel File & Send to Browser
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();
?>
