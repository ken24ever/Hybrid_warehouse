<?php
// In settings.php

// Include the database connection
include('connection.php');

function getSettings() {
    global $conn;

    if (isset($_SESSION['globalSettings'])) {
        return $_SESSION['globalSettings'];
    }

    $settings = [];
    $query = "SELECT * FROM settings LIMIT 1";
    $result = $conn->query($query);

    if ($result) {
        $settings = $result->fetchArray(SQLITE3_ASSOC);
    }

    $_SESSION['globalSettings'] = $settings;
    return $settings;
}
?>
