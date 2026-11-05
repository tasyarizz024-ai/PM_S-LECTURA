-- Pindahkan kepemilikan jadwal dari tabel pivot ke jadwal_mengajar.
-- Untuk jadwal lama dengan lebih dari satu relasi, pemilik ditetapkan deterministik
-- ke id_dosen terkecil; jadwal dan data waktu tetap dipertahankan.
USE `s_lectura`;

ALTER TABLE `jadwal_mengajar`
    ADD COLUMN `id_dosen` INT NULL AFTER `id`,
    ADD COLUMN `termin` ENUM('1', '2') NOT NULL DEFAULT '1' AFTER `tipe_sesi`;

UPDATE `jadwal_mengajar` jm
JOIN (
    SELECT `id_jadwal`, MIN(`id_dosen`) AS `id_dosen`
    FROM `jadwal_dosen`
    GROUP BY `id_jadwal`
) pemilik ON pemilik.`id_jadwal` = jm.`id`
SET jm.`id_dosen` = pemilik.`id_dosen`;

ALTER TABLE `jadwal_mengajar`
    MODIFY COLUMN `id_dosen` INT NOT NULL,
    ADD CONSTRAINT `fk_jadwal_dosen` FOREIGN KEY (`id_dosen`)
        REFERENCES `dosen` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

DROP TABLE `jadwal_dosen`;
