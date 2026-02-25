
  $(document).ready(function() {
  // Function to fetch users with pagination
  function fetchUsers(page) {
    $.ajax({
      url: 'view_users.php',//php endpoint
      type: 'GET',
      dataType: 'json',
      data: {
        page: page
      },
      success: function(response) {
        // Clear the table body
        $('#userTableBody').empty();
        $('#paginationCont').html(response.pagination)
  
        // Populate the table with user data
        $.each(response.users, function(index, user) {
          var row = '<tr>';
          row += '<td>' + user.user_id + '</td>';
          row += '<td>' + user.username + '</td>';
          row += '<td>' + user.role_name + '</td>';
          row += '<td>';
          row += '<button class="btn btn-info editUser btn-xl " data-user-id = "'+ user.user_id +'">Edit</button>';
          row += '<button class="btn btn-danger deleteUser btn-xl " data-user-id = "'+ user.user_id +'">Delete</button>';
          row += '</td>';
          row += '</tr>';
          $('#userTableBody').append(row);
        });
      },
      error: function(xhr, status, error) {
        // Handle error response
        console.log(error);
      }
    });
  }
  
  // Function to handle pagination link click
  function handlePaginationClick(e) {
    e.preventDefault();
    var page = $(this).data('page');
    fetchUsers(page);
  }
  
  // Bind click event to pagination links
  $(document).on('click', '.pagination-link', handlePaginationClick);
  
  // Fetch users for the initial page
  fetchUsers(1);

    // Function to fetch and populate role options
    function fetchRoles() {
      $.ajax({
        url: 'fetch_roles.php',//php endpoint
        type: 'GET',
        dataType: 'json',
        success: function(response) {
          // Populate the role select dropdown
          var roleSelect = $('#role');
          roleSelect.empty(); // Clear existing options
          $.each(response, function(index, role) {
            roleSelect.append('<option value="' + role.role_id + '">' + role.role_name + '</option>');
          });
        },
        error: function(xhr, status, error) {
          // Handle error response
          console.log(error);
        }
      });
    }

    // Fetch and populate the role options
    fetchRoles();

   // 3. HANDLE FORM SUBMISSION
  $('#createUserForm').submit(function(e) {
    e.preventDefault();

    // Capture Values
    var username = $('#username').val();
    var password = $('#password').val();
    var role_id  = $('#role').val();
    var branch_code = $('#branchSelect').val(); // <--- NEW BRANCH VALUE

    // Button UI - Loading State
    var submitBtn = $('#submitBtn');
    var originalText = submitBtn.text();
    submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Creating...');

    $.ajax({
      url: 'create_user.php',
      type: 'POST',
      dataType: 'json',
      data: {
        username: username,
        password: password,
        role_id: role_id,
        branch_code: branch_code // <--- SENDING TO SERVER
      },
      success: function(response) {
        if (response.success) {
          // Success Notification
          Toastify({
            text: response.message,
            duration: 5000,
            gravity: 'top',
            close: true,
            style: { background: 'linear-gradient(to right, #28a745, #218838)' }
          }).showToast();

          // Reset Form & Refresh User List
          $('#createUserForm')[0].reset();
          if(typeof fetchUsers === 'function') {
             fetchUsers(1); 
          }
          
        } else {
          // Error Notification
          Toastify({
            text: response.message,
            duration: 5000,
            gravity: 'top',
            close: true,
            style: { background: 'linear-gradient(to right, #dc3545, #c82333)' }
          }).showToast();
        }
      },
      error: function(xhr, status, error) {
        console.error("AJAX Error:", error);
        Toastify({
          text: "System error occurred. Please try again.",
          duration: 5000,
          gravity: 'top',
          close: true,
          style: { background: 'linear-gradient(to right, #dc3545, #c82333)' }
        }).showToast();
      },
      complete: function() {
        // Reset Button State
        submitBtn.prop('disabled', false).text(originalText);
      }
    });
  });
});