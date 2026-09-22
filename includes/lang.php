<?php
/**
 * Main Pintar - Sistem Multi-Bahasa (Indonesia / English)
 * Prioritas bahasa: URL (?lang=) > preferensi user tersimpan (DB) > cookie > default admin.
 * Pemakaian: <?= __('Selamat datang') ?> atau <?= __('Hai, :nama', ['nama' => $nama]) ?>
 */

require_once __DIR__ . '/config.php';

$GLOBALS['MP_LANG_STRINGS'] = null;
$GLOBALS['MP_LANG'] = null;

/**
 * Muat kamus bahasa aktif (file includes/lang/id.php atau en.php)
 */
function mp_lang_strings(string $lang): array
{
    static $cache = [];
    if (isset($cache[$lang])) {
        return $cache[$lang];
    }
    $file = __DIR__ . '/lang/' . $lang . '.php';
    $strings = is_file($file) ? (array)require $file : [];
    $cache[$lang] = $strings;
    return $strings;
}

/**
 * Kode bahasa yang sedang aktif (hanya 'id' atau 'en').
 * Booting malas: pembacaan preferensi user dilakukan sekali via mp_boot_lang()
 * saat pertama kali dibutuhkan (render HTML), bukan di tiap request API.
 */
function mp_lang(): string
{
    if ($GLOBALS['MP_LANG'] === null) {
        mp_boot_lang();
    }
    return $GLOBALS['MP_LANG'];
}

/**
 * Boot bahasa: baca settings default_lang + preferensi user (jika sudah login),
 * lalu tangani ?lang= (simpan ke DB utk user login, cookie utk guest/publik)
 * dan redirect kembali tanpa param agar URL tetap bersih.
 */
function mp_boot_lang(): void
{
    if ($GLOBALS['MP_LANG'] !== null) {
        return;
    }

    // 1. Default dari pengaturan admin
    $default = 'id';
    try {
        $pdo = db();
        $st = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'default_lang' LIMIT 1");
        $st->execute();
        $v = $st->fetchColumn();
        if ($v && in_array($v, ['id', 'en'], true)) {
            $default = $v;
        }
    } catch (Throwable $e) {
        // DB belum dimigrasi / down: pakai default bawaan
    }

    // 2. Preferensi user tersimpan (login)
    if (function_exists('mp_is_logged_in') && mp_is_logged_in() && !empty($_SESSION['user_bahasa'])) {
        $b = (string)$_SESSION['user_bahasa'];
        if (in_array($b, ['id', 'en'], true)) {
            $default = $b;
        }
    }

    // 3. Cookie (guest / publik)
    if (!empty($_COOKIE['mp_lang']) && in_array($_COOKIE['mp_lang'], ['id', 'en'], true)) {
        $default = $_COOKIE['mp_lang'];
    }

    // 4. Parameter ?lang= menang semua + persist
    if (isset($_GET['lang'])) {
        $pilih = strtolower(trim((string)$_GET['lang']));
        if (in_array($pilih, ['id', 'en'], true)) {
            $default = $pilih;
            try {
                if (function_exists('mp_is_logged_in') && mp_is_logged_in()) {
                    db()->prepare("UPDATE users SET bahasa = ? WHERE id = ?")
                        ->execute([$pilih, (int)$_SESSION['user_id']]);
                    $_SESSION['user_bahasa'] = $pilih;
                }
            } catch (Throwable $e) {
                // abaikan: cookie tetap disimpan di bawah
            }
            @setcookie('mp_lang', $pilih, [
                'expires'  => time() + 365 * 24 * 60 * 60,
                'path'     => '/',
                'samesite' => 'Lax',
                'httponly' => true,
                'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            ]);
            // Bersihkan URL: buang param lang agar tidak dobel tersimpan
            $qs = $_GET;
            unset($qs['lang']);
            $target = strtok($_SERVER['REQUEST_URI'] ?? '', '?') ?: '';
            if ($qs) {
                $target .= '?' . http_build_query($qs);
            }
            header('Location: ' . $target);
            exit;
        }
    }

    $GLOBALS['MP_LANG'] = $default;
    $GLOBALS['MP_LANG_STRINGS'] = mp_lang_strings($default);
}

/**
 * Terjemahkan string. Fallback: kunci asli (bahasa Indonesia) bila belum ada di kamus.
 */
function __(string $key, array $replace = []): string
{
    if ($GLOBALS['MP_LANG_STRINGS'] === null) {
        $GLOBALS['MP_LANG_STRINGS'] = mp_lang_strings(mp_lang());
    }
    $strings = $GLOBALS['MP_LANG_STRINGS'] ?? [];
    $out = $strings[$key] ?? $key;
    foreach ($replace as $k => $v) {
        $out = str_replace(':' . $k, (string)$v, $out);
    }
    return $out;
}

/**
 * Pilihan bahasa untuk dropdown/toggle UI.
 */
function mp_lang_options(): array
{
    return [
        'id' => 'Bahasa Indonesia',
        'en' => 'English',
    ];
}

/**
 * URL bahasa lain (untuk tombol ganti bahasa di header).
 */
function mp_lang_switch_url(string $to): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $path = strtok($uri, '?') ?: '';
    $qs = $_GET;
    $qs['lang'] = $to;
    return $path . '?' . http_build_query($qs);
}
