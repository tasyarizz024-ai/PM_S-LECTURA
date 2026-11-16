<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

set_flash('info', 'Pendaftaran mandiri tidak tersedia. Silakan hubungi admin jurusan untuk mendapatkan akun.');
redirect(BASE_URL . '/login.php');
