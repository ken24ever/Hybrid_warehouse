<?php
$filename = isset($_GET['file']) ? $_GET['file'] : null;

if (!$filename || !file_exists(__DIR__ . '/' . $filename)) {
    die("Error 404: File not found");
}

header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header("Content-Disposition: attachment; filename=" . basename($filename));
header("Content-Length: " . filesize(__DIR__ . '/' . $filename));
readfile(__DIR__ . '/' . $filename);
exit; 
?>
