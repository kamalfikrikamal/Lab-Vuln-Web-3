<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require_login();

$userId = current_user_id();

$stmt = $mysqli->prepare('SELECT id, kategori, nominal, status, created_at FROM klaim WHERE user_id = ? ORDER BY created_at DESC');
$stmt->bind_param('i', $userId);
$stmt->execute();
$klaims = $stmt->get_result();

require __DIR__ . '/includes/layout.php';
render_header('Dashboard');
?>

<div class="card">
    <h1>Ajukan Klaim Reimbursement</h1>
    <form method="post" action="/submit_klaim.php" enctype="multipart/form-data">
        <label>Kategori
            <select name="kategori" required>
                <option value="transport">Transport</option>
                <option value="medis">Medis</option>
                <option value="makan">Makan</option>
                <option value="lainnya">Lainnya</option>
            </select>
        </label>
        <label>Nominal (Rp)
            <input type="number" name="nominal" min="1" step="1" required>
        </label>
        <label>Keterangan
            <textarea name="keterangan" rows="3" required></textarea>
        </label>
        <label>Lampiran Struk (opsional, jpg/png/pdf)
            <input type="file" name="lampiran" accept=".jpg,.jpeg,.png,.pdf">
        </label>
        <button type="submit">Ajukan Klaim</button>
    </form>
</div>

<div class="card">
    <div class="card-header-row">
        <h2>Klaim Saya</h2>
        <form method="get" action="/search.php" class="search-form">
            <input type="text" name="q" placeholder="Cari keterangan klaim...">
            <button type="submit">Cari</button>
        </form>
    </div>
    <table class="table">
        <thead>
            <tr><th>Tanggal</th><th>Kategori</th><th>Nominal</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
            <?php while ($row = $klaims->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['created_at']) ?></td>
                <td><?= htmlspecialchars(ucfirst($row['kategori'])) ?></td>
                <td>Rp <?= number_format((float) $row['nominal'], 0, ',', '.') ?></td>
                <td><span class="badge badge-<?= htmlspecialchars($row['status']) ?>"><?= htmlspecialchars(ucfirst($row['status'])) ?></span></td>
                <td><a href="/claim.php?id=<?= (int) $row['id'] ?>">Detail</a></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php
render_footer();
