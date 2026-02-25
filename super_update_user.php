<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include('connection.php');

    $user_id = $_POST['editUserId'];
    $new_username = $_POST['editUsername'];
    $new_role_name = $_POST['editRole'];
    $userPass = isset($_POST['password']) && !empty($_POST['password']) ? $_POST['password'] : null;
    $comment = isset($_POST['comment']) && !empty($_POST['comment']) ? $_POST['comment'] : null;
    $logged_in_user_id = $_SESSION['user_id']; // Authenticated user

    try {
        $conn->exec("BEGIN TRANSACTION");

        // Step 1: Fetch the logged-in user's role and permissions
        $stmt = $conn->prepare("
            SELECT 
                r.role_name, 
                ur.can_edit_settings, 
                ur.can_update_users
            FROM users u
            JOIN roles r ON u.role_id = r.role_id
            LEFT JOIN user_roles ur ON u.user_id = ur.user_id
            WHERE u.user_id = ?
        ");
        $stmt->bindParam(1, $logged_in_user_id, SQLITE3_INTEGER);
        $authUser = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if (!$authUser) {
            throw new Exception("Authenticated user not found.");
        }

        $authRole = $authUser['role_name'];
        $canEdit = (int) ($authUser['can_edit_settings'] ?? 0);
        $canUpdateUsers = (int) ($authUser['can_update_users'] ?? 0);

        // Permission check for Admin role
        if ($authRole === 'Admin') {
            if ($canEdit !== 1 && $canUpdateUsers !== 1) {
                throw new Exception("Access denied: You do not have permission to update user details.");
            }
        }

        // Step 2: Fetch current user data
        $stmt = $conn->prepare("
            SELECT u.username, r.role_name, u.password, u.reasons
            FROM users u
            JOIN roles r ON u.role_id = r.role_id
            WHERE u.user_id = ?
        ");
        $stmt->bindParam(1, $user_id, SQLITE3_INTEGER);
        $current_user = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if (!$current_user) {
            throw new Exception("Target user not found.");
        }

        $current_username = $current_user['username'];
        $current_role_name = $current_user['role_name'];
        $current_password = $current_user['password'];
        $current_comment = $current_user['reasons'];

        // Track changes
        $changes = [];
        if ($new_username !== $current_username) {
            $changes[] = "Changed username from '$current_username' to '$new_username'";
        }
        if ($new_role_name !== $current_role_name) {
            $changes[] = "Changed role from '$current_role_name' to '$new_role_name'";
        }

        if ($userPass && !password_verify($userPass, $current_password)) {
            $changes[] = "Updated password for user: $new_username";
            $hashedPassword = password_hash($userPass, PASSWORD_DEFAULT);
        } else {
            $hashedPassword = $current_password; 
        }

        if ($comment !== $current_comment) {
            $changes[] = "Updated comment/reason from '$current_comment' to '$comment'";
        }

        // Only update if there are changes
        if (!empty($changes)) {
            
            // ------------------------------------------------------------------
            // [PROFESSIONAL FIX] DYNAMIC CONTEXT & DUAL-WRITE LOGIC
            // ------------------------------------------------------------------
            
            // 1. Resolve Context Dynamically (No Hardcoding)
            $session_branch = $_SESSION['branch_code'] ?? 'UNKNOWN_BRANCH';
            // Allow frontend to pass a specific branch, otherwise fallback to session
            $target_branch = $_POST['branch_code'] ?? $session_branch; 
            
            $sync_status = 0; // Default to 0 (Offline/Unsynced)
            
            // 2. Attempt Cloud Update FIRST
            try {
                $cloud_conn = @new mysqli('srv1254.hstgr.io', 'u106033383_jemerald1234', 'Wearelive_1234', 'u106033383_jemerald_cloud');
                
                if ($cloud_conn && !$cloud_conn->connect_error) {
                    
                    // Prepare Cloud SQL
                    $cStmt = $cloud_conn->prepare("UPDATE users SET username = ?, password = ?, role_name = ?, reasons = ? WHERE local_id = ? AND branch_code = ?");
                    if ($cStmt) {
                        $cStmt->bind_param("ssssis", $new_username, $hashedPassword, $new_role_name, $comment, $user_id, $target_branch);
                        
                        if ($cStmt->execute() && $cStmt->affected_rows >= 0) {
                            
                            // Fetch Cloud ID to register in the Change Log
                            $cloud_id = null;
                            $cIdStmt = $cloud_conn->prepare("SELECT id FROM users WHERE local_id = ? AND branch_code = ?");
                            if ($cIdStmt) {
                                $cIdStmt->bind_param("is", $user_id, $target_branch);
                                $cIdStmt->execute();
                                $res = $cIdStmt->get_result();
                                if ($row = $res->fetch_assoc()) {
                                    $cloud_id = $row['id'];
                                    
                                    // Register the Update for Syncing (To trigger downloads by other devices)
                                    $clStmt = $cloud_conn->prepare("INSERT INTO cloud_change_log (table_name, record_id, branch_code, action_type, created_at) VALUES ('users', ?, ?, 'UPDATE', NOW())");
                                    if ($clStmt) {
                                        $clStmt->bind_param("is", $cloud_id, $target_branch);
                                        $clStmt->execute();
                                        $clStmt->close();
                                    }
                                }
                                $cIdStmt->close();
                            }

                            // Mark as successfully synced!
                            $sync_status = 1; 
                        }
                        $cStmt->close();
                    }
                    $cloud_conn->close();
                }
            } catch (Exception $e) {
                // Silently bypass cloud errors. sync_status safely remains 0 (pending sync).
            }

            // 3. Execute Local Update (With Dynamic Sync Status)
            $stmt = $conn->prepare("
                UPDATE users 
                SET username = ?, 
                    password = ?, 
                    role_id = (SELECT role_id FROM roles WHERE role_name = ? LIMIT 1), 
                    reasons = ?,
                    sync_status = ?
                WHERE user_id = ?
            ");
            
            $stmt->bindParam(1, $new_username, SQLITE3_TEXT);
            $stmt->bindParam(2, $hashedPassword, SQLITE3_TEXT);
            $stmt->bindParam(3, $new_role_name, SQLITE3_TEXT);
            $stmt->bindParam(4, $comment, SQLITE3_TEXT);
            $stmt->bindParam(5, $sync_status, SQLITE3_INTEGER); // Toggle between 0 and 1
            $stmt->bindParam(6, $user_id, SQLITE3_INTEGER);

            if (!$stmt->execute()) {
                throw new Exception("Failed to update user locally.");
            }
            // ------------------------------------------------------------------

            // Log each change
            foreach ($changes as $change) {
                // (Safely suppress sqlite warning if rollback fallback happens)
                $logStmt = $conn->prepare("
                    INSERT INTO audit_logs (user_id, action, timestamp) 
                    VALUES (?, ?, datetime('now', 'localtime'))
                ");
                $logStmt->bindParam(1, $logged_in_user_id, SQLITE3_INTEGER);
                $logStmt->bindParam(2, $change, SQLITE3_TEXT);
                $logStmt->execute();
            }

            $conn->exec("COMMIT");
            $response = ['success' => true, 'message' => 'User updated successfully', 'changes' => $changes];
        } else {
            // No changes, cleanly rollback the initial start
            if (isset($conn) && $conn) {
                @$conn->exec("ROLLBACK");
            }
            $response = ['success' => false, 'message' => 'No changes detected.'];
        }
    } catch (Exception $e) {
        if (isset($conn) && $conn) {
            @$conn->exec("ROLLBACK");
        }
        $response = ['success' => false, 'message' => "Error: " . $e->getMessage()];
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>