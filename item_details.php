<?php
// item_details.php
// VERSION: Hybrid Branch Context (Local & Cloud Support)

session_start();
include('connection.php'); // Default Local SQLite Connection

header('Content-Type: application/json');

// 1. INPUTS
$searchTerm = isset($_GET['searchTerm']) ? trim($_GET['searchTerm']) : '';

// 2. CONTEXT RESOLUTION
$session_branch   = $_SESSION['branch_code'];
$requested_branch = $_GET['branch_code'] ?? $session_branch;
$is_remote        = ($requested_branch !== $session_branch);

if (empty($searchTerm)) {
    echo json_encode(['status' => 'error', 'message' => 'No search term provided']);
    exit;
}

try {
    $items = [];

    if ($is_remote) {
        // =========================================================
        // MODE A: CLOUD FETCH (MySQL)
        // =========================================================
        $cloud_host = 'srv1254.hstgr.io';
        $cloud_user = 'u106033383_jemerald1234';
        $cloud_pass = 'Wearelive_1234';
        $cloud_name = 'u106033383_jemerald_cloud';

        $cloud_conn = new mysqli($cloud_host, $cloud_user, $cloud_pass, $cloud_name);
        if ($cloud_conn->connect_error) throw new Exception("Cloud Connection Failed");

        // Prepare SQL with Column Aliasing to match Local SQLite Schema
        $columns = "id AS item_id, item_name, item_unique_no, 
                    wholesale_price AS wholesale, retail_price AS retail, 
                    quantity_in_stock, item_description, expiration_date";

        if (is_numeric($searchTerm)) {
            // Barcode Search
            $sql = "SELECT $columns FROM items WHERE item_unique_no = ? AND branch_code = ?";
            $stmt = $cloud_conn->prepare($sql);
            $stmt->bind_param("ss", $searchTerm, $requested_branch);
        } else {
            // Name Search (Suggestions)
            $sql = "SELECT $columns FROM items WHERE item_name LIKE ? AND branch_code = ? LIMIT 10";
            $stmt = $cloud_conn->prepare($sql);
            $likeTerm = "%$searchTerm%";
            $stmt->bind_param("ss", $likeTerm, $requested_branch);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        $cloud_conn->close();

    } else {
        // =========================================================
        // MODE B: LOCAL FETCH (SQLite)
        // =========================================================
        
        $columns = "item_id, item_name, item_unique_no, wholesale, retail, 
                    quantity_in_stock, item_description, expiration_date";

        if (is_numeric($searchTerm)) {
            // Barcode Search
            $query = "SELECT $columns FROM items 
                      WHERE item_unique_no = :searchTerm AND branch_code = :branch";
            $stmt = $conn->prepare($query);
            $stmt->bindValue(':searchTerm', $searchTerm, SQLITE3_TEXT);
        } else {
            // Name Search
            $query = "SELECT $columns FROM items 
                      WHERE item_name LIKE :searchTerm AND branch_code = :branch 
                      LIMIT 10";
            $stmt = $conn->prepare($query);
            $stmt->bindValue(':searchTerm', "%$searchTerm%", SQLITE3_TEXT);
        }

        $stmt->bindValue(':branch', $requested_branch, SQLITE3_TEXT);
        $result = $stmt->execute();

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $items[] = $row;
        }
    }

    // --- 3. PROCESS RESULTS (Common Logic) ---
    $processedItems = [];
    foreach ($items as $row) {
        // Calculate expiration status
        $row['is_expired'] = false;
        if (!empty($row['expiration_date'])) {
            $expTimestamp = strtotime($row['expiration_date']);
            // Check if valid date and older than "now"
            if ($expTimestamp !== false && $expTimestamp < time()) {
                $row['is_expired'] = true;
            }
        }
        $processedItems[] = $row;
    }

    if (!empty($processedItems)) {
        // If searching by barcode (numeric), usually implies direct fetch -> success
        // If searching by text, implies suggestions list
        $status = is_numeric($searchTerm) ? 'success' : 'suggestions';
        
        echo json_encode([
            'status' => $status,
            'data' => $processedItems,
            'source' => $is_remote ? 'Cloud' : 'Local'
        ]);
    } else {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Item not found in ' . ($is_remote ? 'Cloud' : 'Local') . ' Inventory.'
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>