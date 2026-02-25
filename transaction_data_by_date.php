<?php
// transaction_data_by_date.php
// VERSION: HYBRID CONTEXT (Cloud + Local) + STATUS FILTERING
// No hardcoding. Strict Context.

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
header('Content-Type: application/json');

// 1. CONTEXT RESOLUTION
if (!isset($_SESSION['branch_code'])) {
    echo json_encode(['transactions' => [], 'total_pages' => 0, 'error' => 'Session missing']);
    exit;
}

$session_branch = $_SESSION['branch_code'];
// Priority: GET > Session
$target_branch = isset($_GET['branch_code']) && !empty($_GET['branch_code']) 
                 ? trim($_GET['branch_code']) 
                 : $session_branch;

// Determine Mode
$is_remote = ($target_branch !== $session_branch) || (isset($_SESSION['role']) && $_SESSION['role'] === 'Super Admin');

// 2. INPUTS
$dateInput = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$date = date('Y-m-d', strtotime($dateInput));
$startDate = date('Y-m-01', strtotime($date));
$endDate = date('Y-m-t', strtotime($date));

$perPage = 100;
$page = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;

$transactions = [];
$totalPages = 0;

try {
    if ($is_remote) {
        // =========================================================
        // MODE A: CLOUD FETCH (MySQL)
        // =========================================================
        $cloud_host = 'srv1254.hstgr.io';
        $cloud_user = 'u106033383_jemerald1234';
        $cloud_pass = 'Wearelive_1234';
        $cloud_name = 'u106033383_jemerald_cloud';

        // Graceful Fallback check
        try {
            $cloud_conn = @new mysqli($cloud_host, $cloud_user, $cloud_pass, $cloud_name);
            if ($cloud_conn->connect_error) throw new Exception("Offline");
        } catch (Exception $e) {
            // If offline and target is different, fail. If target is local, fall through.
            if ($target_branch !== $session_branch) {
                throw new Exception("Offline: Cannot access remote branch data.");
            }
            $is_remote = false; // Fallback to local
        }
    }

    if ($is_remote && isset($cloud_conn)) {
        // --- CLOUD QUERY ---
        // Note: Cloud 'items' table usually uses 'id' as PK, not 'item_id'
        
        // 1. Data Query
        $sql = "SELECT t.id as transaction_id, t.transaction_date, t.profit_loss, t.item_id, 
                       COALESCE(i.item_name, 'Unknown Item') as item_name, 
                       i.item_description, i.purchase_price, i.wholesale_price as wholesale, 
                       i.retail_price as retail, i.quantity_in_stock, 
                       t.transaction_type, t.quantity, 
                       t.sold_at, t.total_amount
                FROM transactions t
                LEFT JOIN items i ON t.item_id = i.id
                WHERE t.status = 0 
                AND t.branch_code = ?
                AND DATE(t.transaction_date) BETWEEN ? AND ?
                LIMIT ? OFFSET ?";

        $stmt = $cloud_conn->prepare($sql);
        $stmt->bind_param("sssii", $target_branch, $startDate, $endDate, $perPage, $offset);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $transactions[] = $row;
        }
        $stmt->close();

        // 2. Count Query
        $countSql = "SELECT COUNT(*) as total 
                     FROM transactions t 
                     WHERE t.status = 0 
                     AND t.branch_code = ? 
                     AND DATE(t.transaction_date) BETWEEN ? AND ?";
        
        $cStmt = $cloud_conn->prepare($countSql);
        $cStmt->bind_param("sss", $target_branch, $startDate, $endDate);
        $cStmt->execute();
        $cRes = $cStmt->get_result()->fetch_assoc();
        $totalTransactions = $cRes['total'];
        $totalPages = ceil($totalTransactions / $perPage);
        $cStmt->close();
        $cloud_conn->close();

    } else {
        // =========================================================
        // MODE B: LOCAL FETCH (SQLite)
        // =========================================================
        include('connection.php');

        // 1. Data Query
        // Note: Local 'items' table uses 'item_id'
        $query = "SELECT t.transaction_id, t.transaction_date, t.profit_loss, t.item_id, 
                         i.item_name, i.item_description, i.purchase_price, i.wholesale, 
                         i.retail, i.quantity_in_stock, t.transaction_type, t.quantity, 
                         t.sold_at, t.total_amount
                  FROM transactions t
                  JOIN items i ON t.item_id = i.item_id
                  WHERE t.status = 0 
                  AND t.branch_code = ?
                  AND date(t.transaction_date) BETWEEN ? AND ? 
                  LIMIT ? OFFSET ?";

        $stmt = $conn->prepare($query);
        $stmt->bindValue(1, $target_branch, SQLITE3_TEXT);
        $stmt->bindValue(2, $startDate, SQLITE3_TEXT);
        $stmt->bindValue(3, $endDate, SQLITE3_TEXT);
        $stmt->bindValue(4, $perPage, SQLITE3_INTEGER);
        $stmt->bindValue(5, $offset, SQLITE3_INTEGER);
        
        $result = $stmt->execute();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $transactions[] = $row;
        }

        // 2. Count Query
        $totalQuery = "SELECT COUNT(*) AS total
                       FROM transactions t
                       JOIN items i ON t.item_id = i.item_id
                       WHERE t.status = 0 
                       AND t.branch_code = ?
                       AND date(t.transaction_date) BETWEEN ? AND ?";

        $totalStmt = $conn->prepare($totalQuery);
        $totalStmt->bindValue(1, $target_branch, SQLITE3_TEXT);
        $totalStmt->bindValue(2, $startDate, SQLITE3_TEXT);
        $totalStmt->bindValue(3, $endDate, SQLITE3_TEXT);
        
        $totalResult = $totalStmt->execute();
        $totalRows = $totalResult->fetchArray(SQLITE3_ASSOC);
        $totalTransactions = $totalRows['total'];
        $totalPages = ceil($totalTransactions / $perPage);
        
        $conn->close();
    }

    echo json_encode([
        'transactions' => $transactions,
        'total_pages' => $totalPages,
        'current_page' => $page,
        'debug_branch' => $target_branch,
        'source' => $is_remote ? 'CLOUD' : 'LOCAL'
    ]);

} catch (Exception $e) {
    echo json_encode(['transactions' => [], 'error' => $e->getMessage()]);
}
?>