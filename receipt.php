<?php
session_start(); // Make sure to start the session at the beginning of your script

if (isset($_SESSION['role'])) {
    $user_role = $_SESSION['role'];
    if ($user_role === "Sales Manager" || $user_role === "Super Admin") {
        $userID = $_SESSION['user_id'];
        $userName = $_SESSION['username'];
        $userRole = $_SESSION['role'];
    } else {
        header("Location:404.php");
        exit;
    }
} else {
    header("Location:404.php");
    exit;
}

require 'defined_global_settings.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt</title>
    <!-- bootstrap v4 -->
  <!--   <link rel="stylesheet" href="bootstrap_v4/css/bootstrap.min.css"> -->

    <script>
        // Pass PHP global settings to JavaScript variables, ensuring fallback if CURRENCY is empty
        const CURRENCY = "<?php echo !empty(CURRENCY) ? CURRENCY : (CUSTOM_CURRENCY ?? ''); ?>";
    </script>

    <!-- jQuery library -->
    <script src="jquery/jquery-3.6.0.min.js"></script> 

    <link rel="shortcut icon" href="<?php echo (defined('BUSINESS_LOGO') && !empty(BUSINESS_LOGO)) ? BUSINESS_LOGO : 'images/favicon.png'; ?>" />
<style> 
        body {
          font-family: 'Courier New', monospace;
          font-size: 12px;
          margin: 0;
          padding: 0;
          background: white;
        }

        .receipt-container {
          width: 58mm;
          padding: none;
          margin: auto;
          color: #000;
        }

        .receipt-header,
        .receipt-footer {
          text-align: center;
          margin-bottom: 5px;
        }

        .receipt-header img {
          max-width: 50px;
          max-height: 50px;
        }

        h5, p {
          margin: 2px 0;
          font-size: 12px;
        }

        table {
          width: 100%;
          border-collapse: collapse;
        }

        th, td {
          padding: 2px;
          border-bottom: 1px dotted #000;
          text-align: left;
          font-size: 11px;
        }

        .summary {
          margin-top: 8px;
          text-align: right;
        }

        .summary p {
          margin: 2px 0;
          font-size: 11px;
        }

        @media print {
          @page {
            margin: 0;
            size: auto;
          }

          body {
            margin: 0;
            padding: 0;
          }

          .btn-group {
            display: none;
          }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="receipt-header">
			<h5><strong><?php echo (!empty(BUSINESS_NAME)) ? BUSINESS_NAME : ''; ?></strong></h5>
            <p><?php echo !empty(RECEIPT_FOOTER) ? RECEIPT_FOOTER : ''; ?></strong></p>
			<br>
            <h5><strong>RECEIPT</strong></h5>
        </div>
        <div>
			<p id="billToLabel"><strong>Bill To:</strong></p>
			<p id="billToNameRow"><strong>Name:</strong> <span id="billToName"></span></p> 
			<p id="billToAddressRow"><strong>Address:</strong> <span id="billToAddress"></span></p>
            <br>
            <p><strong>Receipt No:</strong> <span id="transactionId"></span></p>
            <p><strong>Payment Date:</strong> <span id="paymentDate"></span></p>
            <p><strong>Cashier:</strong> <?php echo htmlspecialchars($userName ?? 'System'); ?></p>
        </div>       
        <br>
        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody id="receiptItems"></tbody>
        </table>
        
        <div class="summary">
            <p><strong>Subtotal:</strong> <span id="subTotal"><?php echo !empty(CURRENCY) ? CURRENCY : (CUSTOM_CURRENCY ?? ''); ?>0.00</span></p>
            <p><strong>Tax:</strong> <span id="taxAmount"><?php echo !empty(CURRENCY) ? CURRENCY : (CUSTOM_CURRENCY ?? ''); ?>0.00</span></p>
            <p><strong>Total:</strong> <span id="grandTotal"><?php echo !empty(CURRENCY) ? CURRENCY : (CUSTOM_CURRENCY ?? ''); ?>0.00</span></p>
        </div>
        <br>
        <div class="receipt-footer">
            <p style="font-size:10px;">Disclaimers:<?php echo !empty(RECEIPT_DISCLAIMER) ? RECEIPT_DISCLAIMER : ''; ?></strong></p>
            <p><strong>Thank You for Your Patronage!</strong></p>
        </div>
        
        <div class="text-center mt-4">
            <div class="btn-group" role="group" aria-label="Receipt Actions">
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Receipt
                </button>
                <a href="pos.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Go Back
                </a>
            </div>
        </div>
    </div>

    <!-- bootstrap v4 js -->
    <script src="bootstrap_v4/js/bootstrap.min.js"></script>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Load stored values
        document.getElementById("transactionId").innerText = localStorage.getItem("transactionId") || "N/A";
        document.getElementById("paymentDate").innerText = localStorage.getItem("paymentDate") || "N/A";
        document.getElementById("grandTotal").innerText = CURRENCY + (localStorage.getItem("grandTotal") || "0.00");
        const billToName = (localStorage.getItem("billToName") || "").trim();
		const billToAddress = (localStorage.getItem("billToAddress") || "").trim();

		document.getElementById("billToName").innerText = billToName;
		document.getElementById("billToAddress").innerText = billToAddress;

		// Hide Name row if empty
		if (!billToName) {
			document.getElementById("billToNameRow").style.display = "none";
		}

		// Hide Address row if empty
		if (!billToAddress) {
			document.getElementById("billToAddressRow").style.display = "none";
		}

		// Hide "Bill To:" label if BOTH are empty
		if (!billToName && !billToAddress) {
			document.getElementById("billToLabel").style.display = "none";
		}
        const items = JSON.parse(localStorage.getItem("items") || "[]");
        console.log(items)
        const receiptItems = document.getElementById("receiptItems");
        receiptItems.innerHTML = "";
      let subtotal = 0;

      items.forEach(item => {
        const total = item.quantity * item.fixedPrice;
        subtotal += total;

        const row = `<tr>
                <td>${item.item_name}</td>
                <td>${item.quantity}</td>
                <td>${item.fixedPrice.toFixed(2)}</td>
                <td>${(item.quantity * item.fixedPrice).toFixed(2)}</td>
            </tr>`;
            receiptItems.innerHTML += row;
        });

      document.getElementById("subTotal").textContent = CURRENCY + subtotal.toFixed(2);
        // Clear storage after printing
        window.onafterprint = function() {
            localStorage.clear();
        };

        // Auto print
        window.print();
    });
    </script>
</body>
</html>