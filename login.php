<?php
// ==============================================================
// S-LECTURA - Politeknik Negeri Lampung
// Halaman Masuk (Login) Multi-Peran
// File: login.php
// ==============================================================

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

// Jika sudah login, alihkan ke dashboard masing-masing
if (is_logged_in()) {
    $user = current_user();
    match ($user['role']) {
        'admin'     => redirect(BASE_URL . '/admin/index.php'),
        'admin_jurusan' => redirect(BASE_URL . '/admin/mahasiswa.php'),
        'dosen'     => redirect(BASE_URL . '/dosen/index.php'),
        'mahasiswa' => redirect(BASE_URL . '/mahasiswa/index.php'),
        default     => redirect(BASE_URL . '/index.php'),
    };
}

$errors = [];
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Sesi form tidak valid atau kedaluwarsa. Silakan muat ulang halaman.';
    } else {
        $identifier = trim($_POST['identifier'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($identifier)) {
            $errors[] = 'Email atau NPM wajib diisi.';
        }
        if (empty($password)) {
            $errors[] = 'Kata sandi wajib diisi.';
        }

        if (empty($errors)) {
            $pdo = get_db_connection();
            // Cari pengguna berdasarkan email, atau NPM (lewat tabel mahasiswa untuk mahasiswa,
            // atau kolom email untuk dosen/admin)
            $stmt = $pdo->prepare(
                "SELECT u.* FROM users u WHERE u.email = :email
                 UNION
                 SELECT u.* FROM users u
                 JOIN mahasiswa m ON m.user_id = u.id
                 WHERE m.npm = :npm
                 LIMIT 1"
            );
            $stmt->execute([':email' => $identifier, ':npm' => $identifier]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Ambil npm dari tabel mahasiswa jika role mahasiswa
                if ($user['role'] === 'mahasiswa') {
                    $stmtNpm = $pdo->prepare("SELECT npm FROM mahasiswa WHERE user_id = :uid LIMIT 1");
                    $stmtNpm->execute([':uid' => $user['id']]);
                    $mhsRow = $stmtNpm->fetch();
                    $user['npm'] = $mhsRow ? $mhsRow['npm'] : null;
                }

                login_user($user);

                // Cek apakah perlu ganti password
                if (!empty($user['must_change_password']) && (int)$user['must_change_password'] === 1) {
                    set_flash('warning', 'Selamat datang! Anda wajib mengganti password sebelum melanjutkan.');
                    redirect(BASE_URL . '/ganti-password.php');
                }

                set_flash('success', 'Selamat datang kembali, ' . $user['nama'] . '!');

                // Alihkan ke halaman tujuan berdasarkan peran
                match ($user['role']) {
                    'admin'     => redirect(BASE_URL . '/admin/index.php'),
                    'admin_jurusan' => redirect(BASE_URL . '/admin/mahasiswa.php'),
                    'dosen'     => redirect(BASE_URL . '/dosen/index.php'),
                    'mahasiswa' => redirect(BASE_URL . '/mahasiswa/index.php'),
                    default     => redirect(BASE_URL . '/index.php'),
                };
            } else {
                $errors[] = 'Kredensial tidak valid. Silakan periksa kembali Email/NPM dan kata sandi Anda.';
            }
        }
    }
}

$page_title = 'Masuk ke Sistem';
$page_class = 'login-page';
require_once __DIR__ . '/includes/header.php';
?>

<div id="loginCampusCarousel" class="carousel slide login-carousel" data-bs-ride="carousel" data-bs-interval="6000" data-bs-pause="false" aria-label="Foto kampus Politeknik Negeri Lampung">
    <div class="carousel-inner h-100">
        <div class="carousel-item active h-100">
            <img src="<?= BASE_URL ?>/assets/img/dosen/POLINELA.jpg" class="d-block w-100 h-100" alt="">
        </div>
        <div class="carousel-item h-100">
            <img src="<?= BASE_URL ?>/assets/img/dosen/polinela2.jpg" class="d-block w-100 h-100" alt="">
        </div>
    </div>
    <div class="login-carousel-shade"></div>
    <div class="carousel-indicators">
        <button type="button" data-bs-target="#loginCampusCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Foto kampus 1"></button>
        <button type="button" data-bs-target="#loginCampusCarousel" data-bs-slide-to="1" aria-label="Foto kampus 2"></button>
    </div>
</div>

<div class="container login-content">
    <div class="row align-items-center justify-content-center g-4 g-lg-5">
        <div class="col-lg-6 d-none d-lg-block">
            <div class="login-intro text-white">
                <span class="login-eyebrow"><i class="bi bi-mortarboard-fill me-2"></i>Politeknik Negeri Lampung</span>
                <h1 class="display-5 fw-bold mt-4 mb-3">Temukan waktu yang tepat untuk bertemu dosen.</h1>
                <p class="lead mb-0">Lihat jadwal, ketersediaan, dan ajukan pertemuan dengan mudah melalui S-LECTURA.</p>
            </div>
        </div>
        <div class="col-sm-10 col-md-8 col-lg-5 col-xl-4">
            <div class="card login-card border-0">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <img class="login-brand-logo" src="<?= BASE_URL ?>/assets/img/branding/Logo-Polinela-New-Standart.png" alt="Logo Politeknik Negeri Lampung">
                        <div class="login-brand-name">S-LECTURA</div>
                        <h2 class="h4 fw-bold text-dark mt-4 mb-1">Selamat datang</h2>
                        <p class="text-muted small mb-0">Masuk ke akun S-LECTURA Anda</p>
                    </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger shadow-sm py-2">
                        <ul class="mb-0 ps-3 small">
                            <?php foreach ($errors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= BASE_URL ?>/login.php" novalidate>
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="identifier" class="form-label small fw-semibold text-secondary">
                            <i class="bi bi-person me-1"></i>Email atau NPM
                        </label>
                        <input type="text" class="form-control py-2" id="identifier" name="identifier"
                               value="<?= e($identifier) ?>" placeholder="contoh: dosen@example.test / DEMO0001" required autofocus>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label small fw-semibold text-secondary">
                            <i class="bi bi-lock me-1"></i>Kata Sandi
                        </label>
                        <input type="password" class="form-control py-2" id="password" name="password"
                               placeholder="Masukkan kata sandi akun" required>
                    </div>

                    <button type="submit" class="btn btn-polinela w-100 py-2 fs-6 mb-1">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Masuk Sekarang
                    </button>
                </form>

                <div class="text-center pt-3 border-top mt-4">
                    <p class="small text-muted mb-0">
                        <i class="bi bi-shield-check text-success me-1"></i>
                        Akun Mahasiswa dan Dosen dikelola langsung oleh Akademik POLINELA.
                    </p>
                </div>
            </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
