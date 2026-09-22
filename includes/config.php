<?php
/**
 * Main Pintar - Kuis Edukatif Kearsipan Kutai Kartanegara
 * Konfigurasi PDO + konstanta InfinityFree
 */

// ===== TIMEZONE: WITA untuk Kaltim =====
date_default_timezone_set('Asia/Makassar');

// ===== ERROR REPORTING: matikan di production =====
error_reporting(0);
ini_set('display_errors', 0);

// ===== KREDENSIAL DATABASE =====
// Nilai asli ada di config.local.php (TIDAK di-commit — lihat .gitignore).
// Salin includes/config.local.example.php menjadi includes/config.local.php
// dan isi kredensial di sana. Fallback di bawah hanya agar IDE tidak error.
if (is_file(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}
define('DB_HOST', defined('DB_HOST') ? DB_HOST : 'localhost');
define('DB_NAME', defined('DB_NAME') ? DB_NAME : 'kuis');
define('DB_USER', defined('DB_USER') ? DB_USER : 'root');
define('DB_PASS', defined('DB_PASS') ? DB_PASS : '');
define('APP_SECRET', defined('APP_SECRET') ? APP_SECRET : '');

// ===== KONSTANTA APLIKASI =====
define('APP_NAME', 'Main Pintar');
define('APP_SUBTITLE', 'Kuis Edukatif Kearsipan Kutai Kartanegara');
define('APP_TAGLINE', 'Asah Pengetahuan Kearsipanmu, Menangkan Predikat!');

// ===== IDENTITAS INSTANSI =====
define('INSTANSI_NAMA', 'Dinas Kearsipan dan Perpustakaan');
define('INSTANSI_KABUPATEN', 'Kutai Kartanegara');
define('INSTANSI_ALAMAT', 'Jl. Panji No. 47, Panji, Kec. Tenggarong, Kab. Kutai Kartanegara, Kalimantan Timur 75513');
define('INSTANSI_EMAIL', 'diarpuskukar@gmail.com');
define('INSTANSI_WEB', 'https://diarpus.kukarkab.go.id/');
define('INSTANSI_LOGO', 'assets/img/logo-kukar.png');
define('INSTANSI_KABID_P2A', 'Varia Fadillah, S.P., M.M.');
define('INSTANSI_KABID_JABATAN', 'Kepala Bidang P2A (Perlindungan dan Penyelamatan Arsip)');
define('INSTANSI_PEMBANGUN', 'Muhammad Fauzan Raffa Al-Habsy');
define('INSTANSI_PEMBANGUN_SEKOLAH', 'SMK Negeri 1 Tenggarong');
define('INSTANSI_PEMBANGUN_JURUSAN', 'Rekayasa Perangkat Lunak (RPL)');
define('INSTANSI_PEMBANGUN_KELAS', 'Kelas 12');

// Polling (milidetik) — aman untuk limit 50.000 hit/hari InfinityFree
define('POLL_INTERVAL_PESERTA', 4000);
define('POLL_INTERVAL_HOST', 3000);

// Batas permainan
define('DEFAULT_POIN', 100);
define('BONUS_KECEPATAN_MAX', 50);
define('CLEANUP_UMUR_HARI', 7);

// ===== KONEKSI PDO =====
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => true,
        // Batas koneksi (detik) agar halaman tidak menggantung saat DB lambat
        PDO::ATTR_TIMEOUT            => 5,
    ];

    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            $options
        );
        // Samakan timezone sesi MySQL (NOW()) dengan PHP (Asia/Makassar = +08:00, tanpa DST)
        // agar perhitungan timer soal (NOW() vs time()) konsisten.
        @$pdo->exec("SET time_zone = '+08:00'");
        return $pdo;
    } catch (PDOException $e) {
        error_log('[MainPintar] DB Error: ' . $e->getMessage());
        http_response_code(503);
        die('<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Gangguan</title>'
            . '<style>body{font-family:system-ui,sans-serif;text-align:center;padding:60px;background:#f6f4fa;color:#3a3349}'
            . '.box{max-width:420px;margin:auto;background:#fff;padding:32px;border-radius:14px;box-shadow:0 8px 30px rgba(40,30,70,.12)}'
            . 'h1{color:#46178f;font-size:1.3rem}p{color:#6b6280}</style></head><body><div class="box">'
            . '<h1>Sistem sedang gangguan</h1><p>Koneksi database gagal. Silakan coba lagi beberapa saat.</p>'
            . '</div></body></html>');
    }
}

// ===== HIT COUNTER (monitoring limit 50.000 hit/hari) =====
// Nilai dibaca tiap request, tapi hanya ditulis SEKALI per 10 hit agar
// file tidak menjadi titik kalah cepat (contention) di hosting bersama.
function hit_count(): int
{
    static $cached = null;
    if ($cached !== null) return $cached;
    $file = __DIR__ . '/hits.txt';
    $meta = is_file($file) ? explode('|', (string)file_get_contents($file)) : [];
    $n = ($meta[0] ?? '') === date('Ymd') ? (int)($meta[1] ?? 0) : 0;
    $cached = $n;
    return $cached;
}

function hit_tick(): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    $file = __DIR__ . '/hits.txt';
    $today = date('Ymd');
    $n = hit_count() + 1;

    // Tulis hanya di kelipatan 10 (atau hit pertama hari itu) — hemat I/O
    if ($n === 1 || ($n % 10) === 0) {
        @file_put_contents($file, $today . '|' . $n, LOCK_EX);
    }
}

hit_tick();

// ===== SETELAN EKSEKUSI (limit 30 detik InfinityFree) =====
if (function_exists('set_time_limit')) {
    @set_time_limit(25);
}
