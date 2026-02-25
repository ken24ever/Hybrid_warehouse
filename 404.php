<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            background: linear-gradient(135deg, #6A11CB, #2575FC);
            color: #fff;
        }
        .container {
            text-align: center;
        }
        .error-code {
            font-size: 8rem;
            font-weight: 700;
            margin: 0;
        }
        .error-message {
            font-size: 1.5rem;
            margin: 10px 0;
        }
        .description {
            margin: 20px 0;
            font-size: 1rem;
            line-height: 1.6;
        }
        .btn {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            font-size: 1rem;
            font-weight: 500;
            color: #fff;
            background: #FF6A3D;
            text-decoration: none;
            border-radius: 25px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            transition: background 0.3s, box-shadow 0.3s;
        }
        .btn:hover {
            background: #ff5722;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.3);
        }
        .illustration {
            margin: 20px 0;
        }
        .illustration img {
            max-width: 100%;
            height: auto;
        }
    </style>
    <script>
document.addEventListener("DOMContentLoaded", function () {
    document.body.style.zoom = "70%"; // Adjust for more/less zoom
});
</script>
</head>
<body>
    <div class="container">
        <div class="illustration">
            <img src="https://cdn.dribbble.com/users/285475/screenshots/2083086/dribbble_1.gif" alt="404 Illustration">
        </div>
        <h1 class="error-code">404</h1>
        <p class="error-message">Oops! Page Not Found</p>
        <p class="description">
            Sorry, the page you are looking for doesn't exist, or there may have been an issue with your login. 
            Please ensure you are logged in properly or check if your user role is valid.
            Let's get you back to where you need to go.
        </p>
        <a href="logout.php" class="btn">Back to Home</a>
    </div>
</body>
</html>
