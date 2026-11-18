-- ==============================================================
-- S-LECTURA: Migrasi 003 - Seed Data Kelas Manajemen Informatika A 5
-- Sandi awal demo dosen & mahasiswa: "123456" (wajib diganti saat login pertama).
-- ==============================================================
USE `s_lectura`;

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- BERSIHKAN DATA LAMA (dosen, mahasiswa, jadwal, status, slot)
-- Pertahankan admin (id=1) dan program_studi
-- ============================================================
DELETE FROM `status_ketersediaan`;
DELETE FROM `slot_konsultasi`;
DELETE FROM `jadwal_mengajar`;
DELETE FROM `dosen`;
DELETE FROM `users` WHERE id > 1;  -- Pertahankan admin
DELETE FROM `mahasiswa`;
DELETE FROM `kelas`;
DELETE FROM `mata_kuliah`;
UPDATE `users`
SET `password_hash` = '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy',
    `must_change_password` = 1
WHERE `id` = 1 AND `role` = 'admin';

-- Reset auto-increment
ALTER TABLE `users` AUTO_INCREMENT = 2;
ALTER TABLE `dosen` AUTO_INCREMENT = 1;
ALTER TABLE `kelas` AUTO_INCREMENT = 1;
ALTER TABLE `mata_kuliah` AUTO_INCREMENT = 1;
ALTER TABLE `jadwal_mengajar` AUTO_INCREMENT = 1;
ALTER TABLE `slot_konsultasi` AUTO_INCREMENT = 1;
ALTER TABLE `status_ketersediaan` AUTO_INCREMENT = 1;
ALTER TABLE `mahasiswa` AUTO_INCREMENT = 1;

-- ============================================================
-- 1. PROGRAM STUDI (pastikan id=2 = Manajemen Informatika ada)
-- ============================================================
INSERT IGNORE INTO `program_studi` (`id`, `nama_prodi`, `jurusan`) VALUES
(1, 'D4 Rekayasa Perangkat Lunak', 'Teknologi Informasi'),
(2, 'Manajemen Informatika',       'Teknologi Informasi'),
(3, 'D3 Akuntansi Terapan',        'Ekonomi dan Bisnis');

-- Update nama prodi id=2 agar konsisten dengan spec
UPDATE `program_studi` SET `nama_prodi`='Manajemen Informatika', `jurusan`='Teknologi Informasi' WHERE id=2;

-- ============================================================
-- 2. KELAS
-- ============================================================
INSERT INTO `kelas` (`id`, `nama_kelas`, `id_prodi`, `semester`, `tahun_ajaran`) VALUES
(1, 'Manajemen Informatika A 5', 2, 5, '2026/2027 Ganjil');

-- ============================================================
-- 3. MATA KULIAH
-- ============================================================
INSERT INTO `mata_kuliah` (`id`, `kode`, `nama`) VALUES
(1, 'PMI1516', 'Pemrograman Basis Data'),
(2, 'PKU1511', 'Proyek Mandiri'),
(3, 'PMI1517', 'Pemrograman Berbasis Mobile'),
(4, 'PMI1519', 'E-Bisnis'),
(5, 'PMI1518', 'Etika dan Hukum Teknologi Informasi'),
(6, 'PMI1511', 'Keamanan Sistem Informasi');

-- ============================================================
-- 4. USERS DOSEN (id 2-7)
-- Hash berikut sudah cocok dengan password_verify('123456', password_hash).
-- ============================================================
-- Hash '123456': $2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy
INSERT INTO `users` (`id`, `nama`, `email`, `password_hash`, `role`, `npm`, `must_change_password`) VALUES
(2,  'Dosen Demo 1', 'dosen1@example.test',      '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'dosen',     NULL, 1),
(3,  'Dosen Demo 2',             'dosen2@example.test',    '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'dosen',     NULL, 1),
(4,  'Dosen Demo 3',      'dosen3@example.test',      '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'dosen',     NULL, 1),
(5,  'Dosen Demo 4',              'dosen4@example.test',    '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'dosen',     NULL, 1),
(6,  'Dosen Demo 5',              'dosen5@example.test',      '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'dosen',     NULL, 1),
(7,  'Dosen Demo 6',         'dosen6@example.test', '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'dosen',     NULL, 1);
-- ============================================================
-- 5. USERS MAHASISWA (id 8-41)
-- Sandi awal demo sama: "123456" (wajib diganti saat login pertama).
-- ============================================================
INSERT INTO `users` (`id`, `nama`, `email`, `password_hash`, `role`, `npm`, `must_change_password`) VALUES
(8,  'Mahasiswa Demo 01',             'mahasiswa01@example.test', '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'mahasiswa', NULL, 1),
(9,  'Mahasiswa Demo 02',           'mahasiswa02@example.test', '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'mahasiswa', NULL, 1),
(10, 'Mahasiswa Demo 03',          'mahasiswa03@example.test', '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'mahasiswa', NULL, 1),
(11, 'Mahasiswa Demo 04',            'mahasiswa04@example.test', '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'mahasiswa', NULL, 1),
(12, 'Mahasiswa Demo 05',        'mahasiswa05@example.test', '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'mahasiswa', NULL, 1),
(13, 'Mahasiswa Demo 06',           'mahasiswa06@example.test', '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'mahasiswa', NULL, 1),
(14, 'Mahasiswa Demo 07',                  'mahasiswa07@example.test', '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'mahasiswa', NULL, 1),
(15, 'Mahasiswa Demo 08',                    'mahasiswa08@example.test', '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'mahasiswa', NULL, 1),
(16, 'Mahasiswa Demo 09',                 'mahasiswa09@example.test', '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'mahasiswa', NULL, 1),
(17, 'Mahasiswa Demo 10',             'mahasiswa10@example.test', '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'mahasiswa', NULL, 1),
(18, 'Mahasiswa Demo 11',           'mahasiswa11@example.test', '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'mahasiswa', NULL, 1),
(19, 'Mahasiswa Demo 12',           'mahasiswa12@example.test', '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'mahasiswa', NULL, 1),
(20, 'Mahasiswa Demo 13',          'mahasiswa13@example.test', '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'mahasiswa', NULL, 1),
(21, 'Mahasiswa Demo 14',                  'mahasiswa14@example.test', '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'mahasiswa', NULL, 1),
(22, 'Mahasiswa Demo 15',          'mahasiswa15@example.test', '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'mahasiswa', NULL, 1),
(23, 'Mahasiswa Demo 16',          'mahasiswa16@example.test', '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'mahasiswa', NULL, 1),
(24, 'Mahasiswa Demo 17',      'mahasiswa17@example.test', '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'mahasiswa', NULL, 1),
(25, 'Mahasiswa Demo 18',       'mahasiswa18@example.test', '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'mahasiswa', NULL, 1),
(26, 'Mahasiswa Demo 19',    'mahasiswa19@example.test', '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'mahasiswa', NULL, 1),
(27, 'Mahasiswa Demo 20',       'mahasiswa20@example.test', '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'mahasiswa', NULL, 1),
(28, 'Mahasiswa Demo 21',      'mahasiswa21@example.test', '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'mahasiswa', NULL, 1),
(29, 'Mahasiswa Demo 22',             'mahasiswa22@example.test', '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'mahasiswa', NULL, 1),
(30, 'Mahasiswa Demo 23',          'mahasiswa23@example.test', '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'mahasiswa', NULL, 1),
(31, 'Mahasiswa Demo 24',                'mahasiswa24@example.test', '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'mahasiswa', NULL, 1),
(32, 'Mahasiswa Demo 25',           'mahasiswa25@example.test', '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'mahasiswa', NULL, 1),
(33, 'Mahasiswa Demo 26',           'mahasiswa26@example.test', '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'mahasiswa', NULL, 1),
(34, 'Mahasiswa Demo 27',                'mahasiswa27@example.test', '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'mahasiswa', NULL, 1),
(35, 'Mahasiswa Demo 28',                 'mahasiswa28@example.test', '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'mahasiswa', NULL, 1),
(36, 'Mahasiswa Demo 29',             'mahasiswa29@example.test', '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'mahasiswa', NULL, 1),
(37, 'Mahasiswa Demo 30',           'mahasiswa30@example.test', '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'mahasiswa', NULL, 1),
(38, 'Mahasiswa Demo 31',       'mahasiswa31@example.test', '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'mahasiswa', NULL, 1),
(39, 'Mahasiswa Demo 32',                 'mahasiswa32@example.test', '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'mahasiswa', NULL, 1),
(40, 'Mahasiswa Demo 33',             'mahasiswa33@example.test', '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'mahasiswa', NULL, 1),
(41, 'Mahasiswa Demo 34',            'mahasiswa34@example.test', '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy', 'mahasiswa', NULL, 1);
-- ============================================================
-- 6. DATA PROFIL DOSEN
-- ============================================================
INSERT INTO `dosen` (`id`, `user_id`, `nidn_nip`, `id_prodi`, `foto`, `bidang_keahlian`) VALUES
(1, 2, NULL,                 2, NULL, 'Pemrograman Basis Data, Teknologi Informasi, Rekayasa Perangkat Lunak'),
(2, 3, NULL,                 2, NULL, 'Rekayasa Perangkat Lunak, Pemrograman Mobile, Teknik Elektro'),
(3, 4, NULL,                 2, NULL, 'Sistem Informasi, Pemrograman Web, Manajemen Proyek'),
(4, 5, NULL, 2, NULL, 'Pemrograman Mobile, Keamanan Sistem Informasi, Basis Data'),
(5, 6, NULL, 2, NULL, 'E-Bisnis, Etika dan Hukum TI, Sistem Informasi'),
(6, 7, NULL,                 2, NULL, 'Keamanan Sistem Informasi, E-Bisnis, Pemrograman Web');

-- ============================================================
-- 7. TABEL MAHASISWA (relasi user_id → npm + kelas + prodi)
-- ============================================================
INSERT INTO `mahasiswa` (`id`, `user_id`, `npm`, `id_kelas`, `id_prodi`) VALUES
(1,  8,  'DEMO0001',         1, 2),
(2,  9,  'DEMO0002',         1, 2),
(3,  10, 'DEMO0003',         1, 2),
(4,  11, 'DEMO0004',         1, 2),
(5,  12, 'DEMO0005',         1, 2),
(6,  13, 'DEMO0006',         1, 2),
(7,  14, 'DEMO0007',         1, 2),
(8,  15, 'DEMO0008',         1, 2),
(9,  16, 'DEMO0009',         1, 2),
(10, 17, 'DEMO0010',         1, 2),
(11, 18, 'DEMO0011',         1, 2),
(12, 19, 'DEMO0012',         1, 2),
(13, 20, 'DEMO0013',         1, 2),
(14, 21, 'DEMO0014',         1, 2),
(15, 22, 'DEMO0015',         1, 2),
(16, 23, 'DEMO0016',         1, 2),
(17, 24, 'DEMO0017',         1, 2),
(18, 25, 'DEMO0018',         1, 2),
(19, 26, 'DEMO0019',         1, 2),
(20, 27, 'DEMO0020',         1, 2),
(21, 28, 'DEMO0021',         1, 2),
(22, 29, 'DEMO0022',         1, 2),
(23, 30, 'DEMO0023',         1, 2),
(24, 31, 'DEMO0024',         1, 2),
(25, 32, 'DEMO0025',         1, 2),
(26, 33, 'DEMO0026',         1, 2),
(27, 34, 'DEMO0027',         1, 2),
(28, 35, 'DEMO0028',         1, 2),
(29, 36, 'DEMO0029',         1, 2),
(30, 37, 'DEMO0030',         1, 2),
(31, 38, 'DEMO0031',         1, 2),
(32, 39, 'DEMO0032',         1, 2),
(33, 40, 'DEMO0033',         1, 2),
-- Seluruh NPM pada seed ini bersifat sintetis dan unik.
(34, 41, 'DEMO0034', 1, 2);
-- ============================================================
-- 8. JADWAL MENGAJAR (satu dosen pemilik pada setiap jadwal)
-- ============================================================
-- Dosen Demo 1-6 memiliki profil dosen dengan ID 1-6.
-- MK: PMI1516=1, PKU1511=2, PMI1517=3, PMI1519=4, PMI1518=5, PMI1511=6
INSERT INTO `jadwal_mengajar` (`id`, `id_dosen`, `id_mk`, `id_kelas`, `hari`, `jam_mulai`, `jam_selesai`, `tipe_sesi`, `termin`, `sks`, `mode`, `ruang`) VALUES
(1,  1, 1, 1, 'Senin',  '07:30:00', '08:20:00', 'teori',   '1', 1, 'daring', NULL),
(2,  3, 2, 1, 'Senin',  '13:00:00', '15:00:00', 'praktik', '1', 1, 'daring', NULL),
(3,  1, 1, 1, 'Senin',  '15:00:00', '19:00:00', 'praktik', '1', 2, 'luring', 'Lab Terpadu Lab. Komputer 3.6'),
(4,  3, 2, 1, 'Selasa', '13:00:00', '15:00:00', 'praktik', '1', 1, 'daring', NULL),
(5,  4, 3, 1, 'Selasa', '07:00:00', '07:50:00', 'teori',   '1', 1, 'daring', NULL),
(6,  5, 4, 1, 'Rabu',   '15:00:00', '17:00:00', 'praktik', '1', 1, 'luring', 'Lab Terpadu Ruang 2.4'),
(7,  6, 5, 1, 'Rabu',   '07:00:00', '07:50:00', 'teori',   '1', 1, 'daring', NULL),
(8,  4, 3, 1, 'Kamis',  '17:00:00', '21:00:00', 'praktik', '1', 2, 'luring', 'Lab Terpadu Lab. Komputer 3.1'),
(9,  1, 6, 1, 'Kamis',  '08:40:00', '10:20:00', 'teori',   '1', 2, 'daring', NULL),
(10, 5, 4, 1, 'Kamis',  '07:00:00', '07:50:00', 'teori',   '1', 1, 'daring', NULL),
(11, 3, 2, 1, 'Jumat',  '09:00:00', '11:00:00', 'praktik', '1', 1, 'daring', NULL),
(12, 6, 5, 1, 'Jumat',  '13:30:00', '15:30:00', 'praktik', '1', 1, 'luring', 'Lab Terpadu Ruang 2.4'),
(13, 1, 6, 1, 'Jumat',  '17:00:00', '21:00:00', 'praktik', '1', 2, 'luring', 'Lab Terpadu Lab. Komputer 3.1');

-- ============================================================
-- 10. STATUS KETERSEDIAAN (semua dosen mulai dengan "Tersedia")
-- ============================================================
INSERT INTO `status_ketersediaan` (`id`, `id_dosen`, `status`, `catatan`, `berlaku_sampai`) VALUES
(1, 1, 'Tersedia', '[DUMMY] Status awal sistem', NULL),
(2, 2, 'Tersedia', '[DUMMY] Status awal sistem', NULL),
(3, 3, 'Tersedia', '[DUMMY] Status awal sistem', NULL),
(4, 4, 'Tersedia', '[DUMMY] Status awal sistem', NULL),
(5, 5, 'Tersedia', '[DUMMY] Status awal sistem', NULL),
(6, 6, 'Tersedia', '[DUMMY] Status awal sistem', NULL);

-- ============================================================
-- 11. SLOT KONSULTASI (1-2 slot per dosen, tidak bentrok jadwal)
-- Semua diberi catatan [DUMMY]
-- ============================================================
-- D1 (Reza): mengajar Senin 07:30-08:20, 15:00-19:00; Kamis 08:40-10:20, 17:00-21:00; Jumat 17:00-21:00
-- Slot: Senin 09:00-10:30 (konsultasi), Kamis 11:00-12:30 (bimbingan)
INSERT INTO `slot_konsultasi` (`id`, `id_dosen`, `hari`, `jam_mulai`, `jam_selesai`, `jenis`, `catatan`, `aktif`) VALUES
(1,  1, 'Senin',  '09:00:00', '10:30:00', 'konsultasi', '[DUMMY] Konsultasi akademik & KRS semester aktif', 1),
(2,  1, 'Kamis',  '11:00:00', '12:30:00', 'bimbingan',  '[DUMMY] Bimbingan Tugas Akhir & Proyek Mandiri',  1),

-- D2 (Dwirgo): mengajar Senin 07:30-08:20, 13:00-19:00; Selasa 13:00-15:00; Jumat 09:00-11:00
-- Slot: Rabu 09:00-11:00 (konsultasi), Kamis 09:00-10:30 (bimbingan)
(3,  2, 'Rabu',   '09:00:00', '11:00:00', 'konsultasi', '[DUMMY] Konsultasi materi & tugas kuliah',        1),
(4,  2, 'Kamis',  '09:00:00', '10:30:00', 'bimbingan',  '[DUMMY] Bimbingan PKU & Proyek Akhir',            1),

-- D3 (Dewi): mengajar Senin 13:00-15:00; Selasa 13:00-15:00; Jumat 09:00-11:00
-- Slot: Rabu 10:00-12:00 (bimbingan), Kamis 13:00-15:00 (konsultasi)
(5,  3, 'Rabu',   '10:00:00', '12:00:00', 'bimbingan',  '[DUMMY] Bimbingan Proyek Mandiri & laporan PKU',  1),
(6,  3, 'Kamis',  '13:00:00', '15:00:00', 'konsultasi', '[DUMMY] Konsultasi akademik & rencana studi',     1),

-- D4 (Akhmad): mengajar Selasa 07:00-07:50; Kamis 08:40-10:20, 17:00-21:00
-- Slot: Senin 10:00-12:00 (konsultasi), Rabu 13:00-15:00 (bimbingan)
(7,  4, 'Senin',  '10:00:00', '12:00:00', 'konsultasi', '[DUMMY] Konsultasi Pemrograman Mobile & Keamanan', 1),
(8,  4, 'Rabu',   '13:00:00', '15:00:00', 'bimbingan',  '[DUMMY] Bimbingan Proyek Akhir & Tugas Besar',    1),

-- D5 (Rima): mengajar Rabu 07:00-07:50, 15:00-17:00; Kamis 07:00-07:50; Jumat 13:30-15:30
-- Slot: Selasa 10:00-12:00 (konsultasi), Kamis 10:30-12:00 (bimbingan)
(9,  5, 'Selasa', '10:00:00', '12:00:00', 'konsultasi', '[DUMMY] Konsultasi E-Bisnis & Etika TI',          1),
(10, 5, 'Kamis',  '10:30:00', '12:00:00', 'bimbingan',  '[DUMMY] Bimbingan PKU & laporan proyek mandiri',  1),

-- D6 (Kurniawan): mengajar Rabu 07:00-07:50, 15:00-17:00; Kamis 07:00-07:50; Jumat 13:30-15:30
-- Slot: Senin 10:00-12:00 (konsultasi), Selasa 10:00-12:00 (bimbingan)
(11, 6, 'Senin',  '10:00:00', '12:00:00', 'konsultasi', '[DUMMY] Konsultasi Keamanan Sistem & E-Bisnis',   1),
(12, 6, 'Selasa', '10:00:00', '12:00:00', 'bimbingan',  '[DUMMY] Bimbingan Proyek Akhir & Skripsi',        1);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- VERIFIKASI JUMLAH DATA
-- ============================================================
SELECT 'users'           AS tabel, COUNT(*) AS jumlah FROM users
UNION ALL SELECT 'dosen',         COUNT(*) FROM dosen
UNION ALL SELECT 'mahasiswa',     COUNT(*) FROM mahasiswa
UNION ALL SELECT 'kelas',         COUNT(*) FROM kelas
UNION ALL SELECT 'mata_kuliah',   COUNT(*) FROM mata_kuliah
UNION ALL SELECT 'jadwal_mengajar', COUNT(*) FROM jadwal_mengajar
UNION ALL SELECT 'slot_konsultasi', COUNT(*) FROM slot_konsultasi
UNION ALL SELECT 'status_ketersediaan', COUNT(*) FROM status_ketersediaan;
