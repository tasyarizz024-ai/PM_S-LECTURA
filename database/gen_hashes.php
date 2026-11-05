<?php
// S-LECTURA - Generate hash untuk sandi awal demo dosen dan mahasiswa.
// Jalankan: php database/gen_hashes.php > database/hashes.txt

$cost = ['cost' => 10];
echo "DEMO|" . password_hash('password', PASSWORD_BCRYPT, $cost) . "\n";
