<?php
// Tool internal developer untuk preview cepat file mentah (lampiran, log,
// template) saat debugging fitur upload. Harusnya dihapus sebelum rilis
// produksi - tertinggal di sini.
$path = $_GET['path'] ?? '';

if ($path === '') {
    die('Gunakan ?path=');
}

include $path;
