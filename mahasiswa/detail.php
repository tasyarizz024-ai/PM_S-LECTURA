<?php
// ==============================================================
// S-LECTURA - Politeknik Negeri Lampung
// Halaman Detail Ketersediaan, Jadwal Mengajar, & Slot Dosen
// File: mahasiswa/detail.php
// ==============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/meetings.php';
require_once __DIR__ . '/../includes/notifications.php';

require_role('mahasiswa');

$pdo = get_db_connection();
$user = current_user();
$idDosen = (int)($_GET['id'] ?? 0);

if ($idDosen <= 0) {
    set_flash('danger', 'Dosen yang Anda tuju tidak valid.');
    redirect(BASE_URL . '/mahasiswa/index.php');
}

$stmtMahasiswa = $pdo->prepare(
    "SELECT m.id, k.semester, k.tahun_ajaran, p.id_jurusan
     FROM mahasiswa m
     JOIN program_studi p ON p.id = m.id_prodi
     LEFT JOIN kelas k ON k.id = m.id_kelas
     WHERE m.user_id = :user_id
     LIMIT 1"
);
$stmtMahasiswa->execute([':user_id' => $user['id']]);
$mahasiswa = $stmtMahasiswa->fetch();
if (!$mahasiswa) {
    set_flash('danger', 'Profil mahasiswa belum tersedia untuk membuka detail dosen.');
    redirect(BASE_URL . '/mahasiswa/index.php');
}

// 1. Ambil data dosen hanya jika masih dalam jurusan mahasiswa
$stmtDosen = $pdo->prepare("SELECT d.*, u.nama, u.email, p.nama_prodi, j.nama_jurusan AS jurusan,
                                   COALESCE(sk.status, 'Tersedia') AS status,
                                   sk.catatan AS status_catatan,
                                   sk.berlaku_sampai,
                                   sk.updated_at
                            FROM dosen d
                            JOIN users u ON d.user_id = u.id
                            JOIN program_studi p ON d.id_prodi = p.id
                            JOIN jurusan j ON j.id = p.id_jurusan
                            LEFT JOIN status_ketersediaan sk ON d.id = sk.id_dosen
                            WHERE d.id = :id AND p.id_jurusan = :jurusan AND u.aktif = 1 LIMIT 1");
$stmtDosen->execute([':id' => $idDosen, ':jurusan' => $mahasiswa['id_jurusan']]);
$dosen = $stmtDosen->fetch();

if (!$dosen) {
    set_flash('danger', 'Dosen tidak ditemukan atau berada di luar jurusan Anda.');
    redirect(BASE_URL . '/mahasiswa/index.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Sesi form tidak valid atau kedaluwarsa. Silakan muat ulang halaman.';
    } else {
        $jenis = trim($_POST['jenis'] ?? '');
        $tanggal = trim($_POST['tanggal'] ?? '');
        $slotId = (int)($_POST['id_slot'] ?? 0);
        $jamMulai = trim($_POST['jam_mulai'] ?? '');
        $jamSelesai = trim($_POST['jam_selesai'] ?? '');
        $alasan = trim($_POST['alasan'] ?? '');

        if (!in_array($jenis, ['konsultasi', 'bimbingan', 'pribadi'], true)) {
            $errors[] = 'Jenis pertemuan tidak valid.';
        }
        if (!meeting_is_future($tanggal, '00:00')) {
            $errors[] = 'Tanggal pertemuan harus setelah hari ini.';
        }

        if ($slotId > 0) {
            $slotType = meeting_slot_type($jenis);
            $stmtSlot = $pdo->prepare(
                "SELECT id, hari, jam_mulai, jam_selesai
                 FROM slot_konsultasi
                 WHERE id = :id AND id_dosen = :id_dosen AND jenis = :jenis AND aktif = 1
                 LIMIT 1"
            );
            $stmtSlot->execute([
                ':id' => $slotId,
                ':id_dosen' => $idDosen,
                ':jenis' => $slotType,
            ]);
            $slot = $stmtSlot->fetch();
            if (!$slot) {
                $errors[] = 'Slot aktif yang dipilih tidak sesuai dengan jenis pertemuan.';
            } elseif (meeting_weekday($tanggal) !== $slot['hari']) {
                $errors[] = 'Tanggal yang dipilih tidak sesuai dengan hari slot dosen.';
            } else {
                $jamMulai = substr($slot['jam_mulai'], 0, 5);
                $jamSelesai = substr($slot['jam_selesai'], 0, 5);
            }
        } elseif (!meeting_is_valid_time_range($jamMulai, $jamSelesai)) {
            $errors[] = 'Jam selesai harus setelah jam mulai dan menggunakan waktu yang valid.';
        }
        if (meeting_weekday($tanggal) === null) {
            $errors[] = 'Tanggal pertemuan tidak valid.';
        } elseif (!meeting_is_future($tanggal, $jamMulai)) {
            $errors[] = 'Waktu pertemuan harus setelah waktu sekarang.';
        }
        if ($jenis !== 'pribadi' && $alasan === '') {
            $errors[] = 'Tujuan singkat pertemuan wajib diisi.';
        }
        if (empty($errors) && meeting_has_conflict($pdo, $idDosen, $tanggal, $jamMulai, $jamSelesai)) {
            $errors[] = 'Waktu tersebut bertabrakan dengan jadwal praktik atau pertemuan lain yang sudah disetujui. Silakan pilih waktu lain.';
        }

        if (empty($errors)) {
            $stmtInsert = $pdo->prepare(
                "INSERT INTO permintaan_pertemuan
                    (id_mahasiswa, id_dosen, jenis, tanggal, jam_mulai, jam_selesai, alasan, semester, tahun_ajaran)
                 VALUES
                    (:mahasiswa, :dosen, :jenis, :tanggal, :mulai, :selesai, :alasan, :semester, :tahun_ajaran)"
            );
            $stmtInsert->execute([
                ':mahasiswa' => $mahasiswa['id'],
                ':dosen' => $idDosen,
                ':jenis' => $jenis,
                ':tanggal' => $tanggal,
                ':mulai' => $jamMulai,
                ':selesai' => $jamSelesai,
                ':alasan' => $jenis === 'pribadi' || $alasan === '' ? null : mb_substr($alasan, 0, 255),
                ':semester' => $mahasiswa['semester'],
                ':tahun_ajaran' => $mahasiswa['tahun_ajaran'],
            ]);
            create_notification(
                $pdo,
                (int)$dosen['user_id'],
                'Ada permintaan pertemuan baru.',
                '/dosen/pertemuan.php'
            );
            if (meeting_has_theory_overlap($pdo, $idDosen, $tanggal, $jamMulai, $jamSelesai)) {
                set_flash('warning', 'Permintaan dikirim. Jadwal tersebut beririsan dengan jadwal teori dosen; dosen akan memeriksa ketersediaannya sebelum menyetujui.');
            } else {
                set_flash('success', 'Permintaan pertemuan berhasil dikirim ke dosen.');
            }
            redirect(BASE_URL . '/mahasiswa/pertemuan.php');
        }
    }
}

$statusVal = $dosen['status'];
$isExpired = false;
if (!empty($dosen['berlaku_sampai']) && strtotime($dosen['berlaku_sampai']) < time()) {
    $isExpired = true;
}

// 2. Ambil jadwal mengajar yang dimiliki dosen
$orderHari = "FIELD(jm.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat')";
$stmtJadwal = $pdo->prepare(
    "SELECT jm.id, jm.hari, jm.jam_mulai, jm.jam_selesai, jm.tipe_sesi, jm.sks, jm.mode, jm.ruang,
            mk.kode AS kode_mk, mk.nama AS nama_mk,
            k.nama_kelas, k.semester, jm.termin,
            u2.nama AS semua_dosen
     FROM jadwal_mengajar jm
     JOIN mata_kuliah mk ON mk.id = jm.id_mk
     JOIN kelas k ON k.id = jm.id_kelas
     JOIN dosen d2 ON d2.id = jm.id_dosen
     JOIN users u2 ON u2.id = d2.user_id
     WHERE jm.id_dosen = :id_dosen
     ORDER BY jm.termin ASC, {$orderHari}, jm.jam_mulai ASC"
);
$stmtJadwal->execute([':id_dosen' => $idDosen]);
$jadwalList = $stmtJadwal->fetchAll();

// 3. Ambil Slot Konsultasi Aktif
$orderHariSlot = "FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat')";
$stmtKonsul = $pdo->prepare("SELECT * FROM slot_konsultasi WHERE id_dosen = :id AND jenis = 'konsultasi' AND aktif = 1 ORDER BY {$orderHariSlot}, jam_mulai ASC");
$stmtKonsul->execute([':id' => $idDosen]);
$konsulList = $stmtKonsul->fetchAll();

// 4. Ambil Slot Bimbingan Umum Aktif
$stmtBimbingan = $pdo->prepare("SELECT * FROM slot_konsultasi WHERE id_dosen = :id AND jenis = 'bimbingan' AND aktif = 1 ORDER BY {$orderHariSlot}, jam_mulai ASC");
$stmtBimbingan->execute([':id' => $idDosen]);
$bimbinganList = $stmtBimbingan->fetchAll();
$slotPertemuanList = array_merge($konsulList, $bimbinganList);

$page_title = 'Jadwal & Ketersediaan ' . $dosen['nama'];
$active_page = 'beranda';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Navigasi Breadcrumb & Tombol Kembali -->
<div class="row mb-3">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2 small">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/mahasiswa/index.php" class="text-success text-decoration-none"><i class="bi bi-grid-fill me-1"></i>Daftar Dosen</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= e($dosen['nama']) ?></li>
            </ol>
        </nav>
        <a href="<?= BASE_URL ?>/mahasiswa/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Kembali ke Daftar Dosen
        </a>
    </div>
</div>

<!-- Kartu Header Profil Dosen & Status Terkini -->
<div class="card card-custom border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="row align-items-center gy-4">
            <!-- Profil Foto & Biodata -->
            <div class="col-lg-7">
                <div class="d-flex align-items-center gap-3">
                    <?php if (!empty($dosen['foto']) && file_exists(__DIR__ . '/../' . $dosen['foto'])): ?>
                        <img src="<?= BASE_URL . '/' . e($dosen['foto']) ?>" alt="Foto Dosen" class="rounded-circle shadow-sm" style="width: 88px; height: 88px; object-fit: cover;">
                    <?php else: ?>
                        <div class="avatar-initial avatar-initial-lg bg-success bg-opacity-10 text-success fw-bold" style="width: 88px; height: 88px;">
                            <?= strtoupper(substr($dosen['nama'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <span class="badge bg-success-subtle text-success fw-bold px-2 py-1 mb-1">
                            <?= e($dosen['nama_prodi']) ?> &bull; <?= e($dosen['jurusan']) ?>
                        </span>
                        <h3 class="fw-bold text-dark mb-1"><?= e($dosen['nama']) ?></h3>
                        <p class="text-secondary small mb-1">
                            <i class="bi bi-person-vcard me-1"></i>NIDN / NIP: <strong class="text-dark"><?= $dosen['nidn_nip'] ? e($dosen['nidn_nip']) : '<span class="text-muted fst-italic">-</span>' ?></strong> &bull; 
                            <i class="bi bi-envelope me-1 ms-2"></i>Email: <a href="mailto:<?= e($dosen['email']) ?>" class="text-decoration-none text-dark"><?= e($dosen['email']) ?></a>
                        </p>
                        <p class="text-muted small mb-0">
                            <i class="bi bi-award me-1 text-warning"></i>Keahlian: <span class="text-dark"><?= e($dosen['bidang_keahlian']) ?></span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Panel Status Ketersediaan Terkini -->
            <div class="col-lg-5">
                <div class="p-3 bg-light rounded-3 border-start border-4 <?= match($statusVal) {
                    'Tersedia' => 'border-success',
                    'Mengajar' => 'border-primary',
                    'Hanya Konsultasi' => 'border-warning',
                    'Hanya Bimbingan' => 'border-info',
                    default => 'border-secondary'
                } ?> shadow-sm">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small fw-bold text-secondary text-uppercase">Status Ketersediaan:</span>
                        <div><?= status_badge($statusVal) ?></div>
                    </div>

                    <div class="small mb-2">
                        <strong class="text-dark d-block mb-1"><i class="bi bi-chat-left-text me-1 text-success"></i>Catatan Dosen:</strong>
                        <div class="text-secondary bg-white p-2 rounded border small">
                            <?= !empty($dosen['status_catatan']) ? nl2br(e($dosen['status_catatan'])) : '<span class="text-muted fst-italic">Tidak ada catatan khusus dari dosen.</span>' ?>
                        </div>
                    </div>

                    <div class="row g-2 small text-muted" style="font-size: 0.78rem;">
                        <div class="col-6">
                            <span>Berlaku Sampai:</span>
                            <strong class="d-block text-dark"><?= $dosen['berlaku_sampai'] ? format_datetime($dosen['berlaku_sampai']) : 'Tidak dibatasi' ?></strong>
                        </div>
                        <div class="col-6">
                            <span>Terakhir Diperbarui:</span>
                            <strong class="d-block text-dark"><?= $dosen['updated_at'] ? format_datetime($dosen['updated_at']) : '-' ?></strong>
                        </div>
                    </div>

                    <?php if ($isExpired): ?>
                        <div class="alert alert-warning py-1 px-2 small mt-2 mb-0 d-flex align-items-center" style="font-size: 0.78rem;">
                            <i class="bi bi-clock-history me-1"></i>Waktu berlaku status telah berakhir. Silakan hubungi dosen melalui email jika mendesak.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Konten Jadwal & Slot Ketersediaan Dosen -->
<div class="row g-4 mb-4">
    <!-- 1. Tabel Jadwal Mengajar Mingguan -->
    <div class="col-12">
        <div class="card card-custom border-0 shadow-sm">
            <div class="card-header card-header-polinela d-flex justify-content-between align-items-center py-3">
                <span class="fw-bold"><i class="bi bi-book-half me-2 text-primary"></i>Jadwal Mengajar Perkuliahan</span>
                <span class="badge bg-primary"><?= count($jadwalList) ?> Sesi Terdaftar</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($jadwalList)): ?>
                    <div class="text-center py-4 px-3">
                        <i class="bi bi-calendar-check fs-2 text-muted d-block mb-2"></i>
                        <p class="text-muted small mb-0">Dosen belum memiliki jadwal mengajar yang terdaftar di sistem.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4" style="width: 40px;">No</th>
                                    <th style="width: 100px;">Hari</th>
                                    <th>Termin</th>
                                    <th style="width: 160px;">Waktu</th>
                                    <th>Mata Kuliah</th>
                                    <th style="width: 100px;">Tipe</th>
                                    <th style="width: 60px;">SKS</th>
                                    <th style="width: 90px;">Mode</th>
                                    <?php if (TAMPILKAN_RUANG): ?>
                                    <th>Ruang</th>
                                    <?php endif; ?>
                                    <th>Dosen Pengampu</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; foreach ($jadwalList as $j): ?>
                                    <tr>
                                        <td class="ps-4 text-muted small"><?= $no++ ?></td>
                                        <td>
                                            <span class="badge bg-success-subtle text-success fw-bold px-2 py-1">
                                                <?= e($j['hari']) ?>
                                            </span>
                                        </td>
                                        <td><span class="badge bg-secondary-subtle text-secondary">Termin <?= e($j['termin']) ?></span></td>
                                        <td>
                                            <i class="bi bi-clock me-1 text-secondary"></i>
                                            <strong><?= substr($j['jam_mulai'], 0, 5) ?></strong>–<strong><?= substr($j['jam_selesai'], 0, 5) ?></strong> <span class="text-muted small">WIB</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-dark bg-opacity-75 font-monospace small me-1"><?= e($j['kode_mk']) ?></span>
                                            <strong class="text-dark"><?= e($j['nama_mk']) ?></strong>
                                            <div class="text-muted small"><?= e($j['nama_kelas']) ?> &bull; Sem. <?= e($j['semester']) ?></div>
                                        </td>
                                        <td><?= tipe_badge($j['tipe_sesi']) ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark border"><?= (int)$j['sks'] ?></span>
                                        </td>
                                        <td><?= mode_badge($j['mode']) ?></td>
                                        <?php if (TAMPILKAN_RUANG): ?>
                                        <td class="small text-secondary">
                                            <?= !empty($j['ruang']) ? e($j['ruang']) : '<span class="text-muted">-</span>' ?>
                                        </td>
                                        <?php endif; ?>
                                        <td class="small"><?= e($j['semua_dosen'] ?? '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 2. Slot Konsultasi Akademik -->
    <div class="col-lg-6">
        <div class="card card-custom border-0 shadow-sm h-100">
            <div class="card-header card-header-polinela d-flex justify-content-between align-items-center py-3">
                <span class="fw-bold"><i class="bi bi-chat-text-fill me-2 text-warning"></i>Slot Konsultasi Akademik</span>
                <span class="badge bg-warning text-dark"><?= count($konsulList) ?> Slot Aktif</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($konsulList)): ?>
                    <div class="text-center py-4 px-3">
                        <i class="bi bi-chat-left-dots fs-2 text-muted d-block mb-2"></i>
                        <p class="text-muted small mb-0">Saat ini tidak ada slot konsultasi akademik yang sedang aktif dibuka.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($konsulList as $k): ?>
                            <div class="list-group-item p-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="badge bg-success-subtle text-success fw-bold px-2 py-1"><?= e($k['hari']) ?></span>
                                    <span class="badge bg-light text-dark border">
                                        <i class="bi bi-clock me-1 text-success"></i><?= substr($k['jam_mulai'], 0, 5) ?> - <?= substr($k['jam_selesai'], 0, 5) ?> WIB
                                    </span>
                                </div>
                                <div class="small text-secondary mt-2">
                                    <strong class="text-dark">Petunjuk / Topik:</strong>
                                    <?= !empty($k['catatan']) ? e($k['catatan']) : '<span class="text-muted fst-italic">Konsultasi umum seputar perkuliahan dan akademik</span>' ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 3. Slot Bimbingan Umum -->
    <div class="col-lg-6">
        <div class="card card-custom border-0 shadow-sm h-100">
            <div class="card-header card-header-polinela d-flex justify-content-between align-items-center py-3">
                <span class="fw-bold"><i class="bi bi-chat-dots-fill me-2 text-info"></i>Slot Bimbingan Umum</span>
                <span class="badge bg-info text-dark"><?= count($bimbinganList) ?> Slot Aktif</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($bimbinganList)): ?>
                    <div class="text-center py-4 px-3">
                        <i class="bi bi-journal-text fs-2 text-muted d-block mb-2"></i>
                        <p class="text-muted small mb-0">Saat ini tidak ada slot bimbingan umum yang dibuka dosen.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($bimbinganList as $b): ?>
                            <div class="list-group-item p-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="badge bg-success-subtle text-success fw-bold px-2 py-1"><?= e($b['hari']) ?></span>
                                    <span class="badge bg-light text-dark border">
                                        <i class="bi bi-clock me-1 text-info"></i><?= substr($b['jam_mulai'], 0, 5) ?> - <?= substr($b['jam_selesai'], 0, 5) ?> WIB
                                    </span>
                                </div>
                                <div class="small text-secondary mt-2">
                                    <strong class="text-dark">Persyaratan / Catatan:</strong>
                                    <?= !empty($b['catatan']) ? e($b['catatan']) : '<span class="text-muted fst-italic">Siapkan topik atau dokumen yang ingin dikonsultasikan.</span>' ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Ajukan Pertemuan -->
<div class="card card-custom border-0 shadow-sm mb-4">
    <div class="card-header card-header-polinela py-3">
        <span class="fw-bold"><i class="bi bi-calendar-plus me-2"></i>Ajukan Janji Pertemuan</span>
    </div>
    <div class="card-body">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>
            <form method="POST" action="<?= BASE_URL ?>/mahasiswa/detail.php?id=<?= $idDosen ?>" class="row g-3">
                <?= csrf_field() ?>
                <div class="col-md-4">
                    <label class="form-label" for="jenis">Jenis pertemuan</label>
                    <select class="form-select" id="jenis" name="jenis" required>
                        <option value="konsultasi">Konsultasi akademik</option>
                        <option value="bimbingan">Bimbingan umum</option>
                        <option value="pribadi">Konsultasi pribadi</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="tanggal">Tanggal</label>
                    <input class="form-control" type="date" id="tanggal" name="tanggal" min="<?= meeting_min_date() ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="request_mode">Pilihan waktu</label>
                    <select class="form-select" id="request_mode" name="request_mode">
                        <option value="custom">Usulkan tanggal dan jam</option>
                        <?php if ($slotPertemuanList): ?><option value="slot">Pilih slot dosen</option><?php endif; ?>
                    </select>
                </div>
                <div class="col-md-4" id="slot-wrapper" hidden>
                    <label class="form-label" for="id_slot">Slot dosen</label>
                    <select class="form-select" id="id_slot" name="id_slot">
                        <option value="">Pilih slot</option>
                        <?php foreach ($slotPertemuanList as $slot): ?>
                            <option value="<?= (int)$slot['id'] ?>" data-slot-type="<?= e($slot['jenis']) ?>" data-hari="<?= e($slot['hari']) ?>">
                                <?= e(ucfirst($slot['jenis'])) ?> — <?= e($slot['hari']) ?>, <?= e(substr($slot['jam_mulai'], 0, 5)) ?>–<?= e(substr($slot['jam_selesai'], 0, 5)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4" id="custom-time-wrapper">
                    <label class="form-label" for="jam_mulai">Jam mulai</label>
                    <input class="form-control" type="time" id="jam_mulai" name="jam_mulai">
                </div>
                <div class="col-md-4" id="custom-end-wrapper">
                    <label class="form-label" for="jam_selesai">Jam selesai</label>
                    <input class="form-control" type="time" id="jam_selesai" name="jam_selesai">
                </div>
                <div class="col-12" id="alasan-wrapper">
                    <label class="form-label" for="alasan">Tujuan singkat</label>
                    <input class="form-control" type="text" id="alasan" name="alasan" maxlength="255" placeholder="Contoh: konsultasi mata kuliah">
                    <div class="form-text">Jangan tulis informasi pribadi atau sensitif di kolom ini.</div>
                </div>
                <div class="col-12">
                    <div class="alert alert-info py-2 small mb-0">
                        Anda bisa memilih slot dosen atau mengusulkan waktu sendiri, termasuk di luar jadwal teori. Permintaan tetap menunggu persetujuan dosen; waktu yang bertabrakan dengan praktik atau pertemuan disetujui tidak dapat diajukan. Untuk konsultasi pribadi, tujuan tidak diminta; laporan admin hanya memakai hitungan agregat.
                    </div>
                </div>
                <div class="col-12 text-end">
                    <button class="btn btn-polinela" type="submit"><i class="bi bi-send me-1"></i>Kirim Permintaan</button>
                </div>
            </form>
            <script>
                const jenisSelect = document.getElementById('jenis');
                const requestMode = document.getElementById('request_mode');
                const slotSelect = document.getElementById('id_slot');
                const slotWrapper = document.getElementById('slot-wrapper');
                const customTimeWrapper = document.getElementById('custom-time-wrapper');
                const customEndWrapper = document.getElementById('custom-end-wrapper');
                const startInput = document.getElementById('jam_mulai');
                const endInput = document.getElementById('jam_selesai');
                const alasanWrapper = document.getElementById('alasan-wrapper');
                const alasanInput = document.getElementById('alasan');
                // Peta hari Indonesia ke nilai getDay() JS (0=Minggu, 1=Senin, ..., 5=Jumat, 6=Sabtu)
                const hariToJsDay = {
                    'Senin': 1, 'Selasa': 2, 'Rabu': 3, 'Kamis': 4, 'Jumat': 5
                };
                const tanggalInput = document.getElementById('tanggal');

                function getNextWeekday(hariName) {
                    const target = hariToJsDay[hariName];
                    if (target === undefined) return '';
                    // Mulai dari besok (min date = tomorrow)
                    const d = new Date();
                    d.setDate(d.getDate() + 1);
                    d.setHours(0, 0, 0, 0);
                    for (let i = 0; i < 7; i++) {
                        if (d.getDay() === target) {
                            // Format YYYY-MM-DD yang diterima input[type=date]
                            const yyyy = d.getFullYear();
                            const mm = String(d.getMonth() + 1).padStart(2, '0');
                            const dd = String(d.getDate()).padStart(2, '0');
                            return yyyy + '-' + mm + '-' + dd;
                        }
                        d.setDate(d.getDate() + 1);
                    }
                    return '';
                }

                function autoFillTanggalFromSlot() {
                    const selected = slotSelect.options[slotSelect.selectedIndex];
                    if (!selected || !selected.value) return;
                    const hari = selected.dataset.hari;
                    if (!hari) return;
                    const nextDate = getNextWeekday(hari);
                    if (nextDate) {
                        tanggalInput.value = nextDate;
                    }
                }

                function filterMeetingSlots() {
                    const requiredType = jenisSelect.value === 'bimbingan' ? 'bimbingan' : 'konsultasi';
                    for (const option of slotSelect.options) {
                        if (!option.value) continue;
                        option.hidden = option.dataset.slotType !== requiredType;
                        if (option.hidden && option.selected) slotSelect.value = '';
                    }
                    const useSlot = requestMode.value === 'slot';
                    slotWrapper.hidden = !useSlot;
                    customTimeWrapper.hidden = useSlot;
                    customEndWrapper.hidden = useSlot;
                    slotSelect.required = useSlot;
                    slotSelect.disabled = !useSlot;
                    startInput.required = !useSlot;
                    endInput.required = !useSlot;
                    startInput.disabled = useSlot;
                    endInput.disabled = useSlot;
                    const isPrivate = jenisSelect.value === 'pribadi';
                    alasanWrapper.hidden = isPrivate;
                    alasanInput.required = !isPrivate;
                    if (isPrivate) alasanInput.value = '';
                    // Jika mode slot aktif dan ada slot yang dipilih, auto-isi tanggal
                    if (useSlot && slotSelect.value) {
                        autoFillTanggalFromSlot();
                    }
                }
                jenisSelect.addEventListener('change', filterMeetingSlots);
                requestMode.addEventListener('change', filterMeetingSlots);
                slotSelect.addEventListener('change', autoFillTanggalFromSlot);
                filterMeetingSlots();
            </script>
    </div>
</div>

<!-- Informasi Etika Mahasiswa -->
<div class="row">
    <div class="col-12">
        <div class="card card-custom border-0 shadow-sm bg-light">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="fs-2 text-success">
                    <i class="bi bi-shield-check"></i>
                </div>
                <div>
                    <h6 class="fw-bold text-dark mb-1">Etika Menemui Dosen Politeknik Negeri Lampung</h6>
                    <p class="small text-muted mb-0">
                        Pastikan Anda datang sesuai dengan slot konsultasi atau bimbingan umum yang telah ditentukan. Datang tanpa jadwal dapat mengganggu waktu mengajar dan penelitian dosen. S-LECTURA menjaga privasi dosen tanpa pelacakan fisik ruangan secara real-time.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
