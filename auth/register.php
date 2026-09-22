<?php
/**
 * Main Pintar - auth/register.php
 * Registrasi peserta baru. Admin tidak bisa daftar di sini.
 * Bisa ditutup dari Pengaturan Sistem (allow_registration = 0).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';

$pdo = db();
$error = '';
$redirect = $_GET['redirect'] ?? '';

mp_maintenance_gate();
mp_boot_lang();

// Registrasi dimatikan dari Pengaturan Sistem
if (!registration_enabled()) {
    mp_head(['title' => __('Daftar'), 'active' => '', 'base' => '../']);
    echo '<div class="join-form"><p class="notice notice-info" role="alert">'
        . e(__('Registrasi ditutup sementara. Hubungi administrator.'))
        . '</p><p class="text-center mt-16"><a class="btn btn-quiet" href="../index.php">'
        . e(__('Beranda')) . '</a></p></div>';
    mp_foot(['base' => '../']);
    exit;
}

// Sudah login (role sungguhan — mode Tamu tetap boleh mendaftar)
if (mp_is_logged_in()) {
    header('Location: ' . mp_safe_redirect($redirect));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim($_POST['username'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if ($username === '' || $nama === '' || $password === '') {
        $error = __('Semua field wajib diisi.');
    } elseif ($password !== $password2) {
        $error = __('Konfirmasi password tidak cocok.');
    } elseif (strlen($password) < 6) {
        $error = __('Password minimal 6 karakter.');
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $error = __('Username 3-20 karakter, hanya huruf, angka, underscore.');
    } else {
        // Check if username exists
        $st = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $st->execute([$username]);
        if ($st->fetch()) {
            $error = __('Username sudah terdaftar.');
        } else {
            try {
                // Create user
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $st = $pdo->prepare("INSERT INTO users (username, nama_lengkap, password, role, bahasa) VALUES (?, ?, ?, 'peserta', ?)");
                $st->execute([$username, $nama, $hash, mp_lang()]);
                $uid = (int)$pdo->lastInsertId();

                // Auto login
                $guestUid = mp_is_guest() ? (int)($_SESSION['user_id'] ?? 0) : 0;
                session_regenerate_id(true);
                $_SESSION['user_id'] = $uid;
                $_SESSION['user_nama'] = $nama;
                $_SESSION['user_role'] = 'peserta';
                $_SESSION['user_bahasa'] = mp_lang();
                $_SESSION['user_tema'] = '';

                // Sambut peserta baru dengan notifikasi selamat datang
                try {
                    $pdo->prepare(
                        "INSERT INTO notifikasi (user_id, judul, pesan, jenis) VALUES (?, ?, ?, 'success')"
                    )->execute([
                        $uid,
                        __('Selamat datang di Main Pintar!'),
                        __('Lengkapi profil & preferensimu di halaman Pengaturan, lalu mulai asah pengetahuan kearsipanmu.'),
                    ]);
                } catch (Throwable $e) {
                    // Tabel notifikasi belum dimigrasi — abaikan
                }

                // Skor/jawaban saat main sebagai tamu ikut pindah ke akun baru
                if ($guestUid > 0) {
                    mp_transfer_guest_data($guestUid, $uid);
                }

                header('Location: ' . mp_safe_redirect($redirect));
                exit;
            } catch (PDOException $e) {
                // Balapan duplikat username (unique key) — jangan sampai 500
                $error = __('Username sudah terdaftar.');
            }
        }
    }
}

mp_head([
    'title' => __('Daftar'),
    'active' => '',
    'base' => '../',
]);
?>
<div class="join-form">
    <h2 class="text-center"><?= __('Buat Akun Baru') ?></h2>
    <p class="text-center muted mb-16"><?= __('Simpan skor & tampil di Peringkat') ?></p>

    <?php if ($error): ?>
        <p class="notice notice-error" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
        <div class="field">
            <label class="field-label" for="username"><?= __('Username') ?></label>
            <input id="username" type="text" name="username" autocomplete="username" required
                   pattern="[a-zA-Z0-9_]{3,20}" placeholder="contoh: budi_123" autofocus>
        </div>
        <div class="field">
            <label class="field-label" for="nama"><?= __('Nama Lengkap') ?></label>
            <input id="nama" type="text" name="nama" autocomplete="name" required maxlength="100" placeholder="<?= __('Nama untuk leaderboard') ?>">
        </div>
        <div class="field">
            <label class="field-label" for="password"><?= __('Password') ?></label>
            <input id="password" type="password" name="password" autocomplete="new-password" required minlength="6" placeholder="<?= __('Minimal 6 karakter') ?>">
        </div>
        <div class="field">
            <label class="field-label" for="password2"><?= __('Konfirmasi Password') ?></label>
            <input id="password2" type="password" name="password2" autocomplete="new-password" required minlength="6" placeholder="<?= __('Ulangi password') ?>">
        </div>
        <div class="btn-row">
            <button class="btn btn-primary w-full" type="submit"><?= __('Daftar & Main') ?></button>
        </div>
    </form>

    <p class="text-center mt-16 muted">
        <?= __('Sudah punya akun?') ?> <a href="login.php<?= $redirect ? '?redirect=' . urlencode($redirect) : '' ?>"><?= __('Masuk di sini') ?></a>
    </p>
    <p class="text-center mt-8 muted">
        <?= __('Ingin main tanpa daftar?') ?> <a href="../kuis.php"><?= __('Main sebagai Tamu') ?></a>
    </p>
</div>

<?php mp_foot(['base' => '../']); ?>
