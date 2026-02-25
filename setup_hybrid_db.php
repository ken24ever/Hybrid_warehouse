<?php
// setup_hybrid_db.php
// VERSION: DYNAMIC BRANCH INPUT & FULL SCHEMA SYNC
header('Content-Type: text/html; charset=utf-8');

$db_file_name = 'warehouse_v2.0.db';
$message = "";

// 1. DATABASE CONNECTION
$base_dir = dirname(__DIR__);
$db_path = $base_dir . '/database/' . $db_file_name;
if (!file_exists($db_path)) { $db_path = $db_file_name; } // Fallback

// 2. HANDLE FORM SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_branch = trim($_POST['branch_name']);
    
    if (empty($target_branch)) {
        $message = "<div class='alert alert-danger'>Error: Branch Name is required.</div>";
    } else {
        try {
            if (!file_exists($db_path)) { throw new Exception("Database file not found at $db_path"); }

            $pdo = new PDO("sqlite:$db_path");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // --- A. DEFINE TABLES TO HYBRIDIZE ---
            // These tables need 'branch_code' and 'sync_status'
            $hybrid_tables = [
                'users', 
                'items', 
                'transactions', 
                'audit_logs', 
                'employees', 
                'suppliers', 
                'item_categories'
            ];

            $log = "<h4>Execution Log:</h4>";

            // --- B. ADD COLUMNS (Safe Alter) ---
            foreach ($hybrid_tables as $table) {
                // 1. Add branch_code
                if (!columnExists($pdo, $table, 'branch_code')) {
                    $pdo->exec("ALTER TABLE $table ADD COLUMN branch_code TEXT DEFAULT NULL");
                    $log .= "<div>Values: Added <b>branch_code</b> to <i>$table</i>.</div>";
                }
                
                // 2. Add sync_status
                if (!columnExists($pdo, $table, 'sync_status')) {
                    $pdo->exec("ALTER TABLE $table ADD COLUMN sync_status INTEGER DEFAULT 0");
                    $log .= "<div>Values: Added <b>sync_status</b> to <i>$table</i>.</div>";
                }
            }

            // --- C. BACKFILL DATA ---
            $log .= "<hr><strong>Backfilling Data for Branch: $target_branch</strong><br>";
            
            foreach ($hybrid_tables as $table) {
                $sql = "UPDATE $table SET branch_code = :code WHERE branch_code IS NULL OR branch_code = ''";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':code' => $target_branch]);
                $count = $stmt->rowCount();
                if ($count > 0) $log .= "<div>Updated $count rows in <i>$table</i>.</div>";
            }

            // --- D. TRIGGERS ---
            $triggerSQL = "
            CREATE TRIGGER IF NOT EXISTS update_items_timestamp 
            AFTER UPDATE ON items 
            BEGIN 
                UPDATE items SET updated_at = datetime('now') WHERE item_id = new.item_id; 
            END;";
            $pdo->exec($triggerSQL);

            $message = "<div class='alert alert-success'><h3>Success!</h3><p>System setup for <strong>$target_branch</strong>.</p>$log</div>";

        } catch (Exception $e) {
            $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
        }
    }
}

// Helper Function
function columnExists($pdo, $table, $col) {
    try {
        $stmt = $pdo->query("PRAGMA table_info($table)");
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $c) {
            if ($c['name'] === $col) return true;
        }
        return false;
    } catch (Exception $e) { return false; }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hybrid DB Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">⚙️ Local Database Hybrid Setup</h4>
            </div>
            <div class="card-body">
                <p>This script upgrades your local SQLite database (`<?php echo $db_file_name; ?>`) to support Cloud Synchronization.</p>
                
                <?php echo $message; ?>

                <?php if ($_SERVER['REQUEST_METHOD'] !== 'POST' || strpos($message, 'alert-danger') !== false): ?>
                <form method="POST">
                    <div class="mb-3">
                        <label for="branch_name" class="form-label"><strong>Set Default Branch Code</strong></label>
                        <input type="text" class="form-control form-control-lg" id="branch_name" name="branch_name" 
                               placeholder="e.g. HEAD_OFFICE, USELU_OFFICE, BENIN_BRANCH" required>
                        <div class="form-text">All existing data will be assigned to this branch.</div>
                    </div>
                    <button type="submit" class="btn btn-success btn-lg w-100">Run Setup</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>