<?php
// Set SQLite database file
$dbFile = "warehouse_v2.0.db";

try {
    // Create (or open) SQLite database
    $conn = new SQLite3($dbFile);
} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage());
}

// Uncomment below to confirm connection
// echo "Connected successfully";
?>
