<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/schedule_import.php';

$pdo = get_db_connection();

$email = 'uji.bentrok@polinela.ac.id';
$pdo->prepare("DELETE FROM users WHERE email = :e")->execute([':e' => $email]);
$pdo->prepare("INSERT INTO users (nama, email, password_hash, role) VALUES ('Kurniawan Saputra', :e, 'hash', 'dosen')")
    ->execute([':e' => $email]);
$uid = (int)$pdo->lastInsertId();
$prodiId = (int)$pdo->query("SELECT id FROM program_studi LIMIT 1")->fetchColumn();
$pdo->prepare("INSERT INTO dosen (user_id, id_prodi, bidang_keahlian) VALUES (:uid, :pid, 'Keahlian')")
    ->execute([':uid' => $uid, ':pid' => $prodiId]);

// Dua baris yang memiliki waktu yang sama persis (misal kelas paralel atau daring)
$rows = [
    [
        '_row_number' => 9,
        'nama_dosen' => 'Kurniawan Saputra',
        'nama_mk' => 'Kewarganegaraan',
        'nama_kelas' => '1A/1B/1C/1D',
        'semester' => '1',
        'tahun_ajaran' => '2026',
        'hari' => 'Jumat',
        'jam_mulai' => '13:30',
        'jam_selesai' => '15:10',
        'tipe_sesi' => 'Teori',
        'mode' => 'Luring',
        'ruang' => 'CB1',
    ],
    [
        '_row_number' => 11,
        'nama_dosen' => 'Kurniawan Saputra',
        'nama_mk' => 'Pendidikan Pancasila',
        'nama_kelas' => '1A/1B/1C/1D',
        'semester' => '1',
        'tahun_ajaran' => '2026',
        'hari' => 'Jumat',
        'jam_mulai' => '13:30', // waktu sama / beririsan
        'jam_selesai' => '15:10',
        'tipe_sesi' => 'Teori',
        'mode' => 'Daring',
        'ruang' => '',
    ],
];

$res = validate_schedule_import($pdo, $rows, null, null, $prodiId);
if (!empty($res['errors'])) {
    echo "[FAIL] Validasi jadwal beririsan ditolak: " . implode('; ', $res['errors']) . "\n";
    exit(1);
}

echo "[PASS] Jadwal dengan waktu sama/beririsan berhasil lolos validasi tanpa error bentrok!\n";

// Cleanup
$pdo->prepare("DELETE FROM users WHERE email = :e")->execute([':e' => $email]);
