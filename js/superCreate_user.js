
  $(document).ready(function() {

      // Function to fetch users with pagination
      function fetchUsers(page) {
        $.ajax({
          url: 'view_users.php',
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
              row += '<button class="btn btn-primary btn-sm edit-user" data-user-id="' + user.user_id + '">Edit</button>';
              row += '<button class="btn btn-danger btn-sm delete-user" data-user-id="' + user.user_id + '">Delete</button>';
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


    

    // Fetch and populate role options
    function fetchRoles() {
      $.ajax({
        url: 'fetch_roles.php',
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

   $(document).ready(function () {

    // --- YOUR NEW ROBUST FORM HANDLER ---
    $('#createUserForm').submit(function (event) {
        event.preventDefault(); // Prevent page reload

        // Serialize automatically grabs all fields with 'name' attributes
        var formData = $(this).serialize(); 

        // Target the button in the footer that triggered the submit
        var submitButton = $('.modal-footer button[type="submit"]');
        var originalButtonText = submitButton.html(); 

        // Show loading effect
        submitButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Creating...');

        $.ajax({
            url: 'create_user.php',  
            type: 'POST',
            data: formData,
            dataType: 'json', 
            success: function (response) {
                if (response.success) {
                    // Success Notification
                    Toastify({
                        text: response.message,
                        duration: 5000,
                        gravity: 'top',
                        close: true,
                        style: { background: 'linear-gradient(to right, #28a745, #218838)' }
                    }).showToast();

                    // Reset form and Close Modal
                    $('#createUserForm')[0].reset();
                    $('#createUserModal').modal('hide'); // Close the modal

                    // Refresh User List (Start at page 1)
                    if (typeof fetchUsers === "function") {
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
            error: function (xhr, status, error) {
                console.error("AJAX Error:", status, error);
                Toastify({
                    text: "An error occurred. Check console for details.",
                    duration: 5000,
                    gravity: 'top',
                    close: true,
                    style: { background: 'linear-gradient(to right, #dc3545, #c82333)' }
                }).showToast();
            },
            complete: function () {
                // Restore button
                submitButton.prop('disabled', false).html(originalButtonText);
            }
        });
    });
  })

   // Fetch and populate role options
   function fetchRoles1() {
    $.ajax({
      url: 'fetch_roles.php',
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
 
        // Edit User
        $(document).on('click', '.edit-user', function() { 
          var userId = $(this).data('user-id');
          // Retrieve the user details using AJAX
          $.ajax({
            url: 'super_get_user.php',
            type: 'POST',
            dataType: 'json',
            data: { user_id: userId },
            success: function(response) {
              // Handle success response
              if (response.success) {
                // Populate the edit user form with the retrieved user data
                $('#editUserId').val(response.user.user_id);
                $('#editUsername').val(response.user.username);
              
      
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
      
        // Edit User Form Submission
$('#editUserForm').submit(function (event) {
  event.preventDefault(); // Prevent default form submission

  // Get form data
  var formData = $(this).serialize();

  // Send AJAX request to update the user
  $.ajax({
    url: 'super_update_user.php', 
    type: 'POST',
    dataType: 'json',
    data: formData,
    beforeSend: function () {
      // Show a loading spinner and disable submit button
      $('#loadingSpinner').show();
      $('#editUserModal').modal('hide');
    },
    success: function (response) {
      if (response.success) {
        let toastMessage = response.message; // Default message

        // Check if there are specific changes to display
        if (response.changes && response.changes.length > 0) {
          toastMessage = `User updated successfully:\n - ${response.changes.join("\n - ")}`;
        }

        // Display a success toast with details of changes
        Toastify({
          text: toastMessage,
          duration: 5000,
          gravity: 'top',
          close: true,
          style: {
            background: 'linear-gradient(to right, #FFA0A0, #B88AFF, #A0A0FF)',
          }
        }).showToast();

        // Refresh user list
        fetchUsers(1);
      } else {
        // Handle case where no changes were made
        Toastify({
          text: response.message || 'No changes detected.',
          duration: 5000,
          gravity: 'top',
          close: true,
          style: {
            background: 'linear-gradient(to right, #FF5733, #FF8C00)',
          }
        }).showToast();
      }
    },
    error: function (xhr, status, error) {
      console.log("AJAX Error:", error);

      // Display an error message
      Toastify({
        text: 'An error occurred while updating the user. Please try again.',
        duration: 5000,
        gravity: 'top',
        close: true,
        style: {
          background: 'linear-gradient(to right, #FF0000, #CC0000)',
        }
      }).showToast();
    },
    complete: function () {
      // Hide the loading spinner
      $('#loadingSpinner').hide();
    }
  });
});


      
      
// refactored Delete User

$(document).on('click', '.delete-user', function() {
    var userId = $(this).data('user-id');
    var row = $(this).closest('tr'); // Capture row for removal

    Swal.fire({
        title: 'Delete User?',
        text: "This will remove the user from BOTH Local and Cloud databases. Ensure the branch is online.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete permanently!'
    }).then((result) => {
        if (result.isConfirmed) {
            
            // Show processing state
            Swal.fire({
                title: 'Syncing...',
                text: 'Verifying Cloud Connection & Deleting...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            $.ajax({
                url: 'delete_user.php',
                type: 'POST',
                dataType: 'json',
                data: { user_id: userId },
                success: function(response) {
                    if (response.success) {
                        Swal.fire(
                            'Deleted!',
                            response.message,
                            'success'
                        );
                        // Remove row from DOM immediately without reload
                        row.fadeOut(500, function() { $(this).remove(); });
                        // Optionally refresh full list
                        // fetchUsers(currentPage); 
                    } else {
                        Swal.fire(
                            'Action Blocked',
                            response.message,
                            'error'
                        );
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire(
                        'System Error',
                        'Failed to communicate with the server.',
                        'error'
                    );
                    console.error(xhr.responseText);
                }
            });
        }
    });
});
      
        // Fetch and display the initial user list
        fetchUsers(1);
      });
      


