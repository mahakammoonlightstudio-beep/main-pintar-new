<?php
/**
 * Main Pintar - Pengaturan Aplikasi (tersimpan di tabel `settings`)
 * Nilai dari DB menimpa konstanta lama (APP_NAME, APP_TAGLINE) saat tersedia.
 * Semua fungsi aman bila tabel belum dimigrasi (fallback ke konstanta).
 */

require_once __DIR__ . '/config.php';

/**
 * Ambil satu nilai pengaturan.
 */
function setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (db()->query("SELECT setting_key, setting_value FROM settings") as $row) {
                $cache[$row['setting_key']] = (string)$row['setting_value'];
            }
        } catch (Throwable $e) {
            // Tabel settings belum ada (belum migrasi) — fallback konstanta
        }
    }
    return $cache[$key] ?? $default;
}

/**
 * Simpan (upsert) satu nilai pengaturan.
 */
function setting_set(string $key, string $value): void
{
    db()->prepare(
        "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    )->execute([$key, $value]);
}

/**
 * Nama aplikasi: dari settings, fallback konstanta APP_NAME.
 */
function app_name(): string
{
    $v = setting('app_name', '');
    return $v !== '' ? $v : APP_NAME;
}

/**
 * Tagline aplikasi: dari settings, fallback konstanta APP_TAGLINE.
 */
function app_tagline(): string
{
    $v = setting('app_tagline', '');
    return $v !== '' ? $v : APP_TAGLINE;
}

/**
 * Mode pemeliharaan aktif? (Admin selalu bisa masuk.)
 */
function maintenance_enabled(): bool
{
    return setting('maintenance_mode', '0') === '1';
}

/**
 * Registrasi akun baru diizinkan?
 */
function registration_enabled(): bool
{
    return setting('allow_registration', '1') === '1';
}

/**
 * Halaman maintenance penuh — dipanggil dari titik masuk publik.
 */
function mp_maintenance_gate(): void
{
    if (!maintenance_enabled() || mp_is_admin()) {
        return;
    }
    http_response_code(503);
    header('Retry-After: 3600');
    $msg = setting('maintenance_message', 'Sistem sedang dalam pemeliharaan. Silakan kembali lagi nanti.');
    $nama = app_name();
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . htmlspecialchars($nama) . ' &middot; Pemeliharaan</title>'
        . '<style>body{font-family:system-ui,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f4f1f9;color:#1e1830}'
        . '.box{max-width:460px;background:#fff;padding:40px;border-radius:16px;box-shadow:0 8px 30px rgba(40,30,70,.12);text-align:center}'
        . 'h1{color:#46178f;font-size:1.35rem;margin-bottom:12px}p{color:#4c4460;line-height:1.6;margin:0}</style></head>'
        . '<body><div class="box"><div style="font-size:3rem">&#128736;</div>'
        . '<h1>' . htmlspecialchars($nama) . '</h1><p>' . htmlspecialchars($msg) . '</p>'
        . '<p style="margin-top:16px;font-size:.85rem;color:#6f6684">Dinas Kearsipan dan Perpustakaan Kabupaten Kutai Kartanegara</p>'
        . '</div></body></html>';
    exit;
}
