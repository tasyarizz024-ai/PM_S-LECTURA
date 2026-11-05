<?php
// Endpoint lama dipertahankan agar tautan tersimpan tidak membuka pengelolaan jadwal admin.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

set_flash('warning', 'Pengelolaan jadwal dan ketersediaan hanya dapat dilakukan oleh dosen terkait.');
redirect(BASE_URL . '/admin/index.php');
