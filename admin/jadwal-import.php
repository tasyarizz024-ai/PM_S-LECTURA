<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/schedule_import.php';

require_role('admin_jurusan');

$pdo = get_db_connection();
$user = current_user();
$stmtScope = $pdo->prepare(
    "SELECT aj.id_jurusan, j.nama_jurusan
     FROM admin_jurusan aj
     JOIN jurusan j ON j.id = aj.id_jurusan
     WHERE aj.user_id = :user_id
     LIMIT 1"
);
$stmtScope->execute([':user_id' => $user['id']]);
$jurusanScope = $stmtScope->fetch();
if (!$jurusanScope) {
    set_flash('danger', 'Akun admin jurusan belum memiliki cakupan jurusan. Hubungi administrator.');
    redirect(BASE_URL . '/logout.php');
}
$jurusan = (int)$jurusanScope['id_jurusan'];

// Ambil daftar prodi yang bernaung di bawah jurusan admin ini
$stmtProdi = $pdo->prepare(
    "SELECT id, nama_prodi FROM program_studi
     WHERE id_jurusan = :jurusan
     ORDER BY nama_prodi"
);
$stmtProdi->execute([':jurusan' => $jurusan]);
$prodiList = $stmtProdi->fetchAll();
$validProdiIds = array_map(static fn($p) => (int)$p['id'], $prodiList);

// Tangkap prodi yang dipilih (POST atau GET)
$selectedProdi = (int)($_POST['prodi'] ?? $_GET['prodi'] ?? 0);
if (!in_array($selectedProdi, $validProdiIds, true)) {
    $selectedProdi = 0;
}
$selectedProgram = null;
if ($selectedProdi > 0) {
    foreach ($prodiList as $p) {
        if ((int)$p['id'] === $selectedProdi) {
            $selectedProgram = $p;
            break;
        }
    }
}

if (isset($_GET['template'])) {
    if ($selectedProdi <= 0) {
        set_flash('danger', 'Pilih program studi terlebih dahulu sebelum mengunduh template jadwal.');
        redirect(BASE_URL . '/admin/jadwal-import.php');
    }
    // Unduh template tanpa kolom prodi
    $template = schedule_xlsx_template(schedule_xlsx_headers(false));
    $cleanProdiName = trim((string)preg_replace('/[^a-zA-Z0-9_-]+/', '-', strtolower(trim((string)($selectedProgram['nama_prodi'] ?? 'prodi')))), '-');
    $filename = 'template-jadwal-' . ($cleanProdiName !== '' ? $cleanProdiName : 'prodi') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($template));
    echo $template;
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Sesi form tidak valid. Muat ulang halaman dan coba lagi.';
    } elseif ($selectedProdi <= 0) {
        $errors[] = 'Pilih program studi yang akan diisi jadwalnya.';
    } elseif (!isset($_FILES['file_excel']) || $_FILES['file_excel']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File Excel gagal diunggah.';
    } elseif ($_FILES['file_excel']['size'] > 5 * 1024 * 1024) {
        $errors[] = 'Ukuran file maksimal 5 MB.';
    } elseif (strtolower(pathinfo($_FILES['file_excel']['name'], PATHINFO_EXTENSION)) !== 'xlsx') {
        $errors[] = 'Gunakan file Excel .xlsx.';
    } else {
        try {
            // Baca baris dengan ekspektasi template tanpa kolom prodi
            $rows = schedule_xlsx_read($_FILES['file_excel']['tmp_name'], schedule_xlsx_headers(false));
            $result = validate_schedule_import($pdo, $rows, null, (int)$jurusan, $selectedProdi);
            $errors = $result['errors'];
            if (!$errors) {
                $pdo->beginTransaction();
                try {
                    insert_schedule_import($pdo, $result['rows']);
                    $pdo->commit();
                    set_flash('success', 'Berhasil mengimpor ' . count($result['rows']) . ' jadwal untuk program studi ' . ($selectedProgram['nama_prodi'] ?? '') . '.');
                    redirect(BASE_URL . '/admin/jadwal-import.php?prodi=' . $selectedProdi);
                } catch (PDOException $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('Schedule import failed for department admin ' . $user['id'] . ': ' . $exception->getMessage());
                    $errors[] = 'Import dibatalkan karena data berubah atau database menolak salah satu baris. Periksa kembali data dan coba lagi.';
                }
            }
        } catch (RuntimeException $exception) {
            $errors[] = $exception->getMessage();
        }
    }
}

$page_title = 'Import Jadwal Dosen';
$active_page = 'jadwal-dosen-jurusan';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <h3 class="fw-bold mb-1"><i class="bi bi-file-earmark-arrow-up me-2 text-success"></i>Import Jadwal Dosen</h3>
    <p class="text-secondary mb-0">Cakupan Jurusan: <strong><?= e($jurusanScope['nama_jurusan']) ?></strong>. Pilih program studi yang akan diisi jadwalnya sebelum mengunduh template atau mengimpor file.</p>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <strong>Import belum berhasil:</strong>
        <ul class="mb-0 mt-2"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<!-- Pilihan Program Studi -->
<div class="card card-custom border-0 shadow-sm mb-4">
    <div class="card-body">
        <h5 class="fw-bold mb-3"><i class="bi bi-diagram-3 me-2 text-success"></i>Pilih Program Studi</h5>
        <div class="row g-3">
            <?php foreach ($prodiList as $p): ?>
                <div class="col-sm-6 col-md-4">
                    <a class="card border <?= $selectedProdi === (int)$p['id'] ? 'border-success bg-light shadow-sm' : 'border-light' ?> h-100 text-decoration-none"
                       href="<?= BASE_URL ?>/admin/jadwal-import.php?prodi=<?= (int)$p['id'] ?>">
                        <div class="card-body d-flex align-items-center justify-content-between">
                            <div>
                                <div class="fw-bold text-dark"><?= e($p['nama_prodi']) ?></div>
                                <small class="<?= $selectedProdi === (int)$p['id'] ? 'text-success fw-semibold' : 'text-muted' ?>">
                                    <?= $selectedProdi === (int)$p['id'] ? '<i class="bi bi-check-circle-fill me-1"></i>Terpilih' : 'Klik untuk memilih' ?>
                                </small>
                            </div>
                            <i class="bi <?= $selectedProdi === (int)$p['id'] ? 'bi-check2-circle text-success fs-4' : 'bi-chevron-right text-muted' ?>"></i>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if ($selectedProdi > 0 && $selectedProgram): ?>
    <div class="card card-custom border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h5 class="fw-bold mb-1"><i class="bi bi-file-earmark-spreadsheet me-2 text-success"></i>Unggah Berkas Jadwal</h5>
                    <p class="text-muted small mb-0">Program Studi: <strong class="text-dark"><?= e($selectedProgram['nama_prodi']) ?></strong></p>
                </div>
                <a class="btn btn-outline-success btn-sm" href="<?= BASE_URL ?>/admin/jadwal-import.php?prodi=<?= $selectedProdi ?>&template=1">
                    <i class="bi bi-download me-1"></i>Unduh Template Excel
                </a>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="prodi" value="<?= $selectedProdi ?>">
                <div class="mb-3">
                    <label for="scheduleExcel" class="form-label fw-semibold">Pilih Berkas Excel (.xlsx, maks. 5 MB)</label>
                    <input id="scheduleExcel" class="form-control" type="file" name="file_excel" accept=".xlsx" required>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-polinela">
                        <i class="bi bi-upload me-1"></i>Validasi dan Import
                    </button>
                    <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/admin/mahasiswa.php">Kembali</a>
                </div>
            </form>
            <hr>
            <p class="small text-muted mb-0">
                Kolom template mencakup: <code>email_dosen</code>, <code>kode_mk</code>, <code>nama_mk</code>, <code>nama_kelas</code>, <code>semester</code>, <code>tahun_ajaran</code>, <code>termin</code> (1/2), <code>hari</code> (Senin–Jumat), <code>jam_mulai</code> & <code>jam_selesai</code> (<code>HH:MM</code>), <code>tipe_sesi</code> (<code>teori</code>/<code>praktik</code>), <code>sks</code>, <code>mode</code> (<code>daring</code>/<code>luring</code>), dan <code>ruang</code>.
            </p>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-warning border-0 shadow-sm">
        <i class="bi bi-exclamation-triangle me-2"></i>
        Silakan pilih <strong>Program Studi</strong> di atas untuk membuka formulir impor dan mengunduh template Excel.
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
