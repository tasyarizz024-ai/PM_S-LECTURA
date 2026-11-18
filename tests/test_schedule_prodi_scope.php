<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/schedule_import.php';
require_once __DIR__ . '/../includes/schedule_xlsx.php';

$pdo = get_db_connection();

// Cek jurusan TI
$jurusanId = (int)$pdo->query(
    "SELECT id FROM jurusan WHERE nama_jurusan = 'Teknologi Informasi' LIMIT 1"
)->fetchColumn();
if ($jurusanId <= 0) {
    fwrite(STDERR, "[FAIL] Jurusan Teknologi Informasi tidak ditemukan.\n");
    exit(1);
}

// Cek prodi MI
$prodiId = (int)$pdo->query(
    "SELECT id FROM program_studi WHERE nama_prodi = 'Manajemen Informatika' AND id_jurusan = {$jurusanId} LIMIT 1"
)->fetchColumn();
if ($prodiId <= 0) {
    fwrite(STDERR, "[FAIL] Prodi Manajemen Informatika tidak ditemukan.\n");
    exit(1);
}

echo "[INFO] Testing 14-column schedule import (tanpa kolom prodi, dengan prodiScope)...\n";

// 1. Template headers tanpa prodi
$headersNoProdi = schedule_xlsx_headers(false);
if (in_array('prodi', $headersNoProdi, true)) {
    fwrite(STDERR, "[FAIL] Kolom prodi masih ada pada headers(false).\n");
    exit(1);
}
if (count($headersNoProdi) !== 14) {
    fwrite(STDERR, "[FAIL] Jumlah kolom headers(false) harus 14, didapat: " . count($headersNoProdi) . "\n");
    exit(1);
}
echo "[PASS] 1. Headers tanpa prodi tepat 14 kolom.\n";

// 2. Buat user dosen dummy sementara untuk validasi jika perlu
$dummyDosenEmail = 'test.dosen.jadwal@polinela.ac.id';
$stmtUser = $pdo->prepare("SELECT id FROM users WHERE email = :email");
$stmtUser->execute([':email' => $dummyDosenEmail]);
$userId = (int)$stmtUser->fetchColumn();
if ($userId <= 0) {
    $pdo->prepare("INSERT INTO users (nama, email, password_hash, role) VALUES ('Dosen Test', :email, 'hash', 'dosen')")
        ->execute([':email' => $dummyDosenEmail]);
    $userId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO dosen (user_id, id_prodi, bidang_keahlian) VALUES (:uid, :pid, 'Testing')")
        ->execute([':uid' => $userId, ':pid' => $prodiId]);
    $dosenId = (int)$pdo->lastInsertId();
} else {
    $dosenId = (int)$pdo->query("SELECT id FROM dosen WHERE user_id = {$userId}")->fetchColumn();
}

// 3. Baris jadwal tanpa kolom prodi
$rowNoProdi = [
    '_row_number' => 2,
    'email_dosen' => $dummyDosenEmail,
    'kode_mk' => 'PMI999',
    'nama_mk' => 'Uji Coba Sistem',
    'nama_kelas' => 'MI Uji 1',
    // Perhatikan: TIDAK ADA KEY 'prodi'
    'semester' => '2',
    'tahun_ajaran' => '2026/2027 Ganjil',
    'termin' => '1',
    'hari' => 'Kamis',
    'jam_mulai' => '13:00',
    'jam_selesai' => '15:00',
    'tipe_sesi' => 'teori',
    'sks' => '2',
    'mode' => 'luring',
    'ruang' => 'Lab 1',
];

$res = validate_schedule_import($pdo, [$rowNoProdi], null, $jurusanId, $prodiId);
if (!empty($res['errors'])) {
    fwrite(STDERR, "[FAIL] Validasi jadwal tanpa prodi gagal: " . implode('; ', $res['errors']) . "\n");
    exit(1);
}
if (count($res['rows']) !== 1 || (int)$res['rows'][0]['id_prodi'] !== $prodiId) {
    fwrite(STDERR, "[FAIL] Baris valid tidak memiliki id_prodi yang tepat.\n");
    exit(1);
}
echo "[PASS] 2. validate_schedule_import sukses mengaitkan prodiScope tanpa kolom prodi di Excel.\n";

// 4. Test prodiScope di luar jurusan harus ditolak
$outsideJurusanProdi = (int)$pdo->query("SELECT id FROM program_studi WHERE id_jurusan != {$jurusanId} LIMIT 1")->fetchColumn();
if ($outsideJurusanProdi > 0) {
    $resDenied = validate_schedule_import($pdo, [$rowNoProdi], null, $jurusanId, $outsideJurusanProdi);
    if (empty($resDenied['errors'])) {
        fwrite(STDERR, "[FAIL] prodiScope di luar jurusan tidak ditolak.\n");
        exit(1);
    }
    echo "[PASS] 3. prodiScope di luar yurisdiksi jurusan berhasil ditolak.\n";
}

// 5. Cleanup dummy dosen
$pdo->exec("DELETE FROM dosen WHERE id = {$dosenId}");
$pdo->exec("DELETE FROM users WHERE id = {$userId}");

echo "\n[ALL TESTS PASSED] Fitur pilihan prodi dan template 14 kolom jadwal sukses diverifikasi!\n";

