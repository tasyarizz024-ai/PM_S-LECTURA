<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

$pdo = get_db_connection();
$user = current_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Sesi form tidak valid. Muat ulang halaman dan coba lagi.';
    } else {
        $notificationId = (int)($_POST['id'] ?? 0);
        $action = $_POST['action'] ?? '';
        if ($action === 'read_all') {
            $stmt = $pdo->prepare(
                "UPDATE notifikasi SET dibaca_at = NOW() WHERE user_id = :user_id AND dibaca_at IS NULL"
            );
            $stmt->execute([':user_id' => $user['id']]);
        } elseif ($action === 'read' && $notificationId > 0) {
            $stmt = $pdo->prepare(
                "UPDATE notifikasi SET dibaca_at = NOW()
                 WHERE id = :id AND user_id = :user_id AND dibaca_at IS NULL"
            );
            $stmt->execute([':id' => $notificationId, ':user_id' => $user['id']]);
        } else {
            $errors[] = 'Aksi notifikasi tidak valid.';
        }
        if (!$errors) {
            redirect(BASE_URL . '/notifikasi.php');
        }
    }
}

$stmt = $pdo->prepare(
    "SELECT id, pesan, tautan, created_at, dibaca_at
     FROM notifikasi WHERE user_id = :user_id
     ORDER BY created_at DESC LIMIT 100"
);
$stmt->execute([':user_id' => $user['id']]);
$notifications = $stmt->fetchAll();

$page_title = 'Notifikasi';
$active_page = 'notifikasi';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-bell me-2 text-success"></i>Notifikasi</h3>
        <p class="text-secondary mb-0">Pemberitahuan perubahan janji temu dan aktivitas akun.</p>
    </div>
    <?php if ($notifications && count(array_filter($notifications, static fn($n) => $n['dibaca_at'] === null))): ?>
        <form method="POST">
            <?= csrf_field() ?><input type="hidden" name="action" value="read_all">
            <button type="submit" class="btn btn-outline-success btn-sm">Tandai semua dibaca</button>
        </form>
    <?php endif; ?>
</div>

<?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<div class="list-group shadow-sm">
    <?php if (!$notifications): ?>
        <div class="list-group-item text-center text-muted p-5">Belum ada notifikasi.</div>
    <?php else: foreach ($notifications as $notification): ?>
        <div class="list-group-item <?= $notification['dibaca_at'] === null ? 'list-group-item-success' : '' ?>">
            <div class="d-flex justify-content-between align-items-start gap-3">
                <div>
                    <a class="fw-semibold text-dark text-decoration-none" href="<?= BASE_URL . e($notification['tautan']) ?>">
                        <?= e($notification['pesan']) ?>
                    </a>
                    <small class="d-block text-muted mt-1"><?= e(format_datetime($notification['created_at'])) ?></small>
                </div>
                <?php if ($notification['dibaca_at'] === null): ?>
                    <form method="POST">
                        <?= csrf_field() ?><input type="hidden" name="action" value="read">
                        <input type="hidden" name="id" value="<?= (int)$notification['id'] ?>">
                        <button class="btn btn-sm btn-outline-secondary" type="submit">Tandai dibaca</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
