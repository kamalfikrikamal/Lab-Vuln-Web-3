<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

// BUG: endpoint aksi ini seharusnya juga memanggil require_role('hr') seperti
// admin/index.php, tapi implementasinya hanya memeriksa apakah user sudah
// login. Akibatnya karyawan biasa (role 'karyawan') bisa memanggil endpoint
// ini langsung untuk approve/reject klaim apa pun, termasuk klaim miliknya
// sendiri, tanpa pernah melewati review HR.
require_login();

header('Content-Type: application/json');

$id = (int) ($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';

if (!in_array($action, ['approve', 'reject'], true) || $id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Permintaan tidak valid.']);
    exit;
}

$status = $action === 'approve' ? 'approved' : 'rejected';
$approverId = current_user_id();

$stmt = $mysqli->prepare('UPDATE klaim SET status = ?, approved_by = ? WHERE id = ?');
$stmt->bind_param('sii', $status, $approverId, $id);
$stmt->execute();

echo json_encode(['success' => true, 'status' => $status]);
