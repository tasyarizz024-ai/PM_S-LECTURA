<?php
// Lecturer appointment management and private activity history.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/meetings.php';
require_once __DIR__ . '/../includes/notifications.php';

require_role('dosen');

$pdo = get_db_connection();
$user = current_user();
$stmtLecturer = $pdo->prepare("SELECT id FROM dosen WHERE user_id = :user_id LIMIT 1");
$stmtLecturer->execute([':user_id' => $user['id']]);
$lecturerId = (int)$stmtLecturer->fetchColumn();
if ($lecturerId <= 0) {
    set_flash('danger', 'Profil dosen tidak ditemukan.');
    redirect(BASE_URL . '/dosen/index.php');
}
$_SESSION['dosen_id'] = $lecturerId;

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Sesi form tidak valid atau kedaluwarsa. Muat ulang halaman dan coba lagi.';
    } else {
        $meetingId = (int)($_POST['id'] ?? 0);
        $action = $_POST['action'] ?? '';
        $stmtMeeting = $pdo->prepare(
            "SELECT pp.*, m.user_id AS mahasiswa_user_id
             FROM permintaan_pertemuan pp
             JOIN mahasiswa m ON m.id = pp.id_mahasiswa
             WHERE pp.id = :id AND pp.id_dosen = :dosen LIMIT 1"
        );
        $stmtMeeting->execute([':id' => $meetingId, ':dosen' => $lecturerId]);
        $meeting = $stmtMeeting->fetch();

        if (!$meeting) {
            $errors[] = 'Permintaan pertemuan tidak ditemukan.';
        } elseif ($action === 'approve' && $meeting['status'] === 'menunggu') {
            if (!meeting_is_future($meeting['tanggal'], substr($meeting['jam_mulai'], 0, 5))) {
                $errors[] = 'Waktu permintaan sudah lewat.';
            } else {
                $pdo->beginTransaction();
                $pdo->prepare("SELECT id FROM dosen WHERE id = :id FOR UPDATE")->execute([':id' => $lecturerId]);
                if (meeting_has_conflict($pdo, $lecturerId, $meeting['tanggal'], $meeting['jam_mulai'], $meeting['jam_selesai'], $meetingId)) {
                    $pdo->rollBack();
                    $errors[] = 'Jadwal tersebut berbenturan dengan jadwal praktik atau pertemuan lain yang sudah disetujui.';
                } else {
                    $stmtUpdate = $pdo->prepare(
                        "UPDATE permintaan_pertemuan SET status = 'disetujui'
                         WHERE id = :id AND id_dosen = :dosen AND status = 'menunggu'"
                    );
                    $stmtUpdate->execute([':id' => $meetingId, ':dosen' => $lecturerId]);
                    if ($stmtUpdate->rowCount() === 1) {
                        create_notification($pdo, (int)$meeting['mahasiswa_user_id'], 'Status janji temu Anda berubah. Periksa detailnya.', '/mahasiswa/pertemuan.php');
                        $pdo->commit();
                        set_flash('success', 'Permintaan pertemuan disetujui.');
                        redirect(BASE_URL . '/dosen/pertemuan.php');
                    }
                    $pdo->rollBack();
                    $errors[] = 'Status permintaan berubah. Muat ulang halaman sebelum mencoba lagi.';
                }
            }
        } elseif ($action === 'reject' && in_array($meeting['status'], ['menunggu', 'usul_jadwal'], true)) {
            $stmtUpdate = $pdo->prepare(
                "UPDATE permintaan_pertemuan SET status = 'ditolak',
                    usul_tanggal = NULL, usul_jam_mulai = NULL, usul_jam_selesai = NULL
                 WHERE id = :id AND id_dosen = :dosen AND status IN ('menunggu', 'usul_jadwal')"
            );
            $stmtUpdate->execute([':id' => $meetingId, ':dosen' => $lecturerId]);
            if ($stmtUpdate->rowCount() === 1) {
                create_notification($pdo, (int)$meeting['mahasiswa_user_id'], 'Status janji temu Anda berubah. Periksa detailnya.', '/mahasiswa/pertemuan.php');
                set_flash('success', 'Permintaan pertemuan ditolak.');
                redirect(BASE_URL . '/dosen/pertemuan.php');
            }
            $errors[] = 'Status permintaan berubah. Muat ulang halaman sebelum mencoba lagi.';
        } elseif ($action === 'complete' && $meeting['status'] === 'disetujui') {
            if (meeting_is_future($meeting['tanggal'], substr($meeting['jam_selesai'], 0, 5))) {
                $errors[] = 'Pertemuan hanya dapat ditandai selesai setelah waktu pertemuannya.';
            } else {
                $stmtUpdate = $pdo->prepare(
                    "UPDATE permintaan_pertemuan SET status = 'selesai'
                     WHERE id = :id AND id_dosen = :dosen AND status = 'disetujui'"
                );
                $stmtUpdate->execute([':id' => $meetingId, ':dosen' => $lecturerId]);
                if ($stmtUpdate->rowCount() === 1) {
                    create_notification($pdo, (int)$meeting['mahasiswa_user_id'], 'Status janji temu Anda berubah. Periksa detailnya.', '/mahasiswa/pertemuan.php');
                    set_flash('success', 'Pertemuan ditandai selesai.');
                    redirect(BASE_URL . '/dosen/pertemuan.php');
                }
                $errors[] = 'Status pertemuan berubah. Muat ulang halaman sebelum mencoba lagi.';
            }
        } elseif ($action === 'propose' && $meeting['status'] === 'menunggu') {
            $newDate = trim($_POST['usul_tanggal'] ?? '');
            $newStart = trim($_POST['usul_jam_mulai'] ?? '');
            $newEnd = trim($_POST['usul_jam_selesai'] ?? '');
            if (!meeting_is_valid_time_range($newStart, $newEnd)) {
                $errors[] = 'Jam selesai harus setelah jam mulai dan menggunakan waktu yang valid.';
            } elseif (!meeting_is_future($newDate, $newStart)) {
                $errors[] = 'Pilih tanggal dan jam usulan yang masih akan datang.';
            } elseif (meeting_has_conflict($pdo, $lecturerId, $newDate, $newStart, $newEnd, $meetingId)) {
                $errors[] = 'Waktu usulan bertabrakan dengan jadwal praktik atau pertemuan lain yang sudah disetujui.';
            } else {
                $stmtUpdate = $pdo->prepare(
                    "UPDATE permintaan_pertemuan
                     SET status = 'usul_jadwal', usul_tanggal = :tanggal,
                         usul_jam_mulai = :mulai, usul_jam_selesai = :selesai
                     WHERE id = :id AND id_dosen = :dosen AND status = 'menunggu'"
                );
                $stmtUpdate->execute([
                    ':tanggal' => $newDate,
                    ':mulai' => $newStart,
                    ':selesai' => $newEnd,
                    ':id' => $meetingId,
                    ':dosen' => $lecturerId,
                ]);
                if ($stmtUpdate->rowCount() === 1) {
                    create_notification($pdo, (int)$meeting['mahasiswa_user_id'], 'Dosen mengusulkan waktu pertemuan baru. Silakan periksa dan konfirmasi.', '/mahasiswa/pertemuan.php');
                    set_flash('success', 'Usulan waktu dikirim kepada mahasiswa untuk dikonfirmasi.');
                    redirect(BASE_URL . '/dosen/pertemuan.php');
                }
                $errors[] = 'Status permintaan berubah. Muat ulang halaman sebelum mencoba lagi.';
            }
        } else {
            $errors[] = 'Aksi tidak dapat dilakukan pada status permintaan ini.';
        }
    }
}

$stmtMeetings = $pdo->prepare(
    "SELECT pp.*, u.nama AS nama_mahasiswa, m.npm
     FROM permintaan_pertemuan pp
     JOIN mahasiswa m ON m.id = pp.id_mahasiswa
     JOIN users u ON u.id = m.user_id
     WHERE pp.id_dosen = :dosen
     ORDER BY FIELD(pp.status, 'menunggu', 'usul_jadwal', 'disetujui', 'selesai', 'ditolak', 'dibatalkan'),
              pp.tanggal ASC, pp.jam_mulai ASC"
);
$stmtMeetings->execute([':dosen' => $lecturerId]);
$meetings = $stmtMeetings->fetchAll();

$stmtSummary = $pdo->prepare(
    "SELECT semester, tahun_ajaran, COUNT(*) AS permintaan,
            SUM(status = 'selesai') AS terlaksana,
            COUNT(DISTINCT CASE
                WHEN status = 'selesai' AND jenis IN ('konsultasi', 'bimbingan')
                THEN id_mahasiswa END) AS mahasiswa_unik_formal,
            SUM(CASE WHEN jenis = 'konsultasi' AND status = 'selesai' THEN 1 ELSE 0 END) AS konsultasi_selesai,
            SUM(CASE WHEN jenis = 'bimbingan' AND status = 'selesai' THEN 1 ELSE 0 END) AS bimbingan_selesai,
            SUM(CASE WHEN jenis = 'pribadi' AND status = 'selesai' THEN 1 ELSE 0 END) AS pribadi_selesai
     FROM permintaan_pertemuan
     WHERE id_dosen = :dosen
     GROUP BY semester, tahun_ajaran
     ORDER BY tahun_ajaran DESC, semester DESC"
);
$stmtSummary->execute([':dosen' => $lecturerId]);
$summaries = $stmtSummary->fetchAll();

$statusLabels = [
    'menunggu' => 'Menunggu',
    'disetujui' => 'Disetujui',
    'usul_jadwal' => 'Menunggu konfirmasi mahasiswa',
    'ditolak' => 'Ditolak',
    'dibatalkan' => 'Dibatalkan',
    'selesai' => 'Selesai',
];
$typeLabels = [
    'konsultasi' => 'Konsultasi akademik',
    'bimbingan' => 'Bimbingan umum',
    'pribadi' => 'Konsultasi pribadi',
];

$page_title = 'Permintaan Pertemuan';
$active_page = 'pertemuan';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <h3 class="fw-bold mb-1"><i class="bi bi-calendar2-heart me-2 text-success"></i>Permintaan Pertemuan</h3>
    <p class="text-secondary mb-0">Kelola janji mahasiswa, tindak lanjuti usulan jadwal, dan lihat rekap per semester.</p>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card card-custom border-0 shadow-sm mb-4">
    <div class="card-header card-header-polinela fw-bold">Rekap Aktivitas Pertemuan per Semester</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th class="ps-4">Periode kelas saat pengajuan</th><th>Permintaan</th><th>Terlaksana</th><th>Mahasiswa unik formal</th><th>Konsultasi selesai</th><th>Bimbingan umum selesai</th><th>Pribadi selesai</th></tr></thead>
            <tbody>
            <?php if (!$summaries): ?>
                <tr><td colspan="7" class="text-center text-muted p-4">Belum ada data pertemuan.</td></tr>
            <?php else: foreach ($summaries as $summary): ?>
                <tr>
                    <td class="ps-4">Sem. <?= e($summary['semester'] ?? '-') ?> — <?= e($summary['tahun_ajaran'] ?? 'Periode belum tercatat') ?></td>
                    <td><?= (int)$summary['permintaan'] ?></td><td><?= (int)$summary['terlaksana'] ?></td>
                    <td><?= (int)$summary['mahasiswa_unik_formal'] ?></td><td><?= (int)$summary['konsultasi_selesai'] ?></td>
                    <td><?= (int)$summary['bimbingan_selesai'] ?></td><td><?= (int)$summary['pribadi_selesai'] ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card card-custom border-0 shadow-sm">
    <div class="card-header card-header-polinela fw-bold">Riwayat dan Permintaan Mahasiswa</div>
    <div class="card-body p-0">
        <?php if (!$meetings): ?>
            <div class="text-center text-muted p-5">Belum ada permintaan pertemuan.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th class="ps-4">Mahasiswa</th><th>Jenis / Tujuan</th><th>Jadwal</th><th>Status</th><th class="text-end pe-4">Tindakan</th></tr></thead>
                    <tbody>
                    <?php foreach ($meetings as $meeting): ?>
                        <tr>
                            <td class="ps-4"><strong><?= e($meeting['nama_mahasiswa']) ?></strong><small class="d-block text-muted"><?= e($meeting['npm']) ?></small></td>
                            <td><?= e($typeLabels[$meeting['jenis']]) ?><small class="d-block text-muted"><?= $meeting['jenis'] === 'pribadi' ? 'Tujuan tidak dicatat' : e($meeting['alasan'] ?? '-') ?></small></td>
                            <td><?= e(date('d/m/Y', strtotime($meeting['tanggal']))) ?><small class="d-block text-muted"><?= e(substr($meeting['jam_mulai'], 0, 5)) ?>–<?= e(substr($meeting['jam_selesai'], 0, 5)) ?></small>
                                <?php if ($meeting['status'] === 'usul_jadwal'): ?>
                                    <small class="d-block text-primary">Usulan: <?= e(date('d/m/Y', strtotime($meeting['usul_tanggal']))) ?>, <?= e(substr($meeting['usul_jam_mulai'], 0, 5)) ?>–<?= e(substr($meeting['usul_jam_selesai'], 0, 5)) ?></small>
                                    <?php if (meeting_has_theory_overlap($pdo, $lecturerId, $meeting['usul_tanggal'], $meeting['usul_jam_mulai'], $meeting['usul_jam_selesai'])): ?>
                                        <small class="d-block text-warning-emphasis"><i class="bi bi-exclamation-triangle me-1"></i>Usulan beririsan dengan jadwal teori.</small>
                                    <?php endif; ?>
                                <?php elseif (in_array($meeting['status'], ['menunggu', 'disetujui'], true) && meeting_has_theory_overlap($pdo, $lecturerId, $meeting['tanggal'], $meeting['jam_mulai'], $meeting['jam_selesai'])): ?>
                                    <small class="d-block text-warning-emphasis"><i class="bi bi-exclamation-triangle me-1"></i>Beririsan dengan jadwal teori; pastikan ketersediaan aktual.</small>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?= $meeting['status'] === 'selesai' ? 'bg-success' : (in_array($meeting['status'], ['ditolak', 'dibatalkan'], true) ? 'bg-secondary' : 'bg-warning text-dark') ?>"><?= e($statusLabels[$meeting['status']]) ?></span></td>
                            <td class="text-end pe-4">
                                <?php if ($meeting['status'] === 'menunggu'): ?>
                                    <form method="POST" class="d-inline">
                                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$meeting['id'] ?>"><input type="hidden" name="action" value="approve">
                                        <button class="btn btn-sm btn-success" type="submit">Setujui</button>
                                    </form>
                                    <form method="POST" class="d-inline">
                                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$meeting['id'] ?>"><input type="hidden" name="action" value="reject">
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Tolak</button>
                                    </form>
                                    <details class="mt-2 text-start">
                                        <summary class="small text-primary">Usulkan waktu lain</summary>
                                        <form method="POST" class="mt-2">
                                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$meeting['id'] ?>"><input type="hidden" name="action" value="propose">
                                            <input class="form-control form-control-sm mb-2" type="date" name="usul_tanggal" min="<?= meeting_min_date() ?>" required>
                                            <div class="row g-2 mb-2">
                                                <div class="col-6"><input class="form-control form-control-sm" type="time" name="usul_jam_mulai" aria-label="Jam mulai usulan" required></div>
                                                <div class="col-6"><input class="form-control form-control-sm" type="time" name="usul_jam_selesai" aria-label="Jam selesai usulan" required></div>
                                            </div>
                                            <button class="btn btn-sm btn-outline-primary" type="submit">Kirim Usulan</button>
                                        </form>
                                    </details>
                                <?php elseif ($meeting['status'] === 'disetujui'): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Tandai pertemuan ini selesai?')">
                                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$meeting['id'] ?>"><input type="hidden" name="action" value="complete">
                                        <button class="btn btn-sm btn-outline-success" type="submit">Tandai selesai</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
