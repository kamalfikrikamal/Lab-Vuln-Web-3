# Arsitektur Lab KlaimKu

## Gambaran Umum

Lab ketiga ini didistribusikan sebagai **Docker Compose** (sama seperti Lab 2, bukan
OVA/VM seperti Lab 1), berisi portal internal pengajuan klaim reimbursement karyawan
fiktif "KlaimKu" milik perusahaan "PT Cahaya Abadi Sejahtera". Sama seperti Lab 1 dan
2, ini adalah lab bergaya assessment (peserta melaporkan temuan), bukan CTF flag-based.

```
Peserta (laptop mana pun, Docker terpasang)
        |
        | docker compose up --build
        v
+-------------------------------------------+
|  Host peserta                              |
|                                             |
|  +----------------+     +----------------+ |
|  |  web (Apache +  |<--->|  db (MySQL 8)  | |
|  |  mod_php,       |     |  PORT 3306     | |
|  |  Debian based)  |     |  DIPUBLIKASIKAN| |
|  +--------+---------+     +-------+--------+ |
|           |                       |          |
|  8084/tcp |               3306/tcp|          |
+-----------|-----------------------|----------+
            v                       v
      http://localhost:8084   localhost:3306 (MySQL)
```

Berbeda dari Lab 1 & 2 (yang MySQL-nya tidak pernah diekspos), di lab ini port
MySQL **sengaja dipublikasikan** ke host - lihat "Keputusan Desain Kunci" di
bawah untuk alasannya. Port aplikasi (`8084`) dipublikasikan eksplisit seperti
Lab 2 - fokus lab ini bukan pada recon jaringan tersembunyi, melainkan pada
dampak berantai dari kebocoran kredensial + service yang salah diekspos, murni
command injection di satu fitur, dan privesc berbasis SUID binary misconfig di
level OS.

## Alur Temuan

Lihat `VULNERABILITIES.md` untuk detail & PoC. Mirip Lab 1 (yang punya rantai
wajib menuju shell), di lab ini ada satu rantai wajib menuju shell `www-data`
yang melibatkan 3 kerentanan berurutan; BFLA dan DOM-based XSS berdiri sendiri
sebagai temuan tambahan dengan dampak yang dibuktikan terpisah.

1. **Local File Inclusion** (`debug_viewer.php`) - tool developer yang lupa
   dihapus, `include()` tanpa validasi apa pun. Dipakai untuk membaca file
   konfigurasi lama (`/var/www/.env`, satu level di luar docroot) yang berisi
   kredensial database - kerentanan LFI di sini murni untuk *information
   disclosure*, tidak langsung berujung RCE (cocok untuk pengenalan LFI bagi
   pemula).
2. **Security Misconfiguration - Database Port Terekspos + Reuse Kredensial**
   - MySQL dipublikasikan ke `3306:3306` di `docker-compose.yml`. Kombinasi
   dengan kredensial dari temuan #1 membuat siapa pun bisa connect langsung ke
   DB dari luar container, membaca tabel `users` (menemukan akun `dewi.lestari`
   berperan `hr` yang kredensialnya tidak pernah dibagikan ke peserta), dan
   **menimpa `password_hash`-nya** dengan hash bcrypt buatan sendiri -> account
   takeover ke role HR tanpa perlu tahu password aslinya.
3. **Command Injection -> RCE** (`admin/verify_rekening.php`, field
   `rekening_bank`) - fitur khusus HR (baru bisa diakses setelah account
   takeover di temuan #2), `shell_exec()` dengan filter naif: memblokir
   karakter pemisah command yang paling umum (`;`, `|`, `&`) tapi lupa
   memfilter newline atau command substitution (backtick / `$()`) yang
   punya efek serupa -> shell `www-data`.
4. **Broken Function Level Authorization** (`admin/approve.php`) - endpoint aksi
   approve/reject klaim hanya memeriksa status login, lupa memeriksa role HR
   seperti yang sudah benar dilakukan di `admin/index.php` -> karyawan biasa
   bisa approve/reject klaim siapa pun termasuk miliknya sendiri, tanpa perlu
   akses HR maupun shell.
5. **DOM-based Reflected XSS** (`assets/js/app.js`, fitur flash message) - parameter
   URL `?msg=` disuntikkan langsung ke `innerHTML` di sisi klien, tanpa pernah
   menyentuh PHP - berbeda gaya dari Stored XSS di Lab 2 (server-side, `nl2br()`
   tanpa `htmlspecialchars()`). Berdiri sendiri, tidak terkait rantai RCE.
6. **Privilege Escalation** - bit SUID dipasang langsung pada `/usr/bin/find`
   (bukan lewat sudo rule seperti Lab 1, bukan lewat Linux capability seperti
   Lab 2) -> root shell (di dalam container).

## Struktur Repo

```
docker-compose.yml    Definisi service web + db (MySQL port dipublikasikan)
docker/web/            Dockerfile (Debian + Apache + mod_php, .env lama,
                       script cek-rekening.sh, setup SUID find) & vhost Apache
app/www/                Source PHP yang di-COPY ke image (docroot /var/www/html)
app/sql/                Skema + seed data MySQL
docs/                   Dokumentasi (arsitektur, kunci jawaban, checklist pengujian)
```

## Keputusan Desain Kunci

- **MySQL port SENGAJA dipublikasikan ke host** (`ports: "3306:3306"` di
  `docker-compose.yml`) - ini kebalikan dari keputusan Lab 1 & 2 (yang secara
  eksplisit tidak pernah expose DB). Keputusan ini disengaja untuk mengajarkan
  dampak nyata dari kombinasi dua hal yang masing-masing terlihat "kecil" -
  kebocoran kredensial (LFI) dan service yang salah diekspos (network
  misconfiguration) - yang jika digabung berakibat jauh lebih parah daripada
  masing-masing berdiri sendiri.
- **File `.env` lama diletakkan satu level di luar docroot** (`/var/www/.env`,
  bukan `/var/www/html/.env`) - narasinya adalah peninggalan sebelum migrasi ke
  environment variable Docker Compose yang lupa dihapus. Sengaja ditaruh di
  luar docroot supaya `GET /.env` langsung lewat browser tetap 404 (bukan bug
  "lupa `.htaccess`" yang terlalu mudah ditemukan) - satu-satunya jalan masuk
  adalah LFI di `debug_viewer.php` yang memang tidak dibatasi ke docroot sama
  sekali.
- **User DB aplikasi (`klaimku_app`) punya hak penuh (SELECT/UPDATE/INSERT) ke
  skema `klaimku`** - ini adalah privilege default yang diberikan image resmi
  `mysql:8.0` ke user yang didefinisikan lewat `MYSQL_USER`/`MYSQL_PASSWORD`,
  bukan hak tambahan yang kita suntikkan secara khusus. Inilah yang membuat
  serangan "timpa `password_hash`" pada temuan #2 bisa berjalan tanpa perlu
  privilege escalation di level database itu sendiri.
- **`debian:bookworm-slim` + `apache2`/`libapache2-mod-php` dari paket resmi Debian**,
  konsisten dengan Lab 2 - realistis, bukan konfigurasi buatan kita. Berbeda dari
  Lab 2, lab ini **tidak** memanfaatkan kuirk pemetaan ekstensi `.phar` - upload
  lampiran justru dibuat aman (whitelist ekstensi + validasi `finfo` di server +
  nama file random) karena fitur upload di lab ini tidak dimaksudkan sebagai
  jalur eksploitasi sama sekali.
- **`viewer.php` (preview lampiran resmi) dibuat AMAN** (`realpath()` + pengecekan
  prefix direktori dasar) sementara `debug_viewer.php` (tool debug yang tertinggal)
  sama sekali tidak memfilter input - kontras yang disengaja, mirip pola
  `dashboard.php` yang benar di Lab 1 dan field `subjek` yang benar di Lab 2:
  bukan semua endpoint file-handling rentan, peserta harus menguji satu per satu
  dan tidak berhenti di endpoint pertama yang terlihat "aman".
- **`admin/verify_rekening.php` (command injection) hanya bisa diakses role
  `hr`** - fitur ini secara sengaja tidak bisa dicapai lewat akun karyawan demo
  yang diberikan di README, memaksa peserta benar-benar menyelesaikan rantai
  LFI -> kebocoran kredensial DB -> account takeover sebelum bisa mencapai
  command injection-nya. Berbeda dari command injection Lab 1 (`courier-check.php`,
  tanpa filter apa pun), di sini ada filter yang **terlihat** masuk akal (blokir
  `;`/`|`/`&`) tapi tidak lengkap - satu akun hasil takeover sudah cukup untuk
  keseluruhan eksploitasi (submit payload lewat form, langsung tereksekusi &
  hasilnya tampil di respons yang sama, gaya klasik seperti Lab 1).
- **`admin/index.php` melakukan role-check benar, `admin/approve.php` tidak** -
  pola klasik BFLA dunia nyata: kontrol otorisasi diterapkan di halaman listing
  tapi lupa diterapkan ulang di endpoint aksi yang dipanggil terpisah lewat
  JavaScript (`fetch`), bukan navigasi halaman biasa.
- **Tidak ada `cap_drop`/`security_opt: no-new-privileges`/`privileged` pada
  service `web`** - berbeda dari Lab 2, di lab ini privesc tidak bergantung pada
  Linux capability sama sekali (murni bit SUID pada binary), jadi opsi hardening
  tersebut sebetulnya tidak akan mematikan jalur privesc lab ini, tapi tetap
  dibiarkan default untuk konsistensi dengan lab lain dan supaya tidak menimbulkan
  kesan "container ini sudah di-hardening".
- **"Root" pada lab ini adalah root di dalam container `web`, bukan root host** -
  sama seperti batasan di Lab 2. Tidak ada langkah container escape (tidak mount
  Docker socket, tidak `--privileged`, tidak `--pid=host`/`--net=host`).
  Instruktur perlu menekankan batas ini ke peserta - termasuk menekankan bahwa
  akses ke MySQL yang terekspos juga terbatas pada container `db`, bukan akses
  ke host peserta.
- **Login karyawan & HR memakai prepared statement + `password_verify()` yang
  benar** - konsisten dengan Lab 2 (bukan Lab 1, di mana staff login justru jadi
  target SQLi) - fokus lab ini bukan pada SQL Injection di aplikasi web sama
  sekali (akses ke data dicapai lewat koneksi DB langsung, bukan lewat query
  aplikasi yang bisa diinjeksi).
