<?php

require_once __DIR__ . '/../includes/user_import.php';

function assertUserImportPolicy(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
}

assertUserImportPolicy(
    user_xlsx_headers('mahasiswa') === ['nama', 'email', 'npm'],
    'Student template headers must not request birth date.'
);
assertUserImportPolicy(
    user_xlsx_headers('dosen') === ['nama', 'email', 'nidn_nip'],
    'Lecturer template must only request account identity fields.'
);
assertUserImportPolicy(initial_user_password() === '123456', 'Initial password must be 123456.');

$hash = password_hash(initial_user_password(), PASSWORD_DEFAULT);
assertUserImportPolicy(password_verify('123456', $hash), 'Initial password hash must verify.');

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE program_studi (id INTEGER PRIMARY KEY)');
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, nama TEXT, email TEXT, password_hash TEXT, role TEXT, npm TEXT, must_change_password INTEGER)');
$pdo->exec('CREATE TABLE mahasiswa (user_id INTEGER, npm TEXT, id_kelas INTEGER, id_prodi INTEGER)');
$pdo->exec('CREATE TABLE dosen (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, nidn_nip TEXT, id_prodi INTEGER, bidang_keahlian TEXT)');
$pdo->exec('CREATE TABLE status_ketersediaan (id_dosen INTEGER, status TEXT, catatan TEXT)');
$pdo->exec('INSERT INTO program_studi (id) VALUES (1)');

$lecturerImport = validate_user_import(
    $pdo,
    [['nama' => 'Dosen Uji', 'email' => 'dosen@example.test', 'nidn_nip' => '12345']],
    1,
    'dosen'
);
assertUserImportPolicy(!$lecturerImport['errors'], 'Lecturer account must import without expertise field.');
assertUserImportPolicy($lecturerImport['rows'][0]['bidang_keahlian'] === '', 'New lecturer expertise must default to an empty string.');
insert_user_import($pdo, $lecturerImport['rows'], 1);
$savedExpertise = $pdo->query('SELECT bidang_keahlian FROM dosen WHERE user_id = 1')->fetchColumn();
assertUserImportPolicy($savedExpertise === '', 'New lecturer must be saved with empty expertise.');

$seedSql = file_get_contents(__DIR__ . '/../database/s_lectura.sql');
assertUserImportPolicy(is_string($seedSql), 'Could not read the initial database seed.');
assertUserImportPolicy(
    preg_match("/admin@example\\.test'\\s*,\\s*'(\\$2y\\$10\\$[^']+)'/", $seedSql, $seedMatch) === 1
        && password_verify('123456', $seedMatch[1]),
    'Root administrator seed password must be 123456.'
);

foreach (['mahasiswa', 'dosen'] as $role) {
    $template = schedule_xlsx_template(user_xlsx_headers($role), ucfirst($role));
    $zipPath = tempnam(sys_get_temp_dir(), 'slectura-user-template-');
    if ($zipPath === false || file_put_contents($zipPath, $template) === false) {
        fwrite(STDERR, "[FAIL] Could not stage {$role} template for validation.\n");
        exit(1);
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        unlink($zipPath);
        fwrite(STDERR, "[FAIL] Could not open generated {$role} template.\n");
        exit(1);
    }
    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    unlink($zipPath);

    assertUserImportPolicy(is_string($sheet), "Could not read {$role} template header row.");
    assertUserImportPolicy(
        !str_contains($sheet, 'tanggal_lahir') && !str_contains($sheet, 'bidang_keahlian'),
        "{$role} template contains a field that should not be requested during account creation."
    );
}

echo "[PASS] User templates omit birth date and new-account password is 123456.\n";
