<?php
session_start();  

// --- 1. AUTHENTICATION & SESSION CHECK ---
if (isset($_SESSION['role'])) {
    $user_role = $_SESSION['role'];
    if ($user_role === "Super Admin") {
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

// --- 2. DYNAMIC BRANCH CONTEXT LOGIC ---
// Determine which branch data to show:
// Priority A: The branch requested in the URL (Viewing another branch)
// Priority B: The user's own branch from Session (Viewing own dashboard)
$active_branch_code = $_SESSION['branch_code'] ?? ''; // Default to session
$active_branch_name = $_SESSION['branch_name'] ?? 'My Branch';

// Check for URL overrides
if (isset($_GET['branch_uuid']) && !empty($_GET['branch_uuid'])) {
    $active_branch_code = $_GET['branch_uuid'];
    $active_branch_name = isset($_GET['branch_name']) ? urldecode($_GET['branch_name']) : $active_branch_code;
}

// --- 2. BRANCH CONTEXT LOGIC (New) ---
// Capture branch info to keep navigation consistent across pages
$filter_branch_uuid = isset($_GET['branch_uuid']) ? $_GET['branch_uuid'] : null;
$filter_branch_name = isset($_GET['branch_name']) ? urldecode($_GET['branch_name']) : null;

// Helper function to append branch info to menu links
// Helper function to append branch info to menu links
function linkTo($url) {
    global $active_branch_code, $active_branch_name;
    
    // Only append params if we are specifically "visiting" a context
    // otherwise, let pages default to session
    if (isset($_GET['branch_uuid'])) { 
        $sep = (strpos($url, '?') !== false) ? '&' : '?';
        return $url . $sep . "branch_uuid=" . urlencode($active_branch_code) . "&branch_name=" . urlencode($active_branch_name);
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

// Set Dashboard Titles based on Context
$dashboard_title = "Overview: " . htmlspecialchars($active_branch_name);

// --- 3. INCLUDES ---
// require_once 'validate_user_license.php'; // Uncomment if needed
include 'auto_logout_script.php';
include 'defined_global_settings.php';

// --- 4. JS VARIABLES FOR CHARTS/LOGIC ---
$currencyVal = !empty(CURRENCY) ? CURRENCY : (defined('CUSTOM_CURRENCY') ? CUSTOM_CURRENCY : '');
$firstWord   = strtok((!empty(BUSINESS_NAME) ? BUSINESS_NAME : ''), " ");
$logoPath    = (!empty(BUSINESS_LOGO)) ? BUSINESS_LOGO : 'images/logo-mini.png';
$faviconPath = (defined('BUSINESS_LOGO') && !empty(BUSINESS_LOGO)) ? BUSINESS_LOGO : 'images/favicon.png';
$themeCss    = (defined('DARK_MODE') && DARK_MODE == 1) ? 'css/dark-theme.css' : 'css/light-theme.css';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>IKA Admin</title>

    <link rel="shortcut icon" href="<?php echo $faviconPath; ?>" />
     <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css">

    <link rel="stylesheet" href="vendors/feather/feather.css">
    <link rel="stylesheet" href="vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="vendors/datatables.net-bs4/dataTables.bootstrap4.css">
    <link rel="stylesheet" type="text/css" href="js/select.dataTables.min.css">
    
    <link rel="stylesheet" href="css/vertical-layout-light/style.css">
    <link rel="stylesheet" href="bootstrap_v4/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo $themeCss; ?>">
    <link rel="stylesheet" href="css/expiring_item_style.css">

    <link rel="stylesheet" href="node_modules/toastify-js/src/toastify.css" />
    <link rel="stylesheet" href="sweetalert2/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="node_modules/tippy.js/dist/tippy.css">

    <script src="jquery/jquery-3.6.0.min.js"></script>
    <script src="highchartsLib/code/highcharts.js"></script>
    <script src="highchartsLib/code/modules/accessibility.js"></script>
    <script src="sweetalert2/dist/sweetalert2.all.min.js"></script>
    <script src="node_modules/@popperjs/core/dist/umd/popper.js"></script>
    <script src="node_modules/tippy.js/dist/tippy.umd.min.js"></script>
    <script src="FileSaver.js/dist/FileSaver.js"></script>
    <script src="js/alpine.min.js" defer></script>

    <script>

        // --- DYNAMIC CONTEXT BRIDGE ---
    // This allows external JS files (like transactions_users.js) to know 
    // exactly which branch we are looking at without hardcoding.
    const ACTIVE_BRANCH_CONTEXT = "<?php echo htmlspecialchars($active_branch_code); ?>";
    const ACTIVE_BRANCH_NAME    = "<?php echo htmlspecialchars($active_branch_name); ?>";
    
        const CURRENCY = "<?php echo $currencyVal; ?>";
        document.addEventListener("DOMContentLoaded", function() {
            document.body.style.zoom = "75%"; // Adjusted slightly for better visibility 
        });
    </script>

    

    <style>
        /* Modern Card Styling */
        .card {
            border: none !important;
            border-radius: 12px !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05) !important;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1) !important;
        }
        .fs-30 {
            font-size: 2.2rem !important;
            font-weight: 700;
        }

        /* Invoice Table */
        #purchaseInvoiceTable {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 1rem;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        #purchaseInvoiceTable thead th {
            background-color: #f1f5f9;
            color: #475569;
            padding: 1rem;
            font-weight: 600;
            border-bottom: 2px solid #e2e8f0;
        }
        #purchaseInvoiceTable tbody td {
            padding: 1rem;
            color: #334155;
            border-bottom: 1px solid #f1f5f9;
        }
        #purchaseInvoiceTable tbody tr:hover {
            background-color: #f8fafc;
        }

        /* Filter Controls */
        .filter-container .form-control {
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            padding: 0.6rem 1rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .filter-container .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        /* Branch Context Pill */
        .branch-context-pill {
            display: flex;
            align-items: center;
            background: linear-gradient(135deg, #e0f7fa 0%, #ffffff 100%);
            border: 1px solid #b2ebf2;
            padding: 8px 20px;
            border-radius: 50px;
            box-shadow: 0 4px 15px rgba(0, 188, 212, 0.15);
            transition: all 0.3s ease;
        }
        .branch-context-pill:hover {
            box-shadow: 0 6px 20px rgba(0, 188, 212, 0.25);
            transform: translateY(-1px);
        }
        .branch-name {
            font-family: 'Nunito', sans-serif;
            font-weight: 800;
            font-size: 1.1rem;
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
        
        /* Navbar Online Dot */
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

        /* Modal Enhancements */
        .modal-content {
            border-radius: 12px;
            overflow: hidden;
            border: none;
        }
        .modal-header {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
    </style>
</head>

<body class="dark-theme light-theme" x-data="expiringItemsAlert()" >

    <script src="node_modules/toastify-js/src/toastify.js"></script>

    <center>
        <div id="backUpStatus" style="position: relative;"></div>
    </center>

    <div x-data="backupSystem()" x-init="init()">
        <button @click="confirmBackup()" style="display:none;" id="hiddenBackupBtn"></button> <div style="text-align:center; margin-top:5px;">
            <button @click="confirmBackup()" class="btn btn-sm btn-info text-white">
                <i class="ti-cloud-down"></i> Start System Backup
            </button>
        </div>

        <div x-show="isBackingUp" class="progress-container mt-2">
            <div class="progress-bar" :style="'width:' + progress + '%'"></div>
            <p x-text="'Progress: ' + progress + '%'"></p>
        </div>
        <div id="tippy-container" style="position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 1000;"></div>
    </div>
    <script src="js/backup_logic.js"></script>

    <div class="container-scroller dark-theme light-theme navbar-dark">
        
        <nav class="navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row dark-theme light-theme">
            <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-center dark-theme light-theme navbar-dark">
                <a class="navbar-brand brand-logo mr-5 dark-theme light-theme navbar-dark" href="superAdmin.php">
                    <?php echo htmlspecialchars($firstWord); ?>&nbsp;
                    <img src="<?php echo $logoPath; ?>" class="mr-2 dark-theme light-theme navbar-dark" alt="Logo" />
                </a>
            </div>

            <div class="navbar-menu-wrapper d-flex align-items-center justify-content-end">
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

<!--     <li class="nav-item nav-search d-none d-lg-block">
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
    </li> -->
    

</ul>

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
    

</ul>

                <ul class="navbar-nav navbar-nav-right">
                    <li class="nav-item dropdown">
                        <a class="nav-link count-indicator dropdown-toggle" id="notificationDropdown" href="#" data-toggle="dropdown">
                            <i class="icon-bell mx-0"></i>
                            <span class="count"></span>
                        </a>
                    </li>
                    <li class="nav-item nav-profile dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown" id="profileDropdown">
                           <img src="../images/male_picture.png" alt="profile" /> 
                        </a>
                        <div class="dropdown-menu dropdown-menu-right navbar-dropdown" aria-labelledby="profileDropdown">
                            <a class="dropdown-item"><i class="ti-settings text-primary"></i> Settings</a>
                            <a class="dropdown-item" href="logout.php"><i class="ti-power-off text-primary"></i> Logout</a>
                        </div>
                    </li>
                </ul>
                <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="offcanvas">
                    <span class="icon-menu"></span>
                </button>
            </div>
        </nav>

        <div class="container-fluid page-body-wrapper dark-theme light-theme navbar-dark">
            
            <nav class="sidebar sidebar-offcanvas dark-theme light-theme navbar-dark" id="sidebar">
                <ul class="nav dark-theme light-theme navbar-dark">
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
                            <i class="icon-head menu-icon"></i><span class="menu-title">Add Items & More</span>
                        </a>
                    </li>

                    <li class="nav-item dark-theme light-theme navbar-dark" id="manageItemsNavItem">
                        <a class="nav-link" href="<?php echo linkTo('manage_item.php'); ?>">
                            <i class="ti-package menu-icon"></i><span class="menu-title">Manage Items</span>
                        </a>
                        <div class="expiring-alert-container" x-show="showAlert || showExpired">
                            <template x-if="showAlert && hasExpiring()">
                                <div class="expiring-alert" @click="redirectToManageItems('expiring')">
                                    <button class="close-button" @click.stop="showAlert = false">&times;</button>
                                    <template x-if="expiring7Days > 0"><p><strong><span x-text="expiring7Days"></span></strong> item(s) expire in 7 days</p></template>
                                    <template x-if="expiring14Days > 0"><p><strong><span x-text="expiring14Days"></span></strong> item(s) expire in 2 weeks</p></template>
                                    <template x-if="expiring21Days > 0"><p><strong><span x-text="expiring21Days"></span></strong> item(s) expire in 3 weeks</p></template>
                                </div>
                            </template>
                            <template x-if="showExpired">
                                <div class="expired-alert" @click="redirectToManageItems('expired')">
                                    <button class="close-button" @click.stop="showExpired = false">&times;</button>
                                    <p><strong><span x-text="expiredItems"></span></strong> item(s) have expired.</p>
                                </div>
                            </template>
                        </div>
                    </li>
                    <li class="nav-item dark-theme light-theme navbar-dark">
                        <a class="nav-link" href="<?php echo linkTo('pos.php'); ?>">
                            <i class="ti-shopping-cart menu-icon"></i><span class="menu-title">Make Sales</span>
                        </a>
                    </li>
                </ul>
                <script src="js/expiring_items_alpine.js"></script>
            </nav>

            <div class="main-panel dark-theme light-theme navbar-dark">
                <div class="content-wrapper dark-theme light-theme navbar-dark">
                    
                    <div class="row dark-theme light-theme navbar-dark mb-3">
                        <div class="col-12 col-xl-8 mb-4 mb-xl-0 dark-theme light-theme navbar-dark">
                            <h3 class="font-weight-bold">Welcome <?php echo htmlspecialchars($_SESSION['username']); ?></h3>
                            <div class="badge badge-outline-primary mt-2">Privilege Level: <?php echo htmlspecialchars($_SESSION['role']); ?></div>
                            <h6 class="font-weight-normal mb-0 mt-2 text-muted">All systems are running smoothly!</h6>
                        </div>
                    </div>

                    <div class="row dark-theme light-theme navbar-dark">
                        <div class="col-md-12 grid-margin transparent dark-theme light-theme navbar-dark">
                            
                            <div class="row dark-theme light-theme navbar-dark">
                                <div class="col-md-3 mb-4 stretch-card transparent dark-theme light-theme navbar-dark">
                                    <div class="card card-light-danger dark-theme light-theme navbar-dark">
                                        <div class="card-body dark-theme light-theme navbar-dark">
                                            <p class="mb-4">Total Sales</p>
                                            <p class="fs-30 mb-2 AllSales"></p>
                                            <p>All Time Sales</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-4 stretch-card transparent dark-theme light-theme navbar-dark">
                                    <div class="card card-tale dark-theme light-theme navbar-dark">
                                        <div class="card-body dark-theme light-theme navbar-dark">
                                            <p class="mb-4">This Month</p>
                                            <p class="fs-30 mb-2 monthlySales"></p>
                                            <p>Total Sales</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-4 stretch-card transparent dark-theme light-theme navbar-dark">
                                    <div class="card card-dark-blue dark-theme light-theme navbar-dark">
                                        <div class="card-body dark-theme light-theme navbar-dark">
                                            <p class="mb-4">Today</p>
                                            <p class="fs-30 mb-2 todaySales dark-theme light-theme navbar-dark"></p>
                                            <p>Total Sales</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-4 stretch-card transparent dark-theme light-theme navbar-dark">
                                    <div class="card card-light-blue dark-theme light-theme navbar-dark">
                                        <div class="card-body dark-theme light-theme navbar-dark">
                                            <p class="mb-4">Employees</p>
                                            <p class="fs-30 mb-2 totalUsers"></p>
                                            <p>Total Users</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row dark-theme light-theme navbar-dark">
                                <div class="col-md-3 mb-4 stretch-card transparent dark-theme light-theme navbar-dark">
                                    <div class="card card-light-green dark-theme light-theme navbar-dark">
                                        <div class="card-body dark-theme light-theme navbar-dark d-flex flex-column justify-content-between">
                                            <div>
                                                <p class="mb-4">Purchase Invoice</p>
                                                <i class="ti-clipboard menu-icon" style="font-size: 2rem; opacity: 0.5;"></i>
                                            </div>
                                            <button class="btn btn-primary btn-block mt-3" id="showPurchaseInvoiceModal">View Invoices</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-4 stretch-card transparent dark-theme light-theme navbar-dark">
                                    <div class="card card-dark-blue dark-theme light-theme navbar-dark">
                                        <div class="card-body dark-theme light-theme navbar-dark">
                                            <p class="mb-4">Stock Value</p>
                                            <p class="fs-30 mb-2 stockValue"></p>
                                            <p>Overall</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-4 stretch-card transparent dark-theme light-theme navbar-dark">
                                    <div class="card card-light-green dark-theme light-theme navbar-dark">
                                        <div class="card-body dark-theme light-theme navbar-dark">
                                            <p class="mb-4">Overall Gross</p>
                                            <p class="fs-30 mb-2 grossValue"></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-4 stretch-card transparent dark-theme light-theme navbar-dark">
                                    <div class="card card-light-green dark-theme light-theme navbar-dark">
                                        <div class="card-body dark-theme light-theme navbar-dark">
                                            <p class="mb-4">Overall Net</p>
                                            <p class="fs-30 mb-2 netValue"></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4 dark-theme light-theme navbar-dark">
                        <div class="card-body py-3">
                            <h5 class="card-title mb-3">Filter Analytics</h5>
                            <div class="row">
                                <div class="col-md-4 dark-theme light-theme navbar-dark mb-2">
                                    <div class="form-group dark-theme light-theme navbar-dark m-0">
                                        <label for="category" class="text-muted small">Transaction Type</label>
                                        <select class="form-control dark-theme light-theme navbar-dark" id="category">
                                            <option value="">All</option>
                                            <option value="sale">Sale</option>
                                            <option value="purchase">Purchase</option>
                                            <option value="adjustment">Adjustment</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4 dark-theme light-theme navbar-dark mb-2">
                                    <div class="form-group dark-theme light-theme navbar-dark m-0">
                                        <label for="start_Date" class="text-muted small">Start Date</label>
                                        <input type="date" id="start_Date" name="start_Date" class="form-control dark-theme light-theme navbar-dark">
                                    </div>
                                </div>
                                <div class="col-md-4 dark-theme light-theme navbar-dark mb-2">
                                    <div class="form-group dark-theme light-theme navbar-dark m-0">
                                        <label for="end_Date" class="text-muted small">End Date</label>
                                        <input type="date" id="end_Date" name="end_Date" class="form-control dark-theme light-theme navbar-dark">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row dark-theme light-theme navbar-dark">
                        <div class="col-md-12 grid-margin stretch-card dark-theme light-theme navbar-dark">
                            <div class="card dark-theme light-theme navbar-dark">
                                <div class="card-body dark-theme light-theme navbar-dark">
                                    <div class="d-flex justify-content-between dark-theme light-theme navbar-dark">
                                        <p class="card-title display-4" style="font-size: 1.5rem;">Sales Report</p>
                                        <a href="#" class="text-info font-weight-bold">View all</a>
                                    </div>
                                    
                                    <div id="sales-legend" class="chartjs-legend mt-4 mb-2 dark-theme light-theme navbar-dark"></div>
                                    
                                    <div id="transactionChartsContainer" class="dark-theme light-theme navbar-dark chart-container mt-3">
                                        <div id="salesChart" class="chart-box" style="width:100%; min-height:400px;"></div>
                                        <div id="inventoryChart" class="chart-box mt-4" style="width:100%; min-height:400px;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <footer class="footer dark-theme light-theme navbar-dark">
                    <div class="d-sm-flex justify-content-center justify-content-sm-between dark-theme light-theme navbar-dark">
                        <span class="text-muted text-center text-sm-left d-block d-sm-inline-block dark-theme light-theme navbar-dark">
                            Copyright © <?php echo date('Y'); ?>. Inventory Keeper App. All rights reserved.
                        </span>
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

    <?php include 'purchaseInvoice_Modal.php'; ?>
    
    <div class="modal fade dark-theme light-theme" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog modal-xl dark-theme light-theme navbar-dark" role="document">
            <div class="modal-content dark-theme light-theme navbar-dark">
                <div class="modal-header dark-theme light-theme navbar-dark">
                    <h4 class="modal-title dark-theme light-theme navbar-dark">
                        Transactions for <span id="modalDate" class="text-primary"></span>
                    </h4>
                    <button type="button" class="close dark-theme light-theme navbar-dark close-button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body responsive overflow-auto dark-theme light-theme navbar-dark" id="modalContent" style="max-height: 60vh;">
                    <div id="transactionDetails" class="dark-theme light-theme navbar-dark">
                        </div>
                </div>
                <div class="modal-footer dark-theme light-theme navbar-dark">
                    <div id="exportBut" class="dark-theme light-theme navbar-dark mr-auto"></div>
                    <div id="modalPagination" class="text-center dark-theme light-theme navbar-dark"></div>
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
    <script src="bootstrap_v4/js/bootstrap.min.js"></script>
    
    <script src="js/purchaseInvoice_Modal.js"></script>
    <script src="js/transactions_users.js"></script>
    <script src="js/transactionsCharts.js"></script>  
 
    <script>
        // Convert minutes to milliseconds
        var autoLogoutMinutes = <?php echo $inactivityMinutes; ?>;
        var autoLogoutMilliseconds = autoLogoutMinutes * 60 * 1000;
        var warningPeriod = 30 * 1000;
        var inactivityTimer;
        var logoutWarningTimer;

        function performLogout() {
            $.ajax({
                url: 'auto_logout.php',
                type: 'POST',
                dataType: 'json',
                success: function(response) { window.location.href = 'index.php'; },
                error: function() { window.location.href = 'index.php'; }
            });
        }

        function showLogoutWarning() {
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
                willClose: () => { clearInterval(timerInterval); }
            }).then((result) => {
                if (result.isConfirmed) { resetInactivityTimer(); } 
                else { performLogout(); }
            });
        }

        function resetInactivityTimer() {
            clearTimeout(inactivityTimer);
            clearTimeout(logoutWarningTimer);
            inactivityTimer = setTimeout(function() { showLogoutWarning(); }, autoLogoutMilliseconds - warningPeriod);
        }

        $(document).on('mousemove keypress click scroll', function() { resetInactivityTimer(); });
        $(document).ready(function() { resetInactivityTimer(); });

        // Trial Expiration Toast
        $(document).ready(function() {
            <?php if (isset($_SESSION['remaining_days']) && $_SESSION['remaining_days'] > 0) {
                $remaining_days = $_SESSION['remaining_days'];
                $message = "Your trial expires in " . $remaining_days . " days!";
            ?>
            Swal.fire({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 20000,
                timerProgressBar: true,
                icon: 'warning',
                title: 'Trial Period',
                text: '<?php echo $message; ?>'
            });
            <?php unset($_SESSION['remaining_days']); ?>
            <?php } ?>
        });
    </script>

     <script src="js/sync_system.js"></script>
</body>
</html>