<?php
// view_items.php
session_start();
include('connection.php'); 
include('defined_global_settings.php'); 

header('Content-Type: application/json');

try {
    // 1. DYNAMIC CONTEXT RESOLUTION
    // Get the branch of the currently logged-in user
    $session_branch = $_SESSION['branch_code'] ?? '';

    // If session is invalid, stop immediately
    if (empty($session_branch)) {
        throw new Exception("Unauthorized: No active session branch found.");
    }

    // Get the branch requested by the JavaScript (via manage_item.php)
    // If JS sends nothing, default to the user's session
    $requested_branch = isset($_GET['branch_code']) && !empty($_GET['branch_code']) 
                        ? $_GET['branch_code'] 
                        : $session_branch;
    
    // Pagination & Settings
    $perPage = 10000;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $offset = ($page - 1) * $perPage;
    $threshold = defined('LOW_STOCK_THRESHOLD') ? LOW_STOCK_THRESHOLD : 10;

    // 2. DETERMINE SOURCE (Local vs Cloud)
    // If the requested branch is NOT the user's physical branch, we must go to Cloud.
    $is_remote = ($requested_branch !== $session_branch);
    
    $items = [];
    $totalItems = 0;
    $totalQuantity = 0;

    if ($is_remote) {
        // =========================================================
        // MODE A: CLOUD FETCH (MySQL) - Viewing Another Branch
        // =========================================================
        $cloud_host = 'srv1254.hstgr.io';
        $cloud_user = 'u106033383_jemerald1234';
        $cloud_pass = 'Wearelive_1234';
        $cloud_name = 'u106033383_jemerald_cloud';

        $cloud_conn = new mysqli($cloud_host, $cloud_user, $cloud_pass, $cloud_name);
        if ($cloud_conn->connect_error) {
            throw new Exception("Cloud Connection Failed: " . $cloud_conn->connect_error);
        }

        // Totals Query
        $stmtTotal = $cloud_conn->prepare("SELECT COUNT(*) AS total_items, SUM(quantity_in_stock) AS total_quantity FROM items WHERE branch_code = ?");
        $stmtTotal->bind_param("s", $requested_branch);
        $stmtTotal->execute();
        $resTotal = $stmtTotal->get_result()->fetch_assoc();
        $totalItems = $resTotal['total_items'] ?? 0;
        $totalQuantity = $resTotal['total_quantity'] ?? 0;

        // Data Query
        $sql = "
            SELECT 
                id AS item_id, 
                item_unique_no, item_name, item_description,
                purchase_price, wholesale_price AS wholesale, retail_price AS retail, category_name AS category, 
                quantity_in_stock, expiration_date, supplier_info,
                invoice_number, date_purchased,
                branch_code,
                CASE 
                    WHEN quantity_in_stock = 0 THEN 'Out of Stock'
                    WHEN quantity_in_stock < ? THEN 'Low Stock'
                    ELSE 'In Stock'
                END AS status,
                CASE 
                    WHEN expiration_date IS NULL OR expiration_date = '' THEN 5
                    WHEN DATEDIFF(expiration_date, NOW()) <= 7 THEN 1
                    WHEN DATEDIFF(expiration_date, NOW()) <= 14 THEN 2
                    WHEN DATEDIFF(expiration_date, NOW()) <= 30 THEN 3
                    WHEN DATEDIFF(expiration_date, NOW()) <= 60 THEN 4
                    ELSE 5
                END AS expiration_priority
            FROM items
            WHERE branch_code = ?
            ORDER BY expiration_priority ASC, id DESC
            LIMIT ? OFFSET ?
        ";

        $stmt = $cloud_conn->prepare($sql);
        $stmt->bind_param("isii", $threshold, $requested_branch, $perPage, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        $cloud_conn->close();

    } else {
        // =========================================================
        // MODE B: LOCAL FETCH (SQLite) - Viewing Own Branch
        // =========================================================
        
        // Totals Query
        $stmtTotal = $conn->prepare("SELECT COUNT(*) AS total_items, SUM(quantity_in_stock) AS total_quantity FROM items WHERE branch_code = :branch");
        $stmtTotal->bindValue(':branch', $requested_branch, SQLITE3_TEXT);
        $resTotal = $stmtTotal->execute()->fetchArray(SQLITE3_ASSOC);
        $totalItems = $resTotal['total_items'] ?? 0;
        $totalQuantity = $resTotal['total_quantity'] ?? 0;

        // Data Query
        $sql = "
            SELECT 
                item_id, item_unique_no, item_name, item_description,
                purchase_price, wholesale, retail, category, 
                quantity_in_stock, expiration_date, supplier_info,
                invoice_number, date_purchased,
                branch_code,
                CASE 
                    WHEN quantity_in_stock = 0 THEN 'Out of Stock'
                    WHEN quantity_in_stock < :threshold THEN 'Low Stock'
                    ELSE 'In Stock'
                END AS status,
                CASE 
                    WHEN expiration_date IS NULL OR expiration_date = '' THEN 5
                    WHEN julianday(expiration_date) - julianday('now') <= 7 THEN 1
                    WHEN julianday(expiration_date) - julianday('now') <= 14 THEN 2
                    WHEN julianday(expiration_date) - julianday('now') <= 30 THEN 3
                    WHEN julianday(expiration_date) - julianday('now') <= 60 THEN 4
                    ELSE 5
                END AS expiration_priority
            FROM items
            WHERE branch_code = :branch
            ORDER BY expiration_priority ASC, item_id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':branch', $requested_branch, SQLITE3_TEXT);
        $stmt->bindValue(':threshold', $threshold, SQLITE3_INTEGER);
        $stmt->bindValue(':limit', $perPage, SQLITE3_INTEGER);
        $stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
        $result = $stmt->execute();

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $items[] = $row;
        }
    }

    $totalPages = ceil($totalItems / $perPage);

    echo json_encode([
        'items' => $items,
        'total_pages' => $totalPages,
        'total_items' => $totalItems,
        'total_quantity' => $totalQuantity,
        'branch' => $requested_branch,
        'source' => $is_remote ? 'Cloud (Remote)' : 'Local (Direct)'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>