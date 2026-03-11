<?php
// get_branches.php
session_start();

// Disable display errors to ensure clean JSON output 
error_reporting(E_ALL);
ini_set('display_errors', 0);


$branches = [];
$cloud_success = false;

// --- 1. CLOUD CONFIGURATION (HOSTINGER) ---
// Update these with your actual Hostinger Remote MySQL details
$cloud_host = 'srv1254.hstgr.io'; // or your specific IP/Hostname
$cloud_user = 'u106033383_jemerald1234';   // Your Database Username
$cloud_pass = 'Wearelive_1234';    // Your Database Password
$cloud_name = 'u106033383_jemerald_cloud';   // Your Database Name

// --- 2. ATTEMPT CLOUD CONNECTION ---
try {
    // Initialize MySQLi with a short timeout (e.g., 2 seconds) to avoid hanging
    $cloud_conn = mysqli_init();
    $cloud_conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 2);

    // Attempt connection (suppress warnings with @)
    if (@$cloud_conn->real_connect($cloud_host, $cloud_user, $cloud_pass, $cloud_name)) {
        
        $query = "SELECT branch_code, branch_name FROM branches ORDER BY branch_name ASC";
        $result = $cloud_conn->query($query);

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $branches[] = $row;
            }
            $cloud_success = true;
        }
        $cloud_conn->close();
    }
} catch (Exception $e) {
    // Cloud connection failed; proceed to fallback
    error_log("Cloud Fetch Error: " . $e->getMessage());
}

// --- 3. FALLBACK TO LOCAL DATABASE (SQLITE) ---
// If cloud failed or returned no data, use the local copy
if (!$cloud_success || empty($branches)) {
    if (file_exists('connection.php')) {
        include('connection.php'); // Uses your existing SQLite connection ($conn)

        // Check if branches table exists locally to prevent errors
        $checkTable = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='branches'");
        
        if ($checkTable && $checkTable->fetchArray()) {
            $query = "SELECT branch_code, branch_name FROM branches ORDER BY branch_name ASC";
            $result = $conn->query($query);

            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $branches[] = $row;
            }
        }
    }
}

// --- 4. ENSURE ACTIVE SESSION BRANCH EXISTS ---
$hasSessionBranch = false;
$sessionBranchCode = $_SESSION['branch_code'] ?? null;
$sessionBranchName = $_SESSION['branch_name'] ?? $sessionBranchCode;

if ($sessionBranchCode) {
    foreach ($branches as $b) {
        if ($b['branch_code'] === $sessionBranchCode) {
            $hasSessionBranch = true;
            break;
        }
    }

    if (!$hasSessionBranch) {
        array_unshift($branches, [
            'branch_code' => $sessionBranchCode,
            'branch_name' => $sessionBranchName . ' (Active View)'
        ]);
    }
}

// --- 5. RETURN JSON ---
header('Content-Type: application/json');
echo json_encode($branches);
?>