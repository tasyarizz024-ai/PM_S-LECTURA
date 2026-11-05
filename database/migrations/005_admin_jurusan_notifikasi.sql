-- S-LECTURA: Admin jurusan dan notifikasi dalam aplikasi.
-- Jalankan setelah migrasi 004.
USE `s_lectura`;

ALTER TABLE `users`
  MODIFY `role` ENUM('mahasiswa', 'dosen', 'admin', 'admin_jurusan') NOT NULL;

CREATE TABLE IF NOT EXISTS `admin_jurusan` (
  `user_id` INT NOT NULL PRIMARY KEY,
  `id_prodi_acuan` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_admin_jurusan_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_admin_jurusan_prodi` FOREIGN KEY (`id_prodi_acuan`)
    REFERENCES `program_studi` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifikasi` (
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
