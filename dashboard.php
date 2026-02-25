<?php
session_start();

if (isset($_SESSION['user_id'])){
  $userID = $_SESSION['user_id'] ;
  $userName = $_SESSION['username'];
  $userRole =  $_SESSION['role'] ;
}
else {
  header("location: logout.php");
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="bootstrap_v4/css/bootstrap.min.css">
  <link rel="stylesheet" href="font_awe_6/css/all.css">

    <!-- sweet  alert 2 lib -->
    <link rel="stylesheet" href="sweetalert2/dist/sweetalert2.min.css">
<script src="sweetalert2/dist/sweetalert2.all.min.js"></script>

	

    <!-- toast styling effect -->
    <link rel="stylesheet" href="node_modules/toastify-js/src/toastify.css" />
    

  <style>
    /* Custom Styles */
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
  </style> 
  <!-- jquery lib -->
  <script src="jquery/jquery-3.6.0.min.js"></script>
  <!-- custom js files -->
  <script src="js/create_role.js"></script>
  <script src="js/view_users.js"></script>
  <script src="js/create_users.js"></script>
  <script src="js/update_user.js"></script>
  <script src="js/delete_user.js"></script>  
  <script>
document.addEventListener("DOMContentLoaded", function () {
    document.body.style.zoom = "70%"; // Adjust for more/less zoom
});
</script>
</head>

<body>
  <!-- toast effect -->
<script src="node_modules/toastify-js/src/toastify.js"></script>

  <nav class="navbar navbar-expand-md navbar-dark bg-dark">
    <div class="container">
      <a class="navbar-brand" href="#">Welcome Admin!</a>
      <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav"
        aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ml-auto">
          <li class="nav-item">
          <a class="nav-link" href="#charts">WELCOME  [ <?php echo $_SESSION['username'];?> ] </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#">Roles</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#">Users</a>
          </li>
          <li class="nav-item">
          <a class="nav-link" href="logout.php">Logout</a>
        </li>
        </ul>
      </div>
    </div>
  </nav>
  <div id="successMessage" style="display: none;"></div>
<div id="errorMessage" style="display: none;"></div>
<div id="loadingSpinner" style="display: none;">Loading...</div>

  <div class="container mt-4">
    <div class="row">
      <div class="col-md-6">
    
        <div class="card">
          <div class="card-header">
            <h5 class="card-title">Create New Role</h5>
          </div>
          <div class="card-body">
          <form id="createRoleForm">
  <div class="form-group">
    <label for="roleName">Role Name</label>
    <input type="text" class="form-control" id="roleName" name="roleName" placeholder="Enter role name">
  </div>
  <button type="submit" class="btn btn-primary">Create Role</button>
</form>

          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card">
          <div class="card-header">
            <h5 class="card-title">Manage Users</h5>
            
          </div>
          <div class="card-body">
            <!-- User management table or other relevant content -->
            <hr>
             <center>
             <div id="paginationCont" class="text-center">
  <!-- Pagination links will be dynamically populated here -->
            </div>
             </center>
             <hr>

            <table class="table">
        <thead>
          <tr>
            <th>User ID</th>
            <th>Username</th>
            <th>Role</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody id="userTableBody"></tbody>
      </table>
            <!-- table ends here -->
          </div>
        </div>
      </div>

   
    </div><!-- end of row -->
  </div><!-- container -->


  <!-- 2nd container starts here -->

  <div class="container mt-4">
    <div class="row">
<!-- 1st column start here -->
    <div class="col-md-6">
  <div class="card">
    <div class="card-header">
      <h5 class="card-title">Create User</h5>
    </div>
    <div class="card-body">
      <form id="createUserForm">
        <div class="form-group">
          <label for="username">Username</label>
          <input type="text" class="form-control" id="username" name="username" placeholder="Enter username" required>
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" class="form-control" id="password" name="password" placeholder="Enter password" required>
          <span class="toggle-password">
                <i class="fa fa-eye"></i>
              </span>
        </div>
        <div class="form-group">
          <label for="role">Role</label>
          <select class="form-control" id="role" name="role" required>
            <!-- Populate options dynamically -->
          </select>
        </div>
        <button type="submit" class="btn btn-primary">Create User</button>
      </form>
    </div>
  </div>
</div>
<!-- 1st column ends here -->

<!-- 2nd col begins here -->

<div class="col-md-6">
  <div class="card">
    <div class="card-header">
      <h5 class="card-title">Update User</h5>
    </div>
    <div class="card-body">
      <form id="updateUserForm">
        <div class="form-group">
          <label for="updateUserId">User ID</label>
          <input type="text" class="form-control" id="updateUserId" name="user_id" readonly>
        </div>
        <div class="form-group">
          <label for="updateUsername">Username</label>
          <input type="text" class="form-control" id="updateUsername" name="username" required>
        </div>
        <div class="form-group">
          <label for="updatePassword">Password</label>
          <input type="password" class="form-control" id="updatePassword" name="password" required>
          <span class="toggle-password">
                <i class="fa fa-eye"></i>
          </span>
        </div>
        <div class="form-group">
          <label for="updateRole">Role</label>
          <select class="form-control" id="updateRole" name="role" required>
            <!-- Populate options dynamically -->
          </select>
        </div>
        <button type="submit" class="btn btn-primary">Update User</button>
      </form>
    </div>


  </div><!-- end of card  -->
</div><!-- end of col md 6 -->


<!-- 2nd col ends here -->




    </div><!-- row ends here -->
</div><!-- container ends here -->




<!-- 3rd container starts here -->

<div class="container mt-4">
    <div class="row">
<!-- 1st column start here -->

<div class="col-md-6">
  <div class="card">
    <div class="card-header">
      <h5 class="card-title">Delete User</h5> 
    </div>
    <div class="card-body">
      <form id="deleteUserForm">
        <div class="form-group">
          <label for="deleteUserId">User ID</label>
          <input type="text" class="form-control" id="deleteUserId" name="user_id" readonly>
        </div>
        <button type="submit" class="btn btn-danger">Delete User</button>
      </form>
    </div>
  </div>
</div>


<!-- 1st col ends here -->

</div><!-- row ends here -->
</div><!-- container ends here -->
<!-- 3rd container ends here -->
<br>

 
  <script src="bootstrap_v4/js/bootstrap.min.js"></script>
  <script>


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
