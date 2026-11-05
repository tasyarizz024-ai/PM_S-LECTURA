<?php
// ==============================================================
// S-LECTURA - Politeknik Negeri Lampung
// Dashboard Administrator Kampus & Ringkasan Metrik
// File: admin/index.php
// ==============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$pdo = get_db_connection();
$user = current_user();

// 1. Ambil Metrik Ringkasan Sistem
$countDosen = (int)$pdo->query("SELECT COUNT(*) FROM dosen")->fetchColumn();
$countMhs = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'mahasiswa'")->fetchColumn();
$countProdi = (int)$pdo->query("SELECT COUNT(*) FROM program_studi")->fetchColumn();
$countJurusan = (int)$pdo->query("SELECT COUNT(*) FROM jurusan")->fetchColumn();

$page_title = 'Dashboard Administrator';
$active_page = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Banner Sambutan Administrator -->
<div class="row mb-4">
    <div class="col-12">
        <div class="hero-banner shadow-sm">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                <div>
                    <span class="badge bg-success-subtle text-success fw-bold px-3 py-1 mb-2">
                        <i class="bi bi-shield-lock-fill me-1"></i>Panel Administrator S-LECTURA
                    </span>
                    <h2 class="fw-bold text-dark mb-1">Pusat Kendali Sistem Politeknik Negeri Lampung</h2>
                    <p class="text-secondary mb-0">
                        Selamat datang, <strong><?= e($user['nama']) ?></strong>. Kelola akun pengguna dan data master sistem.
                    </p>
                </div>
                <div class="text-md-end">
                    <span class="badge bg-light text-dark border p-2 px-3 small">
                        <i class="bi bi-calendar3 me-1 text-success"></i><?= date('l, d F Y') ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Kartu Metrik Ringkasan (Stat Cards) -->
<div class="row g-3 mb-4">
    <!-- Total Dosen -->
    <div class="col-sm-6 col-xl">
        <div class="card stat-card bg-success shadow-sm h-100">
            <div class="card-body">
                <span class="fw-semibold text-white-50 text-uppercase small">Total Dosen</span>
                <h2 class="fw-bold text-white mb-0"><?= $countDosen ?></h2>
                <div class="mt-2">
                    <a href="<?= BASE_URL ?>/admin/dosen.php" class="text-white text-decoration-none small fw-semibold">
                        Kelola Dosen <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
            <i class="bi bi-person-video3 stat-icon"></i>
        </div>
    </div>

    <!-- Total Mahasiswa -->
    <div class="col-sm-6 col-xl">
        <div class="card stat-card bg-primary shadow-sm h-100">
            <div class="card-body">
                <span class="fw-semibold text-white-50 text-uppercase small">Mahasiswa Terdaftar</span>
                <h2 class="fw-bold text-white mb-0"><?= $countMhs ?></h2>
                <div class="mt-2">
                    <a href="<?= BASE_URL ?>/admin/users.php" class="text-white text-decoration-none small fw-semibold">
                        Kelola Pengguna <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
            <i class="bi bi-people-fill stat-icon"></i>
        </div>
    </div>

    <!-- Total Program Studi -->
    <div class="col-sm-6 col-xl">
        <div class="card stat-card bg-warning shadow-sm h-100">
            <div class="card-body">
                <span class="fw-semibold text-dark text-uppercase small">Jurusan</span>
                <h2 class="fw-bold text-dark mb-0"><?= $countJurusan ?></h2>
                <div class="mt-2">
                    <a href="<?= BASE_URL ?>/admin/jurusan.php" class="text-dark text-decoration-none small fw-semibold">
                        Kelola Jurusan <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
            <i class="bi bi-buildings stat-icon"></i>
        </div>
    </div>

    <!-- Total Program Studi -->
    <div class="col-sm-6 col-xl">
        <div class="card stat-card bg-secondary shadow-sm h-100">
            <div class="card-body">
                <span class="fw-semibold text-white-50 text-uppercase small">Program Studi</span>
                <h2 class="fw-bold text-white mb-0"><?= $countProdi ?></h2>
                <div class="mt-2">
                    <a href="<?= BASE_URL ?>/admin/prodi.php" class="text-white text-decoration-none small fw-semibold">
                        Kelola Prodi <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
            <i class="bi bi-building-fill stat-icon"></i>
        </div>
    </div>
</div>

<!-- Navigasi Menu Cepat Admin -->
<div class="row g-3">
    <div class="col-md-4">
        <div class="card card-custom border-0 shadow-sm p-3">
            <div class="d-flex align-items-center gap-3">
                <span class="p-3 rounded-circle bg-primary bg-opacity-10 text-primary fs-3">
                    <i class="bi bi-person-gear"></i>
                </span>
                <div>
                    <h6 class="fw-bold text-dark mb-1">Manajemen Pengguna</h6>
                    <p class="small text-muted mb-2">Tambah admin, dosen, mahasiswa, reset sandi.</p>
                    <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-sm btn-outline-primary">Buka Pengguna</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-custom border-0 shadow-sm p-3">
            <div class="d-flex align-items-center gap-3">
                <span class="p-3 rounded-circle bg-success bg-opacity-10 text-success fs-3">
                    <i class="bi bi-person-badge"></i>
                </span>
                <div>
                    <h6 class="fw-bold text-dark mb-1">Master Data Dosen</h6>
                    <p class="small text-muted mb-2">Penugasan NIDN, prodi, dan keahlian.</p>
                    <a href="<?= BASE_URL ?>/admin/dosen.php" class="btn btn-sm btn-outline-success">Buka Master Dosen</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-custom border-0 shadow-sm p-3">
            <div class="d-flex align-items-center gap-3">
                <span class="p-3 rounded-circle bg-warning bg-opacity-10 text-warning fs-3">
                    <i class="bi bi-building"></i>
                </span>
                <div>
                    <h6 class="fw-bold text-dark mb-1">Master Program Studi</h6>
                    <p class="small text-muted mb-2">Kelola program studi yang terhubung ke master jurusan.</p>
                    <a href="<?= BASE_URL ?>/admin/prodi.php" class="btn btn-sm btn-outline-warning text-dark">Buka Prodi</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
