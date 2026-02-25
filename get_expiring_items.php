<?php
// Include the database connection
include('connection.php');

// Function to safely fetch expiring items
function getExpiringItems($conn, $days) {
    $sql = "SELECT COUNT(*) FROM items WHERE expiration_date BETWEEN DATE('now') AND DATE('now', '+' || :days || ' days')";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':days', $days, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $count = $result->fetchArray(SQLITE3_NUM)[0] ?? 0; // Use ?? for null coalescing
    return $count;
}

function getExpiredItems($conn) {
    $sql = "SELECT expiration_date FROM items WHERE expiration_date IS NOT NULL";
    $result = $conn->query($sql);
    
    $expiredCount = 0;
    $today = new DateTime();

    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $dateStr = $row['expiration_date'];
        $itemDate = DateTime::createFromFormat('Y-m-d', $dateStr);

        if ($itemDate && $itemDate < $today) {
            $expiredCount++;
        }
    }

    return $expiredCount;
}


// Get counts for each interval
$expiring7Days = getExpiringItems($conn, 7);
$expiring14Days = getExpiringItems($conn, 14);
$expiring21Days = getExpiringItems($conn, 21);
$expiredItems = getExpiredItems($conn);

// Return the data as a JSON object
header('Content-Type: application/json');
echo json_encode([
    'expiring7Days' => $expiring7Days,
    'expiring14Days' => $expiring14Days,
    'expiring21Days' => $expiring21Days,
    'expiredItems' => $expiredItems,
]);

// Close the database connection
$conn->close();
?>
