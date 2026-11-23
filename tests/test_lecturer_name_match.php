<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/schedule_import.php';

$pdo = get_db_connection();

// Buat akun dosen uji coba
$email = 'uji.dosen.nama@polinela.ac.id';
$pdo->prepare("DELETE FROM users WHERE email = :e")->execute([':e' => $email]);
$pdo->prepare("INSERT INTO users (nama, email, password_hash, role) VALUES ('Kurniawan Saputra', :e, 'hash', 'dosen')")
    ->execute([':e' => $email]);
$uid = (int)$pdo->lastInsertId();
$prodiId = (int)$pdo->query("SELECT id FROM program_studi LIMIT 1")->fetchColumn();
$pdo->prepare("INSERT INTO dosen (user_id, id_prodi, bidang_keahlian) VALUES (:uid, :pid, 'Keahlian')")
    ->execute([':uid' => $uid, ':pid' => $prodiId]);

// Uji pencarian dengan gelar di Excel: "Kurniawan Saputra, M.Kom."
$found = find_lecturer_by_identifier($pdo, 'Kurniawan Saputra, M.Kom.');
if (!$found || $found['nama'] !== 'Kurniawan Saputra') {
    echo "[FAIL] Pencarian dosen dengan gelar gagal\n";
    exit(1);
}

// Uji baris dengan nama dosen bergelar, Tipe Sesi kapital "Praktik", dan Mode kapital "Luring"
$row = [
    '_row_number' => 2,
    'nama_dosen' => 'Kurniawan Saputra, M.Kom.',
    'nama_mk' => 'E-Bisnis',
    'nama_kelas' => '5C',
    'semester' => '5',
    'tahun_ajaran' => '2026',
    'hari' => 'Senin',
    'jam_mulai' => '09:30',
    'jam_selesai' => '11:30',
    'tipe_sesi' => 'Praktik', // Kapital
    'mode' => 'Luring',       // Kapital
    'ruang' => 'SFS 2.4',
];

$res = validate_schedule_import($pdo, [$row], null, null, $prodiId);
if (!empty($res['errors'])) {
    echo "[FAIL] Validasi gagal: " . implode('; ', $res['errors']) . "\n";
    exit(1);
}

echo "[PASS] Impor jadwal dengan nama dosen bergelar dan kapitalisasi berhasil!\n";

// Cleanup
$pdo->prepare("DELETE FROM users WHERE email = :e")->execute([':e' => $email]);
