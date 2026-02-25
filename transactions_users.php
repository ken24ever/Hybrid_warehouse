<?php
// transactions_users.php
// VERSION: INPUT FIELD SEARCH + DEEP LOOKUP
session_start();
include('connection.php'); 

header('Content-Type: application/json'); 

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['transactions' => [], 'total_pages' => 0, 'error' => 'Unauthorized']);
    exit;
}

// --- CONTEXT ---
if (!isset($_SESSION['branch_code'])) {
    echo json_encode(['transactions' => [], 'error' => 'Session Error: Branch context missing.']);
    exit;
}

$current_branch = $_SESSION['branch_code'];
$target_branch  = $_GET['branch_code'] ?? $current_branch;
$is_remote      = ($target_branch !== $current_branch) || (isset($_SESSION['role']) && $_SESSION['role'] === 'Super Admin');

// --- INPUTS ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$startDate = $_GET['startDate'] ?? null;
$endDate   = $_GET['endDate'] ?? null;
$transType = $_GET['transactionType'] ?? '';
$transUser = $_GET['transactionUser'] ?? ''; // Now a search string

$transactions = [];
$total_pages  = 1;
$total_sales  = 0;
$total_profit = 0;
$total_loss   = 0;
$is_fallback  = false; 
$error_msg    = "";

$use_cloud_source = false;
$cloud_conn = null;

if ($should_try_cloud = ($target_branch !== $current_branch) || (isset($_SESSION['role']) && $_SESSION['role'] === 'Super Admin')) {
    try {
        $cloud_host = 'p:srv1254.hstgr.io'; 
        $cloud_user = 'u106033383_jemerald1234';
        $cloud_pass = 'Wearelive_1234';
        $cloud_name = 'u106033383_jemerald_cloud';

        $cloud_conn = @new mysqli($cloud_host, $cloud_user, $cloud_pass, $cloud_name);
        if ($cloud_conn->connect_error) throw new Exception("Cloud Connection Failed");
        $use_cloud_source = true; 
    } catch (Exception $e) {
        $use_cloud_source = false;
        $is_fallback = true;
        $error_msg = "Offline Mode: Showing local data.";
    }
}

try {
    // =========================================================
    // MODE A: CLOUD FETCH
    // =========================================================
    if ($use_cloud_source) {
        
        $whereClauses = ["t.branch_code = ?", "t.status = 0"];
        $types = "s";               
        $params = [$target_branch]; 

        if (!empty($startDate) && !empty($endDate)) {
            $whereClauses[] = "DATE(t.transaction_date) BETWEEN ? AND ?";
            $types .= "ss";
            $params[] = $startDate;
            $params[] = $endDate;
        }

        if (!empty($transType)) {
            $whereClauses[] = "t.transaction_type = ?";
            $types .= "s";
            $params[] = $transType;
        }

        // [FIX] DEEP SEARCH FILTER
        // Checks direct link OR looks up local/remote username via local_user_id
        if (!empty($transUser)) {
            $searchTerm = "%" . $transUser . "%";
            
            $whereClauses[] = "(
                u.username LIKE ? 
                OR 
                (t.local_id > 0 AND (SELECT username FROM users WHERE local_id = t.local_user_id AND branch_code = t.branch_code LIMIT 1) LIKE ?)
                OR 
                (t.local_id < 0 AND (SELECT username FROM users WHERE local_id = t.local_user_id AND branch_code != t.branch_code LIMIT 1) LIKE ?)
            )";
            
            $types .= "sss";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $whereSql = "WHERE " . implode(" AND ", $whereClauses);

        // 2. Count Total
        $countSql = "SELECT COUNT(*) as total FROM transactions t LEFT JOIN users u ON t.user_id = u.id $whereSql";
        $stmt = $cloud_conn->prepare($countSql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params); 
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $total_records = $row['total'];
            $total_pages = ceil($total_records / $limit);
            $stmt->close();
        }

        // 3. Fetch Data
        $dataSql = "SELECT 
            t.id as transaction_id, 
            t.transaction_date,
            t.modeOfPayment,
            t.quantity,
            t.total_amount,
            t.sold_at,
            t.profit_loss,
            t.status,
            t.transaction_group_id,
            t.profit,
            t.fixed_price_at_sale,
            t.transaction_type, 
            t.branch_code,
            COALESCE(i.item_name, CONCAT('Item #', t.item_id)) as item_name,
            
            CASE 
                WHEN u_cloud.username IS NOT NULL THEN CONCAT(u_cloud.username, ' (', u_cloud.branch_code, ')')
                WHEN t.local_id < 0 THEN COALESCE((SELECT CONCAT(username, ' (', branch_code, ')') FROM users WHERE local_id = t.local_user_id AND branch_code != t.branch_code ORDER BY (role_name = 'Super Admin') DESC LIMIT 1), 'Remote Admin')
                WHEN t.local_id > 0 THEN CONCAT(COALESCE((SELECT username FROM users WHERE local_id = t.local_user_id AND branch_code = t.branch_code LIMIT 1), 'System User'), ' (Local)')
                ELSE 'System User'
            END as username,
            
            COALESCE(i.purchase_price, 0) as purchase_price
        FROM transactions t
        LEFT JOIN items i ON (t.item_id = i.id OR (t.item_id = i.local_id AND i.branch_code = t.branch_code))
        LEFT JOIN users u_cloud ON t.user_id = u_cloud.id
        LEFT JOIN users u ON t.user_id = u.id 
        $whereSql 
        ORDER BY t.transaction_date DESC 
        LIMIT ? OFFSET ?";

        $typesWithLimit = $types . "ii"; 
        $paramsWithLimit = $params;
        $paramsWithLimit[] = $limit;
        $paramsWithLimit[] = $offset;

        $stmt = $cloud_conn->prepare($dataSql);
        if (!$stmt) throw new Exception("Query Error: " . $cloud_conn->error);

        $stmt->bind_param($typesWithLimit, ...$paramsWithLimit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }
        $stmt->close();

        // 4. Aggregates
        $aggSql = "SELECT 
                    SUM(t.total_amount) as total_sales,
                    SUM(CASE WHEN t.profit_loss > 0 THEN t.profit_loss ELSE 0 END) as total_profit,
                    SUM(ABS(CASE WHEN t.profit_loss < 0 THEN t.profit_loss ELSE 0 END)) as total_loss
                   FROM transactions t
                   LEFT JOIN users u ON t.user_id = u.id
                   $whereSql";
        
        $stmt = $cloud_conn->prepare($aggSql);
        if($stmt) {
            $stmt->bind_param($types, ...$params); 
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $total_sales  = $res['total_sales'] ?? 0;
            $total_profit = $res['total_profit'] ?? 0;
            $total_loss   = $res['total_loss'] ?? 0;
            $stmt->close();
        }

    } 
    // =========================================================
    // MODE B: LOCAL FETCH
    // =========================================================
    else {
        
        $whereClauses = [];
        $params = [];
        $whereClauses[] = "t.status = 0";

        if (!empty($startDate) && !empty($endDate)) {
            $whereClauses[] = "DATE(t.transaction_date) BETWEEN :start AND :end";
            $params[':start'] = $startDate;
            $params[':end']   = $endDate;
        }

        if (!empty($target_branch)) {
            $whereClauses[] = "t.branch_code = :branch";
            $params[':branch'] = $target_branch;
        }

        if (!empty($transType)) {
            $whereClauses[] = "t.transaction_type = :type";
            $params[':type'] = $transType;
        }

        // [FIX] Simple Partial Match for Local Mode
        if (!empty($transUser)) {
            $whereClauses[] = "u.username LIKE :user";
            $params[':user'] = "%" . $transUser . "%";
        }

        $whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

        // 1. Count Total
        $countSql = "SELECT COUNT(*) as total FROM transactions t LEFT JOIN users u ON t.user_id = u.user_id $whereSql";
        $stmt = $conn->prepare($countSql);
        foreach ($params as $key => $val) $stmt->bindValue($key, $val);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        $total_records = $row['total'];
        $total_pages = ceil($total_records / $limit);

        // 2. Aggregates
        $aggSql = "SELECT 
                    SUM(t.total_amount) as total_sales,
                    SUM(CASE WHEN t.profit_loss > 0 THEN t.profit_loss ELSE 0 END) as total_profit,
                    SUM(ABS(CASE WHEN t.profit_loss < 0 THEN t.profit_loss ELSE 0 END)) as total_loss
                   FROM transactions t 
                   LEFT JOIN users u ON t.user_id = u.user_id
                   $whereSql";
        $stmt = $conn->prepare($aggSql);
        foreach ($params as $key => $val) $stmt->bindValue($key, $val);
        $res = $stmt->execute();
        $agg = $res->fetchArray(SQLITE3_ASSOC);
        $total_sales  = $agg['total_sales'] ?? 0;
        $total_profit = $agg['total_profit'] ?? 0;
        $total_loss   = $agg['total_loss'] ?? 0;

        // 3. Fetch Data
        $dataSql = "SELECT 
                        t.*,
                        COALESCE(i.item_name, 'Item #' || t.item_id) as item_name,
                        COALESCE(u.username || ' (Local)', 'System User') as username, 
                        t.profit, 
                        t.fixed_price_at_sale,
                        COALESCE(i.purchase_price, (t.total_amount - t.profit) / NULLIF(t.quantity, 0), 0) as purchase_price
                    FROM transactions t
                    LEFT JOIN items i ON t.item_id = i.item_id
                    LEFT JOIN users u ON t.user_id = u.user_id
                    $whereSql 
                    ORDER BY t.transaction_date DESC 
                    LIMIT :limit OFFSET :offset";
        
        $stmt = $conn->prepare($dataSql);
        foreach ($params as $key => $val) $stmt->bindValue($key, $val);
        $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
        $stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
        
        $result = $stmt->execute();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $transactions[] = $row;
        }
    }

    echo json_encode([
        'transactions' => $transactions,
        'total_pages'  => $total_pages,
        'total_sales'  => $total_sales,
        'total_profit' => $total_profit,
        'total_loss'   => $total_loss,
        'debug_branch' => $target_branch,
        'mode'         => $use_cloud_source ? 'CLOUD' : 'LOCAL',
        'is_fallback'  => $is_fallback, 
        'warning'      => $error_msg
    ]);

} catch (Exception $e) {
    echo json_encode([
        'transactions' => [], 
        'error' => $e->getMessage(),
        'debug_branch' => $target_branch
    ]);
}
?>