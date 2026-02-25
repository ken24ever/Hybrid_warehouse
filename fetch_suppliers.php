<?php
// Fetches supplier names from the 'suppliers' table for the 'Supplier Info' autocomplete field.

header('Content-Type: application/json');
include('connection.php');

$term = isset($_GET['term']) ? trim($_GET['term']) : '';
$sql = "SELECT company_name FROM suppliers WHERE company_name LIKE :term ORDER BY company_name ASC LIMIT 10";

try {
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':term', '%' . $term . '%', SQLITE3_TEXT); 
    $result = $stmt->execute();
    
    $suggestions = [];
    if ($result) {
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) { 
            // Return only the name (value) for the JQuery UI Autocomplete list
            $suggestions[] = $row['company_name']; 
        }
    }
    
    echo json_encode($suggestions);

} catch (Exception $e) {
    echo json_encode([]);
}
exit;
?>