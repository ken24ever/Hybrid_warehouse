<?php
session_start();

if (isset($_SESSION['user_id'])){
 // header("location: logout.php");
 header("location: hub_dashboard.php");
 header("location: admin.php");
 header("location: sales_manager.php");
 
}


?>
<!DOCTYPE html> 
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login Page</title>
  <link rel="stylesheet" href="bootstrap_v4/css/bootstrap.min.css">
  <link rel="stylesheet" href="font_awe_6/css/all.css">

       <!-- toast styling effect -->
       <link rel="stylesheet" href="node_modules/toastify-js/src/toastify.css" />
    
  <style>
    body {
      background-color: #f8f9fa;
    }

    .navbar {
      background-color: #343a40;
      color: #ffffff;
    }

    .navbar a {
      color: #ffffff;
    }

    .navbar-brand {
      font-weight: bold;
    }

    .form-container {
      margin-top: 4rem;
      background-color: #ffffff;
      border-radius: 0.3rem;
      padding: 2rem;
    }

    .form-container h2 {
      font-weight: bold;
      margin-bottom: 2rem;
      text-align: center;
    }

    .form-group label {
      font-weight: bold;
    }

    .show-password {
      position: relative;
    }

    .show-password input[type="password"] {
      padding-right: 40px;
    }

    .show-password .toggle-password {
      position: absolute;
      top: 10px;
      right: 10px;
      cursor: pointer; 
    }

    .footer {
      background-color: #343a40;
      color: #ffffff;
      padding: 1rem;
      text-align: center;
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
    }

    
  </style>
  <style>
.loader {
  border: 16px solid #f3f3f3;
  border-radius: 50%;
  border-top: 16px solid blue;
  border-right: 16px solid green;
  border-bottom: 16px solid red;
  width: 120px;
  height: 120px;
  -webkit-animation: spin 2s linear infinite;
  animation: spin 2s linear infinite;
}

@-webkit-keyframes spin {
  0% { -webkit-transform: rotate(0deg); }
  100% { -webkit-transform: rotate(360deg); }
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}
</style>
    <!-- jquery lib -->
    <script src="jquery/jquery-3.6.0.min.js"></script>
    
<script src='js/login.js'></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    document.body.style.zoom = "70%"; // Adjust for more/less zoom
});
</script>
</head>

<body>
  <!-- toast effect -->
<script src="node_modules/toastify-js/src/toastify.js"></script>

  <nav class="navbar navbar-expand-md navbar-dark">
    <div class="container">
      <a class="navbar-brand" href="#">Inventory Keeper App</a>
      <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav"
        aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ml-auto">
          <li class="nav-item">
            <a class="nav-link" href="#">About Us</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#">Contact Us</a>
          </li>
        </ul>
      </div>
    </div>
  </nav>
<center><div class="loader"></div></center>
  <div class="container">
    <div class="row">
      <div class="col-md-12">
        <div class="form-container">
          <h2>Users Login</h2>
          <div id="errorMessage" class="alert alert-danger" style="display: none;"></div>
          <form id="loginForm">
            <div class="form-group">
              <label for="adminUsername">Username</label>
              <input type="text" class="form-control" id="adminUsername" placeholder="Enter your username">
            </div>
            <div class="form-group show-password">
              <label for="adminPassword">Password</label>
              <input type="password" class="form-control" id="adminPassword" placeholder="Enter your password">
              <span class="toggle-password">
                <i class="fa fa-eye"></i>
              </span>
            </div>
            <div class="form-group">
              <a href="#" class="forgot-password">Forgot Password?</a>
            </div>
            <button type="submit" class="btn btn-primary">Login</button>
          </form>
        </div>
      </div>
     
    </div>
  </div>

  <footer class="footer">
    <div class="container">
      &copy; <span id="currentYear"></span> Inventory Keeper App. All rights reserved.
    </div>
  </footer>

  <script src="jquery/jquery-3.6.0.min.js"></script>
  <script src="bootstrap_v4/js/bootstrap.min.js"></script>
  <script>
    document.getElementById("currentYear").innerHTML = new Date().getFullYear();

    // Show Password Functionality
    $(".toggle-password").click(function () {
      var input = $(this).prev("input");
      var icon = $(this).children("i");

      if (input.attr("type") === "password") {
        input.attr("type", "text");
        icon.removeClass("fa-eye").addClass("fa-eye-slash");
      } else {
        input.attr("type", "password");
        icon.removeClass("fa-eye-slash").addClass("fa-eye");
      }
    });
  </script>
</body>

</html>
