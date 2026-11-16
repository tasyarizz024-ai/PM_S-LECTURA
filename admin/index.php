<?php
// Executive dashboard for department administrators and academic master data.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$pdo = get_db_connection();
$user = current_user();
$countDepartmentAdmins = (int)$pdo->query(
    "SELECT COUNT(*) FROM users WHERE role = 'admin_jurusan' AND aktif = 1"
)->fetchColumn();
$countDepartments = (int)$pdo->query("SELECT COUNT(*) FROM jurusan")->fetchColumn();
$countPrograms = (int)$pdo->query("SELECT COUNT(*) FROM program_studi")->fetchColumn();
$countCompletedMeetings = (int)$pdo->query(
    "SELECT COUNT(*) FROM permintaan_pertemuan WHERE status = 'selesai'"
)->fetchColumn();

$page_title = 'Dashboard Administrator';
$active_page = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="hero-banner shadow-sm">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                <div>
                    <span class="badge bg-success-subtle text-success fw-bold px-3 py-1 mb-2">
                        <i class="bi bi-shield-lock-fill me-1"></i>Administrator Utama
                    </span>
                    <h2 class="fw-bold text-dark mb-1">Pengelolaan Akademik Terpusat</h2>
                    <p class="text-secondary mb-0">
                        Selamat datang, <strong><?= e($user['nama']) ?></strong>. Kelola admin jurusan, data induk, dan pantau rekap pertemuan seluruh kampus.
                    </p>
                </div>
                <div class="text-md-end">
                    <span class="badge bg-light text-dark border p-2 px-3 small">
                        <i class="bi bi-calendar3 me-1 text-success"></i><?= date('d/m/Y') ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card bg-success h-100">
            <div class="card-body">
                <span class="fw-semibold text-white-50 text-uppercase small">Admin Jurusan Aktif</span>
                <h2 class="fw-bold text-white mb-0"><?= $countDepartmentAdmins ?></h2>
                <div class="mt-2"><a href="<?= BASE_URL ?>/admin/users.php" class="text-white text-decoration-none small fw-semibold">Kelola akun <i class="bi bi-arrow-right ms-1"></i></a></div>
            </div>
            <i class="bi bi-person-vcard stat-icon"></i>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card bg-primary h-100">
            <div class="card-body">
                <span class="fw-semibold text-white-50 text-uppercase small">Jurusan</span>
                <h2 class="fw-bold text-white mb-0"><?= $countDepartments ?></h2>
                <div class="mt-2"><a href="<?= BASE_URL ?>/admin/jurusan.php" class="text-white text-decoration-none small fw-semibold">Data induk <i class="bi bi-arrow-right ms-1"></i></a></div>
            </div>
            <i class="bi bi-buildings stat-icon"></i>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card bg-warning h-100">
            <div class="card-body">
                <span class="fw-semibold text-dark text-uppercase small">Program Studi</span>
                <h2 class="fw-bold text-dark mb-0"><?= $countPrograms ?></h2>
                <div class="mt-2"><a href="<?= BASE_URL ?>/admin/prodi.php" class="text-dark text-decoration-none small fw-semibold">Data induk <i class="bi bi-arrow-right ms-1"></i></a></div>
            </div>
            <i class="bi bi-building-fill stat-icon"></i>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card bg-info h-100">
            <div class="card-body">
                <span class="fw-semibold text-white-50 text-uppercase small">Pertemuan Selesai</span>
                <h2 class="fw-bold text-white mb-0"><?= $countCompletedMeetings ?></h2>
                <div class="mt-2"><a href="<?= BASE_URL ?>/admin/pertemuan.php" class="text-white text-decoration-none small fw-semibold">Lihat laporan <i class="bi bi-arrow-right ms-1"></i></a></div>
            </div>
            <i class="bi bi-clipboard-data stat-icon"></i>
        </div>
    </div>
</div>

<div class="mb-3">
    <h4 class="fw-bold text-dark mb-1">Menu Administrasi</h4>
    <p class="text-secondary small mb-0">Akun mahasiswa dan dosen dikelola langsung oleh admin jurusan sesuai cakupannya.</p>
</div>
<div class="row g-3">
    <div class="col-md-6 col-xl-3">
        <a href="<?= BASE_URL ?>/admin/users.php" class="card card-custom border-0 shadow-sm h-100 text-decoration-none">
            <div class="card-body p-4">
                <span class="d-inline-flex p-3 rounded-4 bg-success bg-opacity-10 text-success fs-3 mb-3"><i class="bi bi-person-vcard"></i></span>
                <h6 class="fw-bold text-dark mb-1">Admin Jurusan</h6>
                <p class="small text-muted mb-3">Buat akun, atur cakupan jurusan, reset sandi, dan status akun.</p>
                <span class="btn btn-sm btn-outline-success">Kelola Admin <i class="bi bi-arrow-right ms-1"></i></span>
            </div>
        </a>
    </div>
    <div class="col-md-6 col-xl-3">
        <a href="<?= BASE_URL ?>/admin/jurusan.php" class="card card-custom border-0 shadow-sm h-100 text-decoration-none">
            <div class="card-body p-4">
                <span class="d-inline-flex p-3 rounded-4 bg-primary bg-opacity-10 text-primary fs-3 mb-3"><i class="bi bi-buildings"></i></span>
                <h6 class="fw-bold text-dark mb-1">Master Jurusan</h6>
                <p class="small text-muted mb-3">Kelola struktur jurusan sebagai induk program studi dan cakupan admin.</p>
                <span class="btn btn-sm btn-outline-primary">Kelola Jurusan <i class="bi bi-arrow-right ms-1"></i></span>
            </div>
        </a>
    </div>
    <div class="col-md-6 col-xl-3">
        <a href="<?= BASE_URL ?>/admin/prodi.php" class="card card-custom border-0 shadow-sm h-100 text-decoration-none">
            <div class="card-body p-4">
                <span class="d-inline-flex p-3 rounded-4 bg-warning bg-opacity-10 text-warning fs-3 mb-3"><i class="bi bi-building-fill"></i></span>
                <h6 class="fw-bold text-dark mb-1">Master Program Studi</h6>
                <p class="small text-muted mb-3">Hubungkan program studi ke jurusan untuk menjaga batas pengelolaan.</p>
                <span class="btn btn-sm btn-outline-warning text-dark">Kelola Prodi <i class="bi bi-arrow-right ms-1"></i></span>
            </div>
        </a>
    </div>
    <div class="col-md-6 col-xl-3">
        <a href="<?= BASE_URL ?>/admin/pertemuan.php" class="card card-custom border-0 shadow-sm h-100 text-decoration-none">
            <div class="card-body p-4">
                <span class="d-inline-flex p-3 rounded-4 bg-info bg-opacity-10 text-info fs-3 mb-3"><i class="bi bi-clipboard-data"></i></span>
                <h6 class="fw-bold text-dark mb-1">Laporan Pertemuan</h6>
                <p class="small text-muted mb-3">Pantau rekap agregat konsultasi dan pertemuan seluruh jurusan.</p>
                <span class="btn btn-sm btn-outline-info">Buka Laporan <i class="bi bi-arrow-right ms-1"></i></span>
            </div>
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
