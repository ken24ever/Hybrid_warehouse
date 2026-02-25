$(document).ready(function() {
  // Handle form submission using AJAX
  $('#createRoleForm').submit(function(event) {
    event.preventDefault(); // Prevent default form submission

    // Get form data
    var formData = $(this).serialize();

    // Send AJAX request
    $.ajax({
      url: 'create_role.php', //php endpoint
      type: 'POST',
      data: formData,
      dataType: 'json', // Expecting JSON response

      // Before sending the AJAX request
      beforeSend: function() {
        $('#submitBtn').prop('disabled', true); // Disable button
        $('#loadingSpinner').show(); // Show spinner
      },

      success: function(response) {
        // Check if the request was successful
        if (response.success) {
          // Reset the form
          $('#createRoleForm')[0].reset();

          // Display success toast message
          Toastify({
            text: response.message,
            duration: 5000,
            gravity: 'top',
            close: true,
            style: {
              background: 'linear-gradient(to right, #00b09b, #96c93d)',
            }
          }).showToast();
        } else {
          // Display error toast message (e.g., duplicate role name)
          Toastify({
            text: response.message,
            duration: 5000,
            gravity: 'top',
            close: true,
            style: {
              background: 'linear-gradient(to right, #FF5733, #C70039)',
            }
          }).showToast();
        }

        // Re-enable the submit button & hide the spinner
        $('#loadingSpinner').hide();
        $('#submitBtn').prop('disabled', false);
      },

      error: function(xhr, status, error) {
        // Display error message
        Toastify({
          text: 'An error occurred. Please try again.',
          duration: 5000,
          gravity: 'top',
          close: true,
          style: {
            background: 'linear-gradient(to right, #FF5733, #C70039)',
          }
        }).showToast();

        // Re-enable the submit button & hide the spinner
        $('#loadingSpinner').hide();
        $('#submitBtn').prop('disabled', false);
      }
    });
  });
});
