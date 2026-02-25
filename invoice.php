<?php
session_start();

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
<head>
  <meta charset="UTF-8">
  <title>Thermal Receipt</title>
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
            margin-top: 8px; /* More space before summary */
            text-align: right;
        }

        .summary p {
            margin: 2px 0;
            font-size: 11px;
        }

    @media print {
  @page {
    margin: 0; /* Remove browser default print margin */
    size: auto; /* Let the browser choose the paper size */
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
      <img src="<?php echo (!empty(BUSINESS_LOGO)) ? BUSINESS_LOGO : 'images/logo-mini.png'; ?>" alt="Logo" />
      <h5><strong><?php echo (!empty(BUSINESS_NAME)) ? BUSINESS_NAME : ''; ?></strong></h5>
      <p><strong>INVOICE</strong></p>
    </div>

    <p><strong>Bill To:</strong></p>
    <p><strong>Name:</strong> <span id="billToName"></span></p>
    <p><strong>Address:</strong> <span id="billToAddress"></span></p>
    <p><strong>Date:</strong> <span id="invoiceDate"></span></p>
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
      <p><strong>Subtotal:</strong> <span id="subTotal"><?php echo CURRENCY; ?>0.00</span></p>
      <p><strong>Tax:</strong> <span id="taxAmount"><?php echo CURRENCY; ?>0.00</span></p>
      <p><strong>Total:</strong> <span id="grandTotal"><?php echo CURRENCY; ?>0.00</span></p>
    </div>
<br>
    <div class="receipt-footer">
      <p><?php echo RECEIPT_FOOTER; ?></p>
      <p>Disclaimer: <?php echo RECEIPT_DISCLAIMER; ?></p>
      <p><strong>Thank You!</strong></p>
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

  <!-- Auto-print on load -->
  <script>
    document.addEventListener("DOMContentLoaded", function () {
      if (!localStorage.getItem("items")) return;

      document.getElementById("billToName").textContent = localStorage.getItem("billToName") || "N/A";
      document.getElementById("billToAddress").textContent = localStorage.getItem("billToAddress") || "N/A";
      document.getElementById("grandTotal").textContent = CURRENCY + (localStorage.getItem("grandTotal") || "0.00");
      document.getElementById("invoiceDate").textContent = localStorage.getItem("invoiceDate") || "N/A";

      const items = JSON.parse(localStorage.getItem("items") || "[]");
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
            <td>${total.toFixed(2)}</td>
          </tr>`;
        receiptItems.innerHTML += row;
      });

      document.getElementById("subTotal").textContent = CURRENCY + subtotal.toFixed(2);
      document.getElementById("taxAmount").textContent = CURRENCY + "0.00"; // if any logic needed
      document.getElementById("grandTotal").textContent = CURRENCY + subtotal.toFixed(2);

      // Auto clear
      window.onafterprint = function () {
        localStorage.clear();
      };

      // Auto print
      // window.print();
    });

    const CURRENCY = "<?php echo !empty(CURRENCY) ? CURRENCY : (CUSTOM_CURRENCY ?? '₦'); ?>";
  </script>
</body>
</html>