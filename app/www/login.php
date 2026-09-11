<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';

$error = '';

if (isset($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $mysqli->prepare('SELECT id, password_hash, nama, role FROM users WHERE username = ?');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['nama'] = $user['nama'];
        $_SESSION['role'] = $user['role'];
        $welcome = 'Selamat datang kembali, ' . $user['nama'] . '!';
        header('Location: /index.php?msg=' . urlencode($welcome));
        exit;
    }

    $error = 'Username atau password salah.';
}

require __DIR__ . '/includes/layout.php';
render_header('Masuk');
?>
<div class="card card-narrow">
    <h1>Masuk ke KlaimKu</h1>
    <?php if ($error): ?>
        <p class="alert alert-error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <form method="post">
        <label>Username
            <input type="text" name="username" required autofocus>
        </label>
        <label>Password
            <input type="password" name="password" required>
        </label>
        <button type="submit">Masuk</button>
    </form>
</div>
<?php
render_footer();
