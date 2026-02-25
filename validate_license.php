<?php
// Function to get motherboard serial number
function getMotherboardSerial() {
    $command = 'wmic baseboard get serialnumber';
    exec($command, $output);
    return isset($output[1]) ? trim($output[1]) : null;
}

// Function to decrypt data
function decryptData($data, $key) {
    $iv = substr($key, 0, 16); // Use the first 16 bytes as IV
    return openssl_decrypt($data, "AES-256-CBC", $key, 0, $iv);
}

// Encryption key (must match generate_license.php)
$encryptionKey = "YourSecretKey1234567890";

// Read license from Windows Registry
$command = 'reg query HKCU\Software\InventoryKeeper /v LicenseKey';
exec($command, $output, $returnVar);

// Check if the registry query was successful
if ($returnVar !== 0 || empty($output)) {
    header("Location: license_error.php?reason=not_found");
    exit;
}

// Extract the stored encrypted license from the registry output
$storedEncryptedLicense = "";
foreach ($output as $line) {
    if (strpos($line, "LicenseKey") !== false) {
        $licenseParts = preg_split('/\s{2,}/', trim($line)); // Split by multiple spaces
        $storedEncryptedLicense = end($licenseParts); // Get the last part (license key)
        break;
    }
}

// Ensure a valid extracted license
if (empty($storedEncryptedLicense)) {
    header("Location: license_error.php?reason=extract_failed");
    exit;
}

// Read license from the license file in the www directory
$filePath = __DIR__ . '/license.lic';
if (!file_exists($filePath)) {
    header("Location: license_error.php?reason=file_missing");
    exit;
}

$storedEncryptedFileLicense = trim(file_get_contents($filePath));

// Ensure registry and file licenses match
if ($storedEncryptedLicense !== $storedEncryptedFileLicense) {
    header("Location: license_error.php?reason=mismatch");
    exit;
}

// Get the current system's motherboard serial number
$hardwareID = getMotherboardSerial();
if (!$hardwareID) {
    header("Location: license_error.php?reason=hardware_failed");
    exit;
}

// Generate expected license key from hardware ID
$expectedLicenseKey = strtoupper(hash('sha256', $hardwareID));

// Decrypt stored license key
$decryptedLicenseKey = decryptData($storedEncryptedLicense, $encryptionKey);

// Validate license
if ($decryptedLicenseKey !== $expectedLicenseKey) {
    header("Location: license_error.php?reason=invalid");
    exit;
}

// License is valid, proceed with the app
?>
