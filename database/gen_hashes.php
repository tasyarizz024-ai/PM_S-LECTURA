<?php
// S-LECTURA - Generate hash untuk sandi awal demo semua akun.
// Jalankan: php database/gen_hashes.php > database/hashes.txt

$cost = ['cost' => 10];
echo "DEMO|" . password_hash('123456', PASSWORD_BCRYPT, $cost) . "\n";
