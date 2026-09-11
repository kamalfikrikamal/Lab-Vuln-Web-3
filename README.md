# Lab Web 3 - KlaimKu

Lab pentest web ketiga, bergaya *assessment* (laporkan temuan, bukan CTF flag-based).
Target: **KlaimKu**, portal internal fiktif pengajuan klaim reimbursement karyawan
milik perusahaan fiktif "PT Cahaya Abadi Sejahtera".

Untuk penggunaan edukasi/latihan pentest terotorisasi saja - jalankan hanya di
environment lokal/terisolasi.

## Menjalankan Lab (Docker Compose)

```bash
docker compose up --build
```

Aplikasi tersedia di [http://localhost:8084](http://localhost:8084).

Hentikan & bersihkan:
```bash
docker compose down -v
```

## Menjalankan Lab (OVA / VirtualBox)

Lab ini juga tersedia sebagai **OVA (VirtualBox)** untuk skenario yang butuh VM mandiri
dengan IP sendiri tanpa peserta perlu install Docker.

1. Buka VirtualBox > **File > Import Appliance**, pilih file `klaimku.ova` yang diterima
   dari instruktur.
2. Saat proses import, VirtualBox akan meminta memilih interface jaringan fisik untuk
   adapter kedua (bridged) - pilih interface yang terhubung ke jaringan yang sama dengan
   laptop Anda.
3. Nyalakan VM. Begitu boot selesai, layar console VM (**tanpa perlu login**) akan
   menampilkan IP dan URL lab, contoh:
   ```
   === KlaimKu Lab ===
   Lab URL: http://192.168.1.50:8084
   ====================
   ```
4. Buka URL tersebut di browser pada laptop Anda.

Jika adapter jaringan VM menampilkan "Not attached" setelah import, buka VM Settings >
Network > Adapter 2 dan pilih ulang jenis adapter (bridged) secara manual, lalu nyalakan
ulang VM. Panduan build OVA untuk maintainer ada di `docs/OVA_BUILD.md`.

## Akun Demo

| Username        | Password        | Role     |
|-----------------|-----------------|----------|
| `budi.santoso`  | `Karyawan123!`  | Karyawan |
| `siti.aminah`   | `Karyawan456!`  | Karyawan |
| `andi.wijaya`   | `Karyawan789!`  | Karyawan |

Akun-akun ini disediakan supaya peserta bisa menjelajahi fitur normal aplikasi
sebagai karyawan (mengajukan klaim, melihat riwayat). Tidak semua fitur atau
endpoint aman - bagian dari latihan adalah menemukan sendiri mana yang rentan.

Ada juga role **HR** di aplikasi ini (untuk meninjau & menyetujui klaim), tapi
kredensialnya **sengaja tidak diberikan** di sini - mendapatkan akses ke role
tersebut adalah bagian dari latihan.

## Cakupan

Lab ini berisi beberapa kerentanan yang disengaja di level aplikasi web dan satu
jalur privilege escalation di level container. Tidak ada dokumentasi kerentanan
yang dibagikan ke peserta - lihat `docs/` hanya untuk instruktur/penilai
(`VULNERABILITIES.md` berisi kunci jawaban dan **tidak** ikut ke dalam image
Docker yang dijalankan peserta).

## Struktur Repo

```
docker-compose.yml    Definisi service web + db
docker/web/            Dockerfile, vhost Apache
app/www/                Source PHP aplikasi
app/sql/                Skema + seed data MySQL
docs/                   Dokumentasi instruktur (arsitektur, kunci jawaban, checklist pengujian)
packer/                 Build OVA (VirtualBox) via Packer - lihat docs/OVA_BUILD.md
```
