<?php
// Manage department administrator accounts.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$pdo = get_db_connection();
$currentUser = current_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Token CSRF tidak valid. Silakan coba lagi.');
        redirect(BASE_URL . '/admin/users.php');
    }

    $action = $_POST['action'] ?? '';
    $targetId = (int)($_POST['id'] ?? 0);

    if ($action === 'create') {
        $name = trim($_POST['nama'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $departmentId = (int)($_POST['id_jurusan'] ?? 0);

        if ($name === '' || mb_strlen($name) > 100) {
            $errors[] = 'Nama wajib diisi dan maksimal 100 karakter.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
            $errors[] = 'Alamat email tidak valid atau melebihi 100 karakter.';
        }
        if (strlen($password) < 6) {
            $errors[] = 'Kata sandi awal minimal 6 karakter.';
        }
        $stmtDepartment = $pdo->prepare("SELECT id FROM jurusan WHERE id = :id LIMIT 1");
        $stmtDepartment->execute([':id' => $departmentId]);
        if (!$stmtDepartment->fetchColumn()) {
            $errors[] = 'Jurusan yang dipilih tidak valid.';
        }
        $stmtDuplicate = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmtDuplicate->execute([':email' => $email]);
        if ($stmtDuplicate->fetchColumn()) {
            $errors[] = 'Email sudah digunakan akun lain.';
        }

        if (!$errors) {
            try {
                $pdo->beginTransaction();
                $stmtCreateUser = $pdo->prepare(
                    "INSERT INTO users (nama, email, password_hash, role, must_change_password)
                     VALUES (:nama, :email, :password_hash, 'admin_jurusan', 1)"
                );
                $stmtCreateUser->execute([
                    ':nama' => $name,
                    ':email' => $email,
                    ':password_hash' => password_hash($password, PASSWORD_BCRYPT),
                ]);
                $newUserId = (int)$pdo->lastInsertId();
                $stmtCreateScope = $pdo->prepare(
                    "INSERT INTO admin_jurusan (user_id, id_jurusan) VALUES (:user_id, :jurusan_id)"
                );
                $stmtCreateScope->execute([':user_id' => $newUserId, ':jurusan_id' => $departmentId]);
                $pdo->commit();
                set_flash('success', "Akun admin jurusan '{$name}' berhasil dibuat.");
                redirect(BASE_URL . '/admin/users.php');
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Department administrator creation failed: ' . $exception->getMessage());
                $errors[] = 'Akun admin jurusan gagal dibuat karena database menolak perubahan.';
            }
        }
    } elseif (in_array($action, ['update', 'reset_password', 'set_active'], true)) {
        $stmtTarget = $pdo->prepare(
            "SELECT u.id, u.nama
             FROM users u
             JOIN admin_jurusan aj ON aj.user_id = u.id
             WHERE u.id = :id AND u.role = 'admin_jurusan'
             LIMIT 1"
        );
        $stmtTarget->execute([':id' => $targetId]);
        $target = $stmtTarget->fetch();
        if (!$target) {
            $errors[] = 'Akun admin jurusan tidak ditemukan.';
        } elseif ($action === 'update') {
            $name = trim($_POST['nama'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $departmentId = (int)($_POST['id_jurusan'] ?? 0);
            if ($name === '' || mb_strlen($name) > 100) {
                $errors[] = 'Nama wajib diisi dan maksimal 100 karakter.';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
                $errors[] = 'Alamat email tidak valid atau melebihi 100 karakter.';
            }
            $stmtDepartment = $pdo->prepare("SELECT id FROM jurusan WHERE id = :id LIMIT 1");
            $stmtDepartment->execute([':id' => $departmentId]);
            if (!$stmtDepartment->fetchColumn()) {
                $errors[] = 'Jurusan yang dipilih tidak valid.';
            }
            $stmtDuplicate = $pdo->prepare(
                "SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1"
            );
            $stmtDuplicate->execute([':email' => $email, ':id' => $targetId]);
            if ($stmtDuplicate->fetchColumn()) {
                $errors[] = 'Email sudah digunakan akun lain.';
            }

            if (!$errors) {
                try {
                    $pdo->beginTransaction();
                    $stmtUpdate = $pdo->prepare(
                        "UPDATE users
                         SET nama = :nama, email = :email
                         WHERE id = :id AND role = 'admin_jurusan'"
                    );
                    $stmtUpdate->execute([':nama' => $name, ':email' => $email, ':id' => $targetId]);
                    $stmtUpdateScope = $pdo->prepare(
                        "UPDATE admin_jurusan SET id_jurusan = :jurusan_id WHERE user_id = :user_id"
                    );
                    $stmtUpdateScope->execute([':jurusan_id' => $departmentId, ':user_id' => $targetId]);
                    $pdo->commit();
                    set_flash('success', "Data admin jurusan '{$name}' berhasil diperbarui.");
                    redirect(BASE_URL . '/admin/users.php');
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('Department administrator update failed: ' . $exception->getMessage());
                    $errors[] = 'Data admin jurusan gagal diperbarui.';
                }
            }
        } elseif ($action === 'reset_password') {
            $password = $_POST['new_password'] ?? '';
            if (strlen($password) < 6) {
                $errors[] = 'Kata sandi baru minimal 6 karakter.';
            } else {
                $stmtReset = $pdo->prepare(
                    "UPDATE users
                     SET password_hash = :password_hash, must_change_password = 1
                     WHERE id = :id AND role = 'admin_jurusan'"
                );
                $stmtReset->execute([
                    ':password_hash' => password_hash($password, PASSWORD_BCRYPT),
                    ':id' => $targetId,
                ]);
                set_flash('success', "Kata sandi admin jurusan '{$target['nama']}' berhasil direset.");
                redirect(BASE_URL . '/admin/users.php');
            }
        } elseif ($action === 'set_active') {
            $active = (int)($_POST['aktif'] ?? 0) === 1 ? 1 : 0;
            if ($targetId === (int)$currentUser['id'] && $active === 0) {
                $errors[] = 'Anda tidak dapat menonaktifkan akun sendiri.';
            } else {
                $stmtSetActive = $pdo->prepare(
                    "UPDATE users SET aktif = :aktif WHERE id = :id AND role = 'admin_jurusan'"
                );
                $stmtSetActive->execute([':aktif' => $active, ':id' => $targetId]);
                set_flash('success', $active
                    ? "Akun admin jurusan '{$target['nama']}' berhasil diaktifkan."
                    : "Akun admin jurusan '{$target['nama']}' berhasil dinonaktifkan.");
                redirect(BASE_URL . '/admin/users.php');
            }
        }
    } else {
        $errors[] = 'Aksi tidak dikenali.';
    }
}

$search = trim($_GET['q'] ?? '');
$sql = "SELECT u.id, u.nama, u.email, u.aktif, u.created_at,
               aj.id_jurusan, j.nama_jurusan
        FROM users u
        JOIN admin_jurusan aj ON aj.user_id = u.id
        JOIN jurusan j ON j.id = aj.id_jurusan
        WHERE u.role = 'admin_jurusan'";
$params = [];
if ($search !== '') {
    $sql .= " AND (u.nama LIKE :nama OR u.email LIKE :email OR j.nama_jurusan LIKE :jurusan)";
    $pattern = '%' . $search . '%';
    $params = [':nama' => $pattern, ':email' => $pattern, ':jurusan' => $pattern];
}
$sql .= " ORDER BY j.nama_jurusan, u.nama";
$stmtAdmins = $pdo->prepare($sql);
$stmtAdmins->execute($params);
$admins = $stmtAdmins->fetchAll();
$departments = $pdo->query("SELECT id, nama_jurusan FROM jurusan ORDER BY nama_jurusan")->fetchAll();

$page_title = 'Admin Jurusan';
$active_page = 'users';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1"><i class="bi bi-person-vcard me-2 text-success"></i>Akun Admin Jurusan</h3>
        <p class="text-secondary small mb-0">Kelola akun admin dan cakupan jurusan. Pengelolaan mahasiswa dan dosen dilakukan admin jurusan.</p>
    </div>
    <button type="button" class="btn btn-polinela shadow-sm" data-bs-toggle="modal" data-bs-target="#createAdminModal">
        <i class="bi bi-person-plus-fill me-1"></i>Tambah Admin Jurusan
    </button>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger shadow-sm">
        <strong class="d-block mb-1">Permintaan belum berhasil:</strong>
        <ul class="mb-0 ps-3 small"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="GET" class="card card-custom border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-column flex-md-row gap-2">
        <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Cari nama admin, email, atau jurusan">
        <button class="btn btn-polinela text-nowrap" type="submit"><i class="bi bi-search me-1"></i>Cari</button>
        <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/admin/users.php" title="Reset pencarian"><i class="bi bi-arrow-counterclockwise"></i></a>
    </div>
</form>

<div class="card card-custom border-0 shadow-sm">
    <div class="card-header card-header-polinela d-flex justify-content-between align-items-center">
        <span class="fw-bold"><i class="bi bi-people me-2"></i>Daftar Admin Jurusan</span>
        <span class="badge bg-success"><?= count($admins) ?> akun</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th class="ps-4">Nama</th><th>Email</th><th>Jurusan</th><th>Status</th><th>Terdaftar</th><th class="text-end pe-4">Aksi</th></tr></thead>
            <tbody>
            <?php if (!$admins): ?>
                <tr><td colspan="6" class="text-center text-muted p-4">Belum ada akun admin jurusan yang sesuai.</td></tr>
            <?php else: foreach ($admins as $admin): ?>
                <tr>
                    <td class="ps-4 fw-semibold">
                        <?= e($admin['nama']) ?>
                        <?php if ((int)$admin['id'] === (int)$currentUser['id']): ?><span class="badge bg-warning text-dark ms-1">Akun Anda</span><?php endif; ?>
                    </td>
                    <td><?= e($admin['email']) ?></td>
                    <td><?= e($admin['nama_jurusan']) ?></td>
                    <td><span class="badge <?= (int)$admin['aktif'] === 1 ? 'bg-success' : 'bg-secondary' ?>"><?= (int)$admin['aktif'] === 1 ? 'Aktif' : 'Nonaktif' ?></span></td>
                    <td class="small text-muted"><?= format_datetime($admin['created_at']) ?></td>
                    <td class="text-end pe-4 text-nowrap">
                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#editAdminModal"
                                data-id="<?= (int)$admin['id'] ?>" data-nama="<?= e($admin['nama']) ?>"
                                data-email="<?= e($admin['email']) ?>" data-jurusan="<?= (int)$admin['id_jurusan'] ?>" title="Edit admin">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-warning text-dark" type="button" data-bs-toggle="modal" data-bs-target="#resetPasswordModal"
                                data-id="<?= (int)$admin['id'] ?>" data-nama="<?= e($admin['nama']) ?>" title="Reset sandi">
                            <i class="bi bi-key-fill"></i>
                        </button>
                        <?php if ((int)$admin['id'] !== (int)$currentUser['id']): ?>
                            <form method="POST" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="set_active">
                                <input type="hidden" name="id" value="<?= (int)$admin['id'] ?>">
                                <input type="hidden" name="aktif" value="<?= (int)$admin['aktif'] === 1 ? 0 : 1 ?>">
                                <button class="btn btn-sm <?= (int)$admin['aktif'] === 1 ? 'btn-outline-danger' : 'btn-outline-success' ?>" type="submit"
                                        data-confirm="<?= (int)$admin['aktif'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?> akun admin <?= e($admin['nama']) ?>?"
                                        title="<?= (int)$admin['aktif'] === 1 ? 'Nonaktifkan akun' : 'Aktifkan akun' ?>">
                                    <i class="bi <?= (int)$admin['aktif'] === 1 ? 'bi-person-dash' : 'bi-person-check' ?>"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="createAdminModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow">
        <form method="POST">
            <?= csrf_field() ?><input type="hidden" name="action" value="create">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold">Tambah Admin Jurusan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <label class="form-label" for="createName">Nama lengkap</label>
                <input class="form-control mb-3" id="createName" name="nama" maxlength="100" required>
                <label class="form-label" for="createEmail">Email akun</label>
                <input class="form-control mb-3" id="createEmail" name="email" type="email" maxlength="100" required>
                <label class="form-label" for="createPassword">Kata sandi awal</label>
                <input class="form-control mb-3" id="createPassword" name="password" type="password" minlength="6" required>
                <label class="form-label" for="createDepartment">Jurusan yang dikelola</label>
                <select class="form-select" id="createDepartment" name="id_jurusan" required>
                    <option value="">Pilih jurusan</option>
                    <?php foreach ($departments as $department): ?><option value="<?= (int)$department['id'] ?>"><?= e($department['nama_jurusan']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Batal</button><button class="btn btn-polinela" type="submit">Buat Akun</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="editAdminModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow">
        <form method="POST">
            <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" id="editAdminId">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">Edit Admin Jurusan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <label class="form-label" for="editAdminName">Nama lengkap</label>
                <input class="form-control mb-3" id="editAdminName" name="nama" maxlength="100" required>
                <label class="form-label" for="editAdminEmail">Email akun</label>
                <input class="form-control mb-3" id="editAdminEmail" name="email" type="email" maxlength="100" required>
                <label class="form-label" for="editAdminDepartment">Jurusan yang dikelola</label>
                <select class="form-select" id="editAdminDepartment" name="id_jurusan" required>
                    <?php foreach ($departments as $department): ?><option value="<?= (int)$department['id'] ?>"><?= e($department['nama_jurusan']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Batal</button><button class="btn btn-polinela" type="submit">Simpan Perubahan</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow">
        <form method="POST">
            <?= csrf_field() ?><input type="hidden" name="action" value="reset_password"><input type="hidden" name="id" id="resetAdminId">
            <div class="modal-header bg-warning">
                <h5 class="modal-title fw-bold">Reset Kata Sandi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p>Reset sandi akun <strong id="resetAdminName"></strong>. Admin diwajibkan mengganti sandi saat login berikutnya.</p>
                <label class="form-label" for="resetAdminPassword">Kata sandi baru</label>
                <input class="form-control" id="resetAdminPassword" name="new_password" type="password" minlength="6" required>
            </div>
            <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Batal</button><button class="btn btn-warning" type="submit">Reset Sandi</button></div>
        </form>
    </div></div>
</div>

<script>
document.getElementById('editAdminModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    this.querySelector('#editAdminId').value = button.dataset.id;
    this.querySelector('#editAdminName').value = button.dataset.nama;
    this.querySelector('#editAdminEmail').value = button.dataset.email;
    this.querySelector('#editAdminDepartment').value = button.dataset.jurusan;
});
document.getElementById('resetPasswordModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    this.querySelector('#resetAdminId').value = button.dataset.id;
    this.querySelector('#resetAdminName').textContent = button.dataset.nama;
    this.querySelector('#resetAdminPassword').value = '';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
