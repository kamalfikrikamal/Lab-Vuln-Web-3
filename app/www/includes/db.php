<?php

$dbHost = getenv('DB_HOST') ?: 'db';
$dbName = getenv('DB_NAME') ?: 'klaimku';
$dbUser = getenv('DB_USER') ?: 'klaimku_app';
$dbPass = getenv('DB_PASS') ?: 'klaimku_app_pw';

$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

if ($mysqli->connect_errno) {
    http_response_code(500);
    die('Koneksi database gagal.');
}

$mysqli->set_charset('utf8mb4');
