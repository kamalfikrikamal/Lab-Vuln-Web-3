# Build OVA (VirtualBox Appliance)

Panduan ini untuk maintainer yang perlu membangun (atau membangun ulang) file `.ova` dari
lab KlaimKu. Untuk peserta yang menerima file `.ova` jadi, lihat bagian "Menjalankan Lab
(OVA / VirtualBox)" di `README.md` - dokumen ini murni tentang proses build-nya.

## Cara kerja

Build otomatis lewat [Packer](https://www.packer.io/), builder `virtualbox-iso`:

1. Boot ISO Debian 12 netinst, install otomatis lewat preseed (`packer/http/preseed.cfg`).
2. Provisioning (`packer/scripts/*.sh`, urut sesuai nomor prefix): install Docker Engine,
   copy `app/`, `docker/`, `docker-compose.yml` apa adanya ke `/opt/klaimku`, jalankan
   `docker compose build` supaya image sudah jadi (tidak perlu build/network saat boot
   pertama penerima), seed database sekali, pasang systemd service yang menjalankan
   `docker compose up -d` di setiap boot, tulis banner IP+URL ke layar console, lalu
   bersihkan (log/cache/machine-id) dan matikan SSH untuk boot berikutnya.
3. VM dimatikan, Packer meng-export jadi satu file `.ova` (built-in `format = "ova"` pada
   builder `virtualbox-iso`, tidak perlu post-processor tambahan).

Stack di dalam VM **tidak berbeda sama sekali** dari jalur distribusi Docker Compose biasa -
`app/`, `docker/`, `docker-compose.yml` di-copy apa adanya, jadi skenario kerentanan
(termasuk publikasi port MySQL 3306 langsung ke jaringan lab dan privesc di dalam
container `web`) tidak berubah.

> **Beda dari Lab 1 & 2**: `docker-compose.yml` lab ini sengaja mempublikasikan port
> MySQL (`3306:3306`) ke luar container, bukan cuma port web (`8084`). Di OVA, karena
> adapter kedua VM (bridged) mendapat IP sendiri di jaringan lab, port 3306 tersebut ikut
> bisa diakses langsung oleh peserta dari luar VM juga - persis seperti pada jalur
> distribusi Docker Compose biasa (`localhost:3306`). Ini bagian dari skenario yang
> disengaja (dikombinasikan dengan kredensial yang bocor lewat LFI), bukan bug pada
> pipeline OVA ini.

## Prasyarat

- **VirtualBox** dan **Packer** (>= 1.9) terinstall di mesin build.
- Koneksi internet (download ISO Debian ~700MB + image `mysql:8.0` + paket Docker saat
  provisioning).
- Ruang disk kosong yang cukup (ISO + VM disk 20GB + hasil OVA) - sediakan minimal ~30GB.

Tidak ada setup jaringan tambahan yang perlu disiapkan di mesin build untuk adapter lab
default (**Bridged** - lihat bagian "Pilihan adapter" di bawah).

## Build

Dari root repo, buat dulu tarball `app/` + `docker/` + `docker-compose.yml` yang akan
di-upload ke VM (satu file tunggal - lihat catatan di bawah kenapa ini perlu langkah
manual, bukan diupload sebagai direktori langsung oleh Packer):

```bash
mkdir -p packer/build-context
tar czf packer/build-context/klaimku-src.tar.gz app docker docker-compose.yml
```

Lalu:

```bash
packer init packer/klaimku.pkr.hcl
packer validate packer/klaimku.pkr.hcl
packer build packer/klaimku.pkr.hcl
```

Ulangi langkah `tar czf` di atas setiap kali `app/`, `docker/`, atau `docker-compose.yml`
berubah dan sebelum build ulang - tarball tidak dibuat otomatis oleh Packer.

**Kenapa tarball, bukan upload direktori langsung?** Provisioner `file` bawaan Packer,
saat dites di kombinasi VirtualBox + komunikator SSH di mesin ini, ternyata menghilangkan
satu level subfolder pada upload direktori berlapis (`docker/web/Dockerfile` jadi
`docker/Dockerfile`; isi `app/www/` dan `app/sql/` malah tercampur rata langsung di bawah
`app/`) - menyebabkan `docker compose build` gagal karena `docker/web/Dockerfile` tidak
ketemu. Upload satu file tarball lalu `tar xzf` di dalam VM (dilakukan oleh
`10-layout-app.sh`) sepenuhnya menghindari masalah ini.

Perkiraan waktu total: **20-40 menit** (mayoritas: install Debian unattended, install
Docker, pull image `mysql:8.0`, dan zero-fill free space sebelum export - build yang
terlihat "diam" selama beberapa menit di tahap-tahap ini adalah normal, bukan hang).

Hasil: `packer/output/klaimku/klaimku.ova` (di-`.gitignore`, jangan di-commit ke repo -
distribusikan terpisah, mis. lewat link download).

## Pilihan adapter jaringan lab

Selain NAT (adapter 1, dipakai Packer sendiri saat build untuk akses internet), VM punya
adapter kedua khusus untuk diakses peserta:

- **`bridged` (default).** Adapter ini reliable saat proses export-ke-OVA lalu import di
  mesin lain - VirtualBox akan minta peserta memilih interface fisik saat import (biasanya
  otomatis pilih yang pertama aktif). VM langsung dapat IP dari DHCP jaringan peserta,
  tanpa setup tambahan. Trade-off: lab jadi bisa diakses siapa pun di jaringan/LAN yang
  sama dengan peserta (termasuk port MySQL 3306-nya) - pertimbangkan ini kalau training
  berjalan di jaringan bersama.

  > **Penting - bridging di atas Wi-Fi sering gagal dapat IP.** Kalau interface fisik yang
  > dipilih (`bridge_interface`, baik saat build maupun saat peserta import) adalah adapter
  > **Wi-Fi**, adapter kedua VM kerap tidak pernah dapat IP (`ip link` menunjukkan status
  > UP dengan carrier, tapi `dhclient -v` menunjukkan `DHCPDISCOVER` terkirim berulang dan
  > `No DHCPOFFERS received` - persis bunyi banner console "No IP yet..."). Ini keterbatasan
  > fundamental VirtualBox: kebanyakan access point/driver Wi-Fi hanya meneruskan frame dari
  > MAC address yang sudah asosiasi ke AP, jadi MAC virtual VM ditolak di level 802.11 -
  > bukan bug pada preseed/script provisioning lab ini (sudah dikonfirmasi terjadi juga pada
  > OVA Lab 2 di host yang sama). **Mitigasi:** pakai koneksi **Ethernet kabel** untuk
  > `bridge_interface` kalau memungkinkan (build maupun saat peserta import), atau jatuhkan
  > pilihan ke adapter `hostonly` di bawah kalau mesin build/peserta hanya punya Wi-Fi.
- **`hostonly`.** Terisolasi (hanya bisa diakses dari mesin peserta sendiri), tapi ada
  bug lama VirtualBox (Oracle #22158, fixed di VirtualBox 7.1.8) yang membuat adapter
  host-only hasil export OVA kadang muncul sebagai "Not attached" saat di-import di
  VirtualBox versi lebih lama - peserta perlu perbaikan manual (VM Settings > Network >
  Adapter 2 > pilih/buat ulang host-only network). Kalau tetap memilih opsi ini, mesin
  **build** juga perlu punya host-only network `vboxnet0` dengan DHCP aktif:
  ```bash
  VBoxManage hostonlyif create
  VBoxManage dhcpserver add --ifname vboxnet0 \
    --ip 192.168.56.1 --netmask 255.255.255.0 \
    --lowerip 192.168.56.10 --upperip 192.168.56.99 --enable
  ```

Untuk build dengan host-only:

```bash
packer build -var "lab_adapter_type=hostonly" packer/klaimku.pkr.hcl
```

## Kapan perlu rebuild

Image Docker di dalam OVA di-*bake* saat build (`docker compose build` dijalankan sekali
di dalam VM saat provisioning), **bukan** saat VM pertama kali dinyalakan oleh penerima.
Artinya: setiap kali `app/`, `docker/`, atau `docker-compose.yml` berubah, `.ova` yang
sudah didistribusikan menjadi basi dan perlu di-build ulang dari awal (tidak ada mekanisme
update inkremental).

## Kredensial bawaan

- User VM (login console): `klaimku` / `ChangeMe123!` (bisa diubah lewat variable
  `ssh_password` di `packer/klaimku.pkr.hcl` sebelum build). User ini punya sudo
  `NOPASSWD` - sengaja dibiarkan meski SSH sudah dimatikan, karena kalau SSH diaktifkan
  lagi secara manual untuk maintenance, akses sudo tetap dibutuhkan; ini bukan bagian dari
  permukaan serangan yang dimaksudkan (SSH mati by default).
- Password MySQL (`klaimku_root_pw`, `klaimku_app_pw`) sama seperti di
  `docker-compose.yml` untuk jalur distribusi Docker Compose biasa - bukan sesuatu yang
  diperkenalkan khusus oleh pipeline OVA ini.
