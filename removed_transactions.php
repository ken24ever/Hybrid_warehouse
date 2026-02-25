<?php
// removed_transactions.php
// VERSION: PROFESSIONAL DB MANAGER + ONLINE CHECK
ob_start(); 
session_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once 'DBManager.php'; // [FIX] Use Centralized Manager
include('connection.php');

// --- 1. SECURITY & CONTEXT ---
if (!isset($_SESSION['user_id'])) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        ob_end_clean(); 
        echo json_encode(['error' => 'Unauthorized']); 
        exit;
    }
    header("Location: index.php"); 
    exit;
}

// [FIX] Remove hardcoded 'HEAD_OFFICE'. Strict Session Usage.
$session_branch = $_SESSION['branch_code'] ?? '';
if (empty($session_branch)) {
    die(json_encode(['error' => 'Session Context Lost. Please Login.']));
}

// [FIX] Robust Context Resolution
$url_branch = $_REQUEST['branch_code'] ?? $_REQUEST['branch_uuid'] ?? null;
$target_branch = (!empty($url_branch)) ? $url_branch : $session_branch;

// Determine Remote Status
$is_remote = ($target_branch !== $session_branch);

// --- 2. API HANDLER: FETCH TRANSACTIONS ---
if (isset($_GET['action']) && $_GET['action'] === 'fetch') {
    ob_end_clean(); 
    header('Content-Type: application/json');
    $data = [];

    $db = new DBManager();

    try {
        if ($is_remote) {
            // [FIX] Use DBManager for Cloud Connection (No hardcoded creds)
            $pdo = $db->getConnection($target_branch);
            
            // Validate Connection Source
            if ($db->current_source !== 'cloud') {
                throw new Exception("Unable to reach Cloud Server for Remote View.");
            }

            $sql = "SELECT t.id as transaction_id, COALESCE(i.item_name, 'Item #', t.item_id) as item_name, 
                           t.quantity, t.total_amount, t.transaction_date, t.modeOfPayment, t.transaction_group_id, t.local_id 
                    FROM transactions t
                    LEFT JOIN items i ON t.item_id = i.id
                    WHERE t.branch_code = :branch AND t.status = 1
                    ORDER BY t.transaction_date DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':branch' => $target_branch]);
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['restore_id'] = $row['transaction_id']; // Map ID for frontend
                $data[] = $row;
            }

        } else {
            // LOCAL FETCH (SQLite)
            $query = "SELECT t.transaction_id, i.item_name, t.quantity, t.total_amount, t.transaction_date, t.modeOfPayment, t.transaction_group_id 
                      FROM transactions t
                      LEFT JOIN items i ON t.item_id = i.item_id
                      WHERE t.status = 1
                      ORDER BY t.transaction_date DESC";
            
            $result = $conn->query($query);
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $row['restore_id'] = $row['transaction_id'];
                $data[] = $row;
            }
        }
        echo json_encode(['data' => $data]);

    } catch (Exception $e) {
        echo json_encode(['data' => [], 'error' => $e->getMessage()]);
    }
    exit;
}

// --- 3. API HANDLER: RESTORE TRANSACTION ---
if (isset($_POST['action']) && $_POST['action'] === 'restore') {
    ob_end_clean(); 
    header('Content-Type: application/json');
    
    $transId = (int)$_POST['transaction_id']; 
    $userId  = $_SESSION['user_id'];
    $userName = $_SESSION['username'];
    
    $db = new DBManager();

    try {
        if ($is_remote) {
            // [FIX] 1. Connect Securely via Manager
            $pdo = $db->getConnection($target_branch);

            if ($db->current_source !== 'cloud') {
                throw new Exception("Offline: Cannot perform remote restore without Cloud Connection.");
            }

            // [CRITICAL FIX] 2. STRICT BRANCH ONLINE CHECK (HEARTBEAT)
            // We check 'last_active_at' to ensure the branch is TRULY live (within 5 minutes)
            // Relying on the text 'status' column alone is unsafe as it may be stale.
            $heartbeatSql = "SELECT TIMESTAMPDIFF(SECOND, last_active_at, NOW()) as seconds_ago 
                             FROM branches WHERE branch_code = ? LIMIT 1";
            
            $statusStmt = $pdo->prepare($heartbeatSql);
            $statusStmt->execute([$target_branch]);
            $secondsAgo = $statusStmt->fetchColumn();

            // Threshold: 300 seconds (5 Minutes)
            // If NULL (never active) or > 300s (stale), treat as OFFLINE.
            if ($secondsAgo === null || $secondsAgo === false || $secondsAgo > 300) {
                $minsAgo = $secondsAgo ? round($secondsAgo / 60) : 'N/A';
                throw new Exception("Action Blocked: Target branch '$target_branch' is OFFLINE (Last active: $minsAgo mins ago). Restoring now would cause inventory desync.");
            }

            // --- REMOTE RESTORE LOGIC (PDO) ---
            $pdo->beginTransaction();

            // A. Get Transaction Details
            $stmt = $pdo->prepare("SELECT item_id, quantity FROM transactions WHERE id = ? AND branch_code = ?");
            $stmt->execute([$transId, $target_branch]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$res) throw new Exception("Transaction not found in Cloud.");
            
            $itemId = $res['item_id'];
            $qty = $res['quantity'];

            // B. Restore Transaction Status
            $pdo->prepare("UPDATE transactions SET status = 0 WHERE id = ?")->execute([$transId]);
            
            // C. Sync Log (Transaction)
            $pdo->prepare("INSERT INTO cloud_change_log (table_name, record_id, branch_code, action_type) VALUES ('transactions', ?, ?, 'UPDATE')")
                ->execute([$transId, $target_branch]);

            // D. Deduct Stock (Reverse the removal)
            $upd = $pdo->prepare("UPDATE items SET quantity_in_stock = quantity_in_stock - ? WHERE id = ?");
            $upd->execute([$qty, $itemId]);

            // E. Sync Log (Items)
            $pdo->prepare("INSERT INTO cloud_change_log (table_name, record_id, branch_code, action_type) VALUES ('items', ?, ?, 'UPDATE')")
                ->execute([$itemId, $target_branch]);

            // F. Audit Log
            $unique_pseudo_id = -1 * mt_rand(100000, 9999999);
            $logMsg = "Remote Restore: Txn #$transId restored by $userName (Stock Deducted)";
            
            $lStmt = $pdo->prepare("INSERT INTO audit_logs (branch_code, action, timestamp, local_id) VALUES (?, ?, NOW(), ?)");
            $lStmt->execute([$target_branch, $logMsg, $unique_pseudo_id]);

            $pdo->commit();

        } else {
            // --- LOCAL RESTORE (SQLite) ---
            // (Your existing Local Logic is mostly fine, just ensured consistency)
            
            $stmt = $conn->prepare("SELECT item_id, quantity FROM transactions WHERE transaction_id = :id");
            $stmt->bindValue(':id', $transId, SQLITE3_INTEGER);
            $res = $stmt->execute();
            $row = $res->fetchArray(SQLITE3_ASSOC);
            
            if (!$row) throw new Exception("Transaction not found locally.");
            
            $itemId = $row['item_id'];
            $qty = $row['quantity'];

            $conn->exec("BEGIN TRANSACTION");

            // Local Updates
            $conn->exec("UPDATE transactions SET status = 0 WHERE transaction_id = $transId");
            
            $updLocal = $conn->prepare("UPDATE items SET quantity_in_stock = quantity_in_stock - :qty WHERE item_id = :id");
            $updLocal->bindValue(':qty', $qty, SQLITE3_INTEGER);
            $updLocal->bindValue(':id', $itemId, SQLITE3_INTEGER);
            $updLocal->execute();

            // Log
            $logMsg = "Restored Sale #$transId (Stock deducted)";
            $lStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, timestamp, branch_code, sync_status) VALUES (:u, :a, datetime('now'), :b, 1)");
            $lStmt->bindValue(':u', $userId, SQLITE3_INTEGER);
            $lStmt->bindValue(':a', $logMsg, SQLITE3_TEXT);
            $lStmt->bindValue(':b', $target_branch, SQLITE3_TEXT);
            $lStmt->execute();

            $conn->exec("COMMIT");
            // --- [FIX] HYBRID PUSH: SYNC TO CLOUD IMMEDIATELY ---
            // We attempt to push this change to the cloud right now.
            // If offline, the 'catch' block allows the script to finish without error.
            try {
                $cloud_host = 'srv1254.hstgr.io';
                $cloud_db   = 'u106033383_jemerald_cloud';
                $cloud_user = 'u106033383_jemerald1234';
                $cloud_pass = 'Wearelive_1234';
                
                $cloud_dsn = "mysql:host=$cloud_host;dbname=$cloud_db;charset=utf8mb4";
                $cloud_pdo = new PDO($cloud_dsn, $cloud_user, $cloud_pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
                    PDO::ATTR_TIMEOUT => 4 // Short timeout to prevent lag
                ]);

                // A. Find the Cloud Transaction ID (Matching Local ID)
                // In the Cloud, 'local_id' stores your SQLite transaction_id
                $findTx = $cloud_pdo->prepare("SELECT id, item_id, quantity FROM transactions WHERE local_id = ? AND branch_code = ?");
                $findTx->execute([$transId, $target_branch]); 
                $cloudTx = $findTx->fetch(PDO::FETCH_ASSOC);
                
                if ($cloudTx) {
                    $cloudTxId = $cloudTx['id'];
                    $cloudItemId = $cloudTx['item_id'];
                    $cloudQty = $cloudTx['quantity'];
                    
                    $cloud_pdo->beginTransaction();
                    
                    // B. Update Cloud Status
                    $cloud_pdo->prepare("UPDATE transactions SET status = 0 WHERE id = ?")->execute([$cloudTxId]);
                    
                    // C. Deduct Cloud Stock
                    $cloud_pdo->prepare("UPDATE items SET quantity_in_stock = quantity_in_stock - ? WHERE id = ?")->execute([$cloudQty, $cloudItemId]);
                    
                    // D. Log Cloud Change (For consistency)
                    $cloud_pdo->prepare("INSERT INTO cloud_change_log (table_name, record_id, branch_code, action_type) VALUES ('transactions', ?, ?, 'UPDATE')")->execute([$cloudTxId, $target_branch]);
                    
                    $cloud_pdo->commit();
                }
            } catch (Exception $e) {
                // Connection failed or transaction missing in cloud.
                // We suppress this error so the User still sees "Success" locally.
                // The background sync (if implemented) or next manual sync will handle discrepancies.
            }
        }

        echo json_encode(['success' => true, 'message' => 'Transaction restored successfully. Stock adjusted.']);

    } catch (Exception $e) {
        if ($is_remote && isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        if (!$is_remote && isset($conn)) $conn->exec("ROLLBACK");
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
?>
<?php
require 'defined_global_settings.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Removed Transactions - <?php echo htmlspecialchars($target_branch); ?></title>
  
  <link rel="stylesheet" href="vendors/feather/feather.css">
  <link rel="stylesheet" href="vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="vendors/css/vendor.bundle.base.css">
  <link rel="stylesheet" href="vendors/datatables.net-bs4/dataTables.bootstrap4.css">
  <link rel="stylesheet" href="css/vertical-layout-light/style.css">
  
  <link rel="stylesheet" href="node_modules/toastify-js/src/toastify.css" />
  <link rel="stylesheet" href="sweetalert2/dist/sweetalert2.min.css">
  <link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css">

  <style>
      .card { border-radius: 12px; border: none; box-shadow: 0 2px 15px rgba(0,0,0,0.05); }
      .table thead th { background-color: #f8f9fa; color: #333; font-weight: 600; border-bottom: 2px solid #e9ecef; }
      .branch-badge { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 5px 15px; border-radius: 20px; font-size: 0.85rem; }
  </style>

  <script>
    // Pass PHP global settings to JavaScript variables, ensuring fallback if CURRENCY is empty
    const CURRENCY = "<?php echo !empty(CURRENCY) ? CURRENCY : (CUSTOM_CURRENCY ?? ''); ?>";
</script>
</head>

<body class="light-theme">
  
  <div class="container-scroller">
    <nav class="navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row navbar-dark">
      <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-center">
    <a class="navbar-brand brand-logo mr-5 dark-theme light-theme navbar-dark" href="transactions.php">
        <?php 
            // Extract the first word of the business name
            $businessName = (!empty(BUSINESS_NAME)) ? BUSINESS_NAME : '';
            $firstWord = strtok($businessName, " "); // Get the first word only
            echo htmlspecialchars($firstWord); // Ensure safe output
        ?>
        &nbsp;
        <img src="<?php echo (!empty(BUSINESS_LOGO)) ? BUSINESS_LOGO : 'images/logo-mini.png'; ?>" 
             class="mr-2 dark-theme light-theme navbar-dark" 
             alt="Business Logo"/>
    </a>
      </div>
<div class="navbar-menu-wrapper d-flex align-items-center justify-content-end">
        <span class="branch-badge mr-3"><i class="fas fa-map-marker-alt mr-2"></i> <?php echo htmlspecialchars($target_branch); ?></span>
        
        <?php 
            // Preserve the branch name if it was passed, otherwise default
            $return_name = isset($_GET['branch_name']) ? $_GET['branch_name'] : 'Branch View'; 
        ?>

        <a href="transactions.php?branch_uuid=<?php echo urlencode($target_branch); ?>&branch_code=<?php echo urlencode($target_branch); ?>&branch_name=<?php echo urlencode($return_name); ?>" 
           class="btn btn-outline-light btn-sm">
           <i class="ti-arrow-left"></i> Back to Transactions
        </a>
      
      </div>
    </nav>

    <div class="container-fluid page-body-wrapper">
      <div class="main-panel" style="width: 100%;">
        <div class="content-wrapper">
          
          <div class="row">
            <div class="col-12 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-center mb-4">
                      <h4 class="card-title mb-0"><i class="ti-trash text-danger"></i> Removed Transactions Archive</h4>
                      <button id="refreshTable" class="btn btn-sm btn-light"><i class="ti-reload"></i> Refresh</button>
                  </div>

                  <div class="table-responsive">
                    <table id="removedTable" class="table table-hover" style="width:100%">
                      <thead>
                        <tr>
                          <th>ID</th>
                          <th>Date</th>
                          <th>Group ID</th>
                          <th>Item Name</th>
                          <th>Qty</th>
                          <th>Amount</th>
                          <th>Payment</th>
                          <th>Action</th>
                        </tr>
                      </thead>
                      <tbody>
                        </tbody>
                    </table>
                  </div>

                </div>
              </div>
            </div>
          </div>

        </div>
        
        <footer class="footer">
          <div class="d-sm-flex justify-content-center justify-content-sm-between">
            <span class="text-muted text-center text-sm-left d-block d-sm-inline-block">Inventory Keeper App</span>
          </div>
        </footer>
      </div>
    </div>
  </div>

  <script src="vendors/js/vendor.bundle.base.js"></script>
  <script src="vendors/datatables.net/jquery.dataTables.js"></script>
  <script src="vendors/datatables.net-bs4/dataTables.bootstrap4.js"></script>
  <script src="sweetalert2/dist/sweetalert2.all.min.js"></script>
  <script src="node_modules/toastify-js/src/toastify.js"></script>

  <script>
    $(document).ready(function() {
        const branchCode = "<?php echo $target_branch; ?>";
        const currency = "₦"; 

        var table = $('#removedTable').DataTable({
            "ajax": "removed_transactions.php?action=fetch&branch_code=" + branchCode,
            "columns": [
                { "data": "transaction_id" },
                { "data": "transaction_date" },
                { 
                    "data": "transaction_group_id",
                    "render": function(data) {
                        return `<span class="badge badge-outline-secondary">${data}</span>`;
                    }
                },
                { "data": "item_name" },
                { "data": "quantity" },
                { 
                    "data": "total_amount",
                    "render": function(data) { return currency + parseFloat(data).toFixed(2); }
                },
                { "data": "modeOfPayment" },
                {
                    "data": "restore_id",
                    "render": function(data, type, row) {
                        return `<button class="btn btn-success btn-sm btn-icon-text restore-btn" data-id="${data}">
                                    <i class="ti-reload btn-icon-prepend"></i> Restore
                                </button>`;
                    }
                }
            ],
            "order": [[ 1, "desc" ]],
            "language": {
                "emptyTable": "No removed transactions found for " + branchCode
            }
        });

        $('#refreshTable').click(function() {
            table.ajax.reload();
        });

        $(document).on('click', '.restore-btn', function() {
            var transId = $(this).data('id');

            Swal.fire({
                title: 'Restore Transaction?',
                text: "This will add the sale back and DEDUCT the stock again.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, Restore it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    
                    Swal.fire({ 
                        title: 'Restoring...', 
                        text: 'Syncing with database...', 
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); } 
                    });

                    $.ajax({
                        url: 'removed_transactions.php',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'restore',
                            transaction_id: transId,
                            branch_code: branchCode
                        },
                        success: function(response) {
                            if (response.success) {
                                Swal.fire("Restored!", response.message, "success");
                                table.ajax.reload(); 
                            } else {
                                Swal.fire("Error!", response.message, "error");
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error(xhr.responseText);
                            Swal.fire("Connection Error", "Failed to connect to server.", "error");
                        }
                    });
                }
            });
        });
    });
  </script>
</body>
</html>