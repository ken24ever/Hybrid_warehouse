<?php
session_start();
require 'connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $role_id = isset($_POST['role_id']) ? intval($_POST['role_id']) : 0;
    $permissions = isset($_POST['permissions']) ? $_POST['permissions'] : [];

    if ($user_id > 0 && $role_id > 0) {
        try {
            // Fetch user info
            $userQuery = "SELECT u.username, u.role_id, r.role_name FROM users u
                          JOIN roles r ON u.role_id = r.role_id
                          WHERE u.user_id = ?";
            $stmt = $conn->prepare($userQuery);
            $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $user = $result->fetchArray(SQLITE3_ASSOC);

            if (!$user) {
                echo json_encode(['status' => 'error', 'message' => 'User not found.']);
                exit;
            }

            $username = $user['username'];
            $current_role_id = $user['role_id'];
            $current_role_name = $user['role_name'];

            // Fetch selected role name
            $roleStmt = $conn->prepare("SELECT role_name FROM roles WHERE role_id = ?");
            $roleStmt->bindValue(1, $role_id, SQLITE3_INTEGER);
            $roleResult = $roleStmt->execute();
            $roleData = $roleResult->fetchArray(SQLITE3_ASSOC);
            $selected_role_name = $roleData['role_name'] ?? '';

            if ($current_role_id !== $role_id) {
                $msg = "Role mismatch! '$username' has '$current_role_name', not '$selected_role_name'.";
                logAudit($conn, $_SESSION['user_id'], $msg);
                echo json_encode(['status' => 'error', 'message' => $msg]);
                exit;
            }

            // Initialize all permissions
            $can_edit_settings = in_array('All', $permissions) ? 1 : 0;
            $can_delete_items = in_array('CDI', $permissions) ? 1 : 0;
            $can_update_items = in_array('CUI', $permissions) ? 1 : 0;
            $can_create_items = in_array('CCI', $permissions) ? 1 : 0;
            $can_delete_users = in_array('CDU', $permissions) ? 1 : 0;
            $can_update_users = in_array('CUU', $permissions) ? 1 : 0;
            $can_create_users = in_array('CCU', $permissions) ? 1 : 0;

            // Upsert user role
            $stmt = $conn->prepare("
                INSERT OR REPLACE INTO user_roles (
                    id, user_id, role_id, 
                    can_edit_settings, can_delete_items, can_update_items, can_create_items, 
                    can_delete_users, can_update_users, can_create_users
                ) 
                VALUES (
                    (SELECT id FROM user_roles WHERE user_id = ? AND role_id = ?),
                    ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
            ");
            $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
            $stmt->bindValue(2, $role_id, SQLITE3_INTEGER);
            $stmt->bindValue(3, $user_id, SQLITE3_INTEGER);
            $stmt->bindValue(4, $role_id, SQLITE3_INTEGER);
            $stmt->bindValue(5, $can_edit_settings, SQLITE3_INTEGER);
            $stmt->bindValue(6, $can_delete_items, SQLITE3_INTEGER);
            $stmt->bindValue(7, $can_update_items, SQLITE3_INTEGER);
            $stmt->bindValue(8, $can_create_items, SQLITE3_INTEGER);
            $stmt->bindValue(9, $can_delete_users, SQLITE3_INTEGER);
            $stmt->bindValue(10, $can_update_users, SQLITE3_INTEGER);
            $stmt->bindValue(11, $can_create_users, SQLITE3_INTEGER);
            $stmt->execute();

            // Build audit message
            $active_permissions = [];
            if ($can_edit_settings) $active_permissions[] = 'All';
            if ($can_delete_items) $active_permissions[] = 'CDI';
            if ($can_update_items) $active_permissions[] = 'CUI';
            if ($can_create_items) $active_permissions[] = 'CCI';
            if ($can_delete_users) $active_permissions[] = 'CDU';
            if ($can_update_users) $active_permissions[] = 'CUU';
            if ($can_create_users) $active_permissions[] = 'CCU';

            $permissions_csv = implode(', ', $active_permissions);
            $action = "Updated permissions for '$username' (Role: $selected_role_name). Granted: [$permissions_csv]";
            logAudit($conn, $_SESSION['user_id'], $action);

            echo json_encode(['status' => 'success', 'message' => 'User role and permissions updated successfully.']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
        }
    } else { 
        echo json_encode(['status' => 'error', 'message' => 'Invalid input.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}

$conn->close();

function logAudit($conn, $user_id, $action) {
    $logStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (?, ?, datetime('now', 'localtime'))");
    $logStmt->bindValue(1, $user_id, SQLITE3_INTEGER);
    $logStmt->bindValue(2, $action, SQLITE3_TEXT);
    $logStmt->execute();
}
