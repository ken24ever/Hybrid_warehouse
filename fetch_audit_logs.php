<?php
// fetch_audit_logs.php

// Include your database connection file. Ensure this file sets up a valid connection in $conn.
require 'connection.php';

// Set the header to JSON for proper AJAX handling
header('Content-Type: application/json');

// Initialize an array to store audit log records
$auditLogs = [];

try {
    // Define the SQL query to join audit_logs with users table
    // This query fetches the latest 5 logs; adjust the LIMIT if needed.
    $query = "SELECT 
                a.log_id, 
                a.action, 
                a.timestamp, 
                u.username 
              FROM audit_logs a 
              LEFT JOIN users u ON a.user_id = u.user_id 
              ORDER BY a.timestamp DESC 
              LIMIT 5";

    // Execute the query
    $result = $conn->query($query);

    // Loop through the results and add them to the auditLogs array
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $auditLogs[] = [
            'log_id'    => $row['log_id'],
            'username'  => $row['username'],
            'action'    => $row['action'],
            'timestamp' => $row['timestamp']
        ];
    }

    // Return a success JSON response with the logs
    echo json_encode([
        'status' => 'success',
        'logs'   => $auditLogs
    ]);
} catch (Exception $e) {
    // In case of a query error, return an error JSON response
    echo json_encode([
        'status'  => 'error',
        'message' => 'Error fetching audit logs: ' . $e->getMessage()
    ]);
}

// Close the database connection
$conn->close();
?>
