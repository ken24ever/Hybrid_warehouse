<?php
// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the user ID from the form submission 
    $user_id = $_POST['user_id'];

    // Include the database connection 
    include('connection.php');

    // Prepare the SQL statement
    $stmt = $conn->prepare("SELECT user_id, username, role_id, reasons FROM users WHERE user_id = :user_id");

    // Bind parameters and execute the statement
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();

    // Fetch the result
    $user = $result->fetchArray(SQLITE3_ASSOC);

    // Close the connection
    $conn->close();

    if ($user) {
        // Prepare the JSON response
        $response = array(
            'success' => true,
            'user' => $user
        );
    } else {
        $response = array(
            'success' => false,
            'message' => 'User not found'
        );
    }

    // Send the JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>
