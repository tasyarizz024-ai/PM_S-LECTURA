-- Tandai akun sebagai aktif/nonaktif tanpa menghapus profil dan riwayat pertemuan.
USE `s_lectura`;

ALTER TABLE `users`
    ADD COLUMN `aktif` TINYINT(1) NOT NULL DEFAULT 1 AFTER `role`;
