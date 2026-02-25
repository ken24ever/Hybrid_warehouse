<?php 
include('connection.php'); 
session_start(); 

// Check if user is logged in 
if (!isset($_SESSION['user_id'])) { 
    echo json_encode(["success" => false, "message" => "Unauthorized: You must be logged in."]); 
    exit; 
} 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transaction_id'])) { 
    $transactionId = (int) $_POST['transaction_id']; 

    // Fetch item_id and quantity from the transaction
    $query = "SELECT item_id, quantity FROM transactions WHERE transaction_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bindValue(1, $transactionId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);

    if (!$row) {
        echo json_encode(["success" => false, "message" => "Transaction not found."]);  
        exit;
    }

    $itemId = $row['item_id'];
    $quantityToReduce = $row['quantity'];

    // Reduce item quantity in ITEMS table
    $updateStockQuery = "UPDATE items SET quantity_in_stock = quantity_in_stock - ? WHERE item_id = ?";
    $stmt = $conn->prepare($updateStockQuery);
    $stmt->bindValue(1, $quantityToReduce, SQLITE3_INTEGER);
    $stmt->bindValue(2, $itemId, SQLITE3_INTEGER);
    $stmt->execute();

    // Update the transaction status to 0 (active)
    $updateTransactionQuery = "UPDATE transactions SET status = 0 WHERE transaction_id = ?";
    $stmt = $conn->prepare($updateTransactionQuery);
    $stmt->bindValue(1, $transactionId, SQLITE3_INTEGER);

    if ($stmt->execute()) { 
        echo json_encode(["success" => true, "message" => "Transaction restored successfully. Item stock updated."]); 
    } else { 
        echo json_encode(["success" => false, "message" => "Failed to restore transaction."]); 
    } 
    exit; 
} 

echo json_encode(["success" => false, "message" => "Invalid request."]); 
exit; 
?>
