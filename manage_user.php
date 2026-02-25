<?php
ob_start(); // Start output buffering
require 'auto_logout_script.php';
require 'defined_global_settings.php';
session_start(); // Start session at the very top of the file
// Add this near the top of your PHP block
$filter_branch_name = isset($_GET['branch_name']) ? urldecode($_GET['branch_name']) : "Global Scope";

if (isset($_SESSION['role'])) {
    $user_role = $_SESSION['role'];
    if ($user_role === "Super Admin") {
        $userID = $_SESSION['user_id'];
        $userName = $_SESSION['username'];
        $userRole = $_SESSION['role'];
    } else {
        header("Location: 404.php");
        exit;
    }
} else {
    header("Location: 404.php"); 
    exit; // Stop script execution after redirect 
}

// --- 2. BRANCH CONTEXT LOGIC (New) ---
// Capture branch info to keep navigation consistent across pages
$filter_branch_uuid = isset($_GET['branch_uuid']) ? $_GET['branch_uuid'] : null;
$filter_branch_name = isset($_GET['branch_name']) ? urldecode($_GET['branch_name']) : null;

// Helper function to append branch info to menu links
function linkTo($url) {
    global $filter_branch_uuid, $filter_branch_name;
    if ($filter_branch_uuid) {
        $sep = (strpos($url, '?') !== false) ? '&' : '?';
        return $url . $sep . "branch_uuid=" . urlencode($filter_branch_uuid) . "&branch_name=" . urlencode($filter_branch_name);
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


ob_end_flush(); // Flush output buffer
?>
<!DOCTYPE html>
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
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css">


  <link rel="shortcut icon" href="<?php echo (defined('BUSINESS_LOGO') && !empty(BUSINESS_LOGO)) ? BUSINESS_LOGO : 'images/favicon.png'; ?>" />


   <!-- toast styling effect -->
<link rel="stylesheet" href="node_modules/toastify-js/src/toastify.css" />


  <!-- sweet  alert 2 lib -->
<link rel="stylesheet" href="sweetalert2/dist/sweetalert2.min.css">
<script src="sweetalert2/dist/sweetalert2.all.min.js"></script>

<!-- jQuery library -->
<script src="jquery/jquery-3.6.0.min.js"></script> 

<!-- Local Tippy.js CSS -->
<link rel="stylesheet" href="node_modules/tippy.js/dist/tippy.css">

<!-- Local Popper.js (UMD version for browser compatibility) -->
<script src="node_modules/@popperjs/core/dist/umd/popper.js"></script>

<!-- Local Tippy.js (UMD version for browser compatibility) -->
<script src="node_modules/tippy.js/dist/tippy.umd.min.js"></script>

<!-- dynamic logic for dark theme and light theme -->
<?php if (defined('DARK_MODE') && DARK_MODE == 1): ?>
    <link rel="stylesheet" type="text/css" href="css/dark-theme.css">
<?php else: ?>
    <link rel="stylesheet" type="text/css" href="css/light-theme.css">
<?php endif; ?>


<!-- bootstrap v4 -->
<link rel="stylesheet" href="bootstrap_v4/css/bootstrap.min.css"> 


<!-- FileSaver -->
<script src="FileSaver.js/dist/FileSaver.js"></script>

<!-- Custom JS files -->
<script src="js/superCreate_user.js"></script>   
<script src="js/alpine.min.js" defer></script>


<style>
  #logoPreview{
    height: 10% !important;
    width: 10% !important;
  }
    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.7);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1050;
    }

    .progress-container {
            width: 100%;
            background: #e9ecef;
            border-radius: 5px;
            margin-top: 10px;
            padding: 5px;
        }
        .progress-bar {
            width: 0%;
            height: 10px;
            background: #28a745;
            border-radius: 5px;
            transition: width 0.3s;
        }

        /* Customizing checkbox labels */
.form-check-label {
    font-size: 1rem;
    font-weight: 500;
    color: #333;
    transition: color 0.3s ease;
}

/* Customizing checkboxes */
.form-check-input {
    width: 20px;
    height: 20px;
    margin-right: 10px;
    cursor: pointer;
    border: 2px solid #ddd;
    border-radius: 4px;
    transition: border-color 0.3s ease, background-color 0.3s ease;
}

/* Styling for hover state */
.form-check-input:hover {
    border-color: #007bff;
}

/* Checked state styling */
.form-check-input:checked {
    background-color: #007bff;
    border-color: #007bff;
}

/* All checkbox special styling */
#canEditAll {
    font-weight: bold;
    color: #007bff;
}

/* When 'All' is checked, apply style to others */
#canEditAll:checked ~ .form-check .form-check-input {
    background-color: #28a745;
    border-color: #28a745;
}

/* On hover over any checkbox */
.form-check-input:focus {
    outline: none;
    box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
}

/* Button Style */
.btn-block {
    width: 100%;
    font-size: 1.1rem;
    padding: 12px 20px;
    border-radius: 5px;
    letter-spacing: 0.5px;
}


</style>

<style>
/* Branch Context Pill Styling */
.branch-context-pill {
    display: flex;
    align-items: center;
    background: linear-gradient(135deg, #e0f7fa 0%, #ffffff 100%);
    border: 1px solid #b2ebf2;
    padding: 8px 20px;
    border-radius: 50px;
    box-shadow: 0 4px 15px rgba(0, 188, 212, 0.15);
    transition: all 0.3s ease;
    margin-right: 15px; /* Spacing */
}
.branch-context-pill:hover {
    box-shadow: 0 6px 20px rgba(0, 188, 212, 0.25);
    transform: translateY(-1px);
}
.branch-name {
    font-family: sans-serif;
    font-weight: 800;
    font-size: 1rem;
    color: #006064;
    margin-left: 10px;
    margin-right: 15px;
}
.branch-icon { font-size: 1.2rem; color: #00bcd4; }
.btn-back-hub {
    background: #ffffff;
    border: 1px solid #e0e0e0;
    color: #546e7a;
    font-weight: 600;
    font-size: 0.85rem;
    padding: 6px 16px;
    border-radius: 30px;
    display: flex;
    align-items: center;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-back-hub:hover {
    background: #37474f;
    color: #ffffff;
    text-decoration: none;
}
.nav-online-dot {
    width: 10px; height: 10px;
    background-color: #28a745;
    border-radius: 50%;
    margin-right: 8px;
    box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7);
    animation: navPulse 2s infinite;
}
@keyframes navPulse {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(40, 167, 69, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
}

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
        url: 'auto_logout.php',//php endpoint
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

<!-- this script handles the routine backup of data -->
<script src="js/backup_logic.js"></script>

  <!-- toast effect -->
  <script src="node_modules/toastify-js/src/toastify.js"></script>

  <div class="container-scroller">
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

      <div class="navbar-menu-wrapper d-flex align-items-center justify-content-end dark-theme light-theme navbar-dark ">
        <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-toggle="minimize">
          <span class="icon-menu"></span>
        </button>

   <ul class="navbar-nav mr-lg-2">
   <?php 
    // --- PROFESSIONAL CONTEXT SWITCHER (DYNAMIC) ---
    
    // 1. SOURCE OF TRUTH: The Logged-in User's Assigned Branch
    // We trust the session set during login. No hardcoded defaults.
    $home_branch_code = $_SESSION['branch_code'] ?? ''; 
    $home_branch_name = $_SESSION['branch_name'] ?? 'My Branch';

    // 2. ACTIVE VIEW: Default to Home, but allow URL Override for Super Admins
    $active_branch_code = $home_branch_code;
    $active_branch_name = $home_branch_name;

    // Override logic if parameters exist
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'Super Admin') {
        if (isset($_GET['branch_uuid']) && !empty($_GET['branch_uuid'])) {
            $active_branch_code = $_GET['branch_uuid'];
            // Use URL name if provided, otherwise fallback to code temporarily until JS loads
            $active_branch_name = isset($_GET['branch_name']) ? urldecode($_GET['branch_name']) : $active_branch_code;
        }
    }

    // 3. LOGIC: Are we visiting a remote branch?
    // We are "visiting" if the Active Branch is different from the User's Home Branch
    $is_visiting = ($active_branch_code !== $home_branch_code);
    
    // Only show back button if visiting AND user has permission
    $show_back_btn = ($is_visiting && isset($_SESSION['role']) && $_SESSION['role'] === 'Super Admin');
    ?>

    <li class="nav-item nav-search d-none d-lg-block">
        <div class="branch-context-pill">
            <div class="nav-online-dot" title="System Online"></div>
            <i class="ti-home branch-icon"></i>
            
            <span class="branch-name"><?php echo htmlspecialchars($active_branch_name); ?></span>
            
            <?php if ($show_back_btn): ?>
                <a href="hub_dashboard.php" class="btn-back-hub ml-3">
                    <i class="ti-arrow-left"></i> Back to Hub
                </a>
            <?php endif; ?>
        
        </div>
    </li>
    
    <script>
        // Pass PHP context to JS securely
        var ACTIVE_BRANCH_CONTEXT = "<?php echo htmlspecialchars($active_branch_code); ?>";
        var ACTIVE_BRANCH_NAME_DEFAULT = "<?php echo htmlspecialchars($active_branch_name); ?>";
    </script>
</ul>

<ul class="navbar-nav navbar-nav-right dark-theme light-theme navbar-dark">
    <li class="nav-item nav-profile">
            <a class="dropdown-item dark-theme light-theme navbar-dark">
                <i class="ti-settings text-primary"></i>
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#settingsModal">
                Settings
                </button>
                
              </a>
    </li>
          <li class="nav-item nav-profile dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown" id="profileDropdown">
              <i class="icon-head menu-icon"></i>
                <span class="menu-title"><?php echo $_SESSION['username'];?></span>
            </a>
            <div class="dropdown-menu dropdown-menu-right navbar-dropdown dark-theme light-theme navbar-dark" aria-labelledby="profileDropdown">
          
              <!-- <a class="dropdown-item" href="https://igs.ng/pos/logout.php"> -->
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
        <ul class="nav">
      <li class="nav-item dark-theme light-theme navbar-dark">
                        <a class="nav-link" href="<?php echo linkTo('superAdmin.php'); ?>">
                            <i class="icon-grid menu-icon"></i><span class="menu-title">Dashboard</span>
                        </a>
                    </li>
      <li class="nav-item dark-theme light-theme navbar-dark">
                       <a class="nav-link" href="<?php echo linkTo('transactions.php'); ?>">
                            <i class="ti-receipt menu-icon"></i><span class="menu-title">Transactions</span>
                        </a>
                    </li>

<?php if (!$is_visiting): ?>
         <li class="nav-item dark-theme light-theme navbar-dark">
            <a class="nav-link" href="<?php echo linkTo('manage_user.php'); ?>">
                <i class="icon-head menu-icon"></i><span class="menu-title">User Mgt. & App Setting</span>
            </a>
         </li>
     <?php endif; ?>

               <li class="nav-item dark-theme light-theme navbar-dark">
                       <a class="nav-link" href="<?php echo linkTo('store_keeper.php'); ?>">
                            <i class="icon-plus menu-icon"></i><span class="menu-title">Add Items & More</span>
                        </a>
                    </li>
                    <li class="nav-item  dark-theme light-theme navbar-dark"></li>
          <li class="nav-item  dark-theme light-theme navbar-dark">
            <a class="nav-link" href="<?php echo linkTo('manage_item.php'); ?>"> 
              <i class="ti-package menu-icon"></i>
              <span class="menu-title">Manage Items</span>
            </a>
          </li> 
          <li class="nav-item  dark-theme light-theme navbar-dark">
            <a class="nav-link" href="<?php echo linkTo('pos.php'); ?>">
              <i class="ti-shopping-cart menu-icon"></i>
              <span class="menu-title">Make Sales</span>
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
                  <h4 class="card-title dark-theme light-theme navbar-dark">User Managment</h4>
                  <div class="modal-body">
    <form id="createUserForm" class="forms-sample">
        
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" class="form-control" id="username" name="username" placeholder="Username" autocomplete="off" required>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" class="form-control" id="password" name="password" placeholder="Password" autocomplete="new-password" required>
        </div>

        <div class="form-group">
            <label for="roleSelect">Role</label>
            <select class="form-control" id="roleSelect" name="role_id" required>
                <option value="">Select Role...</option>
                <option value="1">Super Admin</option>
                <option value="2">Admin</option>
                <option value="3">Sales Manager</option>
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

        <button type="submit" style="display:none;"></button>

    </form>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
    <button type="submit" form="createUserForm" class="btn btn-primary">Create User</button>
</div>
                </div>
              </div>
            </div>
          </div>
          
          <div class="row dark-theme light-theme">
            <div class="col-md-12 grid-margin stretch-card dark-theme light-theme">
              <div class="card dark-theme light-theme">
                <div class="card-body dark-theme light-theme">
                  <div class="row dark-theme light-theme">
                    <div class="col-12 dark-theme light-theme">
                    <center>
                                <div id="paginationCont" class="text-center">
                      <!-- Pagination links will be dynamically populated here -->
                                </div>
                   </center>
                      <div class="table-responsive dark-theme light-theme navbar-dark">
                        <table  class="display expandable-table table-striped dark-theme light-theme navbar-dark" style="width:100%">
                          <thead>
                            <tr>
                              <th>User ID</th>
                              <th>Username</th>
                              <th>Role</th>
                              <th>Action</th>
                            </tr>
                          </thead>
                          <tbody id="userTableBody" class="dark-theme light-theme navbar-dark">
                          <!-- User data dynamically populated using AJAX -->
                        </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                </div>
              </div>  
            </div>
          </div>

            <!-- Edit User Modal -->
  <div class="modal fade dark-theme light-theme" id="editUserModal" tabindex="-1" role="dialog" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <!-- Modal markup code here -->
    <div class="modal-dialog dark-theme light-theme" role="document">
      <div class="modal-content dark-theme light-theme">
        <div class="modal-header dark-theme light-theme">
          <h5 class="modal-title dark-theme light-theme" id="editUserModalLabel">Edit User</h5>
          <button type="button dark-theme light-theme" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body dark-theme light-theme">
          <form id="editUserForm">
            <input type="hidden" id="editUserId" name="editUserId">
  
            <div class="form-group">
              <label for="editUsername">Username</label>
              <input type="text" class="form-control" id="editUsername" name="editUsername" required>
            </div>
  
            <div class="form-group">
              <label for="editRole">Role</label>
              <select class="form-control" id="editRole" name="editRole" required>
                <!-- Role options dynamically populated using AJAX -->
              </select>
            </div>
  
            <div class="modal-footer">
              <button type="submit" class="btn btn-primary">Save Changes</button>
              <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

        <!-- content-wrapper ends -->
        <!-- partial:partials/_footer.html -->
        <footer class="footer dark-theme light-theme navbar-dark">
          <div class="d-sm-flex justify-content-center justify-content-sm-between dark-theme light-theme navbar-dark">
            <span class="text-muted text-center text-sm-left d-block d-sm-inline-block dark-theme light-theme navbar-dark ">Copyright © <?php echo date('Y'); ?>.  Inventory Keeper App. All rights reserved.</span>
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
  <!-- bootstrap v4 js -->
  <script src="bootstrap_v4/js/bootstrap.min.js"></script>

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

            // 1. Resolve Active Context
            // Use the variable passed from PHP. If undefined, we have a logic error (no session), but no hardcoding.
            let activeCode = (typeof ACTIVE_BRANCH_CONTEXT !== 'undefined') ? ACTIVE_BRANCH_CONTEXT : '';
            
            // 2. Resolve Friendly Name
            // Start with the name PHP gave us (from Session or URL)
            let activeName = (typeof ACTIVE_BRANCH_NAME_DEFAULT !== 'undefined') ? ACTIVE_BRANCH_NAME_DEFAULT : 'Current Branch';

            // 3. Improve Name Accuracy
            // Try to find the exact branch details in the DB response for a perfect match
            if (activeCode) {
                let foundBranch = branches.find(b => b.branch_code === activeCode);
                if (foundBranch) {
                    activeName = foundBranch.branch_name;
                }
            }

            // 4. Set the Single Option & Hidden Value
            if (activeCode) {
                $select.append(`<option value="${activeCode}" selected>${activeName}</option>`);
                $hiddenInput.val(activeCode);
            } else {
                // Handle rare edge case where session is lost
                $select.append(`<option value="" disabled selected>Session Context Missing</option>`);
            }
        },
        error: function() {
            // Fallback: Use the PHP context blindly if AJAX fails (Offline Mode)
            let fallbackCode = (typeof ACTIVE_BRANCH_CONTEXT !== 'undefined') ? ACTIVE_BRANCH_CONTEXT : '';
            let fallbackName = (typeof ACTIVE_BRANCH_NAME_DEFAULT !== 'undefined') ? ACTIVE_BRANCH_NAME_DEFAULT : 'Current Branch';
            
            if(fallbackCode) {
                $('#branchSelect').html(`<option value="${fallbackCode}" selected>${fallbackName}</option>`);
                $('#hiddenBranchCode').val(fallbackCode);
            }
        }
    });
}
</script>

  <!-- End custom js for this page-->

<!-- Settings Modal -->
<div class="modal fade" id="settingsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">POS Settings</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs" id="settingsTabs">
                    <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#general">General</a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#users">Users & Roles</a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#sales">Sales Config</a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#auditLogs">Audit Logs</a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#inventory">Inventory & Stock Control </a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#additional">Additional Settings</a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#backUp">Back Up</a></li>

                  </ul>
                
                <div class="tab-content mt-3">
                    <!-- General Settings -->
                    <div class="tab-pane fade show active" id="general">
<div class="card shadow-lg rounded-lg">
    <div class="card-header bg-primary text-white rounded-top-lg py-3">
        <h3 class="card-title mb-0 text-center">General Settings</h3>
    </div>
    <div class="card-body py-4 px-3 px-md-4">
        <form id="generalSettingsForm" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="business_name" class="col-form-label">Company Name:</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-building"></i></span>
                    </div>
                    <input type="text" class="form-control form-control-lg" name="business_name" id="business_name" placeholder="Enter company name" required>
                </div>
                <small id="businessNameHelp" class="form-text text-muted">
                    Enter the name of your company.
                </small>
            </div>
            <div class="form-group">
                <label for="logoUpload" class="col-form-label">Upload Logo:</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-upload"></i></span>
                    </div>
                    <div class="custom-file">
                        <input type="file" class="custom-file-input form-control-lg" id="logoUpload" name="logoUpload" accept="image/*" aria-describedby="logoUploadHelp">
                        <label class="custom-file-label" for="logoUpload">Choose file</label>
                    </div>
                </div>
                <small id="logoUploadHelp" class="form-text text-muted">
                    Upload your company logo (image file).
                </small>
                <img id="logoPreview" class="preview-logo mt-3 d-none" alt="Logo Preview" style="max-width: 200px; height: auto;">
            </div>
            <div class="form-group">
                <label for="currencySelect" class="col-form-label">Currency:</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-money-bill-wave"></i></span>
                    </div>
                    <select class="form-control form-control-lg" name="currency" id="currencySelect">
                        <option value="₦">NGN (₦)</option>
                        <option value="$">USD ($)</option>
                        <option value="€">EUR (€)</option>
                        <option value="custom">Custom</option>
                    </select>
                </div>
                 <small id="currencySelectHelp" class="form-text text-muted">
                    Select your default currency.
                </small>
            </div>
            <div class="form-group" id="customCurrencyDiv" style="display:none;">
                <label for="currency_custom" class="col-form-label">Custom Currency:</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-edit"></i></span>
                    </div>
                    <input type="text" class="form-control form-control-lg" name="currency_custom" id="currency_custom" placeholder="Enter custom currency symbol">
                </div>
                 <small id="customCurrencyHelp" class="form-text text-muted">
                    Enter your custom currency symbol (e.g., "Ksh").
                </small>
            </div>
            <button type="submit" class="btn btn-primary btn-lg btn-block mt-4">
                <i class="fas fa-save mr-2"></i> Save Changes
            </button>
        </form>
    </div>
    <div class="card-footer text-muted text-center py-2 rounded-bottom-lg">
        Configure your general application settings.
    </div>
</div>

                    </div><!-- end of General settings tab -->

                    <!-- users role management section -->
                          <!-- Users & Roles Management Tab -->
<div class="tab-pane fade" id="users">
<hr>
<div id="assignedUsersContainer"></div>
<hr>
   <div class="card shadow-lg rounded-lg">
    <div class="card-header bg-primary text-white rounded-top-lg py-3">
        <h3 class="card-title mb-0 text-center">Assign User Roles and Permissions</h3>
    </div>
    <div class="card-body py-4 px-3 px-md-4">
        <form id="usersRolesForm">
            <div class="form-group">
                <label for="userSelect" class="col-form-label">Select User:</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                    </div>
                    <select class="form-control form-control-lg" id="userSelect" name="user_id" required>
                        <option value="" disabled selected>Select a User</option>
                    </select>
                </div>
                <small id="userSelectHelp" class="form-text text-muted">
                    Choose the user to assign roles and permissions.
                </small>
            </div>

            <div class="form-group">
                <label for="roleSelect" class="col-form-label">Assign Role:</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                    </div>
                    <select class="form-control form-control-lg" id="roleSelect1" name="role_id" required>
                        <option value="" disabled selected>Select a Role</option>
                    </select>
                </div>
                 <small id="roleSelectHelp" class="form-text text-muted">
                    Select the role to assign to the user.
                </small>
            </div>

            <div class="form-group">
                <label class="col-form-label">Edit Settings:</label>
                <div class="input-group">
                     <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-cogs"></i></span>
                    </div>
                <div class="form-check mt-2">
                    <input type="checkbox" class="form-check-input" id="canEditAll" name="permissions[]" value="All">
                    <label class="form-check-label" for="canEditAll">All</label>
                </div>

                <div class="form-check">
                    <input type="checkbox" class="form-check-input can-edit-option" id="canEditCUI" name="permissions[]" value="CUI">
                    <label class="form-check-label" for="canEditCUI">Update Items</label>
                </div>

                <div class="form-check">
                    <input type="checkbox" class="form-check-input can-edit-option" id="canEditCDI" name="permissions[]" value="CDI">
                    <label class="form-check-label" for="canEditCDI">Delete Items</label>
                </div>

                <div class="form-check">
                    <input type="checkbox" class="form-check-input can-edit-option" id="canEditCCI" name="permissions[]" value="CCI">
                    <label class="form-check-label" for="canEditCCI">Create Items</label>
                </div>

                <div class="form-check">
                    <input type="checkbox" class="form-check-input can-edit-option" id="canEditCDU" name="permissions[]" value="CDU">
                    <label class="form-check-label" for="canEditCDU">Delete Users </label>
                </div>

                <div class="form-check">
                    <input type="checkbox" class="form-check-input can-edit-option" id="canEditCUU" name="permissions[]" value="CUU">
                    <label class="form-check-label" for="canEditCUU">Update Users </label>
                </div>

                <div class="form-check">
                    <input type="checkbox" class="form-check-input can-edit-option" id="canEditCCU" name="permissions[]" value="CCU">
                    <label class="form-check-label" for="canEditCCU">Create Users</label>
                </div>
                </div>
                 <small id="permissionsHelp" class="form-text text-muted">
                    Select the permissions for the user.
                </small>
            </div>

            <div class="form-group mt-4">
                <button type="submit" class="btn btn-primary btn-lg btn-block">
                    <i class="fas fa-save mr-2"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
     <div class="card-footer text-muted text-center py-2 rounded-bottom-lg">
        Assign roles and permissions to users.
    </div>
</div>

    <script>
document.getElementById('canEditAll').addEventListener('change', function() {
    const isChecked = this.checked;
    const checkboxes = document.querySelectorAll('.can-edit-option');

    checkboxes.forEach(function(checkbox) {
        checkbox.checked = isChecked;
    });
});

</script>

</div>  <!--end of users role management section -->

 <!-- Sales Config Tab -->
<div class="tab-pane fade" id="sales" role="tabpanel" aria-labelledby="sales-tab">
  <div class="card shadow-lg rounded-lg">
    <div class="card-header bg-primary text-white rounded-top-lg py-3">
        <h3 class="card-title mb-0 text-center">Sales Configuration</h3>
    </div>
    <div class="card-body py-4 px-3 px-md-4">
        <form id="salesConfigForm" method="POST" enctype="multipart/form-data">
            <h4 class="mt-2">Price Options</h4> 
            <div class="form-group">
                <label for="priceTypeToggle" class="col-form-label">Default Price Type:</label>
                <div class="custom-control custom-switch custom-control-inline">
                    <input type="checkbox" class="custom-control-input" id="priceTypeToggle" name="price_type" >
                    <label class="custom-control-label" for="priceTypeToggle" id="priceTypeLabel">Retail</label>
                </div>
            </div>

            <h4 class="mt-4">Receipt Customization</h4>
            <div class="form-group">
                <label for="receiptFooter" class="col-form-label">Receipt Footnotes:</label>
                <textarea class="form-control form-control-lg" id="receiptFooter" name="receipt_footer" rows="3" placeholder="Enter receipt footnotes" required></textarea>
                 <small id="receiptFooterHelp" class="form-text text-muted">
                    Enter any additional notes to be displayed at the bottom of receipts.
                </small>
            </div>
            <div class="form-group">
                <label for="receiptDisclaimer" class="col-form-label">Receipt Disclaimers:</label>
                <textarea class="form-control form-control-lg" id="receiptDisclaimer" name="receipt_disclaimer" rows="3" placeholder="Enter receipt disclaimers" required></textarea>
                <small id="receiptDisclaimerHelp" class="form-text text-muted">
                    Enter any disclaimers to be displayed on receipts.
                </small>
            </div>

            <button type="submit" class="btn btn-primary btn-lg btn-block mt-4">Save Sales Configuration</button>
        </form>
    </div>
    <div class="card-footer text-muted text-center py-2 rounded-bottom-lg">
        Configure default sales settings and receipt appearance.
    </div>
</div>

   <!-- Dynamic Table Displaying Global displayPriceTypeStatus Settings -->
   <div id="displayPriceTypeStatus" class="mt-4">
    <!-- The dynamic table will be loaded here -->
  </div>
</div><!-- ends -->

              <!-- Security Settings Tab (Placeholder Content) -->
              <div class="tab-pane fade" id="security" role="tabpanel" aria-labelledby="security-tab">
            <!-- Security settings form elements can be added here -->
            <p class="text-muted">Security settings will be added here.</p>
          </div>

       <!-- Audit Logs Tab -->
          <div class="tab-pane fade" id="auditLogs" role="tabpanel" aria-labelledby="auditLogs-tab">
            <div class="table-responsive">
              <table class="table table-striped table-bordered">
                <thead class="thead-dark">
                  <tr>
                    <th>Log ID</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Timestamp</th>
                  </tr>
                </thead>
                <tbody id="auditLogsBody">
                  <!-- Audit logs loaded dynamically via AJAX -->
                </tbody>
              </table>
            </div>
            <button id="viewAllLogs" class="btn btn-warning">View All Logs</button>
          </div>
          
        
      

      <!-- Inventory & Stock Control Tab -->
<div class="tab-pane fade" id="inventory" role="tabpanel" aria-labelledby="inventory-tab">
  <div class="card shadow-lg rounded-lg">
    <div class="card-header bg-primary text-white rounded-top-lg py-3">
        <h3 class="card-title mb-0 text-center">Inventory & Stock Control</h3>
    </div>
    <div class="card-body py-4 px-3 px-md-4">
        <form id="inventorySettingsForm" method="POST">
            <h4 class="mt-2">Stock Management</h4>
            <div class="form-group">
                <label for="lowStockThreshold" class="col-form-label">Low Stock Threshold:</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-exclamation-triangle"></i></span>
                    </div>
                    <input type="number" class="form-control form-control-lg" id="lowStockThreshold" name="low_stock_threshold" placeholder="Enter threshold" required>
                </div>
                <small id="lowStockThresholdHelp" class="form-text text-muted">
                    Enter the quantity at which stock levels are considered low.
                </small>
            </div>

            <div class="form-group">
                <label for="enableLowStockAlert" class="col-form-label">Enable Low Stock Alerts:</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-bell"></i></span>
                    </div>
                    <select name="enable_low_stock_alert" id="enableLowStockAlert" class="form-control form-control-lg">
                        <option value="0">Disable</option>
                        <option value="1">Enable</option>
                    </select>
                </div>
                 <small id="enableLowStockAlertHelp" class="form-text text-muted">
                    Enable or disable notifications for low stock levels.
                </small>
            </div>
            <div class="form-group">
                <label for="allowExpiredSale" class="col-form-label">Allow Sale of Expired Items:</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-calendar-times"></i></span>
                    </div>
                    <select name="allow_expired_items_sale" id="allowExpiredSale" class="form-control form-control-lg">
                        <option value="0">Disallow (Recommended)</option>
                        <option value="1">Allow</option>
                    </select>
                </div>
                 <small id="allowExpiredSaleHelp" class="form-text text-muted">
                    Determine if expired items can be added to the cart at the POS.
                </small>
            </div>
            <button type="submit" class="btn btn-primary btn-lg btn-block mt-4">Save Inventory Settings</button>
        </form>
    </div>
    <div class="card-footer text-muted text-center py-2 rounded-bottom-lg">
        Configure settings for managing your inventory and stock.
    </div>
</div>


   <!-- Dynamic Table Displaying Global Inventory Settings -->
   <div id="inventorySettings" class="mt-4">
    <!-- The dynamic table will be loaded here -->
  </div>
</div>

      
<!-- Additional Features Tab -->
<div class="tab-pane fade" id="additional" role="tabpanel" aria-labelledby="additional-tab">
  <div class="card shadow-lg rounded-lg">
    <div class="card-header bg-primary text-white rounded-top-lg py-3">
        <h3 class="card-title mb-0 text-center">Additional Features</h3>
    </div>
    <div class="card-body py-4 px-3 px-md-4">
        <form id="additionalSettingsForm" method="POST">
            <h4 class="mt-2">Display Options</h4>
            <div class="form-group">
                <label for="darkModeToggle" class="col-form-label">Dark Mode:</label>
                <div class="custom-control custom-switch custom-control-inline">
                    <input type="checkbox" class="custom-control-input" id="darkModeToggle" name="dark_mode">
                    <label class="custom-control-label" for="darkModeToggle">Enable Dark Mode</label>
                </div>
            </div>

            <h4 class="mt-4">Language and Localization</h4>
            <div class="form-group">
                <label for="languageSelect" class="col-form-label">Language:</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-globe"></i></span>
                    </div>
                    <select class="form-control form-control-lg" id="languageSelect" name="language">
                        <option value="English">English</option>
                        <option value="French">French</option>
                        <option value="Spanish">Spanish</option>
                        <option value="Custom">Custom</option>
                    </select>
                </div>
                <small id="languageSelectHelp" class="form-text text-muted">
                    Select your preferred language.
                </small>
            </div>
            <div class="form-group" id="customLanguageDiv" style="display:none;">
                <label for="customLanguage" class="col-form-label">Custom Language:</label>
                 <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-edit"></i></span>
                    </div>
                    <input type="text" class="form-control form-control-lg" id="customLanguage" name="custom_language" placeholder="Enter custom language">
                </div>
                <small id="customLanguageHelp" class="form-text text-muted">
                    Enter your custom language.
                </small>
            </div>

            <button type="submit" class="btn btn-primary btn-lg btn-block mt-4">Save Additional Settings</button>
        </form>
    </div>
    <div class="card-footer text-muted text-center py-2 rounded-bottom-lg">
        Configure additional features and preferences.
    </div>
</div>

  <!-- Dynamic Table Displaying Global Additional Settings -->
  <div id="additionalSettingsDisplay" class="mt-4">
    <!-- The dynamic table will be loaded here -->
  </div>
</div>
<div class="tab-pane fade" id="backUp" role="tabpanel">
    <p>This section contains backup of system data from the transactions and items tables.</p>

    <!-- Backup Progress (Visible only to Super Admin) -->
    <div id="backupProgressSection" style="display: none;">
        <p>Backup is in progress...</p>
        <div class="progress">
            <div id="backupProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;">0%</div>
        </div>
    </div>

    <div class="card shadow-lg rounded-lg">
    <div class="card-header bg-primary text-white rounded-top-lg py-3">
        <h3 class="card-title mb-0 text-center">Backup & Restore</h3>
    </div>
    <div class="card-body py-4 px-3 px-md-4">
        <div class="mb-4">
            <h4 class="mb-3">Download Backup</h4>
            <p class="text-muted mb-2">
                Download a backup of your current data.  It is recommended to do this regularly.
            </p>
            <button id="downloadBackup" class="btn btn-primary btn-lg btn-block">
                <i class="fas fa-download mr-2"></i> Download Latest Backup
            </button>
        </div>

        <hr class="my-4">

        <div>
            <h4 class="mb-3">Restore Backup</h4>
            <p class="text-muted mb-2">
                Restore data from a previously saved backup file.  This will overwrite your current data.
            </p>
            <form id="restoreForm" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="backupFile" class="col-form-label">Select Backup File (.xlsx):</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-upload"></i></span>
                        </div>
                        <div class="custom-file">
                            <input type="file" id="backupFile" name="backupFile" class="custom-file-input form-control-lg" accept=".xlsx" required aria-describedby="backupFileHelp">
                            <label class="custom-file-label" for="backupFile">Choose file</label>
                        </div>
                    </div>
                    <small id="backupFileHelp" class="form-text text-muted">
                        Select the Excel (.xlsx) file to restore.
                    </small>
                </div>
                <button type="submit" class="btn btn-success btn-lg btn-block mt-3">
                    <i class="fas fa-upload mr-2"></i> Restore Data
                </button>
            </form>
        </div>
    </div>
    <div class="card-footer text-muted text-center py-2 rounded-bottom-lg">
        Manage your data backups. Download a backup or restore from a previous one.
    </div>
</div>

<script>
    $(document).ready(function () {
        // Update the file input label
        $('#backupFile').on('change', function () {
            let fileName = $(this).val().split('\\').pop();
            $(this).next('.custom-file-label').html(fileName);
        });
    });
</script>
</div>



          </div><!-- end of tabs -->

                
                 
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<!-- settings modal ends here --> 
<script>
$(document).ready(function() {
    // Sanitization function to clean inputs
    function sanitizeInput(input) {
        return input.replace(/[^\w\s\-\.]/gi, ''); // Allow only alphanumeric, spaces, hyphens, and dots
    }

    // Client-side validation
    function validateForm(formData) {
        let isValid = true;
        let businessName = formData.get('business_name');
        let currency = formData.get('currency');

        // Validate Company Name
        if (!businessName || businessName.trim() === '') {
            isValid = false;
            Toastify({
                text: "Company Name is required.",
                duration: 5000,
                gravity: 'top',
                close: true,
                style: {
                    background: 'linear-gradient(to right, #ff5b5b, #f6b57e)', // Red color for errors
                }
            }).showToast();
        } 

        // Validate Currency selection
        if (currency === 'custom' && !formData.get('currency_custom')) {
            isValid = false;
            Toastify({
                text: "Custom currency is required.",
                duration: 5000,
                gravity: 'top',
                close: true,
                style: {
                    background: 'linear-gradient(to right, #ff5b5b, #f6b57e)', // Red color for errors
                }
            }).showToast();
        }

        return isValid;
    }

    // Handle currency selection change to show or hide custom currency input
    $('#currencySelect').change(function() {
        if ($(this).val() === 'custom') {
            $('#customCurrencyDiv').slideDown(300); // Show custom currency input with animation
        } else {
            $('#customCurrencyDiv').slideUp(300); // Hide custom currency input with animation
        }
    });

    // Logo upload preview
    $("#logoUpload").change(function() {
        let file = this.files[0];
        if (file) {
            let reader = new FileReader();
            reader.onload = function(e) {
                $("#logoPreview").attr("src", e.target.result).removeClass("d-none");
            };
            reader.readAsDataURL(file);
        }
    });

    // Form submission
    $("#generalSettingsForm").submit(function(e) {
        e.preventDefault();
        let formData = new FormData(this);

        // Sanitize inputs
        let sanitizedBusinessName = sanitizeInput(formData.get('business_name'));
        formData.set('business_name', sanitizedBusinessName);

        // Validate the form data
        if (!validateForm(formData)) {
            return; // Prevent form submission if validation fails
        }

        // Show loader
        let loader = $('<div class="loading-overlay"><div class="spinner-border text-primary" role="status"></div></div>');
        $('body').append(loader);

        $.ajax({
            url: "save_settings.php", //php endpoint 
            type: "POST",
            dataType: 'json',
            data: formData,
            contentType: false,
            processData: false,
            beforeSend: function() {
                loader.fadeIn();
            },
            success: function(response) {
                loader.fadeOut(function() { $(this).remove(); });

                // Check the response status and show the appropriate message
                if (response.status === 'success') {

                   // Clear form inputs after successful submission
    $('#generalSettingsForm')[0].reset(); // Reset the form fields
    $('#logoPreview').addClass("d-none");  // Hide the logo preview
    
                    Toastify({
                        text: response.message,
                        duration: 5000,
                        gravity: 'top',
                        close: true,
                        style: {
                            background: 'linear-gradient(to right, #00b09b, #96c93d)', // Green color for success
                        }
                    }).showToast();
                } else {
                    Toastify({
                        text: response.message,
                        duration: 5000,
                        gravity: 'top',
                        close: true,
                        style: {
                            background: 'linear-gradient(to right, #ff5b5b, #f6b57e)', // Red color for errors
                        }
                    }).showToast();
                }
            },
            error: function(xhr, status, error) {
                loader.fadeOut(function() { $(this).remove(); });

                // Show the actual error from the response or a generic message
                let errorMessage = xhr.responseJSON?.message;
                Toastify({
                    text: errorMessage,
                    duration: 5000,
                    gravity: 'top',
                    close: true,
                    style: {
                        background: 'linear-gradient(to right, #ff5b5b, #f6b57e)', // Red color for errors
                    }
                }).showToast();
            }
        });
    });
});
</script>

<script>
    $(document).ready(function () {
        // Fetch users and roles
        function loadUsersRoles() {
        $.ajax({
            url: 'fetch_users_roles.php',//php endpoint 
            type: 'GET',
            dataType: 'json', // Expect JSON response
            success: function (response) {
                if (response.status === 'success') {
                    let userOptions = response.users.map(user => 
                        `<option value="${user.id}">${user.name}</option>`
                    ).join('');
                    
                    let roleOptions = response.roles.map(role => 
                        `<option value="${role.id}">${role.name}</option>`
                    ).join('');

                    $('#userSelect').html(userOptions);
                    $('#roleSelect1').html(roleOptions);
                }
            },
            error: function (xhr, status, error) {
                console.error("Error fetching users and roles:", error);
            }
        });
    }
    loadUsersRoles();

    function getUserRoles(){

      $.ajax({
          url: 'user_and_roles.php',//php endpoint 
          type: 'GET',
          success: function(data){
              $('#assignedUsersContainer').html(data);
          },
          error: function(){
              console.error('Error refreshing the assigned users table.');
          }
      });
    }
    getUserRoles();

$('#usersRolesForm').submit(function (e) {
    e.preventDefault(); // prevent default form submission

    let formData = $(this).serialize(); // serialize all form data

    $.ajax({
        url: 'update_user_role.php',
        type: 'POST',
        data: formData,
        success: function (response) {
            if (typeof response === 'string') {
                response = JSON.parse(response);
            }

            if (response.status === 'success') {
                Swal.fire('Success', response.message, 'success');
                getUserRoles(); // Refresh roles display
            } else if (response.status === 'error') {
                Swal.fire('Error', response.message || 'An unexpected error occurred.', 'error');
            } else {
                console.error('Unexpected response format:', response);
                Swal.fire('Error', 'Invalid response format.', 'error');
            }
        },
        error: function (xhr, status, error) {
            console.log('AJAX Error:', error);
            Swal.fire('Error', 'There was an error submitting the form.', 'error');
        }
    });
});/* ends */

    });

     // -- Audit Logs Section --
     $(document).ready(function () {
  // Function to load the latest audit logs via AJAX (as implemented previously)
  function loadAuditLogs() {
    $.ajax({
      url: 'fetch_audit_logs.php',//php endpoint 
      type: 'GET',
      dataType: 'json',
      success: function (response) {
        if(response.status === 'success') {
          let rows = response.logs.map(log => `
            <tr>
              <td>${log.log_id}</td>
              <td>${log.username}</td>
              <td>${log.action}</td>
              <td>${log.timestamp}</td>
            </tr>
          `).join('');
          $('#auditLogsBody').html(rows);
        }
      },
      error: function (xhr, status, error) {
        console.error('Error loading audit logs:', error);
      }
    });
  }
  
  // Load audit logs when the Audit Logs tab is shown
  $('a[href="#auditLogs"]').on('shown.bs.tab', function () {
    loadAuditLogs();
  });
  
  // Export to Excel button click handler
  $('#exportExcel').click(function(){
    // Get the table element
    var table = document.getElementById('auditLogsTable');
    
    // Use XLSX to convert the table into a workbook
    var workbook = XLSX.utils.table_to_book(table, {sheet: "Audit Logs"});
    
    // Generate a file and trigger a download with the desired file name
    XLSX.writeFile(workbook, "AuditLogs.xlsx");
  });
  
  // Optional: Redirect to a full audit logs page if required
  $('#viewAllLogs').click(function(){
    window.location.href = 'export_audit_logs.php';
  });
});

$(document).ready(function(){
  // Update the price type label based on the toggle state
  $('#priceTypeToggle').change(function() {
    var currentState = $(this).prop('checked') ? 'Wholesale' : 'Retail';
    $('#priceTypeLabel').text(currentState);
  });

      // Function to load the dynamic additional settings table
      function loadPriceTypeStatus() {
    $('#displayPriceTypeStatus').load('display_settings.php'); //php endpoint 
  }

    // Initially load the table
    loadPriceTypeStatus();

  // Handle Sales Config form submission via AJAX
  $('#salesConfigForm').submit(function(e){
    e.preventDefault(); // Prevent the default form submission

    // Determine price type based on the toggle state:
    // If checked, use 'Wholesale', else 'Retail'
    var priceType = $('#priceTypeToggle').prop('checked') ? 'Wholesale' : 'Retail';

    // Create a FormData object from the form and add the price_type value
    var formData = new FormData(this);
    formData.set('price_type', priceType);

      // Show loader
      let loader = $('<div class="loading-overlay"><div class="spinner-border text-primary" role="status"></div></div>');
        $('body').append(loader);


    $.ajax({
      url: 'save_sales_config.php',//php endpoint
      type: 'POST',
      data: formData,
      contentType: false,
      processData: false,
      dataType: 'json',
      beforeSend: function(){
        // Optionally, display a loading spinner or disable the submit button
        loader.fadeIn();
        
      },
      success: function(response){
        loader.fadeOut(function() { $(this).remove(); });

        if(response.status === 'success'){
          Swal.fire('Success', response.message, 'success');
          // Optionally, you can clear or update form fields here
          loadPriceTypeStatus();
        } else {
          Swal.fire('Error', response.message, 'error');
        }
      },
      error: function(xhr, status, error){
        loader.fadeOut(function() { $(this).remove(); });
        Swal.fire('Error', 'An error occurred while saving the settings.', 'error');
      }
    });
  });
});

$(document).ready(function(){
   // Function to load the dynamic INVENTORY settings table
   function loadInventorySettings() {
    $('#inventorySettings').load('display_inventory_sett.php');//php endpoint 
  }
  loadInventorySettings();

  $('#inventorySettingsForm').submit(function(e){
    e.preventDefault(); // Prevent the default form submission

      

    // Serialize the form data
    var formData = $(this).serialize();

    $.ajax({
      url: 'save_inventory_settings.php',//php endpoint
      type: 'POST',
      data: formData,
      dataType: 'json',
      success: function(response){
        if(response.status === 'success'){ 
          Swal.fire('Success', response.message, 'success');
          loadInventorySettings()
        } else {
          Swal.fire('Error', response.message, 'error');
        }
      },
      error: function(xhr, status, error){
        Swal.fire('Error', 'An error occurred while saving inventory settings.', 'error');
      }
    });
  });
});


$(document).ready(function(){
  // Show/hide the custom language input based on language selection
  $('#languageSelect').change(function(){
    if($(this).val() === 'Custom'){
      $('#customLanguageDiv').slideDown();
    } else {
      $('#customLanguageDiv').slideUp();
      $('#customLanguage').val(''); // clear custom language input when not needed
    }
  });

    // Function to load the dynamic additional settings table
    function loadAdditionalSettingsTable() {
    $('#additionalSettingsDisplay').load('display_additional_settings.php');//php endpoint
  }

    // Initially load the table
    loadAdditionalSettingsTable();
  
  // Handle Additional Features form submission via AJAX
  $('#additionalSettingsForm').submit(function(e){
    e.preventDefault(); // Prevent default form submission

    

    // Manually capture values:
    let dark_mode = $('#darkModeToggle').prop('checked') ? 1 : 0;
    let language = $('#languageSelect').val();
    let custom_language = $('#customLanguage').val();

    // Build the data object
    let dataObj = {
      dark_mode: dark_mode,
      language: language,
      custom_language: custom_language
    };

    $.ajax({
      url: 'save_additional_settings.php', //php endpoint
      type: 'POST',
      data: dataObj,
      dataType: 'json',
      success: function(response){
        if(response.status === 'success'){
          Swal.fire('Success', response.message, 'success');
                    // Refresh the dynamic table after successful update
                    loadAdditionalSettingsTable();
        } else {
          Swal.fire('Error', response.message, 'error');
        }
      },
      error: function(xhr, status, error){
        Swal.fire('Error', 'An error occurred while saving additional settings.', 'error');
      }
    });
  });
});





</script>
<script>
$(document).ready(function () {
   
$("#downloadBackup").click(function () {
    $.ajax({
        url: "backup.php", 
        type: "POST",
        success: function (response) {
            var res = JSON.parse(response);
            if (res.success) {
                window.location.href = "download.php?file=" + res.file;
            } else {
                showAlert("Backup Failed", res.message, "error");
            }
        },
        error: function () {
            showAlert("Error", "An unexpected error occurred during backup.", "error");
        }
    });
});

$("#restoreForm").submit(function (e) {
    e.preventDefault();
    var formData = new FormData(this);

    // Initialize progress tracking
    let progress = 0;
    let progressBarInterval;

    // Show SweetAlert2 progress indicator
    Swal.fire({
        title: "Restoring Backup...",
        html: `
            <div class="progress-container" style="width: 100%; background: #eee; border-radius: 5px; overflow: hidden;">
                <div class="progress-bar" style="width: 100%; height: 20px; background: #ddd;">
                    <div class="progress" id="progress-bar" style="width: 0%; height: 100%; background: #28a745;"></div>
                </div>
                <p id="progress-text" style="margin-top: 10px;">Initializing...</p>
            </div>
        `,
        showConfirmButton: false,
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            let progressBar = document.getElementById("progress-bar");
            let progressText = document.getElementById("progress-text");

            // Simulate gradual progress increase
            progressBarInterval = setInterval(() => {
                if (progress < 90) {
                    progress += 10;
                    progressBar.style.width = progress + "%";
                    progressText.innerText = "Restoring... " + progress + "%";
                }
            }, 500);
        }
    });

    $.ajax({
        url: "restore.php",
        type: "POST",
        data: formData,
        dataType: "json",
        contentType: false,
        processData: false,
        success: function (response) {
            clearInterval(progressBarInterval); // Stop fake progress

            // Ensure the progress bar reaches 100%
            $("#progress-bar").css("width", "100%");
            $("#progress-text").text("Finalizing...");

            setTimeout(() => {
                Swal.close();

                if (response.success) {
                    Swal.fire({
                        icon: "success",
                        title: "Restore Completed",
                        html: `<b>${response.message}</b><br>
                               Transactions restored: <b>${response.transactions ?? 0}</b><br>
                               Items restored: <b>${response.items ?? 0}</b>`,
                        confirmButtonColor: "#3085d6",
                        confirmButtonText: "OK"
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Restore Failed",
                        text: response.message
                    });
                }
            }, 500); // Give a slight delay before closing the progress modal
        },
        error: function (xhr, status, error) {
            clearInterval(progressBarInterval);
            Swal.close();

            Swal.fire({
                icon: "error",
                title: "Error",
                text: `An unexpected error occurred: ${xhr.responseText || error}`
            });
        }
    });
});


});// end of doc ready.

</script>
 <script src="js/sync_system.js"></script>
</body>
</html>