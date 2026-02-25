<?php
// Encryption Key (Keep this secret and unchanged)
$encryptionKey = "YourSecretKey1234567890"; // Must be 32 characters for AES-256

// Function to get the motherboard serial number
function getMotherboardSerial() {
    $command = 'wmic baseboard get serialnumber';
    exec($command, $output);
    return isset($output[1]) ? trim($output[1]) : null;
}

// Function to encrypt data
function encryptData($data, $key) {
    $iv = substr($key, 0, 16); // Use the first 16 bytes as IV
    return openssl_encrypt($data, "AES-256-CBC", $key, 0, $iv);
}

// Generate License Key
$hardwareID = getMotherboardSerial();
if (!$hardwareID) {
    die("Failed to retrieve hardware ID.\n");
}

$licenseKey = strtoupper(hash('sha256', $hardwareID));
$encryptedLicense = encryptData($licenseKey, $encryptionKey);

// Store encrypted license in Windows Registry
$command = 'reg add HKCU\Software\InventoryKeeper /v LicenseKey /t REG_SZ /d "' . $encryptedLicense . '" /f';
exec($command, $output, $returnVar);

if ($returnVar !== 0) {
    die("Failed to store license in registry.\n");
}

// Store encrypted license in the www directory
$wwwDir = __DIR__ . '/';
if (!is_dir($wwwDir)) {
    mkdir($wwwDir, 0777, true); // Create the directory if it doesn't exist
}
$filePath = $wwwDir . '/license.lic';
file_put_contents($filePath, $encryptedLicense);

echo "License generated and stored successfully in www directory.\n";
?>
