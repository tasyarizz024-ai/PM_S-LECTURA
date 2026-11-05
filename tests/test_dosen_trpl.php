<?php
require_once __DIR__ . '/../config/database.php';

$pdo = get_db_connection();
$stmt = $pdo->prepare(
    "SELECT u.email, u.password_hash, u.must_change_password,
            d.bidang_keahlian, p.nama_prodi
     FROM users u
     JOIN dosen d ON d.user_id = u.id
     JOIN program_studi p ON p.id = d.id_prodi
     WHERE u.email IN (
        'trpl1@example.test', 'trpl2@example.test',
        'trpl3@example.test', 'trpl4@example.test'
     )
     ORDER BY u.email"
);
$stmt->execute();
$lecturers = $stmt->fetchAll();

if (count($lecturers) !== 4) {
    fwrite(STDERR, "[FAIL] Harus ada empat profil dosen contoh TRPL.\n");
    exit(1);
}

foreach ($lecturers as $lecturer) {
    if ($lecturer['nama_prodi'] !== 'D4 Rekayasa Perangkat Lunak'
        || $lecturer['bidang_keahlian'] === ''
        || (int)$lecturer['must_change_password'] !== 1
        || !password_verify('password', $lecturer['password_hash'])) {
        fwrite(STDERR, "[FAIL] Profil atau kredensial {$lecturer['email']} tidak sesuai.\n");
        exit(1);
    }
}

echo "[PASS] Empat akun dosen contoh terhubung ke D4 Rekayasa Perangkat Lunak.\n";
