<?php
session_start(); // Start session to track the logged-in user

require_once 'connection.php';

// Check if the request method is POST
$response = ['status' => 'error', 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get data from the form submission
    $business_name   = trim($_POST['business_name'] ?? '');
    $currency        = trim($_POST['currency'] ?? '');
    $custom_currency = trim($_POST['currency_custom'] ?? ''); // Capture the custom currency value
    $logoPath        = ''; // Will store the new logo path if uploaded

    // Process file upload if a file is provided
    if (isset($_FILES['logoUpload']) && $_FILES['logoUpload']['error'] === UPLOAD_ERR_OK) {
        $targetDir = "uploads/logos/";
        $fileName  = basename($_FILES['logoUpload']['name']);
        $targetFilePath = $targetDir . $fileName;
        $fileType  = pathinfo($targetFilePath, PATHINFO_EXTENSION);
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];

        // Validate file type
        if (in_array(strtolower($fileType), $allowedTypes)) {
            // Validate file size (1MB max)
            if ($_FILES["logoUpload"]["size"] > 1048576) {
                $response['message'] = "Sorry, your file is too large.";
                echo json_encode($response);
                exit;
            }

            // Move the uploaded file to the target directory
            if (move_uploaded_file($_FILES['logoUpload']['tmp_name'], $targetFilePath)) {
                $logoPath = $targetFilePath;
            } else {
                error_log("Error uploading file: " . $_FILES['logoUpload']['error']);
                $response['message'] = "Error uploading file.";
                echo json_encode($response);
                exit;
            }
        } else {
            $response['message'] = "Invalid file type. Only JPG, JPEG, PNG, and GIF files are allowed.";
            echo json_encode($response);
            exit;
        }
    }

    // Validate required inputs
    if (empty($business_name)) {
        $response['message'] = "Company name is required.";
        echo json_encode($response);
        exit;
    } elseif (empty($currency)) {
        $response['message'] = "Currency selection is required.";
        echo json_encode($response);
        exit;
    } elseif ($currency === 'custom' && empty($custom_currency)) {
        $response['message'] = "Custom currency is required.";
        echo json_encode($response);
        exit;
    } else {
        try {
            // Use custom currency if provided; otherwise, use the selected currency
            $finalCurrency = ($currency === 'custom') ? $custom_currency : $currency;

            // Check if a settings record exists (only one record is maintained)
            $query = "SELECT * FROM settings LIMIT 1";
            $result = $conn->query($query);
            $row = $result->fetchArray(SQLITE3_ASSOC);

            if ($row) {
                // A settings record exists; update it.
                $setting_id = $row['setting_id'];

                // If no new logo uploaded, keep the existing logo
                if (empty($logoPath)) {
                    if (!empty($row['business_logo']) && file_exists($row['business_logo'])) {
                        $logoPath = $row['business_logo'];
                    }
                }

                // Prepare update query.
                if (!empty($logoPath)) {
                    $stmt = $conn->prepare("UPDATE settings SET business_name = ?, business_logo = ?, currency = ?, custom_currency = ? WHERE setting_id = ?");
                    $stmt->bindValue(1, $business_name, SQLITE3_TEXT);
                    $stmt->bindValue(2, $logoPath, SQLITE3_TEXT);
                    $stmt->bindValue(3, $finalCurrency, SQLITE3_TEXT);
                    $stmt->bindValue(4, $custom_currency, SQLITE3_TEXT);
                    $stmt->bindValue(5, $setting_id, SQLITE3_INTEGER);
                } else {
                    // No logo update; update other fields only.
                    $stmt = $conn->prepare("UPDATE settings SET business_name = ?, currency = ?, custom_currency = ? WHERE setting_id = ?");
                    $stmt->bindValue(1, $business_name, SQLITE3_TEXT);
                    $stmt->bindValue(2, $finalCurrency, SQLITE3_TEXT);
                    $stmt->bindValue(3, $custom_currency, SQLITE3_TEXT);
                    $stmt->bindValue(4, $setting_id, SQLITE3_INTEGER);
                }
            } else {
                // No settings record exists; insert a new record.
                $stmt = $conn->prepare("INSERT INTO settings (business_name, business_logo, currency, custom_currency) VALUES (?, ?, ?, ?)");
                $stmt->bindValue(1, $business_name, SQLITE3_TEXT);
                $stmt->bindValue(2, $logoPath, SQLITE3_TEXT);
                $stmt->bindValue(3, $finalCurrency, SQLITE3_TEXT);
                $stmt->bindValue(4, $custom_currency, SQLITE3_TEXT);
            }

            if ($stmt->execute()) {
                // Log the action in the audit_logs table
                $userId = $_SESSION['user_id']; // Get the logged-in user's ID
                $action = "Updated settings - Business Name: $business_name, Currency: $finalCurrency";
                
                // Insert into audit logs
                $logStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (?, ?, datetime('now', 'localtime'))");
                $logStmt->bindValue(1, $userId, SQLITE3_INTEGER);
                $logStmt->bindValue(2, $action, SQLITE3_TEXT);
                $logStmt->execute();

                // Respond with success message
                $response = ['status' => 'success', 'message' => 'Settings saved successfully'];
            } else {
                $response['message'] = "Database error.";
            }
        } catch (Exception $e) {
            $response['message'] = "Error: " . $e->getMessage();
        }
    }
}

echo json_encode($response);
$conn->close();
