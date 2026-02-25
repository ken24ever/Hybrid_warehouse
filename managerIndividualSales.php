<?php
session_start();

// Include the database connection
include('connection.php');

// Function to calculate total sales for today 
function getCurrentDayTotalSales($conn) { 
    $userID = $_SESSION['user_id'];
    $currentDate = date('Y-m-d');
    $sql = "SELECT SUM(total_amount) AS current_day_total_sales 
            FROM transactions 
            WHERE transaction_type = 'sale' 
            AND transaction_date = :currentDate 
            AND user_id = :userID";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':currentDate', $currentDate, SQLITE3_TEXT);
    $stmt->bindValue(':userID', $userID, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    return $row['current_day_total_sales'] ?? 0;
}

// Function to calculate yesterday's total sales
function getYesterdayTotalSales($conn) {
    $userID = $_SESSION['user_id'];
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $sql = "SELECT SUM(total_amount) AS yesterday_total_sales 
            FROM transactions 
            WHERE transaction_type = 'sale' 
            AND transaction_date = :yesterday 
            AND user_id = :userID";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':yesterday', $yesterday, SQLITE3_TEXT);
    $stmt->bindValue(':userID', $userID, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    return $row['yesterday_total_sales'] ?? 0;
}

// Function to count total number of items sold today for a specific user
function getTodaysItemCount($conn) {
    $userID = $_SESSION['user_id'];
    $currentDate = date('Y-m-d');
    $sql = "SELECT SUM(quantity) AS total_items_sold 
            FROM transactions 
            WHERE transaction_type = 'sale' 
            AND transaction_date = :currentDate 
            AND user_id = :userID";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':currentDate', $currentDate, SQLITE3_TEXT);
    $stmt->bindValue(':userID', $userID, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    return $row['total_items_sold'] ?? 0;
}

// Function to get detailed information on all items sold today, grouped by transaction_group_id
function getTodaysSoldItems($conn) {
    $userID = $_SESSION['user_id'];
    $currentDate = date('Y-m-d');
    $sql = "SELECT t.transaction_group_id, i.item_name, i.wholesale, i.retail, t.transaction_date, 
                   t.total_amount AS aggregate_amount, t.sold_at, t.quantity 
            FROM transactions t 
            JOIN items i ON t.item_id = i.item_id 
            WHERE t.transaction_type = 'sale' 
            AND t.transaction_date = :currentDate 
            AND t.user_id = :userID";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':currentDate', $currentDate, SQLITE3_TEXT);
    $stmt->bindValue(':userID', $userID, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $items = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $items[] = $row;
    }
    return $items;
}

// Determine the action requested
if (isset($_GET['action'])) {
    $action = $_GET['action'];

    if ($action === 'fetchItemsAndStats') {
        // Get detailed sold items for today
        $items = getTodaysSoldItems($conn);

        // Fetch statistics
        $current_day_total_sales = getCurrentDayTotalSales($conn);
        $yesterday_total_sales = getYesterdayTotalSales($conn);
        $items_sold_today = getTodaysItemCount($conn);

        // Create combined response
        $response = array(
            'items' => $items,
            'current_day_total_sales' => $current_day_total_sales,
            'yesterday_total_sales' => $yesterday_total_sales,
            'items_sold_today' => $items_sold_today,
        );

        // Return as JSON
        header("Content-Type: application/json");
        echo json_encode($response);
        exit;
    }
}

$conn->close();
?>
