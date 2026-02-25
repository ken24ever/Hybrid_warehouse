<?php
// setup_ceo.php
// VERSION: SECURE CEO CREDENTIAL MANAGER (UI)
session_start();
error_reporting(0);

// 1. SECURITY LOCK: ONLY ALLOW HOME_OFFICE (CEO)
$current_branch = $_SESSION['branch_code'] ?? '';
$user_role      = $_SESSION['role'] ?? '';

if ($current_branch !== 'HOME_OFFICE' || $user_role !== 'Super Admin') {
    // If not CEO, redirect away for security
    header("Location: hub_dashboard.php");
    exit;
}

$message = '';
$msg_type = '';

// 2. HANDLE FORM SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_username = trim($_POST['username']);
    $new_password = trim($_POST['password']);
    $confirm_pass = trim($_POST['confirm_password']);

    if (empty($new_username) || empty($new_password)) {
        $message = "All fields are required.";
        $msg_type = "danger";
    } elseif ($new_password !== $confirm_pass) {
        $message = "Passwords do not match.";
        $msg_type = "warning";
    } else {
        // CONNECT TO CLOUD
        $host = 'srv1254.hstgr.io';
        $user = 'u106033383_jemerald1234';
        $pass = 'Wearelive_1234';
        $db   = 'u106033383_jemerald_cloud';

        $mysqli = @new mysqli($host, $user, $pass, $db);

        if ($mysqli->connect_error) {
            $message = "Cloud Connection Failed: " . $mysqli->connect_error;
            $msg_type = "danger";
        } else {
            // HASH PASSWORD
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $branch   = 'HOME_OFFICE';

            // UPDATE OR INSERT
            // We use ON DUPLICATE KEY UPDATE logic logic via standard INSERT/UPDATE flow
            
            // Check if user exists
            $check = $mysqli->query("SELECT id FROM users WHERE branch_code = '$branch' AND role_name = 'Super Admin'");
            
            if ($check->num_rows > 0) {
                // Update Existing
                $stmt = $mysqli->prepare("UPDATE users SET username = ?, password = ? WHERE branch_code = ? AND role_name = 'Super Admin'");
                $stmt->bind_param("sss", $new_username, $new_hash, $branch);
            } else {
                // Create New (Recovery)
                $stmt = $mysqli->prepare("INSERT INTO users (branch_code, username, password, role_name, fullname, status) VALUES (?, ?, ?, 'Super Admin', 'Jemerald CEO', 1)");
                $stmt->bind_param("sss", $branch, $new_username, $new_hash);
            }

            if ($stmt->execute()) {
                $message = "CEO Credentials Updated Successfully!";
                $msg_type = "success";
                // Update Session immediately so they don't get logged out
                $_SESSION['username'] = $new_username;
            } else {
                $message = "Database Error: " . $stmt->error;
                $msg_type = "danger";
            }
            $stmt->close();
            $mysqli->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage CEO Access | Jemerald Enterprise</title>
    <link rel="stylesheet" href="bootstrap_v4/css/bootstrap.min.css">
    <link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .access-card {
            background: #ffffff;
            width: 100%;
            max-width: 500px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
            border: none;
        }
        .card-header-custom {
            background: linear-gradient(to right, #6a11cb 0%, #2575fc 100%);
            padding: 30px;
            text-align: center;
            color: white;
        }
        .avatar-circle {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 2rem;
            border: 2px solid rgba(255,255,255,0.5);
        }
        .form-control-lg {
            border-radius: 10px;
            font-size: 1rem;
            padding: 25px 20px;
            border: 2px solid #e1e5ea;
        }
        .form-control-lg:focus {
            box-shadow: none;
            border-color: #2575fc;
        }
        .btn-update {
            background: #2575fc;
            border: none;
            padding: 15px;
            border-radius: 10px;
            font-weight: 600;
            letter-spacing: 1px;
            box-shadow: 0 4px 15px rgba(37, 117, 252, 0.3);
            transition: all 0.3s;
        }
        .btn-update:hover {
            background: #1a65e6;
            transform: translateY(-2px);
        }
        .input-group-text {
            background: white;
            border: 2px solid #e1e5ea;
            border-left: 0;
            border-radius: 0 10px 10px 0;
            cursor: pointer;
        }
        .input-group .form-control {
            border-right: 0;
        }
    </style>
</head>
<body>

    <div class="access-card">
        <div class="card-header-custom">
            <div class="avatar-circle">
                <i class="fas fa-user-shield"></i>
            </div>
            <h3 class="font-weight-bold mb-0">CEO Access Manager</h3>
            <p class="mb-0 opacity-75 small">Update Secure Remote Credentials</p>
        </div>
        
        <div class="card-body p-4 p-md-5">
            
            <?php if(!empty($message)): ?>
                <div class="alert alert-<?php echo $msg_type; ?> shadow-sm rounded-lg mb-4">
                    <i class="fas <?php echo ($msg_type == 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group mb-4">
                    <label class="font-weight-bold text-muted small text-uppercase">Username</label>
                    <input type="text" name="username" class="form-control form-control-lg" value="<?php echo htmlspecialchars($_SESSION['username']); ?>" required>
                </div>

                <div class="form-group mb-4">
                    <label class="font-weight-bold text-muted small text-uppercase">New Password</label>
                    <div class="input-group">
                        <input type="password" name="password" id="pass1" class="form-control form-control-lg" placeholder="Enter new password" required>
                        <div class="input-group-append" onclick="togglePass('pass1')">
                            <span class="input-group-text"><i class="fas fa-eye text-muted"></i></span>
                        </div>
                    </div>
                </div>

                <div class="form-group mb-5">
                    <label class="font-weight-bold text-muted small text-uppercase">Confirm Password</label>
                    <div class="input-group">
                        <input type="password" name="confirm_password" id="pass2" class="form-control form-control-lg" placeholder="Confirm new password" required>
                        <div class="input-group-append" onclick="togglePass('pass2')">
                            <span class="input-group-text"><i class="fas fa-eye text-muted"></i></span>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-update btn-lg text-white">
                    <i class="fas fa-save mr-2"></i> UPDATE CREDENTIALS
                </button>

                <div class="text-center mt-4">
                    <a href="hub_dashboard.php" class="text-muted font-weight-bold" style="text-decoration: none;">
                        <i class="fas fa-arrow-left mr-1"></i> Back to Dashboard
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function togglePass(id) {
            var input = document.getElementById(id);
            if (input.type === "password") {
                input.type = "text";
            } else {
                input.type = "password";
            }
        }
    </script>
</body>
</html>