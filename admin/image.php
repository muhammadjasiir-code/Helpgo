<?php
require_once '../config.php';
if (!isAdmin()) { die("Access denied"); }

$file = $_GET['file'] ?? '';
if (empty($file)) die("No file specified.");

$filePath = UPLOAD_DIR . 'riders/' . basename($file);

if (!file_exists($filePath)) {
    http_response_code(404);
    die("File not found.");
}

$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$mime = '';
switch ($ext) {
    case 'jpg': case 'jpeg': $mime = 'image/jpeg'; break;
    case 'png': $mime = 'image/png'; break;
    case 'gif': $mime = 'image/gif'; break;
    case 'pdf': $mime = 'application/pdf'; break;
    default: $mime = 'application/octet-stream';
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
?>