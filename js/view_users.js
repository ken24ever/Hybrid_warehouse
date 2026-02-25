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

     // Fetch and populate role options
     function fetchRoles1() {
      $.ajax({
        url: 'fetch_roles.php',//php endpoint 
        type: 'GET',
        dataType: 'json',
        success: function(response) {
          // Populate the role select dropdown
      
          var roleSelectName = $('#editRole');
          roleSelectName.empty(); // Clear existing options
          $.each(response, function(index, roleDet) {
            roleSelectName.append('<option value="' + roleDet.role_name + '">' + roleDet.role_name + '</option>');
          });
  
        },
        error: function(xhr, status, error) {
          // Handle error response
          console.log(error);
        }
      });
    }
  
    fetchRoles1()
  
  // Fetch users for the initial page 
  fetchUsers(1);
  
          // Edit User
          $(document).on('click', '.editUser', function() { 
            var userId = $(this).data('user-id');
            // Retrieve the user details using AJAX
            $.ajax({
              url: 'super_get_user.php',//php endpoint
              type: 'POST',
              dataType: 'json',
              data: { user_id: userId },
              success: function(response) {
                // Handle success response
                if (response.success) {
                  // Populate the edit user form with the retrieved user data
                  $('#editUserId').val(response.user.user_id);
                  $('#editUsername').val(response.user.username);
                   $('#comment').val(response.user.reasons);
        
                  // Show the edit user modal
                  $('#editUserModal').modal('show');
                }
              },
              error: function(xhr, status, error) {
                // Handle error response
                console.log(error);
              }
            });
          });

          $(document).ready(function() {
            // Edit User Form Submission
            $('#updateUserForm').submit(function(event) {
              event.preventDefault(); // Prevent default form submission
          
              // Get form data
              var formData = $(this).serialize();
          
              // Send AJAX request to update the user
              $.ajax({
                url: 'super_update_user.php',//php endpoint
                type: 'POST',
                dataType: 'json',
                data: formData,
          
                // Before sending request
                beforeSend: function() {
                  $('#submitBtn').prop('disabled', true); // Disable button
                  $('#loadingSpinner').show(); // Show spinner
                },
          
                success: function(response) {
                  if (response.success) {
                    // Success message
                    Toastify({
                      text: response.message,
                      duration: 5000,
                      gravity: 'top',
                      close: true,
                      style: {
                        background: 'linear-gradient(to right, #00b09b, #96c93d)',
                      }
                    }).showToast();
          
                    // Hide the edit user modal
                    $('#editUserModal').modal('hide');
          
                    // Fetch and display updated user list
                    fetchUsers(1);
                  } else {
                    // Handle "No Changes Detected" case
                    Toastify({
                      text: response.message,
                      duration: 5000,
                      gravity: 'top',
                      close: true,
                      style: {
                        background: 'linear-gradient(to right, #FFA500, #FF4500)',
                      }
                    }).showToast();
                  }
                },
          
                error: function(xhr, status, error) {
                  console.error("AJAX Error:", error);
                  Toastify({
                    text: 'An error occurred. Please try again.',
                    duration: 5000,
                    gravity: 'top',
                    close: true,
                    style: {
                      background: 'linear-gradient(to right, #FF5733, #C70039)',
                    }
                  }).showToast();
                },
          
                // After request completes (success or error)
                complete: function() {
                  $('#loadingSpinner').hide(); // Hide spinner
                  $('#submitBtn').prop('disabled', false); // Enable button
                }
              });
            });
          });
          


         $(document).on('click', '.deleteUser', function() {
    var userId = $(this).data('user-id');

    // 1. Browser-Level Offline Check (Immediate Feedback)
    if (!navigator.onLine) {
        Swal.fire({
            title: 'System Offline',
            text: 'You are currently offline. User deletion is blocked to prevent data inconsistency between Cloud and Local databases.',
            icon: 'warning',
            confirmButtonColor: '#d33'
        });
        return;
    }

    // 2. Confirmation Dialog
    Swal.fire({
        title: 'Delete User?',
        text: 'This will remove the user from BOTH Local and Cloud databases. This action requires an active internet connection.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33', // Professional Red for Delete
        cancelButtonColor: '#3085d6', // Standard Blue for Cancel
        confirmButtonText: 'Yes, Delete Permanently!',
        cancelButtonText: 'Cancel',
        allowOutsideClick: false,
    }).then((result) => {
        if (result.isConfirmed) {

            // 3. Server-Level Connectivity Check (Visual Feedback)
            Swal.fire({
                title: 'Verifying Connectivity...',
                text: 'Syncing with Cloud Database to enforce uniformity...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // 4. Send AJAX Request
            $.ajax({
                url: 'delete_user.php',
                type: 'POST',
                dataType: 'json',
                data: { user_id: userId },
                success: function(response) {
                    if (response.success) {
                        // Success: Show Alert & Refresh
                        Swal.fire(
                            'Deleted!',
                            response.message,
                            'success'
                        );
                        fetchUsers(1);
                    } else {
                        // Logic Error (e.g., Cloud Unreachable / Permission Denied)
                        Swal.fire(
                            'Action Blocked',
                            response.message,
                            'error'
                        );
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error:", error);
                    Swal.fire(
                        'Connection Error',
                        'Failed to communicate with the server. Please check your network.',
                        'error'
                    );
                }
            });
        }
    });
});
          


    }); //end of doc ready