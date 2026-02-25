<?php
$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['lastBackupTime'])) {
    file_put_contents("backup-time.json", json_encode($data));
    echo json_encode(["success" => true, "message" => "Backup time saved."]);
} else {
    echo json_encode(["success" => false, "message" => "Invalid data received."]);
}
?> 
