<?php
// Helpers for appointment scheduling and availability checks.

function meeting_weekday(string $date): ?string {
    $dayNames = [1 => 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        return null;
    }
    return $dayNames[(int)$parsed->format('N')];
}

function meeting_min_date(): string {
    $timezone = new DateTimeZone('Asia/Jakarta');
    return (new DateTimeImmutable('tomorrow', $timezone))->format('Y-m-d');
}

function meeting_has_teaching_conflict(
    PDO $pdo,
    int $dosenId,
    string $date,
    string $start,
    string $end,
    string $sessionType
): bool {
    $weekday = meeting_weekday($date);
    if ($weekday === null) {
        return true;
    }

    $sql = "SELECT COUNT(*)
            FROM jadwal_mengajar jm
            WHERE jm.id_dosen = :dosen_jadwal
              AND jm.hari = :hari
              AND jm.tipe_sesi = :tipe_sesi
              AND jm.jam_mulai < :jadwal_selesai
              AND jm.jam_selesai > :jadwal_mulai";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':dosen_jadwal' => $dosenId,
        ':hari' => $weekday,
        ':tipe_sesi' => $sessionType,
        ':jadwal_selesai' => $end,
        ':jadwal_mulai' => $start,
    ]);
    return (int)$stmt->fetchColumn() > 0;
}

function meeting_has_practical_conflict(PDO $pdo, int $dosenId, string $date, string $start, string $end): bool {
    return meeting_has_teaching_conflict($pdo, $dosenId, $date, $start, $end, 'praktik');
}

function meeting_has_theory_overlap(PDO $pdo, int $dosenId, string $date, string $start, string $end): bool {
    return meeting_has_teaching_conflict($pdo, $dosenId, $date, $start, $end, 'teori');
}

function meeting_has_approved_conflict(
    PDO $pdo,
    int $dosenId,
    string $date,
    string $start,
    string $end,
    ?int $excludeId = null
): bool {
    if (meeting_weekday($date) === null) {
        return true;
    }

    $sql = "SELECT COUNT(*)
            FROM permintaan_pertemuan
            WHERE id_dosen = :dosen_pertemuan
              AND tanggal = :tanggal
              AND status = 'disetujui'
              AND jam_mulai < :pertemuan_selesai
              AND jam_selesai > :pertemuan_mulai";
    $params = [
        ':dosen_pertemuan' => $dosenId,
        ':tanggal' => $date,
        ':pertemuan_selesai' => $end,
        ':pertemuan_mulai' => $start,
    ];
    if ($excludeId !== null) {
        $sql .= " AND id != :exclude_id";
        $params[':exclude_id'] = $excludeId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn() > 0;
}

function meeting_has_conflict(
    PDO $pdo,
    int $dosenId,
    string $date,
    string $start,
    string $end,
    ?int $excludeId = null
): bool {
    return meeting_has_practical_conflict($pdo, $dosenId, $date, $start, $end)
        || meeting_has_approved_conflict($pdo, $dosenId, $date, $start, $end, $excludeId);
}

function meeting_slot_type(string $meetingType): string {
    return $meetingType === 'bimbingan' ? 'bimbingan' : 'konsultasi';
}

function meeting_is_valid_time_range(string $start, string $end): bool {
    $startTime = DateTimeImmutable::createFromFormat('!H:i', $start);
    $endTime = DateTimeImmutable::createFromFormat('!H:i', $end);
    return $startTime !== false
        && $endTime !== false
        && $startTime->format('H:i') === $start
        && $endTime->format('H:i') === $end
        && $endTime > $startTime;
}

function meeting_is_future(string $date, string $time): bool {
    $timezone = new DateTimeZone('Asia/Jakarta');
    $requested = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $time, $timezone);
    return $requested !== false
        && $requested->format('Y-m-d H:i') === $date . ' ' . $time
        && $requested > new DateTimeImmutable('now', $timezone);
}
