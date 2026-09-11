<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require_role('hr');

$rekening = '';
$output = null;
$blocked = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rekening = trim($_POST['rekening_bank'] ?? '');

    // Filter memblokir karakter pemisah perintah yang paling umum - tapi lupa
    // memfilter newline atau command substitution (backtick / $()) yang
    // punya efek serupa untuk memisahkan/menyisipkan perintah shell.
    $blacklist = [';', '|', '&'];
    if (str_replace($blacklist, '', $rekening) !== $rekening) {
        $blocked = true;
    } else {
        // Validasi format nomor rekening lewat script peninggalan tim
        // finance - dipakai apa adanya sejak lama, tidak pernah ditinjau
        // ulang untuk keamanan input (tidak ada escapeshellarg()).
        $output = shell_exec('/opt/scripts/cek-rekening.sh ' . $rekening . ' 2>&1');
    }
}

require __DIR__ . '/../includes/layout.php';
render_header('Verifikasi Rekening');
?>

<div class="card">
    <h1>Verifikasi Rekening Bank</h1>
    <p class="muted">Cek format nomor rekening karyawan sebelum pencairan dana klaim.</p>
    <form method="post">
        <label>Nomor Rekening
            <input type="text" name="rekening_bank" value="<?= htmlspecialchars($rekening) ?>" required>
        </label>
        <button type="submit">Verifikasi</button>
    </form>

    <?php if ($blocked): ?>
        <p class="alert alert-error">Nomor rekening mengandung karakter yang tidak diizinkan.</p>
    <?php elseif ($output !== null): ?>
        <pre><?= htmlspecialchars($output) ?></pre>
    <?php endif; ?>

    <a href="/admin/index.php">&larr; Kembali</a>
</div>

<?php
render_footer();
