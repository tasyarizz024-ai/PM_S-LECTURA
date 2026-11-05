<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$pdo = get_db_connection();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Token CSRF tidak valid.');
        redirect(BASE_URL . '/admin/jurusan.php');
    }

    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $nama = trim($_POST['nama_jurusan'] ?? '');

    if (in_array($action, ['create', 'update'], true)) {
        if ($nama === '') {
            $errors[] = 'Nama jurusan wajib diisi.';
        } elseif (mb_strlen($nama) > 100) {
            $errors[] = 'Nama jurusan maksimal 100 karakter.';
        } else {
            if ($action === 'update') {
                $stmtExists = $pdo->prepare("SELECT id FROM jurusan WHERE id = :id");
                $stmtExists->execute([':id' => $id]);
                if (!$stmtExists->fetchColumn()) {
                    $errors[] = 'Jurusan yang akan diperbarui tidak ditemukan.';
                }
            }
            $stmtDuplicate = $pdo->prepare(
                "SELECT id FROM jurusan WHERE nama_jurusan = :nama AND id <> :id LIMIT 1"
            );
            $stmtDuplicate->execute([':nama' => $nama, ':id' => $id]);
            if (!$errors && $stmtDuplicate->fetchColumn()) {
                $errors[] = 'Nama jurusan sudah terdaftar.';
            }
        }

        if (!$errors) {
            if ($action === 'create') {
                $stmt = $pdo->prepare("INSERT INTO jurusan (nama_jurusan) VALUES (:nama)");
                $stmt->execute([':nama' => $nama]);
                set_flash('success', "Jurusan '{$nama}' berhasil ditambahkan.");
            } else {
                $stmt = $pdo->prepare("UPDATE jurusan SET nama_jurusan = :nama WHERE id = :id");
                $stmt->execute([':nama' => $nama, ':id' => $id]);
                set_flash('success', "Jurusan '{$nama}' berhasil diperbarui.");
            }
            redirect(BASE_URL . '/admin/jurusan.php');
        }
    } elseif ($action === 'delete') {
        $stmtUsage = $pdo->prepare(
            "SELECT
                (SELECT COUNT(*) FROM program_studi WHERE id_jurusan = :prodi_id) +
                (SELECT COUNT(*) FROM admin_jurusan WHERE id_jurusan = :admin_id)"
        );
        $stmtUsage->execute([':prodi_id' => $id, ':admin_id' => $id]);
        $usageCount = (int)$stmtUsage->fetchColumn();
        if ($usageCount > 0) {
            set_flash('danger', 'Jurusan tidak dapat dihapus karena masih digunakan oleh program studi atau admin jurusan.');
        } else {
            $stmtDelete = $pdo->prepare("DELETE FROM jurusan WHERE id = :id");
            $stmtDelete->execute([':id' => $id]);
            set_flash($stmtDelete->rowCount() === 1 ? 'success' : 'danger',
                $stmtDelete->rowCount() === 1 ? 'Jurusan berhasil dihapus.' : 'Jurusan tidak ditemukan.');
        }
        redirect(BASE_URL . '/admin/jurusan.php');
    }
}

$jurusanList = $pdo->query(
    "SELECT j.id, j.nama_jurusan, COUNT(DISTINCT p.id) AS total_prodi,
            COUNT(DISTINCT aj.user_id) AS total_admin
     FROM jurusan j
     LEFT JOIN program_studi p ON p.id_jurusan = j.id
     LEFT JOIN admin_jurusan aj ON aj.id_jurusan = j.id
     GROUP BY j.id
     ORDER BY j.nama_jurusan"
)->fetchAll();

$page_title = 'Master Jurusan';
$active_page = 'jurusan';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row align-items-center mb-4">
    <div class="col-md-8">
        <h3 class="fw-bold mb-1"><i class="bi bi-buildings me-2 text-success"></i>Master Jurusan</h3>
        <p class="text-secondary small mb-0">Kelola jurusan kampus. Program studi dan admin jurusan ditautkan ke data ini.</p>
    </div>
    <div class="col-md-4 text-md-end mt-3 mt-md-0">
        <button class="btn btn-polinela" data-bs-toggle="modal" data-bs-target="#modalTambahJurusan">
            <i class="bi bi-plus-circle me-1"></i>Tambah Jurusan
        </button>
    </div>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="card card-custom border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th class="ps-4">Jurusan</th><th>Program Studi</th><th>Admin Jurusan</th><th class="text-end pe-4">Aksi</th></tr>
            </thead>
            <tbody>
                <?php if (!$jurusanList): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">Belum ada data jurusan.</td></tr>
                <?php else: ?>
                    <?php foreach ($jurusanList as $jurusan): ?>
                        <tr>
                            <td class="ps-4 fw-semibold"><?= e($jurusan['nama_jurusan']) ?></td>
                            <td><?= (int)$jurusan['total_prodi'] ?></td>
                            <td><?= (int)$jurusan['total_admin'] ?></td>
                            <td class="text-end pe-4">
                                <button type="button" class="btn btn-sm btn-outline-primary btn-edit-jurusan"
                                        data-id="<?= (int)$jurusan['id'] ?>"
                                        data-nama="<?= e($jurusan['nama_jurusan']) ?>"
                                        data-bs-toggle="modal" data-bs-target="#modalEditJurusan" title="Edit jurusan">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$jurusan['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit"
                                            data-confirm="Hapus jurusan '<?= e($jurusan['nama_jurusan']) ?>'? Jurusan yang masih digunakan tidak dapat dihapus."
                                            title="Hapus jurusan"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalTambahJurusan" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow">
        <form method="POST">
            <?= csrf_field() ?><input type="hidden" name="action" value="create">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold">Tambah Jurusan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label" for="nama_jurusan">Nama jurusan</label>
                <input class="form-control" id="nama_jurusan" name="nama_jurusan" maxlength="100" required>
            </div>
            <div class="modal-footer"><button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Batal</button><button class="btn btn-polinela">Simpan</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="modalEditJurusan" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow">
        <form method="POST">
            <?= csrf_field() ?><input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_jurusan_id">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">Edit Jurusan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label" for="edit_nama_jurusan">Nama jurusan</label>
                <input class="form-control" id="edit_nama_jurusan" name="nama_jurusan" maxlength="100" required>
            </div>
            <div class="modal-footer"><button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary">Simpan Perubahan</button></div>
        </form>
    </div></div>
</div>

<script>
document.querySelectorAll('.btn-edit-jurusan').forEach(function (button) {
    button.addEventListener('click', function () {
        document.getElementById('edit_jurusan_id').value = this.dataset.id;
        document.getElementById('edit_nama_jurusan').value = this.dataset.nama;
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
