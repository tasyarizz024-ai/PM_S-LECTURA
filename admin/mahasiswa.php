<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/user_import.php';

require_role('admin_jurusan');

$pdo = get_db_connection();
$user = current_user();
$stmtScope = $pdo->prepare(
    "SELECT aj.id_jurusan, j.nama_jurusan
     FROM admin_jurusan aj
     JOIN jurusan j ON j.id = aj.id_jurusan
     WHERE aj.user_id = :user_id"
);
$stmtScope->execute([':user_id' => $user['id']]);
$jurusanScope = $stmtScope->fetch();
if (!$jurusanScope) {
    set_flash('danger', 'Akun admin jurusan belum memiliki cakupan jurusan. Hubungi administrator.');
    redirect(BASE_URL . '/logout.php');
}
$jurusan = (int)$jurusanScope['id_jurusan'];

$stmtProdi = $pdo->prepare(
    "SELECT id, nama_prodi FROM program_studi
     WHERE id_jurusan = :jurusan
     ORDER BY nama_prodi"
);
$stmtProdi->execute([':jurusan' => $jurusan]);
$prodiList = $stmtProdi->fetchAll();
$validProdiIds = array_map(static fn($prodi) => (int)$prodi['id'], $prodiList);
$selectedProdi = (int)($_POST['prodi'] ?? $_GET['prodi'] ?? 0);
if (!in_array($selectedProdi, $validProdiIds, true)) {
    $selectedProdi = 0;
}

if (($_GET['template'] ?? '') === 'users') {
    $importRole = (string)($_GET['role'] ?? '');
    if ($selectedProdi <= 0) {
        set_flash('danger', 'Pilih program studi yang valid untuk mengunduh template akun.');
        redirect(BASE_URL . '/admin/mahasiswa.php');
    }
    if (!in_array($importRole, ['mahasiswa', 'dosen'], true)) {
        set_flash('danger', 'Pilih jenis akun yang valid untuk mengunduh template.');
        redirect(BASE_URL . '/admin/mahasiswa.php?prodi=' . $selectedProdi);
    }
    $template = schedule_xlsx_template(user_xlsx_headers($importRole), ucfirst($importRole));
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="template-' . $importRole . '.xlsx"');
    header('Content-Length: ' . strlen($template));
    echo $template;
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Sesi form tidak valid. Muat ulang halaman dan coba lagi.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'import_users') {
            $importRole = (string)($_POST['import_role'] ?? '');
            if (!in_array($importRole, ['mahasiswa', 'dosen'], true)) {
                $errors[] = 'Pilih jenis akun mahasiswa atau dosen.';
            } elseif ($selectedProdi <= 0) {
                $errors[] = 'Pilih program studi sebelum mengimpor akun.';
            } elseif (!isset($_FILES['file_excel']) || $_FILES['file_excel']['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'File Excel gagal diunggah.';
            } elseif ($_FILES['file_excel']['size'] > 5 * 1024 * 1024) {
                $errors[] = 'Ukuran file maksimal 5 MB.';
            } elseif (strtolower(pathinfo($_FILES['file_excel']['name'], PATHINFO_EXTENSION)) !== 'xlsx') {
                $errors[] = 'Gunakan file Excel .xlsx.';
            } else {
                try {
                    $rows = schedule_xlsx_read($_FILES['file_excel']['tmp_name'], user_xlsx_headers($importRole));
                    $result = validate_user_import($pdo, $rows, $selectedProdi, $importRole);
                    $errors = $result['errors'];
                    if (!$errors) {
                        $pdo->beginTransaction();
                        try {
                            $credentials = insert_user_import($pdo, $result['rows'], $selectedProdi);
                            $pdo->commit();
                            $_SESSION['user_import_credentials'] = $credentials;
                            set_flash('success', count($credentials) . ' akun berhasil diimpor. Simpan sandi sementara sebelum meninggalkan halaman ini.');
                            redirect(BASE_URL . '/admin/mahasiswa.php?prodi=' . $selectedProdi);
                        } catch (Throwable $exception) {
                            if ($pdo->inTransaction()) {
                                $pdo->rollBack();
                            }
                            error_log('User import failed for department admin ' . $user['id'] . ': ' . $exception->getMessage());
                            $errors[] = 'Import dibatalkan karena ada data yang berubah atau ditolak database. Tidak ada akun yang dibuat; periksa data lalu coba lagi.';
                        }
                    }
                } catch (RuntimeException $exception) {
                    $errors[] = $exception->getMessage();
                }
            }
        } elseif ($action === 'create_user') {
            $accountRole = (string)($_POST['account_role'] ?? '');
            if (!in_array($accountRole, ['mahasiswa', 'dosen'], true)) {
                $errors[] = 'Pilih jenis akun mahasiswa atau dosen.';
            } elseif ($selectedProdi <= 0) {
                $errors[] = 'Pilih program studi sebelum menambahkan akun.';
            } else {
                $row = [
                    '_row_number' => 1,
                    'nama' => trim($_POST['nama'] ?? ''),
                    'email' => trim($_POST['email'] ?? ''),
                    'npm' => trim($_POST['npm'] ?? ''),
                    'nidn_nip' => trim($_POST['nidn_nip'] ?? ''),
                    'bidang_keahlian' => trim($_POST['bidang_keahlian'] ?? ''),
                    'tanggal_lahir' => trim($_POST['tanggal_lahir'] ?? ''),
                ];
                $result = validate_user_import($pdo, [$row], $selectedProdi, $accountRole);
                $errors = $result['errors'];
                if (!$errors) {
                    try {
                        $pdo->beginTransaction();
                        $credentials = insert_user_import($pdo, $result['rows'], $selectedProdi);
                        $pdo->commit();
                        $_SESSION['user_import_credentials'] = $credentials;
                        set_flash('success', 'Akun berhasil dibuat. Simpan sandi sementara sebelum meninggalkan halaman ini.');
                        redirect(BASE_URL . '/admin/mahasiswa.php?prodi=' . $selectedProdi);
                    } catch (Throwable $exception) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        error_log('Manual user creation failed for department admin ' . $user['id'] . ': ' . $exception->getMessage());
                        $errors[] = 'Akun gagal dibuat karena database menolak perubahan. Periksa data dan coba lagi.';
                    }
                }
            }
        } elseif ($selectedProdi <= 0) {
            $errors[] = 'Pilih program studi terlebih dahulu.';
        } elseif (in_array($action, ['update_lecturer', 'reset_lecturer_password', 'set_lecturer_active'], true)) {
            $lecturerUserId = (int)($_POST['user_id'] ?? 0);
            $stmtLecturerAccount = $pdo->prepare(
                "SELECT u.id, u.nama
                 FROM users u
                 JOIN dosen d ON d.user_id = u.id
                 WHERE u.id = :id AND u.role = 'dosen' AND d.id_prodi = :prodi
                 LIMIT 1"
            );
            $stmtLecturerAccount->execute([':id' => $lecturerUserId, ':prodi' => $selectedProdi]);
            $lecturerAccount = $stmtLecturerAccount->fetch();
            if (!$lecturerAccount) {
                $errors[] = 'Dosen tidak ditemukan dalam program studi yang dipilih.';
            } elseif ($action === 'update_lecturer') {
                $name = trim($_POST['nama'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $nidn = trim($_POST['nidn_nip'] ?? '');
                $expertise = trim($_POST['bidang_keahlian'] ?? '');
                if ($name === '' || mb_strlen($name) > 100) {
                    $errors[] = 'Nama wajib diisi dan maksimal 100 karakter.';
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
                    $errors[] = 'Alamat email tidak valid atau melebihi 100 karakter.';
                } else {
                    $stmtDuplicateEmail = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1");
                    $stmtDuplicateEmail->execute([':email' => $email, ':id' => $lecturerUserId]);
                    if ($stmtDuplicateEmail->fetchColumn()) {
                        $errors[] = 'Email sudah digunakan akun lain.';
                    }
                }
                if ($nidn !== '' && mb_strlen($nidn) > 30) {
                    $errors[] = 'NIDN/NIP maksimal 30 karakter.';
                } elseif ($nidn !== '') {
                    $stmtDuplicateNidn = $pdo->prepare("SELECT id FROM dosen WHERE nidn_nip = :nidn AND user_id != :user_id LIMIT 1");
                    $stmtDuplicateNidn->execute([':nidn' => $nidn, ':user_id' => $lecturerUserId]);
                    if ($stmtDuplicateNidn->fetchColumn()) {
                        $errors[] = 'NIDN/NIP sudah digunakan dosen lain.';
                    }
                }
                if ($expertise === '' || mb_strlen($expertise) > 150) {
                    $errors[] = 'Bidang keahlian wajib diisi dan maksimal 150 karakter.';
                }
                if (!$errors) {
                    $pdo->beginTransaction();
                    try {
                        $stmtUpdate = $pdo->prepare(
                            "UPDATE users u
                             JOIN dosen d ON d.user_id = u.id
                             SET u.nama = :nama, u.email = :email,
                                 d.nidn_nip = :nidn, d.bidang_keahlian = :expertise
                             WHERE u.id = :id AND u.role = 'dosen' AND d.id_prodi = :prodi"
                        );
                        $stmtUpdate->execute([
                            ':nama' => $name,
                            ':email' => $email,
                            ':nidn' => $nidn !== '' ? $nidn : null,
                            ':expertise' => $expertise,
                            ':id' => $lecturerUserId,
                            ':prodi' => $selectedProdi,
                        ]);
                        $pdo->commit();
                        set_flash('success', 'Data dosen berhasil diperbarui.');
                        redirect(BASE_URL . '/admin/mahasiswa.php?prodi=' . $selectedProdi);
                    } catch (Throwable $exception) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        error_log('Lecturer profile update failed for department admin ' . $user['id'] . ': ' . $exception->getMessage());
                        $errors[] = 'Data dosen gagal diperbarui karena database menolak perubahan.';
                    }
                }
            } elseif ($action === 'reset_lecturer_password') {
                $birthDatePassword = user_import_password_from_birth_date($_POST['tanggal_lahir'] ?? '');
                if ($birthDatePassword === null) {
                    $errors[] = 'Tanggal lahir tidak valid. Masukkan tanggal DD-MM-YYYY dan pastikan bukan tanggal di masa depan.';
                } else {
                    $stmtUpdate = $pdo->prepare(
                        "UPDATE users u
                         JOIN dosen d ON d.user_id = u.id
                         SET u.password_hash = :password_hash, u.must_change_password = 1
                         WHERE u.id = :id AND u.role = 'dosen' AND d.id_prodi = :prodi"
                    );
                    $stmtUpdate->execute([
                        ':password_hash' => password_hash($birthDatePassword, PASSWORD_DEFAULT),
                        ':id' => $lecturerUserId,
                        ':prodi' => $selectedProdi,
                    ]);
                    if ($stmtUpdate->rowCount() === 1) {
                        set_flash('success', 'Sandi dosen berhasil direset ke tanggal lahir (' . $birthDatePassword . '). Sampaikan secara pribadi; sandi wajib diganti saat login.');
                        redirect(BASE_URL . '/admin/mahasiswa.php?prodi=' . $selectedProdi);
                    }
                    $errors[] = 'Sandi tidak berubah. Pastikan tanggal lahir benar dan coba lagi.';
                }
            } else {
                $active = (int)($_POST['aktif'] ?? -1);
                if (!in_array($active, [0, 1], true)) {
                    $errors[] = 'Status akun dosen tidak valid.';
                } else {
                    $stmtUpdate = $pdo->prepare(
                        "UPDATE users u
                         JOIN dosen d ON d.user_id = u.id
                         SET u.aktif = :aktif
                         WHERE u.id = :id AND u.role = 'dosen' AND d.id_prodi = :prodi"
                    );
                    $stmtUpdate->execute([
                        ':aktif' => $active,
                        ':id' => $lecturerUserId,
                        ':prodi' => $selectedProdi,
                    ]);
                    if ($stmtUpdate->rowCount() === 1) {
                        $statusLabel = $active === 1 ? 'diaktifkan' : 'dinonaktifkan';
                        set_flash('success', 'Akun dosen ' . $lecturerAccount['nama'] . ' berhasil ' . $statusLabel . '. Jadwal dan riwayat tetap tersimpan.');
                        redirect(BASE_URL . '/admin/mahasiswa.php?prodi=' . $selectedProdi);
                    }
                    $errors[] = 'Status akun dosen tidak berubah.';
                }
            }
        } else {
        $studentUserId = (int)($_POST['user_id'] ?? 0);
        $stmtStudent = $pdo->prepare(
            "SELECT u.id, u.nama
             FROM users u
             JOIN mahasiswa m ON m.user_id = u.id
             JOIN program_studi p ON p.id = m.id_prodi
             WHERE u.id = :id AND u.role = 'mahasiswa' AND p.id = :prodi
             LIMIT 1"
        );
        $stmtStudent->execute([':id' => $studentUserId, ':prodi' => $selectedProdi]);
        $studentAccount = $stmtStudent->fetch();
        if (!$studentAccount) {
            $errors[] = 'Mahasiswa tidak ditemukan dalam program studi yang dipilih.';
        } elseif ($action === 'update_profile') {
            $name = trim($_POST['nama'] ?? '');
            $email = trim($_POST['email'] ?? '');
            if ($name === '' || mb_strlen($name) > 100) {
                $errors[] = 'Nama wajib diisi dan maksimal 100 karakter.';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
                $errors[] = 'Alamat email tidak valid atau melebihi 100 karakter.';
            } else {
                $stmtDuplicate = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1");
                $stmtDuplicate->execute([':email' => $email, ':id' => $studentUserId]);
                if ($stmtDuplicate->fetchColumn()) {
                    $errors[] = 'Email sudah digunakan akun lain.';
                }
            }
            if (!$errors) {
                $stmtUpdate = $pdo->prepare(
                    "UPDATE users u
                     JOIN mahasiswa m ON m.user_id = u.id
                     SET u.nama = :nama, u.email = :email
                     WHERE u.id = :id AND u.role = 'mahasiswa' AND m.id_prodi = :prodi"
                );
                $stmtUpdate->execute([
                    ':nama' => $name,
                    ':email' => $email,
                    ':id' => $studentUserId,
                    ':prodi' => $selectedProdi,
                ]);
                set_flash('success', 'Nama dan email mahasiswa berhasil diperbarui.');
                redirect(BASE_URL . '/admin/mahasiswa.php?prodi=' . $selectedProdi);
            }
        } elseif ($action === 'reset_password') {
            $birthDatePassword = user_import_password_from_birth_date($_POST['tanggal_lahir'] ?? '');
            if ($birthDatePassword === null) {
                $errors[] = 'Tanggal lahir tidak valid. Masukkan tanggal DD-MM-YYYY dan pastikan bukan tanggal di masa depan.';
            } else {
                $passwordHash = password_hash($birthDatePassword, PASSWORD_DEFAULT);
                $stmtUpdate = $pdo->prepare(
                    "UPDATE users u
                     JOIN mahasiswa m ON m.user_id = u.id
                     JOIN program_studi p ON p.id = m.id_prodi
                     SET u.password_hash = :password_hash, u.must_change_password = 1
                     WHERE u.id = :id AND u.role = 'mahasiswa' AND p.id = :prodi"
                );
                $stmtUpdate->execute([
                    ':password_hash' => $passwordHash,
                    ':id' => $studentUserId,
                    ':prodi' => $selectedProdi,
                ]);
                if ($stmtUpdate->rowCount() === 1) {
                    set_flash(
                        'success',
                        'Sandi mahasiswa berhasil direset ke tanggal lahir (' . $birthDatePassword . '). Sampaikan secara pribadi; sandi wajib diganti saat login.'
                    );
                    redirect(BASE_URL . '/admin/mahasiswa.php?prodi=' . $selectedProdi);
                }
                $errors[] = 'Sandi tidak berubah. Pastikan tanggal lahir benar dan coba lagi.';
            }
        } elseif ($action === 'set_active') {
            $active = (int)($_POST['aktif'] ?? -1);
            if (!in_array($active, [0, 1], true)) {
                $errors[] = 'Status akun tidak valid.';
            } else {
                $stmtUpdate = $pdo->prepare(
                    "UPDATE users u
                     JOIN mahasiswa m ON m.user_id = u.id
                     SET u.aktif = :aktif
                     WHERE u.id = :id AND u.role = 'mahasiswa' AND m.id_prodi = :prodi"
                );
                $stmtUpdate->execute([
                    ':aktif' => $active,
                    ':id' => $studentUserId,
                    ':prodi' => $selectedProdi,
                ]);
                if ($stmtUpdate->rowCount() === 1) {
                    $statusLabel = $active === 1 ? 'diaktifkan' : 'dinonaktifkan';
                    set_flash('success', 'Akun mahasiswa ' . $studentAccount['nama'] . ' berhasil ' . $statusLabel . '. Riwayat pertemuan tetap tersimpan.');
                    redirect(BASE_URL . '/admin/mahasiswa.php?prodi=' . $selectedProdi);
                }
                $errors[] = 'Status akun mahasiswa tidak berubah.';
            }
        } else {
            $errors[] = 'Aksi bantuan akun tidak valid.';
        }
        }
    }
}

$search = trim($_GET['q'] ?? '');
$statusFilter = (string)($_GET['status'] ?? 'aktif');
if (!in_array($statusFilter, ['aktif', 'nonaktif', 'semua'], true)) {
    $statusFilter = 'aktif';
}
$sql = "SELECT u.id, u.nama, u.email, m.npm, p.nama_prodi,
               u.aktif,
               (SELECT COUNT(*) FROM permintaan_pertemuan pp WHERE pp.id_mahasiswa = m.id) AS jumlah_pertemuan
        FROM users u
        JOIN mahasiswa m ON m.user_id = u.id
        JOIN program_studi p ON p.id = m.id_prodi
        WHERE u.role = 'mahasiswa' AND p.id = :prodi";
$params = [':prodi' => $selectedProdi];
if ($statusFilter !== 'semua') {
    $sql .= ' AND u.aktif = :aktif';
    $params[':aktif'] = $statusFilter === 'aktif' ? 1 : 0;
}
if ($search !== '') {
    $sql .= " AND (u.nama LIKE :nama_q OR u.email LIKE :email_q OR m.npm LIKE :npm_q)";
    $params[':nama_q'] = '%' . $search . '%';
    $params[':email_q'] = '%' . $search . '%';
    $params[':npm_q'] = '%' . $search . '%';
}
$sql .= ' ORDER BY u.nama';
$stmtStudents = $pdo->prepare($sql);
$students = [];
$lecturers = [];
$selectedProgram = null;
$importCredentials = [];
if ($selectedProdi > 0) {
    $stmtStudents->execute($params);
    $students = $stmtStudents->fetchAll();
    $stmtLecturers = $pdo->prepare(
        "SELECT u.id, u.nama, u.email, u.aktif, d.nidn_nip, d.bidang_keahlian,
                (SELECT COUNT(*) FROM jadwal_mengajar jm WHERE jm.id_dosen = d.id) AS jumlah_jadwal,
                (SELECT COUNT(*) FROM permintaan_pertemuan pp WHERE pp.id_dosen = d.id) AS jumlah_pertemuan
         FROM dosen d
         JOIN users u ON u.id = d.user_id
         WHERE d.id_prodi = :prodi AND (:status = 'semua' OR u.aktif = :aktif)
         ORDER BY u.nama"
    );
    $stmtLecturers->execute([
        ':prodi' => $selectedProdi,
        ':status' => $statusFilter,
        ':aktif' => $statusFilter === 'nonaktif' ? 0 : 1,
    ]);
    $lecturers = $stmtLecturers->fetchAll();
    foreach ($prodiList as $program) {
        if ((int)$program['id'] === $selectedProdi) {
            $selectedProgram = $program;
            break;
        }
    }
}
if (isset($_SESSION['user_import_credentials']) && $selectedProdi > 0) {
    $importCredentials = $_SESSION['user_import_credentials'];
    unset($_SESSION['user_import_credentials']);
}

$page_title = 'Pengguna per Program Studi';
$active_page = 'mahasiswa-jurusan';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <h3 class="fw-bold mb-1"><i class="bi bi-person-vcard me-2 text-success"></i>Pengguna per Program Studi</h3>
    <p class="text-secondary mb-0">Pilih program studi dalam <?= e($jurusanScope['nama_jurusan']) ?> untuk melihat dan mengelola akun di dalamnya.</p>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <strong>Permintaan belum berhasil:</strong>
        <ul class="mb-0 mt-2"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <?php foreach ($prodiList as $prodi): ?>
        <div class="col-sm-6 col-xl-4">
            <a class="card card-custom border-0 shadow-sm h-100 text-decoration-none <?= $selectedProdi === (int)$prodi['id'] ? 'border-start border-success border-4' : '' ?>"
               href="<?= BASE_URL ?>/admin/mahasiswa.php?prodi=<?= (int)$prodi['id'] ?>">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <div class="fw-bold text-dark"><?= e($prodi['nama_prodi']) ?></div>
                        <small class="text-muted">Kelola akun prodi</small>
                    </div>
                    <i class="bi bi-arrow-right-circle text-success fs-4"></i>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($selectedProdi > 0 && $selectedProgram): ?>
    <div class="d-flex flex-column flex-md-row justify-content-between gap-2 align-items-md-center mb-3">
        <div>
            <h4 class="fw-bold mb-1"><?= e($selectedProgram['nama_prodi']) ?></h4>
            <p class="text-muted mb-0">Akun yang ditambahkan manual maupun melalui impor akan terhubung ke program studi ini.</p>
        </div>
        <button type="button" class="btn btn-polinela text-nowrap" data-bs-toggle="modal" data-bs-target="#createUserModal">
            <i class="bi bi-person-plus-fill me-1"></i>Tambah Manual
        </button>
    </div>

    <?php if ($importCredentials): ?>
        <div class="alert alert-warning">
            <h5 class="alert-heading">Simpan sandi awal ini sekarang</h5>
            <p class="small">Sandi ditampilkan satu kali saja. Berikan kepada pemilik akun dan minta untuk menggantinya saat login pertama.</p>
            <div class="table-responsive">
                <table class="table table-sm table-bordered bg-white mb-0">
                    <thead><tr><th>Nama</th><th>Peran</th><th>Email</th><th>Sandi sementara</th></tr></thead>
                    <tbody><?php foreach ($importCredentials as $credential): ?>
                        <tr>
                            <td><?= e($credential['nama']) ?></td>
                            <td><?= e(ucfirst($credential['peran'])) ?></td>
                            <td><?= e($credential['email']) ?></td>
                            <td><code><?= e($credential['password']) ?></code></td>
                        </tr>
                    <?php endforeach; ?></tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_user">
                    <input type="hidden" name="prodi" value="<?= $selectedProdi ?>">
                    <div class="modal-header bg-success text-white">
                        <div>
                            <h5 class="modal-title fw-bold" id="createUserModalLabel"><i class="bi bi-person-plus-fill me-2"></i>Tambah Akun Manual</h5>
                            <div class="small text-white-50"><?= e($selectedProgram['nama_prodi']) ?></div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="alert alert-info py-2 small">
                            Password awal dibuat dari tanggal lahir dalam format <code>DDMMYYYY</code>. Tanggal lahir tidak disimpan dan akun harus mengganti password saat login pertama.
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="manualAccountRole">Jenis akun</label>
                            <select class="form-select" name="account_role" id="manualAccountRole" required>
                                <option value="mahasiswa">Mahasiswa</option>
                                <option value="dosen">Dosen</option>
                            </select>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="manualAccountName">Nama lengkap</label>
                                <input class="form-control" type="text" name="nama" id="manualAccountName" maxlength="100" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="manualAccountEmail">Email</label>
                                <input class="form-control" type="email" name="email" id="manualAccountEmail" maxlength="100" required>
                            </div>
                            <div class="col-md-6" id="manualStudentFields">
                                <label class="form-label fw-semibold" for="manualAccountNpm">NPM</label>
                                <input class="form-control" type="text" name="npm" id="manualAccountNpm" maxlength="20">
                            </div>
                            <div class="col-md-6 d-none" id="manualLecturerIdField">
                                <label class="form-label fw-semibold" for="manualAccountNidn">NIDN/NIP <span class="fw-normal text-muted">(opsional)</span></label>
                                <input class="form-control" type="text" name="nidn_nip" id="manualAccountNidn" maxlength="30" disabled>
                            </div>
                            <div class="col-12 d-none" id="manualLecturerExpertiseField">
                                <label class="form-label fw-semibold" for="manualAccountExpertise">Bidang keahlian</label>
                                <input class="form-control" type="text" name="bidang_keahlian" id="manualAccountExpertise" maxlength="150" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="manualAccountBirthDate">Tanggal lahir</label>
                                <input class="form-control" type="date" name="tanggal_lahir" id="manualAccountBirthDate" max="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-polinela"><i class="bi bi-save me-1"></i>Buat Akun</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card card-custom border-0 shadow-sm mb-4">
        <div class="card-body">
            <h5 class="fw-bold"><i class="bi bi-file-earmark-arrow-up text-success me-2"></i>Impor Data Pengguna</h5>
            <p class="text-secondary">Pilih jenis data, unduh template khusus, lalu unggah file. Maksimal 500 baris; seluruh baris harus valid agar impor dilakukan.</p>
            <form method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="import_users">
                <input type="hidden" name="prodi" value="<?= $selectedProdi ?>">
                <div class="row g-3 align-items-end mb-3">
                    <div class="col-md-5">
                        <label class="form-label fw-semibold" for="importRole">Jenis data yang diimpor</label>
                        <select class="form-select" name="import_role" id="importRole" required>
                            <option value="mahasiswa">Mahasiswa</option>
                            <option value="dosen">Dosen</option>
                        </select>
                    </div>
                    <div class="col-md-7">
                        <a id="downloadUserTemplate" class="btn btn-outline-success" href="<?= BASE_URL ?>/admin/mahasiswa.php?prodi=<?= $selectedProdi ?>&template=users&role=mahasiswa">
                            <i class="bi bi-download me-1"></i>Unduh Template Mahasiswa
                        </a>
                    </div>
                </div>
                <label class="form-label fw-semibold" for="usersExcel">Pilih file .xlsx (maks. 5 MB)</label>
                <div class="d-flex flex-column flex-md-row gap-2">
                    <input id="usersExcel" class="form-control" type="file" name="file_excel" accept=".xlsx" required>
                    <button class="btn btn-polinela text-nowrap" type="submit"><i class="bi bi-upload me-1"></i>Validasi dan Impor</button>
                </div>
            </form>
            <div class="small text-muted mt-2">Tanggal lahir dipakai sebagai password awal dengan format <code>DDMMYYYY</code> (contoh: 7 Januari 2004 menjadi <code>07012004</code>), lalu wajib diganti saat login pertama. Data tanggal lahir tidak disimpan. Formatkan NPM/NIDN sebagai teks di Excel agar angka nol di depan tidak hilang.</div>
        </div>
    </div>

    <form method="GET" class="card card-custom border-0 shadow-sm mb-4">
        <input type="hidden" name="prodi" value="<?= $selectedProdi ?>">
        <div class="card-body row g-3 align-items-end">
            <div class="col-md-6">
                <label class="form-label" for="studentSearch">Cari akun di prodi ini</label>
                <input id="studentSearch" class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Nama, email, atau NPM">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="studentStatus">Status akun</label>
                <select id="studentStatus" class="form-select" name="status">
                    <option value="aktif" <?= $statusFilter === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                    <option value="nonaktif" <?= $statusFilter === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                    <option value="semua" <?= $statusFilter === 'semua' ? 'selected' : '' ?>>Semua</option>
                </select>
            </div>
            <div class="col-md-3">
                <button class="btn btn-polinela w-100" type="submit"><i class="bi bi-search me-1"></i>Tampilkan</button>
            </div>
        </div>
    </form>

    <div class="card card-custom border-0 shadow-sm mb-4">
        <div class="card-header card-header-polinela fw-bold">
            Mahasiswa <?= ucfirst($statusFilter) ?> (<?= count($students) ?>)
            <span class="small fw-normal ms-2">Penonaktifan mempertahankan profil dan riwayat.</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Nama</th><th>NPM</th><th>Email</th><th>Status</th><th>Riwayat</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                <?php if (!$students): ?>
                    <tr><td colspan="6" class="text-center text-muted p-4">Belum ada mahasiswa pada hasil ini.</td></tr>
                <?php else: foreach ($students as $student): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($student['nama']) ?></td>
                        <td><?= e($student['npm']) ?></td>
                        <td><?= e($student['email']) ?></td>
                        <td><span class="badge <?= (int)$student['aktif'] === 1 ? 'bg-success' : 'bg-secondary' ?>"><?= (int)$student['aktif'] === 1 ? 'Aktif' : 'Nonaktif' ?></span></td>
                        <td><?= (int)$student['jumlah_pertemuan'] ?> pertemuan</td>
                        <td class="text-end text-nowrap">
                            <button type="button" class="btn btn-sm btn-outline-primary btn-edit-student"
                                data-id="<?= (int)$student['id'] ?>"
                                data-nama="<?= e($student['nama']) ?>"
                                data-email="<?= e($student['email']) ?>">
                                <i class="bi bi-pencil me-1"></i>Edit
                            </button>
                            <?php if ((int)$student['aktif'] === 1): ?>
                                <button type="button" class="btn btn-sm btn-outline-warning text-dark btn-reset-student"
                                    data-id="<?= (int)$student['id'] ?>"
                                    data-nama="<?= e($student['nama']) ?>">
                                    <i class="bi bi-key me-1"></i>Reset sandi
                                </button>
                            <?php endif; ?>
                            <form method="POST" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="prodi" value="<?= $selectedProdi ?>">
                                <input type="hidden" name="action" value="set_active">
                                <input type="hidden" name="aktif" value="<?= (int)$student['aktif'] === 1 ? 0 : 1 ?>">
                                <input type="hidden" name="user_id" value="<?= (int)$student['id'] ?>">
                                <button class="btn btn-sm <?= (int)$student['aktif'] === 1 ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                                    type="submit"
                                    data-confirm="<?= (int)$student['aktif'] === 1
                                        ? 'Nonaktifkan akun ' . e($student['nama']) . '? Riwayat ' . (int)$student['jumlah_pertemuan'] . ' pertemuan akan tetap tersimpan.'
                                        : 'Aktifkan kembali akun ' . e($student['nama']) . '?' ?>">
                                    <i class="bi <?= (int)$student['aktif'] === 1 ? 'bi-person-dash' : 'bi-person-check' ?> me-1"></i><?= (int)$student['aktif'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="editStudentModal" tabindex="-1" aria-labelledby="editStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_profile">
                    <input type="hidden" name="prodi" value="<?= $selectedProdi ?>">
                    <input type="hidden" name="user_id" id="editStudentId">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold" id="editStudentModalLabel">Edit Data Mahasiswa</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="editStudentName">Nama</label>
                            <input class="form-control" id="editStudentName" name="nama" maxlength="100" required>
                        </div>
                        <div>
                            <label class="form-label" for="editStudentEmail">Email</label>
                            <input class="form-control" type="email" id="editStudentEmail" name="email" maxlength="100" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-polinela">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="resetStudentPasswordModal" tabindex="-1" aria-labelledby="resetStudentPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="prodi" value="<?= $selectedProdi ?>">
                    <input type="hidden" name="user_id" id="resetStudentId">
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title fw-bold" id="resetStudentPasswordModalLabel">Reset Sandi Mahasiswa</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <p>Reset sandi untuk <strong id="resetStudentName"></strong>. Masukkan tanggal lahir mahasiswa untuk membuat sandi awal.</p>
                        <label class="form-label" for="resetStudentBirthDate">Tanggal lahir</label>
                        <input class="form-control" type="date" id="resetStudentBirthDate" name="tanggal_lahir" required>
                        <div class="form-text">Sandi awal menjadi DDMMYYYY dan mahasiswa wajib menggantinya saat login.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-warning">Reset Sandi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card card-custom border-0 shadow-sm">
        <div class="card-header card-header-polinela fw-bold">
            Dosen <?= ucfirst($statusFilter) ?> (<?= count($lecturers) ?>)
            <span class="small fw-normal ms-2">Penonaktifan mempertahankan jadwal dan riwayat pertemuan.</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Nama</th><th>NIDN/NIP</th><th>Email</th><th>Bidang Keahlian</th><th>Status</th><th>Aktivitas</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                <?php if (!$lecturers): ?>
                    <tr><td colspan="7" class="text-center text-muted p-4">Belum ada dosen pada hasil ini.</td></tr>
                <?php else: foreach ($lecturers as $lecturer): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($lecturer['nama']) ?></td>
                        <td><?= e($lecturer['nidn_nip'] ?: '-') ?></td>
                        <td><?= e($lecturer['email']) ?></td>
                        <td><?= e($lecturer['bidang_keahlian']) ?></td>
                        <td><span class="badge <?= (int)$lecturer['aktif'] === 1 ? 'bg-success' : 'bg-secondary' ?>"><?= (int)$lecturer['aktif'] === 1 ? 'Aktif' : 'Nonaktif' ?></span></td>
                        <td><?= (int)$lecturer['jumlah_jadwal'] ?> jadwal<br><?= (int)$lecturer['jumlah_pertemuan'] ?> pertemuan</td>
                        <td class="text-end text-nowrap">
                            <button type="button" class="btn btn-sm btn-outline-primary btn-edit-lecturer"
                                data-id="<?= (int)$lecturer['id'] ?>"
                                data-nama="<?= e($lecturer['nama']) ?>"
                                data-email="<?= e($lecturer['email']) ?>"
                                data-nidn="<?= e($lecturer['nidn_nip'] ?? '') ?>"
                                data-expertise="<?= e($lecturer['bidang_keahlian']) ?>">
                                <i class="bi bi-pencil me-1"></i>Edit
                            </button>
                            <?php if ((int)$lecturer['aktif'] === 1): ?>
                                <button type="button" class="btn btn-sm btn-outline-warning text-dark btn-reset-lecturer"
                                    data-id="<?= (int)$lecturer['id'] ?>"
                                    data-nama="<?= e($lecturer['nama']) ?>">
                                    <i class="bi bi-key me-1"></i>Reset sandi
                                </button>
                            <?php endif; ?>
                            <form method="POST" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="prodi" value="<?= $selectedProdi ?>">
                                <input type="hidden" name="action" value="set_lecturer_active">
                                <input type="hidden" name="aktif" value="<?= (int)$lecturer['aktif'] === 1 ? 0 : 1 ?>">
                                <input type="hidden" name="user_id" value="<?= (int)$lecturer['id'] ?>">
                                <button class="btn btn-sm <?= (int)$lecturer['aktif'] === 1 ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                                    type="submit"
                                    data-confirm="<?= (int)$lecturer['aktif'] === 1
                                        ? 'Nonaktifkan akun dosen ' . e($lecturer['nama']) . '? Jadwal dan riwayat ' . (int)$lecturer['jumlah_pertemuan'] . ' pertemuan tetap tersimpan.'
                                        : 'Aktifkan kembali akun dosen ' . e($lecturer['nama']) . '?' ?>">
                                    <i class="bi <?= (int)$lecturer['aktif'] === 1 ? 'bi-person-dash' : 'bi-person-check' ?> me-1"></i><?= (int)$lecturer['aktif'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="editLecturerModal" tabindex="-1" aria-labelledby="editLecturerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_lecturer">
                    <input type="hidden" name="prodi" value="<?= $selectedProdi ?>">
                    <input type="hidden" name="user_id" id="editLecturerId">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold" id="editLecturerModalLabel">Edit Data Dosen</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="editLecturerName">Nama</label>
                            <input class="form-control" id="editLecturerName" name="nama" maxlength="100" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="editLecturerEmail">Email</label>
                            <input class="form-control" type="email" id="editLecturerEmail" name="email" maxlength="100" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="editLecturerNidn">NIDN/NIP (opsional)</label>
                            <input class="form-control" id="editLecturerNidn" name="nidn_nip" maxlength="30">
                        </div>
                        <div>
                            <label class="form-label" for="editLecturerExpertise">Bidang Keahlian</label>
                            <input class="form-control" id="editLecturerExpertise" name="bidang_keahlian" maxlength="150" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-polinela">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="resetLecturerPasswordModal" tabindex="-1" aria-labelledby="resetLecturerPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reset_lecturer_password">
                    <input type="hidden" name="prodi" value="<?= $selectedProdi ?>">
                    <input type="hidden" name="user_id" id="resetLecturerId">
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title fw-bold" id="resetLecturerPasswordModalLabel">Reset Sandi Dosen</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <p>Reset sandi untuk <strong id="resetLecturerName"></strong>. Masukkan tanggal lahir dosen untuk membuat sandi awal.</p>
                        <label class="form-label" for="resetLecturerBirthDate">Tanggal lahir</label>
                        <input class="form-control" type="date" id="resetLecturerBirthDate" name="tanggal_lahir" required>
                        <div class="form-text">Sandi awal menjadi DDMMYYYY dan dosen wajib menggantinya saat login.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-warning">Reset Sandi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Pilih salah satu kartu program studi untuk membuka daftar pengguna, menambah akun manual, atau mengimpor akun.</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const editModalElement = document.getElementById('editStudentModal');
    const resetModalElement = document.getElementById('resetStudentPasswordModal');
    const editLecturerModalElement = document.getElementById('editLecturerModal');
    const resetLecturerModalElement = document.getElementById('resetLecturerPasswordModal');
    const editModal = editModalElement ? new bootstrap.Modal(editModalElement) : null;
    const resetModal = resetModalElement ? new bootstrap.Modal(resetModalElement) : null;
    const editLecturerModal = editLecturerModalElement ? new bootstrap.Modal(editLecturerModalElement) : null;
    const resetLecturerModal = resetLecturerModalElement ? new bootstrap.Modal(resetLecturerModalElement) : null;

    document.querySelectorAll('.btn-edit-student').forEach(function (button) {
        button.addEventListener('click', function () {
            document.getElementById('editStudentId').value = this.dataset.id;
            document.getElementById('editStudentName').value = this.dataset.nama;
            document.getElementById('editStudentEmail').value = this.dataset.email;
            editModal.show();
        });
    });

    document.querySelectorAll('.btn-reset-student').forEach(function (button) {
        button.addEventListener('click', function () {
            document.getElementById('resetStudentId').value = this.dataset.id;
            document.getElementById('resetStudentName').textContent = this.dataset.nama;
            document.getElementById('resetStudentBirthDate').value = '';
            resetModal.show();
        });
    });

    document.querySelectorAll('.btn-edit-lecturer').forEach(function (button) {
        button.addEventListener('click', function () {
            document.getElementById('editLecturerId').value = this.dataset.id;
            document.getElementById('editLecturerName').value = this.dataset.nama;
            document.getElementById('editLecturerEmail').value = this.dataset.email;
            document.getElementById('editLecturerNidn').value = this.dataset.nidn;
            document.getElementById('editLecturerExpertise').value = this.dataset.expertise;
            editLecturerModal.show();
        });
    });

    document.querySelectorAll('.btn-reset-lecturer').forEach(function (button) {
        button.addEventListener('click', function () {
            document.getElementById('resetLecturerId').value = this.dataset.id;
            document.getElementById('resetLecturerName').textContent = this.dataset.nama;
            document.getElementById('resetLecturerBirthDate').value = '';
            resetLecturerModal.show();
        });
    });

    const roleSelect = document.getElementById('importRole');
    const templateLink = document.getElementById('downloadUserTemplate');
    if (roleSelect && templateLink) {
        roleSelect.addEventListener('change', function () {
            const roleLabel = this.value === 'dosen' ? 'Dosen' : 'Mahasiswa';
            templateLink.href = '<?= BASE_URL ?>/admin/mahasiswa.php?prodi=<?= $selectedProdi ?>&template=users&role=' + encodeURIComponent(this.value);
            templateLink.innerHTML = '<i class="bi bi-download me-1"></i>Unduh Template ' + roleLabel;
        });
    }

    const manualRoleSelect = document.getElementById('manualAccountRole');
    const studentFields = document.getElementById('manualStudentFields');
    const lecturerIdField = document.getElementById('manualLecturerIdField');
    const lecturerExpertiseField = document.getElementById('manualLecturerExpertiseField');
    if (manualRoleSelect && studentFields && lecturerIdField && lecturerExpertiseField) {
        const studentNpm = document.getElementById('manualAccountNpm');
        const lecturerNidn = document.getElementById('manualAccountNidn');
        const lecturerExpertise = document.getElementById('manualAccountExpertise');
        const updateManualFields = function () {
            const isLecturer = manualRoleSelect.value === 'dosen';
            studentFields.classList.toggle('d-none', isLecturer);
            lecturerIdField.classList.toggle('d-none', !isLecturer);
            lecturerExpertiseField.classList.toggle('d-none', !isLecturer);
            studentNpm.disabled = isLecturer;
            studentNpm.required = !isLecturer;
            lecturerNidn.disabled = !isLecturer;
            lecturerExpertise.disabled = !isLecturer;
            lecturerExpertise.required = isLecturer;
        };
        manualRoleSelect.addEventListener('change', updateManualFields);
        updateManualFields();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
