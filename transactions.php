<?php
session_start(); // Make sure to start the session at the beginning of your script
// Add this near the top of your PHP block
$filter_branch_name = isset($_GET['branch_name']) ? urldecode($_GET['branch_name']) : "Global Scope";
if (isset($_SESSION['role'])) {
    $user_role = $_SESSION['role'];
    if ($user_role === "Super Admin") {
        $userID = $_SESSION['user_id'];
        $userName = $_SESSION['username']; 
        $userRole = $_SESSION['role'];
    } else{
      header("Location:404.php");
    }
} else  if (!isset($_SESSION['role'])){
    /* header("Location: https://igs.ng/pos/logout.php"); */
    header("Location:404.php"); 
    exit; // Stop the script to prevent further execution 
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
?>  
<?php
require 'auto_logout_script.php';
require 'defined_global_settings.php';
?>
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

<script src="jquery/jquery-3.6.0.min.js"></script> 

<link rel="stylesheet" href="node_modules/tippy.js/dist/tippy.css">

<script src="node_modules/@popperjs/core/dist/umd/popper.js"></script>

<script src="node_modules/tippy.js/dist/tippy.umd.min.js"></script>


<script src="jquery/jquery-ui.min.js"></script>
<link rel="stylesheet" href="jquery/jquery-ui.css"> 

<script src="node_modules/xlsx/dist/xlsx.full.min.js"></script> 
 
<script src="FileSaver.js/dist/FileSaver.js"></script>  

<script>
    // Pass PHP global settings to JavaScript variables, ensuring fallback if CURRENCY is empty
    const CURRENCY = "<?php echo !empty(CURRENCY) ? CURRENCY : (CUSTOM_CURRENCY ?? ''); ?>";
</script>


<script src="js/transactions_users.js"></script>   
<script src="js/alpine.min.js" defer></script>  
 
<script>
document.addEventListener("DOMContentLoaded", function () {
    document.body.style.zoom = "70%"; // Adjust for more/less zoom
});
</script>

<?php if (defined('DARK_MODE') && DARK_MODE == 1): ?>
    <link rel="stylesheet" type="text/css" href="css/dark-theme.css">
<?php else: ?>
    <link rel="stylesheet" type="text/css" href="css/light-theme.css">
<?php endif; ?>

<style> 
  .table-container { 
    max-height: 600px; 
    overflow-y: auto; 
}

.table-fixed {
    width: 100%; 
}

/* This is the class for the <thead> */
.sticky-header {
    position: sticky;
    top: 0;
    z-index: 1; 
    /* The background color must match the card body to look seamless */
    background-color: var(--card-bg, #ffffff); 
}

/* Ensure the dark theme sticky header also matches */
.dark-theme .sticky-header {
    background-color: var(--card-bg, #212529); 
}

/* draggable styling */
.draggable-widget {
    cursor: move !important;
    position: relative; /* Ensure proper dragging */
}

.fullscreen {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    background: #212529 !important; /* Dark background */
    z-index: 9999 !important;
    padding: 20px !important;
    overflow: hidden !important;
    box-shadow: none !important;
    border-radius: 0 !important;
    transition: none !important;
}

/* Prevent shaking when fullscreen is active */
.fullscreen .card:hover,
.fullscreen .sidebar:hover {
    transform: none !important;
}

/* Prevent scrolling when fullscreen */
body.fullscreen-active {
    overflow: hidden !important;
}

/* NEW UI STYLES */
/* Style for the new summary block */
.summary-dashboard {
    background-color: var(--card-bg-darker, #f8f9fa); /* A slightly different bg */
    border-radius: 8px;
    padding: 1.5rem;
}
.dark-theme .summary-dashboard {
    background-color: var(--card-bg-darker, #343a40);
}
.summary-dashboard .col-md-3 {
    border-right: 1px solid var(--border-color, #dee2e6);
}
.summary-dashboard .col-md-3:last-child {
    border-right: none;
}
/* Responsive handling for summary block */
@media (max-width: 767px) {
    .summary-dashboard .col-md-3 {
        border-right: none;
        border-bottom: 1px solid var(--border-color, #dee2e6);
        padding-bottom: 1rem;
        margin-bottom: 1rem;
    }
    .summary-dashboard .col-md-3:last-child {
        border-bottom: none;
        padding-bottom: 0;
        margin-bottom: 0;
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
</head>
<body class="dark-theme light-theme">
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
   
  <div class="container-scroller dark-theme light-theme navbar-dark ">
    <nav class="navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row dark-theme light-theme">
    <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-center dark-theme light-theme navbar-dark">
    <a class="navbar-brand brand-logo mr-5 dark-theme light-theme navbar-dark" href="transactions.php">
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
            
           
                <a href="hub_dashboard.php" class="btn-back-hub ml-3">
                    <i class="ti-arrow-left"></i> Back to Hub
                </a>
           
        </div>
    </li>
    
    <script>
        var ACTIVE_BRANCH_CONTEXT = "<?php echo $active_branch_code; ?>";
    </script>
</ul>
        
        <ul class="navbar-nav navbar-nav-right dark-theme light-theme navbar-dark ">
             
          <li class="nav-item nav-profile dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown" id="profileDropdown">
              <i class="icon-head menu-icon"></i>
                <span class="menu-title"><?php echo $_SESSION['username'];?></span>
            </a>
            <div class="dropdown-menu dropdown-menu-right navbar-dropdown dark-theme light-theme navbar-dark " aria-labelledby="profileDropdown">
         
              <a class="dropdown-item dark-theme light-theme navbar-dark " href="logout.php">
                <i class="ti-power-off text-primary"></i>
                Logout
              </a> 
            </div>
          </li>
          <li class="nav-item dark-theme light-theme navbar-dark "> 
              <a href="logout.php">
                <i class="ti-power-off text-primary"></i>
                Logout
              </a>
            </li>
        </ul>
      </div>
    </nav>
    <div class="container-fluid page-body-wrapper dark-theme light-theme navbar-dark ">
      <nav class="sidebar sidebar-offcanvas dark-theme light-theme navbar-dark " id="sidebar">
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
      <div class="main-panel dark-theme light-theme navbar-dark ">
        <div class="content-wrapper dark-theme light-theme navbar-dark">
          <div class="row">

            <div class="col-md-12 grid-margin dark-theme light-theme navbar-dark">
              <div class="card dark-theme light-theme navbar-dark">
                <div class="card-body dark-theme light-theme navbar-dark">
                  <p class="card-title dark-theme light-theme navbar-dark">Transaction Controls</p>
                  
                  <div class="row dark-theme light-theme navbar-dark">
                    <div class="col-md-3 dark-theme light-theme navbar-dark">
                      <div class="form-group dark-theme light-theme navbar-dark">
                        <label for="transactionType">Transaction Type</label>
                        <select class="form-control dark-theme light-theme navbar-dark" id="transactionType">
                          <option value="">All</option>
                          <option value="sale">Sale</option>
                          <option value="purchase">Purchase</option>
                          <option value="adjustment">Adjustment</option>
                        </select>
                      </div>
                    </div>
               <div class="col-md-3 dark-theme light-theme navbar-dark">
                <div class="form-group dark-theme light-theme navbar-dark">
                    <label for="transactionUser">User Search</label>
                    <input type="text" class="form-control dark-theme light-theme navbar-dark" 
                        id="transactionUser" placeholder="Type username..." autocomplete="off">
                </div>
                </div>
                    <div class="col-md-3 dark-theme light-theme navbar-dark">
                      <div class="form-group dark-theme light-theme navbar-dark">
                        <label for="startDate">Start Date</label>
                        <input type="date" class="form-control dark-theme light-theme navbar-dark" id="startDate">
                      </div>
                    </div>
                    <div class="col-md-3 dark-theme light-theme navbar-dark">
                      <div class="form-group dark-theme light-theme navbar-dark">
                        <label for="endDate">End Date</label>
                        <input type="date" class="form-control dark-theme light-theme navbar-dark" id="endDate">
                      </div>
                    </div>
                  </div>

                  <div class="row d-flex justify-content-between align-items-center mt-3">
                    <div class="col-md-5 dark-theme light-theme navbar-dark">
                      <div class="search-container mb-3 dark-theme light-theme navbar-dark">
                        <label for="searchTransactionGroup" class="form-label dark-theme light-theme navbar-dark">Search by Transaction Group ID:</label>
                        <input type="text" id="searchTransactionGroup" class="form-control dark-theme light-theme navbar-dark" placeholder="Enter Transaction Group ID">
                      </div>
                    </div>
                    <div class="col-md-7 dark-theme light-theme navbar-dark d-flex justify-content-md-end">
                      <div class="btn-group" role="group">
                        <button id="exportExcelBtn" class="btn btn-success">Export to Excel</button>
                        <button id="removeSelectedTransactions" class="btn btn-danger">Remove Selected Transactions</button>
                        <button id="viewRemovedTransactions" class="btn btn-info">View Removed Transactions</button>
                      </div>
                    </div>
                  </div>

                </div>
              </div>
            </div>

            <div class="col-md-12 grid-margin stretch-card dark-theme light-theme navbar-dark">
              <div class="card dark-theme light-theme navbar-dark">
                <div class="card-body dark-theme light-theme navbar-dark">
                  <p class="card-title dark-theme light-theme navbar-dark">Transaction Log</p>
                  
                  <div class="summary-dashboard card-body shadow-sm mb-4 dark-theme light-theme navbar-dark">
                    <div class="row text-center">
                      <div class="col-md-3 col-6">
                        <label class="text-white">Total Sales</label>
                        <h5 class="mb-0">
                          <strong><span id="totalSales_" class="dark-theme light-theme navbar-dark text-white"></span></strong> 
                        </h5>
                      </div>
                      <div class="col-md-3 col-6">
                        <label class="text-white">Aggregated (Filtered) Total</label>
                        <h5 class="mb-0">
                          <strong><span id="aggregatedTotal" class="dark-theme light-theme navbar-dark text-white">0.00</span></strong>
                        </h5>
                      </div>
                      <div class="col-md-3 col-6">
                        <label class=" text-white">Total Profit</label>
                        <div class="mb-0">
                          <strong><p id="totalProfits" class="dark-theme light-theme navbar-dark mb-0 text-white">Total Profit: ₦0.00</p></strong>
                        </div>
                      </div>
                      <div class="col-md-3 col-6">
                        <label class=" text-white">Total Loss</label>
                        <div class="mb-0">
                          <strong><p id="totalLosses" class="dark-theme light-theme navbar-dark mb-0 text-white">Total Loss: ₦0.00</p></strong>
                        </div>
                      </div>
                    </div>
                  </div>
                  
                  <div class="table-container dark-theme light-theme navbar-dark">
                    <table class="table table-bordered table-hover table-fixed dark-theme light-theme navbar-dark">
                      <thead class="sticky-header dark-theme light-theme navbar-dark">
                        <tr>
                          <th><input type="checkbox" id="selectAll"></th>
                          <th>Transaction Group ID</th>
                          <th>User</th>
                          <th>Item</th>
                          <th>Date</th>
                          <th>Type</th>
                          <th>Quantity</th>
                          <th>Total Amount</th>
                          <th>Sold At</th>
                          <th>Cost Price</th>
                          <th>Profit/Loss</th>
                          <th>Purchase Price Updated_At</th>
                          <th>WHL/RTL Updated_At</th>
                        </tr>
                      </thead>
                      <tbody id="transactionTableBody"></tbody>
                    </table>
                  </div>
                  
                  <div class="d-flex justify-content-center mt-3">
                    <div id="paginationContainer" class="dark-theme light-theme navbar-dark pagination">
                      </div>
                  </div>

                </div>
              </div>
            </div>
            
            <div class="col-12 grid-margin dark-theme light-theme navbar-dark">
              <div class="card dark-theme light-theme navbar-dark">
                <div class="card-body dark-theme light-theme navbar-dark">
                  <h2 class="card-title text-center mb-4 dark-theme light-theme navbar-dark">Interactive Insights Dashboard</h2>
                  
                  <div id="dashboardCanvas" class="row dark-theme light-theme">
                    <div class="col-md-6 mb-4 draggable-widget chart-container" id="profitProjections">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title">Profit Projections</h5>
                                <div>
                                    <button class="btn btn-sm btn-outline-secondary refresh-chart" data-chart="profitProjectionsChart">↻</button>
                                    <button class="btn btn-sm btn-outline-secondary toggle-widget">▼</button>
                                    <button class="btn btn-sm btn-outline-primary maximize-chart" data-chart="profitProjectionsChart">🔍 Maximize</button>
                                    <button class="btn btn-sm btn-outline-danger minimize-chart" style="display:none;">❌ Minimize</button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="profitProjectionsChart" class="chart-box"></div>
                            </div>
                        </div>
                    </div>
                
                    <div class="col-md-6 mb-4 draggable-widget chart-container" id="lossProjections">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title">Loss Projections</h5>
                                <div>
                                    <button class="btn btn-sm btn-outline-secondary refresh-chart" data-chart="lossProjectionsChart">↻</button>
                                    <button class="btn btn-sm btn-outline-secondary toggle-widget">▼</button>
                                    <button class="btn btn-sm btn-outline-primary maximize-chart" data-chart="profitProjectionsChart">🔍 Maximize</button>
                                    <button class="btn btn-sm btn-outline-danger minimize-chart" style="display:none;">❌ Minimize</button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="lossProjectionsChart" class="chart-box"></div>
                            </div>
                        </div>
                    </div>
                
                    <div class="col-md-6 mb-4 draggable-widget chart-container" id="ongoingTransactions">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title">Ongoing Transactions</h5>
                                <div>
                                    <button class="btn btn-sm btn-outline-secondary refresh-chart" data-chart="ongoingTransactionsChart">↻</button>
                                    <button class="btn btn-sm btn-outline-secondary toggle-widget">▼</button>
                                    <button class="btn btn-sm btn-outline-primary maximize-chart" data-chart="profitProjectionsChart">🔍 Maximize</button>
                                    <button class="btn btn-sm btn-outline-danger minimize-chart" style="display:none;">❌ Minimize</button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="ongoingTransactionsChart" class="chart-box"></div>
                            </div>
                        </div>
                    </div>
                
                    <div class="col-md-6 mb-4 draggable-widget chart-container" id="userPerformance">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title">User Performance</h5>
                                <div>
                                    <button class="btn btn-sm btn-outline-secondary refresh-chart" data-chart="userPerformanceChart">↻</button>
                                    <button class="btn btn-sm btn-outline-secondary toggle-widget">▼</button>
                                    <button class="btn btn-sm btn-outline-primary maximize-chart" data-chart="profitProjectionsChart">🔍 Maximize</button>
                                    <button class="btn btn-sm btn-outline-danger minimize-chart" style="display:none;">❌ Minimize</button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="userPerformanceChart" class="chart-box"></div>
                            </div>
                        </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            </div>
        </div>
        <footer class="footer dark-theme light-theme navbar-dark ">
          <div class="d-sm-flex justify-content-center justify-content-sm-between dark-theme light-theme navbar-dark ">
            <span class="text-muted text-center text-sm-left d-block d-sm-inline-block dark-theme light-theme navbar-dark ">Copyright © <?php echo date('Y'); ?>.  Inventory Keeper App. All rights reserved.</span>
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

  $(document).ready(function () {
    // Enable drag-and-drop functionality
    $("#dashboardCanvas").sortable({
        items: ".draggable-widget",
        handle: ".card-title",
        placeholder: "sortable-placeholder",
        tolerance: "pointer",
        update: saveLayout // Save layout on change
    }).disableSelection();

    // Enable resizable charts
    $(".draggable-widget").resizable({
        handles: "e, w",
        stop: function () {
            adjustChartSize($(this).find(".chart-box").attr("id"));
        }
    });

    // Toggle widget collapse
    $(".toggle-widget").click(function () {
        let cardBody = $(this).closest(".card").find(".card-body");
        cardBody.slideToggle();
        $(this).text(cardBody.is(":visible") ? "▼" : "▲");
    });

    // Refresh individual charts
    $(".refresh-chart").click(function () {
        let chartId = $(this).data("chart");
        refreshChartData(chartId);
    });

    // Maximize & Minimize charts
    $(".maximize-chart").click(function () {
        let chartId = $(this).data("chart");
        openFullScreen(chartId);
    });

    $(".minimize-chart").click(function () {
        closeFullScreen();
    });

    // Load dashboard layout
    loadSavedLayout();

     // Fetch dashboard data initially & auto-refresh every 30 seconds
    fetchDashboardData();
    /*setInterval(fetchDashboardData, 30000); */
});

// Function to fetch dashboard data with Branch Context
function fetchDashboardData() { 
    showLoadingIndicators(); 

    // 1. Resolve Branch Context
    var currentBranch = '';
    if (typeof ACTIVE_BRANCH_CONTEXT !== 'undefined') {
        currentBranch = ACTIVE_BRANCH_CONTEXT;
    } else {
        const urlParams = new URLSearchParams(window.location.search);
        currentBranch = urlParams.get('branch_uuid') || '';
    }

    console.log("Fetching Dashboard Insights for: " + (currentBranch || "Global Scope"));

    $.ajax({
        url: "fetch_dashboard_data.php",
        method: "GET",
        dataType: "json",
        data: {
            branch_code: currentBranch // Pass the context 
        },
        success: function (response) {
            if (!response) {
                alert("No data received!"); 
                return;
            }

            updateSummaryUI(response);
            renderProfitProjections(response.profitProjections);
            renderLossProjections(response.lossProjections);
            renderOngoingTransactions(response.transactionSummary);
            renderUserPerformance(response.userSales);
        },
        error: function (xhr, status, error) {
            console.error("AJAX Error:", error);
            // Optional: Only alert if it's a critical failure, otherwise silent fail for dashboard widgets
        },
        complete: function () {
            hideLoadingIndicators();
        }
    });
}

// Function to update summary UI
function updateSummaryUI(data) {
    $("#totalProfit").text(`₦${formatNumber(data.totalProfit)}`);
    $("#totalLoss").text(`₦${formatNumber(data.totalLoss)}`);
    $("#totalSales").text(`₦${formatNumber(data.transactionSummary.sale)}`);
    $("#totalPurchases").text(`₦${formatNumber(data.transactionSummary.purchase)}`);
    $("#totalAdjustments").text(`₦${formatNumber(data.transactionSummary.adjustment)}`);
}

// Function to render profit projections
function renderProfitProjections(projections) {
    Highcharts.chart("profitProjectionsChart", {
        chart: { type: "line" },
        title: { text: "Projected Monthly Profits" },
        xAxis: { categories: projections.months },
        yAxis: { title: { text: "Profit (₦)" } },
        series: [{
            name: "Projected Profit",
            data: projections.values.map(Number),
            color: "#28a745"
        }]
    });
}

// Function to render loss projections
function renderLossProjections(projections) {
    Highcharts.chart("lossProjectionsChart", {
        chart: { type: "line" },
        title: { text: "Projected Monthly Losses" },
        xAxis: { categories: projections.months },
        yAxis: { title: { text: "Loss (₦)" } },
        series: [{
            name: "Projected Loss",
            data: projections.values.map(Number),
            color: "#dc3545"
        }]
    });
}

// Function to render ongoing transactions
function renderOngoingTransactions(transactionData) {
    if (!transactionData || typeof transactionData.sale === 'undefined' || typeof transactionData.purchase === 'undefined' || typeof transactionData.adjustment === 'undefined') {
        console.error('Invalid transaction data:', transactionData);
        return;
    }

    Highcharts.chart('ongoingTransactionsChart', {
        chart: { type: 'pie' },
        title: { text: 'Ongoing Transactions Breakdown' },
        series: [{
            name: 'Transactions',
            colorByPoint: true,
            data: [
                { name: 'Sales', y: parseFloat(transactionData.sale), color: '#007bff' },
                { name: 'Purchases', y: parseFloat(transactionData.purchase), color: '#ffc107' },
                { name: 'Adjustments', y: parseFloat(transactionData.adjustment), color: '#17a2b8' }
            ]
        }]
    });
}


// Function to render user performance
function renderUserPerformance(userSales) {
    let users = Object.keys(userSales);
    let sales = users.map(user => parseFloat(userSales[user]));

    Highcharts.chart("userPerformanceChart", {
        chart: { type: "bar" },
        title: { text: "Top Users by Sales" },
        xAxis: { categories: users },
        yAxis: { title: { text: "Total Sales (₦)" } },
        series: [{
            name: "Sales",
            data: sales,
            color: "#17a2b8"
        }]
    });
}



// Function to format numbers with commas
function formatNumber(value) {
    return parseFloat(value).toLocaleString();
}

// Function to save user layout
function saveLayout() {
    let layoutOrder = $("#dashboardCanvas").sortable("toArray");
    localStorage.setItem("dashboardLayout", JSON.stringify(layoutOrder));
}

// Function to load saved layout
function loadSavedLayout() {
    let savedLayout = localStorage.getItem("dashboardLayout");
    if (savedLayout) {
        savedLayout = JSON.parse(savedLayout);
        savedLayout.forEach(widgetId => {
            $("#" + widgetId).appendTo("#dashboardCanvas"); 
        });
    }
}

// Function to adjust chart size dynamically
function adjustChartSize(chartId) {
    Highcharts.charts.forEach(chart => {
        if (chart && chart.renderTo.id === chartId) {
            chart.reflow();
        }
    });
}

// Show loading indicators while fetching data
function showLoadingIndicators() {
    $(".chart-box").each(function () {
        $(this).html('<div class="loading-spinner">Loading...</div>');
    });
}

// Hide loading indicators after fetching data
function hideLoadingIndicators() {
    $(".loading-spinner").remove();
}
  </script>

<script>
    document.getElementById("viewRemovedTransactions").addEventListener("click", function () {
        // 1. Resolve Context from URL (Priority for Super Admin navigation)
        const urlParams = new URLSearchParams(window.location.search);
        let branchUUID = urlParams.get('branch_uuid');
        let branchName = urlParams.get('branch_name');

        // 2. Fallback to PHP-injected Context (For Local User navigation)
        if (!branchUUID && typeof ACTIVE_BRANCH_CONTEXT !== 'undefined' && ACTIVE_BRANCH_CONTEXT !== 'HEAD_OFFICE') {
            branchUUID = ACTIVE_BRANCH_CONTEXT;
        }

        // 3. Construct the robust URL
        let url = "removed_transactions.php";
        let params = [];

        if (branchUUID) {
            // Pass BOTH identifiers to be safe
            params.push("branch_code=" + encodeURIComponent(branchUUID));
            params.push("branch_uuid=" + encodeURIComponent(branchUUID));
        }
        
        if (branchName) {
            params.push("branch_name=" + encodeURIComponent(branchName));
        }

        if (params.length > 0) {
            url += "?" + params.join("&");
        }
        
        window.location.href = url;
    });
</script>


  </body>
</html>