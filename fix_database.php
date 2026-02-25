<?php
// fix_database.php
// RUN THIS SCRIPT ONCE TO UPGRADE YOUR DATABASE TABLES

header('Content-Type: text/plain');
$db_path = 'warehouse_v1.4.db';

if (!file_exists($db_path)) {
    die("Error: Database file '$db_path' not found.");
}

try {
    $conn = new PDO("sqlite:$db_path");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "--- DATABASE UPGRADE STARTED ---\n\n";

    // 1. Define the columns we need to ensure exist in 'transactions'
    $columns_to_add = [
        'profit'               => 'REAL DEFAULT 0.00',
        'fixed_price_at_sale'  => 'REAL DEFAULT 0.00',
        'branch_code'          => "TEXT DEFAULT 'HEAD_OFFICE'",
        'transaction_group_id' => "INTEGER DEFAULT 0"
    ];

    // Get current columns
    $stmt = $conn->query("PRAGMA table_info(transactions)");
    $existing_columns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existing_columns[] = $row['name'];
    }

    // 2. Add missing columns
    foreach ($columns_to_add as $col => $definition) {
        if (!in_array($col, $existing_columns)) {
            echo "Adding column '$col' to transactions table... ";
            $conn->exec("ALTER TABLE transactions ADD COLUMN $col $definition");
            echo "DONE.\n";
        } else {
            echo "Column '$col' already exists. Skipped.\n";
        }
    }

    echo "\n--- DATABASE UPGRADE COMPLETED SUCCESSFULLY ---\n";
    echo "You can now process sales without errors.";

} catch (Exception $e) {
    echo "\nCRITICAL ERROR: " . $e->getMessage();
}
?>