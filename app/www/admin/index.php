<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require_role('hr');

$result = $mysqli->query(
    'SELECT klaim.id, klaim.kategori, klaim.nominal, klaim.keterangan, klaim.status, klaim.created_at, users.nama
     FROM klaim JOIN users ON klaim.user_id = users.id
     ORDER BY klaim.created_at DESC'
);

require __DIR__ . '/../includes/layout.php';
render_header('Dashboard HR');
?>

<div class="card">
    <div class="card-header-row">
        <h1>Review Klaim Karyawan</h1>
        <a href="/admin/verify_rekening.php">Verifikasi Rekening</a>
    </div>
    <table class="table">
        <thead>
            <tr><th>Tanggal</th><th>Karyawan</th><th>Kategori</th><th>Nominal</th><th>Keterangan</th><th>Status</th><th>Aksi</th></tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['created_at']) ?></td>
                <td><?= htmlspecialchars($row['nama']) ?></td>
                <td><?= htmlspecialchars(ucfirst($row['kategori'])) ?></td>
                <td>Rp <?= number_format((float) $row['nominal'], 0, ',', '.') ?></td>
                <td><?= htmlspecialchars($row['keterangan']) ?></td>
                <td><span class="badge badge-<?= htmlspecialchars($row['status']) ?>"><?= htmlspecialchars(ucfirst($row['status'])) ?></span></td>
                <td>
                    <?php if ($row['status'] === 'pending'): ?>
                    <button type="button" onclick="approveClaim(<?= (int) $row['id'] ?>, 'approve')">Setujui</button>
                    <button type="button" onclick="approveClaim(<?= (int) $row['id'] ?>, 'reject')">Tolak</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php
render_footer();
