<?php
/**
 * Main Pintar - Session & autentikasi (OPTIMIZED)
 * Support: logged-in users, anonymous guests, admin.
 */
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly'  => true,
        'cookie_secure'    => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'cookie_samesite'  => 'Lax',
        'use_strict_mode'  => true,
        'read_and_close'   => false, // Keep session open for writes
    ]);
}

/**
 * Cek apakah user adalah admin
 */
function mp_is_admin(): bool
{
    return (($_SESSION['user_role'] ?? '') === 'admin');
}

/**
 * Cek apakah user sudah login (bukan guest)
 */
function mp_is_logged_in(): bool
{
    return !empty($_SESSION['user_id']) && ($_SESSION['user_role'] ?? '') !== 'guest';
}

/**
 * Cek apakah user adalah guest (anonymous)
 */
function mp_is_guest(): bool
{
    return (($_SESSION['user_role'] ?? '') === 'guest');
}

/**
 * Require admin - redirect ke login jika bukan admin
 */
function mp_require_admin(): void
{
    if (!mp_is_admin()) {
        header('Location: ' . (basename($_SERVER['SCRIPT_NAME']) === 'login.php' ? 'login.php' : '../admin/login.php'));
        exit;
    }
}

/**
 * Require login - redirect ke login jika guest
 */
function mp_require_login(string $redirect = ''): void
{
    if (!mp_is_logged_in()) {
        $loginUrl = '../auth/login.php' . ($redirect ? '?redirect=' . urlencode($redirect) : '');
        header('Location: ' . $loginUrl);
        exit;
    }
}

/**
 * Get current user ID (0 if guest)
 */
function mp_get_user_id(): int
{
    return (int)($_SESSION['user_id'] ?? 0);
}

/**
 * Get current user name
 */
function mp_get_user_nama(): string
{
    return $_SESSION['user_nama'] ?? 'Tamu';
}

/**
 * Get current user role
 */
function mp_get_user_role(): string
{
    return $_SESSION['user_role'] ?? 'guest';
}

/**
 * Ambil atau buat user peserta.
 * - Jika sudah login: return user_id
 * - Jika guest dengan nickname: buat user anon (role=guest) atau upgrade ke peserta
 * - Jika tidak ada session: buat guest session
 */
function mp_get_or_create_peserta(string $nickname = ''): int
{
    $pdo = db();

    // 1. Already logged in as peserta/admin
    $uid = $_SESSION['user_id'] ?? 0;
    if ($uid > 0 && (($_SESSION['user_role'] ?? '') !== 'guest')) {
        $st = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role IN ('peserta','admin') LIMIT 1");
        $st->execute([$uid]);
        if ($st->fetch()) {
            // Jangan timpa nama user terdaftar dengan nickname live
            return $uid;
        }
    }

    // 2. Guest session exists - upgrade or reuse
    if ($uid > 0 && ($_SESSION['user_role'] ?? '') === 'guest') {
        if ($nickname !== '') {
            // Upgrade guest to peserta with nickname
            $st = $pdo->prepare("UPDATE users SET nama_lengkap = ?, role = 'peserta' WHERE id = ?");
            $st->execute([$nickname, $uid]);
            $_SESSION['user_nama'] = $nickname;
            $_SESSION['user_role'] = 'peserta';
        }
        return $uid;
    }

    // 3. Create new guest user (anonymous)
    $nama = $nickname !== '' ? $nickname : 'Tamu' . random_int(1000, 9999);
    $username = 'guest_' . bin2hex(random_bytes(6));
    $st = $pdo->prepare("INSERT INTO users (username, nama_lengkap, password, role) VALUES (?, ?, ?, 'guest')");
    $st->execute([$username, $nama, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
    $uid = (int)$pdo->lastInsertId();

    $_SESSION['user_id']  = $uid;
    $_SESSION['user_nama'] = $nama;
    $_SESSION['user_role'] = $nickname !== '' ? 'peserta' : 'guest';

    return $uid;
}

/**
 * Pindahkan data permainan (skor, jawaban, peserta sesi) dari user guest ke akun
 * tujuan (hasil register/login), lalu hapus user guest. Aman dipanggil kapan saja.
 */
function mp_transfer_guest_data(int $oldUid, int $newUserId): void
{
    if ($oldUid <= 0 || $newUserId <= 0 || $oldUid === $newUserId) {
        return;
    }
    $pdo = db();

    // Pindahkan data; jika konflik unique key (jarang), baris guest dibiarkan
    // dan dibuang pada langkah pembersihan di bawah.
    $moveSqls = [
        "UPDATE jawaban SET user_id = ? WHERE user_id = ?",
        "UPDATE skor SET user_id = ? WHERE user_id = ?",
        "UPDATE peserta_sesi SET user_id = ? WHERE user_id = ?",
    ];
    foreach ($moveSqls as $sql) {
        try {
            $pdo->prepare($sql)->execute([$newUserId, $oldUid]);
        } catch (PDOException $e) {
            error_log('[MainPintar] Guest transfer conflict: ' . $e->getMessage());
        }
    }

    // Buang sisa data guest, lalu hapus user guest-nya
    try {
        $pdo->prepare("DELETE FROM jawaban WHERE user_id = ?")->execute([$oldUid]);
        $pdo->prepare("DELETE FROM peserta_sesi WHERE user_id = ?")->execute([$oldUid]);
        $pdo->prepare("DELETE FROM skor WHERE user_id = ?")->execute([$oldUid]);
        $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'guest'")->execute([$oldUid]);
    } catch (PDOException $e) {
        error_log('[MainPintar] Guest cleanup failed: ' . $e->getMessage());
    }
}

/**
 * Start guest session for anonymous play
 */
function mp_start_guest_session(): int
{
    if (empty($_SESSION['user_id'])) {
        try {
            return mp_get_or_create_peserta();
        } catch (Throwable $e) {
            error_log('[MainPintar] Guest session failed: ' . $e->getMessage());
            return 0;
        }
    }
    return (int)$_SESSION['user_id'];
}