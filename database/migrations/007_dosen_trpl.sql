-- Akun dan profil dosen contoh untuk prodi D4 Rekayasa Perangkat Lunak (TRPL).
-- Aman dijalankan ulang; akun menggunakan sandi awal "123456" dan wajib menggantinya.
USE `s_lectura`;

START TRANSACTION;

INSERT INTO `users` (`nama`, `email`, `password_hash`, `role`, `must_change_password`)
SELECT contoh.`nama`, contoh.`email`,
       '$2y$10$AfDZrKP5VaISdFA0tH6rg..RTToDhnWKpbgsE72opZnqjz2KNiDXy',
       'dosen', 1
FROM (
    SELECT 'Dosen TRPL 1' AS `nama`, 'trpl1@example.test' AS `email`
    UNION ALL SELECT 'Dosen TRPL 2', 'trpl2@example.test'
    UNION ALL SELECT 'Dosen TRPL 3', 'trpl3@example.test'
    UNION ALL SELECT 'Dosen TRPL 4', 'trpl4@example.test'
) contoh
WHERE NOT EXISTS (
    SELECT 1 FROM `users` u WHERE u.`email` = contoh.`email`
);

INSERT INTO `dosen` (`user_id`, `nidn_nip`, `id_prodi`, `foto`, `bidang_keahlian`)
SELECT u.`id`, NULL, p.`id`, NULL, contoh.`bidang_keahlian`
FROM (
    SELECT 'trpl1@example.test' AS `email`, 'Rekayasa Perangkat Lunak, Analisis Sistem' AS `bidang_keahlian`
    UNION ALL SELECT 'trpl2@example.test', 'Pemrograman Web, Pengembangan Aplikasi'
    UNION ALL SELECT 'trpl3@example.test', 'Basis Data, Integrasi Sistem'
    UNION ALL SELECT 'trpl4@example.test', 'Pengujian Perangkat Lunak, DevOps'
) contoh
JOIN `users` u ON u.`email` = contoh.`email` AND u.`role` = 'dosen'
JOIN `program_studi` p
  ON p.`nama_prodi` = 'D4 Rekayasa Perangkat Lunak'
 AND p.`jurusan` = 'Teknologi Informasi'
WHERE NOT EXISTS (
    SELECT 1 FROM `dosen` d WHERE d.`user_id` = u.`id`
);

COMMIT;
