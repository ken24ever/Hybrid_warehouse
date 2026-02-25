<?php
// api/get_live_stats.php
// VERSION: ACCURATE TIME AGO + SQL HEARTBEAT
header('Content-Type: application/json');

$CACHE_FILE = 'live_stats_cache.json';
$CACHE_TIME = 60;  

// 1. CHECK CACHE
if (file_exists($CACHE_FILE) && (time() - filemtime($CACHE_FILE) < $CACHE_TIME)) {
    readfile($CACHE_FILE);
    exit;
}

// 2. FETCH FRESH DATA
require_once '../DBManager.php';
$dbManager = new DBManager();
$response = [];

try {
    $pdo = $dbManager->getConnection('GLOBAL_DASHBOARD');  
    
    if ($dbManager->current_source === 'cloud') {
        // [FIX] We now query 'last_active_at' directly.
        // [FIX] We calculate 'seconds_ago' in SQL to prevent Timezone errors. 
        $sql = "
            SELECT 
                b.branch_code,
                b.branch_name, 
                b.location,
                b.last_active_at,
                TIMESTAMPDIFF(SECOND, b.last_active_at, NOW()) as seconds_ago,
                COALESCE((SELECT SUM(total_amount) FROM transactions WHERE branch_code = b.branch_code AND status = 0 AND DATE(transaction_date) = CURDATE()), 0) as sales_today
            FROM branches b
            WHERE b.branch_code != 'HOME_OFFICE'
            ORDER BY b.last_active_at DESC
        ";

        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $branches = [];
        foreach ($rows as $row) {
            // Pass the SQL-calculated seconds directly to the formatter
            $formatted = formatBranchData($row['seconds_ago']);
            
            $branches[] = [
                'uuid' => $row['branch_code'],
                'name' => $row['branch_name'],
                'location' => $row['location'],
                'status' => $formatted['is_online'] ? 'online' : 'offline',
                'last_sync' => $formatted['time_diff'],
                'sales_today' => '₦ ' . number_format($row['sales_today'], 2)
            ];
        }

        $response = [
            'meta' => [
                'source' => 'cloud',
                'timestamp' => date('Y-m-d H:i:s')
            ],
            'data' => $branches
        ];

        file_put_contents($CACHE_FILE, json_encode($response));
    
    } else {
        throw new Exception($dbManager->connection_error);
    }

} catch (Exception $e) {
    // Fail-safe logic (Cache or Error)
    if (file_exists($CACHE_FILE)) {
        $response = json_decode(file_get_contents($CACHE_FILE), true);
        $response['meta']['source'] = 'cache (stale)';
    } else {
        $response = [
            'meta' => ['source' => 'local', 'error' => $e->getMessage()],
            'data' => [['uuid' => 'HEAD_OFFICE', 'name' => 'Main Warehouse (Local)', 'status' => 'offline', 'last_sync' => 'Check Network', 'sales_today' => '₦ 0.00']]
        ];
    }
}

echo json_encode($response);

// --- REVISED HELPER FUNCTION ---
function formatBranchData($seconds_ago) {
    $is_online = false;
    $time_diff_str = "Never";

    // If seconds_ago is NULL (never synced), return default
    if ($seconds_ago === null) {
        return ['is_online' => false, 'time_diff' => 'Never'];
    }

    $seconds_ago = (int)$seconds_ago;

    // Logic: Online if active in last 5 mins (300 seconds)
    if ($seconds_ago < 300) { 
        $is_online = true; 
    }

    // Precise Time Ago Formatting
    if ($seconds_ago < 60) {
        $time_diff_str = "Just now";
    } elseif ($seconds_ago < 3600) {
        $mins = floor($seconds_ago / 60);
        $time_diff_str = $mins . ($mins == 1 ? " min ago" : " mins ago");
    } elseif ($seconds_ago < 86400) {
        $hours = floor($seconds_ago / 3600);
        $time_diff_str = $hours . ($hours == 1 ? " hour ago" : " hours ago");
    } else {
        $days = floor($seconds_ago / 86400);
        $time_diff_str = $days . ($days == 1 ? " day ago" : " days ago");
    }
    
    return ['is_online' => $is_online, 'time_diff' => $time_diff_str];
}
?>