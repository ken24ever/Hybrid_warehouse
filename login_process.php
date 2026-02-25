<?php
// login_process.php
// VERSION: DUAL-SOURCE AUTH (Local SQLite + Cloud MySQL Fallback)
session_start();
include('connection.php'); // Local SQLite Connection

header('Content-Type: application/json');

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Credentials missing.']);
    exit;
}

// =======================================================================
// AUTHENTICATION PHASE 1: LOCAL SQLITE (Standard Staff)
// =======================================================================
$query = "SELECT u.*, r.role_name 
          FROM users u
          JOIN roles r ON u.role_id = r.role_id
          WHERE u.username = :username";

$stmt = $conn->prepare($query);
$stmt->bindValue(':username', $username, SQLITE3_TEXT);
$result = $stmt->execute();
$local_user_found = false;

if ($row = $result->fetchArray(SQLITE3_ASSOC)) { 
    // User found locally, verify password
    if (password_verify($password, $row['password'])) {
        $local_user_found = true;
        establishSession($row);
        
        // Audit Log (Local)
        logLocalAudit($conn, $row['user_id'], $row['branch_code']);
        
        echo json_encode([
            'success' => true,
            'role' => $row['role_name'],
            'branch_name' => resolveBranchName($conn, $row['branch_code']),
            'source' => 'local'
        ]);
        exit;
    } else {
        // Found locally but wrong password -> Fail immediately (Security)
        echo json_encode(['success' => false, 'message' => 'Invalid credentials.']);
        exit;
    }
}

// =======================================================================
// AUTHENTICATION PHASE 2: CLOUD MYSQL (CEO / Remote Fallback)
// =======================================================================
if (!$local_user_found) {
    
    // 1. Connect to Cloud
    $cloud_host = 'srv1254.hstgr.io';
    $cloud_user = 'u106033383_jemerald1234';
    $cloud_pass = 'Wearelive_1234';
    $cloud_db   = 'u106033383_jemerald_cloud';

    try {
        $cloud_conn = @new mysqli($cloud_host, $cloud_user, $cloud_pass, $cloud_db);
        
        if ($cloud_conn->connect_error) {
            throw new Exception("Local user not found and Cloud unreachable.");
        }

        // 2. Query Cloud Users
        $cStmt = $cloud_conn->prepare("SELECT * FROM users WHERE username = ? AND status = 1");
        $cStmt->bind_param("s", $username);
        $cStmt->execute();
        $cResult = $cStmt->get_result();

        if ($cloudRow = $cResult->fetch_assoc()) {
            // 3. Verify Password
            if (password_verify($password, $cloudRow['password'])) {
                
                // 4. Mimic Local Session Structure
                // Note: Remote users might not have a numeric role_id compatible with local DB, 
                // so we rely on the string 'role_name' which must be 'Super Admin'.
                $sessionData = [
                    'user_id' => $cloudRow['id'] * -1, // Negative ID to indicate Remote User
                    'username' => $cloudRow['username'],
                    'role_name' => $cloudRow['role_name'],
                    'branch_code' => $cloudRow['branch_code']
                ];
                
                establishSession($sessionData);

                // Audit Log (Remote - via Cloud Connection)
                // We log this login attempt to the Cloud Audit Log since Local DB doesn't know this user
                $logAction = "Remote Login: " . $cloudRow['username'];
                $logBranch = $cloudRow['branch_code'];
                $pseudoId  = -1 * mt_rand(1000, 999999);
                
                $logQ = $cloud_conn->prepare("INSERT INTO audit_logs (local_id, branch_code, local_user_id, action, timestamp) VALUES (?, ?, ?, ?, NOW())");
                $logQ->bind_param("isis", $pseudoId, $logBranch, $sessionData['user_id'], $logAction);
                $logQ->execute();

                echo json_encode([
                    'success' => true,
                    'role' => $cloudRow['role_name'],
                    'branch_name' => 'CEO Remote Hub', // Custom Name for HOME_OFFICE
                    'source' => 'cloud'
                ]);
                $cloud_conn->close();
                exit;

            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid credentials (Remote).']);
                exit;
            }
        }
        $cloud_conn->close();

    } catch (Exception $e) {
        // Silently fail to generic message to prevent username enumeration
    }
}

// If we reached here, neither Local nor Cloud authenticated the user
echo json_encode(['success' => false, 'message' => 'User not found.']);
exit;


// =======================================================================
// HELPER FUNCTIONS
// =======================================================================

function establishSession($data) {
    $_SESSION['user_id']  = $data['user_id'];
    $_SESSION['username'] = $data['username'];
    $_SESSION['role']     = $data['role_name']; // Critical: Must be 'Super Admin'
    
    // Branch Context
    $branch_code = !empty($data['branch_code']) ? $data['branch_code'] : 'Unknown';
    $_SESSION['branch_code'] = $branch_code;
    
    // For CEO/Home Office, set a friendly name manually if DB lookup fails later
    if ($branch_code === 'HOME_OFFICE') {
        $_SESSION['branch_name'] = 'CEO Personal Hub';
    }
}

function resolveBranchName($conn, $branch_code) {
    $branch_name = $branch_code; 
    
    // Safe check if table exists
    $checkTable = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='branches'");
    if ($checkTable->fetchArray()) {
        $bStmt = $conn->prepare("SELECT branch_name FROM branches WHERE branch_code = :code");
        if ($bStmt) {
            $bStmt->bindValue(':code', $branch_code, SQLITE3_TEXT);
            $bRes = $bStmt->execute();
            if ($bRow = $bRes->fetchArray(SQLITE3_ASSOC)) {
                $branch_name = $bRow['branch_name'];
            }
        }
    }
    $_SESSION['branch_name'] = $branch_name;
    return $branch_name;
}

function logLocalAudit($conn, $userId, $branchCode) {
    $action = "Logged-in at " . date('Y-m-d H:i:s');
    $logStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, timestamp, sync_status, branch_code) VALUES (:u, :a, datetime('now'), 0, :b)");
    $logStmt->bindValue(':u', $userId, SQLITE3_INTEGER);
    $logStmt->bindValue(':a', $action, SQLITE3_TEXT);
    $logStmt->bindValue(':b', $branchCode, SQLITE3_TEXT);
    $logStmt->execute();
}
?>