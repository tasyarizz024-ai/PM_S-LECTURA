<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

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

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Sesi form tidak valid. Muat ulang halaman dan coba lagi.';
    } else {
        $studentUserId = (int)($_POST['user_id'] ?? 0);
        $action = $_POST['action'] ?? '';
        $stmtStudent = $pdo->prepare(
            "SELECT u.id
             FROM users u
             JOIN mahasiswa m ON m.user_id = u.id
             JOIN program_studi p ON p.id = m.id_prodi
             WHERE u.id = :id AND u.role = 'mahasiswa' AND p.id_jurusan = :jurusan
             LIMIT 1"
        );
        $stmtStudent->execute([':id' => $studentUserId, ':jurusan' => $jurusan]);
        if (!$stmtStudent->fetchColumn()) {
            $errors[] = 'Mahasiswa tidak ditemukan dalam cakupan jurusan Anda.';
        } elseif ($action === 'update_email') {
            $email = trim($_POST['email'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Alamat email tidak valid.';
            } else {
                $stmtDuplicate = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1");
                $stmtDuplicate->execute([':email' => $email, ':id' => $studentUserId]);
                if ($stmtDuplicate->fetchColumn()) {
                    $errors[] = 'Email sudah digunakan akun lain.';
                } else {
                    $stmtUpdate = $pdo->prepare(
                        "UPDATE users u
                         JOIN mahasiswa m ON m.user_id = u.id
                         JOIN program_studi p ON p.id = m.id_prodi
                         SET u.email = :email
                         WHERE u.id = :id AND u.role = 'mahasiswa' AND p.id_jurusan = :jurusan"
                    );
                    $stmtUpdate->execute([
                        ':email' => $email,
                        ':id' => $studentUserId,
                        ':jurusan' => $jurusan,
                    ]);
                    set_flash('success', 'Email mahasiswa berhasil diperbarui.');
                    redirect(BASE_URL . '/admin/mahasiswa.php');
                }
            }
        } elseif ($action === 'reset_password') {
            $temporaryPassword = bin2hex(random_bytes(6));
            $passwordHash = password_hash($temporaryPassword, PASSWORD_DEFAULT);
            $stmtUpdate = $pdo->prepare(
                "UPDATE users u
                 JOIN mahasiswa m ON m.user_id = u.id
                 JOIN program_studi p ON p.id = m.id_prodi
                 SET u.password_hash = :password_hash, u.must_change_password = 1
                 WHERE u.id = :id AND u.role = 'mahasiswa' AND p.id_jurusan = :jurusan"
            );
            $stmtUpdate->execute([
                ':password_hash' => $passwordHash,
                ':id' => $studentUserId,
                ':jurusan' => $jurusan,
            ]);
            if ($stmtUpdate->rowCount() === 1) {
                set_flash(
                    'success',
                    'Sandi sementara: ' . $temporaryPassword . '. Berikan secara pribadi kepada mahasiswa; sandi baru wajib dibuat saat login.'
                );
                redirect(BASE_URL . '/admin/mahasiswa.php');
            }
            $errors[] = 'Mahasiswa tidak lagi berada dalam cakupan jurusan Anda.';
        } else {
            $errors[] = 'Aksi bantuan akun tidak valid.';
        }
    }
}

$search = trim($_GET['q'] ?? '');
$filterProdi = (int)($_GET['prodi'] ?? 0);
$stmtProdi = $pdo->prepare(
    "SELECT id, nama_prodi FROM program_studi
     WHERE id_jurusan = :jurusan
     ORDER BY nama_prodi"
);
$stmtProdi->execute([':jurusan' => $jurusan]);
$prodiList = $stmtProdi->fetchAll();
$validProdiIds = array_map(static fn($prodi) => (int)$prodi['id'], $prodiList);
if ($filterProdi > 0 && !in_array($filterProdi, $validProdiIds, true)) {
    $filterProdi = 0;
}

$sql = "SELECT u.id, u.nama, u.email, m.npm, p.nama_prodi
        FROM users u
        JOIN mahasiswa m ON m.user_id = u.id
        JOIN program_studi p ON p.id = m.id_prodi
        WHERE u.role = 'mahasiswa' AND p.id_jurusan = :jurusan";
$params = [':jurusan' => $jurusan];
if ($filterProdi > 0) {
    $sql .= " AND p.id = :prodi";
    $params[':prodi'] = $filterProdi;
}
if ($search !== '') {
    $sql .= " AND (u.nama LIKE :nama_q OR u.email LIKE :email_q OR m.npm LIKE :npm_q)";
    $params[':nama_q'] = '%' . $search . '%';
    $params[':email_q'] = '%' . $search . '%';
    $params[':npm_q'] = '%' . $search . '%';
}
$sql .= ' ORDER BY u.nama';
$stmtStudents = $pdo->prepare($sql);
$stmtStudents->execute($params);
$students = $stmtStudents->fetchAll();

$page_title = 'Bantuan Akun Mahasiswa';
$active_page = 'mahasiswa-jurusan';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <h3 class="fw-bold mb-1"><i class="bi bi-person-vcard me-2 text-success"></i>Bantuan Akun Mahasiswa</h3>
    <p class="text-secondary mb-0">Cakupan: <?= e($jurusanScope['nama_jurusan']) ?>. Anda hanya dapat membantu akun mahasiswa di jurusan ini.</p>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div>
<?php endif; ?>

<form method="GET" class="card card-custom border-0 shadow-sm mb-4">
    <div class="card-body row g-3 align-items-end">
        <div class="col-md-6">
            <label class="form-label" for="studentSearch">Cari mahasiswa</label>
            <input id="studentSearch" class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Nama, email, atau NPM">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="studentProdi">Program studi</label>
            <select id="studentProdi" class="form-select" name="prodi">
                <option value="0">Semua prodi di jurusan</option>
                <?php foreach ($prodiList as $prodi): ?>
                    <option value="<?= (int)$prodi['id'] ?>" <?= $filterProdi === (int)$prodi['id'] ? 'selected' : '' ?>>
                        <?= e($prodi['nama_prodi']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-polinela flex-grow-1" type="submit"><i class="bi bi-filter me-1"></i>Filter</button>
            <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/admin/mahasiswa.php" title="Reset filter"><i class="bi bi-arrow-counterclockwise"></i></a>
        </div>
    </div>
</form>

<div class="card card-custom border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Mahasiswa</th><th>NPM</th><th>Program Studi</th><th>Email</th><th class="text-end">Bantuan</th></tr></thead>
            <tbody>
            <?php if (!$students): ?>
                <tr><td colspan="5" class="text-center text-muted p-5">Tidak ada mahasiswa ditemukan.</td></tr>
            <?php else: foreach ($students as $student): ?>
                <tr>
                    <td class="fw-semibold"><?= e($student['nama']) ?></td>
                    <td><?= e($student['npm']) ?></td>
                    <td><?= e($student['nama_prodi']) ?></td>
                    <td>
                        <form method="POST" class="d-flex gap-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update_email">
                            <input type="hidden" name="user_id" value="<?= (int)$student['id'] ?>">
                            <input class="form-control form-control-sm" type="email" name="email" value="<?= e($student['email']) ?>" required aria-label="Email mahasiswa">
                            <button class="btn btn-sm btn-outline-primary" type="submit">Simpan</button>
                        </form>
                    </td>
                    <td class="text-end">
                        <form method="POST" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="reset_password">
                            <input type="hidden" name="user_id" value="<?= (int)$student['id'] ?>">
                            <button class="btn btn-sm btn-outline-warning text-dark" type="submit" data-confirm="Reset sandi untuk <?= e($student['nama']) ?>?">
                                <i class="bi bi-key me-1"></i>Reset sandi
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
