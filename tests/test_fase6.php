<?php
// Focused schema and business-rule checks for appointment requests.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/meetings.php';

$pdo = get_db_connection();
$pdo->query("SELECT 1 FROM permintaan_pertemuan LIMIT 1");

$student = $pdo->query(
    "SELECT m.id, k.semester, k.tahun_ajaran
     FROM mahasiswa m
     LEFT JOIN kelas k ON k.id = m.id_kelas
     ORDER BY m.id
     LIMIT 1"
)->fetch();
$slot = $pdo->query(
    "SELECT s.id_dosen, s.jenis, s.hari, s.jam_mulai, s.jam_selesai
     FROM slot_konsultasi s
     JOIN dosen d ON d.id = s.id_dosen
     WHERE s.aktif = 1
     ORDER BY s.id
     LIMIT 1"
)->fetch();

if (!$student || !$slot) {
    fwrite(STDERR, "[FAIL] Data mahasiswa atau slot aktif dibutuhkan untuk pengujian.\n");
    exit(1);
}

if (!meeting_is_valid_time_range('09:00', '09:30')
    || meeting_is_valid_time_range('09:30', '09:00')
    || meeting_is_valid_time_range('09:00', '09:00')
    || meeting_is_valid_time_range('25:00', '26:00')) {
    fwrite(STDERR, "[FAIL] Validasi rentang jam pengajuan mandiri gagal.\n");
    exit(1);
}
echo "[PASS] Validasi jam mulai dan selesai untuk waktu usulan mandiri.\n";

$sessionByType = $pdo->query(
    "SELECT jm.id_dosen, jm.hari, jm.jam_mulai, jm.jam_selesai, jm.tipe_sesi
     FROM jadwal_mengajar jm
     WHERE jm.tipe_sesi IN ('teori', 'praktik')
     ORDER BY jm.id"
)->fetchAll();
$practiceSession = null;
$theorySession = null;
foreach ($sessionByType as $session) {
    if ($session['tipe_sesi'] === 'praktik' && !$practiceSession) {
        $practiceSession = $session;
    }
    if ($session['tipe_sesi'] === 'teori' && !$theorySession) {
        $theorySession = $session;
    }
}
if (!$practiceSession || !$theorySession) {
    fwrite(STDERR, "[FAIL] Data jadwal teori dan praktik dibutuhkan untuk menguji kebijakan bentrok.\n");
    exit(1);
}
$dateForDay = static function (string $weekday): string {
    $date = new DateTimeImmutable(meeting_min_date(), new DateTimeZone('Asia/Jakarta'));
    for ($offset = 0; $offset < 7; $offset++) {
        if (meeting_weekday($date->format('Y-m-d')) === $weekday) {
            return $date->format('Y-m-d');
        }
        $date = $date->modify('+1 day');
    }
    throw new RuntimeException('Tidak dapat menentukan tanggal untuk hari jadwal.');
};
$practiceDate = $dateForDay($practiceSession['hari']);
$theoryDate = $dateForDay($theorySession['hari']);
if (!meeting_has_practical_conflict(
    $pdo,
    (int)$practiceSession['id_dosen'],
    $practiceDate,
    $practiceSession['jam_mulai'],
    $practiceSession['jam_selesai']
)) {
    fwrite(STDERR, "[FAIL] Bentrok jadwal praktik tidak terdeteksi.\n");
    exit(1);
}
if (meeting_has_conflict(
    $pdo,
    (int)$theorySession['id_dosen'],
    $theoryDate,
    $theorySession['jam_mulai'],
    $theorySession['jam_selesai']
)) {
    fwrite(STDERR, "[FAIL] Jadwal teori seharusnya menjadi peringatan, bukan bentrok yang memblokir.\n");
    exit(1);
}
if (!meeting_has_theory_overlap(
    $pdo,
    (int)$theorySession['id_dosen'],
    $theoryDate,
    $theorySession['jam_mulai'],
    $theorySession['jam_selesai']
)) {
    fwrite(STDERR, "[FAIL] Peringatan jadwal teori tidak terdeteksi.\n");
    exit(1);
}
echo "[PASS] Jadwal praktik memblokir pengajuan; jadwal teori hanya menghasilkan peringatan.\n";

$timezone = new DateTimeZone('Asia/Jakarta');
$meetingDate = new DateTimeImmutable(meeting_min_date(), $timezone);
for ($dayOffset = 0; $dayOffset < 7 && meeting_weekday($meetingDate->format('Y-m-d')) !== $slot['hari']; $dayOffset++) {
    $meetingDate = $meetingDate->modify('+1 day');
}
$meetingDateString = $meetingDate->format('Y-m-d');

if (meeting_weekday($meetingDateString) !== $slot['hari'] || !meeting_is_future($meetingDateString, substr($slot['jam_mulai'], 0, 5))) {
    fwrite(STDERR, "[FAIL] Validasi hari/tanggal slot gagal.\n");
    exit(1);
}
echo "[PASS] Validasi hari slot dan waktu mendatang.\n";

$pdo->beginTransaction();
try {
    $stmtInsert = $pdo->prepare(
        "INSERT INTO permintaan_pertemuan
            (id_mahasiswa, id_dosen, jenis, tanggal, jam_mulai, jam_selesai, alasan, semester, tahun_ajaran, status)
         VALUES (:mahasiswa, :dosen, 'pribadi', :tanggal, '23:45:00', '23:59:00', NULL, :semester, :tahun_ajaran, 'disetujui')"
    );
    $stmtInsert->execute([
        ':mahasiswa' => $student['id'],
        ':dosen' => $slot['id_dosen'],
        ':tanggal' => $meetingDateString,
        ':semester' => $student['semester'],
        ':tahun_ajaran' => $student['tahun_ajaran'],
    ]);
    $meetingId = (int)$pdo->lastInsertId();

    $stmtSaved = $pdo->prepare("SELECT * FROM permintaan_pertemuan WHERE id = :id");
    $stmtSaved->execute([':id' => $meetingId]);
    $saved = $stmtSaved->fetch();
    if (!$saved || $saved['jenis'] !== 'pribadi' || $saved['alasan'] !== null
        || (int)$saved['semester'] !== (int)$student['semester']
        || $saved['tahun_ajaran'] !== $student['tahun_ajaran']) {
        throw new RuntimeException('Simpan kategori pribadi atau snapshot periode tidak sesuai.');
    }
    echo "[PASS] Data pertemuan pribadi menyimpan snapshot periode tanpa catatan.\n";

    if (!meeting_has_conflict($pdo, (int)$slot['id_dosen'], $meetingDateString, '23:45:00', '23:59:00', null)) {
        throw new RuntimeException('Bentrok dengan pertemuan disetujui tidak terdeteksi.');
    }
    echo "[PASS] Deteksi bentrok dengan pertemuan dosen yang disetujui.\n";

    $stmtSummary = $pdo->prepare(
        "SELECT COUNT(*) AS permintaan,
                SUM(CASE WHEN jenis = 'pribadi' AND status = 'selesai' THEN 1 ELSE 0 END) AS pribadi_selesai
         FROM permintaan_pertemuan
         WHERE id_dosen = :dosen AND semester = :semester AND tahun_ajaran = :tahun_ajaran"
    );
    $stmtSummary->execute([
        ':dosen' => $slot['id_dosen'],
        ':semester' => $student['semester'],
        ':tahun_ajaran' => $student['tahun_ajaran'],
    ]);
    $summary = $stmtSummary->fetch();
    if (!$summary || (int)$summary['permintaan'] < 1 || (int)$summary['pribadi_selesai'] !== 0) {
        throw new RuntimeException('Rekap agregat status pertemuan tidak sesuai.');
    }
    echo "[PASS] Query agregat menghitung permintaan, bukan menganggap janji sebagai pertemuan terlaksana.\n";
} finally {
    $pdo->rollBack();
}

echo "=== PENGUJIAN FASE 6 SELESAI ===\n";
