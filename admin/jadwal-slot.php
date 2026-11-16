<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

set_flash('info', 'Pengelolaan jadwal dan slot dilakukan oleh dosen atau admin jurusan sesuai tugasnya.');
redirect(BASE_URL . '/admin/index.php');
