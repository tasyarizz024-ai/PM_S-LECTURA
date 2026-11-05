<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/schedule_import.php';

$pdo = get_db_connection();
$lecturerId = (int)$pdo->query(
    "SELECT d.id
     FROM dosen d
     JOIN users u ON u.id = d.user_id
     WHERE u.email = 'dosen1@example.test'
     LIMIT 1"
)->fetchColumn();
if ($lecturerId <= 0) {
    fwrite(STDERR, "[FAIL] Demo lecturer is required for schedule import tests.\n");
    exit(1);
}

$row = [
    '_row_number' => 2,
    'email_dosen' => 'dosen1@example.test',
    'kode_mk' => 'DMI101',
    'nama_mk' => 'Dasar Pemrograman',
    'nama_kelas' => 'Kelas Uji Import',
    'prodi' => 'Manajemen Informatika',
    'semester' => '1',
    'tahun_ajaran' => '2026/2027 Ganjil',
    'termin' => '2',
    'hari' => 'Jumat',
    'jam_mulai' => '23:30',
    'jam_selesai' => '23:59',
    'tipe_sesi' => 'teori',
    'sks' => '2',
    'mode' => 'daring',
    'ruang' => '',
];
$jurusanId = (int)$pdo->query(
    "SELECT id FROM jurusan WHERE nama_jurusan = 'Teknologi Informasi' LIMIT 1"
)->fetchColumn();
if ($jurusanId <= 0) {
    fwrite(STDERR, "[FAIL] Teknologi Informasi jurusan is required for schedule import tests.\n");
    exit(1);
}

$valid = validate_schedule_import($pdo, [$row], $lecturerId, null);
if ($valid['errors'] || count($valid['rows']) !== 1) {
    fwrite(STDERR, "[FAIL] Valid lecturer import row was rejected: " . implode(' ', $valid['errors']) . "\n");
    exit(1);
}

$outsideLecturer = $row;
$outsideLecturer['email_dosen'] = 'dosen2@example.test';
$deniedPersonal = validate_schedule_import($pdo, [$outsideLecturer], $lecturerId, null);
if (!$deniedPersonal['errors'] || $deniedPersonal['rows']) {
    fwrite(STDERR, "[FAIL] Lecturer import accepted another lecturer's email.\n");
    exit(1);
}

$secondLecturer = $row;
$secondLecturer['_row_number'] = 3;
$secondLecturer['email_dosen'] = 'trpl1@example.test';
$secondLecturer['nama_kelas'] = 'Kelas Uji Import TRPL';
$departmentImport = validate_schedule_import($pdo, [$row, $secondLecturer], null, $jurusanId);
if ($departmentImport['errors'] || count($departmentImport['rows']) !== 2) {
    fwrite(STDERR, "[FAIL] Same-department multi-lecturer import failed: " . implode(' ', $departmentImport['errors']) . "\n");
    exit(1);
}

$sameClassSecondLecturer = $secondLecturer;
$sameClassSecondLecturer['nama_kelas'] = $row['nama_kelas'];
$duplicateClass = validate_schedule_import(
    $pdo,
    [$row, $sameClassSecondLecturer],
    null,
    $jurusanId
);
if (!$duplicateClass['errors']) {
    fwrite(STDERR, "[FAIL] Import accepted overlapping same-class schedules for two lecturers.\n");
    exit(1);
}

$existingClassConflict = $row;
$existingClassConflict['nama_kelas'] = 'Manajemen Informatika A 1';
$existingClassConflict['termin'] = '1';
$existingClassConflict['hari'] = 'Senin';
$existingClassConflict['jam_mulai'] = '08:00';
$existingClassConflict['jam_selesai'] = '10:00';
$existingClassRejected = validate_schedule_import(
    $pdo,
    [$existingClassConflict],
    null,
    $jurusanId
);
if (!$existingClassRejected['errors'] || $existingClassRejected['rows']) {
    fwrite(STDERR, "[FAIL] Import accepted an existing class-time overlap.\n");
    exit(1);
}

$outsideProgram = $row;
$outsideProgram['prodi'] = 'D3 Akuntansi Terapan';
$deniedDepartment = validate_schedule_import($pdo, [$outsideProgram], null, $jurusanId);
if (!$deniedDepartment['errors'] || $deniedDepartment['rows']) {
    fwrite(STDERR, "[FAIL] Department import accepted a program outside its scope.\n");
    exit(1);
}

$before = (int)$pdo->query("SELECT COUNT(*) FROM jadwal_mengajar")->fetchColumn();
$pdo->beginTransaction();
try {
    insert_schedule_import($pdo, $departmentImport['rows']);
    $during = (int)$pdo->query("SELECT COUNT(*) FROM jadwal_mengajar")->fetchColumn();
    if ($during !== $before + 2) {
        throw new RuntimeException('Expected two imported schedules inside the transaction.');
    }
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "[FAIL] Import transaction failed: " . $exception->getMessage() . "\n");
    exit(1);
}
$pdo->rollBack();
$after = (int)$pdo->query("SELECT COUNT(*) FROM jadwal_mengajar")->fetchColumn();
if ($after !== $before) {
    fwrite(STDERR, "[FAIL] Import rollback did not restore the original timetable.\n");
    exit(1);
}

echo "[PASS] Lecturer ownership, multi-lecturer department import, scope rejection, and transactional inserts.\n";
