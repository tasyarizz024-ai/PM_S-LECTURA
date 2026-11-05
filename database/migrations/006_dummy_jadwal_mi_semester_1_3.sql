-- Data jadwal contoh untuk Manajemen Informatika semester 1 dan 3.
-- Aman dijalankan ulang dan tidak mengubah jadwal kelas lain.
USE `s_lectura`;

START TRANSACTION;

UPDATE `kelas`
SET `nama_kelas` = REPLACE(`nama_kelas`, '[DUMMY] ', '')
WHERE `nama_kelas` IN (
    '[DUMMY] Manajemen Informatika A 1',
    '[DUMMY] Manajemen Informatika A 3'
);

UPDATE `mata_kuliah`
SET `nama` = REPLACE(`nama`, '[DUMMY] ', '')
WHERE `kode` IN ('DMI101', 'DMI102', 'DMI103', 'DMI301', 'DMI302', 'DMI303');

UPDATE `jadwal_mengajar`
SET `ruang` = NULL
WHERE `ruang` = '[DUMMY]'
  AND `id_mk` IN (SELECT `id` FROM `mata_kuliah` WHERE `kode` LIKE 'DMI%');

INSERT INTO `kelas` (`nama_kelas`, `id_prodi`, `semester`, `tahun_ajaran`)
SELECT 'Manajemen Informatika A 1', p.`id`, 1, '2026/2027 Ganjil'
FROM `program_studi` p
WHERE p.`nama_prodi` = 'Manajemen Informatika'
  AND p.`jurusan` = 'Teknologi Informasi'
  AND NOT EXISTS (
    SELECT 1 FROM `kelas` k
    WHERE k.`nama_kelas` = 'Manajemen Informatika A 1'
      AND k.`id_prodi` = p.`id` AND k.`semester` = 1
  );

INSERT INTO `kelas` (`nama_kelas`, `id_prodi`, `semester`, `tahun_ajaran`)
SELECT 'Manajemen Informatika A 3', p.`id`, 3, '2026/2027 Ganjil'
FROM `program_studi` p
WHERE p.`nama_prodi` = 'Manajemen Informatika'
  AND p.`jurusan` = 'Teknologi Informasi'
  AND NOT EXISTS (
    SELECT 1 FROM `kelas` k
    WHERE k.`nama_kelas` = 'Manajemen Informatika A 3'
      AND k.`id_prodi` = p.`id` AND k.`semester` = 3
  );

INSERT IGNORE INTO `mata_kuliah` (`kode`, `nama`) VALUES
('DMI101', 'Dasar Pemrograman'),
('DMI102', 'Sistem Komputer'),
('DMI103', 'Matematika Diskrit'),
('DMI301', 'Struktur Data'),
('DMI302', 'Basis Data Lanjut'),
('DMI303', 'Rekayasa Perangkat Lunak');

INSERT INTO `jadwal_mengajar`
    (`id_dosen`, `id_mk`, `id_kelas`, `hari`, `jam_mulai`, `jam_selesai`, `tipe_sesi`, `termin`, `sks`, `mode`, `ruang`)
SELECT d.`id`, mk.`id`, k.`id`, jadwal.`hari`, jadwal.`jam_mulai`, jadwal.`jam_selesai`,
       jadwal.`tipe_sesi`, '1', jadwal.`sks`, jadwal.`mode`, NULL
FROM (
    SELECT 'DMI101' AS `kode`, 'Manajemen Informatika A 1' AS `kelas`,
           'Senin' AS `hari`, '08:00:00' AS `jam_mulai`, '10:00:00' AS `jam_selesai`,
           'teori' AS `tipe_sesi`, 2 AS `sks`, 'daring' AS `mode`, 'dosen1@example.test' AS `email`
    UNION ALL SELECT 'DMI102', 'Manajemen Informatika A 1', 'Selasa', '09:00:00', '12:00:00', 'praktik', 2, 'luring', 'dosen2@example.test'
    UNION ALL SELECT 'DMI103', 'Manajemen Informatika A 1', 'Rabu', '10:00:00', '12:00:00', 'teori', 2, 'daring', 'dosen3@example.test'
    UNION ALL SELECT 'DMI101', 'Manajemen Informatika A 1', 'Jumat', '08:00:00', '10:00:00', 'praktik', 2, 'luring', 'dosen4@example.test'
    UNION ALL SELECT 'DMI301', 'Manajemen Informatika A 3', 'Senin', '10:00:00', '12:00:00', 'teori', 2, 'daring', 'dosen4@example.test'
    UNION ALL SELECT 'DMI302', 'Manajemen Informatika A 3', 'Selasa', '09:00:00', '12:00:00', 'praktik', 2, 'luring', 'dosen5@example.test'
    UNION ALL SELECT 'DMI303', 'Manajemen Informatika A 3', 'Rabu', '08:00:00', '10:00:00', 'teori', 2, 'daring', 'dosen6@example.test'
    UNION ALL SELECT 'DMI301', 'Manajemen Informatika A 3', 'Kamis', '13:00:00', '16:00:00', 'praktik', 2, 'luring', 'dosen1@example.test'
) jadwal
JOIN `mata_kuliah` mk ON mk.`kode` = jadwal.`kode`
JOIN `kelas` k ON k.`nama_kelas` = jadwal.`kelas`
JOIN `users` u ON u.`email` = jadwal.`email` AND u.`role` = 'dosen'
JOIN `dosen` d ON d.`user_id` = u.`id`
WHERE NOT EXISTS (
    SELECT 1
    FROM `jadwal_mengajar` jm
    WHERE jm.`id_mk` = mk.`id` AND jm.`id_kelas` = k.`id`
      AND jm.`hari` = jadwal.`hari`
      AND jm.`jam_mulai` = jadwal.`jam_mulai`
      AND jm.`jam_selesai` = jadwal.`jam_selesai`
);

COMMIT;
