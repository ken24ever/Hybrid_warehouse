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
          row += '<button class="btn btn-primary btn-xl ">Click</button>';
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
    
    // Function to populate the "Delete User" form
    function populateDeleteForm(userId) {
      // Set the form value
      $('#deleteUserId').val(userId);
    }

    // Handle user row click event to populate the "Delete User" form
    $('#userTableBody').on('click', 'tr', function() {
      // Get the selected user ID from the table row
      var userId = $(this).find('td:first').text();

      // Populate the "Delete User" form
      populateDeleteForm(userId);
    });

    // Handle form submission using AJAX
    $('#deleteUserForm').submit(function(event) {
      event.preventDefault(); // Prevent form submission

      // Get form data
      var formData = $(this).serialize();
      var formInput = $('#deleteUserId').val()
      if (formInput == ""){
        Toastify({
          text: 'Field Is Empty! ',
          duration: 5000,
          gravity: 'top',
          close: true,
          style: {
            background: 'linear-gradient(to right, #FFA0A0, #B88AFF, #A0A0FF)',
          }
        }).showToast();
      }
      else
      {
 // Confirm before deleting
 Swal.fire({
  title: 'Are You Sure You Want To Delete Item With ID ?',
  html: '<h6 style="color:red">This Action Cannot Be Undone If You Proceed!</h6>',
  icon: 'question',
  showCancelButton: true,
  confirmButtonColor: 'darkGreen',
  cancelButtonColor: 'darkRed',
  confirmButtonText: 'Yes, Submit!',
  showLoaderOnConfirm: true,
}).then((result) => {
      if (result.isConfirmed)
         {
                // Make an AJAX request to delete the user
          // Send AJAX request
                      $.ajax({
                        url: 'delete_user.php',
                        type: 'POST',
                        // Before sending the AJAX request
                        beforeSend: function() {
                          // Show a loading spinner or disable the submit button to indicate processing
                          $('button[type="submit"]').prop('disabled', true);
                          $('#loadingSpinner').show();
                        },
                        dataType: 'json',
                        data: formData,
                        success: function(response) {
                          // Handle success response
                          if (response.success) {
                            // Display a success message
                          // $('#successMessage').text(response.message).show();
                          Toastify({
                            text: response.message,
                            duration: 5000,
                            gravity: 'top',
                            close: true,
                            style: {
                              background: 'linear-gradient(to right, #FFA0A0, #B88AFF, #A0A0FF)',
                            }
                          }).showToast();
                            // Fetch and display the updated user data
                            fetchUsers();

                            // Reset the form
                            $('#deleteUserForm')[0].reset();
                          }
                        },
                        error: function(xhr, status, error) {
                          // Handle error response
                          console.log(error);
                        },
                        complete: function() {
                          // Hide the loading spinner and enable the submit button
                          $('#deleteUserForm button[type="submit"]').prop('disabled', false);
                          $('#loadingSpinner').hide();
                          $('button[type="submit"]').prop('disabled', false);
                        }
                      });

          }//end of result.isConfirmed
          else
          {
                // Show display when you click cancel
                Swal.fire({
                  icon: 'info',
                  html: '<b>You backed out!</b>'
                  });
          }
      });//end of thenables

      }//end of else 

      


    });
  });

