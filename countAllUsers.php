<?php
// countAllUsers.php
// VERSION: DYNAMIC BRANCH CONTEXT (No Hardcoding)
header("Content-Type: application/json");

// 1. Start Session to access Active User's Branch
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Include the Manager
require_once 'DBManager.php'; 

// 3. Resolve Context
// PRIORITY 1: Requested Branch (from URL/JS)
// PRIORITY 2: Active Session Branch (Logged-in User's Branch)
// FALLBACK:  Empty (Will fail gracefully or query global if logic permits)
$active_branch    = $_SESSION['branch_code'] ?? ''; 
$requested_branch = isset($_GET['branch_code']) && !empty($_GET['branch_code']) 
                    ? trim($_GET['branch_code']) 
                    : $active_branch;

// Security Check: If we still don't have a branch, we can't query securely.
if (empty($requested_branch)) {
    echo json_encode(['user_count' => 0, 'error' => 'No branch context determined']);
    exit;
}

// 4. Get Intelligent Connection
// We pass the Dynamic Active Branch as the "Local" fallback, not a hardcoded string.
$dbManager = new DBManager();
$pdo = $dbManager->getConnection($requested_branch, $active_branch);

$userCount = 0;

try {
    // 5. Run Query
    if (!empty($requested_branch)) {
        // Determine which branch code to query
        // If we are in fallback mode (Offline), we can ONLY query the Local (Active) branch.
        $query_branch = $dbManager->is_fallback ? $active_branch : $requested_branch;
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as user_count FROM users WHERE branch_code = :branch");
        $stmt->execute([':branch' => $query_branch]);
        $userCount = $stmt->fetchColumn();
    } else {
        // Global Scope query (if applicable)
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        $userCount = $stmt->fetchColumn();
    }
} catch (Exception $e) {
    // Log error internally, return 0 to frontend
    error_log("CountUsers Error: " . $e->getMessage());
    $userCount = 0; 
}

// 6. Return JSON
echo json_encode([
    'total_users'    => $userCount,
    'viewing_branch' => $requested_branch,
    'is_fallback'    => $dbManager->is_fallback
]);
?>