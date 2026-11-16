<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

set_flash('info', 'Pengelolaan akun dan profil dosen dilakukan oleh admin jurusan sesuai program studi.');
redirect(BASE_URL . '/admin/index.php');
