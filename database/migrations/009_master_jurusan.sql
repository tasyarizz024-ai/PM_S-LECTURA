-- Normalisasi jurusan menjadi master data terpisah dari program studi.
USE `s_lectura`;

CREATE TABLE `jurusan` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_jurusan` VARCHAR(100) NOT NULL UNIQUE,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `jurusan` (`nama_jurusan`)
SELECT DISTINCT COALESCE(NULLIF(TRIM(`jurusan`), ''), 'Belum ditentukan')
FROM `program_studi`;

ALTER TABLE `program_studi`
    ADD COLUMN `id_jurusan` INT NULL AFTER `id`;

UPDATE `program_studi` p
JOIN `jurusan` j
  ON j.`nama_jurusan` = COALESCE(NULLIF(TRIM(p.`jurusan`), ''), 'Belum ditentukan')
SET p.`id_jurusan` = j.`id`;

ALTER TABLE `program_studi`
    MODIFY COLUMN `id_jurusan` INT NOT NULL,
    ADD CONSTRAINT `fk_prodi_jurusan` FOREIGN KEY (`id_jurusan`)
        REFERENCES `jurusan` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    DROP COLUMN `jurusan`;

ALTER TABLE `admin_jurusan`
    ADD COLUMN `id_jurusan` INT NULL AFTER `user_id`;

UPDATE `admin_jurusan` aj
JOIN `program_studi` p ON p.`id` = aj.`id_prodi_acuan`
SET aj.`id_jurusan` = p.`id_jurusan`;

ALTER TABLE `admin_jurusan`
    MODIFY COLUMN `id_jurusan` INT NOT NULL,
    DROP FOREIGN KEY `fk_admin_jurusan_prodi`,
    ADD CONSTRAINT `fk_admin_jurusan_jurusan` FOREIGN KEY (`id_jurusan`)
        REFERENCES `jurusan` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    DROP COLUMN `id_prodi_acuan`;
