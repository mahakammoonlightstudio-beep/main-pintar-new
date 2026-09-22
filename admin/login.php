<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';

// Bersihkan data lama saat admin login (jalankan sekali, bukan cron)
cleanup_old_data(db());

$pdo = db();
$error = '';

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

if (isset($_SESSION['user_id']) && ($_SESSION['user_role'] ?? '') === 'admin') {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $st = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'admin' LIMIT 1");
        $st->execute([$username]);
        $user = $st->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_nama'] = $user['nama_lengkap'];
            $_SESSION['user_role'] = 'admin';
            header('Location: dashboard.php');
            exit;
        }
        $error = 'Username atau password salah.';
    }
}

mp_head([
    'title' => 'Login Admin',
    'active' => '',
    'body_class' => 'mp-admin',
    'base' => '../',
]);
?>
<div class="join-form">
    <h2 class="text-center">Login Admin</h2>
    <p class="text-center muted mb-16"><?= APP_NAME ?></p>

    <?php if ($error): ?>
        <p class="notice notice-error" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="POST">
        <?= csrf_field() ?>
        <div class="field">
            <label class="field-label" for="username">Username</label>
            <input id="username" type="text" name="username" autocomplete="username" required>
        </div>
        <div class="field">
            <label class="field-label" for="password">Password</label>
            <input id="password" type="password" name="password" autocomplete="current-password" required>
        </div>
        <div class="btn-row">
            <button class="btn btn-primary w-full" type="submit">Masuk</button>
        </div>
    </form>
</div>

<?php mp_foot(['base' => '../']); ?>