<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('mahasiswa');

set_flash('info', 'Jadwal kelas mahasiswa dikelola melalui eKuliah dan tidak lagi ditampilkan di S-LECTURA.');
redirect(BASE_URL . '/mahasiswa/index.php');
