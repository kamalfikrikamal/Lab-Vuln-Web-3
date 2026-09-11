<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require_login();

$baseDir = realpath(__DIR__ . '/uploads');
$requested = $_GET['file'] ?? '';
$fullPath = $baseDir !== false ? realpath($baseDir . '/' . $requested) : false;

if ($fullPath === false || strncmp($fullPath, $baseDir . DIRECTORY_SEPARATOR, strlen($baseDir) + 1) !== 0) {
    http_response_code(403);
    die('Akses file ditolak.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($fullPath);
header('Content-Type: ' . $mime);
readfile($fullPath);
