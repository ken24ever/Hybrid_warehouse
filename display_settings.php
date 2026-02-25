<?php
require_once 'settings.php'; // Include the settings file to fetch global settings

$globalSettings = getSettings(); // Fetch settings from the database

// Get price type, receipt footer, and receipt disclaimer
$priceType = isset($globalSettings['price_type']) ? $globalSettings['price_type'] : 'Retail';
$receiptFooter = isset($globalSettings['receipt_footer']) ? $globalSettings['receipt_footer'] : '';
$receiptDisclaimer = isset($globalSettings['receipt_disclaimer']) ? $globalSettings['receipt_disclaimer'] : '';

// Display table with current values in a read-only format
?>
<table class="table table-striped table-bordered">
    <thead class="thead-dark">
        <tr>
            <th>Setting</th>
            <th>Value</th>
        </tr>
    </thead>
    <tbody>
        <!-- Display Price Type -->
        <tr>
            <td><strong>Price Type</strong></td>
            <td>
                <!-- Display the current price type -->
                <span id="priceTypeStatus"><?php echo htmlspecialchars($priceType); ?></span>
            </td>
        </tr>

        <!-- Display Receipt Footer -->
        <tr>
            <td><strong>Receipt Footnotes</strong></td>
            <td>
                <!-- Display receipt footer as a read-only text area -->
                <textarea class="form-control" rows="3" readonly><?php echo htmlspecialchars($receiptFooter); ?></textarea>
            </td>
        </tr>

        <!-- Display Receipt Disclaimer -->
        <tr>
            <td><strong>Receipt Disclaimers</strong></td>
            <td>
                <!-- Display receipt disclaimer as a read-only text area -->
                <textarea class="form-control" rows="3" readonly><?php echo htmlspecialchars($receiptDisclaimer); ?></textarea>
            </td>
        </tr>
    </tbody>
</table>
