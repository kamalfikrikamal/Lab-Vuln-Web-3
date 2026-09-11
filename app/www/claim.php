<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require_login();

$userId = current_user_id();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $mysqli->prepare('SELECT id, kategori, nominal, keterangan, lampiran_path, status, created_at FROM klaim WHERE id = ? AND user_id = ?');
$stmt->bind_param('ii', $id, $userId);
$stmt->execute();
$klaim = $stmt->get_result()->fetch_assoc();

if (!$klaim) {
    http_response_code(404);
    require __DIR__ . '/includes/layout.php';
    render_header('Tidak Ditemukan');
    echo '<div class="card"><p>Klaim tidak ditemukan.</p></div>';
    render_footer();
    exit;
}

require __DIR__ . '/includes/layout.php';
render_header('Detail Klaim #' . $klaim['id']);
?>

<div class="card">
    <h1>Detail Klaim #<?= (int) $klaim['id'] ?></h1>
    <dl class="detail-list">
        <dt>Tanggal</dt><dd><?= htmlspecialchars($klaim['created_at']) ?></dd>
        <dt>Kategori</dt><dd><?= htmlspecialchars(ucfirst($klaim['kategori'])) ?></dd>
        <dt>Nominal</dt><dd>Rp <?= number_format((float) $klaim['nominal'], 0, ',', '.') ?></dd>
        <dt>Keterangan</dt><dd><?= htmlspecialchars($klaim['keterangan']) ?></dd>
        <dt>Status</dt><dd><span class="badge badge-<?= htmlspecialchars($klaim['status']) ?>"><?= htmlspecialchars(ucfirst($klaim['status'])) ?></span></dd>
        <?php if ($klaim['lampiran_path']): ?>
        <dt>Lampiran</dt><dd><a href="/viewer.php?file=<?= urlencode($klaim['lampiran_path']) ?>" target="_blank">Lihat lampiran</a></dd>
        <?php endif; ?>
    </dl>
    <a href="/index.php">&larr; Kembali ke dashboard</a>
</div>

<?php
render_footer();
