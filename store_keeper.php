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


// --- 2. BRANCH CONTEXT LOGIC (New) ---
// Capture branch info to keep navigation consistent across pages
$filter_branch_uuid = isset($_GET['branch_uuid']) ? $_GET['branch_uuid'] : null;
$filter_branch_name = isset($_GET['branch_name']) ? urldecode($_GET['branch_name']) : null;
$session_branch     = $_SESSION['branch_code']; // e.g., "HEAD_OFFICE", "BRANCH_001", etc.

// Define the Page Context Branch
$page_context_branch = $filter_branch_uuid ? $filter_branch_uuid : $session_branch;
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
require 'defined_global_settings.php'
?>

<?php
 // Include the database connection 
    include('connection.php');

// Fetch all categories from the item_categories table
$query = "SELECT category_name FROM item_categories ORDER BY category_name"; // Order by name
$result = $conn->query($query);

if (!$result) {
    die("Query failed: " . $conn->lastErrorMsg());
}

// Function to generate the HTML select dropdown
function generateCategoryDropdown($result, $selectedCategory = null) { // Added parameter
    $html = '<select name="categorySelect" class="form-control" id="categorySelect">';
    $html .= '<option value="" disabled selected>Select a Category</option>'; // Added a default option

    $currentGroup = '';
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $categoryName = $row['category_name'];
        // Get the first letter of the category for grouping
        $firstLetter = strtoupper(substr($categoryName, 0, 1));

        // Start a new optgroup if the first letter changes
        if ($firstLetter !== $currentGroup) {
            if ($currentGroup !== '') {
                $html .= '</optgroup>'; // Close previous optgroup
            }
            $html .= '<optgroup label="' . $firstLetter . '">';
            $currentGroup = $firstLetter;
        }
      $selected = ($categoryName == $selectedCategory) ? 'selected' : ''; //check if category is selected
    $html .= '<option value="' . htmlspecialchars($categoryName) . '" '.$selected.'>' . htmlspecialchars($categoryName) . '</option>';

    }

    if ($currentGroup !== '') {
        $html .= '</optgroup>'; // Close the last optgroup
    }

    $html .= '</select>';
    return $html;
}

// Generate the dropdown HTML
$categoryDropdown = generateCategoryDropdown($result); //call the function

// --- NEW SUPPLIER DROPDOWN LOGIC ---

// 1. Fetch all suppliers
// (Assuming table is 'suppliers' and column is 'company_name' from your addSupplierForm)
$querySuppliers = "SELECT company_name FROM suppliers ORDER BY company_name";
$resultSuppliers = $conn->query($querySuppliers);

if (!$resultSuppliers) {
    die("Query for suppliers failed: " . $conn->lastErrorMsg());
}

// 2. Function to generate the HTML select dropdown for suppliers
function generateSupplierDropdown($result, $selectedSupplier = null) {
    // Start with the input-group wrapper to match your form's style
    $html = '<div class="input-group">';
    $html .= '<div class="input-group-prepend">';
    $html .= '<span class="input-group-text"><i class="fas fa-truck text-dark"></i></span>';
    $html .= '</div>';
    
    // Add the select element
    $html .= '<select name="supplierInfo" class="form-control form-control-lg" id="supplierInfo">';
    $html .= '<option value="" selected>Select a Supplier (Optional)</option>'; // Default option

    $currentGroup = '';
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $supplierName = $row['company_name'];
        // Get the first letter for grouping (matching your category style)
        $firstLetter = strtoupper(substr($supplierName, 0, 1));

        // Start a new optgroup if the first letter changes
        if ($firstLetter !== $currentGroup) {
            if ($currentGroup !== '') {
                $html .= '</optgroup>'; // Close previous optgroup
            }
            $html .= '<optgroup label="' . $firstLetter . '">';
            $currentGroup = $firstLetter;
        }
      
        $selected = ($supplierName == $selectedSupplier) ? 'selected' : '';
        $html .= '<option value="' . htmlspecialchars($supplierName) . '" '.$selected.'>' . htmlspecialchars($supplierName) . '</option>';
    }

    if ($currentGroup !== '') {
        $html .= '</optgroup>'; // Close the last optgroup
    }

    $html .= '</select>';
    $html .= '</div>'; // Close the input-group wrapper
    return $html;
}

// 3. Generate the supplier dropdown HTML
$supplierDropdown = generateSupplierDropdown($resultSuppliers);

// --- END OF NEW SUPPLIER LOGIC ---

// Close the database connection
$conn->close();
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

  <!-- toast styling effect -->
<link rel="stylesheet" href="node_modules/toastify-js/src/toastify.css" />

<!-- highcharts lib -->
<script src="highchartsLib/code/highcharts.js"></script>
<script src="highchartsLib/code/modules/accessibility.js"></script>

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

<script src="js/alpine.min.js" defer></script> 
<script>
    // We use json_encode to prevent syntax errors (handling quotes/nulls automatically)
    var ACTIVE_BRANCH_CONTEXT = <?php echo json_encode($page_context_branch); ?>;
    var USER_SESSION_BRANCH   = <?php echo json_encode($_SESSION['branch_code']); ?>;

    // Debugging (Optional: View in console to verify)
    console.log("Context Loaded:", { active: ACTIVE_BRANCH_CONTEXT, session: USER_SESSION_BRANCH });
</script>




  <!-- custom js files --> 
  <script src="js/add_item.js"></script>  
  <script src="js/view_item.js"></script>
  <script src="js/allChart.js"></script> 
  <script src="js/upload_items_data.js"></script>
  
  
  <style>
  .accordion { 
    background-color: #f5f5f5; 
    color: #333;
    cursor: pointer;
    padding: 18px;
    width: 100%;
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

  .active:after {
    content: "\2212";
  }

  .panel {
    padding: 0 18px;
    background-color: white;
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.2s ease-out;
  }
</style>
<style>
    .form-container {
        display: none !important; /* Hide all forms by default */
    }
    .form-container.active {
        display: block !important; /* Ensure active forms are visible */ /* Show only the active form */
    }
    .btn.active {
        background-color: #007bff;
        color: white;
    }

    /* style for loader */
    #loader {
    display: none; /* Hide loader initially */
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    border: 4px solid #f3f3f3; /* Light grey */
    border-top: 4px solid #3498db; /* Blue */
    border-radius: 50%;
    width: 50px;
    height: 50px;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* General styling for the form container */
.form-container {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    background-color: #f7f7f7;
    padding: 20px;
    box-sizing: border-box;/* Ensure padding and borders don't increase size */
    width: 100%; /* Adjust container width to fill available space */
    max-width: 100%; /* Ensure the container stays within its parent */
    overflow: hidden; /* Prevent overflow */
    border: 1px solid #ddd; /* Optional: Add a light border for better visuals */
    border-radius: 8px; /* Optional: Slightly rounded corners for aesthetics */
}

/* If you want to allow scrolling for overflowing content */
.form-container.scrollable {
    overflow-y: auto; /* Enable vertical scrolling for long forms */
    max-height: 500px; /* Limit the maximum height for better UI control */
}
@media (max-width: 768px) {
    .form-container {
        padding: 10px;
        max-width: 100%;
        overflow-x: hidden; /* Prevent horizontal overflow */
    }
    .form-container form {
        width: 100%; /* Ensure form elements scale properly */
    }
    .form-container input,
    .form-container textarea,
    .form-container select {
        font-size: 14px; /* Adjust font size for smaller screens */
    }
}


/* Form styling */
.upload-form {
    background-color: #ffffff;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); 
    padding: 40px;
    width: 100%;
    max-width: 500px;
    text-align: center;
    box-sizing: border-box;
}

/* File input label styling */
.file-label {
    display: block;
    font-size: 16px;
    font-weight: 600;
    color: #333;
    margin-bottom: 10px;
    text-align: left;
}

/* File input styling */
.file-input {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 16px;
    background-color: #f9f9f9;
    margin-bottom: 20px;
    color: #333;
    transition: border-color 0.3s ease;
}

.file-input:focus {
    outline: none;
    border-color: #4caf50; /* Green on focus */
}

/* Submit button styling */
.submit-btn {
    background-color: #4caf50;
    color: #fff;
    font-size: 16px;
    font-weight: 600;
    padding: 12px 24px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition: background-color 0.3s ease, transform 0.2s ease;
}

.submit-btn:hover {
    background-color: #45a049;
    transform: translateY(-2px);
}

.submit-btn:active {
    background-color: #388e3c;
    transform: translateY(0);
}

/* Responsive design */
@media screen and (max-width: 600px) {
    .form-container {
        padding: 10px;
    }

    .upload-form {
        padding: 20px;
        width: 100%;
    }

    .file-label {
        font-size: 14px;
    }

    .file-input, .submit-btn {
        font-size: 14px;
        padding: 10px;
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

<style>
    /* Wrapper to ensure relative positioning context */
    .autocomplete-wrapper {
        position: relative;
    }

    /* The Dropdown List */
    #item-suggestions-list {
        position: absolute;
        top: 100%; /* Push exactly below input */
        left: 0;
        right: 0;
        z-index: 1050; /* Above other content */
        background: #fff;
        border: 1px solid #ced4da;
        border-top: none;
        border-radius: 0 0 8px 8px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        max-height: 250px;
        overflow-y: auto;
        display: none; /* Hidden by default */
    }

    /* Individual Items */
    .suggestion-item {
        padding: 12px 15px;
        font-size: 0.95rem;
        color: #333;
        cursor: pointer;
        border-bottom: 1px solid #f1f1f1;
        transition: all 0.2s ease;
    }

    .suggestion-item:last-child {
        border-bottom: none;
    }

    .suggestion-item:hover {
        background-color: #f8f9fa;
        color: #007bff;
        padding-left: 20px; /* Slide effect */
    }

    .suggestion-item i {
        margin-right: 10px;
        color: #adb5bd;
    }
</style>
<script>
document.addEventListener("DOMContentLoaded", function () {
    document.body.style.zoom = "65%"; // Adjust for more/less zoom
});
</script>
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
     $(document).on('input', '#itemUniqueNo', function(e){
                    e.preventDefault();
           let itemUniqueNo = $("#itemUniqueNo").val();
      $.ajax({
                type: "Post",
                url: "itemUniqueNo.php",
                data: {itemUniqueNo:itemUniqueNo},
                success:function(res){
                  if (res == 1){
                       $("#itemUniqueNo").val("");

              Swal.fire({
                      icon: 'info',
                      html: '<b style="color:red">Ooops! Item Bar Code Number Already Exists!</b>'
                      });


                  }
                  else if (res == 0){
               
                 //show nothing when item no does not exist!

                  }
                    
                }// end of success


        })//end of ajax

    })// end of on submit event


</script>

<script>
    $(document).ready(function () {
        $('#addEmployeeForm').on('submit', function (event) {
            event.preventDefault(); // Prevent default form submission

            // Serialize form data
            var formData = $(this).serialize();

            // Show loader (optional, add your loader element if needed)
            $('#loader').show();

            // Send AJAX POST request
            $.ajax({
                url: 'add_employee.php',  // Server-side script to handle the request
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function (response) {
                    $('#loader').hide(); // Hide loader

                    if (response.success) {
                        Toastify({
                            text: response.message,
                            duration: 5000,
                            gravity: 'top',
                            close: true,
                            style: {
                                background: 'linear-gradient(to right, #00b09b, #96c93d)',
                            },
                        }).showToast();

                        // Optionally reset the form
                        $('#addEmployeeForm')[0].reset();
                    } else {
                        Toastify({
                            text: response.message,
                            duration: 5000,
                            gravity: 'top',
                            close: true,
                            style: {
                                background: 'linear-gradient(to right, #ff5f6d, #ffc371)',
                            },
                        }).showToast();
                    }
                },
                error: function () {
                    $('#loader').hide();

                    Toastify({
                        text: 'An error occurred. Please try again later.',
                        duration: 5000,
                        gravity: 'top',
                        close: true,
                        style: {
                            background: 'linear-gradient(to right, #ff5f6d, #ffc371)',
                        },
                    }).showToast();
                },
            });
        });
    });
</script>

</head>
<body dark-theme light-theme>


      <!-- toast effect -->
<script src="node_modules/toastify-js/src/toastify.js"></script>

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

  <div class="container-scroller dark-theme light-theme">
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
        <li class="nav-item">Privilege Level: <?php echo $_SESSION['role']; ?></li>
          <li class="nav-item nav-profile dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown" id="profileDropdown">
              
              <span class="menu-title"><i class="icon-head menu-icon"></i>   <?php echo $_SESSION['username'];?> </span>
        
            </a>
            <div class="dropdown-menu dropdown-menu-right navbar-dropdown dark-theme light-theme navbar-dark" aria-labelledby="profileDropdown">

              <!-- <a class="dropdown-item" href="https://igs.ng/pos/logout.php">  -->
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
    <?php endif; ?>

        <?php if (!$is_visiting): ?>
         <li class="nav-item dark-theme light-theme navbar-dark">
            <a class="nav-link" href="<?php echo linkTo('manage_user.php'); ?>">
                <i class="icon-head menu-icon"></i><span class="menu-title">User Mgt. & App Setting</span>
            </a>
         </li>
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
    <li class="nav-item dark-theme light-theme navbar-dark">
        <a class="nav-link" href="<?php echo linkTo('manage_item.php'); ?>">
            <i class="ti-package menu-icon"></i>
            <span class="menu-title">Manage Items</span>
        </a>
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
        
      </nav>
      <!-- partial -->
      <div class="main-panel dark-theme light-theme navbar-dark">
        <div class="content-wrapper dark-theme light-theme navbar-dark">
          <div class="row dark-theme light-theme navbar-dark">
            <div class="col-md-6 grid-margin stretch-card dark-theme light-theme navbar-dark">
              <div class="card dark-theme light-theme navbar-dark">
                <div class="card-body dark-theme light-theme navbar-dark">
                  <h4 class="card-title dark-theme light-theme navbar-dark">Item Addition</h4>
                  <div class="container mt-5 dark-theme light-theme navbar-dark">

<!-- Toggle Buttons -->
<div class="d-flex justify-content-center mb-4 dark-theme light-theme navbar-dark">
<button id="toggleForm1" class="btn btn-outline-primary active dark-theme light-theme navbar-dark">Add Item Form</button>
<button id="toggleForm2" class="btn btn-outline-secondary dark-theme light-theme navbar-dark">Upload File Form</button>
<button id="toggleForm3" class="btn btn-outline-secondary dark-theme light-theme navbar-dark">Add Employees</button>
<button id="toggleViewEmployees" class="btn btn-outline-secondary dark-theme light-theme navbar-dark">View All Employees</button>
<?php if ($user_role === "Super Admin" || $user_role === "Admin"){ 
        echo ' <button id="itemCategory" class="btn btn-outline-secondary dark-theme light-theme navbar-dark">Add Item Category</button>';
        // NEW SUPPLIER BUTTON
        echo ' <button id="toggleAddSupplier" class="btn btn-outline-info dark-theme light-theme navbar-dark">Add Supplier</button>';
    } ?>
</div>

<div id="addSupplierForm" class="container card p-4 mb-4 shadow-sm" style="display:none; background-color: var(--card-bg);">
    <h4 class="mb-4 text-center">Add New Supplier</h4>
    <form id="supplierForm" class="dark-theme light-theme navbar-dark">
        <div class="form-row">
            <div class="form-group col-md-6">
                <label for="companyName">Company Name <span class="text-danger">*</span></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-industry text-dark"></i></span>
                    </div>
                    <input type="text" class="form-control" id="companyName" name="companyName" placeholder="e.g Coca Cola" required>
                </div>
            </div>
            <div class="form-group col-md-6">
                <label for="supplierDescription">Description (Optional)</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-file-alt text-dark"></i></span>
                    </div>
                    <textarea class="form-control" id="supplierDescription" name="supplierDescription" rows="1" placeholder="Brief description of the supplier"></textarea>
                </div>
            </div>
        </div>
        <button type="submit" class="btn btn-success mt-3 w-100"><i class="fas fa-save text-dark"></i> Save Supplier</button>
    </form>
</div>

                  <!-- loader to show here -->
                  <div id="loader"></div>

                   <!-- end of loader -->



    <!-- Add Item Form -->
    <div id="form1" class="form-container scrollable dark-theme light-theme navbar-dark">
<form id="addItemForm" class="">

    <div class="form-group">
        <label for="itemUniqueNo" class="col-form-label">Item Unique Number:</label>
        <div class="input-group">
            <div class="input-group-prepend">
                <span class="input-group-text"><i class="fas fa-barcode text-danger"></i></span>
            </div>
            <input type="text" class="form-control form-control-lg" id="itemUniqueNo" name="itemUniqueNo" pattern="[0-9]{1,15}" maxlength="15" placeholder="Enter Item Bar Code Number e.g 6151100050564" autocomplete="off" required>
        </div>
        <small id="itemUniqueNoHelp" class="form-text text-muted">
            Enter Item Bar Code Number (usually 13 digits).
        </small>
    </div>

<div class="form-group autocomplete-wrapper"> <label for="itemName" class="col-form-label">Item Name:</label>
    <div class="input-group">
        <div class="input-group-prepend">
            <span class="input-group-text"><i class="fas fa-box-open text-dark"></i></span>
        </div>
        <input type="text" class="form-control form-control-lg" id="itemName" name="itemName" placeholder="Enter item name" autocomplete="off" required>
    </div>
    
    <div id="item-suggestions-list"></div>

    <small id="itemNameHelp" class="form-text text-muted">
        Enter the name of the item (e.g., "Laptop", "T-Shirt").
    </small>
</div>

    <div class="form-group">
        <label for="itemDescription" class="col-form-label">Item Description:</label>
        <div class="input-group">
            <div class="input-group-prepend">
                <span class="input-group-text"><i class="fas fa-file-text text-dark"></i></span>
            </div>
            <textarea class="form-control form-control-lg" id="itemDescription" name="itemDescription" rows="3" placeholder="Enter a brief description"></textarea>
        </div>
        <small id="itemDescriptionHelp" class="form-text text-muted">
            Describe the item (optional).
        </small>
    </div>

<div class="form-group">
        <label for="supplierInfo" class="col-form-label">Supplier Info:</label>
        
        <?php echo $supplierDropdown; ?> 

        <small id="supplierInfoHelp" class="form-text text-muted">
            Select a supplier from the list (optional).
        </small>
    </div>

    <div class="form-group">
        <label for="invoiceNumber" class="col-form-label">Invoice Number:</label>
        <div class="input-group">
            <div class="input-group-prepend">
                <span class="input-group-text"><i class="fas fa-file-invoice text-dark"></i></span>
            </div>
            <input type="text" class="form-control form-control-lg" id="invoiceNumber" name="invoiceNumber" placeholder="Enter invoice number" autocomplete="off">
        </div>
        <small id="invoiceNumberHelp" class="form-text text-muted">
            Enter the invoice number for this purchase (optional).
        </small>
    </div>

    <div class="form-group">
        <label for="datePurchased" class="col-form-label">Date Purchased:</label>
        <div class="input-group">
            <div class="input-group-prepend">
                <span class="input-group-text"><i class="fas fa-calendar-day text-dark"></i></span>
            </div>
            <input type="date" id="datePurchased" name="datePurchased" class="form-control form-control-lg">
        </div>
        <small id="datePurchasedHelp" class="form-text text-muted">
            Enter the date the item was purchased (optional).
        </small>
    </div>

    <div class="form-group">
        <label for="itemPrice" class="col-form-label">Price:</label>
        <div class="input-group">
            <div class="input-group-prepend">
                <span class="input-group-text"><i class="fas fa-dollar-sign text-dark"></i></span>
            </div>
            <input type="number" class="form-control form-control-lg" id="itemPrice" name="itemPrice" min="0" step="0.01" placeholder="Enter price" required>
        </div>
        <small id="itemPriceHelp" class="form-text text-muted">
            Enter the price of the item (e.g., 199.99).
        </small>
    </div>

    <div class="form-group">
        <label for="wholesale_prc" class="col-form-label">Wholesale Price:</label>
        <div class="input-group">
            <div class="input-group-prepend">
                <span class="input-group-text"><i class="fas fa-tags text-dark"></i></span>
            </div>
            <input type="number" class="form-control form-control-lg" id="wholesale_prc" name="wholesale_prc" min="0" step="0.01" placeholder="Enter wholesale price">
        </div>
        <small id="wholesalePriceHelp" class="form-text text-muted">
            Enter the wholesale price (optional).
        </small>
    </div>

    <div class="form-group">
        <label for="retail_prc" class="col-form-label">Retail Price:</label>
        <div class="input-group">
            <div class="input-group-prepend">
                <span class="input-group-text"><i class="fas fa-store text-dark"></i></span>
            </div>
            <input type="number" class="form-control form-control-lg" id="retail_prc" name="retail_prc" min="0" step="0.01" placeholder="Enter retail price">
        </div>
        <small id="retailPriceHelp" class="form-text text-muted">
            Enter the retail price (optional).
        </small>
    </div>

    <div class="form-group">
        <label for="categorySelect" class="col-form-label">Item Category:</label>
        <?php echo $categoryDropdown; ?>
        <small id="categorySelectHelp" class="form-text text-muted">
            Select the category for this item.
        </small>
    </div>

    

    <div class="form-group">
        <label for="itemQuantity" class="col-form-label">Quantity:</label>
        <div class="input-group">
            <div class="input-group-prepend">
                <span class="input-group-text"><i class="fas fa-cubes text-dark"></i></span>
            </div>
            <input type="number" class="form-control form-control-lg" id="itemQuantity" name="itemQuantity" min="0" placeholder="Enter quantity" required>
        </div>
        <small id="itemQuantityHelp" class="form-text text-muted">
            Enter the quantity of the item.
        </small>
    </div>



    <div class="form-group">
        <label for="expirationDate" class="col-form-label">Item Expiration Date:</label>
        <div class="input-group">
            <div class="input-group-prepend">
                <span class="input-group-text"><i class="fas fa-calendar-alt text-dark"></i></span>
            </div>
            <input type="date" id="expirationDate" name="expirationDate" class="form-control form-control-lg" required>
        </div>
        <small id="expirationDateHelp" class="form-text text-muted">
            Enter the expiration date of the item.
        </small>
    </div>

    <button type="submit" class="btn btn-primary btn-lg btn-block mt-4">
        <i class="fas fa-plus mr-2 text-dark"></i> Add Item
    </button>
</form>


    </div>

    <!-- Upload File Form -->
    <div id="form2" class="form-container d-none dark-theme light-theme navbar-dark">
        <!-- progress bar section --> 

        <div id="uploadProgress" class="progress my-3" style="display: none; height: 25px;">
  <div id="uploadBar" class="progress-bar progress-bar-striped progress-bar-animated bg-info"
       role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
       Uploading...
  </div>
</div>

         <!-- progress bar section ends -->
<form id="uploadFileForm" enctype="multipart/form-data">
    <div class="form-group">
        <label for="excelFile" class="col-form-label">Choose an Excel file</label>
        <div class="input-group">
            <div class="input-group-prepend">
                <span class="input-group-text"><i class="fas fa-file-excel text-dark"></i></span>
            </div>
            <input type="file" class="form-control form-control-lg" id="excelFile" name="excelFile" accept=".xls,.xlsx" >
        </div>
        <small id="fileHelp" class="form-text text-muted">
            Please select an Excel file (.xls or .xlsx). 
        </small>
    </div>
    <button type="submit" class="btn btn-primary btn-lg btn-block mt-4">
        <i class="fas fa-upload mr-2 text-dark"></i> Upload
    </button>
</form>


   
</div>

 <!-- add Employees form -->
 <div id="form3" class="form-container d-none scrollable dark-theme light-theme navbar-dark">
    <h3 class="text-center mb-4">Add Employee</h3>
<form id="addEmployeeForm">
    <div class="form-group">
        <label for="firstName" class="col-form-label">First Name:</label>
        <div class="input-group">
            <div class="input-group-prepend">
                <span class="input-group-text"><i class="fas fa-user text-dark"></i></span>
            </div>
            <input type="text" class="form-control form-control-lg" id="firstName" name="First_Name" placeholder="Enter first name" required>
        </div>
        <small id="firstNameHelp" class="form-text text-muted">
            Enter the employee's first name.
        </small>
    </div>

    <div class="form-group">
        <label for="lastName" class="col-form-label">Last Name:</label>
        <div class="input-group">
            <div class="input-group-prepend">
                <span class="input-group-text"><i class="fas fa-user text-dark"></i></span>
            </div>
            <input type="text" class="form-control form-control-lg" id="lastName" name="Last_Name" placeholder="Enter last name" required>
        </div>
        <small id="lastNameHelp" class="form-text text-muted">
            Enter the employee's last name.
        </small>
    </div>

    <div class="form-group">
        <label for="email" class="col-form-label">Email:</label>
        <div class="input-group">
            <div class="input-group-prepend">
                <span class="input-group-text"><i class="fas fa-envelope text-dark"></i></span>
            </div>
            <input type="email" class="form-control form-control-lg" id="email" name="Email" placeholder="Enter email">
        </div>
         <small id="emailHelp" class="form-text text-muted">
            Enter a valid email address.
        </small>
    </div>

    <div class="form-group">
        <label for="phoneNumber" class="col-form-label">Phone Number:</label>
        <div class="input-group">
            <div class="input-group-prepend">
                <span class="input-group-text"><i class="fas fa-phone text-dark"></i></span>
            </div>
            <input type="text" class="form-control form-control-lg" id="phoneNumber" name="Phone_Number" placeholder="Enter phone number">
        </div>
        <small id="phoneNumberHelp" class="form-text text-muted">
            Enter the employee's phone number.
        </small>
    </div>

    <div class="form-group">
        <label for="address" class="col-form-label">Address:</label>
        <div class="input-group">
            <div class="input-group-prepend">
                <span class="input-group-text"><i class="fas fa-map-marker-alt text-dark"></i></span>
            </div>
            <textarea class="form-control form-control-lg" id="address" name="Address" placeholder="Enter address"></textarea>
        </div>
        <small id="addressHelp" class="form-text text-muted">
             Enter the employee's address.
        </small>
    </div>

    <div class="form-group">
        <label for="dob" class="col-form-label">Date of Birth:</label>
        <div class="input-group">
            <div class="input-group-prepend">
                <span class="input-group-text"><i class="fas fa-calendar-alt text-dark"></i></span>
            </div>
            <input type="date" class="form-control form-control-lg" id="dob" name="Date_Of_Birth" required>
        </div>
        <small id="dobHelp" class="form-text text-muted">
            Enter the employee's date of birth.
        </small>
    </div>

    <div class="form-group">
        <label for="dateOfHire" class="col-form-label">Date of Hire:</label>
        <div class="input-group">
            <div class="input-group-prepend">
                <span class="input-group-text"><i class="fas fa-calendar-plus text-dark"></i></span>
            </div>
            <input type="date" class="form-control form-control-lg" id="dateOfHire" name="Date_Of_Hire" required>
        </div>
        <small id="dateOfHireHelp" class="form-text text-muted">
            Enter the employee's date of hire.
        </small>
    </div>

    <div class="form-group">
        <label for="jobTitle" class="col-form-label">Job Title:</label>
        <div class="input-group">
            <div class="input-group-prepend">
                <span class="input-group-text"><i class="fas fa-briefcase text-dark"></i></span>
            </div>
            <input type="text" class="form-control form-control-lg" id="jobTitle" name="Job_Title" placeholder="Enter job title" required>
        </div>
        <small id="jobTitleHelp" class="form-text text-muted">
            Enter the employee's job title.
        </small>
    </div>

    <div class="form-group">
        <label for="department" class="col-form-label">Department:</label>
        <div class="input-group">
            <div class="input-group-prepend">
                <span class="input-group-text"><i class="fas fa-building text-dark"></i></span>
            </div>
            <input type="text" class="form-control form-control-lg" id="department" name="Department" placeholder="Enter department">
        </div>
        <small id="departmentHelp" class="form-text text-muted">
            Enter the employee's department.
        </small>
    </div>

    <div class="form-group">
        <label for="status" class="col-form-label">Employee Status:</label>
        <div class="input-group">
            <div class="input-group-prepend">
                <span class="input-group-text"><i class="fas fa-user-tag text-dark"></i></span>
            </div>
            <select class="form-control form-control-lg" id="status" name="Employee_Status" required>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
                <option value="Terminated">Terminated</option>
            </select>
        </div>
         <small id="statusHelp" class="form-text text-muted">
            Select the employee's status.
        </small>
    </div>

    <button type="submit" class="btn btn-primary btn-lg btn-block mt-4">
        <i class="fas fa-plus mr-2 text-dark"></i> Add Employee
    </button>
</form>

</div>

     <!-- end of add Employees form -->

     <!-- view employees records -->
     <div id="viewEmployeesContainer" class="form-container d-none dark-theme light-theme navbar-dark">
    <h3 class="text-center dark-theme light-theme navbar-dark">All Employees</h3>
    <div class="table-responsive dark-theme light-theme navbar-dark">
            <!-- Pagination Controls -->
    <div id="paginationControls" class="d-flex justify-content-center mt-3 dark-theme light-theme navbar-dark"></div>
    
        <table id="employeeTable" class="table table-bordered dark-theme light-theme navbar-dark">
            <thead>
                <tr>
                    <th>#</th>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Department</th>
                    <th>Job Title</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <!-- Data will be dynamically populated -->
            </tbody>
        </table>
    </div>

</div>
      <!-- end of view employees -->

   <!-- start of add item category -->
<div id="viewItemCategory" class="form-container d-none dark-theme light-theme navbar-dark">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <div class="card shadow-lg rounded-lg mt-5">
                    <div class="card-header bg-success text-white rounded-top-lg py-3">
                        <h3 class="card-title mb-0 text-center text-light">Add New Item Category</h3>
                    </div>
                    <div class="card-body py-4 px-3 px-md-6">
                        <form id="addItemCategoryForm" class="form-horizontal">
                            <div class="form-group">
                                <label for="categoryName" class="col-form-label">Category Name:</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-tags text-dark"></i></span>
                                    </div> 
                                    <input type="text" class="form-control form-control-lg" id="categoryName" name="categoryName" placeholder="Enter category name" required>
                                </div>
                                <small id="categoryNameHelp" class="form-text text-muted">
                                    Please enter a unique category name (e.g., "Electronics", "Clothing").
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="categoryDescription" class="col-form-label">Category Description:</label>
                                 <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-file-text text-dark"></i></span>
                                    </div>
                                    <textarea class="form-control form-control-lg" id="categoryDescription" name="categoryDescription" rows="3" placeholder="Enter a brief description"></textarea>
                                 </div>
                                <small id="categoryDescriptionHelp" class="form-text text-muted">
                                    Describe the category (optional).
                                </small>
                            </div>

                            <div class="form-group mt-4">
                                <button type="submit" class="btn btn-primary btn-lg btn-block">
                                    <i class="fas fa-plus mr-2 text-dark"></i> Add Category
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer text-muted text-center py-2 rounded-bottom-lg">
                        Ensure all fields are filled correctly.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // --- Form Submission ---
    $('#addItemCategoryForm').submit(function(event) {
        event.preventDefault(); // Prevent default form submission

        // Get form values
        const categoryName = $('#categoryName').val().trim();
        const categoryDescription = $('#categoryDescription').val().trim();

        // --- Validation ---
        let isValid = true;
        if (categoryName === '') {
            $('#categoryName').addClass('is-invalid');
            $('#categoryNameHelp').text('Please enter a category name.');
            isValid = false;
        } else {
            $('#categoryName').removeClass('is-invalid');
            $('#categoryNameHelp').text('Please enter a unique category name (e.g., "Electronics", "Clothing").');
        }

        if (!isValid) {
            return; // Stop if form is invalid
        }

        // --- AJAX Check for Duplicate Category ---
        $.ajax({
            url: 'check_category_name.php', // Replace with your server-side script
            method: 'POST',
            data: { categoryName: categoryName },
            dataType: 'json', // Expect JSON response
            success: function(response) {
                if (response.exists) {
                    // Category name already exists
                    $('#categoryName').addClass('is-invalid');
                    $('#categoryNameHelp').text('Category name already exists. Please choose a different name.');
                    Swal.fire({
                        title: 'Error!',
                        text: 'Category name already exists. Please enter a unique category name.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                } else {
                    // Category name is unique, proceed with form submission (or further processing)
                    $('#categoryName').removeClass('is-invalid');
                    $('#categoryNameHelp').text('Please enter a unique category name (e.g., "Electronics", "Clothing").');

                    // --- AJAX Submission (Conceptual) ---
                    // In a real application, you would send the data to your server using AJAX
                    // Here's a conceptual example using jQuery's $.ajax:

                    $.ajax({
                        url: 'add_category.php', // Replace with your server-side script
                        method: 'POST',
                        data: {
                            categoryName: categoryName,
                            categoryDescription: categoryDescription
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    title: 'Success!',
                                    text: response.message,
                                    icon: 'success',
                                    confirmButtonText: 'OK'
                                }).then(() => {
                                    $('#addItemCategoryForm')[0].reset();
                                    $('.form-container').addClass('d-none');
                                    $('#toggleForm1').removeClass('active');
                                    $('#toggleForm1').click();
                                });
                            } else {
                                Swal.fire({
                                    title: 'Error!',
                                    text: response.message,
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            Swal.fire({
                                title: 'Error!',
                                text: 'An error occurred: ' + error,
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    });
                }
            },
            error: function(xhr, status, error) {
                Swal.fire({
                    title: 'Error!',
                    text: 'Error checking category name: ' + error,
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        });
    });
});
</script>

   <!-- end of add item category -->
      
</div>

                </div>
              </div>
            </div>
            <div class="col-md-6 grid-margin stretch-card dark-theme light-theme navbar-dark">
              <div class="card dark-theme light-theme navbar-dark">
                  <div class="card-body dark-theme light-theme navbar-dark">
                  <h4 class="card-title dark-theme light-theme navbar-dark">Charts</h4>
                    <!-- Add the code to display the charts here -->
                    <!--  -->
                
                    
                    
                    <button class="accordion dark-theme light-theme navbar-dark">Inventory Overview</button>
                    <div class="panel dark-theme light-theme navbar-dark">
                     <div id="inventoryOverviewChartContainer" class="dark-theme light-theme navbar-dark"></div>
                    </div>
                      <hr>
                      <button class="accordion dark-theme light-theme navbar-dark">Sales Trend</button>
                      <div class="panel dark-theme light-theme navbar-dark">
                      <div id="salesTrendChartContainer" class="dark-theme light-theme navbar-dark"></div>
                      </div>
                      <hr>
                      <button class="accordion dark-theme light-theme navbar-dark">Top Selling Items</button>
                      <div class="panel dark-theme light-theme navbar-dark">
                      <div id="topSellingItemsChartContainer" class="dark-theme light-theme navbar-dark"></div>
                      </div>
                      <hr>
                      <button class="accordion dark-theme light-theme navbar-dark">Inventory Value</button>
                      <div class="panel dark-theme light-theme navbar-dark">
                      <div id="inventoryValueChartContainer" class="dark-theme light-theme navbar-dark"></div>
                      </div>
                      <hr>
                      <button class="accordion dark-theme light-theme navbar-dark">Item Category Breakdown</button>
                      <div class="panel dark-theme light-theme navbar-dark">
                      <div id="itemCategoryBreakdownChartContainer" class="dark-theme light-theme navbar-dark"></div>
                      </div>
                   
                    
                     <!--  -->



<script>
  const accordions = document.querySelectorAll('.accordion');

  accordions.forEach(accordion => {
    accordion.addEventListener('click', () => {
      accordion.classList.toggle('active');
      const panel = accordion.nextElementSibling;
      if (panel.style.maxHeight) {
        panel.style.maxHeight = null;
      } else {
        panel.style.maxHeight = panel.scrollHeight + 'px';
      }
    });
  });
</script>

<script>
    $(document).ready(function () {
    // Default: Show the first form
    $('#form1').removeClass('d-none').addClass('active');

    // Toggle between forms
    $('#toggleForm1').click(function () {
        $('#form1').removeClass('d-none').addClass('active');
        $('#form2, #form3').addClass('d-none').removeClass('active');
        $('#toggleForm1').addClass('active');
        $('#toggleForm2, #toggleForm3').removeClass('active');
    });

    $('#toggleForm2').click(function () {
        $('#form2').removeClass('d-none').addClass('active');
        $('#form1, #form3').addClass('d-none').removeClass('active');
        $('#toggleForm2').addClass('active');
        $('#toggleForm1, #toggleForm3').removeClass('active');
    });

    $('#toggleForm3').click(function () {
        $('#form3').removeClass('d-none').addClass('active');
        $('#form1, #form2').addClass('d-none').removeClass('active');
        $('#toggleForm3').addClass('active');
        $('#toggleForm1, #toggleForm2').removeClass('active');
    });
});

</script>


                  </div>
                </div>
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


    <!-- The Modal -->
     <script>
    const USER_ROLE_JS = "<?php echo $_SESSION['role'] ?? ''; ?>";
</script>

<div class="modal fade" id="itemBranchModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-network-wired mr-2"></i> Inventory Location Context</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-lock mr-2"></i> New items will be added to the inventory of the currently active branch.
                </div>
                <div class="form-group">
                    <label for="targetBranchSelect" class="font-weight-bold">Assign Item To:</label>
                    <select class="form-control form-control-lg bg-light" id="targetBranchSelect" disabled>
                        <option value="HEAD_OFFICE">Loading Context...</option>
                    </select>
                </div>
                <p class="text-muted small">
                    * Remote items will be saved directly to the Cloud Server.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmItemAddBtn">
                    Confirm & Add Item <i class="fas fa-check ml-2"></i>
                </button>
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="bulkUploadBranchModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-file-excel mr-2"></i> Bulk Upload Context</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> You are performing a Bulk Upload. 
                    <br>
                    New items will be added to the inventory of the currently active branch.
                </div>
                <div class="form-group">
                    <label for="bulkTargetBranchSelect" class="font-weight-bold">Upload Items To:</label>
                    <select class="form-control form-control-lg" id="bulkTargetBranchSelect" disabled>
                        <option value="HEAD_OFFICE">HEAD OFFICE (Default)</option>
                    </select>
                </div>
                <p class="text-muted small">
                    * <strong>Remote Branch:</strong> Items saved DIRECTLY to Cloud (No Local Copy).<br>
                    * <strong>Local Branch:</strong> Items saved Locally + Synced to Cloud.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirmBulkUploadBtn">
                    Confirm & Upload <i class="fas fa-upload ml-2"></i>
                </button>
            </div>
        </div>
    </div>
</div>
    
    </body>
    </html>

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
  <!-- End custom js for this page-->

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

$(document).ready(function () {
    // Function to handle toggling
    function toggleForm(activeForm, activeButton) {
        // Hide all forms
        $('.form-container').addClass('d-none').removeClass('active');
        // Show the active form
        $(`#${activeForm}`).removeClass('d-none').addClass('active');

        // Remove "active" class from all buttons
        $('.btn-outline-primary, .btn-outline-secondary').removeClass('active');
        // Add "active" class to the selected button
        $(`#${activeButton}`).addClass('active');
    }

    // Handle "Add Item Form" toggle
    $('#toggleForm1').click(function () {
        toggleForm('form1', 'toggleForm1');
    });

    // Handle "Upload File Form" toggle
    $('#toggleForm2').click(function () {
        toggleForm('form2', 'toggleForm2');
    });

    // Handle "Add Employees Form" toggle
    $('#toggleForm3').click(function () {
        toggleForm('form3', 'toggleForm3');
    });

    // Handle "View All Employees" toggle
    $('#toggleViewEmployees').click(function () {
        toggleForm('viewEmployeesContainer', 'toggleViewEmployees');
        fetchEmployees(); // Call function to fetch employees
    });

    // Handle "Add Item Category" toggle
    $('#itemCategory').click(function () {
        toggleForm('viewItemCategory', 'itemCategory');
    });

    // Constants for pagination
    const rowsPerPage = 5;
    let currentPage = 1;


    // Fetch employees from server
    let lastEmployeeCount = 0; // Tracks the last known count of employees

// Fetch employees from server
function fetchEmployees() {
    $.ajax({
        url: 'getEmployees.php',//php endpoint
        method: 'GET',
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                const currentCount = response.data.length;

                if (currentCount === 0) {
                    // No records in the database
                    renderTable([]); // Clear the table
                    showToast('No employees found in the database.', 'info');
                } else if (currentCount > lastEmployeeCount) {
                    // New records have been added
                    const newRecords = currentCount - lastEmployeeCount;
                    renderTable(response.data); // Update the table
                    showToast(`${newRecords} new employee(s) added!`, 'success');
                } else if (currentCount === lastEmployeeCount) {
                    // No new records; suppress messages
                    renderTable(response.data); // Update the table silently
                }

                // Update the last known count
                lastEmployeeCount = currentCount;
            } else {
                showToast('Failed to fetch employees: ' + response.message, 'error');
            }
        },
        error: function () {
            showToast('An error occurred while fetching employees.', 'error');
        }
    });
}

// Render table with pagination
function renderTable(data) {
    const tableBody = $('#employeeTable tbody');
    tableBody.empty();

    if (data.length === 0) {
        tableBody.append('<tr><td colspan="9" class="text-center">No data available.</td></tr>');
        return;
    }

    // Calculate pagination
    const totalPages = Math.ceil(data.length / rowsPerPage);
    const startIndex = (currentPage - 1) * rowsPerPage;
    const endIndex = Math.min(startIndex + rowsPerPage, data.length);

    // Populate table rows
    for (let i = startIndex; i < endIndex; i++) {
        const employee = data[i];
        const row = `
            <tr>
                <td>${i + 1}</td>
                <td>${employee.First_Name}</td>
                <td>${employee.Last_Name}</td>
                <td>${employee.Email}</td>
                <td>${employee.Phone_Number || 'N/A'}</td>
                <td>${employee.Department || 'N/A'}</td>
                <td>${employee.Job_Title || 'N/A'}</td>
                <td>${employee.Employee_Status}</td>
                <td>
                    <button class="btn btn-sm btn-warning edit-btn" data-id="${employee.Employee_ID}">Edit</button>
                    <button class="btn btn-sm btn-danger delete-btn" data-id="${employee.Employee_ID}">Delete</button>
                </td>
            </tr>
        `;
        tableBody.append(row);
    }

    // Render pagination controls
    renderPaginationControls(totalPages);
}

// Toast notification function
function showToast(message, type) {
    Toastify({
        text: message,
        duration: 3000,
        gravity: 'top',
        position: 'right',
        backgroundColor: type === 'success' ? 'green' : type === 'error' ? 'red' : 'blue',
    }).showToast();
}

    // Render pagination controls with ellipsis
    function renderPaginationControls(totalPages) {
        const paginationControls = $('#paginationControls');
        paginationControls.empty();

        // Helper to create buttons
        function createButton(page, isActive = false) {
            return `<button class="btn btn-sm ${isActive ? 'btn-primary' : 'btn-light'} pagination-btn" data-page="${page}">${page}</button>`;
        }

        // Add "First" and "Previous" buttons
        if (currentPage > 1) {
            paginationControls.append(createButton(1));
            paginationControls.append(`<button class="btn btn-sm btn-light pagination-btn" data-page="${currentPage - 1}">&laquo;</button>`);
        }

        // Add ellipsis pagination logic
        for (let i = 1; i <= totalPages; i++) {
            if (
                i === 1 || 
                i === totalPages || 
                (i >= currentPage - 1 && i <= currentPage + 1)
            ) {
                paginationControls.append(createButton(i, i === currentPage));
            } else if (
                i === currentPage - 2 || 
                i === currentPage + 2
            ) {
                paginationControls.append('<span class="mx-1">...</span>');
            }
        }

        // Add "Next" and "Last" buttons
        if (currentPage < totalPages) {
            paginationControls.append(`<button class="btn btn-sm btn-light pagination-btn" data-page="${currentPage + 1}">&raquo;</button>`);
            paginationControls.append(createButton(totalPages));
        }

        // Handle page click
        $('.pagination-btn').click(function () {
            currentPage = parseInt($(this).data('page'));
            fetchEmployees();
        });
    }

    $(document).on('click', '.edit-btn', function () {
    const employeeId = $(this).data('id');

    // Fetch current employee details
    $.ajax({
        url: `getEmployeeDetails.php?id=${employeeId}`, //php endpoint
        method: 'GET',
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                // Populate the modal form with employee data
                $('#editEmployeeId').val(response.data.Employee_ID);
                $('#editFirstName').val(response.data.First_Name);
                $('#editLastName').val(response.data.Last_Name);
                $('#editEmail').val(response.data.Email);
                $('#editPhoneNumber').val(response.data.Phone_Number);
                $('#editDepartment').val(response.data.Department);
                $('#editJobTitle').val(response.data.Job_Title);
                $('#statusEdit').val(response.data.Employee_Status);

                // Show the modal
                $('#editEmployeeModal').modal('show');
            } else {
                Swal.fire('Error!', 'Failed to fetch employee details: ' + response.message, 'error');
            }
        },
        error: function () {
            Swal.fire('Error!', 'An error occurred while fetching employee details.', 'error');
        }
    });
});

// Submit updated employee data
$('#editEmployeeForm').submit(function (e) {
    e.preventDefault(); // Prevent default form submission

    // Serialize form data
    const formData = $(this).serialize();

    // Send updated data to the server
    $.ajax({
        url: 'updateEmployee.php', 
        method: 'POST',
        data: formData,
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                let changesList = '';

                // Check if there are changes and format them as a list
                if (response.changes && response.changes.length > 0) {
                    changesList = '<ul>';
                    response.changes.forEach(change => {
                        changesList += `<li>${change}</li>`;
                    });
                    changesList += '</ul>';
                }

                // Show SweetAlert with detailed change summary
                Swal.fire({
                    title: 'Success!',
                    html: `Employee details updated successfully.<br><br><strong>Changes:</strong> ${changesList}`,
                    icon: 'success'
                });

                $('#editEmployeeModal').modal('hide');
                fetchEmployees(); // Refresh employee list
            } else {
                Swal.fire('Error!', response.message || 'Failed to update employee details.', 'error');
            }
        },
        error: function () {
            Swal.fire('Error!', 'An error occurred while updating employee details.', 'error');
        }
    });
});

    // Delete employee
    $(document).on('click', '.delete-btn', function () {
        const employeeId = $(this).data('id');
        Swal.fire({
            title: 'Are you sure?',
            text: 'This action cannot be undone!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `deleteEmployee.php?id=${employeeId}`, //php endpoint
                    method: 'GET',
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            Swal.fire('Deleted!', 'Employee has been deleted.', 'success');
                            fetchEmployees();
                        } else {
                            Swal.fire('Error!', 'Failed to delete employee: ' + response.message, 'error');
                        }
                    },
                    error: function () {
                        Swal.fire('Error!', 'An error occurred while deleting the employee.', 'error');
                    }
                });
            }
        });
    });

    // Show toast notifications
    function showToast(message, type) {
        Toastify({
            text: message,
            duration: 3000,
            close: true,
            gravity: 'top',
            position: 'right',
            backgroundColor: type === 'success' ? 'green' : 'red',
        }).showToast();
    }
});
</script>

<script>
// JQUERY script for Supplier Form and AJAX Submission
$(document).ready(function() {
    // 1. Toggle "Add Supplier" Form (Fade In/Out)
    $('#toggleAddSupplier').click(function() {
        $('#addSupplierForm').fadeToggle(300);

        // Optional: Change button style to indicate the form is active
        if ($('#addSupplierForm').is(':visible')) {
            $(this).removeClass('btn-outline-info').addClass('btn-info');
        } else {
            $(this).removeClass('btn-info').addClass('btn-outline-info');
        }
    });

    // 2. JQUERY AJAX script for New Supplier Submission
    $('#supplierForm').submit(function(e) {
        e.preventDefault(); // Stop the default form submission

        let formData = $(this).serialize(); // Gather all form data

        $.ajax({
            url: 'add_supplier_endpoint.php', // ***NEW PHP ENDPOINT FILE REQUIRED***
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    // Use Sweet Alert 2 for a professional notification
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: response.message
                    });
                    $('#supplierForm')[0].reset(); // Clear the form
                    $('#toggleAddSupplier').click(); // Hide the form
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Oops...',
                        text: response.message
                    });
                }
            },
            error: function(xhr, status, error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Network Error',
                    text: 'An error occurred while connecting to the server.'
                });
            }
        });
    });
});

// Function to set up JQuery UI Autocomplete
function setupAutocomplete(inputId, endpointUrl) {
    $('#' + inputId).autocomplete({
        source: function(request, response) {
            $.ajax({
                url: endpointUrl, // The PHP file that fetches suggestions
                dataType: "json",
                data: {
                    term: request.term // Send current input text to the backend
                },
                success: function(data) {
                    response(data);
                }
            });
        },
        minLength: 2, // Start suggesting after 2 characters
        delay: 500 // Wait half a second after user stops typing
    });
}

/* $(document).ready(function() {
 
   // Apply Autocomplete to Item Name
    setupAutocomplete('itemName', 'fetch_items.php'); // ***NEW PHP ENDPOINT FILE REQUIRED***
}); */
  </script>
  <script>
$(document).ready(function() {
    let debounceTimer;
    const $input = $('#itemName');
    const $list = $('#item-suggestions-list');

    // 1. Resolve Context (Supports Super Admin URL params)
    function getContextBranch() {
        const urlParams = new URLSearchParams(window.location.search);
        // Returns URL branch or defaults (PHP handles the rest)
        return urlParams.get('branch_uuid') || urlParams.get('branch_code') || '';
    }

    // 2. Input Listener (Debounced)
    $input.on('keyup', function() {
        clearTimeout(debounceTimer);
        const term = $(this).val().trim();

        if (term.length < 1) {
            $list.hide().empty();
            return;
        }

        debounceTimer = setTimeout(() => {
            $.ajax({
                url: 'fetch_items.php',
                type: 'GET',
                dataType: 'json',
                data: { 
                    term: term,
                    branch_code: getContextBranch() // Pass Context
                },
                success: function(data) {
                    $list.empty();
                    if (data.length > 0) {
                        data.forEach(item => {
                            // Sleek Item Markup
                            const itemHtml = `
                                <div class="suggestion-item">
                                    <i class="fas fa-search"></i> ${item}
                                </div>
                            `;
                            $list.append(itemHtml);
                        });
                        $list.show();
                    } else {
                        $list.hide();
                    }
                }
            });
        }, 300); // 300ms delay
    });

    // 3. Selection Handler
    $(document).on('click', '.suggestion-item', function() {
        const selectedText = $(this).text().trim();
        $input.val(selectedText);
        $list.hide();
    });

    // 4. Click Outside to Close
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.autocomplete-wrapper').length) {
            $list.hide();
        }
    });
});
</script>
   <script src="js/sync_system.js"></script>



<script src="jquery/jquery-ui.min.js"></script>
<!-- Edit Employee Modal -->
<div class="modal fade dark-theme light-theme navbar-dark" id="editEmployeeModal" tabindex="-1" aria-labelledby="editEmployeeModalLabel" aria-hidden="true">
    <div class="modal-dialog dark-theme light-theme navbar-dark">
        <div class="modal-content dark-theme light-theme navbar-dark">
            <form id="editEmployeeForm">
                <div class="modal-header dark-theme light-theme navbar-dark">
                    <h5 class="modal-title dark-theme light-theme navbar-dark" id="editEmployeeModalLabel">Edit Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body dark-theme light-theme navbar-dark">
                    <input type="hidden" id="editEmployeeId" name="employeeId">
                    <div class="mb-3 dark-theme light-theme navbar-dark">
                        <label for="editFirstName" class="form-label">First Name</label>
                        <input type="text" class="form-control dark-theme light-theme navbar-dark" id="editFirstName" name="first_name" required>
                    </div>
                    <div class="mb-3 dark-theme light-theme navbar-dark">
                        <label for="editLastName" class="form-label dark-theme light-theme navbar-dark">Last Name</label>
                        <input type="text" class="form-control dark-theme light-theme navbar-dark" id="editLastName" name="last_name" required>
                    </div>
                    <div class="mb-3 dark-theme light-theme navbar-dark">
                        <label for="editEmail" class="form-label dark-theme light-theme navbar-dark">Email</label>
                        <input type="email" class="form-control dark-theme light-theme navbar-dark" id="editEmail" name="email" required>
                    </div>
                    <div class="mb-3 dark-theme light-theme navbar-dark">
                        <label for="editPhoneNumber" class="form-label">Phone Number</label>
                        <input type="text" class="form-control dark-theme light-theme navbar-dark" id="editPhoneNumber" name="phone_number">
                    </div>
                    <div class="mb-3 dark-theme light-theme navbar-dark">
                        <label for="editDepartment" class="form-label">Department</label>
                        <input type="text" class="form-control dark-theme light-theme navbar-dark" id="editDepartment" name="department">
                    </div>
                    <div class="mb-3 dark-theme light-theme navbar-dark">
                        <label for="editJobTitle" class="form-label">Job Title</label>
                        <input type="text" class="form-control dark-theme light-theme navbar-dark" id="editJobTitle" name="job_title">
                    </div>
                    <div class="mb-3 dark-theme light-theme navbar-dark">
                    <label for="status">Employee Status</label>
                    <select class="form-control dark-theme light-theme navbar-dark" id="statusEdit" name="Employee_Status">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                        <option value="Terminated">Terminated</option>
                    </select>
                    </div>
                </div>
                <div class="modal-footer dark-theme light-theme navbar-dark">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

       <!-- end of modal for employees -->

