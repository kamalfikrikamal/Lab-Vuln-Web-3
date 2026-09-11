<?php

session_start();

function current_user_id()
{
    return $_SESSION['user_id'] ?? null;
}

function current_user_role()
{
    return $_SESSION['role'] ?? null;
}

function current_user_nama()
{
    return $_SESSION['nama'] ?? '';
}

function require_login()
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
}

function require_role($role)
{
    require_login();
    if (($_SESSION['role'] ?? null) !== $role) {
        http_response_code(403);
        die('Akses ditolak. Halaman ini khusus untuk role ' . htmlspecialchars($role) . '.');
    }
}
