<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /index.php');
    exit;
}

$userId = current_user_id();
$kategori = $_POST['kategori'] ?? '';
$nominal = (float) ($_POST['nominal'] ?? 0);
$keterangan = trim($_POST['keterangan'] ?? '');

$allowedKategori = ['transport', 'medis', 'makan', 'lainnya'];
if (!in_array($kategori, $allowedKategori, true) || $nominal <= 0 || $keterangan === '') {
    header('Location: /index.php?msg=' . urlencode('Data klaim tidak valid.'));
    exit;
}

$lampiranPath = null;

if (!empty($_FILES['lampiran']['name'])) {
    $file = $_FILES['lampiran'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        header('Location: /index.php?msg=' . urlencode('Gagal mengunggah lampiran.'));
        exit;
    }

    // Whitelist ekstensi (bukan blacklist) + validasi tipe file sungguhan di
    // server pakai fileinfo (bukan Content-Type kiriman klien yang mudah
    // dispoof) + nama file di-random supaya tidak bisa ditebak/dipakai untuk
    // menyisipkan ekstensi eksekusi lewat nama asli.
    $allowedExtensions = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!array_key_exists($extension, $allowedExtensions)) {
        header('Location: /index.php?msg=' . urlencode('Lampiran harus berformat jpg, png, atau pdf.'));
        exit;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $realMimeType = $finfo->file($file['tmp_name']);

    if ($realMimeType !== $allowedExtensions[$extension]) {
        header('Location: /index.php?msg=' . urlencode('Isi file lampiran tidak sesuai dengan ekstensinya.'));
        exit;
    }

    $randomName = bin2hex(random_bytes(16)) . '.' . $extension;
    $uploadDir = __DIR__ . '/uploads/klaim/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $randomName)) {
        header('Location: /index.php?msg=' . urlencode('Gagal menyimpan lampiran.'));
        exit;
    }

    $lampiranPath = 'klaim/' . $randomName;
}

$stmt = $mysqli->prepare('INSERT INTO klaim (user_id, kategori, nominal, keterangan, lampiran_path) VALUES (?, ?, ?, ?, ?)');
$stmt->bind_param('isdss', $userId, $kategori, $nominal, $keterangan, $lampiranPath);
$stmt->execute();

header('Location: /index.php?msg=' . urlencode('Klaim berhasil diajukan, menunggu review HR.'));
exit;
