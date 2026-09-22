<?php
/**
 * Main Pintar - CONTOH konfigurasi lokal.
 * Salin file ini menjadi config.local.php lalu isi nilai aslinya.
 * config.local.php TIDAK di-commit (lihat .gitignore).
 */

// Kredensial database (dari cPanel > MySQL Databases)
define('DB_HOST', 'localhost');
define('DB_NAME', 'nama_database');
define('DB_USER', 'user_database');
define('DB_PASS', 'ganti_password_anda');

// Kunci tanda-tangan (opsional, 32+ karakter acak)
define('APP_SECRET', 'ganti-dengan-string-acak-panjang');
