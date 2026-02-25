<?php
// Include your SQLite database connection
require 'connection.php';

$inactivityMinutes = 10; // Default value

// Query to fetch the auto logout timeout
$query = "SELECT inactivity_minutes FROM auto_logout_settings WHERE id = 1 LIMIT 1";

$result = $conn->query($query);
if ($result) {
    $row = $result->fetchArray(SQLITE3_ASSOC);
    if ($row) {
        $inactivityMinutes = intval($row['inactivity_minutes']);
    }
}
$conn->close(); 
?>
