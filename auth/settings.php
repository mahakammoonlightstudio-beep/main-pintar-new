<?php
/**
 * Main Pintar - auth/settings.php
 * Pusat Pengaturan untuk user/pekerja:
 *  - Preferensi: bahasa & tema tampilan (tersimpan ke akun, ikut saat login di perangkat mana pun)
 *  - Data pekerja: jabatan, unit kerja, nomor WhatsApp (untuk laporan admin)
 *  - Pintasan ke profil (nama & password) dan notifikasi
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';

if (!mp_is_logged_in()) {
    header('Location: login.php?redirect=' . urlencode('settings.php'));
    exit;
}

$pdo = db();
$uid = mp_get_user_id();
$msg = $_GET['msg'] ?? '';
$error = '';

// ===== Simpan preferensi (bahasa & tema) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_prefs'])) {
    csrf_verify();
    $bahasa = in_array($_POST['bahasa'] ?? 'id', ['id', 'en'], true) ? $_POST['bahasa'] : 'id';
    $tema = in_array($_POST['tema'] ?? '', ['system', 'light', 'dark'], true) ? $_POST['tema'] : '';

    $pdo->prepare("UPDATE users SET bahasa = ?, tema = ? WHERE id = ?")->execute([$bahasa, $tema, $uid]);
    $_SESSION['user_bahasa'] = $bahasa;
    $_SESSION['user_tema'] = $tema;
    @setcookie('mp_lang', $bahasa, [
        'expires'  => time() + 365 * 24 * 60 * 60,
        'path'     => '/',
        'samesite' => 'Lax',
        'httponly' => true,
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    ]);
    header('Location: settings.php?msg=prefs');
    exit;
}

// ===== Simpan data pekerja =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_pekerja'])) {
    csrf_verify();
    $jabatan = trim($_POST['jabatan'] ?? '');
    $unit = trim($_POST['unit_kerja'] ?? '');
    $wa = trim($_POST['no_whatsapp'] ?? '');

    if (mb_strlen($jabatan) > 100 || mb_strlen($unit) > 100) {
        $error = __('Data terlalu panjang (maks 100 karakter).');
    } elseif ($wa !== '' && !preg_match('/^[0-9+\-\s]{6,20}$/', $wa)) {
        $error = __('Nomor WhatsApp tidak valid.');
    } else {
        $pdo->prepare("UPDATE users SET jabatan = ?, unit_kerja = ?, no_whatsapp = ? WHERE id = ?")
            ->execute([
                $jabatan !== '' ? $jabatan : null,
                $unit !== '' ? $unit : null,
                $wa !== '' ? $wa : null,
                $uid,
            ]);
        header('Location: settings.php?msg=pekerja');
        exit;
    }
}

// Data user saat ini
$stU = $pdo->prepare("SELECT username, bahasa, tema, jabatan, unit_kerja, no_whatsapp FROM users WHERE id = ? LIMIT 1");
$stU->execute([$uid]);
$u = $stU->fetch() ?: [];
$bahasaUser = $u['bahasa'] ?: mp_lang();
$temaUser = $u['tema'] ?? '';

$nama = mp_get_user_nama();
$inisial = mb_strtoupper(mb_substr($nama, 0, 1));
if ($inisial === '' || $inisial === null) $inisial = '?';

$scoreRow = mp_get_user_scores($pdo, $uid);
$totalSkor = (int)array_sum(array_column($scoreRow, 'best_poin'));

mp_head([
    'title' => __('Pengaturan'),
    'active' => 'settings',
    'base' => '../',
]);
?>
<div class="page-head">
    <h1><?= __('Pengaturan') ?></h1>
    <p><?= __('Atur preferensi tampilan, bahasa, dan data dirimu di sini.') ?></p>
</div>

<?php if ($msg === 'prefs'): ?>
    <p class="notice notice-ok mt-8" role="status"><?= __('Preferensi berhasil disimpan.') ?></p>
<?php elseif ($msg === 'pekerja'): ?>
    <p class="notice notice-ok mt-8" role="status"><?= __('Data pekerja berhasil disimpan.') ?></p>
<?php elseif ($error): ?>
    <p class="notice notice-error mt-8" role="alert"><?= e($error) ?></p>
<?php endif; ?>

<!-- Hero ringkas -->
<div class="profile-hero reveal">
    <span class="avatar-badge<?= $totalSkor >= 1000 ? ' gold' : '' ?>"><?= e($inisial) ?></span>
    <div class="profile-hero-main">
        <div class="profile-hero-name"><?= e($nama) ?></div>
        <div class="profile-hero-sub">@<?= e($u['username'] ?? '') ?></div>
    </div>
    <div class="profile-hero-stats">
        <div class="profile-hero-stat"><b><?= $totalSkor ?></b><span><?= __('Total Skor') ?></span></div>
    </div>
</div>

<div class="grid-2 section-tight">
    <!-- Preferensi -->
    <section class="panel reveal">
        <header>
            <h2><?= __('Preferensi Saya') ?></h2>
            <p><?= __('Bahasa dan tema tersimpan ke akunmu, jadi tampilan selalu sama di perangkat mana pun.') ?></p>
        </header>
        <div class="panel-body">
            <form method="POST">
                <?= csrf_field() ?>
                <div class="field">
                    <label class="field-label" for="bahasa"><?= __('Bahasa') ?></label>
                    <select id="bahasa" name="bahasa">
                        <?php foreach (mp_lang_options() as $kode => $label): ?>
                            <option value="<?= $kode ?>"<?= $bahasaUser === $kode ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="hint"><?= __('Dipakai untuk seluruh teks aplikasi.') ?></span>
                </div>
                <div class="field">
                    <label class="field-label" for="tema"><?= __('Tema Tampilan') ?></label>
                    <select id="tema" name="tema">
                        <option value="system"<?= $temaUser === 'system' || $temaUser === '' ? ' selected' : '' ?>><?= __('Mengikuti Sistem') ?></option>
                        <option value="light"<?= $temaUser === 'light' ? ' selected' : '' ?>><?= __('Mode Terang') ?></option>
                        <option value="dark"<?= $temaUser === 'dark' ? ' selected' : '' ?>><?= __('Mode Gelap') ?></option>
                    </select>
                </div>
                <button class="btn btn-primary" name="save_prefs" value="1" type="submit"><?= __('Simpan') ?></button>
            </form>
        </div>
    </section>

    <!-- Data pekerja -->
    <section class="panel reveal">
        <header>
            <h2><?= __('Data Pekerja') ?></h2>
            <p><?= __('Lengkapi data ini agar admin dapat mengenalmu di laporan hasil kuis.') ?></p>
        </header>
        <div class="panel-body">
            <form method="POST">
                <?= csrf_field() ?>
                <div class="field">
                    <label class="field-label" for="jabatan"><?= __('Jabatan') ?></label>
                    <input id="jabatan" type="text" name="jabatan" maxlength="100"
                           value="<?= e($u['jabatan'] ?? '') ?>" placeholder="<?= __('cth: Arsiparis Muda') ?>">
                </div>
                <div class="field">
                    <label class="field-label" for="unit_kerja"><?= __('Unit Kerja') ?></label>
                    <input id="unit_kerja" type="text" name="unit_kerja" maxlength="100"
                           value="<?= e($u['unit_kerja'] ?? '') ?>" placeholder="<?= __('cth: Bidang P2A') ?>">
                </div>
                <div class="field">
                    <label class="field-label" for="no_whatsapp"><?= __('No. WhatsApp') ?></label>
                    <input id="no_whatsapp" type="tel" name="no_whatsapp" maxlength="20"
                           value="<?= e($u['no_whatsapp'] ?? '') ?>" placeholder="<?= __('cth: 0812xxxxxxx') ?>">
                </div>
                <button class="btn btn-primary" name="save_pekerja" value="1" type="submit"><?= __('Simpan') ?></button>
            </form>
        </div>
    </section>
</div>

<!-- Pintasan -->
<section class="panel section-tight reveal">
    <header>
        <h2><?= __('Pintasan') ?></h2>
    </header>
    <div class="panel-body">
        <div class="btn-row">
            <a class="btn btn-quiet" href="profile.php"><?= __('Informasi Akun') ?></a>
            <a class="btn btn-quiet" href="notifikasi.php"><?= __('Notifikasi') ?></a>
            <a class="btn btn-quiet" href="stats.php"><?= __('Statistik') ?></a>
        </div>
    </div>
</section>

<?php mp_foot(['base' => '../']); ?>
