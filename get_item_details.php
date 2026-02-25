<?php
// get_item_details.php
session_start();
include('connection.php'); // Default Local Connection

header('Content-Type: application/json');

// 1. INPUT VALIDATION
if (!isset($_GET['itemId'])) {
    echo json_encode(['error' => 'Item ID not provided.']);
    exit;
}
$itemId = intval($_GET['itemId']);

// 2. CONTEXT RESOLUTION
$session_branch   = $_SESSION['branch_code'] ?? 'HEAD_OFFICE';
$requested_branch = $_GET['branch_code'] ?? $session_branch;
$is_remote        = ($requested_branch !== $session_branch);

try {
    $itemDetails = null;

    if ($is_remote) {
        // =========================
        // MODE A: CLOUD FETCH (MySQL)
        // =========================
        $cloud_host = 'srv1254.hstgr.io';
        $cloud_user = 'u106033383_jemerald1234';
        $cloud_pass = 'Wearelive_1234';
        $cloud_name = 'u106033383_jemerald_cloud';

        $cloud_conn = new mysqli($cloud_host, $cloud_user, $cloud_pass, $cloud_name);
        if ($cloud_conn->connect_error) throw new Exception("Cloud Connection Failed");

        // Map 'id' (Cloud) to 'item_id' (Local Standard)
        $sql = "SELECT 
                    id AS item_id, item_unique_no, item_name, item_description, 
                    purchase_price, wholesale_price AS wholesale, retail_price AS retail, 
                    quantity_in_stock, status, category_name AS category, 
                    expiration_date, invoice_number, supplier_info, date_purchased
                FROM items 
                WHERE id = ? AND branch_code = ?";
        
        $stmt = $cloud_conn->prepare($sql);
        $stmt->bind_param("is", $itemId, $requested_branch);
        $stmt->execute();
        $result = $stmt->get_result();
        $itemDetails = $result->fetch_assoc();
        
        $cloud_conn->close();

    } else {
        // =========================
        // MODE B: LOCAL FETCH (SQLite)
        // =========================
        $sql = "SELECT * FROM items WHERE item_id = :item_id AND branch_code = :branch";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':item_id', $itemId, SQLITE3_INTEGER);
        $stmt->bindValue(':branch', $requested_branch, SQLITE3_TEXT);
        $result = $stmt->execute();
        $itemDetails = $result->fetchArray(SQLITE3_ASSOC);
    }

    if ($itemDetails) {
        echo json_encode($itemDetails);
    } else {
        echo json_encode(['error' => 'Item not found in ' . ($is_remote ? 'Cloud' : 'Local') . ' DB.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>