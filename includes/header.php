<?php
// ==============================================================
// S-LECTURA - Politeknik Negeri Lampung
// Template Header & Navigasi Responsif
// File: includes/header.php
// ==============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';

$user = current_user();
$activePage = $active_page ?? '';
$pageClass = $page_class ?? '';
$unreadNotifications = 0;
if ($user) {
    $stmtUnread = get_db_connection()->prepare(
        "SELECT COUNT(*) FROM notifikasi WHERE user_id = :user_id AND dibaca_at IS NULL"
    );
    $stmtUnread->execute([':user_id' => $user['id']]);
    $unreadNotifications = (int)$stmtUnread->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? e($page_title) . ' - ' : '' ?>S-LECTURA Politeknik Negeri Lampung</title>
    
    <!-- Bootstrap 5.3 CSS (Lokal Offline + CDN Fallback) -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons (Lokal Offline + CDN Fallback) -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Kustom Style Polinela -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="<?= e($pageClass) ?>">

<!-- Navigasi Bar Utama -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-polinela sticky-top py-2">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= BASE_URL ?>/index.php">
            <img class="site-brand-logo" src="<?= BASE_URL ?>/assets/img/branding/Logo-Polinela-New-Standart.png" alt="Logo Politeknik Negeri Lampung">
            <span class="site-brand-copy">
                <span class="fw-bold tracking-wide">S-LECTURA</span>
                <span class="d-block text-white-50">Politeknik Negeri Lampung</span>
            </span>
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php if ($user && $user['role'] === 'mahasiswa'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $activePage === 'beranda' ? 'active' : '' ?>" href="<?= BASE_URL ?>/mahasiswa/index.php">
                            <i class="bi bi-grid-fill me-1"></i>Daftar Dosen & Ketersediaan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $activePage === 'pertemuan' ? 'active' : '' ?>" href="<?= BASE_URL ?>/mahasiswa/pertemuan.php">
                            <i class="bi bi-calendar2-heart me-1"></i>Janji Temu Saya
                        </a>
                    </li>
                <?php elseif ($user && $user['role'] === 'dosen'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $activePage === 'dashboard' ? 'active' : '' ?>" href="<?= BASE_URL ?>/dosen/index.php">
                            <i class="bi bi-speedometer2 me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $activePage === 'jadwal' ? 'active' : '' ?>" href="<?= BASE_URL ?>/dosen/jadwal.php">
                            <i class="bi bi-calendar3 me-1"></i>Jadwal Mengajar
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $activePage === 'slot' ? 'active' : '' ?>" href="<?= BASE_URL ?>/dosen/slot.php">
                            <i class="bi bi-clock-history me-1"></i>Slot Konsultasi & Bimbingan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $activePage === 'pertemuan' ? 'active' : '' ?>" href="<?= BASE_URL ?>/dosen/pertemuan.php">
                            <i class="bi bi-calendar2-heart me-1"></i>Permintaan Pertemuan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $activePage === 'profil' ? 'active' : '' ?>" href="<?= BASE_URL ?>/dosen/profil.php">
                            <i class="bi bi-person-lines-fill me-1"></i>Profil Saya
                        </a>
                    </li>
                <?php elseif ($user && $user['role'] === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $activePage === 'dashboard' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/index.php">
                            <i class="bi bi-speedometer2 me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $activePage === 'users' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/users.php">
                            <i class="bi bi-people-fill me-1"></i>Pengguna
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $activePage === 'dosen' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/dosen.php">
                            <i class="bi bi-person-badge-fill me-1"></i>Data Dosen
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $activePage === 'jurusan' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/jurusan.php">
                            <i class="bi bi-buildings me-1"></i>Master Jurusan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $activePage === 'prodi' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/prodi.php">
                            <i class="bi bi-building-fill me-1"></i>Program Studi
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $activePage === 'laporan-pertemuan' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/pertemuan.php">
                            <i class="bi bi-clipboard-data me-1"></i>Laporan Pertemuan
                        </a>
                    </li>
                <?php elseif ($user && $user['role'] === 'admin_jurusan'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $activePage === 'jadwal-dosen-jurusan' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/jadwal-import.php">
                            <i class="bi bi-calendar2-week me-1"></i>Import Jadwal Dosen
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $activePage === 'mahasiswa-jurusan' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/mahasiswa.php">
                            <i class="bi bi-person-vcard me-1"></i>Bantuan Akun Mahasiswa
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $activePage === 'laporan-pertemuan' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/pertemuan.php">
                            <i class="bi bi-clipboard-data me-1"></i>Laporan Pertemuan
                        </a>
                    </li>
                <?php endif; ?>
            </ul>

            <!-- Area Profil / Tombol Masuk -->
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <?php if ($user): ?>
                    <li class="nav-item me-lg-2">
                        <a class="nav-link position-relative" href="<?= BASE_URL ?>/notifikasi.php" aria-label="Notifikasi">
                            <i class="bi bi-bell-fill"></i><span class="d-lg-none ms-1">Notifikasi</span>
                            <?php if ($unreadNotifications > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?= $unreadNotifications > 99 ? '99+' : $unreadNotifications ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <button class="nav-link dropdown-toggle d-flex align-items-center gap-2 py-1 px-2 rounded user-menu-toggle" type="button" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false" aria-haspopup="true" aria-label="Menu akun <?= e($user['nama']) ?>">
                            <span class="avatar-initial avatar-initial-sm bg-warning text-dark fw-bold">
                                <?= strtoupper(substr($user['nama'], 0, 1)) ?>
                            </span>
                            <div class="text-start pe-1">
                                <span class="d-block fw-semibold text-white" style="font-size: 0.85rem; max-width: 160px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?= e($user['nama']) ?>
                                </span>
                                <span class="badge bg-light text-dark text-capitalize" style="font-size: 0.65rem;">
                                    <?= e(str_replace('_', ' ', $user['role'])) ?><?= $user['npm'] ? ' (' . e($user['npm']) . ')' : '' ?>
                                </span>
                            </div>
                            <i class="bi bi-chevron-down user-menu-caret" aria-hidden="true"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" aria-labelledby="userMenu">
                            <li><h6 class="dropdown-header">Akun Terautentikasi</h6></li>
                            <li><span class="dropdown-item-text small text-muted"><?= e($user['email']) ?></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php if ($user['role'] === 'dosen'): ?>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/dosen/profil.php"><i class="bi bi-person me-2"></i>Edit Profil Dosen</a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/ganti-password.php"><i class="bi bi-key me-2"></i>Ganti Kata Sandi</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php" data-confirm="Apakah Anda yakin ingin keluar dari sistem?">
                                    <i class="bi bi-box-arrow-right me-2"></i>Keluar (Logout)
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="btn btn-outline-light btn-sm px-3" href="<?= BASE_URL ?>/login.php">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Masuk
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main class="<?= $pageClass === 'login-page' ? 'login-main' : 'py-4' ?>">
    <?php if ($pageClass !== 'login-page'): ?>
    <div class="container">
        <!-- Render Flash Message Otomatis -->
        <?= display_flash() ?>
    <?php else: ?>
        <div class="container login-flash">
            <?= display_flash() ?>
        </div>
    <?php endif; ?>
