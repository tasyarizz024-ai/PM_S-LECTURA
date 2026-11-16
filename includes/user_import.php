<?php

require_once __DIR__ . '/schedule_xlsx.php';

function user_xlsx_headers(string $role): array
{
    if ($role === 'mahasiswa') {
        return ['nama', 'email', 'npm', 'tanggal_lahir'];
    }
    if ($role === 'dosen') {
        return ['nama', 'email', 'nidn_nip', 'bidang_keahlian', 'tanggal_lahir'];
    }
    throw new InvalidArgumentException('Jenis akun tidak valid.');
}

function user_import_password_from_birth_date(string $value): ?string
{
    $value = trim($value);
    if (preg_match('/^(\d{2})[-\/](\d{2})[-\/](\d{4})$/', $value, $match)) {
        $date = DateTimeImmutable::createFromFormat('!d-m-Y', $match[1] . '-' . $match[2] . '-' . $match[3]);
        if (!$date || $date->format('d-m-Y') !== $match[1] . '-' . $match[2] . '-' . $match[3]) {
            return null;
        }
    } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $match)) {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            return null;
        }
    } elseif (is_numeric($value) && (float)$value > 0 && (float)$value < 2958466) {
        $date = (new DateTimeImmutable('1899-12-30'))->modify('+' . (int)floor((float)$value) . ' days');
    } else {
        return null;
    }

    if (!$date || $date->format('Y-m-d') > (new DateTimeImmutable('today'))->format('Y-m-d')) {
        return null;
    }

    $today = new DateTimeImmutable('today');
    if ($date < $today->modify('-120 years')) {
        return null;
    }

    return $date->format('dmY');
}

function validate_user_import(PDO $pdo, array $rows, int $prodiId, string $role): array
{
    $errors = [];
    $validRows = [];
    $emailsInFile = [];
    $npmInFile = [];
    $nidnInFile = [];
    if (!in_array($role, ['mahasiswa', 'dosen'], true)) {
        return ['errors' => ['Jenis akun tidak valid.'], 'rows' => []];
    }

    $stmtProgram = $pdo->prepare("SELECT COUNT(*) FROM program_studi WHERE id = :id");
    $stmtProgram->execute([':id' => $prodiId]);
    if ((int)$stmtProgram->fetchColumn() !== 1) {
        return ['errors' => ['Program studi yang dipilih tidak valid.'], 'rows' => []];
    }

    $stmtEmail = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1");
    $stmtNpm = $pdo->prepare(
        "SELECT id FROM users WHERE npm = :user_npm
         UNION SELECT user_id FROM mahasiswa WHERE npm = :student_npm LIMIT 1"
    );
    $stmtNidn = $pdo->prepare("SELECT id FROM dosen WHERE nidn_nip = :nidn LIMIT 1");

    foreach ($rows as $row) {
        $rowNumber = (int)($row['_row_number'] ?? 0);
        $name = trim((string)($row['nama'] ?? ''));
        $email = trim((string)($row['email'] ?? ''));
        $npm = trim((string)($row['npm'] ?? ''));
        $nidn = trim((string)($row['nidn_nip'] ?? ''));
        $expertise = trim((string)($row['bidang_keahlian'] ?? ''));
        $birthDate = trim((string)($row['tanggal_lahir'] ?? ''));
        $initialPassword = user_import_password_from_birth_date($birthDate);
        $rowErrors = [];

        if ($name === '' || mb_strlen($name) > 100) {
            $rowErrors[] = 'nama wajib diisi (maksimal 100 karakter)';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
            $rowErrors[] = 'email tidak valid atau melebihi 100 karakter';
        }
        if ($role === 'mahasiswa' && ($npm === '' || mb_strlen($npm) > 20)) {
            $rowErrors[] = 'NPM wajib diisi (maksimal 20 karakter)';
        }
        if ($role === 'dosen') {
            if ($nidn !== '' && mb_strlen($nidn) > 30) {
                $rowErrors[] = 'NIDN/NIP maksimal 30 karakter';
            }
            if ($expertise === '' || mb_strlen($expertise) > 150) {
                $rowErrors[] = 'bidang keahlian wajib diisi (maksimal 150 karakter)';
            }
        }
        if ($initialPassword === null) {
            $rowErrors[] = 'tanggal_lahir wajib berupa tanggal valid DD-MM-YYYY dan tidak boleh di masa depan';
        }

        $emailKey = mb_strtolower($email);
        if ($email !== '' && isset($emailsInFile[$emailKey])) {
            $rowErrors[] = 'email duplikat di dalam file';
        } elseif ($email !== '') {
            $emailsInFile[$emailKey] = true;
            $stmtEmail->execute([':email' => $email]);
            if ($stmtEmail->fetchColumn()) {
                $rowErrors[] = 'email sudah digunakan';
            }
        }

        if ($role === 'mahasiswa' && $npm !== '') {
            $npmKey = mb_strtolower($npm);
            if (isset($npmInFile[$npmKey])) {
                $rowErrors[] = 'NPM duplikat di dalam file';
            } else {
                $npmInFile[$npmKey] = true;
                $stmtNpm->execute([':user_npm' => $npm, ':student_npm' => $npm]);
                if ($stmtNpm->fetchColumn()) {
                    $rowErrors[] = 'NPM sudah digunakan';
                }
            }
        }

        if ($role === 'dosen' && $nidn !== '') {
            $nidnKey = mb_strtolower($nidn);
            if (isset($nidnInFile[$nidnKey])) {
                $rowErrors[] = 'NIDN/NIP duplikat di dalam file';
            } else {
                $nidnInFile[$nidnKey] = true;
                $stmtNidn->execute([':nidn' => $nidn]);
                if ($stmtNidn->fetchColumn()) {
                    $rowErrors[] = 'NIDN/NIP sudah digunakan';
                }
            }
        }

        if ($rowErrors) {
            $errors[] = 'Baris ' . $rowNumber . ': ' . implode('; ', $rowErrors) . '.';
            continue;
        }

        $validRows[] = [
            'peran' => $role,
            'nama' => $name,
            'email' => $email,
            'npm' => $role === 'mahasiswa' ? $npm : null,
            'nidn_nip' => $role === 'dosen' && $nidn !== '' ? $nidn : null,
            'bidang_keahlian' => $role === 'dosen' ? $expertise : null,
            'initial_password' => $initialPassword,
        ];
    }

    if (!$validRows) {
        $errors[] = 'Tidak ada baris akun valid untuk diimpor.';
    }

    return ['errors' => $errors, 'rows' => $validRows];
}

function insert_user_import(PDO $pdo, array $rows, int $prodiId): array
{
    $credentials = [];
    $stmtUser = $pdo->prepare(
        "INSERT INTO users (nama, email, password_hash, role, npm, must_change_password)
         VALUES (:nama, :email, :password_hash, :role, :npm, 1)"
    );
    $stmtStudent = $pdo->prepare(
        "INSERT INTO mahasiswa (user_id, npm, id_kelas, id_prodi)
         VALUES (:user_id, :npm, NULL, :prodi_id)"
    );
    $stmtLecturer = $pdo->prepare(
        "INSERT INTO dosen (user_id, nidn_nip, id_prodi, bidang_keahlian)
         VALUES (:user_id, :nidn_nip, :prodi_id, :expertise)"
    );
    $stmtStatus = $pdo->prepare(
        "INSERT INTO status_ketersediaan (id_dosen, status, catatan)
         VALUES (:dosen_id, 'Tersedia', 'Siap menerima konsultasi')"
    );

    foreach ($rows as $row) {
        $temporaryPassword = $row['initial_password'];
        $stmtUser->execute([
            ':nama' => $row['nama'],
            ':email' => $row['email'],
            ':password_hash' => password_hash($temporaryPassword, PASSWORD_DEFAULT),
            ':role' => $row['peran'],
            ':npm' => $row['npm'],
        ]);
        $userId = (int)$pdo->lastInsertId();

        if ($row['peran'] === 'mahasiswa') {
            $stmtStudent->execute([
                ':user_id' => $userId,
                ':npm' => $row['npm'],
                ':prodi_id' => $prodiId,
            ]);
        } else {
            $stmtLecturer->execute([
                ':user_id' => $userId,
                ':nidn_nip' => $row['nidn_nip'],
                ':prodi_id' => $prodiId,
                ':expertise' => $row['bidang_keahlian'],
            ]);
            $stmtStatus->execute([':dosen_id' => (int)$pdo->lastInsertId()]);
        }

        $credentials[] = [
            'nama' => $row['nama'],
            'email' => $row['email'],
            'peran' => $row['peran'],
            'password' => $temporaryPassword,
        ];
    }

    return $credentials;
}
