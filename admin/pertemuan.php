<?php
// Aggregate appointment report. This page deliberately never fetches student identities or notes.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['admin', 'admin_jurusan']);

$pdo = get_db_connection();
$user = current_user();
$jurusanScope = null;
if ($user['role'] === 'admin_jurusan') {
    $stmtScope = $pdo->prepare(
        "SELECT aj.id_jurusan
         FROM admin_jurusan aj
         WHERE aj.user_id = :user_id"
    );
    $stmtScope->execute([':user_id' => $user['id']]);
    $jurusanScope = $stmtScope->fetchColumn();
    if (!$jurusanScope) {
        set_flash('danger', 'Akun admin jurusan belum memiliki cakupan jurusan.');
        redirect(BASE_URL . '/admin/mahasiswa.php');
    }
}
$yearFilter = trim($_GET['tahun_ajaran'] ?? '');
$semesterFilter = (int)($_GET['semester'] ?? 0);

$periodSql = "SELECT DISTINCT pp.tahun_ajaran, pp.semester
              FROM permintaan_pertemuan pp
              JOIN dosen d ON d.id = pp.id_dosen
              JOIN program_studi p ON p.id = d.id_prodi
              WHERE pp.tahun_ajaran IS NOT NULL AND pp.semester IS NOT NULL";
$periodParams = [];
if ($jurusanScope !== null) {
    $periodSql .= " AND p.id_jurusan = :jurusan";
    $periodParams[':jurusan'] = $jurusanScope;
}
$periodSql .= " ORDER BY pp.tahun_ajaran DESC, pp.semester DESC";
$stmtPeriods = $pdo->prepare($periodSql);
$stmtPeriods->execute($periodParams);
$periods = $stmtPeriods->fetchAll();

$where = [];
$params = $periodParams;
if ($jurusanScope !== null) {
    $where[] = 'p.id_jurusan = :jurusan';
}
if ($yearFilter !== '') {
    $where[] = 'pp.tahun_ajaran = :tahun_ajaran';
    $params[':tahun_ajaran'] = $yearFilter;
}
if ($semesterFilter > 0) {
    $where[] = 'pp.semester = :semester';
    $params[':semester'] = $semesterFilter;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmtReport = $pdo->prepare(
    "SELECT d.id AS id_dosen, u.nama AS nama_dosen, p.nama_prodi,
            pp.semester, pp.tahun_ajaran,
            COUNT(*) AS jumlah_permintaan,
            SUM(CASE WHEN pp.status = 'selesai' THEN 1 ELSE 0 END) AS pertemuan_terlaksana,
            COUNT(DISTINCT CASE
                WHEN pp.status = 'selesai' AND pp.jenis IN ('konsultasi', 'bimbingan')
                THEN pp.id_mahasiswa END) AS mahasiswa_unik_formal,
            SUM(CASE WHEN pp.jenis = 'konsultasi' AND pp.status = 'selesai' THEN 1 ELSE 0 END) AS konsultasi_terlaksana,
            SUM(CASE WHEN pp.jenis = 'bimbingan' AND pp.status = 'selesai' THEN 1 ELSE 0 END) AS bimbingan_terlaksana,
            SUM(CASE WHEN pp.jenis = 'pribadi' THEN 1 ELSE 0 END) AS permintaan_pribadi,
            SUM(CASE WHEN pp.jenis = 'pribadi' AND pp.status = 'selesai' THEN 1 ELSE 0 END) AS pribadi_terlaksana
     FROM permintaan_pertemuan pp
     JOIN dosen d ON d.id = pp.id_dosen
     JOIN users u ON u.id = d.user_id
     JOIN program_studi p ON p.id = d.id_prodi
     {$whereSql}
     GROUP BY d.id, u.nama, p.nama_prodi, pp.semester, pp.tahun_ajaran
     ORDER BY pp.tahun_ajaran DESC, pp.semester DESC, u.nama"
);
$stmtReport->execute($params);
$reportRows = $stmtReport->fetchAll();

$page_title = 'Laporan Pertemuan';
$active_page = 'laporan-pertemuan';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <h3 class="fw-bold mb-1"><i class="bi bi-clipboard-data me-2 text-success"></i>Laporan Pertemuan Dosen</h3>
    <p class="text-secondary mb-0">Rekap agregat permintaan dan pertemuan yang telah ditandai selesai.</p>
</div>

<div class="alert alert-info">
    Laporan hanya menampilkan angka agregat per dosen dan periode. Identitas mahasiswa maupun tujuan/catatan konsultasi tidak ditampilkan. Konsultasi pribadi hanya tercatat sebagai hitungan.
</div>

<form method="GET" class="card card-custom border-0 shadow-sm mb-4">
    <div class="card-body row g-3 align-items-end">
        <div class="col-md-5">
            <label class="form-label" for="tahun_ajaran">Tahun ajaran</label>
            <select class="form-select" id="tahun_ajaran" name="tahun_ajaran">
                <option value="">Semua tahun ajaran</option>
                <?php foreach (array_unique(array_column($periods, 'tahun_ajaran')) as $year): ?>
                    <option value="<?= e($year) ?>" <?= $yearFilter === $year ? 'selected' : '' ?>><?= e($year) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="semester">Semester kelas</label>
            <select class="form-select" id="semester" name="semester">
                <option value="0">Semua semester</option>
                <?php for ($semester = 1; $semester <= 14; $semester++): ?>
                    <option value="<?= $semester ?>" <?= $semesterFilter === $semester ? 'selected' : '' ?>>Semester <?= $semester ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-polinela flex-grow-1">Terapkan</button>
            <a href="<?= BASE_URL ?>/admin/pertemuan.php" class="btn btn-outline-secondary">Reset</a>
        </div>
    </div>
</form>

<div class="card card-custom border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Dosen / Prodi</th><th>Periode snapshot</th>
                    <th>Permintaan</th><th>Terlaksana</th><th>Mahasiswa unik formal</th>
                    <th>Konsultasi selesai</th><th>Bimbingan selesai</th>
                    <th>Permintaan pribadi</th><th>Pribadi terlaksana</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$reportRows): ?>
                <tr><td colspan="9" class="text-center text-muted p-5">Belum ada data untuk periode ini.</td></tr>
            <?php else: foreach ($reportRows as $row): ?>
                <tr>
                    <td class="ps-4"><strong><?= e($row['nama_dosen']) ?></strong><small class="d-block text-muted"><?= e($row['nama_prodi']) ?></small></td>
                    <td>Sem. <?= e($row['semester'] ?? '-') ?><small class="d-block text-muted"><?= e($row['tahun_ajaran'] ?? 'Periode belum tercatat') ?></small></td>
                    <td><?= (int)$row['jumlah_permintaan'] ?></td><td><?= (int)$row['pertemuan_terlaksana'] ?></td>
                    <td><?= (int)$row['mahasiswa_unik_formal'] ?></td><td><?= (int)$row['konsultasi_terlaksana'] ?></td>
                    <td><?= (int)$row['bimbingan_terlaksana'] ?></td><td><?= (int)$row['permintaan_pribadi'] ?></td>
                    <td><?= (int)$row['pribadi_terlaksana'] ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
