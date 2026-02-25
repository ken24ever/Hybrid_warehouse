<?php
//session_start();

if (!isset($_SESSION['user_id'])) {
    header("location: index.php");
    exit();
}

include('connection.php');

function isLicenseActive($conn, $user_id) {
    $query = "SELECT COUNT(*) FROM licenses WHERE user_id = :user_id AND is_active = 1";
    $stmt = $conn->prepare($query);
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $count = $result->fetchArray()[0];
    
    return $count > 0;
}

$user_id = $_SESSION['user_id'];
$query = "SELECT trial_start_date, created_by_user_id FROM users WHERE user_id = :user_id";
$stmt = $conn->prepare($query);
$stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
$result = $stmt->execute();
$current_user_data = $result->fetchArray(SQLITE3_ASSOC);

$parent_user_id = $current_user_data['created_by_user_id'];

$trial_check_user_id = $user_id;
if (!empty($parent_user_id)) {
    $trial_check_user_id = $parent_user_id;
    $parent_query = "SELECT trial_start_date FROM users WHERE user_id = :parent_user_id";
    $parent_stmt = $conn->prepare($parent_query);
    $parent_stmt->bindValue(':parent_user_id', $parent_user_id, SQLITE3_INTEGER);
    $parent_result = $parent_stmt->execute();
    $parent_data = $parent_result->fetchArray(SQLITE3_ASSOC);
    
    $_SESSION['trial_start_date'] = $parent_data['trial_start_date'];
} else {
    $_SESSION['trial_start_date'] = $current_user_data['trial_start_date'];
}

$trial_start_date = new DateTime($_SESSION['trial_start_date']);
$current_date = new DateTime();
$interval = $trial_start_date->diff($current_date);
$days_elapsed = $interval->days;

$trial_period_days = 30; 
$remaining_days = $trial_period_days - $days_elapsed;

// Store remaining days in session to be used by Sweet Alert
$_SESSION['remaining_days'] = $remaining_days;

if ($remaining_days <= 0) {
    if (!isLicenseActive($conn, $trial_check_user_id)) {
        header("location: trial_expired.php");
        exit();
    }
}

$conn->close();
?>