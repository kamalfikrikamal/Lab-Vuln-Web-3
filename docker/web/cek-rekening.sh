#!/bin/bash
# Validasi format nomor rekening bank (10-16 digit) sebelum pencairan dana
# klaim. Script legacy tim finance, dipanggil langsung dengan argumen mentah
# dari aplikasi web (lihat app/www/admin/verify_rekening.php).
if [[ "$1" =~ ^[0-9]{10,16}$ ]]; then
    echo "Nomor rekening $1 valid."
else
    echo "Nomor rekening $1 tidak valid."
fi
