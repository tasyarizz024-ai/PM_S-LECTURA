<?php
// Student profile settings.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('mahasiswa');

$pdo = get_db_connection();
$user = current_user();
$stmtStudent = $pdo->prepare(
    "SELECT m.id, m.npm, m.foto, u.nama, u.email, p.nama_prodi
     FROM mahasiswa m
     JOIN users u ON u.id = m.user_id
     JOIN program_studi p ON p.id = m.id_prodi
     WHERE m.user_id = :user_id
     LIMIT 1"
);
$stmtStudent->execute([':user_id' => $user['id']]);
$student = $stmtStudent->fetch();

if (!$student) {
    set_flash('danger', 'Profil mahasiswa belum tersedia.');
    redirect(BASE_URL . '/mahasiswa/index.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Sesi form tidak valid atau kedaluwarsa. Silakan muat ulang halaman.';
    } else {
        $uploadedPhotoPath = null;
        $newPhotoFile = null;
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['foto'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Terjadi kesalahan saat mengunggah foto.';
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $errors[] = 'Ukuran foto maksimal 2MB.';
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
                if ($finfo) {
                    finfo_close($finfo);
                }
                $imageInfo = @getimagesize($file['tmp_name']);
                $extensions = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                ];
                if (!isset($extensions[$mime]) || $imageInfo === false) {
                    $errors[] = 'Foto harus berupa gambar JPG, PNG, atau WEBP yang valid.';
                } else {
                    $targetDir = __DIR__ . '/../assets/img/mahasiswa/';
                    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                        $errors[] = 'Folder penyimpanan foto tidak dapat disiapkan.';
                    } else {
                        $newPhotoFile = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
                        $targetPath = $targetDir . $newPhotoFile;
                        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                            $uploadedPhotoPath = 'assets/img/mahasiswa/' . $newPhotoFile;
                        } else {
                            $newPhotoFile = null;
                            $errors[] = 'Foto gagal disimpan ke server.';
                        }
                    }
                }
            }
        }
        if ($uploadedPhotoPath === null && !$errors) {
            $errors[] = 'Pilih foto profil yang ingin diunggah.';
        }

        if (!$errors) {
            try {
                $pdo->beginTransaction();
                $stmtUpdatePhoto = $pdo->prepare(
                    "UPDATE mahasiswa SET foto = :foto WHERE id = :student_id"
                );
                $stmtUpdatePhoto->execute([
                    ':foto' => $uploadedPhotoPath,
                    ':student_id' => $student['id'],
                ]);
                $pdo->commit();
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($newPhotoFile !== null) {
                    $newPhotoPath = __DIR__ . '/../assets/img/mahasiswa/' . $newPhotoFile;
                    if (is_file($newPhotoPath) && !unlink($newPhotoPath)) {
                        error_log('Unable to remove uncommitted student profile photo: ' . $newPhotoPath);
                    }
                }
                error_log('Student profile update failed for user ' . $user['id'] . ': ' . $exception->getMessage());
                $errors[] = 'Profil gagal disimpan. Periksa kembali data dan coba lagi.';
            }

            if (!$errors) {
                if ($uploadedPhotoPath !== null && !empty($student['foto'])
                    && preg_match('~^assets/img/mahasiswa/[a-f0-9]{32}\.(?:jpg|png|webp)$~', $student['foto'])) {
                    $oldPhotoPath = __DIR__ . '/../' . $student['foto'];
                    if (is_file($oldPhotoPath) && !unlink($oldPhotoPath)) {
                        error_log('Unable to remove replaced student profile photo: ' . $oldPhotoPath);
                    }
                }
                set_flash('success', 'Foto profil berhasil diperbarui.');
                redirect(BASE_URL . '/mahasiswa/profil.php');
            }
        } elseif ($newPhotoFile !== null) {
            $newPhotoPath = __DIR__ . '/../assets/img/mahasiswa/' . $newPhotoFile;
            if (is_file($newPhotoPath) && !unlink($newPhotoPath)) {
                error_log('Unable to remove invalid student profile photo: ' . $newPhotoPath);
            }
        }
    }
}

$page_title = 'Profil Saya';
$active_page = 'profil';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="mb-4">
            <h3 class="fw-bold text-dark mb-1"><i class="bi bi-person-lines-fill me-2 text-success"></i>Profil Saya</h3>
            <p class="text-secondary small mb-0">Informasi identitas hanya dapat dilihat. Anda dapat memperbarui foto profil.</p>
        </div>

        <?php if ($errors): ?>
            <div class="alert alert-danger shadow-sm">
                <strong class="d-block mb-1"><i class="bi bi-exclamation-octagon-fill me-1"></i>Profil belum tersimpan:</strong>
                <ul class="mb-0 ps-3 small">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card card-custom border-0 shadow-sm">
            <div class="card-header card-header-polinela py-3">
                <span class="fw-bold"><i class="bi bi-person-circle me-2"></i>Informasi Akun Mahasiswa</span>
            </div>
            <div class="card-body p-4">
                <form action="<?= BASE_URL ?>/mahasiswa/profil.php" method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="d-flex align-items-center gap-3 mb-4 p-3 bg-light rounded-3">
                        <?php if (!empty($student['foto']) && is_file(__DIR__ . '/../' . $student['foto'])): ?>
                            <img src="<?= BASE_URL . '/' . e($student['foto']) ?>" alt="Foto profil mahasiswa" class="rounded-circle shadow-sm" style="width: 80px; height: 80px; object-fit: cover;">
                        <?php else: ?>
                            <div class="avatar-initial avatar-initial-lg bg-success bg-opacity-10 text-success">
                                <?= strtoupper(substr($student['nama'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <div class="flex-grow-1">
                            <label for="foto" class="form-label fw-semibold small text-secondary mb-1">Foto Profil</label>
                            <input type="hidden" name="MAX_FILE_SIZE" value="2097152">
                            <input type="file" class="form-control form-control-sm" id="foto" name="foto" accept="image/jpeg,image/png,image/webp">
                            <div class="form-text small text-muted">JPG, PNG, atau WEBP; ukuran maksimal 2MB.</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="nama" class="form-label fw-semibold small text-secondary">Nama Lengkap</label>
                        <input type="text" class="form-control bg-light" id="nama" value="<?= e($student['nama']) ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="npm" class="form-label fw-semibold small text-secondary">NPM</label>
                        <input type="text" class="form-control bg-light" id="npm" value="<?= e($student['npm']) ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="prodi" class="form-label fw-semibold small text-secondary">Program Studi</label>
                        <input type="text" class="form-control bg-light" id="prodi" value="<?= e($student['nama_prodi']) ?>" readonly>
                    </div>
                    <div class="mb-4">
                        <label for="email" class="form-label fw-semibold small text-secondary">Email</label>
                        <input type="email" class="form-control bg-light" id="email" value="<?= e($student['email']) ?>" readonly>
                        <div class="form-text small text-muted">Nama, email, NPM, dan program studi hanya dapat diubah oleh admin jurusan.</div>
                    </div>
                    <button type="submit" class="btn btn-polinela px-4 py-2 fw-semibold shadow-sm">
                        <i class="bi bi-save me-1"></i>Simpan Foto Profil
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
