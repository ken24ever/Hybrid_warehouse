<?php
session_start();

  // Now proceed with license validation
  //require 'validate_license.php';

if (isset($_SESSION['user_id'])){
 // header("location: logout.php");
 header("location: hub_dashboard.php");
 header("location: admin.php");
 header("location: pos.php"); 
 exit();

}

 

?> 


<!DOCTYPE html> 
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
        <title>Login Page</title>
        <link rel="stylesheet" href="bootstrap_v4/css/bootstrap.min.css">
        <link rel="stylesheet" href="font_awe_6/css/all.css">
        <link rel="stylesheet" href="node_modules/toastify-js/src/toastify.css" />
        <!-- jquery lib -->
        <script src="jquery/jquery-3.6.0.min.js"></script>
        <script src='js/login.js'></script>
        <link rel="stylesheet" href="css/style.css">
        <script>
document.addEventListener("DOMContentLoaded", function () {
    document.body.style.zoom = "100%"; // Adjust for more/less zoom
});
</script>
        <style>
         /*    body{
                zoom: 100%;/* adjust zoom 
            } */
            .footer {
               position: fixed;
               /*margin-top: 58px;*/
               bottom: 0;
               width: 100%;
               text-align: center;
               color: #fff;
            }
        </style>
    </head>
	<body class="img" style="background-image: url(img/bg4.jpg);">
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
                            <a class="nav-link" href="#">FAQs</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">Support</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
        <center><div class="loader"></div></center>
        <div class="container">
            <div class="row">
                <div class="col-md-12">
        		    <div class="row justify-content-center">
        			    <div class="col-md-6 col-lg-4">
        				    <div class="login-wrap p-0">
                                <br><br>
        	      	            <h3 class="mb-4 text-center">Login</h3>
                                <div id="errorMessage" class="alert alert-danger" style="display: none;"></div>
        	      	            <form id="loginForm" class="signin-form">
                    	      		<div class="form-group">
                    	      			<input type="text" class="form-control" placeholder="Username" id="adminUsername" required>
                    	      		</div>
                                    <div class="form-group">
                                        <input type="password" class="form-control" placeholder="Password" id="adminPassword" required>
                                        <span toggle="#password-field" class="fa fa-fw fa-eye field-icon toggle-password"></span>
                                    </div>
                                    <div class="form-group">
                                	    <button type="submit" class="form-control btn btn-primary submit px-3">Login</button>
                                    </div>
                                    <div class="form-group d-md-flex">
                                	    <div class="w-50">
                    	            	    <label class="checkbox-wrap checkbox-primary">Remember Me
                                                <input type="checkbox" checked>
                    						    <span class="checkmark"></span>
                    						</label>
                    					</div>
                    					<div class="w-50 text-md-right">
                    					    <a href="#" style="color: #fff">Forgot Password</a>
                    					</div>
                                    </div>
                                </form> 
                                <!--<p class="w-100 text-center">&mdash; Or Create New Account &mdash;</p>-->
                                <!--<div class="social d-flex text-center">-->
                              	 <!--   <a href="#" class="px-2 py-2 mr-md-1 rounded"><span class="ion-logo-facebook mr-2"></span> Create Account</a>-->
                                <!--</div>-->
                            </div>
				        </div>
			        </div>
                </div>
            </div>
        </div>

        <div class="footer">
          &copy; <span id="currentYear"></span> Inventory Keeper App. All rights reserved.
        </div>
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
