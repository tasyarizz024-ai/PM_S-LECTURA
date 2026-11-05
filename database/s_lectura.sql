-- ==============================================================
-- S-LECTURA: Smart Lecturer Availability System
-- Politeknik Negeri Lampung (POLINELA)
-- Skema Basis Data & Data Awal Lengkap (Normalized v2)
-- Database: s_lectura
-- Charset: utf8mb4 / InnoDB
-- ==============================================================

CREATE DATABASE IF NOT EXISTS `s_lectura` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `s_lectura`;

SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------------
-- PEMBERSIHAN TABEL
-- --------------------------------------------------------------
DROP TABLE IF EXISTS `status_ketersediaan`;
DROP TABLE IF EXISTS `notifikasi`;
DROP TABLE IF EXISTS `permintaan_pertemuan`;
DROP TABLE IF EXISTS `admin_jurusan`;
DROP TABLE IF EXISTS `slot_konsultasi`;
DROP TABLE IF EXISTS `jadwal_mengajar`;
DROP TABLE IF EXISTS `mahasiswa`;
DROP TABLE IF EXISTS `dosen`;
DROP TABLE IF EXISTS `kelas`;
DROP TABLE IF EXISTS `mata_kuliah`;
DROP TABLE IF EXISTS `program_studi`;
DROP TABLE IF EXISTS `jurusan`;
DROP TABLE IF EXISTS `users`;

-- --------------------------------------------------------------
-- 1. Tabel: users
-- --------------------------------------------------------------
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('mahasiswa', 'dosen', 'admin', 'admin_jurusan') NOT NULL,
  `must_change_password` TINYINT(1) NOT NULL DEFAULT 1,
  `npm` VARCHAR(20) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- 2. Tabel: jurusan dan program_studi
-- --------------------------------------------------------------
CREATE TABLE `jurusan` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_jurusan` VARCHAR(100) NOT NULL UNIQUE,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `program_studi` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_prodi` VARCHAR(100) NOT NULL,
  `id_jurusan` INT NOT NULL,
  CONSTRAINT `fk_prodi_jurusan` FOREIGN KEY (`id_jurusan`)
    REFERENCES `jurusan` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `admin_jurusan` (
  `user_id` INT NOT NULL PRIMARY KEY,
  `id_jurusan` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_admin_jurusan_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_admin_jurusan_jurusan` FOREIGN KEY (`id_jurusan`)
    REFERENCES `jurusan` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notifikasi` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `pesan` VARCHAR(255) NOT NULL,
  `tautan` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `dibaca_at` DATETIME NULL,
  INDEX `idx_notifikasi_user` (`user_id`, `dibaca_at`, `created_at`),
  CONSTRAINT `fk_notifikasi_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- 3. Tabel: kelas
-- --------------------------------------------------------------
CREATE TABLE `kelas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_kelas` VARCHAR(100) NOT NULL,
  `id_prodi` INT NOT NULL,
  `semester` TINYINT NOT NULL,
  `tahun_ajaran` VARCHAR(30) NOT NULL,
  CONSTRAINT `fk_kelas_prodi` FOREIGN KEY (`id_prodi`) REFERENCES `program_studi` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- 4. Tabel: mata_kuliah
-- --------------------------------------------------------------
CREATE TABLE `mata_kuliah` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `kode` VARCHAR(20) NOT NULL UNIQUE,
  `nama` VARCHAR(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- 5. Tabel: dosen
-- --------------------------------------------------------------
CREATE TABLE `dosen` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `nidn_nip` VARCHAR(30) NULL UNIQUE,
  `id_prodi` INT NOT NULL,
  `foto` VARCHAR(255) NULL,
  `bidang_keahlian` VARCHAR(150) NOT NULL,
  CONSTRAINT `fk_dosen_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_dosen_prodi` FOREIGN KEY (`id_prodi`) REFERENCES `program_studi` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- 6. Tabel: mahasiswa
-- --------------------------------------------------------------
CREATE TABLE `mahasiswa` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `npm` VARCHAR(20) NOT NULL UNIQUE,
  `id_kelas` INT NULL,
  `id_prodi` INT NOT NULL,
  CONSTRAINT `fk_mhs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_mhs_kelas` FOREIGN KEY (`id_kelas`) REFERENCES `kelas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_mhs_prodi` FOREIGN KEY (`id_prodi`) REFERENCES `program_studi` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- 7. Tabel: jadwal_mengajar (satu dosen per jadwal, dengan termin)
-- --------------------------------------------------------------
CREATE TABLE `jadwal_mengajar` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_dosen` INT NOT NULL,
  `id_mk` INT NOT NULL,
  `id_kelas` INT NOT NULL,
  `hari` ENUM('Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat') NOT NULL,
  `jam_mulai` TIME NOT NULL,
  `jam_selesai` TIME NOT NULL,
  `tipe_sesi` ENUM('teori', 'praktik') NOT NULL DEFAULT 'teori',
  `termin` ENUM('1', '2') NOT NULL DEFAULT '1',
  `sks` TINYINT NOT NULL DEFAULT 1,
  `mode` ENUM('daring', 'luring') NOT NULL DEFAULT 'luring',
  `ruang` VARCHAR(100) NULL,
  CONSTRAINT `fk_jadwal_dosen` FOREIGN KEY (`id_dosen`) REFERENCES `dosen` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_jadwal_mk` FOREIGN KEY (`id_mk`) REFERENCES `mata_kuliah` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_jadwal_kelas` FOREIGN KEY (`id_kelas`) REFERENCES `kelas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- 9. Tabel: slot_konsultasi
-- --------------------------------------------------------------
CREATE TABLE `slot_konsultasi` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_dosen` INT NOT NULL,
  `hari` ENUM('Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat') NOT NULL,
  `jam_mulai` TIME NOT NULL,
  `jam_selesai` TIME NOT NULL,
  `jenis` ENUM('konsultasi', 'bimbingan') NOT NULL,
  `catatan` VARCHAR(255) NULL,
  `aktif` TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT `fk_slot_dosen` FOREIGN KEY (`id_dosen`) REFERENCES `dosen` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- 10. Tabel: status_ketersediaan
-- --------------------------------------------------------------
CREATE TABLE `status_ketersediaan` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_dosen` INT NOT NULL UNIQUE,
  `status` ENUM('Tersedia', 'Mengajar', 'Hanya Konsultasi', 'Hanya Bimbingan', 'Tidak Tersedia') NOT NULL DEFAULT 'Tersedia',
  `catatan` VARCHAR(255) NULL,
  `berlaku_sampai` DATETIME NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_status_dosen` FOREIGN KEY (`id_dosen`) REFERENCES `dosen` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- 11. Permintaan pertemuan mahasiswa dengan dosen
-- --------------------------------------------------------------
CREATE TABLE `permintaan_pertemuan` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_mahasiswa` INT NOT NULL,
  `id_dosen` INT NOT NULL,
  `jenis` ENUM('konsultasi', 'bimbingan', 'pribadi') NOT NULL,
  `tanggal` DATE NOT NULL,
  `jam_mulai` TIME NOT NULL,
  `jam_selesai` TIME NOT NULL,
  `alasan` VARCHAR(255) NULL,
  `semester` TINYINT NULL,
  `tahun_ajaran` VARCHAR(30) NULL,
  `status` ENUM('menunggu', 'disetujui', 'usul_jadwal', 'ditolak', 'dibatalkan', 'selesai') NOT NULL DEFAULT 'menunggu',
  `usul_tanggal` DATE NULL,
  `usul_jam_mulai` TIME NULL,
  `usul_jam_selesai` TIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_pertemuan_dosen_tanggal` (`id_dosen`, `tanggal`, `status`),
  INDEX `idx_pertemuan_mahasiswa_tanggal` (`id_mahasiswa`, `tanggal`),
  INDEX `idx_pertemuan_periode` (`tahun_ajaran`, `semester`, `id_dosen`),
  CONSTRAINT `fk_pertemuan_mahasiswa` FOREIGN KEY (`id_mahasiswa`)
    REFERENCES `mahasiswa` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pertemuan_dosen` FOREIGN KEY (`id_dosen`)
    REFERENCES `dosen` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ==============================================================
-- DATA AWAL (SEEDING LENGKAP)
-- ==============================================================

-- 1. Jurusan dan Program Studi
INSERT INTO `jurusan` (`id`, `nama_jurusan`) VALUES
(1, 'Teknologi Informasi'),
(2, 'Ekonomi dan Bisnis');

INSERT INTO `program_studi` (`id`, `nama_prodi`, `id_jurusan`) VALUES
(1, 'D4 Rekayasa Perangkat Lunak', 1),
(2, 'Manajemen Informatika', 1),
(3, 'D3 Akuntansi Terapan', 2);

-- 2. Kelas
INSERT INTO `kelas` (`id`, `nama_kelas`, `id_prodi`, `semester`, `tahun_ajaran`) VALUES
(1, 'Manajemen Informatika A 5', 2, 5, '2026/2027 Ganjil'),
(2, 'Manajemen Informatika A 1', 2, 1, '2026/2027 Ganjil'),
(3, 'Manajemen Informatika A 3', 2, 3, '2026/2027 Ganjil');

-- 3. Mata Kuliah
INSERT INTO `mata_kuliah` (`id`, `kode`, `nama`) VALUES
(1, 'PMI1516', 'Pemrograman Basis Data'),
(2, 'PKU1511', 'Proyek Mandiri'),
(3, 'PMI1517', 'Pemrograman Berbasis Mobile'),
(4, 'PMI1519', 'E-Bisnis'),
(5, 'PMI1518', 'Etika dan Hukum Teknologi Informasi'),
(6, 'PMI1511', 'Keamanan Sistem Informasi'),
(7, 'DMI101', 'Dasar Pemrograman'),
(8, 'DMI102', 'Sistem Komputer'),
(9, 'DMI103', 'Matematika Diskrit'),
(10, 'DMI301', 'Struktur Data'),
(11, 'DMI302', 'Basis Data Lanjut'),
(12, 'DMI303', 'Rekayasa Perangkat Lunak');

-- 4. Users (1 Admin + 10 Dosen + 34 Mahasiswa = 45 Akun)
-- Hash:
-- admin123: $2y$10$82aIXLlST8d/RmgbrdE/Hu6GnXdCKxIFy/xWsSSCbv6DYobFQnA/i
-- Sandi awal demo dosen dan mahasiswa: "password" (wajib diganti saat login pertama).
-- Hash berikut sudah cocok dengan password_verify('password', password_hash).
INSERT INTO `users` (`id`, `nama`, `email`, `password_hash`, `role`, `must_change_password`, `npm`) VALUES
-- 1 Admin
(1,  'Administrator Demo',                     'admin@example.test',           '$2y$10$82aIXLlST8d/RmgbrdE/Hu6GnXdCKxIFy/xWsSSCbv6DYobFQnA/i', 'admin',     0, NULL),
-- 6 Dosen
(2,  'Dosen Demo 1', 'dosen1@example.test',            '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dosen',     1, NULL),
(3,  'Dosen Demo 2',             'dosen2@example.test',          '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dosen',     1, NULL),
(4,  'Dosen Demo 3',      'dosen3@example.test',            '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dosen',     1, NULL),
(5,  'Dosen Demo 4',              'dosen4@example.test',          '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dosen',     1, NULL),
(6,  'Dosen Demo 5',              'dosen5@example.test',            '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dosen',     1, NULL),
(7,  'Dosen Demo 6',         'dosen6@example.test',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dosen',     1, NULL),
-- 34 Mahasiswa
(8,  'Mahasiswa Demo 01',                         'mahasiswa01@example.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, 'DEMO0001'),
(9,  'Mahasiswa Demo 02',                       'mahasiswa02@example.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, 'DEMO0002'),
(10, 'Mahasiswa Demo 03',                      'mahasiswa03@example.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, 'DEMO0003'),
(11, 'Mahasiswa Demo 04',                        'mahasiswa04@example.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, 'DEMO0004'),
(12, 'Mahasiswa Demo 05',                    'mahasiswa05@example.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, 'DEMO0005'),
(13, 'Mahasiswa Demo 06',                       'mahasiswa06@example.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, 'DEMO0006'),
(14, 'Mahasiswa Demo 07',                              'mahasiswa07@example.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, 'DEMO0007'),
(15, 'Mahasiswa Demo 08',                                'mahasiswa08@example.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, 'DEMO0008'),
(16, 'Mahasiswa Demo 09',                             'mahasiswa09@example.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, 'DEMO0009'),
(17, 'Mahasiswa Demo 10',                         'mahasiswa10@example.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, 'DEMO0010'),
(18, 'Mahasiswa Demo 11',                       'mahasiswa11@example.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, 'DEMO0011'),
(19, 'Mahasiswa Demo 12',                       'mahasiswa12@example.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, 'DEMO0012'),
(20, 'Mahasiswa Demo 13',                      'mahasiswa13@example.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, 'DEMO0013'),
(21, 'Mahasiswa Demo 14',                              'mahasiswa14@example.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, 'DEMO0014'),
(22, 'Mahasiswa Demo 15',                      'mahasiswa15@example.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, 'DEMO0015'),
(23, 'Mahasiswa Demo 16',                      'mahasiswa16@example.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, 'DEMO0016'),
(24, 'Mahasiswa Demo 17',                  'mahasiswa17@example.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, 'DEMO0017'),
(25, 'Mahasiswa Demo 18',                   'mahasiswa18@example.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, 'DEMO0018'),
(26, 'Mahasiswa Demo 19',                'mahasiswa19@example.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, 'DEMO0019'),
(27, 'Mahasiswa Demo 20',                   'mahasiswa20@example.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, 'DEMO0020'),
(28, 'Mahasiswa Demo 21',                  'mahasiswa21@example.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, 'DEMO0021'),
(29, 'Mahasiswa Demo 22',                         'mahasiswa22@example.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, 'DEMO0022'),
(30, 'Mahasiswa Demo 23',                      'mahasiswa23@example.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, 'DEMO0023'),
(31, 'Mahasiswa Demo 24',                            'mahasiswa24@example.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, 'DEMO0024'),
(32, 'Mahasiswa Demo 25',                       'mahasiswa25@example.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, 'DEMO0025'),
(33, 'Mahasiswa Demo 26',                       'mahasiswa26@example.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, 'DEMO0026'),
(34, 'Mahasiswa Demo 27',                            'mahasiswa27@example.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, 'DEMO0027'),
(35, 'Mahasiswa Demo 28',                             'mahasiswa28@example.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, 'DEMO0028'),
(36, 'Mahasiswa Demo 29',                         'mahasiswa29@example.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, 'DEMO0029'),
(37, 'Mahasiswa Demo 30',                       'mahasiswa30@example.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, 'DEMO0030'),
(38, 'Mahasiswa Demo 31',                   'mahasiswa31@example.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, 'DEMO0031'),
(39, 'Mahasiswa Demo 32',                             'mahasiswa32@example.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, 'DEMO0032'),
(40, 'Mahasiswa Demo 33',                         'mahasiswa33@example.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, 'DEMO0033'),
-- Seluruh identitas mahasiswa pada seed ini bersifat sintetis.
(41, 'Mahasiswa Demo 34',                        'mahasiswa34@example.test','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, 'DEMO0034');
-- 4 akun contoh dosen D4 Rekayasa Perangkat Lunak (TRPL)
INSERT INTO `users` (`id`, `nama`, `email`, `password_hash`, `role`, `must_change_password`, `npm`) VALUES
(42, 'Dosen TRPL 1', 'trpl1@example.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dosen', 1, NULL),
(43, 'Dosen TRPL 2', 'trpl2@example.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dosen', 1, NULL),
(44, 'Dosen TRPL 3', 'trpl3@example.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dosen', 1, NULL),
(45, 'Dosen TRPL 4', 'trpl4@example.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dosen', 1, NULL);

-- 5. Profil Dosen
INSERT INTO `dosen` (`id`, `user_id`, `nidn_nip`, `id_prodi`, `foto`, `bidang_keahlian`) VALUES
(1, 2, NULL,                 2, NULL, 'Pemrograman Basis Data, Teknologi Informasi, Rekayasa Perangkat Lunak'),
(2, 3, NULL,                 2, NULL, 'Rekayasa Perangkat Lunak, Pemrograman Mobile, Teknik Elektro'),
(3, 4, NULL,                 2, NULL, 'Sistem Informasi, Pemrograman Web, Manajemen Proyek'),
(4, 5, NULL, 2, NULL, 'Pemrograman Mobile, Keamanan Sistem Informasi, Basis Data'),
(5, 6, NULL, 2, NULL, 'E-Bisnis, Etika dan Hukum TI, Sistem Informasi'),
(6, 7, NULL,                 2, NULL, 'Keamanan Sistem Informasi, E-Bisnis, Pemrograman Web'),
(7, 42, NULL,                1, NULL, 'Rekayasa Perangkat Lunak, Analisis Sistem'),
(8, 43, NULL,                1, NULL, 'Pemrograman Web, Pengembangan Aplikasi'),
(9, 44, NULL,                1, NULL, 'Basis Data, Integrasi Sistem'),
(10, 45, NULL,               1, NULL, 'Pengujian Perangkat Lunak, DevOps');

-- 6. Mahasiswa (Relasi Kelas & Prodi)
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
(34, 41, 'DEMO0034', 1, 2);
-- 7. Jadwal Mengajar (13 baris kelas Manajemen Informatika A 5)
-- D1=Reza(1), D2=Dwirgo(2), D3=Dewi(3), D4=Akhmad(4), D5=Rima(5), D6=Kurniawan(6)
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

-- Jadwal contoh semester 1 dan 3 Manajemen Informatika.
INSERT INTO `jadwal_mengajar` (`id`, `id_dosen`, `id_mk`, `id_kelas`, `hari`, `jam_mulai`, `jam_selesai`, `tipe_sesi`, `termin`, `sks`, `mode`, `ruang`) VALUES
(14, 1,  7, 2, 'Senin',  '08:00:00', '10:00:00', 'teori',   '1', 2, 'daring', NULL),
(15, 2,  8, 2, 'Selasa', '09:00:00', '12:00:00', 'praktik', '1', 2, 'luring', NULL),
(16, 3,  9, 2, 'Rabu',   '10:00:00', '12:00:00', 'teori',   '1', 2, 'daring', NULL),
(17, 4,  7, 2, 'Jumat',  '08:00:00', '10:00:00', 'praktik', '1', 2, 'luring', NULL),
(18, 4, 10, 3, 'Senin',  '10:00:00', '12:00:00', 'teori',   '1', 2, 'daring', NULL),
(19, 5, 11, 3, 'Selasa', '09:00:00', '12:00:00', 'praktik', '1', 2, 'luring', NULL),
(20, 6, 12, 3, 'Rabu',   '08:00:00', '10:00:00', 'teori',   '1', 2, 'daring', NULL),
(21, 1, 10, 3, 'Kamis',  '13:00:00', '16:00:00', 'praktik', '1', 2, 'luring', NULL);

-- 9. Status Ketersediaan (Semua diawali "Tersedia")
INSERT INTO `status_ketersediaan` (`id`, `id_dosen`, `status`, `catatan`, `berlaku_sampai`) VALUES
(1, 1, 'Tersedia', '[DUMMY] Status awal sistem', NULL),
(2, 2, 'Tersedia', '[DUMMY] Status awal sistem', NULL),
(3, 3, 'Tersedia', '[DUMMY] Status awal sistem', NULL),
(4, 4, 'Tersedia', '[DUMMY] Status awal sistem', NULL),
(5, 5, 'Tersedia', '[DUMMY] Status awal sistem', NULL),
(6, 6, 'Tersedia', '[DUMMY] Status awal sistem', NULL);

-- 10. Slot Konsultasi & Bimbingan (Tidak bentrok dengan jadwal mengajar)
INSERT INTO `slot_konsultasi` (`id`, `id_dosen`, `hari`, `jam_mulai`, `jam_selesai`, `jenis`, `catatan`, `aktif`) VALUES
-- D1 (Reza)
(1,  1, 'Senin',  '09:00:00', '10:30:00', 'konsultasi', '[DUMMY] Konsultasi akademik & KRS semester aktif', 1),
(2,  1, 'Kamis',  '11:00:00', '12:30:00', 'bimbingan',  '[DUMMY] Bimbingan Tugas Akhir & Proyek Mandiri',  1),
-- D2 (Dwirgo)
(3,  2, 'Rabu',   '09:00:00', '11:00:00', 'konsultasi', '[DUMMY] Konsultasi materi & tugas kuliah',        1),
(4,  2, 'Kamis',  '09:00:00', '10:30:00', 'bimbingan',  '[DUMMY] Bimbingan PKU & Proyek Akhir',            1),
-- D3 (Dewi)
(5,  3, 'Rabu',   '10:00:00', '12:00:00', 'bimbingan',  '[DUMMY] Bimbingan Proyek Mandiri & laporan PKU',  1),
(6,  3, 'Kamis',  '13:00:00', '15:00:00', 'konsultasi', '[DUMMY] Konsultasi akademik & rencana studi',     1),
-- D4 (Akhmad)
(7,  4, 'Senin',  '10:00:00', '12:00:00', 'konsultasi', '[DUMMY] Konsultasi Pemrograman Mobile & Keamanan', 1),
(8,  4, 'Rabu',   '13:00:00', '15:00:00', 'bimbingan',  '[DUMMY] Bimbingan Proyek Akhir & Tugas Besar',    1),
-- D5 (Rima)
(9,  5, 'Selasa', '10:00:00', '12:00:00', 'konsultasi', '[DUMMY] Konsultasi E-Bisnis & Etika TI',          1),
(10, 5, 'Kamis',  '10:30:00', '12:00:00', 'bimbingan',  '[DUMMY] Bimbingan PKU & laporan proyek mandiri',  1),
-- D6 (Kurniawan)
(11, 6, 'Senin',  '10:00:00', '12:00:00', 'konsultasi', '[DUMMY] Konsultasi Keamanan Sistem & E-Bisnis',   1),
(12, 6, 'Selasa', '10:00:00', '12:00:00', 'bimbingan',  '[DUMMY] Bimbingan Proyek Akhir & Skripsi',        1);
