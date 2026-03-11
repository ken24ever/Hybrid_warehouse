<?php
session_start(); // Make sure to start the session at the beginning of your script
// Add this near the top of your PHP block
$filter_branch_name = isset($_GET['branch_name']) ? urldecode($_GET['branch_name']) : "Global Scope";

if (isset($_SESSION['role'])) {
    $user_role = $_SESSION['role'];
    if ($user_role === "Sales Manager" || $user_role === "Super Admin" ) {
        $userID = $_SESSION['user_id'];
        $userName = $_SESSION['username'];
        $userRole = $_SESSION['role'];
      } else {
        header("Location:404.php");
        exit();
      }
} else {
    
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
// This line should be at the very top of your PHP file
//require_once 'validate_user_license.php';
?>
<?php
//require 'auto_logout_script.php';
require 'defined_global_settings.php'
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>IKA POS</title>
  <iframe id="receiptFrame" name="receiptFrame" style="width:0; height:0; border:0; border:none; visibility:hidden;"></iframe>
  <!-- plugins:css -->
  <link rel="stylesheet" href="vendors/feather/feather.css">
  <link rel="stylesheet" href="vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="vendors/css/vendor.bundle.base.css">
  <!-- endinject -->
  <!-- Plugin css for this page -->
  <link rel="stylesheet" href="vendors/datatables.net-bs4/dataTables.bootstrap4.css">
  <link rel="stylesheet" href="vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" type="text/css" href="js/select.dataTables.min.css">
   <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css">
  <!-- End plugin css for this page -->
  <!-- inject:css -->
  <link rel="stylesheet" href="css/vertical-layout-light/style.css">
  <!-- endinject -->
  <link rel="shortcut icon" href="<?php echo (defined('BUSINESS_LOGO') && !empty(BUSINESS_LOGO)) ? BUSINESS_LOGO : 'images/favicon.png'; ?>" />

    <!-- toast styling effect -->
<link rel="stylesheet" href="node_modules/toastify-js/src/toastify.css" />
  
<!-- Local Tippy.js CSS -->
<link rel="stylesheet" href="node_modules/tippy.js/dist/tippy.css">

<!-- Local Popper.js (UMD version for browser compatibility) -->
<script src="node_modules/@popperjs/core/dist/umd/popper.js"></script>

<!-- Local Tippy.js (UMD version for browser compatibility) -->
<script src="node_modules/tippy.js/dist/tippy.umd.min.js"></script>

<!-- bootstrap v4 -->
<link rel="stylesheet" href="bootstrap_v4/css/bootstrap.min.css">

<!-- dynamic logic for dark theme and light theme -->
<?php if (defined('DARK_MODE') && DARK_MODE == 1): ?>
    <link rel="stylesheet" type="text/css" href="css/dark-theme.css">
<?php else: ?>
    <link rel="stylesheet" type="text/css" href="css/light-theme.css">
<?php endif; ?>

<?php
$allowExpired = defined('ALLOW_EXPIRED_ITEMS_SALE') && ALLOW_EXPIRED_ITEMS_SALE == 1;
?>
<script>
const ALLOW_EXPIRED_ITEMS_SALE = <?= $allowExpired ? 'true' : 'false' ?>;
</script>


<script>
    // Pass PHP global settings to JavaScript variables, ensuring fallback if CURRENCY is empty
    const CURRENCY = "<?php echo !empty(CURRENCY) ? CURRENCY : (CUSTOM_CURRENCY ?? ''); ?>";
    const  PRICE_TYPE = "<?php echo !empty(PRICE_TYPE) ? PRICE_TYPE : ''; ?>";

</script>


    <!-- sweet  alert 2 lib -->
<link rel="stylesheet" href="sweetalert2/dist/sweetalert2.min.css">
<script src="sweetalert2/dist/sweetalert2.all.min.js"></script>
    <script src="js/alpine.min.js" defer></script>

<style>
/* Custom tooltip theme */
.tippy-box[data-theme~='custom'] {
  background-color: #ffffff; /* White background */
  color:rgb(51, 138, 101); /* Dark gray text */
  border: 1px solid #ddd; /* Light border */
  border-radius: 8px; /* Smooth rounded corners */
  box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1); /* Subtle shadow */
  font-family: 'Arial', sans-serif; /* Professional font */
  font-size: 14px; /* Adjust font size */
  padding: 8px; /* Extra padding for readability */
}

.tippy-box[data-theme~='custom'][data-placement^='top'] .tippy-arrow {
  border-top-color: #ffffff; /* Match background for the arrow */
}
.tippy-box[data-theme~='custom'][data-placement^='bottom'] .tippy-arrow {
  border-bottom-color: #ffffff;
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
  // Initialize Tippy.js with a custom theme and animation
  tippy('.tooltip-element', {
    content: 'This is a tooltip with custom styling!',
    theme: 'custom', // Apply the custom theme
    animation: 'scale', // Smooth scale effect
    arrow: true, // Show arrow
    placement: 'top', // Tooltip placement
    duration: [300, 200], // Smooth in and out durations
  });
</script>



      <!-- jquery lib -->
      <script src="jquery/jquery-3.6.0.min.js"></script>

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
    // This script will run on page load
    $(document).ready(function() {
        <?php
        if (isset($_SESSION['remaining_days']) && $_SESSION['remaining_days'] > 0) {
            $remaining_days = $_SESSION['remaining_days'];
            $message = "Your trial expires in " . $remaining_days . " days!";
        ?>
            // SweetAlert toast for the remaining days
            Swal.fire({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 20000, // Display for 20 seconds
                timerProgressBar: true,
                icon: 'warning',
                title: 'Trial Period',
                text: '<?php echo $message; ?>'
            });
            // Clear the session variable so the alert only shows once per login
            <?php unset($_SESSION['remaining_days']); ?>
        <?php
        }
        ?>
    });
</script>
      <style>
    /* Add your CSS styling for the suggestions container and items */
    .suggestions-container {
            position: relative;
        }

        #suggestions {
            border: 1px solid #ccc;
            padding: 10px;
            background-color: #fff;
            position: absolute;
            top: 100%; /* Position suggestions below the input */
            left: 0;
            width: 100%;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000; /* Ensure the suggestions appear above other elements */
            display: none; /* Hide suggestions by default */
        }

        .suggestion-item {
            cursor: pointer;
            padding: 5px;
            border-bottom: 2px solid #ccc;
            font-family: Maiandra GD !important;
            /* font-weight:bold !important; */
            font-variant:small-caps !important;
            font-size: 14px !important;
            
           
        }

        .suggestion-item:hover {
            background-color: #f0f0f0;
            font-size: 17px !important;
            font-weight:400 !important;
            transition: ease-in-out 1s;
            animation: ease-in-out;
            
        }

        /* Style the input field */
.classy-input {
    width: 100%;
    padding: 15px;
    font-size: 18px;
    border: none;
    border-bottom: 2px solid #3498db; /* Highlight bottom border */
    outline: none;
    background-color: #f9f9f9; /* Light background color */
    border-radius: 5px 5px 0 0;
    transition: all 0.3s ease-in-out;
    color: #333; /* Text color */
    font-family: "Helvetica Neue", sans-serif; /* Change the font family */
}

/* Style the placeholder text */
.classy-input::placeholder {
    color: #aaa; /* Placeholder text color */
}

/* On focus, highlight the input field */
.classy-input:focus {
    border-bottom: 2px solid #e74c3c; /* Change border color on focus */
    background-color: #fff; /* Change background color on focus */
    box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1); /* Add a subtle box shadow */
}

/* overflow cart styling */
#divCart {
  overflow-x: hidden; /* Hide horizontal scrollbar */
  overflow-y: scroll; /* Add vertical scrollbar */
  max-height: 500px !important;
}

/* styling for amount input field */
#total_Amt {
  /* Base Styles */
  width: 90%;
  padding: 10px;
  border: 1px solid #ccc;
  border-radius: 5px;
  font-size: 16px;

  /* Readonly Styling */
  background-color: #f2f2f2;
  color: #666;
  pointer-events: none; /* Prevent accidental clicks */
}

/* printers for receipt styling */
/* Style for mobile view */
@media screen and (max-width: 768px) {
    #ReceiptModal .modal-dialog {
        max-width: 100%;
        margin: 15px;
    }
    .modal-content {
        padding: 10px;
    }
}

/* A4 print styling */
@media print {
    body, html {
        width: 100%;
        height: 100%;
        margin: 0;
        padding: 0;
    }

    .modal-content {
        width: 21cm; /* A4 width */
        height: 29.7cm; /* A4 height */
        padding: 20px;
        border: none;
        box-shadow: none;
        margin: auto;
    }

    .modal-header, .modal-footer {
        display: none;
    }

    .modal-body {
        width: 100%;
        padding: 20px;
        font-size: 14px;
    }

    .table th, .table td {
        padding: 8px 15px;
    }

    .table {
        width: 100%;
        border-collapse: collapse;
    }

    .text-center {
        text-align: center;
    }

    .text-right {
        text-align: right;
    }

    .text-md-right {
        text-align: right;
    }

    .text-muted {
        color: #6c757d !important;
    }

    .receipt-footer {
        margin-top: 20px;
        text-align: center;
    }

    /* Ensure page break between sections if needed */
    .receipt-footer, .receipt-items {
        page-break-before: always;
    }
}

/* Ensure items are listed in a readable format */
#receiptItems {
    list-style-type: none;
    padding-left: 0;
}

#receiptItems li {
    padding: 10px;
    border-bottom: 1px solid #ddd;
}

/* Header Text Adjustments */
.modal-header h5 {
    font-size: 1.5rem;
}

/* Styling for print button */
.printReceipt {
    margin-right: 10px;
}


/* Add this CSS to style the textarea for a sleek and modern look */
.sleek-textarea {
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease-in-out;
    padding: 12px;
    font-size: 14px;
    resize: vertical;
}

.sleek-textarea:focus {
    border-color: #007bff;
    box-shadow: 0 4px 8px rgba(0, 123, 255, 0.3);
    outline: none;
}

/* Optionally add a slight focus effect to input fields */
.form-control:focus {
    box-shadow: none;
    border-color: #007bff;
}


/* Style the quantity input field */
/* Wrapper for quantity input and buttons */
.quantity-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
}

/* Style the input field */
.cart-item-quantity {
  width: 60px;
  height: 35px;
  font-size: 16px;
  text-align: center;
  border: 2px solid #ddd;
  border-radius: 5px;
  padding: 5px;
  background-color: #f5f5f5;
  color: #333;
  transition: all 0.3s ease;
}

/* Add focus effect for the quantity input */
.cart-item-quantity:focus {
  outline: none;
  border-color: #4CAF50;
  background-color: #e8f5e9;
}

/* Style for the increment and decrement buttons */
.quantity-decrement, .quantity-increment {
  background-color: #4CAF50;
  color: white;
  font-size: 18px;
  border: none;
  padding: 5px 10px;
  border-radius: 50%;
  cursor: pointer;
  transition: background-color 0.3s ease;
  width: 35px;
  height: 35px;
}

.quantity-decrement:hover, .quantity-increment:hover {
  background-color: #45a049;
}

/* Disable button when input is at its minimum or maximum value */
.quantity-decrement:disabled, .quantity-increment:disabled {
  background-color: #ccc;
  cursor: not-allowed;
}

    </style>
</head>
<body class="dark-theme light-theme">
        <!-- toast effect -->
        <script src="node_modules/toastify-js/src/toastify.js"></script>

  <div class="container-scroller dark-theme light-theme navbar-dark">
    <!-- partial:partials/_navbar.html -->
    <nav class="navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row dark-theme light-theme">
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
        <button class="navbar-toggler navbar-toggler align-self-center " type="button" data-toggle="minimize">
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
        // Store PHP context in JS variable
        var ACTIVE_BRANCH_CONTEXT = "<?php echo $active_branch_code; ?>";
    </script>
</ul>
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
          <li class="nav-item nav-profile dropdown dark-theme light-theme navbar-dark">
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
        <ul class="nav dark-theme light-theme navbar-dark">

    <?php if ($user_role === "Super Admin"): ?>
        <li class="nav-item dark-theme light-theme navbar-dark">
            <a class="nav-link" href="<?php echo linkTo('superAdmin.php'); ?>">
                <i class="icon-grid menu-icon"></i>
                <span class="menu-title">Dashboard</span>
            </a>
        </li>
    <?php endif; ?>

    <?php if ($user_role === "Super Admin"): ?>
        <li class="nav-item dark-theme light-theme navbar-dark">
            <a class="nav-link" href="<?php echo linkTo('transactions.php'); ?>">
                <i class="ti-receipt menu-icon"></i>
                <span class="menu-title">Transactions</span>
            </a>
        </li>
    <?php endif; ?>
        <?php if (!$is_visiting): ?>
         <li class="nav-item dark-theme light-theme navbar-dark">
            <a class="nav-link" href="<?php echo linkTo('manage_user.php'); ?>">
                <i class="icon-head menu-icon"></i><span class="menu-title">User Mgt. & App Setting</span>
            </a>
         </li>
     <?php endif; ?>

    <?php if ($user_role === "Super Admin"): ?>


        <li class="nav-item dark-theme light-theme navbar-dark">
            <a class="nav-link" href="<?php echo linkTo('store_keeper.php'); ?>">
                <i class="icon-plus menu-icon"></i>
                <span class="menu-title">Add Items & More</span>
            </a>
        </li>
        <li class="nav-item dark-theme light-theme navbar-dark">
            <a class="nav-link" href="<?php echo linkTo('manage_item.php'); ?>"> 
                <i class="ti-package menu-icon"></i>
                <span class="menu-title">Manage Items</span>
            </a>
        </li>
    <?php endif; ?>

    

    <li class="nav-item dark-theme light-theme navbar-dark">
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
            <div class="col-md-12 grid-margin dark-theme light-theme navbar-dark">
              <div class="row dark-theme light-theme navbar-dark">
                <div class="col-12 col-xl-8 mb-4 mb-xl-0 dark-theme light-theme navbar-dark">
                  <h3 class="font-weight-bold dark-theme light-theme navbar-dark">Welcome <?php echo $_SESSION['username'];?> | Privilege Level: <?php echo $_SESSION['role']; ?></h3>
                  <h6 class="font-weight-normal mb-0 dark-theme light-theme navbar-dark">POS systems ready to initiate!</span></h6>
                </div>
              </div>
            </div>
          </div>



          <div class="row dark-theme light-theme navbar-dark">
            <div class="col-md-6 grid-margin stretch-card dark-theme light-theme navbar-dark">
              <div class="card dark-theme light-theme navbar-dark">
                <div class="card-body dark-theme light-theme navbar-dark">
                  <h4 class="card-title mb-4">POS</h4>
 <form id="searchForm">
                    
                    <!-- Row 1: Item Search (Spans full width) -->
                    <div class="form-row">
                        <div class="col-12 form-group position-relative"> <!-- position-relative is for the suggestions dropdown -->
                            <label for="itemUniqueCode" class="col-form-label font-weight-bold">Item Search</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-search text-dark m-0"></i></span>
                                </div>
                                <input type="text" class="form-control form-control-lg" id="itemUniqueCode" name="itemUniqueCode" placeholder="Enter Bar-code or name..." autocomplete="off">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-primary btn-lg" id="add_to_cart_btn">
                                        <i class="fas fa-cart-plus mr-2 "></i> Add
                                    </button>
                                </div>
                                <!-- 
                                  Your suggestions div. Your JS will populate this.
                                  I'd recommend your JS adds 'list-group' to this div
                                  and 'list-group-item list-group-item-action' to each suggestion-item.
                                -->
                                <div id="suggestions" class="suggestions-list shadow-sm">
                                    <!-- Example of a suggestion item your JS might create -->
                                    <!-- <div class="suggestion-item list-group-item list-group-item-action">Sample Item 1</div> -->
                                    <!-- <div class="suggestion-item list-group-item list-group-item-action">Sample Item 2</div> -->
                                </div>
                            </div>
                            <small id="itemSearchHelp" class="form-text text-muted">
                                Enter the item's Bar Code  or name to search.
                            </small>
                        </div>
                    </div>

                    <!-- Row 2: Item ID and Name -->
                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label for="item_id" class="col-form-label">Item ID:</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-id-card text-dark"></i></span>
                                </div>
                                <input type="text" class="form-control form-control-lg" id="item_id" name="item_id" readonly>
                            </div>
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="item_name" class="col-form-label">Item Name:</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-box-open text-dark"></i></span>
                                </div>
                                <input type="text" class="form-control form-control-lg" id="item_name" name="item_name" readonly>
                            </div>
                        </div>
                    </div>

                    <!-- Row 3: Item Description -->
                    <div class="form-row">
                        <div class="col-12 form-group">
                            <label for="item_notes" class="col-form-label">Item Description:</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-file-alt text-dark"></i></span>
                                </div>
                                <textarea class="form-control form-control-lg" id="item_notes" name="item_notes" rows="3" readonly></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Row 4: Price Type, Price, and Stock -->
                    <div class="form-row">
                        <div class="col-md-4 form-group">
                            <label for="price_category" class="col-form-label">Price Type:</label>
                            <!-- Wrapped in a div to match height of other inputs -->
                            <div class="price-switch-wrapper"> 
                                <div class="input-group-prepend">
                                    <span class="input-group-text border-0 bg-transparent pl-0"><i class="fas fa-tag text-dark"></i></span>
                                </div>
                                <div class="custom-control custom-switch custom-control-lg">
                                    <input type="checkbox" class="custom-control-input" id="price_category" name="price_category">
                                    <label class="custom-control-label" for="price_category" id="price_label">Retail</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 form-group">
                            <label for="item_price" class="col-form-label">Item Price:</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text text-dark"><?php echo !empty(CURRENCY) ? CURRENCY : (CUSTOM_CURRENCY ?? ''); ?></span>
                                </div>
                                <input type="number" class="form-control form-control-lg" id="item_price" name="item_price" <?php if ($user_role !== 'Super Admin') echo 'readonly'; ?> placeholder="Enter price">
                                <?php if ($user_role === 'Super Admin') { ?>
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-warning" id="edit_price_btn">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                </div>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="col-md-4 form-group">
                            <label for="quantity_in_stock" class="col-form-label">Quantity in Stock:</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-cubes text-dark"></i></span>
                                </div>
                                <input type="text" class="form-control form-control-lg" id="quantity_in_stock" name="quantity_in_stock" readonly>
                            </div>
                        </div>
                    </div>
                </form>


                    </div> 
                </div> 
            </div>
            <div class="col-md-6 grid-margin dark-theme light-theme navbar-dark">
    <div class="card dark-theme light-theme navbar-dark">
        <div class="card-body dark-theme light-theme navbar-dark">
            <h4 class="card-title dark-theme light-theme navbar-dark">POS</h4>

            <strong style="font-size: 35px !important;"><?php echo !empty(CURRENCY) ? CURRENCY : (CUSTOM_CURRENCY ?? ''); ?><input type="number" id="total_Amt" name="total_Amt" class="dark-theme light-theme navbar-dark" readonly></strong>

            <!-- "Add Billing Details" Button -->
            <div id="cartHeader" class="cart-header dark-theme light-theme navbar-dark">
            <br>
              <p> <strong>Items in Cart: <span id="itemCount">0</span></strong> <button type="button" class="btn btn-sm btn-info mb-2" data-toggle="modal" data-target="#billingModal">
    + Add Billing Details
</button></p>
  <?php if ($user_role === 'Super Admin') { ?> 
<div class="form-group col-md-4">
        <label for="EditTransactionDate" class="col-form-label">Edit Transaction Date:</label>
        <div class="input-group">
            <div class="input-group-prepend">
                <span class="input-group-text"><i class="fas fa-calendar-days text-dark"></i></span>
            </div>
                        <!-- MODIFIED: Changed type to 'datetime-local' and updated default value format -->
                        <input type="datetime-local" class="form-control dark-theme light-theme navbar-dark " name="EditTransactionDate" id="EditTransactionDate" value="<?php echo date("Y-m-d\TH:i"); ?>">
        </div>
    </div>
    <?php } else{
        ?>
        <div class="form-group col-md-4">
        <label for="EditTransactionDate" class="col-form-label">Edit Transaction Date:</label>
        <div class="input-group">
            <div class="input-group-prepend">
                <span class="input-group-text"><i class="fas fa-cubes"></i></span>
            </div>
                        <!-- MODIFIED: Changed type to 'datetime-local' and updated default value format -->
                        <input type="datetime-local" class="form-control dark-theme light-theme navbar-dark " name="EditTransactionDate" id="EditTransactionDate" value="<?php echo date("Y-m-d\TH:i"); ?>" readonly>
        </div>
    </div>
    
    <?php } ?>

            </div>
            <div id="divCart" class="dark-theme light-theme navbar-dark">
                <ul id="cart" class="list-group mb-4 dark-theme light-theme navbar-dark">
                    </ul>
            </div>

            <button type="button" class="btn btn-primary btn-block" id="previewButton">Preview Items</button>
        </div>
    </div>
</div>
        </div><!-- ends -->
        </div>
        <div class="modal fade dark-theme light-theme navbar-dark" id="previewModal" tabindex="-1" role="dialog" aria-labelledby="previewModalLabel" aria-hidden="true">
  <div class="modal-dialog dark-theme light-theme navbar-dark" role="document">
    <div class="modal-content dark-theme light-theme navbar-dark">
      <div class="modal-header dark-theme light-theme navbar-dark">
        <h5 class="modal-title dark-theme light-theme navbar-dark" id="previewModalLabel">Preview Cart Items</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body dark-theme light-theme navbar-dark">
        <ul id="previewCart" class="list-group dark-theme light-theme navbar-dark"></ul>
        <hr>
        <b><span id="tot" class="dark-theme light-theme navbar-dark"></span></b>
        <hr>
        <br>
        <br>
        <form id="paymentForm" class="dark-theme light-theme navbar-dark">
          <div class="form-group dark-theme light-theme navbar-dark">
            <label for="modeOfPayment">Mode of Payment</label>
            <select class="form-control dark-theme light-theme navbar-dark" id="modeOfPayment" name="modeOfPayment">
              <option value="cash">Cash</option>
              <option value="pos">POS</option>
              <option value="mobile_transfer">Mobile Transfer</option>
            </select>
          </div>
        </form>
        
      </div>
      <div class="modal-footer dark-theme light-theme navbar-dark">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        <button type="button" id="initiateInvoice" class="btn btn-warning">Initiate Invoice</button>
        <button type="button" id="finalSubmitBtn" class="btn btn-primary">Make Payment</button>
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
<!--   <script src="vendors/js/vendor.bundle.base.js"></script> -->
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
  <!-- End custom js for this page-->

  <script>
 $(document).ready(function () {
    var totalAmount = 0.0;

        // Set the initial price type from the settings (Retail or Wholesale)
        var priceType = PRICE_TYPE.toLowerCase() === 'wholesale' ? 'wholesale' : 'retail';
    $('#price_category').prop('checked', priceType === 'wholesale');
    $('#price_label').text(priceType.charAt(0).toUpperCase() + priceType.slice(1));

    // Event listener for price switch toggle
    $('#price_category').on('change', function () {
        var selectedCategory = $(this).is(':checked') ? 'wholesale' : 'retail';
        $('#price_label').text(selectedCategory.charAt(0).toUpperCase() + selectedCategory.slice(1));

        // Update the price dynamically
        var itemPrice = selectedCategory === 'retail' ? $('#item_price').data('retail') : $('#item_price').data('wholesale');
        $('#item_price').val(itemPrice);
    });
    

   // Handle input and suggestions for the item search
$(document).on('input', '#itemUniqueCode', function (e) {
    e.preventDefault();
    var searchTerm = $('#itemUniqueCode').val();

    // Make an AJAX request to fetch item suggestions  
    $.ajax({
        url: 'item_details.php',
        type: 'GET',
              // [FIX]: Added branch_code to ensure we search the correct branch context
        data: { 
            searchTerm: searchTerm,
            branch_code: typeof ACTIVE_BRANCH_CONTEXT !== 'undefined' ? ACTIVE_BRANCH_CONTEXT : '' 
        },
        dataType: 'json',
        success: function (response) {
            if (response.status === 'success') {
                var item = response.data;

                // If item is out of stock, notify the user
                if (item[0].quantity_in_stock <= 0) {
                    Toastify({
                        text: "This item is out of stock, go restock now!",
                        duration: 5000,
                        gravity: 'top',
                        close: true,
                        style: {
                            background: 'linear-gradient(to right, #FFA0A0, #B88AFF, #A0A0FF)',
                        },
                    }).showToast();
                    return false;
                };

                // MODIFIED EXPIRATION CHECK
                // Prevent adding expired items to the form when disallowed
                if (item[0]?.is_expired && ALLOW_EXPIRED_ITEMS_SALE === false) {
                    Toastify({
                        text: "Sale of expired items is disallowed by system settings!",
                        duration: 4000,
                        gravity: "top",
                        close: true,
                        stopOnFocus: true,
                        style: {
                            background: "linear-gradient(to right, #ff4d4d, #ff8080)",
                            color: "#fff",
                            fontWeight: "600"
                        }
                    }).showToast();

                    // Reset the field and prevent submission
                    $('#itemUniqueCode').val('').focus();
                    return false;
                }
                // *** END FIX ***
                
                // Populate form with item details
                $('#item_id').val(item[0].item_id);
                $('#item_id').data('is-expired', item[0].is_expired); // Store expired status
                $('#item_name').val(item[0].item_name);
                $('#quantity_in_stock').val(item[0].quantity_in_stock);
                $('#item_notes').val(item[0].item_description); // Gets the item notes

                // Store both price types in the element
                $('#item_price')
                    .data('retail', item[0].retail)
                    .data('wholesale', item[0].wholesale);

                // Ensure checkbox reflects the correct price type
                var defaultPriceType = PRICE_TYPE.toLowerCase(); // 'retail' or 'wholesale'
                $('#price_category').prop('checked', defaultPriceType === 'wholesale');
                $('#price_label').text(defaultPriceType.charAt(0).toUpperCase() + defaultPriceType.slice(1));

                // Now, set the item price based on `PRICE_TYPE`
                var defaultPrice = defaultPriceType === 'wholesale' ? item[0].wholesale : item[0].retail;
                $('#item_price').val(defaultPrice);

                    // ✅ Auto-add to cart for 13-digit barcode items (exclude named items)
                if (/^\d{13}$/.test(searchTerm) && !isNaN(searchTerm)) {
                    setTimeout(function () {
                        $('#add_to_cart_btn').trigger('click');
                        $('#itemUniqueCode').val('');
                    }, 200);
                }


                // Enable editing of the price field when "Edit Price" button is clicked
                $('#edit_price_btn')
                    .off('click') // Remove previous event handlers
                    .on('click', function () {
                        $('#item_price').prop('readonly', false); // Make price editable
                    });

       

                // Hide suggestions and clear search field
                $('#suggestions').hide();
                $('#itemUniqueCode').val('');
            } else if (response.status === 'suggestions') {
                // Generate autocomplete suggestions
                var suggestions = response.data;
                var suggestionsHtml = '';

   

                $.each(suggestions, function (index, suggestion) {
    const isExpired = suggestion.is_expired;
    const expiredTag = isExpired ? `<span class="badge badge-danger ml-2">Expired</span>` : '';

    suggestionsHtml += `
        <div class="tooltip-element suggestion-item d-flex align-items-center justify-content-between dark-theme light-theme navbar-dark" data-tippy-content="
            <strong>Item:</strong> ${suggestion.item_name}<br>
            <strong>Retail:</strong> ${CURRENCY + suggestion.retail}<br>
            <strong>Wholesale:</strong> ${CURRENCY + suggestion.wholesale}<br>
            <strong>Stock:</strong> ${suggestion.quantity_in_stock}<br>
            <strong>Desc:</strong> ${suggestion.item_description}<br>
            <strong>Expires:</strong> ${suggestion.expiration_date || 'N/A'}">
            <span class="suggestion-item-name">${suggestion.item_name}</span>
            ${expiredTag}
        </div>`;
});


                // Display or hide suggestions
                if (suggestionsHtml !== '') {
                    $('#suggestions').html(suggestionsHtml).show();
                } else {
                    $('#suggestions').hide();
                }

                // Initialize tooltips for the suggestions
                tippy('.tooltip-element', {
                    theme: 'custom',
                    animation: 'scale',
                    arrow: true,
                    placement: 'right',
                    duration: [300, 200],
                    allowHTML: true,
                });


    // Handle click on a suggestion item
$('#suggestions').off('click', '.suggestion-item').on('click', '.suggestion-item', function () {
    var selectedSuggestion = $(this).find('.suggestion-item-name').text().trim();

    // Hide suggestions dropdown
    $('#suggestions').hide();
    $('#itemUniqueCode').val(''); // Clear the search field

    // Find and populate selected item's details
    if (typeof suggestions !== 'undefined' && suggestions.length > 0) {
        var selectedItem = suggestions.find(function (s) {
            return s.item_name.trim().toLowerCase() === selectedSuggestion.toLowerCase();
        });

        if (selectedItem) {
           
           // Block expired items from being added to the cart when sale is disallowed
            if (selectedItem?.is_expired && ALLOW_EXPIRED_ITEMS_SALE === false) {
                Toastify({
                    text: `This item (“${selectedItem.item_name}”) has expired and cannot be sold!`,
                    duration: 5000,
                    gravity: "top",
                    close: true,
                    stopOnFocus: true,
                    style: {
                        background: "linear-gradient(to right, #ff4d4d, #ff8080)",
                        color: "#fff",
                        fontWeight: "600"
                    }
                }).showToast();

                $('#itemUniqueCode').val('').focus(); // Corrected this line
                return false;
            }
            // END NEW CHECK

            $('#item_id').val(selectedItem.item_id);
            $('#item_id').data('is-expired', selectedItem.is_expired); // Store expired status
            $('#item_name').val(selectedItem.item_name);
            $('#quantity_in_stock').val(selectedItem.quantity_in_stock);
            $('#item_notes').val(selectedItem.item_description);

            // Store both price types in the element
            $('#item_price')
                .data('retail', selectedItem.retail)
                .data('wholesale', selectedItem.wholesale);

            // Determine the default price type
            var defaultPriceType = PRICE_TYPE.toLowerCase(); // 'retail' or 'wholesale'
            
            // Update checkbox and label based on the default price type
            $('#price_category').prop('checked', defaultPriceType === 'wholesale');
            $('#price_label').text(defaultPriceType.charAt(0).toUpperCase() + defaultPriceType.slice(1));

            // Set the price input based on the selected price type
            var defaultPrice = defaultPriceType === 'wholesale' ? selectedItem.wholesale : selectedItem.retail;
            $('#item_price').val(defaultPrice);
        
                        } else {
                            console.error('Selected item not found in suggestions.', { selectedSuggestion, suggestions });
                        }
                    } else {
                        console.error('Suggestions array is undefined or empty.', suggestions);
                    }
                });
            } else {
                Toastify({
                    text: response.message || 'An error occurred.',
                    duration: 5000,
                    gravity: 'top',
                    close: true,
                    style: {
                        background: 'linear-gradient(to right, #FFA0A0, #B88AFF, #A0A0FF)',
                    },
                }).showToast();
            }
        },
        error: function (xhr, status, error) {
            console.error('AJAX error:', error);
        },
    });
});// ends here



    // Handle adding item to the cart
// Handle adding item to the cart
$('#add_to_cart_btn').on('click', function () {
    const selectedPrice = parseFloat($('#item_price').val());
    const item_id = $('#item_id').val();
    const item_name = $('#item_name').val();
    const EditTransactionDate = $('#EditTransactionDate').val();
    const quantity_in_stock = parseInt($('#quantity_in_stock').val());
    const item_notes = $('#item_notes').val();
    const is_expired = $('#item_id').data('is-expired'); // Retrieve the stored status

    // Check if item is expired and not allowed
    if (is_expired && ALLOW_EXPIRED_ITEMS_SALE === false) {
        Toastify({
            text: `This item (“${item_name}”) has expired and cannot be sold!`,
            duration: 5000,
            gravity: "top",
            close: true,
            stopOnFocus: true,
            style: {
                background: "linear-gradient(to right, #ff4d4d, #ff8080)",
                color: "#fff",
                fontWeight: "600"
            }
        }).showToast();
        return false; // Stop the item from being added
    }

    // Ensure the item doesn't already exist in the cart
    if ($('#' + item_id).length > 0) {
        Toastify({
            text: 'Item already exists in the cart!',
            duration: 5000,
            gravity: 'top',
            close: true,
            style: {
                background: 'linear-gradient(to right, #FFA0A0, #B88AFF, #A0A0FF)',
            },
        }).showToast();
        return false;
    }

          // If item is out of stock, notify the user
          if (quantity_in_stock <= 0) {
                    Toastify({
                        text: "This item is out of stock, go restock now!",
                        duration: 5000,
                        gravity: 'top',
                        close: true,
                        style: {
                            background: 'linear-gradient(to right, #FFA0A0, #B88AFF, #A0A0FF)',
                        },
                    }).showToast();
                    return false;
                }

    // Add the item to the cart
    /**
 * This is the new JavaScript template string for your cart item.
 * * - It uses Bootstrap 4 classes for a modern, professional UI.
 * - It removes all the old "dark-theme", "light-theme", "navbar-dark" classes.
 * - It 100% PRESERVES all your original IDs, data attributes, and JS-critical classes
 * (like 'quantity-decrement', 'cart-item-quantity', 'cart-item-remove', 'cart-item-price')
 * so your existing JavaScript logic will continue to work perfectly.
 * * You can copy and paste this directly into your main JS file to replace the old 'cartItem' variable.
 */

const cartItem = `
<li id="${item_id}" class="list-group-item d-flex justify-content-between align-items-center shadow-sm mb-2 rounded dark-theme light-theme navbar-dark" data-sale-price="${selectedPrice}" data-description="${item_notes}">
  
  <div class="flex-grow-1" style="max-width: 40%; dark-theme light-theme navbar-dark">
    <strong class="item-name text-truncate d-block">${item_name}</strong>
    <small class="dark-theme light-theme navbar-dark">Price: ${CURRENCY}${selectedPrice}</small>
  </div>
  
  <div class="quantity-wrapper d-flex align-items-center dark-theme light-theme navbar-dark">
    <button class="quantity-decrement btn btn-sm btn-outline-secondary font-weight-bolder rounded-circle p-0" style="width: 30px; height: 30px;" type="button">−</button>
    <input type="number" min="1" max="${quantity_in_stock}" value="1" class="cart-item-quantity form-control form-control-sm text-center mx-2" data-quantity="1" style="width: 50px;">
    <button class="quantity-increment btn btn-sm btn-outline-secondary font-weight-bolder rounded-circle p-0" style="width: 30px; height: 30px;" type="button">+</button>
  </div>
  
  <div class="cart-item-price font-weight-bold dark-theme light-theme navbar-dark" style="min-width: 90px; text-align: right; dark-theme light-theme navbar-dark">
    ${CURRENCY}${selectedPrice}
  </div>
  
  <button type="button" class="btn btn-outline-danger btn-sm cart-item-remove ml-2 dark-theme light-theme navbar-dark" style="width: 30px; height: 30px; padding: 0;">
    <i class="fas fa-times"></i>
  </button>
  
  <div class="cart-item-description text-muted" style="display:none; font-size: 0.875rem; margin-top: 5px;">${item_notes}</div>
  <div class="cart-EditTransactionDate text-muted" style="display:none; font-size: 0.875rem; margin-top: 5px;">${EditTransactionDate}</div>
</li>`;

    $('#cart').append(cartItem);

    // Update total amount and item count
    totalAmount += selectedPrice;
    updateTotalAmountDisplay();
    updateItemCount();
});

// Handle quantity increment and decrement
$('#cart').on('click', '.quantity-increment, .quantity-decrement', function (event) {
    event.preventDefault();
    const $button = $(this);
    const $input = $button.siblings('.cart-item-quantity');
    let currentValue = parseInt($input.val());
    const maxQuantity = parseInt($input.attr('max'));

    // Increment or decrement the quantity
    if ($button.hasClass('quantity-increment') && currentValue < maxQuantity) {
        currentValue++;
    } else if ($button.hasClass('quantity-decrement') && currentValue > 1) {
        currentValue--;
    }

    $input.val(currentValue); // Update the input value
    updateCartItemPrice($input); // Update item price
    recalculateTotalAmount(); // Update the total amount
    updateItemCount(); // Update the item count
});

// Handle direct input in quantity fields
$('#cart').on('input', '.cart-item-quantity', function () {
    const $input = $(this);
    const maxQuantity = parseInt($input.attr('max'));
    let enteredQuantity = parseInt($input.val());

    // Validate and correct the input
    if (isNaN(enteredQuantity) || enteredQuantity <= 0) {
        enteredQuantity = 1;
    } else if (enteredQuantity > maxQuantity) {
        enteredQuantity = maxQuantity;
        Toastify({
            text: `You cannot exceed the available quantity (${maxQuantity})!`,
            duration: 5000,
            gravity: 'top',
            close: true,
            style: {
                background: 'linear-gradient(to right, #FFA0A0, #B88AFF, #A0A0FF)',
            },
        }).showToast();
    }

    $input.val(enteredQuantity); // Correct the value
    updateCartItemPrice($input); // Update the price for the item
    recalculateTotalAmount(); // Update the total cart amount
});

// Update price for this item when quantity changes
function updateCartItemPrice($input) {
    const $item = $input.closest('li');
    const quantity = parseInt($input.val());
    const salePrice = parseFloat($item.data('sale-price'));
    const itemPrice = salePrice * quantity;

    // Update the price in the cart item
    $item.find('.cart-item-price').text(CURRENCY + itemPrice.toFixed(2));
}

// Recalculate the total amount after quantity change
function recalculateTotalAmount() {
    totalAmount = 0;

    // Sum up the total amount of all items in the cart
    $('#cart .cart-item-quantity').each(function () {
        const quantity = parseInt($(this).val());
        const price = parseFloat($(this).closest('li').data('sale-price'));
        totalAmount += quantity * price;
    });

    // Update the total amount display
    updateTotalAmountDisplay();
}

// Handle removing items from the cart
$('#cart').on('click', '.cart-item-remove', function () {
    const $cartItem = $(this).closest('li');
    const salePrice = parseFloat($cartItem.data('sale-price'));
    const quantity = parseInt($cartItem.find('.cart-item-quantity').val());
    const totalItemPrice = salePrice * quantity;

    totalAmount -= totalItemPrice; // Adjust the total amount
    $cartItem.remove(); // Remove the item from the cart

    // Update the total amount and item count
    updateTotalAmountDisplay();
    updateItemCount();
});

// Update the total amount display
function updateTotalAmountDisplay() {
    $('#totalAmount').text(CURRENCY + totalAmount.toFixed(2));
}

// Update the cart item count
function updateItemCount() {
    const itemCount = $('#cart li').length;
    $('#itemCount').text(itemCount);
}

 

    // Function to update the total amount display
function updateTotalAmountDisplay() {
    var formattedAmount = CURRENCY + totalAmount.toFixed(2);
    $('#totalAmount').text('Total Amount: ' + formattedAmount);
    $('#total_Amt').val(totalAmount.toFixed(2));
}

// Function to update the item count in the cart
function updateItemCount() {
    let totalItems = 0;
    $('#cart .cart-item-quantity').each(function () {
        totalItems += parseInt($(this).val(), 10);
    });
    $('#itemCount').text(totalItems);
}

// Handle preview button click
$(document).on('click', '#previewButton', function () {
    var cartItems = [];
    var totalAmount = 0;
    const itemCount = $('#cart li').length;

    /* check if the cart is empty */
    if (!itemCount > 0) {
        Toastify({
            text: 'Oops! Cart Must Be Populated With Items List.',
            duration: 5000,
            gravity: 'top',
            close: true,
            style: {
                background: 'linear-gradient(to right, #FFA0A0, #B88AFF, #A0A0FF)',
            }
        }).showToast();
        return false;
    }

    $('#cart li').each(function () {
        var item_name = $(this).find('strong.item-name').text();
        var quantity = parseInt($(this).find('.cart-item-quantity').val(), 10);
        var salePrice = parseFloat($(this).find('.cart-item-price').text().replace(/[^\d.]/g, ''));
        var fixedPrice = parseFloat($(this).data('sale-price'));

        if (isNaN(fixedPrice)) {
            console.error("Fixed price is undefined for:", item_name);
            fixedPrice = salePrice; // Fallback if missing
        }

        var totalItemSale = quantity * fixedPrice;
        totalAmount += totalItemSale;

        if (quantity === 0) {
            Toastify({
                text: 'Oops! Item Quantity Cannot Be Zero.',
                duration: 5000,
                gravity: 'top',
                close: true,
                style: {
                    background: 'linear-gradient(to right, #FFA0A0, #B88AFF, #A0A0FF)',
                }
            }).showToast();
            return false;
        }

        cartItems.push({
            item_name: item_name,
            quantity: quantity,
            fixedPrice: fixedPrice,
            totalItemSale: totalItemSale
        });
    });

    // Get Bill To Name and Address
    var billToName = $('#billToName').val() || "Walk-in Customer";
    var billToAddress = $('#billToAddress').val() || "N/A";

    // Populate the preview modal
    $('#previewCart').empty();
    cartItems.forEach(function (item) {
        $('#previewCart').append(
            `<li class="list-group-item dark-theme light-theme navbar-dark">${item.item_name} x ${item.quantity} (${CURRENCY + item.fixedPrice}) -> ${CURRENCY + item.totalItemSale}</li>`
        );
    });

    // Add Bill To Details and Grand Total
    $('#previewCart').append(
        `<li class="list-group-item dark-theme light-theme navbar-dark"><strong>Bill To: </strong>${billToName}</li>` +
        `<li class="list-group-item dark-theme light-theme navbar-dark"><strong>Address: </strong>${billToAddress}</li>` +
        `<li class="list-group-item dark-theme light-theme navbar-dark"><strong>Grand Total: </strong>${CURRENCY + totalAmount.toFixed(2)}</li>`
    );

    $('#previewModal').modal('show');
});

$(document).on('click', '#initiateInvoice', function () { 
    var cartItems = [];
    var totalAmount = 0;
    var EditTransactionDate = $('#EditTransactionDate').val();

    $('#cart li').each(function () {
        var item_name = $(this).find('strong.item-name').text();
        var quantity = parseInt($(this).find('.cart-item-quantity').val(), 10);
        var fixedPrice = parseFloat($(this).data('sale-price')) || 0;
        var item_description = $(this).data('description') || "N/A";

        var totalItemSale = quantity * fixedPrice;
        totalAmount += totalItemSale;

        cartItems.push({
            item_name: item_name,
            quantity: quantity,
            fixedPrice: fixedPrice,
            totalItemSale: totalItemSale,
            item_description: item_description
        });
    });

    var billToName = $('#billToName').val() || "Walk-in Customer";
    var billToAddress = $('#billToAddress').val() || "N/A";

    // Generate current date
    var invoiceDate = $('#EditTransactionDate').val();

    // Store data in localStorage
    localStorage.setItem("billToName", billToName);
    localStorage.setItem("billToAddress", billToAddress);
    localStorage.setItem("grandTotal", totalAmount.toFixed(2));
    localStorage.setItem("items", JSON.stringify(cartItems));
    localStorage.setItem("invoiceDate", invoiceDate);

    // Redirect to invoice page
    window.location.href = 'invoice.php';
});



// 1. Define the User Role Variable for JS
const USER_ROLE_JS = "<?php echo $_SESSION['role'] ?? ''; ?>";

// 2. The Central Processing Function
function processSaleSubmission(targetBranchCode) {
    var modeOfPayment = $('#modeOfPayment').val(); 
    var priceCategory = $('#price_category').val();
    var EditTransactionDate = $('#EditTransactionDate').val(); 
    var cartItems = [];
    var userID = <?php echo $_SESSION['user_id']; ?>; 

    // UI Feedback
    var $btn = $('#finalSubmitBtn'); 
    var $modalBtn = $('#confirmAttributionBtn'); 
    
    // Disable buttons
    $btn.prop('disabled', true).text('Processing...');
    $modalBtn.prop('disabled', true).text('Processing...');

    // Gather Cart Items
    $('#cart li').each(function () {
        // [CRITICAL FIX] Get the ID from the <li> attribute
        var item_id = $(this).attr('id'); 

        var item_name = $(this).find('strong.item-name').text();
        var quantity = parseInt($(this).find('.cart-item-quantity').val(), 10);
        var salePrice = parseFloat($(this).find('.cart-item-price').text().replace(/[^\d.]/g, ''));
        var fixedPrice = parseFloat($(this).data('sale-price'));
        var item_description = $(this).data('description');

        if (quantity > 0) {
            cartItems.push({
                id: item_id,   // <--- ADD THIS LINE (Server needs this!)
                item_name: item_name,
                quantity: quantity,
                salePrice: salePrice,  
                totalItemSale: quantity * fixedPrice,
                priceCategory: priceCategory,
                modeOfPayment: modeOfPayment,
                userID: userID,
                fixedPrice: fixedPrice,
                item_description: item_description,
                EditTransactionDate: EditTransactionDate
            });
        }
    });

    if (cartItems.length === 0) {
        Toastify({ text: 'Cart is empty!', style: { background: 'red' } }).showToast();
        $btn.prop('disabled', false).text('Make Payment');
        $modalBtn.prop('disabled', false).text('Confirm & Process Sale');
        return;
    }

    var totalAmount = cartItems.reduce((sum, item) => sum + item.totalItemSale, 0);

// Prepare Payload
    // [CRITICAL FIX] Added 'modeOfPayment' to top-level payload
    let payload = { 
        cartItems: JSON.stringify(cartItems),
        modeOfPayment: modeOfPayment 
    };
    
    // Attach Branch Code ONLY if specifically selected (Super Admin Override)
    if (targetBranchCode) {
        payload.target_branch_code = targetBranchCode;
    }

    $.ajax({
        url: 'process_sale.php',
        type: 'POST',
        data: payload,
        dataType: 'json',
        success: function (response) {
    if (response.success) {
                // 1. Save Data for Receipt
                localStorage.setItem("transactionId", response.transaction_group_id); 
                localStorage.setItem("paymentDate", response.transactionDate);
                localStorage.setItem("billToName", $('#billToName').val() || '');
                localStorage.setItem("billToAddress", $('#billToAddress').val() || '');
                localStorage.setItem("grandTotal", totalAmount.toFixed(2));
                localStorage.setItem("items", JSON.stringify(cartItems));

                // 2. Trigger Silent Print (Load Receipt in Hidden Frame)
                // The iframe will load receipt.php, which executes window.print() automatically.
                // With --kiosk-printing enabled, this happens instantly in the background.
                var receiptFrame = document.getElementById('receiptFrame');
                receiptFrame.src = 'receipt.php' + window.location.search;

                // 3. Success Notification
                Toastify({ 
                    text: "Sale Successful! Printing Receipt...", 
                    style: { background: "linear-gradient(to right, #00b09b, #96c93d)" },
                    duration: 3000 
                }).showToast();

                // 4. Reset POS Interface for Next Sale Immediately
                resetPosInterface();

            } else {
                Toastify({ text: response.message, style: { background: 'red' } }).showToast();
                $btn.prop('disabled', false).text('Make Payment');
                $modalBtn.prop('disabled', false).text('Confirm & Process Sale');
            }
        },
        error: function () {
            Toastify({ text: 'Server Error', style: { background: 'red' } }).showToast();
            $btn.prop('disabled', false).text('Make Payment');
            $modalBtn.prop('disabled', false).text('Confirm & Process Sale');
        },
        complete: function () {
            $('#salesAttributionModal').modal('hide');
        }
    });
}

// 3. The ONLY Click Handler for #finalSubmitBtn
$(document).on('click', '#finalSubmitBtn', function (e) {
    e.preventDefault();

    // STRICT CHECK: Only Super Admin gets the modal
    if (USER_ROLE_JS === 'Super Admin' && navigator.onLine) {
        
        // [FIX] FORCE FRESH LOAD: Always fetch latest branch status when opening modal
        // This prevents "Stale Data" where a branch went offline after you loaded the page.
        $.ajax({
            url: 'get_branches.php', 
            dataType: 'json',
            beforeSend: function() {
                // Show loading state in the dropdown while fetching
                $('#targetBranchSelect').empty().append('<option>Loading Real-time Status...</option>');
            },
            success: function(branches) {
                let $select = $('#targetBranchSelect');
                $select.empty();
                
                // [FIX] No Hardcoded 'HEAD_OFFICE'. We trust the API completely.
                if (branches.length === 0) {
                    $select.append('<option value="" disabled>No Branches Found</option>'); 
                }

                $.each(branches, function(i, branch) {
                    // 1. Normalize Status (Handle 'Online', 'online', '1', 'Active')
                    let rawStatus = (branch.status || 'offline').toString().toLowerCase().trim();
                    let normalizedStatus = 'offline';
                    
                    if (['online', 'active', '1', 'true'].includes(rawStatus)) {
                        normalizedStatus = 'online';
                    }

                    // 2. Create Option
                    $select.append(`<option value="${branch.branch_code}" data-status="${normalizedStatus}">${branch.branch_name}</option>`);
                });

                // 3. Auto-Select Current Context
                if (typeof ACTIVE_BRANCH_CONTEXT !== 'undefined' && ACTIVE_BRANCH_CONTEXT) {
                    $select.val(ACTIVE_BRANCH_CONTEXT);
                } else if (typeof myCurrentBranch !== 'undefined' && myCurrentBranch) {
                     $select.val(myCurrentBranch);
                }
            },
            error: function() {
                $('#targetBranchSelect').empty().append('<option>Error loading branches</option>');
            }
        });
        
        // B. Show Modal
        $('#previewModal').modal('hide'); 
        $('#salesAttributionModal').modal('show');
    
    } else {
        // ALL OTHER ROLES (Sales Manager, Admin, etc.)
        // Process immediately without modal
        processSaleSubmission(null);
    }
});

// 4. Modal Confirmation Handler (For Super Admin)
$('#confirmAttributionBtn').on('click', function() {
    var selectedBranch = $('#targetBranchSelect').val();
    var selectedBranchName = $('#targetBranchSelect option:selected').text();
    
    // --- VALIDATION START ---
    
    // 1. Context Resolution
// [FIX] Removed hardcoded fallback. Strictly use Session or Empty.
    let myCurrentBranch = "<?php echo $_SESSION['branch_code'] ?? ''; ?>"; 

    // 2. Validation: Offline Check (Strict for Remote Sales)
    let isRemoteSale = (selectedBranch !== myCurrentBranch);

// [MODIFIED] Frontend Offline Check REMOVED.
        // We now allow the request to proceed to the server (process_sale.php),
        // which will perform a Real-Time Heartbeat Check against the Cloud Database.
    // --- VALIDATION END ---

    processSaleSubmission(selectedBranch);
});


  });//end of ready state
</script>

     <script src="js/sync_system.js"></script>


     <script>
        // Helper to clear the POS screen after a background sale
function resetPosInterface() {
    // Clear Cart DOM
    $('#cart').empty();
    
    // Reset Counters
    $('#total_Amt').val('0.00');
    $('#totalAmount').text(CURRENCY + '0.00');
    $('#itemCount').text('0');
    
    // Reset Inputs
    $('#billToName').val('');
    $('#billToAddress').val('');
    $('#itemUniqueCode').val('').focus(); // Refocus for next scan
    
    // Re-enable Buttons
    $('#finalSubmitBtn').prop('disabled', false).text('Make Payment');
    $('#confirmAttributionBtn').prop('disabled', false).text('Confirm & Process Sale');
    
    // Close Modals
    $('#previewModal').modal('hide');
    $('#billingModal').modal('hide');
    
    // Reset internal total variable (if accessible, otherwise rely on UI state)
    // Note: Since 'totalAmount' is scoped inside document.ready, clearing the cart UI 
    // effectively resets the next calculation logic.
}
     </script>

</body>
</html>


<!-- Bootstrap Modal for Billing Details -->
<div class="modal fade dark-theme light-theme navbar-dark" id="billingModal" tabindex="-1" role="dialog" aria-labelledby="billingModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content dark-theme light-theme">
            <div class="modal-header">
                <h5 class="modal-title dark-theme light-theme navbar-dark" id="billingModalLabel">Billing Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Bill To Name -->
                <div class="form-group">
                    <label for="billToName">Bill To Name</label>
                    <input type="text" id="billToName" class="form-control" placeholder="Enter customer name" autocomplete="off">
                </div>

                <!-- Bill To Address -->
                <div class="form-group">
                    <label for="billToAddress">Bill To Address</label>
                    <textarea id="billToAddress" class="form-control" rows="3" placeholder="Enter customer address"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveBillingDetails">Save</button>
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="salesAttributionModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-network-wired mr-2"></i> Active Branch Context</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-lock mr-2"></i> You are recording a sale. The target branch is locked to your current active view.
                </div>
                <div class="form-group">
                    <label for="targetBranchSelect" class="font-weight-bold">Sale Assigned To:</label>
                    <select class="form-control form-control-lg bg-light" id="targetBranchSelect" disabled>
                        <option value="HEAD_OFFICE">Loading Context...</option>
                    </select>
                </div>
                <p class="text-muted small">
                    * Your User ID (Super Admin) will remain attached to this transaction for audit purposes.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmAttributionBtn">
                    Confirm & Process Sale <i class="fas fa-check ml-2"></i>
                </button>
            </div>
        </div>
    </div>
</div>