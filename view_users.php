<?php

// Start a new session or resume the existing session
session_start();

// Include the database connection
include('connection.php');

// Pagination settings
$perPage = 5; // Number of users per page
$page = isset($_GET['page']) ? intval($_GET['page']) : 1; // Convert $page to an integer
$offset = ($page - 1) * $perPage; // Calculate the offset for SQL query

// Retrieve user data from the database with pagination
$userRole = $_SESSION['role'] ?? ''; // Assuming user role is stored in a session variable

if ($userRole == 'Admin') {
    // Exclude 'Super Admin' role if the user is 'Admin'
    $query = "SELECT u.user_id, u.username, r.role_name 
              FROM users u 
              INNER JOIN roles r ON u.role_id = r.role_id 
              WHERE r.role_name <> 'Super Admin' 
              ORDER BY u.user_id DESC 
              LIMIT :limit OFFSET :offset";
} else {
    // For other roles, include all records
    $query = "SELECT u.user_id, u.username, r.role_name 
              FROM users u 
              INNER JOIN roles r ON u.role_id = r.role_id 
              ORDER BY u.user_id DESC 
              LIMIT :limit OFFSET :offset";
}

$stmt = $conn->prepare($query);
$stmt->bindValue(':limit', $perPage, SQLITE3_INTEGER);
$stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
$result = $stmt->execute();

// Fetch user records
$users = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $users[] = $row;
}

// Retrieve the total number of users
$totalQuery = "SELECT COUNT(*) AS total FROM users";
$totalResult = $conn->query($totalQuery);
$totalRow = $totalResult->fetchArray(SQLITE3_ASSOC);
$totalUsers = $totalRow['total'] ?? 0;

// Calculate total pages
$totalPages = ceil($totalUsers / $perPage);

// Prepare the JSON response
$response = [
    'users' => $users,
    'totalPages' => $totalPages
];

// Determine pagination range
$start_page = max(1, $page - 5);
$end_page = min($totalPages, $start_page + 9);

// Generate pagination links
$pagination = '<ul class="pagination">';

// Previous button
if ($page > 1) {
    $previous = $page - 1;
    $pagination .= '<li class="pagination-link page-item" id="'.$previous.'" title="'.$previous.'">
                        <span class="page-link"><i class="fa fa-arrow-left">Previous</i></span>
                    </li>';
}

// Always show the first page
if ($page > 3) {
    $pagination .= '<li class="pagination-link page-item" id="1">
                        <a class="page-link" href="javascript:void(0);">1</a>
                    </li>';
}

if ($page > 4) {
    $pagination .= '<li class="pagination-link page-item disabled">
                        <a class="page-link" href="javascript:void(0);">...</a>
                    </li>';
}

for ($i = max(2, $page - 2); $i <= min($totalPages - 1, $page + 2); $i++) {
    $active_class = ($i == $page) ? "active" : "";
    $pagination .= '<li class="pagination-link page-item '.$active_class.'" id="'.$i.'" title="'.$i.'">
                        <a class="page-link" href="javascript:void(0);">'.$i.'</a>
                    </li>';
}

if ($page < $totalPages - 3) {
    $pagination .= '<li class="pagination-link page-item disabled">
                        <a class="page-link" href="javascript:void(0);">...</a>
                    </li>';
}

if ($page < $totalPages - 2) {
    $pagination .= '<li class="pagination-link page-item" id="'.$totalPages.'" title="'.$totalPages.'">
                        <a class="page-link" href="javascript:void(0);">'.$totalPages.'</a>
                    </li>';
}

// Next button
if ($page < $totalPages) {
    $next = $page + 1;
    $pagination .= '<li class="pagination-link page-item" data-page="'.$next.'" title="'.$next.'">
                        <span class="page-link"><i class="fa fa-arrow-right">Next</i></span>
                    </li>';
}

$pagination .= '</ul>';

// Append the pagination links to the JSON response
$response['pagination'] = $pagination;

// Send the JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit();

?>
