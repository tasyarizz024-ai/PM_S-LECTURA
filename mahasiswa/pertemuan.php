<?php
// Student appointment requests and history.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/meetings.php';
require_once __DIR__ . '/../includes/notifications.php';

require_role('mahasiswa');

$pdo = get_db_connection();
$user = current_user();
$stmtStudent = $pdo->prepare("SELECT id FROM mahasiswa WHERE user_id = :user_id LIMIT 1");
$stmtStudent->execute([':user_id' => $user['id']]);
$studentId = (int)$stmtStudent->fetchColumn();
if ($studentId <= 0) {
    set_flash('danger', 'Profil mahasiswa belum tersedia.');
    redirect(BASE_URL . '/mahasiswa/index.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Sesi form tidak valid atau kedaluwarsa. Muat ulang halaman dan coba lagi.';
    } else {
        $meetingId = (int)($_POST['id'] ?? 0);
        $action = $_POST['action'] ?? '';
        $stmtMeeting = $pdo->prepare(
            "SELECT * FROM permintaan_pertemuan WHERE id = :id AND id_mahasiswa = :mahasiswa LIMIT 1"
        );
        $stmtMeeting->execute([':id' => $meetingId, ':mahasiswa' => $studentId]);
        $meeting = $stmtMeeting->fetch();

        if (!$meeting) {
            $errors[] = 'Permintaan pertemuan tidak ditemukan.';
        } elseif ($action === 'cancel' && in_array($meeting['status'], ['menunggu', 'disetujui', 'usul_jadwal'], true)) {
            $stmtUpdate = $pdo->prepare(
                "UPDATE permintaan_pertemuan
                 SET status = 'dibatalkan', usul_tanggal = NULL, usul_jam_mulai = NULL, usul_jam_selesai = NULL
                 WHERE id = :id AND id_mahasiswa = :mahasiswa
                   AND status IN ('menunggu', 'disetujui', 'usul_jadwal')"
            );
            $stmtUpdate->execute([':id' => $meetingId, ':mahasiswa' => $studentId]);
            if ($stmtUpdate->rowCount() === 1) {
                $stmtLecturerUser = $pdo->prepare("SELECT user_id FROM dosen WHERE id = :id");
                $stmtLecturerUser->execute([':id' => $meeting['id_dosen']]);
                create_notification(
                    $pdo,
                    (int)$stmtLecturerUser->fetchColumn(),
                    'Mahasiswa membatalkan permintaan pertemuan.',
                    '/dosen/pertemuan.php'
                );
                set_flash('success', 'Permintaan pertemuan dibatalkan.');
                redirect(BASE_URL . '/mahasiswa/pertemuan.php');
            }
            $errors[] = 'Status permintaan berubah. Muat ulang halaman sebelum mencoba lagi.';
        } elseif ($action === 'confirm' && $meeting['status'] === 'usul_jadwal') {
            $newDate = $meeting['usul_tanggal'];
            $newStart = substr($meeting['usul_jam_mulai'], 0, 5);
            $newEnd = substr($meeting['usul_jam_selesai'], 0, 5);
            if (!meeting_is_future($newDate, $newStart)) {
                $errors[] = 'Waktu usulan dosen sudah lewat. Hubungi dosen untuk pengajuan waktu baru.';
            } else {
                $pdo->beginTransaction();
                $pdo->prepare("SELECT id FROM dosen WHERE id = :id FOR UPDATE")->execute([':id' => $meeting['id_dosen']]);
                if (meeting_has_conflict($pdo, (int)$meeting['id_dosen'], $newDate, $meeting['usul_jam_mulai'], $meeting['usul_jam_selesai'], $meetingId)) {
                    $pdo->rollBack();
                    $errors[] = 'Waktu usulan sudah bertabrakan dengan jadwal praktik atau pertemuan lain yang disetujui. Minta dosen mengusulkan waktu lain.';
                } else {
                    $stmtUpdate = $pdo->prepare(
                        "UPDATE permintaan_pertemuan
                         SET tanggal = usul_tanggal, jam_mulai = usul_jam_mulai, jam_selesai = usul_jam_selesai,
                             status = 'disetujui', usul_tanggal = NULL, usul_jam_mulai = NULL, usul_jam_selesai = NULL
                         WHERE id = :id AND id_mahasiswa = :mahasiswa AND status = 'usul_jadwal'"
                    );
                    $stmtUpdate->execute([':id' => $meetingId, ':mahasiswa' => $studentId]);
                    if ($stmtUpdate->rowCount() === 1) {
                        $stmtLecturerUser = $pdo->prepare("SELECT user_id FROM dosen WHERE id = :id");
                        $stmtLecturerUser->execute([':id' => $meeting['id_dosen']]);
                        create_notification(
                            $pdo,
                            (int)$stmtLecturerUser->fetchColumn(),
                            'Mahasiswa mengonfirmasi usulan waktu pertemuan.',
                            '/dosen/pertemuan.php'
                        );
                        $pdo->commit();
                        set_flash('success', 'Waktu usulan dosen dikonfirmasi.');
                        redirect(BASE_URL . '/mahasiswa/pertemuan.php');
                    }
                    $pdo->rollBack();
                    $errors[] = 'Status usulan berubah. Muat ulang halaman sebelum mencoba lagi.';
                }
            }
        } else {
            $errors[] = 'Aksi tidak dapat dilakukan pada status permintaan ini.';
        }
    }
}

$stmtMeetings = $pdo->prepare(
    "SELECT pp.*, u.nama AS nama_dosen, p.nama_prodi
     FROM permintaan_pertemuan pp
     JOIN dosen d ON d.id = pp.id_dosen
     JOIN users u ON u.id = d.user_id
     JOIN program_studi p ON p.id = d.id_prodi
     WHERE pp.id_mahasiswa = :mahasiswa
     ORDER BY pp.tanggal DESC, pp.jam_mulai DESC, pp.created_at DESC"
);
$stmtMeetings->execute([':mahasiswa' => $studentId]);
$meetings = $stmtMeetings->fetchAll();

$statusLabels = [
    'menunggu' => 'Menunggu persetujuan',
    'disetujui' => 'Disetujui',
    'usul_jadwal' => 'Menunggu konfirmasi waktu',
    'ditolak' => 'Ditolak',
    'dibatalkan' => 'Dibatalkan',
    'selesai' => 'Selesai',
];
$typeLabels = [
    'konsultasi' => 'Konsultasi akademik',
    'bimbingan' => 'Bimbingan umum',
    'pribadi' => 'Konsultasi pribadi',
];

$page_title = 'Janji Temu Saya';
$active_page = 'pertemuan';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-calendar2-heart me-2 text-success"></i>Janji Temu Saya</h3>
        <p class="text-secondary mb-0">Lihat status, konfirmasi usulan waktu dosen, atau batalkan permintaan.</p>
    </div>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card card-custom border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (!$meetings): ?>
            <div class="text-center text-muted p-5">Belum ada permintaan pertemuan. Buka detail dosen untuk mengajukan janji.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th class="ps-4">Dosen</th><th>Jenis</th><th>Jadwal</th><th>Status</th><th>Tujuan</th><th class="text-end pe-4">Aksi</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($meetings as $meeting): ?>
                        <tr>
                            <td class="ps-4"><strong><?= e($meeting['nama_dosen']) ?></strong><small class="d-block text-muted"><?= e($meeting['nama_prodi']) ?></small></td>
                            <td><?= e($typeLabels[$meeting['jenis']]) ?></td>
                            <td><?= e(date('d/m/Y', strtotime($meeting['tanggal']))) ?><small class="d-block text-muted"><?= e(substr($meeting['jam_mulai'], 0, 5)) ?>–<?= e(substr($meeting['jam_selesai'], 0, 5)) ?></small>
                                <?php if ($meeting['status'] === 'usul_jadwal'): ?>
                                    <span class="d-block text-primary small mt-1">Usulan: <?= e(date('d/m/Y', strtotime($meeting['usul_tanggal']))) ?>, <?= e(substr($meeting['usul_jam_mulai'], 0, 5)) ?>–<?= e(substr($meeting['usul_jam_selesai'], 0, 5)) ?></span>
                                    <?php if (meeting_has_theory_overlap($pdo, (int)$meeting['id_dosen'], $meeting['usul_tanggal'], $meeting['usul_jam_mulai'], $meeting['usul_jam_selesai'])): ?>
                                        <span class="d-block text-warning small mt-1"><i class="bi bi-exclamation-triangle me-1"></i>Usulan beririsan dengan jadwal teori.</span>
                                    <?php endif; ?>
                                <?php elseif (in_array($meeting['status'], ['menunggu', 'disetujui'], true) && meeting_has_theory_overlap($pdo, (int)$meeting['id_dosen'], $meeting['tanggal'], $meeting['jam_mulai'], $meeting['jam_selesai'])): ?>
                                    <span class="d-block text-warning small mt-1"><i class="bi bi-exclamation-triangle me-1"></i>Waktu beririsan dengan jadwal teori; menunggu pengecekan dosen.</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?= $meeting['status'] === 'selesai' ? 'bg-success' : ($meeting['status'] === 'ditolak' || $meeting['status'] === 'dibatalkan' ? 'bg-secondary' : 'bg-warning text-dark') ?>"><?= e($statusLabels[$meeting['status']]) ?></span></td>
                            <td><?= $meeting['jenis'] === 'pribadi' ? '<span class="text-muted">Tidak dicatat</span>' : e($meeting['alasan'] ?? '-') ?></td>
                            <td class="text-end pe-4">
                                <?php if ($meeting['status'] === 'usul_jadwal'): ?>
                                    <form method="POST" class="d-inline">
                                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$meeting['id'] ?>"><input type="hidden" name="action" value="confirm">
                                        <button class="btn btn-sm btn-success" type="submit">Konfirmasi waktu</button>
                                    </form>
                                <?php endif; ?>
                                <?php if (in_array($meeting['status'], ['menunggu', 'disetujui', 'usul_jadwal'], true)): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Batalkan permintaan ini?')">
                                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$meeting['id'] ?>"><input type="hidden" name="action" value="cancel">
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Batalkan</button>
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
