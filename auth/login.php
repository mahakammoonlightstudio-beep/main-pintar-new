<?php
/**
 * Main Pintar - auth/login.php
 * Login untuk peserta & admin. Redirect ke beranda/panel sesuai role.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';

$pdo = db();
$error = '';
$redirect = $_GET['redirect'] ?? '';

mp_maintenance_gate();
mp_boot_lang();

// Already logged in (role sungguhan — mode Tamu tetap boleh ke sini)
if (mp_is_logged_in()) {
    $role = $_SESSION['user_role'] ?? 'peserta';
    if ($role === 'admin') {
        header('Location: ../admin/dashboard.php');
    } else {
        header('Location: ' . mp_safe_redirect($redirect));
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if ($username === '' || $password === '') {
        $error = __('Username') . ' & ' . __('Password') . ' wajib diisi.';
    } else {
        $st = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $st->execute([$username]);
        $user = $st->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $guestUid = mp_is_guest() ? (int)($_SESSION['user_id'] ?? 0) : 0;
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_nama'] = $user['nama_lengkap'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_bahasa'] = $user['bahasa'] ?? '';
            $_SESSION['user_tema'] = $user['tema'] ?? '';

            // Skor/jawaban saat main sebagai tamu ikut pindah ke akun yang login
            if ($guestUid > 0) {
                mp_transfer_guest_data($guestUid, (int)$user['id']);
            }

            if ($remember) {
                // Perpanjang cookie sesi aktif jadi 30 hari
                // (session_set_cookie_params tidak berpengaruh setelah session jalan)
                $p = session_get_cookie_params();
                setcookie(session_name(), session_id(), [
                    'expires'  => time() + 30 * 24 * 60 * 60,
                    'path'     => $p['path'],
                    'domain'   => $p['domain'],
                    'secure'   => $p['secure'],
                    'httponly' => $p['httponly'],
                    'samesite' => $p['samesite'] ?: 'Lax',
                ]);
            }

            // Redirect based on role
            if ($user['role'] === 'admin') {
                header('Location: ../admin/dashboard.php');
            } else {
                header('Location: ' . mp_safe_redirect($redirect));
            }
            exit;
        }
        $error = __('Username atau password salah.');
    }
}

mp_head([
    'title' => __('Masuk'),
    'active' => '',
    'base' => '../',
]);
?>
<div class="join-form">
    <h2 class="text-center"><?= __('Masuk ke Main Pintar') ?></h2>
    <p class="text-center muted mb-16"><?= e(app_name()) ?></p>

    <?php if ($error): ?>
        <p class="notice notice-error" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
        <div class="field">
            <label class="field-label" for="username"><?= __('Username') ?></label>
            <input id="username" type="text" name="username" autocomplete="username" required autofocus>
        </div>
        <div class="field">
            <label class="field-label" for="password"><?= __('Password') ?></label>
            <input id="password" type="password" name="password" autocomplete="current-password" required>
        </div>
        <div class="field agree" style="margin-top:var(--s2);">
            <input type="checkbox" id="remember" name="remember">
            <label for="remember"><?= __('Ingat saya (30 hari)') ?></label>
        </div>
        <div class="btn-row">
            <button class="btn btn-primary w-full" type="submit"><?= __('Masuk') ?></button>
        </div>
    </form>

    <p class="text-center mt-16 muted">
        <?= __('Belum punya akun?') ?>
        <?php if (registration_enabled()): ?>
            <a href="register.php<?= $redirect ? '?redirect=' . urlencode($redirect) : '' ?>"><?= __('Daftar di sini') ?></a>
        <?php endif; ?>
    </p>
    <p class="text-center mt-8 muted">
        <?= __('Ingin main tanpa daftar?') ?> <a href="../kuis.php"><?= __('Main sebagai Tamu') ?></a>
    </p>
</div>

<?php mp_foot(['base' => '../']); ?>
