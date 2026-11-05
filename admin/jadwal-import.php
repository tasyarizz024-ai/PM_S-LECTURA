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

if (isset($_GET['template'])) {
    $template = schedule_xlsx_template();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="template-jadwal-jurusan.xlsx"');
    header('Content-Length: ' . strlen($template));
    echo $template;
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Sesi form tidak valid. Muat ulang halaman dan coba lagi.';
    } elseif (!isset($_FILES['file_excel']) || $_FILES['file_excel']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File Excel gagal diunggah.';
    } elseif ($_FILES['file_excel']['size'] > 5 * 1024 * 1024) {
        $errors[] = 'Ukuran file maksimal 5 MB.';
    } elseif (strtolower(pathinfo($_FILES['file_excel']['name'], PATHINFO_EXTENSION)) !== 'xlsx') {
        $errors[] = 'Gunakan file Excel .xlsx.';
    } else {
        try {
            $rows = schedule_xlsx_read($_FILES['file_excel']['tmp_name']);
            $result = validate_schedule_import($pdo, $rows, null, (int)$jurusan);
            $errors = $result['errors'];
            if (!$errors) {
                $pdo->beginTransaction();
                try {
                    insert_schedule_import($pdo, $result['rows']);
                    $pdo->commit();
                    set_flash('success', 'Berhasil mengimpor ' . count($result['rows']) . ' jadwal.');
                    redirect(BASE_URL . '/admin/jadwal-import.php');
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
    <p class="text-secondary mb-0">Cakupan: <?= e($jurusanScope['nama_jurusan']) ?>. Satu file dapat memuat beberapa dosen, tetapi semua dosen dan program studi harus berada di jurusan Anda.</p>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <strong>Import belum dilakukan:</strong>
        <ul class="mb-0 mt-2"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="card card-custom border-0 shadow-sm">
    <div class="card-body">
        <h5 class="fw-bold">Format file Excel</h5>
        <p>Gunakan template dan isi satu baris untuk setiap jadwal. Admin dapat mencantumkan beberapa dosen dalam satu file dengan mengisi email dosen pada setiap baris.</p>
        <a class="btn btn-outline-success mb-3" href="<?= BASE_URL ?>/admin/jadwal-import.php?template=1">
            <i class="bi bi-download me-1"></i>Unduh Template Excel
        </a>
        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <label for="scheduleExcel" class="form-label fw-semibold">Pilih file .xlsx (maks. 5 MB)</label>
            <input id="scheduleExcel" class="form-control mb-3" type="file" name="file_excel" accept=".xlsx" required>
            <button type="submit" class="btn btn-polinela"><i class="bi bi-upload me-1"></i>Validasi dan Import</button>
            <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/admin/mahasiswa.php">Kembali</a>
        </form>
        <hr>
        <p class="small mb-0">Kolom wajib mencakup email dosen, kode dan nama mata kuliah, kelas, prodi, semester, tahun ajaran, termin (1/2), hari (Senin–Jumat), jam (<code>HH:MM</code>), tipe sesi (<code>teori</code>/<code>praktik</code>), SKS, dan mode (<code>daring</code>/<code>luring</code>). Jadwal hanya disimpan jika seluruh baris valid; kesalahan menyebutkan nomor baris.</p>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
