<?php
// fetch_items.php
// VERSION: HYBRID CONTEXT (Cloud + Local)
session_start();
header('Content-Type: application/json');

// 1. SECURITY & INPUTS
if (!isset($_SESSION['user_id'])) {
    echo json_encode([]); 
    exit;
}

$term = isset($_GET['term']) ? trim($_GET['term']) : '';
if (strlen($term) < 1) {
    echo json_encode([]);
    exit;
}

// 2. CONTEXT RESOLUTION
// Priority: GET Param > Session > Fallback
$session_branch = $_SESSION['branch_code'];
$target_branch  = isset($_GET['branch_code']) && !empty($_GET['branch_code']) 
                  ? $_GET['branch_code'] 
                  : $session_branch;

// Determine if we need to hit the Cloud DB
$is_remote = ($target_branch !== $session_branch) || (isset($_SESSION['role']) && $_SESSION['role'] === 'Super Admin' && $target_branch !== $session_branch);

$suggestions = [];

try {
    if ($is_remote) {
        // =========================================================
        // MODE A: CLOUD FETCH (MySQL)
        // =========================================================
        $cloud_host = 'srv1254.hstgr.io';
        $cloud_user = 'u106033383_jemerald1234';
        $cloud_pass = 'Wearelive_1234';
        $cloud_name = 'u106033383_jemerald_cloud';

        // Suppress warnings for cleaner JSON output on failure
        $cloud_conn = @new mysqli($cloud_host, $cloud_user, $cloud_pass, $cloud_name);

        if ($cloud_conn && !$cloud_conn->connect_error) {
            // Fetch items belonging to the TARGET branch
            $sql = "SELECT DISTINCT item_name FROM items 
                    WHERE item_name LIKE ? AND branch_code = ? 
                    ORDER BY item_name ASC LIMIT 10";
            
            $stmt = $cloud_conn->prepare($sql);
            $searchTerm = '%' . $term . '%';
            $stmt->bind_param("ss", $searchTerm, $target_branch);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $suggestions[] = $row['item_name'];
            }
            $stmt->close();
            $cloud_conn->close();
        }
    } 
    
    // =========================================================
    // MODE B: LOCAL FETCH (SQLite)
    // =========================================================
    // Fallback to local if not remote OR if cloud failed (optional hybrid safety)
    if (empty($suggestions) && !$is_remote) {
        include('connection.php');
        
        // Local DB usually contains items for the local branch
        $sql = "SELECT DISTINCT item_name FROM items 
                WHERE item_name LIKE :term 
                ORDER BY item_name ASC LIMIT 10";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':term', '%' . $term . '%', SQLITE3_TEXT);
        $result = $stmt->execute();
        
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $suggestions[] = $row['item_name'];
        }
        $conn->close();
    }

    echo json_encode($suggestions);

} catch (Exception $e) {
    echo json_encode([]);
}
?>