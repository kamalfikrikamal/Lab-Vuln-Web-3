-- Seed data KlaimKu
-- Akun demo karyawan (dicatat di README.md untuk peserta - akun HR SENGAJA
-- tidak diberikan, lihat docs/VULNERABILITIES.md untuk cara mendapatkan akses HR):
--   Karyawan : budi.santoso   / Karyawan123!
--   Karyawan : siti.aminah    / Karyawan456!
--   Karyawan : andi.wijaya    / Karyawan789!
--   HR       : dewi.lestari   / (tidak diberikan ke peserta)

INSERT INTO users (username, password_hash, nama, role) VALUES
('budi.santoso', '$2y$12$vcoeQO91EYKs3f8N4qfmKefqkWx9q2CJGGbx8WtePPBCHYiDeIWMa', 'Budi Santoso', 'karyawan'),
('siti.aminah',  '$2y$12$fVjMFmaBsLQ0gfbR7Q7NFeuZFVEqITkqG8xpYVXu.FxMss4fs4eUq', 'Siti Aminah', 'karyawan'),
('andi.wijaya',  '$2y$12$3bbejVK2QLFrSCzPQOqEv.XC8SFFrLVweDaQgARJB/4N3ElD4kv/i', 'Andi Wijaya', 'karyawan'),
('dewi.lestari', '$2y$12$JdVjuu7bwN/UY/60zsMctuju5TaBJx3ia998ESa/kpNuoOrNKqVsC', 'Dewi Lestari', 'hr');

INSERT INTO klaim (user_id, kategori, nominal, keterangan, status, approved_by) VALUES
(1, 'transport', 150000.00, 'Taksi ke kantor cabang Bandung untuk meeting klien.', 'approved', 4),
(1, 'makan', 85000.00, 'Makan siang tim saat lembur proyek migrasi server.', 'pending', NULL),
(2, 'medis', 320000.00, 'Konsultasi dokter umum + obat, kontrol rutin.', 'approved', 4),
(2, 'transport', 45000.00, 'Ojek online dari stasiun ke kantor karena hujan deras.', 'pending', NULL),
(3, 'lainnya', 200000.00, 'Pembelian alat tulis kantor untuk kebutuhan tim.', 'rejected', 4),
(3, 'makan', 60000.00, 'Makan malam saat perjalanan dinas ke Surabaya.', 'pending', NULL);
