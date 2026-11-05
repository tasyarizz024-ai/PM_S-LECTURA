<?php
require_once __DIR__ . '/../config/database.php';

$pdo = get_db_connection();
$stmt = $pdo->prepare(
    "SELECT k.semester, COUNT(jm.id) AS jumlah_jadwal,
            SUM(mk.kode LIKE 'DMI%') AS jadwal_prodi
     FROM kelas k
     JOIN program_studi p ON p.id = k.id_prodi
     JOIN jurusan j ON j.id = p.id_jurusan
     LEFT JOIN jadwal_mengajar jm ON jm.id_kelas = k.id
     LEFT JOIN mata_kuliah mk ON mk.id = jm.id_mk
     WHERE p.nama_prodi = 'Manajemen Informatika'
       AND j.nama_jurusan = 'Teknologi Informasi'
       AND k.semester IN (1, 3)
       AND k.nama_kelas = CONCAT('Manajemen Informatika A ', k.semester)
     GROUP BY k.semester
     ORDER BY k.semester"
);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_UNIQUE);

foreach ([1, 3] as $semester) {
    $row = $rows[$semester] ?? null;
    if (!$row || (int)$row['jumlah_jadwal'] === 0 || (int)$row['jadwal_prodi'] !== (int)$row['jumlah_jadwal']) {
        fwrite(STDERR, "[FAIL] Jadwal contoh semester {$semester} tidak lengkap.\n");
        exit(1);
    }
}

echo "[PASS] Jadwal contoh tersedia untuk semester 1 dan 3 Manajemen Informatika.\n";
