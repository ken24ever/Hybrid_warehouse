<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trial Expired</title>
<!-- bootstrap v4 -->
<link rel="stylesheet" href="bootstrap_v4/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
      
    <!-- sweet  alert 2 lib -->
<link rel="stylesheet" href="sweetalert2/dist/sweetalert2.min.css">
<script src="sweetalert2/dist/sweetalert2.all.min.js"></script>

    <style>
        body {
            background-color: #f8f9fa;
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            width: 90%;
            padding: 30px;
            text-align: center;
        }
        .card-header {
            background-color: #dc3545;
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 20px;
            font-size: 1.5rem;
            font-weight: bold;
        }
        .card-body .icon {
            font-size: 4rem;
            color: #dc3545;
            margin-bottom: 20px;
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
            border-radius: 50px;
            padding: 12px 25px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #004a9d;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header bg-danger text-white">
            Trial Expired
        </div>
        <div class="card-body">
            <div class="icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h2 class="card-title">Your 30-Day Trial Has Ended</h2>
            <p class="card-text">
                Thank you for trying our Point of Sale application. To continue using the software and access all features, please activate a full license.
            </p>
            <p>
                Please contact the administrator or visit our website to purchase a license key.
            </p>
            <a href="https://yourwebsite.com/purchase" class="btn btn-primary mt-3">
                Purchase a License
            </a>
            <button class="btn btn-secondary mt-3" onclick="showContactInfo()">
                Contact Support
            </button>
        </div>
    </div>

      <!-- jquery lib -->
      <script src="jquery/jquery-3.6.0.min.js"></script>

    <!-- bootstrap v4 js -->
    <script src="bootstrap_v4/js/bootstrap.min.js"></script>
    
    <script>
        function showContactInfo() {
            Swal.fire({
                title: 'Contact Information',
                html: `
                    <p>For assistance with purchasing a license, please contact us:</p>
                    <p>Email: <a href="mailto:kenenobas@gmail">kenenobas@gmail</a></p>
                    <p>Phone: +2347060474268</p>
                `,
                icon: 'info',
                showConfirmButton: true,
                confirmButtonText: 'OK'
            });
        }
    </script>
</body>
</html>