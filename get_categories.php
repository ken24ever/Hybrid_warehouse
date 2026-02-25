<?php
// get_categories.php
// VERSION: FIXED COLUMN MAPPING (Cloud 'id' vs Local 'category_id')
session_start();
include('connection.php'); // Ensure this file defines $conn (Local SQLite)

header('Content-Type: application/json');

// Disable error display to prevent HTML breaking JSON, but log them
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    $session_branch   = $_SESSION['branch_code'] ?? 'HEAD_OFFICE';
    $requested_branch = $_GET['branch_code'] ?? $session_branch;
    $is_remote        = ($requested_branch !== $session_branch);
    
    $categories = [];

    if ($is_remote) {
        // --- CLOUD CONNECTION (MySQL) ---
        $cloud_host = 'srv1254.hstgr.io';
        $cloud_user = 'u106033383_jemerald1234';
        $cloud_pass = 'Wearelive_1234';
        $cloud_name = 'u106033383_jemerald_cloud';

        $cloud_conn = @new mysqli($cloud_host, $cloud_user, $cloud_pass, $cloud_name);
        
        if ($cloud_conn && !$cloud_conn->connect_error) {
            
            // 1. Try Specific Branch First
            // NOTE: Cloud uses 'id', not 'category_id'
            $stmt = $cloud_conn->prepare("SELECT id as category_id, category_name FROM item_categories WHERE branch_code = ? ORDER BY category_name ASC");
            $stmt->bind_param("s", $requested_branch);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) { $categories[] = $row; }
            $stmt->close();
            
            // 2. FALLBACK: If empty, fetch ALL categories (Global/Shared List)
            if (empty($categories)) {
                // NOTE: Cloud uses 'id'
                $stmtAll = $cloud_conn->prepare("SELECT DISTINCT category_name, id as category_id FROM item_categories ORDER BY category_name ASC");
                $stmtAll->execute();
                $resultAll = $stmtAll->get_result();
                while ($row = $resultAll->fetch_assoc()) { $categories[] = $row; }
                $stmtAll->close();
            }
            $cloud_conn->close();
        } else {
            // Optional: Return a specific error if cloud connection fails
            // $categories[] = ['category_name' => 'Error: Cloud Offline'];
        }
    } else {
        // --- LOCAL CONNECTION (SQLite) ---
        // NOTE: Local uses 'category_id'
        $stmt = $conn->prepare("SELECT category_id, category_name FROM item_categories WHERE branch_code = :branch ORDER BY category_name ASC");
        $stmt->bindValue(':branch', $requested_branch, SQLITE3_TEXT);
        $result = $stmt->execute();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) { $categories[] = $row; }
        
        // Local Fallback
        if (empty($categories)) {
             $res = $conn->query("SELECT DISTINCT category_name, category_id FROM item_categories ORDER BY category_name ASC");
             while ($row = $res->fetchArray(SQLITE3_ASSOC)) { $categories[] = $row; }
        }
    }

    echo json_encode($categories);

} catch (Exception $e) {
    // Return empty array on error so JS doesn't crash
    echo json_encode([]);
}
?>