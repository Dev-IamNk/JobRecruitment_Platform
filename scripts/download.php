<?php
require_once '../config/db.php';
redirectIfNotLoggedIn();

// Make sure a file is specified
if (!isset($_GET['file'])) {
    exit('No file specified.');
}

// Prevent directory traversal attacks
$file = basename($_GET['file']);
$filepath = __DIR__ . '/../assets/uploads/' . $file;

if (file_exists($filepath)) {
    // Force download
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit;
} else {
    exit('File not found.');
}
