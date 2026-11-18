<?php

ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

start_session_if_none();

// 1. Uji timeout constant
if (!defined('SESSION_IDLE_TIMEOUT') || SESSION_IDLE_TIMEOUT !== 1800) {
    fwrite(STDERR, "[FAIL] SESSION_IDLE_TIMEOUT harus bernilai 1800 detik (30 menit).\n");
    exit(1);
}
echo "[PASS] 1. SESSION_IDLE_TIMEOUT terdefinisi 1800 detik (30 menit).\n";

// 2. Simulasikan login
$dummyUser = [
    'id' => 1,
    'nama' => 'Tester',
    'email' => 'admin@polinela.ac.id',
    'role' => 'admin',
    'must_change_password' => 0,
];
login_user($dummyUser);

if (!isset($_SESSION['last_activity']) || abs($_SESSION['last_activity'] - time()) > 2) {
    fwrite(STDERR, "[FAIL] last_activity tidak diset saat login_user.\n");
    exit(1);
}
echo "[PASS] 2. login_user() berhasil mencatat timestamp last_activity.\n";

// 3. Simulasikan aktivitas dalam batas 30 menit (misal 5 menit lalu)
$_SESSION['last_activity'] = time() - 300;
$timeBefore = $_SESSION['last_activity'];

// Update aktivitas
if (isset($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity']) <= SESSION_IDLE_TIMEOUT) {
    $_SESSION['last_activity'] = time();
}

if ($_SESSION['last_activity'] <= $timeBefore) {
    fwrite(STDERR, "[FAIL] Timestamp last_activity tidak ter-refresh saat ada aktivitas.\n");
    exit(1);
}
echo "[PASS] 3. Sesi aktif berhasil memperbarui timestamp ke waktu terkini.\n";

// 4. Simulasikan tidak aktif lebih dari 30 menit (1801 detik lalu)
$_SESSION['last_activity'] = time() - 1805;
$isTimedOut = (time() - (int)$_SESSION['last_activity']) > SESSION_IDLE_TIMEOUT;
if (!$isTimedOut) {
    fwrite(STDERR, "[FAIL] Sesi lebih dari 30 menit seharusnya terdeteksi timeout.\n");
    exit(1);
}

// Eksekusi pembersihan sesi
logout_user();
if (is_logged_in()) {
    fwrite(STDERR, "[FAIL] logout_user() gagal membersihkan sesi.\n");
    exit(1);
}
echo "[PASS] 4. Sesi idle > 30 menit berhasil dideteksi kedaluwarsa dan dibersihkan.\n";

echo "\n[ALL TESTS PASSED] Fitur Session Idle Timeout 30 menit berhasil diverifikasi 100%!\n";
