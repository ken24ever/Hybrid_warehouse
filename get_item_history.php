<?php
// get_item_history.php
// VERSION: UNIFIED IDENTITY & DUPLICATE PREVENTION (Local + Remote)
session_start();
include('connection.php'); 

// 1. SECURITY & INPUT
if (!isset($_SESSION['role'])) { 
    http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; 
}

header('Content-Type: application/json');

try {
    if (!isset($_GET['itemId']) || empty($_GET['itemId'])) throw new Exception('Item ID not provided.');
    
    $itemId = intval($_GET['itemId']); 
    
 // 2. DYNAMIC CONTEXT RESOLUTION
    // [FIX] Strict Session Check (No hardcoding 'HEAD_OFFICE')
    if (!isset($_SESSION['branch_code'])) {
        http_response_code(401); echo json_encode(['error' => 'Session Error: Branch context missing.']); exit;
    }

    $session_branch = $_SESSION['branch_code']; 
    $requested_branch = $_GET['branch_code'] ?? $session_branch;

   // [FIX] Force Cloud Mode for Super Admins. 
    // This allows us to resolve "Remote Usernames" (Culprits) via the Cloud DB
    // even if we are looking at our own branch history.
    $is_remote = ($requested_branch !== $session_branch) || (isset($_SESSION['role']) && $_SESSION['role'] === 'Super Admin');
    $historyEvents = [];
    $cloud_conn = null; // Initialize variable

    // ---------------------------------------------------------
    // [FIX] CONNECTION CHECK (Run this BEFORE entering logic block)
    // ---------------------------------------------------------
    if ($is_remote) {
        $cloud_host = 'srv1254.hstgr.io';
        $cloud_user = 'u106033383_jemerald1234';
        $cloud_pass = 'Wearelive_1234';
        $cloud_name = 'u106033383_jemerald_cloud';

        try {
            // Use '@' to suppress the PHP Warning that breaks your JSON
            $cloud_conn = @new mysqli($cloud_host, $cloud_user, $cloud_pass, $cloud_name);
            
            if ($cloud_conn->connect_error) {
                throw new Exception("Offline");
            }
        } catch (Exception $e) {
            // CASE A: If viewing a DIFFERENT branch, we simply cannot show data.
            if ($requested_branch !== $session_branch) {
                echo json_encode(['history' => [], 'error' => 'Offline: Cannot access remote branch data.']);
                exit;
            }
            
            // CASE B: If viewing CURRENT branch (Super Admin), FALLBACK to Local SQLite.
            $is_remote = false; 
            $cloud_conn = null;
        }
    }

    // ---------------------------------------------------------
    // [FIX] EXECUTION BLOCK (Only if Connection Exists)
    // ---------------------------------------------------------
    if ($is_remote && $cloud_conn) {
        // =========================================================
        // MODE A: CLOUD FETCH (MySQL) - SMART ID RESOLUTION
        // =========================================================
        
        // [STEP 1]: Resolve IDs (Local vs Cloud)
        // We assume $_GET['itemId'] is the LOCAL ID (from the POS view)
        $targetLocalId = $itemId; 
        $targetCloudId = 0;

        // Find the corresponding Cloud ID for this item
        $idStmt = $cloud_conn->prepare("SELECT id FROM items WHERE local_id = ? AND branch_code = ?");
        $idStmt->bind_param("is", $targetLocalId, $requested_branch);
        $idStmt->execute();
        $idRes = $idStmt->get_result()->fetch_assoc();
        if ($idRes) {
            $targetCloudId = intval($idRes['id']);
        }
        $idStmt->close();

        // [STEP 2]: Fetch History using BOTH IDs
        // Transactions uses Cloud ID. Audit Logs typically uses Local ID.
        $sql = "
        (SELECT 
            t.transaction_date as timestamp, 
            t.quantity, 
            'sale' as action_type, 
            
            CONCAT('Sold ', t.quantity, ' x ', COALESCE(i.item_name, 'Item'), ' (Ref: #', t.transaction_group_id, ')') as details,
            
            CASE 
                -- 1. Direct Cloud Link
                WHEN u.username IS NOT NULL THEN 
                    CONCAT(u.username, ' (', u.branch_code, ')')

                -- 2. Remote Culprit Fallback
                WHEN t.local_id < 0 THEN 
                    COALESCE(
                        (SELECT CONCAT(username, ' (', branch_code, ')') 
                         FROM users 
                         WHERE local_id = t.local_user_id 
                         AND branch_code != t.branch_code 
                         ORDER BY (role_name = 'Super Admin') DESC 
                         LIMIT 1),
                        'Remote Admin (Unknown)'
                    )

                -- 3. Local Fallback
                WHEN t.local_id > 0 THEN 
                    CONCAT(
                        COALESCE(
                            (SELECT username FROM users WHERE local_id = t.local_user_id AND branch_code = t.branch_code LIMIT 1), 
                            'System User'
                        ), 
                        ' (Local)'
                    )

                ELSE 'System (POS)'
            END as username

        FROM transactions t
        LEFT JOIN items i ON t.item_id = i.id
        LEFT JOIN users u ON t.user_id = u.id
        -- [FIX] Search Transactions using the CLOUD ID (Primary) or Local ID (Backup)
        WHERE (t.item_id = ? OR t.item_id = ?) AND t.branch_code = ?)

        UNION ALL

        (SELECT 
            a.timestamp, 
            0 as quantity, 
            'adjustment' as action_type, 
            a.action as details,
            
            CASE 
                WHEN u.username IS NOT NULL THEN 
                    CONCAT(u.username, ' (', u.branch_code, ')')
                
                WHEN a.action LIKE '%[Remote:%' THEN 
                    'Super Admin (Remote)'

                WHEN a.local_user_id > 0 THEN 
                    CONCAT(
                        COALESCE(
                            (SELECT username FROM users WHERE local_id = a.local_user_id AND branch_code = a.branch_code LIMIT 1), 
                            'System'
                        ), 
                        ' (Local)'
                    )
                
                ELSE 'System/Unknown'
            END as username

        FROM audit_logs a
        LEFT JOIN users u ON a.user_id = u.id
        -- [FIX] Search Audit Logs using LOCAL ID (Primary) or Cloud ID (Backup) or Regex Text
        WHERE 
            (a.item_id = ? OR a.item_id = ? OR a.action LIKE ? OR a.action LIKE ?) 
            AND a.branch_code = ?
            AND a.action NOT LIKE 'Sold %'
        )
        
        ORDER BY timestamp DESC
        ";
        
        $stmt = $cloud_conn->prepare($sql);
        
        $cloudPattern = "%(ID:$targetCloudId)%"; 
        $localPattern = "%(ID:$targetLocalId)%";
        
        // [FIX] Bind Params: Cloud ID first for transactions, Local ID first for Logs
        $stmt->bind_param("iisiiiss", 
            $targetCloudId, $targetLocalId, $requested_branch,  // Transactions Params
            $targetLocalId, $targetCloudId, $localPattern, $cloudPattern, $requested_branch // Logs Params
        );        
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $historyEvents[] = $row;
        }
        $cloud_conn->close();

    } else {
                // ... (Local SQLite Logic remains unchanged) ...        // =========================================================
        // MODE B: LOCAL FETCH (SQLite)
        // =========================================================
        
        $sql = "
            SELECT * FROM (
                -- 1. TRANSACTIONS (Sales)
                SELECT 
                    t.transaction_date as timestamp, 
                    COALESCE(u.username || ' (Local)', 'System (POS)') as username, 
                    'sale' as action_type, 
                    -- [FIX] Uses 'i.item_name', so we MUST join the items table below
                    'Sold ' || t.quantity || ' x ' || COALESCE(i.item_name, 'Item') || ' (Ref: #' || t.transaction_group_id || ')' as details
                FROM transactions t
                LEFT JOIN items i ON t.item_id = i.item_id  -- [ADDED] Missing Join Fixed Here
                LEFT JOIN users u ON t.user_id = u.user_id
                WHERE t.item_id = :item_id1 AND t.branch_code = :branch1

                UNION ALL

                -- 2. AUDIT LOGS (Adjustments/Edits)
                SELECT 
                    a.timestamp, 
                    CASE 
                        -- [FIX] Identify Remote Admin Actions Locally
                        WHEN a.action LIKE '%[Remote:%' THEN 'Super Admin (HEAD_OFFICE)'
                        ELSE COALESCE(u.username || ' (Local)', 'System/Unknown')
                    END as username, 
                    'adjustment' as action_type, 
                    a.action as details
                FROM audit_logs a
                LEFT JOIN users u ON a.user_id = u.user_id
                WHERE 
                    (a.item_id = :item_id2 OR a.action LIKE :item_pattern) 
                    AND a.branch_code = :branch2
                    -- [FIX] Prevent Duplicates: Hide 'Sold' entries from Audit Log
                    AND a.action NOT LIKE 'Sold %'
            ) 
            ORDER BY timestamp DESC
        ";

        $stmt = $conn->prepare($sql);
        
        $stmt->bindValue(':item_id1', $itemId, SQLITE3_INTEGER);
        $stmt->bindValue(':branch1', $requested_branch, SQLITE3_TEXT);
        
        $stmt->bindValue(':item_id2', $itemId, SQLITE3_INTEGER); 
        $pattern = "%(ID:$itemId)%"; 
        $stmt->bindValue(':item_pattern', $pattern, SQLITE3_TEXT); 
        $stmt->bindValue(':branch2', $requested_branch, SQLITE3_TEXT);

        $result = $stmt->execute();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $historyEvents[] = $row;
        }
    }

    $users = [];
    if (!empty($historyEvents)) {
        $users = array_unique(array_column($historyEvents, 'username'));
        sort($users);
    }

    echo json_encode([
        'success' => true,
        'history' => $historyEvents,
        'users' => $users
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>