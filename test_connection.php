<?php
// test_connection.php
// VERSION: ROBUST DIAGNOSTIC TOOL
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// --- API HANDLER ---
if (isset($_GET['action']) && $_GET['action'] === 'run_diagnostic') {
    header('Content-Type: application/json');
    
    // Credentials (Same as DBManager)
    $host = 'srv1254.hstgr.io';
    $db   = 'u106033383_jemerald_cloud';
    $user = 'u106033383_jemerald1234';
    $pass = 'Wearelive_1234';

    $start_time = microtime(true);
    
    try {
        // 1. Attempt Connection
        $dsn = "mysql:host=$host;dbname=$db;port=3306;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 8, // Fail fast (8 seconds)
            PDO::ATTR_PERSISTENT => false // Disable persistence for testing to force fresh handshake
        ];

        $pdo = new PDO($dsn, $user, $pass, $options);

        // 2. Run functionality test
        $stmt = $pdo->query("SELECT 1");
        $stmt->fetch();

        // 3. Calculate Latency
        $end_time = microtime(true);
        $duration = round(($end_time - $start_time) * 1000, 2); // ms

        // Determine Health Quality (Adjusted for Nigeria Region)
        $quality = 'Excellent';
        
        // Threshold A: 400ms (Reasonable for Cross-Continent requests)
        if ($duration > 400) $quality = 'Fair'; 
        
        // Threshold B: 1000ms (1 Second is the limit for "feeling" slow)
        if ($duration > 1000) $quality = 'Poor/Slow';

        echo json_encode([
            'status' => 'success',
            'message' => 'Connection Established',
            'latency' => $duration . ' ms',
            'quality' => $quality
        ]);

    } catch (PDOException $e) {
        // 4. Analyze Errors
        $code = $e->getCode();
        $msg = $e->getMessage();
        $driverCode = $e->errorInfo[1] ?? 0; // MySQL specific error code

        $diagnosis = "Unknown Connectivity Issue";
        $suggestion = "Check your internet connection and try again.";
        $is_critical = false;

        // ERROR MAPPING
        if ($driverCode == 1040 || $driverCode == 1203 || $driverCode == 1226) {
            $diagnosis = "⚠️ Maximum Connection Limit Reached";
            $suggestion = "The database is currently overloaded with too many active users. Please wait for about 1 hour and try again.";
            $is_critical = true;
        } 
        elseif ($driverCode == 2002) {
            $diagnosis = "❌ Server Unreachable (Network Error)";
            $suggestion = "Your device cannot reach the cloud server. Check your Wi-Fi/Internet connection or Firewall settings.";
            $is_critical = true;
        } 
        elseif ($driverCode == 1045) {
            $diagnosis = "🔒 Authentication Failed";
            $suggestion = "Incorrect username or password configuration.";
            $is_critical = true;
        }

        echo json_encode([
            'status' => 'error',
            'title' => $diagnosis,
            'message' => $msg,
            'suggestion' => $suggestion,
            'code' => $driverCode
        ]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Diagnostics | Jemerald Stores</title>
    
    <link rel="stylesheet" href="bootstrap_v4/css/bootstrap.min.css">
    <link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css">
    
    <style>
        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .diag-card {
            width: 100%;
            max-width: 600px;
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
            background: white;
        }
        .diag-header {
            background: linear-gradient(135deg, #1a2980 0%, #26d0ce 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .pulse-icon {
            font-size: 3rem;
            margin-bottom: 10px;
        }
        .btn-run {
            border-radius: 50px;
            padding: 12px 30px;
            font-weight: 600;
            letter-spacing: 1px;
            transition: all 0.3s;
        }
        .btn-run:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .result-box {
            display: none;
            margin-top: 20px;
            padding: 20px;
            border-radius: 10px;
            animation: fadeIn 0.5s;
        }
        .spinner-border {
            width: 3rem; height: 3rem;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        /* Status Colors */
        .status-success { color: #28a745; }
        .status-danger { color: #dc3545; }
        .status-warning { color: #ffc107; }

        .detail-row {
            display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee;
        }
        .detail-row:last-child { border-bottom: none; }
    </style>
</head>
<body>

    <div class="diag-card">
        <div class="diag-header">
            <i class="fas fa-satellite-dish pulse-icon"></i>
            <h3>Server Connectivity Diagnostics</h3>
            <p class="mb-0 opacity-75">Cloud Database Health Check</p>
        </div>
        
        <div class="card-body p-4 text-center">
            
            <p class="text-muted mb-4">
                Click the button below to initiate a real-time connection test to the Hostinger Cloud Database. 
                This will check for latency, timeouts, and connection limits.
            </p>

            <button id="runTestBtn" class="btn btn-primary btn-run btn-lg">
                <i class="fas fa-play mr-2"></i> RUN DIAGNOSTICS
            </button>

            <a href="hub_dashboard.php" class="btn btn-outline-secondary btn-run btn-lg ml-2">
                <i class="fas fa-arrow-left mr-2"></i> Back to Hub
            </a>

            <div id="loader" class="mt-4" style="display: none;">
                <div class="spinner-border text-primary text-center" role="status"></div>
                <p class="mt-2 font-weight-bold text-primary">Testing Connection...</p>
                <small class="text-muted">Establishing handshake with srv1254.hstgr.io</small>
            </div>

            <div id="successBox" class="result-box bg-light border-success" style="border-left: 5px solid #28a745;">
                <h4 class="text-success"><i class="fas fa-check-circle mr-2"></i>System Online</h4>
                <p>The connection to the cloud database is stable.</p>
                
                <div class="detail-row">
                    <span class="text-muted">Status</span>
                    <span class="font-weight-bold text-success">Connected</span>
                </div>
                <div class="detail-row">
                    <span class="text-muted">Latency</span>
                    <span class="font-weight-bold" id="latencyVal">--</span>
                </div>
                <div class="detail-row">
                    <span class="text-muted">Connection Quality</span>
                    <span class="font-weight-bold" id="qualityVal">--</span>
                </div>
            </div>

            <div id="errorBox" class="result-box bg-light border-danger" style="border-left: 5px solid #dc3545;">
                <h4 class="text-danger" id="errorTitle"><i class="fas fa-exclamation-triangle mr-2"></i>Connection Failed</h4>
                <p id="errorMsg" class="text-dark">Unable to establish connection.</p>
                
                <div class="alert alert-warning mt-3 text-left">
                    <strong><i class="fas fa-lightbulb mr-1"></i> Suggestion:</strong>
                    <span id="suggestionVal">Check internet.</span>
                </div>
                
                <div class="text-left mt-2">
                    <small class="text-muted">Technical Code: <span id="errorCode"></span></small>
                </div>
            </div>

        </div>
    </div>

    <script src="jquery/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#runTestBtn').click(function() {
                // UI Reset
                $(this).prop('disabled', true);
                $('#successBox, #errorBox').hide();
                $('#loader').fadeIn();

                // Run AJAX Test
                $.ajax({
                    url: 'test_connection.php',
                    type: 'GET',
                    data: { action: 'run_diagnostic' },
                    dataType: 'json',
                    timeout: 10000, // Client side timeout
                    success: function(response) {
                        $('#loader').hide();
                        $('#runTestBtn').prop('disabled', false).html('<i class="fas fa-redo mr-2"></i> RETEST');

                        if (response.status === 'success') {
                            $('#latencyVal').text(response.latency);
                            $('#qualityVal').text(response.quality);
                            $('#successBox').fadeIn();
                        } else {
                            showError(response);
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#loader').hide();
                        $('#runTestBtn').prop('disabled', false).html('<i class="fas fa-redo mr-2"></i> RETEST');
                        
                        let mockResponse = {
                            title: "❌ Request Timed Out",
                            message: "The server took too long to respond. This usually indicates a poor internet connection or the server is down.",
                            suggestion: "Check your local network signal.",
                            code: "TIMEOUT"
                        };
                        showError(mockResponse);
                    }
                });
            });

            function showError(data) {
                $('#errorTitle').html(data.title);
                $('#errorMsg').text(data.message);
                $('#suggestionVal').text(data.suggestion);
                $('#errorCode').text(data.code);
                $('#errorBox').fadeIn();
            }
        });
    </script>
</body>
</html>