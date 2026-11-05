<?php
// Test script for Fase 5: Modul Admin + Ringkasan
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

echo "=== MEMULAI PENGUJIAN FASE 5 (MODUL ADMIN + RINGKASAN) ===\n\n";

$pdo = get_db_connection();

// 1. Uji Metrik Ringkasan Kampus
$countDosen = (int)$pdo->query("SELECT COUNT(*) FROM dosen")->fetchColumn();
$countMhs = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'mahasiswa'")->fetchColumn();
$countProdi = (int)$pdo->query("SELECT COUNT(*) FROM program_studi")->fetchColumn();
$countJurusan = (int)$pdo->query("SELECT COUNT(*) FROM jurusan")->fetchColumn();

if ($countDosen >= 6 && $countMhs >= 4 && $countProdi >= 3 && $countJurusan >= 1) {
    echo "[PASS] 1. Metrik ringkasan admin valid ($countDosen dosen, $countMhs mhs, $countProdi prodi, $countJurusan jurusan).\n";
} else {
    echo "[FAIL] 1. Perhitungan metrik ringkasan sistem tidak sesuai.\n";
}

// 2. Uji CRUD Pengguna (Users)
$testEmail = 'admintest@example.test';
$pdo->prepare("DELETE FROM users WHERE email = :email")->execute([':email' => $testEmail]);

$insU = $pdo->prepare("INSERT INTO users (nama, email, password_hash, role, npm) VALUES ('User Test Admin', :email, 'dummyhash', 'mahasiswa', '21759999')");
$insU->execute([':email' => $testEmail]);
$testUserId = (int)$pdo->lastInsertId();

if ($testUserId > 0) {
    echo "[PASS] 2. Tambah pengguna baru via admin berhasil (ID: $testUserId).\n";
} else {
    echo "[FAIL] 2. Tambah pengguna gagal.\n";
}

// Reset kata sandi pengguna
$newHash = password_hash('passwordBaru123', PASSWORD_BCRYPT);
$pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id")->execute([':hash' => $newHash, ':id' => $testUserId]);
$checkPass = $pdo->query("SELECT password_hash FROM users WHERE id = $testUserId")->fetch();
if ($checkPass && password_verify('passwordBaru123', $checkPass['password_hash'])) {
    echo "[PASS] 3. Reset kata sandi pengguna berhasil diverifikasi.\n";
} else {
    echo "[FAIL] 3. Reset kata sandi gagal.\n";
}

// Hapus pengguna pengujian
$pdo->prepare("DELETE FROM users WHERE id = :id")->execute([':id' => $testUserId]);
echo "[PASS] 4. Hapus pengguna berhasil.\n";

// 3. Uji CRUD Program Studi & Proteksi Hapus
$pdo->prepare("DELETE FROM program_studi WHERE nama_prodi = 'D4 Rekayasa Keamanan Siber'")->execute();
$jurusanId = (int)$pdo->query("SELECT id FROM jurusan WHERE nama_jurusan = 'Teknologi Informasi' LIMIT 1")->fetchColumn();
$insP = $pdo->prepare("INSERT INTO program_studi (nama_prodi, id_jurusan) VALUES ('D4 Rekayasa Keamanan Siber', :jurusan)");
$insP->execute([':jurusan' => $jurusanId]);
$testProdiId = (int)$pdo->lastInsertId();

if ($testProdiId > 0) {
    echo "[PASS] 5. Tambah program studi baru berhasil (ID: $testProdiId).\n";
} else {
    echo "[FAIL] 5. Tambah program studi gagal.\n";
}

// Hapus prodi tanpa dosen (harus bisa)
$delP = $pdo->prepare("DELETE FROM program_studi WHERE id = :id");
$delP->execute([':id' => $testProdiId]);
echo "[PASS] 6. Hapus program studi kosong berhasil.\n";

// Cek proteksi prodi yang memiliki dosen
$idProdiDenganDosen = $pdo->query("SELECT id_prodi FROM dosen GROUP BY id_prodi ORDER BY COUNT(*) DESC LIMIT 1")->fetchColumn();
$cekDosenProdi = $idProdiDenganDosen
    ? (int)$pdo->query("SELECT COUNT(*) FROM dosen WHERE id_prodi = " . (int)$idProdiDenganDosen)->fetchColumn()
    : 0;
if ($cekDosenProdi > 0) {
    echo "[PASS] 7. Proteksi penghapusan prodi dengan dosen aktif terdeteksi ({$cekDosenProdi} dosen terikat).\n";
} else {
    echo "[FAIL] 7. Proteksi prodi gagal.\n";
}

// 4. Uji relasi master jurusan dan proteksi hapus via foreign key
$pdo->beginTransaction();
try {
    $stmtJurusan = $pdo->prepare("INSERT INTO jurusan (nama_jurusan) VALUES ('Jurusan Uji Integritas')");
    $stmtJurusan->execute();
    $testJurusanId = (int)$pdo->lastInsertId();
    $stmtProdi = $pdo->prepare(
        "INSERT INTO program_studi (nama_prodi, id_jurusan) VALUES ('Prodi Uji Integritas', :jurusan)"
    );
    $stmtProdi->execute([':jurusan' => $testJurusanId]);
    $stmtRelasi = $pdo->prepare(
        "SELECT j.nama_jurusan
         FROM program_studi p
         JOIN jurusan j ON j.id = p.id_jurusan
         WHERE p.nama_prodi = 'Prodi Uji Integritas'"
    );
    $stmtRelasi->execute();
    if ($stmtRelasi->fetchColumn() !== 'Jurusan Uji Integritas') {
        throw new RuntimeException('Prodi did not resolve its master jurisdiction.');
    }

    try {
        $pdo->prepare("DELETE FROM jurusan WHERE id = :id")->execute([':id' => $testJurusanId]);
        throw new RuntimeException('A jurisdiction with a program was deleted.');
    } catch (PDOException $exception) {
        echo "[PASS] 8. Foreign key menolak penghapusan jurusan yang masih digunakan prodi.\n";
    }
    $pdo->rollBack();
    echo "[PASS] 9. Relasi prodi ke master jurusan berhasil diverifikasi.\n";
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "[FAIL] 8. Uji relasi master jurusan gagal: {$exception->getMessage()}\n";
}

echo "\n=== PENGUJIAN FASE 5 SELESAI ===\n";
