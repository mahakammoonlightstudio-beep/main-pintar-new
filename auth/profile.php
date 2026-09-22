<?php
/**
 * Main Pintar - auth/profile.php
 * Halaman profil peserta: hero profil, statistik, riwayat, update nama & password.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';

if (!mp_is_logged_in()) {
    header('Location: login.php?redirect=' . urlencode('profile.php'));
    exit;
}

$pdo = db();
$uid = mp_get_user_id();
$msg = $_GET['msg'] ?? '';
$error = '';

mp_maintenance_gate();
mp_boot_lang();

// Update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    csrf_verify();
    $nama = trim($_POST['nama'] ?? '');
    if ($nama === '') {
        $error = 'Nama tidak boleh kosong.';
    } elseif (strlen($nama) > 100) {
        $error = 'Nama terlalu panjang (maks 100 karakter).';
    } else {
        $pdo->prepare("UPDATE users SET nama_lengkap = ? WHERE id = ?")->execute([$nama, $uid]);
        $_SESSION['user_nama'] = $nama;
        header('Location: profile.php?msg=updated');
        exit;
    }
}

// Change password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    csrf_verify();
    $old = $_POST['old_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $new2 = $_POST['new_password2'] ?? '';

    $st = $pdo->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
    $st->execute([$uid]);
    $user = $st->fetch();

    if (!$user || !password_verify($old, $user['password'])) {
        $error = 'Password lama salah.';
    } elseif (strlen($new) < 6) {
        $error = 'Password baru minimal 6 karakter.';
    } elseif ($new !== $new2) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $uid]);
        header('Location: profile.php?msg=password_changed');
        exit;
    }
}

// Data profil + statistik
$stU = $pdo->prepare("SELECT username, created_at, jabatan, unit_kerja FROM users WHERE id = ? LIMIT 1");
$stU->execute([$uid]);
$meta = $stU->fetch() ?: ['username' => '', 'created_at' => null, 'jabatan' => null, 'unit_kerja' => null];
$usernameAsli = (string)($meta['username'] ?? '');
$memberSince = !empty($meta['created_at']) ? date('M Y', strtotime($meta['created_at'])) : '-';

$scores = mp_get_user_scores($pdo, $uid);
$activity = mp_get_user_activity($pdo, $uid, 10);
$totalSkor = (int)array_sum(array_column($scores, 'best_poin'));
$totalMain = (int)array_sum(array_column($scores, 'jumlah_main'));
$bestOverall = $scores ? (int)max(array_column($scores, 'best_poin')) : 0;

$nama = mp_get_user_nama();
$inisial = mb_strtoupper(mb_substr($nama, 0, 1));
if ($inisial === '' || $inisial === null) $inisial = '?';

mp_head([
    'title' => 'Akun Saya',
    'active' => 'profile',
    'base' => '../',
]);
?>

<!-- Hero profil -->
<div class="profile-hero reveal">
    <span class="avatar-badge<?= $totalSkor >= 1000 ? ' gold' : '' ?>"><?= e($inisial) ?></span>
    <div class="profile-hero-main">
        <div class="profile-hero-name"><?= e($nama) ?></div>
        <div class="profile-hero-sub">@<?= e($usernameAsli ?: 'peserta') ?> &middot; <?= $totalSkor >= 1000 ? __('Master Kuis') : __('Petualang Arsip') ?> &middot; <?= __('anggota sejak') ?> <?= e($memberSince) ?></div>
        <?php if (!empty($meta['jabatan']) || !empty($meta['unit_kerja'])): ?>
        <div class="profile-hero-sub"><?= e(trim(($meta['jabatan'] ?? '') . (!empty($meta['jabatan']) && !empty($meta['unit_kerja']) ? ' — ' : '') . ($meta['unit_kerja'] ?? ''))) ?></div>
        <?php endif; ?>
        <div class="tag-row" style="margin-top:var(--s2);">
            <span class="badge-chip<?= $totalSkor >= 1000 ? ' chip-gold' : '' ?>"><?= $totalSkor >= 1000 ? '&#11088; ' . __('Master Kuis') : '&#127919; ' . __('Petualang Arsip') ?></span>
            <span class="badge-chip chip-ok"><?= (int)count($scores) ?> <?= __('kategori dimainkan') ?></span>
        </div>
    </div>
    <div class="profile-hero-stats">
        <div class="profile-hero-stat"><b><?= $totalSkor ?></b><span>Total Skor</span></div>
        <div class="profile-hero-stat"><b><?= $totalMain ?></b><span>Total Main</span></div>
        <div class="profile-hero-stat"><b><?= $bestOverall ?></b><span>Terbaik</span></div>
    </div>
</div>

<?php if ($msg === 'updated'): ?>
    <p class="notice notice-ok mt-8" role="status"><?= __('Profil berhasil diperbarui.') ?></p>
<?php elseif ($msg === 'password_changed'): ?>
    <p class="notice notice-ok mt-8" role="status"><?= __('Password berhasil diubah.') ?></p>
<?php elseif ($error): ?>
    <p class="notice notice-error mt-8" role="alert"><?= e($error) ?></p>
<?php endif; ?>

<!-- Form: nama + password berdampingan -->
<div class="grid-2 section-tight">
    <section class="panel reveal">
        <header>
            <h2><?= __('Informasi Akun') ?></h2>
            <p><?= __('Nama tampil inilah yang terlihat di papan peringkat.') ?></p>
        </header>
        <div class="panel-body">
            <form method="POST">
                <?= csrf_field() ?>
                <div class="field">
                    <label class="field-label" for="username"><?= __('Username') ?></label>
                    <input id="username" type="text" value="<?= e($usernameAsli) ?>" disabled>
                    <span class="hint"><?= __('Username tidak bisa diubah') ?></span>
                </div>
                <div class="field">
                    <label class="field-label" for="nama"><?= __('Nama Tampil (di Leaderboard)') ?></label>
                    <input id="nama" type="text" name="nama" value="<?= e($nama) ?>" required maxlength="100">
                </div>
                <div class="field">
                    <label class="field-label"><?= __('Role') ?></label>
                    <input type="text" value="<?= mp_is_admin() ? __('Administrator') : __('Peserta') ?>" disabled>
                </div>
                <button class="btn btn-primary" name="update_profile" value="1" type="submit"><?= __('Simpan Perubahan') ?></button>
            </form>
        </div>
    </section>

    <section class="panel reveal">
        <header>
            <h2><?= __('Ubah Password') ?></h2>
            <p><?= __('Gunakan kombinasi yang mudah kamu ingat tapi sulit ditebak.') ?></p>
        </header>
        <div class="panel-body">
            <form method="POST">
                <?= csrf_field() ?>
                <div class="field password-field">
                    <label class="field-label" for="old_password">Password Lama</label>
                    <input id="old_password" type="password" name="old_password" autocomplete="current-password" required>
                    <button type="button" class="password-toggle" data-toggle-for="old_password" aria-label="Tampilkan password">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
                <div class="field password-field">
                    <label class="field-label" for="new_password">Password Baru</label>
                    <input id="new_password" type="password" name="new_password" autocomplete="new-password" required minlength="6">
                    <button type="button" class="password-toggle" data-toggle-for="new_password" aria-label="Tampilkan password">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
                <div class="field password-field">
                    <label class="field-label" for="new_password2">Konfirmasi Password Baru</label>
                    <input id="new_password2" type="password" name="new_password2" autocomplete="new-password" required minlength="6">
                    <button type="button" class="password-toggle" data-toggle-for="new_password2" aria-label="Tampilkan password">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
                <button class="btn btn-quiet" name="change_password" value="1" type="submit">Ubah Password</button>
            </form>
        </div>
    </section>
</div>

<!-- Statistik ringkas -->
<section class="panel section-tight reveal">
    <header>
        <h2><?= __('Statistik Permainan') ?></h2>
        <a class="btn-sm" href="stats.php"><?= __('Lihat Detail Lengkap') ?> &rarr;</a>
    </header>
    <div class="panel-body">
        <div class="metrics">
            <div class="metric"><dt><?= __('Total Skor') ?></dt><dd data-countup="<?= $totalSkor ?>">0</dd></div>
            <div class="metric"><dt><?= __('Total Main') ?></dt><dd data-countup="<?= $totalMain ?>">0</dd></div>
            <div class="metric"><dt><?= __('Skor Tertinggi') ?></dt><dd data-countup="<?= $bestOverall ?>">0</dd></div>
            <div class="metric"><dt><?= __('Kategori Dimainkan') ?></dt><dd data-countup="<?= count($scores) ?>">0</dd></div>
        </div>
    </div>
</section>

<!-- Skor per kategori -->
<?php if (!empty($scores)): ?>
<section class="panel section-tight reveal">
    <header>
        <h2><?= __('Skor per Kategori') ?></h2>
    </header>
    <div class="panel-body">
        <ul class="list-tile">
            <?php foreach ($scores as $s): ?>
            <li>
                <span class="tile-icon"><?= e(mb_strtoupper(mb_substr($s['nama_kategori'], 0, 1))) ?></span>
                <div class="tile-main">
                    <div class="tile-title"><?= e($s['nama_kategori']) ?></div>
                    <div class="tile-sub"><?= (int)$s['jumlah_main'] ?>x main &middot; terakhir <?= date('d M Y', strtotime($s['terakhir_main'])) ?></div>
                </div>
                <span class="badge-chip chip-gold"><?= (int)$s['best_poin'] ?> poin</span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>

<!-- Riwayat -->
<?php if (!empty($activity)): ?>
<section class="panel section-tight reveal">
    <header>
        <h2><?= __('Riwayat Permainan Terbaru') ?></h2>
    </header>
    <div class="panel-body">
        <ul class="list-tile">
            <?php foreach ($activity as $a): ?>
            <li>
                <span class="tile-icon">&#127919;</span>
                <div class="tile-main">
                    <div class="tile-title"><?= e($a['nama_kategori']) ?></div>
                    <div class="tile-sub"><?= date('d M Y H:i', strtotime($a['tanggal_main'])) ?></div>
                </div>
                <span class="lb-score"><?= (int)$a['total_poin'] ?> poin</span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>

<!-- Zona berbahaya -->
<section class="panel danger-zone section-tight reveal">
    <header>
        <h2><?= __('Zona Berbahaya') ?></h2>
        <p><?= __('Tindakan di sini bersifat permanen.') ?></p>
    </header>
    <div class="panel-body">
        <div class="btn-row">
            <a class="btn btn-danger" href="logout.php"><?= __('Keluar dari akun') ?></a>
        </div>
    </div>
</section>

<?php mp_foot(['base' => '../']); ?>
