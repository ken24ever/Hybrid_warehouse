<?php
session_start();

// Enable error reporting but strictly capture it
error_reporting(E_ALL);
ini_set('display_errors', 0);

// [PROFESSIONAL FIX] Turn off automatic mysqli exceptions to prevent 500 Fatal Crashes
// This forces mysqli to return false on errors so we can catch and read them.
mysqli_report(MYSQLI_REPORT_OFF);

header('Content-Type: application/json');

// Start a global try-catch to catch ANY fatal errors
try {
    include_once 'connection.php';

    $db_errors = [];

    // ==================================================================
    // 1. DYNAMIC CONTEXT RESOLUTION (Local vs Remote)
    // ==================================================================
    $session_branch = $_SESSION['branch_code'] ?? '';
    $requested_branch = isset($_GET['branch_code']) && !empty($_GET['branch_code']) ? $_GET['branch_code'] : $session_branch;

    $is_remote = ($requested_branch !== $session_branch);

    $cloud_conn = null;
    if ($is_remote) {
        $cloud_conn = @new mysqli('srv1254.hstgr.io', 'u106033383_jemerald1234', 'Wearelive_1234', 'u106033383_jemerald_cloud');
        if ($cloud_conn->connect_error) {
            throw new Exception("Cloud Connection Failed: " . $cloud_conn->connect_error);
        }
    }

    $branchSql = "";
    $userBranchSql = "";       
    $userTableBranchSql = ""; 

    if (!empty($requested_branch)) {
        if ($is_remote) {
            $safeBranch = $cloud_conn->real_escape_string($requested_branch);
        } else {
            $safeBranch = SQLite3::escapeString($requested_branch);
        }
        $branchSql = " AND branch_code = '$safeBranch' ";
        $userBranchSql = " AND t.branch_code = '$safeBranch' ";
        $userTableBranchSql = " AND u.branch_code = '$safeBranch' "; 
    }

    // --- 1. Transaction Summary ---
    $query = "
        SELECT 
            COALESCE(SUM(CASE WHEN transaction_type = 'sale' THEN total_amount ELSE 0 END), 0) AS total_sales,
            COALESCE(SUM(CASE WHEN transaction_type = 'purchase' THEN total_amount ELSE 0 END), 0) AS total_purchases,
            COALESCE(SUM(CASE WHEN transaction_type = 'adjustment' THEN total_amount ELSE 0 END), 0) AS total_adjustments
        FROM transactions 
        WHERE status IN (0, 1)
        $branchSql
    ";

    $transactionSummary = ["sale" => 0, "purchase" => 0, "adjustment" => 0];

    if ($is_remote) {
        $result = $cloud_conn->query($query);
        if ($result) {
            $data = $result->fetch_assoc();
            $transactionSummary = [
                "sale" => $data['total_sales'] ?? 0,
                "purchase" => $data['total_purchases'] ?? 0,
                "adjustment" => $data['total_adjustments'] ?? 0
            ];
        } else {
            $db_errors[] = "Query 1 (Summary) Failed: " . $cloud_conn->error;
        }
    } else {
        $result = $conn->query($query);
        if ($result) {
            $data = $result->fetchArray(SQLITE3_ASSOC); 
            $transactionSummary = [
                "sale" => $data['total_sales'] ?? 0,
                "purchase" => $data['total_purchases'] ?? 0,
                "adjustment" => $data['total_adjustments'] ?? 0
            ];
        }
    }

    // --- 2. Profit and Loss ---
    $query_profit_loss = "
        SELECT 
            COALESCE(SUM(CASE WHEN profit_loss > 0 THEN profit_loss ELSE 0 END), 0) AS total_profit,
            COALESCE(SUM(CASE WHEN profit_loss < 0 THEN profit_loss ELSE 0 END), 0) AS total_loss
        FROM transactions
        WHERE status IN (0, 1) 
        $branchSql
    ";

    $totalProfit = 0;
    $totalLoss = 0;

    if ($is_remote) {
        $result_profit_loss = $cloud_conn->query($query_profit_loss);
        if ($result_profit_loss) {
            $profit_loss_data = $result_profit_loss->fetch_assoc();
            $totalProfit = $profit_loss_data['total_profit'] ?? 0;
            $totalLoss = $profit_loss_data['total_loss'] ?? 0;
        } else {
            $db_errors[] = "Query 2 (P&L) Failed: " . $cloud_conn->error;
        }
    } else {
        $result_profit_loss = $conn->query($query_profit_loss);
        if ($result_profit_loss) {
            $profit_loss_data = $result_profit_loss->fetchArray(SQLITE3_ASSOC);
            $totalProfit = $profit_loss_data['total_profit'] ?? 0;
            $totalLoss = $profit_loss_data['total_loss'] ?? 0;
        }
    }

    // --- 3. User Sales Performance ---
    $userSales = [];

    if ($is_remote) {
        $query_user_sales = "
            SELECT u.username, COALESCE(SUM(t.total_amount), 0) AS total_sales
            FROM users u
            LEFT JOIN transactions t ON (t.user_id = u.id OR t.local_user_id = u.local_id) 
                AND t.transaction_type = 'sale' 
                AND t.status IN (0, 1) $userBranchSql
            WHERE (u.role_name IN ('Admin', 'Sales Manager', 'Cashier', 'Super Admin') OR u.role_name IS NULL OR u.role_name = '') 
            $userTableBranchSql
            GROUP BY u.username
        ";
        $result_user_sales = $cloud_conn->query($query_user_sales);
        
        if ($result_user_sales) {
            while ($row = $result_user_sales->fetch_assoc()) {
                $userSales[$row['username']] = $row['total_sales'];
            }
        } else {
            $db_errors[] = "Query 3 (User Sales) Failed: " . $cloud_conn->error;
        }
    } else {
        $query_user_sales = "
            SELECT u.username, COALESCE(SUM(t.total_amount), 0) AS total_sales
            FROM users u
            LEFT JOIN transactions t ON u.user_id = t.user_id AND t.transaction_type = 'sale' AND t.status IN (0, 1) $userBranchSql
            WHERE u.role_id IN (1, 2, 3) $userTableBranchSql
            GROUP BY u.username
        ";
        $result_user_sales = $conn->query($query_user_sales);
        
        if ($result_user_sales) {
            while ($row = $result_user_sales->fetchArray(SQLITE3_ASSOC)) {
                $userSales[$row['username']] = $row['total_sales'];
            }
        }
    }

    // --- 4. Monthly Data Retrieval ---
    $currentYear = date("Y");
    $prevYear = $currentYear - 1;
    $currentMonth = (int)date("n"); 
    $startTrackingDate = "$prevYear-10-01"; 

    $dateFormatSql = $is_remote ? "DATE_FORMAT(transaction_date, '%Y-%m')" : "strftime('%Y-%m', transaction_date)";

    // [PROFESSIONAL FIX] Changed alias from 'year_month' (Reserved Keyword) to 'txn_month'
    $query_monthly = "
        SELECT 
            $dateFormatSql AS txn_month,
            COALESCE(SUM(CASE WHEN profit_loss > 0 THEN profit_loss ELSE 0 END), 0) AS monthly_profit,
            COALESCE(SUM(CASE WHEN profit_loss < 0 THEN profit_loss ELSE 0 END), 0) AS monthly_loss
        FROM transactions
        WHERE transaction_date >= '$startTrackingDate'
        AND status IN (0, 1)
        $branchSql
        GROUP BY $dateFormatSql
        ORDER BY txn_month ASC
    ";

    $historyData = [];
    if ($is_remote) {
        $result_monthly = $cloud_conn->query($query_monthly);
        if ($result_monthly) {
            while ($row = $result_monthly->fetch_assoc()) {
                $historyData[$row['txn_month']] = [
                    'profit' => $row['monthly_profit'],
                    'loss' => abs($row['monthly_loss'])
                ];
            }
        } else {
            $db_errors[] = "Query 4 (Monthly) Failed: " . $cloud_conn->error;
        }
    } else {
        $result_monthly = $conn->query($query_monthly);
        if ($result_monthly) {
            while ($row = $result_monthly->fetchArray(SQLITE3_ASSOC)) {
                $historyData[$row['txn_month']] = [
                    'profit' => $row['monthly_profit'],
                    'loss' => abs($row['monthly_loss'])
                ];
            }
        }
    }

    if ($is_remote && $cloud_conn) {
        $cloud_conn->close();
    }

    // Analytics Math
    $rawProfits = []; $rawLosses = [];
    for ($m = 10; $m <= 12; $m++) {
        $key = "$prevYear-" . str_pad($m, 2, "0", STR_PAD_LEFT);
        $rawProfits[] = isset($historyData[$key]) ? $historyData[$key]['profit'] : 0;
        $rawLosses[] = isset($historyData[$key]) ? $historyData[$key]['loss'] : 0;
    }
    for ($m = 1; $m <= $currentMonth; $m++) {
        $key = "$currentYear-" . str_pad($m, 2, "0", STR_PAD_LEFT);
        $rawProfits[] = isset($historyData[$key]) ? $historyData[$key]['profit'] : 0;
        $rawLosses[] = isset($historyData[$key]) ? $historyData[$key]['loss'] : 0;
    }

    function stripLeadingZeros($data) {
        $cleaned = []; $started = false;
        foreach ($data as $val) {
            if ($val > 0) $started = true;
            if ($started) $cleaned[] = $val;
        }
        return empty($cleaned) ? [0] : $cleaned;
    }

    $cleanProfits = stripLeadingZeros($rawProfits);
    $cleanLosses = stripLeadingZeros($rawLosses);

    function calculateHoltForecast($data, $monthsToProject, $alpha = 0.5, $beta = 0.3) {
        $count = count($data);
        if ($count < 2) return array_pad([], $monthsToProject, $count > 0 ? end($data) : 0);
        $forecasts = [];
        $level = $data[0]; $trend = $data[1] - $data[0];
        for ($i = 1; $i < $count; $i++) {
            $lastLevel = $level; $lastTrend = $trend; $actual = $data[$i];
            $level = $alpha * $actual + (1 - $alpha) * ($lastLevel + $lastTrend);
            $trend = $beta * ($level - $lastLevel) + (1 - $beta) * $lastTrend;
        }
        for ($m = 1; $m <= $monthsToProject; $m++) {
            $forecasts[] = max(0, round($level + ($m * $trend), 2));
        }
        return $forecasts;
    }

    $monthsRemaining = 12 - $currentMonth;
    $projectedProfits = calculateHoltForecast($cleanProfits, $monthsRemaining, 0.4, 0.2);
    $projectedLosses = calculateHoltForecast($cleanLosses, $monthsRemaining, 0.4, 0.2);

    $finalProfitData = []; $finalLossData = [];
    for ($m = 1; $m <= $currentMonth; $m++) {
        $key = "$currentYear-" . str_pad($m, 2, "0", STR_PAD_LEFT);
        $finalProfitData[] = isset($historyData[$key]) ? $historyData[$key]['profit'] : 0;
        $finalLossData[] = isset($historyData[$key]) ? $historyData[$key]['loss'] : 0;
    }

    $finalProfitData = array_merge($finalProfitData, $projectedProfits);
    $finalLossData = array_merge($finalLossData, $projectedLosses);
    $months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
    $chartLabels = array_map(fn($m) => "$m $currentYear", $months);

    // If there were query errors, force them into the console payload for debugging
    if (count($db_errors) > 0) {
        echo json_encode(["status" => "sql_error", "message" => "Database Query Failed.", "db_errors" => $db_errors]);
        exit;
    }

    echo json_encode([
        "status" => "success",
        "totalProfit" => $totalProfit,
        "totalLoss" => $totalLoss,
        "transactionSummary" => $transactionSummary,
        "profitProjections" => ["months" => $chartLabels, "values" => $finalProfitData],
        "lossProjections" => ["months" => $chartLabels, "values" => $finalLossData],
        "userSales" => $userSales,
        "debug_branch" => $requested_branch,
        "mode" => $is_remote ? "cloud" : "local"
    ]);

} catch (Throwable $e) {
    // ------------------------------------------------------------------
    // [PROFESSIONAL FIX] The ultimate safety net. 
    // Captures the fatal error causing the 500 status and prints it to JSON.
    // ------------------------------------------------------------------
    http_response_code(200); // Override 500 error so JS can read the response
    echo json_encode([
        "status" => "fatal_crash",
        "error_message" => $e->getMessage(),
        "file" => $e->getFile(),
        "line" => $e->getLine()
    ]);
    exit;
}
?>