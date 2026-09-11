<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require_login();

$userId = current_user_id();
$q = $_GET['q'] ?? '';

$results = [];
if ($q !== '') {
    $like = '%' . $q . '%';
    $stmt = $mysqli->prepare('SELECT id, kategori, nominal, status, created_at FROM klaim WHERE user_id = ? AND keterangan LIKE ? ORDER BY created_at DESC');
    $stmt->bind_param('is', $userId, $like);
    $stmt->execute();
    $results = $stmt->get_result();
}

require __DIR__ . '/includes/layout.php';
render_header('Cari Klaim');
?>

<div class="card">
    <h1>Cari Klaim</h1>
    <form method="get" action="/search.php" class="search-form">
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Cari keterangan klaim...">
        <button type="submit">Cari</button>
    </form>

    <?php if ($q !== ''): ?>
    <table class="table">
        <thead>
            <tr><th>Tanggal</th><th>Kategori</th><th>Nominal</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
            <?php if ($results): while ($row = $results->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['created_at']) ?></td>
                <td><?= htmlspecialchars(ucfirst($row['kategori'])) ?></td>
                <td>Rp <?= number_format((float) $row['nominal'], 0, ',', '.') ?></td>
                <td><span class="badge badge-<?= htmlspecialchars($row['status']) ?>"><?= htmlspecialchars(ucfirst($row['status'])) ?></span></td>
                <td><a href="/claim.php?id=<?= (int) $row['id'] ?>">Detail</a></td>
            </tr>
            <?php endwhile; endif; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php
render_footer();
