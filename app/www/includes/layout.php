<?php

function render_header($title)
{
    $userId = current_user_id();
    $role = current_user_role();
    $nama = current_user_nama();
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($title) ?> - KlaimKu</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<header class="navbar">
    <a class="navbar-brand" href="/index.php">KlaimKu <span>PT Cahaya Abadi Sejahtera</span></a>
    <nav>
        <?php if ($userId): ?>
            <span class="navbar-user"><?= htmlspecialchars($nama) ?> (<?= htmlspecialchars($role) ?>)</span>
            <?php if ($role === 'hr'): ?>
                <a href="/admin/index.php">Dashboard HR</a>
            <?php endif; ?>
            <a href="/logout.php">Keluar</a>
        <?php endif; ?>
    </nav>
</header>
<div id="flash" class="flash"></div>
<main class="container">
<?php
}

function render_footer()
{
    ?>
</main>
<footer class="site-footer">&copy; <?= date('Y') ?> PT Cahaya Abadi Sejahtera - KlaimKu</footer>
<script src="/assets/js/app.js"></script>
</body>
</html>
<?php
}
