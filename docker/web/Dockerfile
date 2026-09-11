FROM debian:bookworm-slim

RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        apache2 \
        libapache2-mod-php \
        php-mysql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY app/www/ /var/www/html/
COPY docker/web/000-klaimku.conf /etc/apache2/sites-available/000-klaimku.conf

RUN a2dissite 000-default \
    && a2ensite 000-klaimku \
    && mkdir -p /var/www/html/uploads/klaim \
    && chown -R www-data:www-data /var/www/html/uploads

# File konfigurasi lama peninggalan sebelum migrasi ke environment variable
# Docker Compose (lihat docker-compose.yml, service web) - sengaja diletakkan
# SATU LEVEL DI LUAR docroot (/var/www/, bukan /var/www/html/) supaya tidak
# bisa diakses langsung lewat HTTP biasa (GET /.env -> 404), tapi tetap bisa
# dibaca lewat bug LFI di app/www/debug_viewer.php yang tidak dibatasi ke
# docroot sama sekali.
COPY docker/web/.env /var/www/.env
RUN chown www-data:www-data /var/www/.env

# Script validasi nomor rekening bank, dipanggil dari
# app/www/admin/verify_rekening.php lewat shell_exec() tanpa escapeshellarg().
COPY docker/web/cek-rekening.sh /opt/scripts/cek-rekening.sh
RUN chmod 755 /opt/scripts/cek-rekening.sh

# Sysadmin memberi bit SUID langsung pada `find` supaya sebuah script
# maintenance lama bisa mencari & membersihkan file lampiran tanpa perlu
# akses root penuh - "solusi cepat" yang keliru (harusnya lewat sudo rule
# yang dibatasi command tertentu, atau service systemd terpisah yang
# berjalan sebagai root) dan tidak pernah dicabut. Ini adalah misconfig
# privilege escalation yang disengaja untuk lab ini.
RUN chmod u+s /usr/bin/find

# Bukti keberhasilan privesc ke root - langsung ada begitu peserta punya shell
# root, tidak perlu dicari (bukan disembunyikan di path lain / butuh find).
RUN echo 'KLAIMKU{suid_find_root_pwn}' > /root/flag.txt \
    && chown root:root /root/flag.txt \
    && chmod 600 /root/flag.txt

EXPOSE 80

CMD ["apache2ctl", "-D", "FOREGROUND"]
