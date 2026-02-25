// login.js
// VERSION: BLENDED CONTEXT & ROLE HANDLING

$(document).ready(function() {
    var loader = $('.loader').hide(); 

    $("#loginForm").submit(function(event) {
        event.preventDefault();
        var username = $("#adminUsername").val().trim();
        var password = $("#adminPassword").val().trim();
        
        if (username === '' || password === '') {
            Toastify({
                text: 'Please fill in all fields.',
                duration: 3000,
                gravity: 'top',
                style: { background: '#FFA500' }
            }).showToast();
            return false;
        }
        
        $.ajax({
            url: "login_process.php", 
            type: "POST",
            data: {
                username: username,
                password: password
            },
            dataType: "json",
            beforeSend: function() { 
                loader.show();
                $('button[type="submit"]').prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    var role = response.role.toLowerCase();
                    var branchName = response.branch_name || 'System';
                    var redirectUrl = "404.php"; // Default safety

                    // --- ROLE BASED REDIRECT ---
                    switch(role) {
                        case "super admin":
                            redirectUrl = "hub_dashboard.php"; // The Hub
                            break;
                        case "admin":
                            redirectUrl = "admin.php"; // Local Admin Dashboard
                            break;
                        case "sales manager":
                            redirectUrl = "pos.php"; // Point of Sale
                            break;
                        case "store keeper":
                            redirectUrl = "store_keeper.php"; // Store Management
                            break;
                        default:
                            redirectUrl = "404.php";
                    }

                    // Success Toast with Branch Context
                    Toastify({
                        text: `Login Successful! Entering ${branchName}...`,
                        duration: 2000,
                        gravity: 'top',
                        close: false,
                        style: {
                            background: 'linear-gradient(to right, #00b09b, #96c93d)',
                        }
                    }).showToast();
                    
                    // Smooth Redirect
                    setTimeout(function() {
                        window.location.href = redirectUrl;
                    }, 1500);

                } else {
                    // Login Failed
                    Toastify({
                        text: response.message,
                        duration: 4000,
                        gravity: 'top',
                        close: true,
                        style: {
                            background: 'linear-gradient(to right, #FF5F6D, #FFC371)',
                        }
                    }).showToast();
                    $('button[type="submit"]').prop('disabled', false);
                }
            },
            complete: function() {
                loader.hide();
            },
            error: function(xhr, status, error) {
                console.error(xhr.responseText);
                Toastify({
                    text: 'Server Connection Error. Please try again.',
                    duration: 4000,
                    gravity: 'top',
                    style: { background: '#FF0000' }
                }).showToast();
                $('button[type="submit"]').prop('disabled', false);
                loader.hide();
            }
        });
    });
});