<?php
require_once 'settings.php'; // Fetch settings
$globalSettings = getSettings(); 

// Define constants for global use
if (!empty($globalSettings)) {
    define('DARK_MODE', $globalSettings['dark_mode']);
    define('LANGUAGE', $globalSettings['language']);
    define('CUSTOM_LANGUAGE', $globalSettings['custom_language']);
    define('CURRENCY', $globalSettings['currency']);
    define('CUSTOM_CURRENCY', $globalSettings['custom_currency']);
    define('BUSINESS_NAME', $globalSettings['business_name']);
    define('BUSINESS_LOGO', $globalSettings['business_logo']);
    define('TIMEZONE', $globalSettings['timezone']);
    define('PRICE_TYPE', $globalSettings['price_type']);
    define('RECEIPT_FOOTER', $globalSettings['receipt_footer']);
    define('RECEIPT_DISCLAIMER', $globalSettings['receipt_disclaimer']);
    define('LOW_STOCK_THRESHOLD', $globalSettings['low_stock_threshold']);
    define('ENABLE_LOW_STOCK_ALERT', $globalSettings['enable_low_stock_alert']);
    // ADD THIS LINE
    define('ALLOW_EXPIRED_ITEMS_SALE', $globalSettings['allow_expired_items_sale']);
    // Set PHP timezone globally
    if (!empty($globalSettings['timezone'])) {
        date_default_timezone_set($globalSettings['timezone']);
    }
}

