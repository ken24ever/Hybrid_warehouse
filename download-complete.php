<?php
header("Content-Type: application/json");

$response = [
    "status" => "success",
    "message" => "✅ Download Completed!",
    "timestamp" => date("Y-m-d H:i:s")
];

// Display a ToastifyJS notification (Optional: You can also log it)
echo json_encode($response);
?>
