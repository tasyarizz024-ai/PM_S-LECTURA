<?php

require_once __DIR__ . '/schedule_xlsx.php';

function validate_schedule_import(PDO $pdo, array $rows, ?int $dosenScope, ?int $jurusanScope, ?int $prodiScope = null): array
{
    $errors = [];
    $validRows = [];
    $pendingSchedules = [];
    $courseNamesByCode = [];
    $weekdays = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];

    $scopedProgram = null;
    if ($prodiScope !== null) {
        $stmtScopeProg = $pdo->prepare('SELECT id, id_jurusan, nama_prodi FROM program_studi WHERE id = :id');
        $stmtScopeProg->execute([':id' => $prodiScope]);
        $scopedProgram = $stmtScopeProg->fetch();
        if (!$scopedProgram || ($jurusanScope !== null && (int)$scopedProgram['id_jurusan'] !== $jurusanScope)) {
            return ['errors' => ['Program studi yang dipilih tidak valid atau di luar cakupan jurusan.'], 'rows' => []];
        }
    }

    foreach ($rows as $row) {
        $rowNumber = (int)($row['_row_number'] ?? 0);
        $value = static fn(string $key): string => trim((string)($row[$key] ?? ''));
        $email = $value('email_dosen');
        $code = $value('kode_mk');
        $courseName = $value('nama_mk');
        $className = $value('nama_kelas');
        $programName = $value('prodi');
        $semester = $value('semester');
        $academicYear = $value('tahun_ajaran');
        $term = $value('termin');
        $day = $value('hari');
        $start = $value('jam_mulai');
        $end = $value('jam_selesai');
        $sessionType = $value('tipe_sesi');
        $credits = $value('sks');
        $mode = $value('mode');
        $room = $value('ruang');

        $rowErrors = [];
        $courseKey = mb_strtolower($code);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $rowErrors[] = 'email dosen tidak valid';
        }
        if ($code === '' || strlen($code) > 20 || $courseName === '' || strlen($courseName) > 150) {
            $rowErrors[] = 'kode dan nama mata kuliah wajib diisi (maksimal 20 dan 150 karakter)';
        }
        if (isset($courseNamesByCode[$courseKey])
            && mb_strtolower($courseNamesByCode[$courseKey]) !== mb_strtolower($courseName)) {
            $rowErrors[] = 'kode mata kuliah yang sama memiliki nama berbeda pada file';
        } elseif ($code !== '' && $courseName !== '') {
            $courseNamesByCode[$courseKey] = $courseName;
        }
        if ($className === '' || strlen($className) > 100) {
            $rowErrors[] = 'nama kelas wajib diisi (maksimal 100 karakter)';
        }
        if ($scopedProgram === null && $programName === '') {
            $rowErrors[] = 'program studi wajib diisi';
        }
        if (!ctype_digit($semester) || (int)$semester < 1 || (int)$semester > 8) {
            $rowErrors[] = 'semester harus berupa angka 1 sampai 8';
        }
        if ($academicYear === '' || strlen($academicYear) > 30) {
            $rowErrors[] = 'tahun ajaran wajib diisi (maksimal 30 karakter)';
        }
        if (!in_array($term, ['1', '2'], true)) {
            $rowErrors[] = 'termin harus 1 atau 2';
        }
        if (!in_array($day, $weekdays, true)) {
            $rowErrors[] = 'hari tidak valid (Senin sampai Jumat)';
        }
        if (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $start)
            || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $end)
            || $start >= $end) {
            $rowErrors[] = 'jam harus berformat HH:MM dan jam selesai setelah jam mulai';
        }
        if (!in_array($sessionType, ['teori', 'praktik'], true)) {
            $rowErrors[] = 'tipe sesi harus teori atau praktik';
        }
        if (!ctype_digit($credits) || (int)$credits < 1 || (int)$credits > 6) {
            $rowErrors[] = 'SKS harus berupa angka 1 sampai 6';
        }
        if (!in_array($mode, ['daring', 'luring'], true)) {
            $rowErrors[] = 'mode harus daring atau luring';
        }
        if (strlen($room) > 100) {
            $rowErrors[] = 'ruang maksimal 100 karakter';
        }

        if (!$rowErrors) {
            $stmtLecturer = $pdo->prepare(
                "SELECT d.id, u.id AS user_id, p.id_jurusan
                 FROM dosen d
                 JOIN users u ON u.id = d.user_id AND u.role = 'dosen'
                 JOIN program_studi p ON p.id = d.id_prodi
                 WHERE LOWER(u.email) = LOWER(:email)
                 LIMIT 1"
            );
            $stmtLecturer->execute([':email' => $email]);
            $lecturer = $stmtLecturer->fetch();
            if (!$lecturer) {
                $rowErrors[] = 'dosen tidak ditemukan';
            } elseif ($dosenScope !== null && (int)$lecturer['id'] !== $dosenScope) {
                $rowErrors[] = 'file hanya boleh memuat email dosen yang sedang login';
            } elseif ($jurusanScope !== null && (int)$lecturer['id_jurusan'] !== $jurusanScope) {
                $rowErrors[] = 'dosen berada di luar cakupan jurusan admin';
            }

            if ($scopedProgram !== null) {
                $program = $scopedProgram;
            } else {
                $sqlProgram = "SELECT id, id_jurusan FROM program_studi WHERE LOWER(nama_prodi) = LOWER(:nama)";
                $programParams = [':nama' => $programName];
                if ($jurusanScope !== null) {
                    $sqlProgram .= " AND id_jurusan = :jurusan";
                    $programParams[':jurusan'] = $jurusanScope;
                }
                $sqlProgram .= ' LIMIT 1';
                $stmtProgram = $pdo->prepare($sqlProgram);
                $stmtProgram->execute($programParams);
                $program = $stmtProgram->fetch();
                if (!$program) {
                    $rowErrors[] = $jurusanScope === null
                        ? 'program studi tidak ditemukan'
                        : 'program studi tidak ditemukan dalam jurusan admin';
                }
            }

            $stmtCourse = $pdo->prepare("SELECT nama FROM mata_kuliah WHERE kode = :kode LIMIT 1");
            $stmtCourse->execute([':kode' => $code]);
            $existingCourseName = $stmtCourse->fetchColumn();
            if ($existingCourseName !== false && mb_strtolower($existingCourseName) !== mb_strtolower($courseName)) {
                $rowErrors[] = 'nama mata kuliah tidak cocok dengan kode yang sudah terdaftar';
            }
        }

        if (!$rowErrors && $lecturer && $program) {
            $conflict = check_time_collision(
                $pdo,
                (int)$lecturer['id'],
                $day,
                $start,
                $end,
                null,
                null,
                $term
            );
            if ($conflict !== null) {
                $rowErrors[] = $conflict;
            }

            $stmtClass = $pdo->prepare(
                "SELECT id FROM kelas
                 WHERE nama_kelas = :nama AND id_prodi = :prodi AND semester = :semester AND tahun_ajaran = :tahun
                 LIMIT 1"
            );
            $stmtClass->execute([
                ':nama' => $className,
                ':prodi' => $program['id'],
                ':semester' => (int)$semester,
                ':tahun' => $academicYear,
            ]);
            $existingClassId = (int)$stmtClass->fetchColumn();
            if ($existingClassId > 0) {
                $classConflict = check_class_schedule_collision(
                    $pdo,
                    $existingClassId,
                    $day,
                    $start,
                    $end,
                    $term
                );
                if ($classConflict !== null) {
                    $rowErrors[] = $classConflict;
                }
            }

            foreach ($pendingSchedules as $pending) {
                $sameTimeScope = $pending['termin'] === $term
                    && $pending['hari'] === $day
                    && $start < $pending['jam_selesai']
                    && $end > $pending['jam_mulai'];
                if ($sameTimeScope && $pending['id_dosen'] === (int)$lecturer['id']) {
                    $rowErrors[] = 'waktu bentrok dengan dosen yang sama pada baris ' . $pending['row_number'] . ' pada file ini';
                    break;
                }
                if ($sameTimeScope
                    && $pending['id_prodi'] === (int)$program['id']
                    && mb_strtolower($pending['nama_kelas']) === mb_strtolower($className)
                    && $pending['semester'] === (int)$semester
                    && mb_strtolower($pending['tahun_ajaran']) === mb_strtolower($academicYear)) {
                    $rowErrors[] = 'jadwal kelas bertabrakan dengan baris ' . $pending['row_number'] . ' pada file ini';
                    break;
                }
            }
        }

        if ($rowErrors) {
            $errors[] = 'Baris ' . $rowNumber . ': ' . implode('; ', $rowErrors) . '.';
            continue;
        }

        $validRows[] = [
            'row_number' => $rowNumber,
            'id_dosen' => (int)$lecturer['id'],
            'id_prodi' => (int)$program['id'],
            'nama_kelas' => $className,
            'semester' => (int)$semester,
            'tahun_ajaran' => $academicYear,
            'kode_mk' => $code,
            'nama_mk' => $courseName,
            'nama_kelas' => $className,
            'semester' => (int)$semester,
            'tahun_ajaran' => $academicYear,
            'termin' => $term,
            'hari' => $day,
            'jam_mulai' => $start,
            'jam_selesai' => $end,
            'tipe_sesi' => $sessionType,
            'sks' => (int)$credits,
            'mode' => $mode,
            'ruang' => $mode === 'daring' || $room === '' ? null : $room,
        ];
        $pendingSchedules[] = [
            'row_number' => $rowNumber,
            'id_dosen' => (int)$lecturer['id'],
            'id_prodi' => (int)$program['id'],
            'nama_kelas' => $className,
            'semester' => (int)$semester,
            'tahun_ajaran' => $academicYear,
            'termin' => $term,
            'hari' => $day,
            'jam_mulai' => $start,
            'jam_selesai' => $end,
        ];
    }

    if (!$rows) {
        $errors[] = 'Tidak ada baris jadwal untuk diimpor.';
    }
    return ['errors' => $errors, 'rows' => $validRows];
}

function insert_schedule_import(PDO $pdo, array $rows): void
{
    $findCourse = $pdo->prepare("SELECT id FROM mata_kuliah WHERE kode = :kode LIMIT 1");
    $insertCourse = $pdo->prepare("INSERT INTO mata_kuliah (kode, nama) VALUES (:kode, :nama)");
    $findClass = $pdo->prepare(
        "SELECT id FROM kelas
         WHERE nama_kelas = :nama AND id_prodi = :prodi AND semester = :semester AND tahun_ajaran = :tahun
         LIMIT 1"
    );
    $insertClass = $pdo->prepare(
        "INSERT INTO kelas (nama_kelas, id_prodi, semester, tahun_ajaran)
         VALUES (:nama, :prodi, :semester, :tahun)"
    );
    $insertSchedule = $pdo->prepare(
        "INSERT INTO jadwal_mengajar
         (id_dosen, id_mk, id_kelas, hari, jam_mulai, jam_selesai, tipe_sesi, termin, sks, mode, ruang)
         VALUES (:dosen, :mk, :kelas, :hari, :mulai, :selesai, :tipe, :termin, :sks, :mode, :ruang)"
    );

    foreach ($rows as $row) {
        $findCourse->execute([':kode' => $row['kode_mk']]);
        $courseId = $findCourse->fetchColumn();
        if (!$courseId) {
            $insertCourse->execute([':kode' => $row['kode_mk'], ':nama' => $row['nama_mk']]);
            $courseId = (int)$pdo->lastInsertId();
        }

        $classParams = [
            ':nama' => $row['nama_kelas'],
            ':prodi' => $row['id_prodi'],
            ':semester' => $row['semester'],
            ':tahun' => $row['tahun_ajaran'],
        ];
        $findClass->execute($classParams);
        $classId = $findClass->fetchColumn();
        if (!$classId) {
            $insertClass->execute($classParams);
            $classId = (int)$pdo->lastInsertId();
        }

        $insertSchedule->execute([
            ':dosen' => $row['id_dosen'],
            ':mk' => $courseId,
            ':kelas' => $classId,
            ':hari' => $row['hari'],
            ':mulai' => $row['jam_mulai'],
            ':selesai' => $row['jam_selesai'],
            ':tipe' => $row['tipe_sesi'],
            ':termin' => $row['termin'],
            ':sks' => $row['sks'],
            ':mode' => $row['mode'],
            ':ruang' => $row['ruang'],
        ]);
    }
}
