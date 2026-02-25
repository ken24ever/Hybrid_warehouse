<?php
session_start(); 

$filter_branch_name = isset($_GET['branch_name']) ? urldecode($_GET['branch_name']) : "Global Scope";

if (isset($_SESSION['role'])) {
    $user_role = $_SESSION['role'];
    if ($user_role === "Admin" || $user_role === "Super Admin") {
        $userID = $_SESSION['user_id'];
        $userName = $_SESSION['username'];
        $userRole = $_SESSION['role'];
      } else {
        header("Location:404.php");
        exit;
      }
} else {
    header("Location:404.php"); 
    exit;  
}

// --- 2. DYNAMIC CONTEXT RESOLUTION ---
// Priority 1: URL Parameter (Super Admin viewing another branch)
// Priority 2: Session Branch (User viewing their own data)
$filter_branch_uuid = isset($_GET['branch_uuid']) ? $_GET['branch_uuid'] : null;
$filter_branch_name = isset($_GET['branch_name']) ? urldecode($_GET['branch_name']) : null;
$session_branch     = $_SESSION['branch_code'] ?? 'UNKNOWN_BRANCH'; 

// The Final Context for this page load
$page_context_branch = $filter_branch_uuid ? $filter_branch_uuid : $session_branch;
$page_context_name   = isset($_GET['branch_name']) ? urldecode($_GET['branch_name']) : $page_context_branch;

// Ensure we have a valid context
if (empty($page_context_branch)) {
    $page_context_branch = 'UNKNOWN_BRANCH'; 
}

// Helper function for links
function linkTo($url) {
    global $filter_branch_uuid, $page_context_name;
    if ($filter_branch_uuid) {
        $sep = (strpos($url, '?') !== false) ? '&' : '?';
        return $url . $sep . "branch_uuid=" . urlencode($filter_branch_uuid) . "&branch_name=" . urlencode($page_context_name);
    }
    return $url;
}

// Set Dashboard Titles based on Context
if ($filter_branch_uuid) {
    $dashboard_title = "Branch View: " . htmlspecialchars($filter_branch_name);
    $badge_class = "badge-info";
} else {
    $dashboard_title = "Global Enterprise Overview";
    $badge_class = "badge-primary";
}
?>

<?php
require 'auto_logout_script.php';
require 'defined_global_settings.php'
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>IKA Admin</title>
  <!-- plugins:css -->
  <link rel="stylesheet" href="vendors/feather/feather.css">
  <link rel="stylesheet" href="vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="vendors/css/vendor.bundle.base.css">
  <!-- endinject -->
  <!-- Plugin css for this page -->
  <link rel="stylesheet" href="vendors/datatables.net-bs4/dataTables.bootstrap4.css">
  <link rel="stylesheet" href="vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" type="text/css" href="js/select.dataTables.min.css">
  <!-- End plugin css for this page -->
  <!-- inject:css -->
  <link rel="stylesheet" href="css/vertical-layout-light/style.css">
  <!-- endinject -->
  <link rel="shortcut icon" href="<?php echo (defined('BUSINESS_LOGO') && !empty(BUSINESS_LOGO)) ? BUSINESS_LOGO : 'images/favicon.png'; ?>" />
   <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css">
   <!-- sweet  alert 2 lib -->
<link rel="stylesheet" href="sweetalert2/dist/sweetalert2.min.css">
<script src="sweetalert2/dist/sweetalert2.all.min.js"></script>

      <!-- toast styling effect -->
      <link rel="stylesheet" href="node_modules/toastify-js/src/toastify.css" />
    
<!-- dynamic logic for dark theme and light theme -->
<?php if (defined('DARK_MODE') && DARK_MODE == 1): ?>
    <link rel="stylesheet" type="text/css" href="css/dark-theme.css">
<?php else: ?>
    <link rel="stylesheet" type="text/css" href="css/light-theme.css">
<?php endif; ?>

    <!-- jquery lib -->
    <script src="jquery/jquery-3.6.0.min.js"></script>

    <!-- Local Tippy.js CSS -->
<link rel="stylesheet" href="node_modules/tippy.js/dist/tippy.css">

<!-- Local Popper.js (UMD version for browser compatibility) -->
<script src="node_modules/@popperjs/core/dist/umd/popper.js"></script>

<!-- Local Tippy.js (UMD version for browser compatibility) -->
<script src="node_modules/tippy.js/dist/tippy.umd.min.js"></script>

  <!-- custom js files -->
  <script src="js/create_role.js"></script>
  <script src="js/view_users.js"></script>
 <script src="js/create_users.js"></script>   
 <script src="js/alpine.min.js" defer></script> 

 <script>
 // Convert minutes to milliseconds
var autoLogoutMinutes = <?php echo $inactivityMinutes; ?>;
var autoLogoutMilliseconds = autoLogoutMinutes * 60 * 1000;

// Warning period (in milliseconds) before the actual logout (e.g., 30 seconds)
var warningPeriod = 30 * 1000;

// Timer variables
var inactivityTimer;
var logoutWarningTimer;

// Function to perform auto logout via AJAX
function performLogout() {
    $.ajax({
        url: 'auto_logout.php',
        type: 'POST',
        dataType: 'json',
        success: function(response) {
            // Redirect to the login page after successful logout
            window.location.href = 'index.php';
        },
        error: function() {
            // In case of error, force redirect
            window.location.href = 'index.php';
        }
    });
}

// Function to show the interactive logout warning
function showLogoutWarning() {
    // Use SweetAlert2 to display an interactive warning with a 30-second countdown.
    let timerInterval;
    Swal.fire({
        title: 'Inactivity Detected',
        html: 'You will be logged out in <strong></strong> seconds. <br>Click "Stay Logged In" to continue your session.',
        icon: 'warning',
        timer: warningPeriod,
        timerProgressBar: true,
        allowOutsideClick: false,
        allowEscapeKey: false,
        showCancelButton: true,
        confirmButtonText: 'Stay Logged In',
        cancelButtonText: 'Logout Now',
        didOpen: () => {
            const content = Swal.getHtmlContainer();
            const b = content.querySelector('strong');
            timerInterval = setInterval(() => {
                b.textContent = Math.ceil(Swal.getTimerLeft() / 1000);
            }, 1000);
        },
        willClose: () => {
            clearInterval(timerInterval);
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // User chose to stay logged in; reset timers.
            resetInactivityTimer();
        } else {
            // User cancelled or timer elapsed: perform logout.
            performLogout();
        }
    });
}

// Function to reset the inactivity timer
function resetInactivityTimer() {
    clearTimeout(inactivityTimer);
    clearTimeout(logoutWarningTimer);
    // Set a new timer that triggers the warning period BEFORE auto logout.
    inactivityTimer = setTimeout(function() {
        showLogoutWarning();
    }, autoLogoutMilliseconds - warningPeriod);
}

// Reset timer on common user activity events
$(document).on('mousemove keypress click scroll', function() {
    resetInactivityTimer();
});

// Start the inactivity timer on page load
$(document).ready(function() {
    resetInactivityTimer();
});
</script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    document.body.style.zoom = "70%"; // Adjust for more/less zoom
});
</script>
<style>
           /* SYNC HUD (Bottom Right) */
    .sync-hud {
        position: fixed; bottom: 20px; right: 20px; z-index: 9999;
        background: white; border-radius: 8px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        padding: 15px; width: 300px;
        border-left: 5px solid #ccc;
        font-family: 'Segoe UI', sans-serif;
        transition: transform 0.3s ease, opacity 0.3s ease;
    }
    .sync-hud.status-offline { border-left-color: #ffc107; } /* Amber */
    .sync-hud.status-online  { border-left-color: #28a745; } /* Green */
    .sync-hud.status-syncing { border-left-color: #17a2b8; } /* Blue */

    .sync-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
    .sync-title { font-weight: 700; font-size: 0.9rem; color: #333; margin: 0; }
    .sync-status-text { font-size: 0.75rem; color: #666; }
    
    /* Progress Bar */
    .sync-progress-bg { width: 100%; height: 6px; background: #f1f1f1; border-radius: 3px; overflow: hidden; }
    .sync-progress-fill { height: 100%; background: #17a2b8; width: 0%; transition: width 0.5s ease; }
    .sync-progress-fill.success { background: #28a745; }
    
    /* Icons/Animations */
    .spin-sync { animation: spin 1s linear infinite; color: #17a2b8; }
    @keyframes spin { 100% { transform: rotate(360deg); } }
</style>
</head>
<body class="dark-theme light-theme">

<center><div id="backUpStatus" style="position: relative;"></div></center>

<div x-data="backupSystem()" x-init="init()">
    <button @click="confirmBackup()" style="padding: 10px; background: #3085d6; color: white; border: none; cursor: pointer;">
        Start Backup
    </button>

    <!-- Progress UI -->
    <div x-show="isBackingUp" class="progress-container">
        <div class="progress-bar" :style="'width:' + progress + '%'"></div>
        <p x-text="'Progress: ' + progress + '%'"></p>
    </div>

    <!-- Tippy.js Notification Container -->
    <div id="tippy-container" style="position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 1000;"></div>
</div>

<script>
    document.addEventListener("alpine:init", () => {
        Alpine.data("backupSystem", backupSystem);
    });

    function backupSystem() {
        return {
            isBackingUp: false,
            progress: 0,
            nextBackupTime: null,

            init() {
                this.loadNextBackupTime();
                
                // Auto-check for backup every 1 minute (60,000ms)
                setInterval(() => {
                    this.confirmBackup();
                }, 60000);
            },

            async getLastBackupTime() {
                let lastBackupTime = localStorage.getItem("lastBackupTime");

                if (lastBackupTime) {
                    return parseInt(lastBackupTime);
                } else {
                    // Fetch from JSON file if localStorage is cleared
                    try {
                        let response = await fetch("backup-time.json");
                        let data = await response.json();
                        return data.lastBackupTime ? parseInt(data.lastBackupTime) : null;
                    } catch (error) {
                        return null;
                    }
                }
            },

            async confirmBackup() {
                let lastBackup = await this.getLastBackupTime();
                let oneWeek = 7 * 24 * 60 * 60 * 1000; // 7 days in milliseconds

                if (!lastBackup || (Date.now() - lastBackup) > oneWeek) {
                    Swal.fire({
                        title: "Backup Initialization",
                        text: "A scheduled backup is due. Do you want to proceed?",
                        icon: "info",
                        showCancelButton: true,
                        confirmButtonColor: "#3085d6",
                        cancelButtonColor: "#d33",
                        confirmButtonText: "Yes, Start Backup",
                        cancelButtonText: "No, Cancel",
                        allowOutsideClick: false,
                        allowEscapeKey: false
                    }).then((result) => {
                        if (result.isConfirmed) {
                            this.startBackup();
                        }
                    });
                } else {
                    this.showTippy("✔️ Backup is not yet due!", "success");
                }
            },

            scheduleNextBackup(lastBackupTime) {
                let oneWeek = 7 * 24 * 60 * 60 * 1000; // 7 days in milliseconds
                let nextBackup = new Date(lastBackupTime + oneWeek); // Schedule exactly 1 week after last backup

                // Persist the next backup time
                localStorage.setItem("nextBackupTime", nextBackup.getTime().toString());

                this.nextBackupTime = nextBackup.toLocaleString();
                this.showTippy(`⏳ Next backup scheduled for ${this.nextBackupTime}`, "info");

                // Also save to backup-time.json
                fetch("save-backup-time.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ lastBackupTime: lastBackupTime })
                });
            },

            async loadNextBackupTime() {
                let nextBackup = Number(localStorage.getItem("nextBackupTime")) || 0;

                if (!nextBackup) {
                    let lastBackup = await this.getLastBackupTime();
                    if (lastBackup) {
                        this.scheduleNextBackup(lastBackup);
                        return;
                    }
                }

                if (nextBackup > Date.now()) {
                    this.nextBackupTime = new Date(nextBackup).toLocaleString();
                    this.showTippy(`⏳ Next backup scheduled for ${this.nextBackupTime}`, "info");
                }
            },

            startBackup() {
                this.isBackingUp = true;
                this.progress = 0;

                fetch('backup.php', { method: 'POST' })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            let interval = setInterval(() => {
                                if (this.progress < 100) {
                                    this.progress += 20;
                                } else {
                                    clearInterval(interval);
                                    this.isBackingUp = false;

                                    // ✅ SUCCESS TOAST NOTIFICATION
                                    Toastify({
                                        text: `✅ Backup Completed! Transactions: ${data.transactions_backed_up} | Items: ${data.items_backed_up}`,
                                        duration: 5000,
                                        gravity: "top",
                                        position: "right",
                                        backgroundColor: "#28a745",
                                        stopOnFocus: true
                                    }).showToast();

                                    let backupTime = Date.now();
                                    localStorage.setItem("lastBackupTime", backupTime.toString()); // Store successful backup time
                                    this.scheduleNextBackup(backupTime); // Schedule next backup
                                }
                            }, 500);
                        } else {
                            this.isBackingUp = false;

                            // ❌ ERROR TOAST NOTIFICATION
                            Toastify({
                                text: `❌ Backup Failed: ${data.message}`,
                                duration: 5000,
                                gravity: "top",
                                position: "right",
                                backgroundColor: "#dc3545",
                                stopOnFocus: true
                            }).showToast();
                        }
                    })
                    .catch(() => {
                        this.isBackingUp = false;

                        // ⚠️ WARNING TOAST NOTIFICATION
                        Toastify({
                            text: "⚠️ An unexpected error occurred.",
                            duration: 5000,
                            gravity: "top",
                            position: "right",
                            backgroundColor: "#ffc107",
                            stopOnFocus: true
                        }).showToast();
                    });
            },

            showTippy(message, type) {
                let bgColor = {
                    success: "#28a745",
                    error: "#dc3545",
                    info: "#17a2b8"
                }[type] || "#6c757d";

                let targetElement = document.getElementById("backUpStatus");

                if (!targetElement) {
                    console.warn("⚠️ Warning: 'backUpStatus' div not found. Tippy.js tooltip cannot be attached.");
                    return;
                }

                tippy(targetElement, {
                    content: message,
                    placement: "top",
                    theme: "custom",
                    animation: "fade",
                    duration: [300, 300], // Smooth fade-in/out
                    allowHTML: true,
                    trigger: "manual",
                    onShow(instance) {
                        setTimeout(() => instance.hide(), 10000); // Auto-hide after 10 seconds
                    }
                }).show();
            }
        };
    }
</script>

  <!-- toast effect -->
  <script src="node_modules/toastify-js/src/toastify.js"></script>

  <div class="container-scroller dark-theme light-theme ">
    <!-- partial:partials/_navbar.html -->
    <nav class="navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row dark-theme light-theme ">
    <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-center dark-theme light-theme navbar-dark">
    <a class="navbar-brand brand-logo mr-5 dark-theme light-theme navbar-dark" href="superAdmin.php">
        <?php 
            // Extract the first word of the business name
            $businessName = (!empty(BUSINESS_NAME)) ? BUSINESS_NAME : '';
            $firstWord = strtok($businessName, " "); // Get the first word only
            echo htmlspecialchars($firstWord); // Ensure safe output
        ?>
        &nbsp;
        <img src="<?php echo (!empty(BUSINESS_LOGO)) ? BUSINESS_LOGO : 'images/logo-mini.png'; ?>" 
             class="mr-2 dark-theme light-theme navbar-dark" 
             alt="Business Logo"/>
    </a>
</div>
      <div class="navbar-menu-wrapper d-flex align-items-center justify-content-end dark-theme light-theme navbar-dark">
        <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-toggle="minimize">
          <span class="icon-menu"></span>
        </button>
        <ul class="navbar-nav navbar-nav-right dark-theme light-theme navbar-dark">
            <li class="nav-item d-none d-lg-flex align-items-center mr-3">
    <div class="badge badge-info badge-pill px-3 py-2" style="font-size: 0.9rem; background: linear-gradient(45deg, #1a2980, #26d0ce); border: none;">
        <i class="fas fa-map-marker-alt mr-2"></i> 
        <?php 
            // Display Branch Name if set, otherwise Branch Code, fallback to Global
            echo htmlspecialchars($_SESSION['branch_name'] ?? $_SESSION['branch_code'] ?? 'Global View'); 
        ?>
    </div>
</li>
          <li class="nav-item dark-theme light-theme navbar-dark">Privilege Level: <?php echo $_SESSION['role']; ?></li>
          <li class="nav-item nav-profile dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown" id="profileDropdown">
              <i class="icon-head menu-icon"></i>
                <span class="menu-title"><?php echo $_SESSION['username'];?></span> 
            </a>
            <div class="dropdown-menu dropdown-menu-right navbar-dropdown dark-theme light-theme navbar-dark" aria-labelledby="profileDropdown">
             <!--  <a class="dropdown-item" href="https://igs.ng/pos/logout.php"> -->
             <a class="dropdown-item" href="logout.php">
                <i class="ti-power-off text-primary"></i>
                Logout
              </a>
            </div>
          </li>
        </ul>
      </div>
    </nav>
    <!-- partial -->
    <div class="container-fluid page-body-wrapper dark-theme light-theme navbar-dark">
      <!-- partial:partials/_sidebar.html -->
      <nav class="sidebar sidebar-offcanvas dark-theme light-theme navbar-dark" id="sidebar">
        <ul class="nav dark-theme light-theme navbar-dark">
        <li class="nav-item dark-theme light-theme navbar-dark">
            <a class="nav-link" href="admin.php">
              <i class="icon-grid menu-icon"></i>
              <span class="menu-title">Dashboard</span>
            </a>
          </li>
          <li class="nav-item dark-theme light-theme navbar-dark">
            <a class="nav-link dark-theme light-theme navbar-dark" href="user_roles.php">
              <i class="icon-head menu-icon"></i>
              <span class="menu-title">Users & Roles</span>
            </a>
          </li>
          <li class="nav-item dark-theme light-theme navbar-dark">
            <a class="nav-link dark-theme light-theme navbar-dark" href="store_keeper.php">
              <i class="icon-plus menu-icon"></i>
              <span class="menu-title">Add Items & More</span>
            </a>
          </li>
          <li class="nav-item dark-theme light-theme navbar-dark">
            <a class="nav-link " href="manage_item.php">
              <i class="icon-briefcase menu-icon"></i>
              <span class="menu-title">Manage Items</span>
            </a>
          </li>
        </ul>
      </nav>
      <!-- partial -->
      <div class="main-panel dark-theme light-theme navbar-dark">
        <div class="content-wrapper dark-theme light-theme navbar-dark">
          <div class="row dark-theme light-theme navbar-dark">
            <div class="col-md-6 grid-margin stretch-card dark-theme light-theme navbar-dark">
              <div class="card dark-theme light-theme navbar-dark">
                <div class="card-body dark-theme light-theme navbar-dark">
                  <h4 class="card-title dark-theme light-theme navbar-dark">Create New User</h4>
                  <form id="createUserForm">
    <div class="form-group dark-theme light-theme navbar-dark">
        <label for="username">Username</label>
        <input type="text" class="form-control dark-theme light-theme navbar-dark" id="username" name="username" placeholder="Enter username" required>
    </div>

    <div class="form-group dark-theme light-theme navbar-dark">
        <label for="password">Password</label>
        <input type="password" class="form-control dark-theme light-theme navbar-dark" id="password" name="password" placeholder="Enter password" required>
        <span class="toggle-password" style="cursor: pointer; position: absolute; right: 25px; top: 38px;">
            <i class="fa fa-eye"></i>
        </span>
    </div>

    <div class="form-group dark-theme light-theme navbar-dark">
        <label for="role">Role</label>
        <select class="form-control dark-theme light-theme navbar-dark" id="role" name="role_id" required>
            <option value="">Select Role...</option>
        </select>
    </div>


    <div class="form-group" id="branchSelectGroup"> 
    <label for="branchSelect" class="font-weight-bold">Assign Branch</label>
    
    <select class="form-control bg-light" id="branchSelect" disabled>
        <option value="">Loading context...</option>
    </select>

    <input type="hidden" id="hiddenBranchCode" name="branch_code">
    
    <small class="form-text text-muted">
        <i class="fas fa-lock text-warning mr-1"></i> Locked to your active branch context.
    </small>
</div>
    <button type="submit" class="btn btn-primary mr-2" id="submitBtn">Create User</button>
</form>
                </div>
              </div>
            </div>
            <div class="col-md-6 dark-theme light-theme navbar-dark">
              <div class="card dark-theme light-theme navbar-dark">
                <div class="card-body dark-theme light-theme navbar-dark">
                  <h4 class="card-title dark-theme light-theme navbar-dark">Create New User Role</h4>
                    <form id="createRoleForm">
                      <div class="form-group dark-theme light-theme navbar-dark">
                        <label for="roleName">Role Name</label>
                        <input type="text" class="form-control dark-theme light-theme navbar-dark" id="roleName" name="roleName" placeholder="Enter role name">
                      </div>
                      <button type="submit" class="btn btn-primary">Create Role</button>
                    </form>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- content-wrapper ends -->
        <!-- partial:partials/_footer.html -->
        <footer class="footer dark-theme light-theme navbar-dark">
          <div class="d-sm-flex justify-content-center justify-content-sm-between dark-theme light-theme navbar-dark">
            <span class="text-muted text-center text-sm-left d-block d-sm-inline-block dark-theme light-theme navbar-dark">Copyright © <?php echo date('Y'); ?>.  Inventory Keeper App. All rights reserved.</span>
          </div>
        </footer> 
        <!-- partial -->
      </div>
      <!-- main-panel ends -->
    </div>   
    <!-- page-body-wrapper ends -->
  </div>
  <!-- container-scroller -->

  <div class="sync-hud" 
     x-data="syncSystem()" 
     x-init="initSync()"
     :class="{ 
        'status-offline': !isOnline, 
        'status-online': isOnline && !isSyncing,
        'status-syncing': isSyncing 
     }" 
     x-show="true" 
     style="display: none;" 
     x-show.important="true"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0 translate-y-10"
     x-transition:enter-end="opacity-100 translate-y-0">
      
      <div class="sync-header">
          <div class="d-flex align-items-center">
              <template x-if="!isOnline">
                  <i class="fas fa-wifi-slash text-warning mr-3" style="font-size: 1.2rem;"></i>
              </template>
              <template x-if="isOnline && !isSyncing">
                  <i class="fas fa-check-circle text-success mr-3" style="font-size: 1.2rem;"></i>
              </template>
              <template x-if="isSyncing">
                  <i class="fas fa-sync-alt spin-sync text-info mr-3" style="font-size: 1.2rem;"></i>
              </template>
              
              <div>
                  <p class="sync-title mb-0" x-text="statusTitle"></p>
                  <span class="sync-status-text text-muted" style="font-size: 0.75rem;" x-text="statusMessage"></span>
              </div>
          </div>
      </div>

      <div class="sync-progress-bg mt-2" x-show="progressWidth > 0">
          <div class="sync-progress-fill" 
               :style="`width: ${progressWidth}%`" 
               :class="{ 'success': progressWidth === 100 }">
          </div>
      </div>
</div>


  <!-- plugins:js -->
  <script src="vendors/js/vendor.bundle.base.js"></script>
  <!-- endinject -->
  <!-- Plugin js for this page -->
  <script src="vendors/chart.js/Chart.min.js"></script>
  <script src="vendors/datatables.net/jquery.dataTables.js"></script>
  <script src="vendors/datatables.net-bs4/dataTables.bootstrap4.js"></script>
  <script src="js/dataTables.select.min.js"></script>

  <!-- End plugin js for this page -->
  <!-- inject:js -->
  <script src="js/off-canvas.js"></script>
  <script src="js/hoverable-collapse.js"></script>
  <script src="js/template.js"></script>
  <script src="js/settings.js"></script>
  <script src="js/todolist.js"></script>
  <!-- endinject -->
  <!-- Custom js for this page-->
  <script src="js/dashboard.js"></script>
  <script src="js/Chart.roundedBarCharts.js"></script>
   <script src="js/sync_system.js"></script>

   <script>
    // We use json_encode to prevent syntax errors (handling quotes/nulls automatically)
    var ACTIVE_BRANCH_CONTEXT = <?php echo json_encode($page_context_branch); ?>;
    var USER_SESSION_BRANCH   = <?php echo json_encode($_SESSION['branch_code']); ?>;

    // Debugging (Optional: View in console to verify)
    console.log("Context Loaded:", { active: ACTIVE_BRANCH_CONTEXT, session: USER_SESSION_BRANCH });
</script>

   <script>
  // A. Load Branches & Lock Context
    if ($('#branchSelect').length > 0) {
        $.ajax({
            url: 'get_branches.php', 
            dataType: 'json',
            success: function(branches) {
                let $select = $('#branchSelect');
                let $hiddenInput = $('#hiddenBranchCode');
                
                $select.empty();

                // 1. Resolve Active Context (STRICTLY DYNAMIC)
                let activeCode = '';
                if (typeof ACTIVE_BRANCH_CONTEXT !== 'undefined' && ACTIVE_BRANCH_CONTEXT) {
                    activeCode = ACTIVE_BRANCH_CONTEXT;
                } else if (typeof USER_SESSION_BRANCH !== 'undefined' && USER_SESSION_BRANCH) {
                    activeCode = USER_SESSION_BRANCH;
                } else {
                    console.error("Context Error: No active branch context found.");
                    activeCode = 'UNKNOWN_CONTEXT'; 
                }
                
                // Set a baseline name in case the DB lookup fails
                let activeName = activeCode; 

                // 2. Find the friendly name dynamically from the DB response
                if (branches && branches.length > 0) {
                    let foundBranch = branches.find(b => b.branch_code === activeCode);
                    if (foundBranch) {
                        activeName = foundBranch.branch_name;
                    }
                }

                // 3. Set the Single Option & Hidden Value
                $select.append(`<option value="${activeCode}" selected>${activeName}</option>`);
                $hiddenInput.val(activeCode);
            },
            error: function() {
                // Fallback in case of network error (STRICTLY DYNAMIC)
                let fallbackCode = '';
                if (typeof ACTIVE_BRANCH_CONTEXT !== 'undefined' && ACTIVE_BRANCH_CONTEXT) {
                    fallbackCode = ACTIVE_BRANCH_CONTEXT;
                } else if (typeof USER_SESSION_BRANCH !== 'undefined' && USER_SESSION_BRANCH) {
                    fallbackCode = USER_SESSION_BRANCH;
                } else {
                    fallbackCode = 'OFFLINE_CONTEXT';
                }

                $('#branchSelect').html(`<option value="${fallbackCode}" selected>${fallbackCode} (Offline)</option>`);
                $('#hiddenBranchCode').val(fallbackCode);
            }
        });
    }
    
    </script>


  <script>


// Show Password Functionality
$(".toggle-password").click(function () {
  var input = $(this).prev("input");
  var icon = $(this).children("i");
      icon.style.padding="50px"
  if (input.attr("type") === "password") {
    input.attr("type", "text");
    icon.removeClass("fa-eye").addClass("fa-eye-slash");
  } else {
    input.attr("type", "password");
    icon.removeClass("fa-eye-slash").addClass("fa-eye");
  }
});
</script>

  <!-- End custom js for this page-->
</body>
</html>