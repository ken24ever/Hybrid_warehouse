<?php
// setup_cloud_db.php (Run this on Hostinger Cloud Server)
//visit this address to set up the cloud DB: https://jemeraldstores.com/jemerald_api/database/setup_cloud_db.php
// VERSION: EXACT SCHEMA MATCH (Includes Online Indicator Fields)
header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- CLOUD CONFIGURATION ---
$cloud_host = 'localhost'; 
$cloud_user = 'u106033383_jemerald1234';
$cloud_pass = 'Wearelive_1234';
$cloud_name = 'u106033383_jemerald_cloud';

$mysqli = new mysqli($cloud_host, $cloud_user, $cloud_pass, $cloud_name);

if ($mysqli->connect_error) {
    die("<h3 style='color:red'>Connection Failed: " . $mysqli->connect_error . "</h3>");
}

echo "<h2>☁️ Cloud Database Schema Synchronizer</h2>";

// --- DEFINING THE EXACT SCHEMA FROM SQL DUMP ---
$queries = [];

// 1. AUDIT LOGS (Includes item_id)
$queries[] = "CREATE TABLE IF NOT EXISTS audit_logs (
  id bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  local_id int(11) NOT NULL,
  log_id int(11) DEFAULT NULL,
  branch_code varchar(50) NOT NULL,
  local_user_id int(11) NOT NULL,
  action text NOT NULL,
  item_id int(11) DEFAULT NULL,
  timestamp datetime DEFAULT NULL,
  synced_at timestamp NOT NULL DEFAULT current_timestamp()
)";

// 2. BRANCHES (Updated with Online Indicator fields)
$queries[] = "CREATE TABLE IF NOT EXISTS branches (
  id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  branch_name varchar(100) NOT NULL,
  branch_code varchar(50) NOT NULL UNIQUE,
  location varchar(255) DEFAULT NULL,
  created_at timestamp DEFAULT current_timestamp(),
  last_active_at DATETIME DEFAULT NULL,    -- [ADDED] For Online Indicator
  status VARCHAR(20) DEFAULT 'offline'     -- [ADDED] For Online Indicator
)";

// 3. CLOUD CHANGE LOG
$queries[] = "CREATE TABLE IF NOT EXISTS cloud_change_log (
  change_id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  table_name varchar(50) NOT NULL,
  record_id int(11) NOT NULL,
  branch_code varchar(50) NOT NULL,
  action_type enum('INSERT','UPDATE','DELETE') NOT NULL,
  timestamp timestamp NOT NULL DEFAULT current_timestamp()
)";

// 4. EMPLOYEES
$queries[] = "CREATE TABLE IF NOT EXISTS employees (
  id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name varchar(100) NOT NULL,
  role varchar(50) DEFAULT NULL,
  branch_code varchar(50) NOT NULL
)";

// 5. ITEMS
$queries[] = "CREATE TABLE IF NOT EXISTS items (
  id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  item_name varchar(255) NOT NULL,
  stock_quantity int(11) DEFAULT 0,
  price decimal(10,2) DEFAULT 0.00,
  branch_code varchar(50) NOT NULL,
  local_id int(11) NOT NULL,
  purchase_price decimal(10,2) DEFAULT 0.00,
  item_unique_no varchar(50) DEFAULT NULL
)";

// 6. ITEM CATEGORIES
$queries[] = "CREATE TABLE IF NOT EXISTS item_categories (
  id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  category_name varchar(100) NOT NULL,
  branch_code varchar(50) NOT NULL
)";

// 7. SUPPLIERS
$queries[] = "CREATE TABLE IF NOT EXISTS suppliers (
  id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  supplier_name varchar(255) NOT NULL,
  contact_info varchar(255) DEFAULT NULL,
  branch_code varchar(50) NOT NULL
)";

// 8. TRANSACTIONS
$queries[] = "CREATE TABLE IF NOT EXISTS transactions (
  id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  local_id int(11) NOT NULL,
  transaction_id int(11) DEFAULT NULL,
  branch_code varchar(50) NOT NULL,
  transaction_type varchar(50) DEFAULT NULL,
  modeOfPayment varchar(50) DEFAULT NULL,
  quantity int(11) DEFAULT 0,
  total_amount decimal(15,2) DEFAULT 0.00,
  profit_loss decimal(15,2) DEFAULT 0.00,
  transaction_date datetime DEFAULT NULL,
  synced_at timestamp NOT NULL DEFAULT current_timestamp(),
  profit decimal(15,2) DEFAULT 0.00,
  fixed_price_at_sale decimal(15,2) DEFAULT 0.00,
  sold_at decimal(15,2) DEFAULT 0.00,
  status int(11) DEFAULT 0,
  user_id int(11) DEFAULT 0,
  item_id int(11) DEFAULT 0,
  transaction_group_id varchar(50) DEFAULT NULL,
  local_user_id int(11) DEFAULT NULL
)";

// 9. USERS (Added local_id to link with local DB)
$queries[] = "CREATE TABLE IF NOT EXISTS users (
  id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username varchar(50) NOT NULL,
  email varchar(100) NOT NULL,
  password varchar(255) NOT NULL,
  role varchar(20) NOT NULL,
  branch_code varchar(50) NOT NULL,
  local_id int(11) DEFAULT 0  -- [ADDED] Links to local SQLite user_id
)";

// --- EXECUTE QUERIES ---
foreach ($queries as $sql) {
    if ($mysqli->query($sql) === TRUE) {
        // Table created successfully (silent success)
    } else {
        echo "<div style='color:red; margin-bottom:5px;'>Error creating table: " . $mysqli->error . "</div>";
    }
}

// --- SCHEMA UPDATES (Apply Changes to Existing Tables) ---
$updates = [
  // [CRITICAL FIX] Add user_id to audit_logs to fix "Unknown column" error
  "ALTER TABLE audit_logs ADD COLUMN IF NOT EXISTS user_id int(11) DEFAULT 0",
  
  // Ensure item_id exists in audit_logs
  "ALTER TABLE audit_logs ADD COLUMN IF NOT EXISTS item_id int(11) DEFAULT NULL",
  
  // Ensure transaction columns exist
  "ALTER TABLE transactions ADD COLUMN IF NOT EXISTS profit decimal(10,2) DEFAULT 0.00",
  "ALTER TABLE transactions ADD COLUMN IF NOT EXISTS fixed_price_at_sale decimal(10,2) DEFAULT 0.00"
];

foreach ($updates as $sql) {
  // We suppress errors here because "Duplicate column" is a harmless error
  if (!$mysqli->query($sql)) {
      // echo "<div>Update Note: " . $mysqli->error . "</div>"; // Uncomment for debug
  }
}

echo "<div style='color:green; border:1px solid green; padding:10px; margin-bottom:20px;'>
      ✅ All Tables Checked/Created Successfully! <br>
      <small>Schema is now synchronized with Local SQLite + Online Indicators.</small>
      </div>";


// --- BRANCH CREATION LOGIC ---
if (isset($_POST['create_branch'])) {
    $b_name = $mysqli->real_escape_string($_POST['branch_name']);
    $b_code = $mysqli->real_escape_string($_POST['branch_code']);
    $b_loc  = $mysqli->real_escape_string($_POST['location']);

    // Check duplicate
    $check = $mysqli->query("SELECT id FROM branches WHERE branch_code = '$b_code'");
    if ($check->num_rows > 0) {
        echo "<div style='color:red;'>❌ Branch Code '$b_code' already exists!</div>";
    } else {
        $insert = "INSERT INTO branches (branch_name, branch_code, location, status) VALUES ('$b_name', '$b_code', '$b_loc', 'offline')";
        if ($mysqli->query($insert)) {
            echo "<div style='color:green;'>✅ Branch '$b_name' Created!</div>";
        } else {
            echo "<div style='color:red;'>Error: " . $mysqli->error . "</div>";
        }
    }
}
?>

<div style="background:#f9f9f9; padding:20px; border:1px solid #ddd; max-width:600px; margin-top:20px;">
    <h3>🏢 Create New Branch</h3>
    <form method="POST">
        <div style="margin-bottom: 10px;">
            <label style="font-weight:bold; display:block; margin-bottom:5px;">Branch Name:</label>
            <input type="text" name="branch_name" required placeholder="e.g. Uselu Branch" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px;">
        </div>

        <div style="margin-bottom: 10px;">
            <label style="font-weight:bold; display:block; margin-bottom:5px;">Branch Code (Unique ID):</label>
            <input type="text" name="branch_code" required placeholder="e.g. USELU_001" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px;">
            <small style="color:#666;">This code must be unique and used in the local setup.</small>
        </div>

        <div style="margin-bottom: 20px;">
            <label style="font-weight:bold; display:block; margin-bottom:5px;">Location / Address:</label>
            <input type="text" name="location" required placeholder="e.g. 12 Akpakpava Road" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px;">
        </div>

        <button type="submit" name="create_branch" style="background:#007bff; color:white; padding:12px 25px; border:none; border-radius:4px; font-size:16px; cursor:pointer; width:100%;">
            Create Branch
        </button>

    </form>

    <br>
    <h4>📋 Current Registered Branches:</h4>
    <ul style="list-style-type: none; padding: 0;">
        <?php
        $res = $mysqli->query("SELECT * FROM branches ORDER BY id DESC");
        if ($res->num_rows > 0) {
            while($row = $res->fetch_assoc()) {
                echo "<li style='background:#fff; border-bottom:1px solid #eee; padding:10px;'>
                        <b>{$row['branch_name']}</b> <span style='color:blue; font-size:0.9em;'>({$row['branch_code']})</span> - <small>{$row['location']}</small>
                        <br> <small>Status: {$row['status']} | Last Active: " . ($row['last_active_at'] ?? 'Never') . "</small>
                      </li>";
            }
        } else {
            echo "<li>No branches found.</li>";
        }
        ?>
    </ul>
</div>