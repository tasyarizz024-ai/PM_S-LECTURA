-- S-LECTURA: Permintaan pertemuan mahasiswa dengan dosen
-- Jalankan setelah skema utama dan migrasi kelas/mahasiswa tersedia.
USE `s_lectura`;

CREATE TABLE IF NOT EXISTS `permintaan_pertemuan` (
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
