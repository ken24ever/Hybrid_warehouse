<?php

// Define variables and initialize with empty values
$output_msg = "";

// Processing form data when form is submitted
if (isset($_POST['itemUniqueNo'])) {

    // Include the database connection
    include('connection.php');

    // Function to validate input data
    function test_input($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }

    // Assign and sanitize input
    $itemUniqueNo = test_input($_POST["itemUniqueNo"]);

    // Prepare a select statement
    $sql = "SELECT item_unique_no FROM items WHERE item_unique_no = :itemUniqueNo";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':itemUniqueNo', $itemUniqueNo, SQLITE3_TEXT);

    // Execute query
    $result = $stmt->execute();

    // Fetch result
    $row = $result->fetchArray(SQLITE3_ASSOC);

    if ($row) {
        $output_msg .= "1"; // Item found
    } else {
        $output_msg .= "0"; // Item not found
    }

    echo $output_msg;

    // Close the database connection
    $conn->close();
}
?>
