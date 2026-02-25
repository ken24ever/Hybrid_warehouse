<?php
// getAllTransactions.php
// VERSION: REMOTE BRANCH VIEWING FIX (Force Cloud + Strict Filtering)
session_start();
header("Content-Type: application/json");

// 1. INCLUDE THE CENTRAL DB MANAGER
// Ensure strict path checking
if (file_exists('DBManager.php')) { require_once 'DBManager.php'; } 
elseif (file_exists('../DBManager.php')) { require_once '../DBManager.php'; }
else { echo json_encode(['error' => 'DBManager missing']); exit; }

// 2. DETECT CONTEXT
// Priority: GET Request (Super Admin View) > Session (Local View)
$target_branch = '';

if (isset($_GET['branch_code']) && !empty($_GET['branch_code'])) {
    $target_branch = trim($_GET['branch_code']);
} elseif (isset($_SESSION['branch_code'])) {
    $target_branch = $_SESSION['branch_code'];
} else {
    $target_branch = 'HEAD_OFFICE'; // Fallback
}

// 3. GET INTELLIGENT CONNECTION
$dbManager = new DBManager();

// [CRITICAL FIX]: We request 'GLOBAL_DASHBOARD' to force DBManager to try CLOUD first.
// If we just requested $target_branch, DBManager might say "Oh, that matches local default" and give us SQLite.
// But we need Cloud if we are at USELU viewing HEAD_OFFICE.
$pdo = $dbManager->getConnection('GLOBAL_DASHBOARD');

$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$retail_col = ($driver === 'sqlite') ? 'retail' : 'retail_price';
$wholesale_col = ($driver === 'sqlite') ? 'wholesale' : 'wholesale_price';

// --- 4. THE BUILD QUERY FUNCTION (Now with Strict Filtering) ---
function buildQuery($pdo, $metricType, $target_branch) {
    global $retail_col, $wholesale_col;

    $today = date('Y-m-d');
    $current_month = date('Y-m');

    try {
        $sql = "";
        $params = [':branch' => $target_branch];

        switch ($metricType) {
            case 'total_sales':
                // Sum ALL sales for this branch
                $sql = "SELECT SUM(total_amount) as val FROM transactions WHERE branch_code = :branch AND transaction_type = 'sale' AND status = 0";
                break;

            case 'month_sales':
                // Sum sales for current Month
                $sql = "SELECT SUM(total_amount) as val FROM transactions 
                        WHERE branch_code = :branch AND transaction_type = 'sale'  AND status = 0
                        AND transaction_date LIKE :dateFilter";
                $params[':dateFilter'] = "$current_month%";
                break;

            case 'today_sales':
                // Sum sales for Today
                $sql = "SELECT SUM(total_amount) as val FROM transactions 
                        WHERE branch_code = :branch AND transaction_type = 'sale' AND status = 0 
                        AND transaction_date LIKE :dateFilter";
                $params[':dateFilter'] = "$today%";
                break;

            case 'stock_value':
                // Inventory Value (Purchase Price * Qty)
                $sql = "SELECT SUM(purchase_price * quantity_in_stock) as val FROM items WHERE branch_code = :branch AND status = 0";
                break;

            case 'gross_value':
                // Potential Revenue (Retail Price * Qty)
                // Note: Using retail price for gross estimation
                $sql = "SELECT SUM($retail_col * quantity_in_stock) as val FROM items WHERE branch_code = :branch AND status = 0";
                break;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row['val'] ? floatval($row['val']) : 0.00;

    } catch (Exception $e) {
        return 0.00;
    }
}

// --- 5. EXECUTE QUERIES ---

$total_sales               = buildQuery($pdo, 'total_sales', $target_branch);
$current_month_total_sales = buildQuery($pdo, 'month_sales', $target_branch);
$current_day_total_sales   = buildQuery($pdo, 'today_sales', $target_branch);

$stock_value = buildQuery($pdo, 'stock_value', $target_branch);
$gross_value = buildQuery($pdo, 'gross_value', $target_branch);
$net_value   = $gross_value - $stock_value; // Estimated Profit Potential

// --- 6. RETURN RESPONSE ---
echo json_encode([
    "success" => true,
    "branch_viewed" => $target_branch,
    "source" => $dbManager->current_source, // Debug: 'cloud' or 'local'
    "total_sales"                => number_format($total_sales, 2, '.', ''),
    "current_month_total_sales"  => number_format($current_month_total_sales, 2, '.', ''),
    "current_day_total_sales"    => number_format($current_day_total_sales, 2, '.', ''),
    "stock_value"                => number_format($stock_value, 2, '.', ''),
    "gross_value"                => number_format($gross_value, 2, '.', ''),
    "net_value"                  => number_format($net_value, 2, '.', '')
]);
?>