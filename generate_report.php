<?php
// generate_report.php
session_start();
include('connection.php');

header('Content-Type: application/json');

$itemId = intval($_POST['itemId'] ?? 0);
$date   = $_POST['date'] ?? '';
$month  = $_POST['month'] ?? '';

// 1. CONTEXT RESOLUTION
$session_branch   = $_SESSION['branch_code'] ?? 'HEAD_OFFICE';
$requested_branch = $_POST['branch_code'] ?? $session_branch;
$is_remote        = ($requested_branch !== $session_branch);

if (!$itemId) {
    echo json_encode(['success' => false, 'message' => 'Invalid item ID.']); 
    exit;
}

try {
    $data = [];

    if ($is_remote) {
        // =========================
        // MODE A: CLOUD (MySQL)
        // =========================
        $cloud_conn = new mysqli('srv1254.hstgr.io', 'u106033383_jemerald1234', 'Wearelive_1234', 'u106033383_jemerald_cloud');
        if ($cloud_conn->connect_error) throw new Exception("Cloud Connection Failed");

        if ($date) {
            // Daily Report
            $query = "SELECT SUM(quantity) AS quantitySold, SUM(total_amount) AS totalAmount, SUM(profit) AS profit 
                      FROM transactions WHERE item_id = ? AND DATE(transaction_date) = ? AND branch_code = ?";
            $stmt = $cloud_conn->prepare($query);
            $stmt->bind_param("iss", $itemId, $date, $requested_branch);
        } elseif ($month) {
            // Monthly Report
            $query = "SELECT SUM(quantity) AS quantitySold, SUM(total_amount) AS totalAmount, SUM(profit) AS profit 
                      FROM transactions WHERE item_id = ? AND DATE_FORMAT(transaction_date, '%Y-%m') = ? AND branch_code = ?";
            $stmt = $cloud_conn->prepare($query);
            $stmt->bind_param("iss", $itemId, $month, $requested_branch);
        } else {
            throw new Exception("No date selected");
        }
        
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();

        // Comparison Data (MySQL)
        $lastMonth = date('Y-m', strtotime($month . " -1 month"));
        $compQuery = "SELECT 
            SUM(CASE WHEN DATE_FORMAT(transaction_date, '%Y-%m') = ? THEN quantity ELSE 0 END) AS lastMonthQuantity,
            SUM(CASE WHEN DATE_FORMAT(transaction_date, '%Y-%m') = ? THEN quantity ELSE 0 END) AS currentMonthQuantity,
            SUM(CASE WHEN DATE_FORMAT(transaction_date, '%Y-%m') = ? THEN total_amount ELSE 0 END) AS lastMonthAmount,
            SUM(CASE WHEN DATE_FORMAT(transaction_date, '%Y-%m') = ? THEN total_amount ELSE 0 END) AS currentMonthAmount
        FROM transactions WHERE item_id = ? AND branch_code = ?";
        
        $stmtComp = $cloud_conn->prepare($compQuery);
        $stmtComp->bind_param("ssssis", $lastMonth, $month, $lastMonth, $month, $itemId, $requested_branch);
        $stmtComp->execute();
        $comparisonData = $stmtComp->get_result()->fetch_assoc();
        
        $cloud_conn->close();

    } else {
        // =========================
        // MODE B: LOCAL (SQLite)
        // =========================
        if ($date) {
            $query = "SELECT SUM(quantity) AS quantitySold, SUM(total_amount) AS totalAmount, SUM(profit_loss) AS profit 
                      FROM transactions WHERE item_id = :item_id AND date(transaction_date) = :date AND branch_code = :branch";
            $stmt = $conn->prepare($query);
            $stmt->bindValue(':date', $date, SQLITE3_TEXT);
        } elseif ($month) {
            $query = "SELECT SUM(quantity) AS quantitySold, SUM(total_amount) AS totalAmount, SUM(profit_loss) AS profit 
                      FROM transactions WHERE item_id = :item_id AND strftime('%Y-%m', transaction_date) = :month AND branch_code = :branch";
            $stmt = $conn->prepare($query);
            $stmt->bindValue(':month', $month, SQLITE3_TEXT);
        } else {
            throw new Exception("No date selected");
        }
        
        $stmt->bindValue(':item_id', $itemId, SQLITE3_INTEGER);
        $stmt->bindValue(':branch', $requested_branch, SQLITE3_TEXT);
        $result = $stmt->execute();
        $data = $result->fetchArray(SQLITE3_ASSOC);

        // Comparison Data (SQLite)
        $lastMonth = date('Y-m', strtotime($month . " -1 month"));
        $compQuery = "SELECT 
            SUM(CASE WHEN strftime('%Y-%m', transaction_date) = :lastMonth THEN quantity ELSE 0 END) AS lastMonthQuantity,
            SUM(CASE WHEN strftime('%Y-%m', transaction_date) = :month THEN quantity ELSE 0 END) AS currentMonthQuantity,
            SUM(CASE WHEN strftime('%Y-%m', transaction_date) = :lastMonth THEN total_amount ELSE 0 END) AS lastMonthAmount,
            SUM(CASE WHEN strftime('%Y-%m', transaction_date) = :month THEN total_amount ELSE 0 END) AS currentMonthAmount
        FROM transactions WHERE item_id = :item_id AND branch_code = :branch";

        $stmtComp = $conn->prepare($compQuery);
        $stmtComp->bindValue(':lastMonth', $lastMonth, SQLITE3_TEXT);
        $stmtComp->bindValue(':month', $month, SQLITE3_TEXT);
        $stmtComp->bindValue(':item_id', $itemId, SQLITE3_INTEGER);
        $stmtComp->bindValue(':branch', $requested_branch, SQLITE3_TEXT);
        $resComp = $stmtComp->execute();
        $comparisonData = $resComp->fetchArray(SQLITE3_ASSOC);
    }

    echo json_encode([
        'success' => true,
        'quantitySold' => (int)($data['quantitySold'] ?? 0),
        'totalAmount' => number_format((float)($data['totalAmount'] ?? 0), 2),
        'profit' => number_format((float)($data['profit'] ?? 0), 2),
        'quantityStock' => 'N/A', // Stock is live, handled by main view
        'chartData' => [
            'quantity' => [
                (int)($comparisonData['lastMonthQuantity'] ?? 0),
                (int)($comparisonData['currentMonthQuantity'] ?? 0)
            ],
            'amount' => [
                (float)($comparisonData['lastMonthAmount'] ?? 0),
                (float)($comparisonData['currentMonthAmount'] ?? 0)
            ]
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>