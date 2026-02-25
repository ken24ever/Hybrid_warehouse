<?php
require_once 'settings.php'; // Include your settings file to get the global settings

// Fetch the current inventory settings
$globalSettings = getSettings(); // Function to fetch global settings from the database

// Fetch low stock threshold and low stock alert status
$lowStockThreshold = isset($globalSettings['low_stock_threshold']) ? $globalSettings['low_stock_threshold'] : '';
$enableLowStockAlert = isset($globalSettings['enable_low_stock_alert']) ? $globalSettings['enable_low_stock_alert'] : 0;
// ADD THIS LINE
$allowExpiredSale = isset($globalSettings['allow_expired_items_sale']) ? $globalSettings['allow_expired_items_sale'] : 0;
?>

<!-- Inventory & Stock Control Tab -->

<table class="table table-striped table-bordered">  
    <thead class="thead-dark">
        <tr>
            <th>Setting</th>
            <th>Value</th>
        </tr>
    </thead>
    <tbody>
        <!-- Display lowStockThreshold -->
        <tr>
            <td><strong>Low Stock Threshold</strong></td>
            <td>
                <!-- Display the current price type -->
                <span><?php echo htmlspecialchars($lowStockThreshold); ?></span>
            </td>
        </tr>

        <!-- Display Enable Low Stock Alerts -->
        <tr>
            <td><strong>Enable Low Stock Alerts</strong></td>
            <td>
                <!-- Display Enable Low Stock Alerts as a read-only text area -->
                <span><?php echo htmlspecialchars($enableLowStockAlert); ?></span>
            </td>
        </tr>
            <!-- Display Allow Sale of Expired Items -->
        <tr>
            <td><strong>Allow Sale of Expired Items</strong></td>
            <td>
                <span><?php echo ($allowExpiredSale == 1) ? 'Allowed' : 'Disallowed (Recommended)'; ?></span>
            </td>
        </tr>

    </tbody>
</table>