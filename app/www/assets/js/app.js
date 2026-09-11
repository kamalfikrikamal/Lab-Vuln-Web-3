// KlaimKu - shared front-end script (dimuat di semua halaman)

// Tampilkan pesan notifikasi (mis. "Klaim berhasil diajukan") lewat parameter
// URL ?msg=... setelah redirect. innerHTML dipakai (bukan textContent) supaya
// pesan bisa mengandung markup ringan seperti <strong> untuk penekanan.
(function showFlashMessage() {
    var params = new URLSearchParams(window.location.search);
    var msg = params.get('msg');
    var flashEl = document.getElementById('flash');

    if (msg && flashEl) {
        flashEl.innerHTML = msg;
        flashEl.classList.add('flash-visible');
    }
})();

// Dipanggil dari dashboard HR (admin/index.php) untuk approve/reject klaim
// tanpa reload halaman.
function approveClaim(id, action) {
    var formData = new FormData();
    formData.append('id', id);
    formData.append('action', action);

    fetch('/admin/approve.php', {
        method: 'POST',
        body: formData
    })
        .then(function (res) { return res.json(); })
        .then(function () { window.location.reload(); })
        .catch(function () { alert('Gagal memproses aksi.'); });
}
