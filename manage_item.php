<?php
session_start(); // Make sure to start the session at the beginning of your script
// Add this near the top of your PHP block
$filter_branch_name = isset($_GET['branch_name']) ? urldecode($_GET['branch_name']) : "Global Scope";

if (isset($_SESSION['role'])) {
    $user_role = $_SESSION['role'];
    if ($user_role === "Admin" || $user_role === "Super Admin"  ) {
        $userID = $_SESSION['user_id'];
        $userName = $_SESSION['username'];
        $userRole = $_SESSION['role'];
      } else {
        header("Location:404.php");
      }
} else {
 
    header("Location:404.php"); 
    exit; // Stop the script to prevent further execution  
}

// --- 2. DYNAMIC CONTEXT RESOLUTION ---
// Priority 1: URL Parameter (Super Admin viewing another branch)
// Priority 2: Session Branch (User viewing their own data)
$filter_branch_uuid = isset($_GET['branch_uuid']) ? $_GET['branch_uuid'] : null;
$filter_branch_name = isset($_GET['branch_name']) ? urldecode($_GET['branch_name']) : null;
$session_branch     = $_SESSION['branch_code']; // e.g., "HEAD_OFFICE", "BRANCH_001", etc.


// The Final Context for this page load
$page_context_branch = $filter_branch_uuid ? $filter_branch_uuid : $session_branch;
$page_context_name   = isset($_GET['branch_name']) ? urldecode($_GET['branch_name']) : $page_context_branch;

// Ensure we have a valid context
if (empty($page_context_branch)) {
    // Fallback or Error handling if no branch is determined
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
require 'defined_global_settings.php'; 
?>


<!DOCTYPE html>
<!DOCTYPE html>
<html lang="en">  

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>IKA Admin</title>
  <link rel="stylesheet" href="vendors/feather/feather.css">
  <link rel="stylesheet" href="vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="vendors/css/vendor.bundle.base.css">
  <link rel="stylesheet" href="vendors/datatables.net-bs4/dataTables.bootstrap4.css">
  <link rel="stylesheet" href="vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" type="text/css" href="js/select.dataTables.min.css">
  <link rel="stylesheet" href="css/vertical-layout-light/style.css">
  <link rel="shortcut icon" href="<?php echo (defined('BUSINESS_LOGO') && !empty(BUSINESS_LOGO)) ? BUSINESS_LOGO : 'images/favicon.png'; ?>" />

   <!-- Font Awesome for icons --> 
    <link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css">

  <link rel="stylesheet" href="node_modules/toastify-js/src/toastify.css" />

<script src="highchartsLib/code/highcharts.js"></script>
<script src="highchartsLib/code/modules/accessibility.js"></script>

  <link rel="stylesheet" href="sweetalert2/dist/sweetalert2.min.css"> 
<script src="sweetalert2/dist/sweetalert2.all.min.js"></script>

<?php if (defined('DARK_MODE') && DARK_MODE == 1): ?>
    <link rel="stylesheet" type="text/css" href="css/dark-theme.css">
<?php else: ?>
    <link rel="stylesheet" type="text/css" href="css/light-theme.css">
<?php endif; ?>

<script src="jquery/jquery-3.6.0.min.js"></script> 


  <script src="sheetjs/dist/xlsx.full.min.js"></script>

<link rel="stylesheet" href="node_modules/tippy.js/dist/tippy.css">

<script src="node_modules/@popperjs/core/dist/umd/popper.js"></script>

<script src="node_modules/tippy.js/dist/tippy.umd.min.js"></script>
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

  <script>
    
    // Pass PHP global settings to JavaScript variables
    const LOW_STOCK_THRESHOLD = <?php echo LOW_STOCK_THRESHOLD; ?>;
    const ENABLE_LOW_STOCK_ALERT = <?php echo (ENABLE_LOW_STOCK_ALERT ? 'true' : 'false'); ?>;
</script>

    
   

<link rel="stylesheet" href="css/expiring_item_style.css">

  <style>
  .accordion {
    background-color: #f5f5f5;  
    color: #333;
    cursor: pointer;
    padding: 18px;
    width: 100%;
    border: none;
    text-align: left;
    outline: none;
    font-size: 18px;
    transition: 0.4s;
  }

  .active, .accordion:hover {
    background-color: #ddd;
  }

  .accordion:after {
    content: '\002B';
    color: #777;
    font-weight: bold;
    float: right;
    margin-left: 5px;
  }

  /*.active:after {*/
  /* content: "\2212";*/
  /*}*/

  .panel {
    padding: 0 18px;
    background-color: white;
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.2s ease-out;
  }

  .table-container {
    max-height: 400px; /* Adjust the height as needed */
    overflow-y: auto;
    position: relative;
}

.table thead {
    position: sticky;
    top: 0;
    background: #343a40; /* Dark background for contrast */
    color: white;
    z-index: 10;
}


</style>
<style>
/* General Table Styling */
.table-container {
    background-color: #ffffff;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    padding: 20px;
}

.table {
    width: 100%;
    margin-bottom: 1rem;
    color: #333;
    border-collapse: collapse; /* Ensure borders are collapsed for a cleaner look */
}

.table th,
.table td {
    padding: 12px 15px;
    vertical-align: middle;
    border-top: 1px solid #dee2e6;
}

.table thead th {
    background-color: #e9ecef;
    color: #495057;
    font-weight: 600;
    border-bottom: 2px solid #dee2e6;
    text-align: left;
}

/* Row Hover Effect */
.table tbody tr:hover {
    background-color: #f8f9fa;
    transition: background-color 0.2s ease-in-out;
}

/* Highlight for stock levels */
.table-danger {
    background-color: #fcecec !important; /* Light red for out of stock/expired */
    color: #a94442 !important;
}

.table-warning {
    background-color: #fff3cd !important; /* Light yellow for low stock */
    color: #8a6d3b !important;
}

/* Status Badges */
.status-badge {
    display: inline-block;
    padding: .35em .6em;
    font-size: 75%;
    font-weight: 700;
    line-height: 1;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: .5rem; /* More rounded for pill shape */
    transition: all 0.2s ease-in-out;
}

.status-badge.out-of-stock {
    color: #fff;
    background-color: #dc3545; /* Bootstrap danger */
}

.status-badge.low-stock {
    color: #212529;
    background-color: #ffc107; /* Bootstrap warning */
}

.status-badge.in-stock {
    color: #fff;
    background-color: #28a745; /* Bootstrap success */
}

/* Reporting Button Style */
.btn-reporting {
    background-color: #ffc107; /* Warning yellow */
    border-color: #ffc107;
    color: #212529;
    transition: all 0.2s ease-in-out;
}

.btn-reporting:hover {
    background-color: #e0a800;
    border-color: #e0a800;
    color: #212529;
}

/* Edit Button Style */
.btn-edit {
    background-color: #007bff; /* Primary blue */
    border-color: #007bff;
    color: #fff;
    transition: all 0.2s ease-in-out;
}

.btn-edit:hover {
    background-color: #0056b3;
    border-color: #0056b3;
    color: #fff;
}

/* Checkbox Styling */
.delete-checkbox {
    width: 18px;
    height: 18px;
    cursor: pointer;
    margin-right: 5px;
}

/* Filter and Alert Sections */
.filter-section, .alert-section {
    background-color: #ffffff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
    padding: 15px 20px;
    margin-bottom: 20px;
}

.filter-section .form-control {
    border-radius: 5px;
}

.alert {
    border-radius: 8px;
    padding: 15px 20px;
}

.alert strong {
    font-weight: 700;
}
</style>

<style>
/* Professional UI Optimizations */
.control-panel .form-row {
    margin-bottom: -15px; /* Compensate for form-group bottom margin */
}
.control-panel .form-group {
    margin-bottom: 15px;
}
.alert-toolbar {
    display: flex;
    flex-flow: row wrap;
    gap: 10px; /* Spacing between alerts */
}
.alert-toolbar .alert {
    margin-bottom: 0 !important; /* Override default alert margin */
    flex-grow: 1;
    min-width: 200px; /* Ensure alerts don't get too squished */
}
.action-buttons {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}
.search-and-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap; /* Allow wrapping on small screens */
}

@media (max-width: 992px) {
    .search-and-actions {
        flex-direction: column;
        align-items: flex-start !important;
    }
    .action-buttons {
        justify-content: flex-start;
        width: 100%;
        margin-top: 10px;
    }
    .search-bar-group {
        width: 100%;
    }
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

</head>
<body class=" dark-theme light-theme" x-data="expiringItemsAlert()">
      <script src="node_modules/toastify-js/src/toastify.js"></script>

<center><div id="backUpStatus" style="position: relative;"></div></center>

 

<div x-data="backupSystem()" x-init="init()">
    <button @click="confirmBackup()" style="padding: 10px; background: #3085d6; color: white; border: none; cursor: pointer;">
        Start Backup
    </button>

    <div x-show="isBackingUp" class="progress-container">
        <div class="progress-bar" :style="'width:' + progress + '%'"></div>
        <p x-text="'Progress: ' + progress + '%'"></p>
    </div>

    <div id="tippy-container" style="position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 1000;"></div>
</div>

<!-- this script handles the routine backup of data -->
<script src="js/backup_logic.js"></script>

  <div class="container-scroller  dark-theme light-theme navbar-dark">
    <nav class="navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row  dark-theme light-theme">
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
      <div class="navbar-menu-wrapper d-flex align-items-center justify-content-end  dark-theme light-theme navbar-dark">
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
    

</ul>
        <ul class="navbar-nav navbar-nav-right  dark-theme light-theme navbar-dark">
            <li class="nav-item d-none d-lg-flex align-items-center mr-3">
    <div class="badge badge-info badge-pill px-3 py-2" style="font-size: 0.9rem; background: linear-gradient(45deg, #1a2980, #26d0ce); border: none;">
        <i class="fas fa-map-marker-alt mr-2"></i> 
        <?php 
            // Display Branch Name if set, otherwise Branch Code, fallback to Global
            echo htmlspecialchars($_SESSION['branch_name'] ?? $_SESSION['branch_code'] ?? 'Global View'); 
        ?>
    </div>
</li>
        <li class="nav-item  dark-theme light-theme navbar-dark">Privilege Level: <?php echo $_SESSION['role']; ?></li>
          <li class="nav-item nav-profile dropdown  dark-theme light-theme navbar-dark">
            <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown" id="profileDropdown">
            <span class="menu-title  dark-theme light-theme navbar-dark"><i class="icon-head menu-icon "></i> <?php echo $_SESSION['username'];?></span>

            </a>
            <div class="dropdown-menu dropdown-menu-right navbar-dropdown  dark-theme light-theme navbar-dark" aria-labelledby="profileDropdown">
              <a class="dropdown-item  dark-theme light-theme navbar-dark" href="logout.php">
                <i class="ti-power-off text-primary"></i>
                Logout
              </a>
            </div>
          </li>
        </ul>
      </div>
    </nav>
    <div class="container-fluid page-body-wrapper  dark-theme light-theme navbar-dark">
      <nav class="sidebar sidebar-offcanvas  dark-theme light-theme navbar-dark" id="sidebar">
<ul class="nav dark-theme light-theme navbar-dark">
    
    <?php if ($user_role === "Super Admin"): ?>
        <li class="nav-item dark-theme light-theme navbar-dark">
            <a class="nav-link" href="<?php echo linkTo('superAdmin.php'); ?>">
                <i class="icon-grid menu-icon"></i>
                <span class="menu-title">Dashboard</span>
            </a>
        </li>
        <li class="nav-item dark-theme light-theme navbar-dark">
            <a class="nav-link" href="<?php echo linkTo('transactions.php'); ?>">
                <i class="ti-receipt menu-icon"></i>
                <span class="menu-title">Transactions</span>
            </a>
        </li>

            <?php if (!$is_visiting): ?>
         <li class="nav-item dark-theme light-theme navbar-dark">
            <a class="nav-link" href="<?php echo linkTo('manage_user.php'); ?>">
                <i class="icon-head menu-icon"></i><span class="menu-title">User Mgt. & App Setting</span>
            </a>
         </li>
     <?php endif; ?>
    <?php endif; ?>



    <?php if ($user_role === "Admin"): ?>
        <li class="nav-item dark-theme light-theme navbar-dark">
            <a class="nav-link" href="<?php echo linkTo('admin.php'); ?>">
                <i class="icon-grid menu-icon"></i>
                <span class="menu-title">Dashboard</span>
            </a>
        </li>
        <li class="nav-item dark-theme light-theme navbar-dark">
            <a class="nav-link" href="<?php echo linkTo('user_roles.php'); ?>">
                <i class="icon-head menu-icon"></i>
                <span class="menu-title">Users & Roles</span>
            </a>
        </li>
    <?php endif; ?>

    <li class="nav-item dark-theme light-theme navbar-dark">
        <a class="nav-link" href="<?php echo linkTo('store_keeper.php'); ?>">
            <i class="icon-plus menu-icon"></i>
            <span class="menu-title">Add Items & More</span>
        </a>
    </li>

    <li class="nav-item dark-theme light-theme navbar-dark" id="manageItemsNavItem">
        <a class="nav-link" href="<?php echo linkTo('manage_item.php'); ?>">
            <i class="ti-package menu-icon"></i>
            <span class="menu-title">Manage Items</span>
        </a>
        
        <div class="expiring-alert-container" x-show="showAlert || showExpired">
            <template x-if="showAlert && hasExpiring()">
                <div class="expiring-alert">
                    <button class="close-button" @click="showAlert = false">&times;</button>
                    <template x-if="expiring7Days > 0">
                        <p><strong><span x-text="expiring7Days"></span></strong> item(s) expire in 7 days</p>
                    </template>
                    <template x-if="expiring14Days > 0">
                        <p><strong><span x-text="expiring14Days"></span></strong> item(s) expire in 2 weeks</p>
                    </template>
                    <template x-if="expiring21Days > 0">
                        <p><strong><span x-text="expiring21Days"></span></strong> item(s) expire in 3 weeks</p>
                    </template>
                </div>
            </template>
            <template x-if="showExpired">
                <div class="expired-alert">
                    <button class="close-button" @click="showExpired = false">&times;</button>
                    <p><strong><span x-text="expiredItems"></span></strong> item(s) have expired.</p>
                </div>
            </template>
        </div>
    </li>

    <?php if ($user_role === "Super Admin"): ?>
        <li class="nav-item dark-theme light-theme navbar-dark">
            <a class="nav-link" href="<?php echo linkTo('pos.php'); ?>">
                <i class="ti-shopping-cart menu-icon"></i>
                <span class="menu-title">Make Sales</span>
            </a>
        </li>
    <?php endif; ?>

</ul>

        
    <script>

    </script>
   <script src="js/expiring_items_alpine.js"></script>
    <script>
      
    </script>

      </nav>
      <div class="main-panel  dark-theme light-theme navbar-dark">
        <div class="content-wrapper  dark-theme light-theme navbar-dark">
          <div class="row  dark-theme light-theme navbar-dark">

            <div class="col-md-12 grid-margin stretch-card  dark-theme light-theme navbar-dark">
              <div class="card  dark-theme light-theme navbar-dark">
                <div class="card-body  dark-theme light-theme navbar-dark">
                  <p class="card-title  dark-theme light-theme navbar-dark">Manage Inventory Items</p>
                </div>
              </div>
            </div>

            <div class="col-12 grid-margin">
                <div class="card card-body dark-theme light-theme navbar-dark alert-section"> 
                    <div class="alert-toolbar">
                        <div class="alert alert-info text-center" id="totalItemsCount" style="font-size: 1.1rem; font-weight: bold;">
                            Loading total items...
                        </div>
                        
                        <div id="expiredItemsAlert" class="alert alert-danger d-none" role="alert">
                            <strong><i class="fas fa-exclamation-triangle"></i> Alert:</strong> You have <span id="expiredItemsCount">0</span> expired items!
                        </div>
                        <div id="expiringSoonAlert" class="alert alert-warning d-none" role="alert">
                            <strong><i class="fas fa-hourglass-half"></i> Warning:</strong> <span id="expiringSoonCount">0</span> items are expiring soon (within 7 days).
                        </div>
                        <div id="lowStockAlert" class="alert alert-warning d-none" role="alert">
                            <strong><i class="fas fa-exclamation-circle"></i> Low Stock:</strong> <span id="lowStockCount">0</span> items are running low.
                        </div>
                        <div id="outOfStockAlert" class="alert alert-danger d-none" role="alert">
                            <strong><i class="fas fa-times-circle"></i> Out of Stock:</strong> <span id="outOfStockCount">0</span> items are out of stock.
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 grid-margin">
                <div class="card control-panel dark-theme light-theme navbar-dark">
                    <div class="card-body">
                        <div class="search-and-actions mb-3">
                            <div class="form-group mb-0 search-bar-group" style="flex-grow: 1; min-width: 300px;">
                                <input type="text" id="searchInput" name="searchInput" class="form-control dark-theme light-theme navbar-dark" placeholder="Search Items By Unique Number Or Name!">
                            </div>
                            <div class="action-buttons">
                                <button class="btn btn-danger" id="deleteSelectedBtn">
                                    <i class="fas fa-trash mr-2"></i> Delete Selected
                                </button>
                                <button id="exportToExcel" class="btn btn-success">
                                    <i class="fas fa-file-excel mr-2"></i> Export to Excel
                                </button>
                            </div>
                        </div>
                        
                        <div class="row filter-section" style="margin-bottom: 0 !important; padding: 0; box-shadow: none; background: none;">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="expirationFilter">Filter by Expiration:</label>
                                    <select id="expirationFilter" class="form-control">
                                        <option value="all">All Items</option>
                                        <option value="7">Expiring in 7 Days</option>
                                        <option value="14">Expiring in 14 Days</option>
                                        <option value="30">Expiring in 30 Days</option>
                                        <option value="60">Expiring in 60 Days</option>
                                        <option value="expired">Expired Items</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="categoryFilter">Filter by Category:</label>
                                    <select id="categoryFilter" class="form-control">
                                        <option value="all">All Categories</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="filterByStatus">Filter by Status:</label>
                                    <select id="filterByStatus" class="form-control dark-theme light-theme navbar-dark" style="width: 100%;">
                                        <option value="all">All</option>
                                        <option value="amber">Low Stock (Amber)</option>
                                        <option value="red">Out of Stock (Red)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 grid-margin">
                <div class="card dark-theme light-theme navbar-dark">
                    <div class="card-body">
                        <div id="loader" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.8); z-index:9999; text-align:center;">
                            <div style="position:relative; top:50%; transform:translateY(-50%);">
                                <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                                    <span class="sr-only">Loading...</span>
                                </div>
                            </div>
                        </div>

                        <div class="table-container">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th><input type="checkbox" class="delete-checkbox"></th>
                                            <th>Item ID</th>
                                            <th>Unique No.</th>
                                            <th>Name</th>
                                            <th>Description</th>
                                            <th>Purchase Price</th>
                                            <th>Wholesale</th>
                                            <th>Retail</th>
                                            <th>Quantity</th>
                                            <th>Status</th>
                                            <th>Category</th>
                                            <th>Expiration Date</th> 
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="itemsTableBody">
                                        </tbody>
                                </table>
                            </div>
                        </div>

                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center" id="paginationControls">
                                </ul>
                        </nav>
                    </div>
                </div>
            </div>

            </div>

        <footer class="footer  dark-theme light-theme navbar-dark">
          <div class="d-sm-flex justify-content-center justify-content-sm-between">
            <span class="text-muted text-center text-sm-left d-block d-sm-inline-block dark-theme light-theme navbar-dark">Copyright © <?php echo date('Y'); ?>.  Inventory Keeper App. All rights reserved.</span>
          </div>
        </footer>
        </div>
      </div>   
    </div>

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


  <div class="modal  dark-theme light-theme navbar-dark" id="reportingModal" tabindex="-1" role="dialog">
  <div class="modal-dialog  dark-theme light-theme navbar-dark" role="document">
    <div class="modal-content  dark-theme light-theme navbar-dark">
      <div class="modal-header  dark-theme light-theme navbar-dark">
        <h5 class="modal-title  dark-theme light-theme navbar-dark"></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body  dark-theme light-theme navbar-dark">
        <label for="specificDate">Select Specific Date:</label>
        <input type="date" id="specificDate" class="form-control mb-3" />

        <label for="specificMonth">Select Specific Month:</label>
        <input type="month" id="specificMonth" class="form-control mb-3"  placeholder="Enter Year and Month e.g 2024-11"/>

        <h5>Report Summary</h5>
        <p><strong>Total Amount Sold:</strong> <span id="reportTotalAmount"></span></p>
        <p><strong>Profit:</strong> <span id="reportProfit"></span></p>
        <p><strong>Quantity Sold:</strong> <span id="reportQuantitySold"></span></p>
        <p><strong>Quantity in Stock:</strong> <span id="reportQuantityStock"></span></p>

        <div id="reportChart" style="height: 400px;"></div>
      </div>
      <div class="modal-footer  dark-theme light-theme navbar-dark">
        <button type="button" class="btn btn-primary" id="generateReport">Generate Report</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>


<div class="modal dark-theme light-theme navbar-dark" id="editItemModal" tabindex="-1" role="dialog" aria-labelledby="editItemModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable dark-theme light-theme navbar-dark" role="document">
    <div class="modal-content dark-theme light-theme navbar-dark">

      <div class="modal-header">
        <h4 class="modal-title" id="editItemModalLabel">EDIT ITEM</h4>
        <button type="button" class="close btn-danger" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body">
        
        <ul class="nav nav-tabs" id="itemModalTabs" role="tablist">
          <li class="nav-item">
            <a class="nav-link active" id="edit-details-tab" data-toggle="tab" href="#editDetailsTab" role="tab" aria-controls="editDetailsTab" aria-selected="true">Edit Details</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" id="item-history-tab" data-toggle="tab" href="#itemHistoryTab" role="tab" aria-controls="itemHistoryTab" aria-selected="false">Item History</a>
          </li>
        </ul>

        <div class="tab-content" id="itemModalTabContent">
          
          <div class="tab-pane fade show active" id="editDetailsTab" role="tabpanel" aria-labelledby="edit-details-tab">
            <div class="p-3">
              <form id="editItemForm">
                <input type="hidden" name="itemID" id="itemID">
                
                <div class="row">
                  <div class="col-md-6">
                       <div class="form-group">
                        <label for="barcode number" class="col-form-label">Item Bar Code:</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-barcode text-dark"></i></span>
                            </div>
                            <input type="text" class="form-control form-control-lg" id="itembarcode" name="itembarcode" placeholder="Enter Item Bar Code Number">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="itemName" class="col-form-label">Item Name:</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-box-open text-dark"></i></span>
                            </div>
                            <input type="text" class="form-control form-control-lg" id="itemName" name="itemName" placeholder="Enter item name">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="itemDescription" class="col-form-label">Item Description:</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-file-text text-dark"></i></span>
                            </div>
                            <textarea class="form-control form-control-lg" id="itemDescription" name="itemDescription" rows="3" placeholder="Enter a description"></textarea>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="itemPrice" class="col-form-label">Purchase Price:</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-dollar-sign text-dark"></i></span>
                            </div>
                            <input type="number" class="form-control form-control-lg" id="itemPrice" name="itemPrice" min="0" step="0.01" placeholder="Enter purchase price" >
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="wholesale_price" class="col-form-label">Wholesale Price:</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-tags text-dark"></i></span>
                            </div>
                            <input type="number" class="form-control form-control-lg" id="wholesale_price" name="wholesale_price" min="0" step="0.01" placeholder="Enter wholesale price" >
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="retail_price" class="col-form-label">Retail Price:</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-store text-dark"></i></span>
                            </div>
                            <input type="number" class="form-control form-control-lg" id="retail_price" name="retail_price" min="0" step="0.01" placeholder="Enter retail price" >
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="itemQuantity" class="col-form-label">Quantity:</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-cubes text-dark"></i></span>
                            </div>
                            <input type="number" class="form-control form-control-lg" id="itemQuantity" name="itemQuantity" min="0" placeholder="Enter quantity" >
                        </div>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <div class="form-group">
                        <label for="expiration_date" class="col-form-label">Expiration Date:</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-calendar-alt text-dark"></i></span>
                            </div>
                            <input type="date" class="form-control form-control-lg" id="expiration_date" name="expiration_date" >
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="invoiceNumber" class="col-form-label">Invoice Number:</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-file-invoice text-dark"></i></span>
                            </div>
                            <input type="text" class="form-control form-control-lg" id="invoiceNumber" name="invoiceNumber"  placeholder="Enter invoice number" >
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="purchaseDate" class="col-form-label">Purchase Date:</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-calendar-alt text-dark"></i></span>
                            </div>
                            <input type="date" class="form-control form-control-lg" id="purchaseDate" name="purchaseDate" >
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="supplierName" class="col-form-label">Supplier Name:</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-truck text-dark"></i></span>
                            </div>
                            <input type="text" class="form-control form-control-lg" id="supplierName" name="supplierName" placeholder="Enter supplier name" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="cate" class="col-form-label">Item Category:</label>
                        <select id="category_Select" name="category_Select" class="form-control form-control-lg" >
                        <option id="category_Sel" style="color: #de1111ff !important;" selected></option>
                         <?php           
                         include('connection.php');
                         $selectHtml = '';
          // Fetch categories from the database
                          $query = "SELECT category_name FROM item_categories WHERE branch_code = '$session_branch' ORDER BY category_name";
                          $result = $conn->query($query);

                          if (!$result) {
                              die("Query failed: " . $conn->lastErrorMsg());
                          }
                              while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                                  $categoryName = $row['category_name'];
                              ?>
                          <option value="<?php echo $categoryName; ?>"><?php echo $categoryName; ?></option>
                        <?php }
// Close the database connection
$conn->close();    ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="itemStatus" class="col-form-label">Status:</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-info-circle text-dark"></i></span>
                            </div>
                            <input type="text" class="form-control form-control-lg" id="itemStatus" name="itemStatus"  readonly>
                        </div>
                    </div>
                  </div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg btn-block mt-4">
                    <i class="fas fa-save mr-2 text-dark"></i> Save Changes
                </button>
              </form>
            </div>
          </div>

          <!-- ====================================================== -->
          <!-- START: MODIFIED HISTORY TAB                          -->
          <!-- ====================================================== -->
          <div class="tab-pane fade" id="itemHistoryTab" role="tabpanel" aria-labelledby="item-history-tab">
            <div class="p-3">
              
              <!-- START: Filter UI -->
              <div class="row mb-3 p-3" style="background-color: #f8f9fa; border-radius: 8px;">
                <div class="col-md-3">
                  <div class="form-group">
                    <label for="historyStartDate">Start Date</label>
                    <input type="date" id="historyStartDate" class="form-control">
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group">
                    <label for="historyEndDate">End Date</label>
                    <input type="date" id="historyEndDate" class="form-control">
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group">
                    <label for="historyUserFilter">User</label>
                    <select id="historyUserFilter" class="form-control">
                      <option value="all" selected>All Users</option>
                      <!-- Users will be populated by JS -->
                    </select>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group">
                    <label for="historyActionFilter">Action Type</label>
                    <select id="historyActionFilter" class="form-control">
                      <option value="all" selected>All Actions</option>
                      <option value="sale">Sale</option>
                      <option value="modification">Modification</option>
                      <option value="adjustment">Adjustment</option>
                    </select>
                  </div>
                </div>
                <div class="col-12 d-flex justify-content-end">
                    <button class="btn btn-secondary mr-2" id="resetHistoryFilterBtn">Reset</button>
                    <button class="btn btn-primary" id="applyHistoryFilterBtn">Apply Filter</button>
                </div>
              </div>
              <!-- END: Filter UI -->

              <div id="historyLoader" style="display: none; text-align: center; padding: 40px;">
                <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                  <span class="sr-only">Loading History...</span>
                </div>
                <p class="mt-2">Loading History...</p>
              </div>

              <div class="table-responsive" id="historyTableContainer" style="max-height: 500px; overflow-y: auto;">
                <table class="table table-striped table-hover" id="itemHistoryTable">
                  <thead style="position: sticky; top: 0; background-color: inherit;">
                    <tr>
                      <th>Date / Time</th>
                      <th>User</th>
                      <th>Action</th>
                      <th>Details</th>
                    </tr> 
                  </thead>
                  <tbody id="itemHistoryTableBody">
                    <!-- History rows will be populated by JS -->
                  </tbody>
                </table>
              </div>
              
              <p id="noHistoryMessage" style="display: none; text-align: center; padding: 20px;">No history found for this item.</p>

              <!-- START: Pagination UI -->
              <nav aria-label="History Page Navigation" class="mt-4 d-flex justify-content-center">
                  <ul class="pagination" id="historyPaginationControls">
                      <!-- Pagination links will be populated by JS -->
                  </ul>
              </nav>
              <!-- END: Pagination UI -->

            </div>
          </div>
          <!-- ====================================================== -->
          <!-- END: MODIFIED HISTORY TAB                            -->
          <!-- ====================================================== -->

        </div> 
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
      </div>

    </div>
  </div>
</div>


  <div class="modal fade" id="addItemModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add New Inventory Item</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <form id="addItemForm">
             <div class="row">
                 <div class="col-md-6 form-group">
                     <label>Item Name</label>
                     <input type="text" class="form-control" id="itemName" name="itemName" required>
                 </div>
                 <div class="col-md-6 form-group">
                     <label>Bar Code (Unique No)</label>
                     <input type="number" class="form-control" id="itemUniqueNo" name="itemUniqueNo" required>
                 </div>
             </div>
             <div class="row">
                 <div class="col-md-4 form-group">
                     <label>Category</label>
                     <select class="form-control" id="categorySelect" name="categorySelect" required></select>
                 </div>
                 <div class="col-md-4 form-group">
                     <label>Quantity</label>
                     <input type="number" class="form-control" id="itemQuantity" name="itemQuantity" required>
                 </div>
                 <div class="col-md-4 form-group">
                     <label>Expiration Date</label>
                     <input type="date" class="form-control" id="expirationDate" name="expirationDate">
                 </div>
             </div>
             <div class="row">
                 <div class="col-md-4 form-group">
                     <label>Purchase Price</label>
                     <input type="number" step="0.01" class="form-control" id="itemPrice" name="itemPrice" required>
                 </div>
                 <div class="col-md-4 form-group">
                     <label>Wholesale Price</label>
                     <input type="number" step="0.01" class="form-control" id="wholesale_prc" name="wholesale" required>
                 </div>
                 <div class="col-md-4 form-group">
                     <label>Retail Price</label>
                     <input type="number" step="0.01" class="form-control" id="retail_prc" name="retail" required>
                 </div>
             </div>
             <div class="form-group">
                 <label>Description</label>
                 <textarea class="form-control" id="itemDescription" name="itemDescription" rows="2"></textarea>
             </div>
             <input type="hidden" id="supplierInfo" name="supplierInfo" value="N/A">
             <input type="hidden" id="invoiceNumber" name="invoiceNumber" value="N/A">
             <input type="hidden" id="datePurchased" name="datePurchased" value="<?php echo date('Y-m-d'); ?>">
             
             <button type="submit" class="btn btn-primary btn-block">Add Item</button>
          </form>
        </div>
      </div>
    </div>
  </div>
   

  <script src="vendors/js/vendor.bundle.base.js"></script>
  <script src="vendors/chart.js/Chart.min.js"></script>
  <script src="vendors/datatables.net/jquery.dataTables.js"></script>
  <script src="vendors/datatables.net-bs4/dataTables.bootstrap4.js"></script>
  <script src="js/dataTables.select.min.js"></script>

  <script src="js/off-canvas.js"></script>
  <script src="js/hoverable-collapse.js"></script>
  <script src="js/template.js"></script>
  <script src="js/settings.js"></script>
  <script src="js/todolist.js"></script>
  <script src="js/dashboard.js"></script>
  <script src="js/Chart.roundedBarCharts.js"></script>
  <script src="js/sync_system.js"></script>
  <script>
  // Get the input element
  var itemUniqueNoInput = document.getElementById('itemUniqueNo');

  // Add an event listener for the input event
  itemUniqueNoInput.addEventListener('input', function(event) {
    // Get the input value
    var inputValue = event.target.value;

    // Remove any non-numeric characters
    var numericValue = inputValue.replace(/\D/g, '');

    // Check if the numeric value is more than 13 digits
    if (numericValue.length > 13) {
      // Trigger an error, you can display an error message or perform other actions
     // alert("Input should not exceed 13 digits!");
      Toastify({
  text: 'Input should not exceed 13 digits!',
  duration: 5000,
  gravity: 'top',
  close: true,
  style: {
    background: 'linear-gradient(to right, #FFA0A0, #B88AFF, #A0A0FF)',
  }
}).showToast();
      // Trim the value to 13 digits
      numericValue = numericValue.slice(0, 13);
    }

    // Update the input value
    event.target.value = numericValue;  
  });
</script> 
<script>
    // We use json_encode to prevent syntax errors (handling quotes/nulls automatically)
    var ACTIVE_BRANCH_CONTEXT = <?php echo json_encode($page_context_branch); ?>;
    var USER_SESSION_BRANCH   = <?php echo json_encode($_SESSION['branch_code']); ?>;

    // Debugging (Optional: View in console to verify)
    console.log("Context Loaded:", { active: ACTIVE_BRANCH_CONTEXT, session: USER_SESSION_BRANCH });
</script>
  <script src="js/view_item.js"></script>   
</body>
</html>   
