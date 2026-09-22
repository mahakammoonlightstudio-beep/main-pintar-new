<?php
/**
 * Main Pintar - admin/pengaturan.php
 * Pengaturan Sistem: identitas aplikasi, registrasi, mode pemeliharaan,
 * bahasa default. Nilai disimpan ke tabel `settings`.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';

if (!mp_is_admin()) {
    header('Location: login.php');
    exit;
}

$pdo = db();
$msg = $_GET['msg'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    csrf_verify();

    $appName = trim($_POST['app_name'] ?? '');
    $tagline = trim($_POST['app_tagline'] ?? '');
    $defaultLang = in_array($_POST['default_lang'] ?? 'id', ['id', 'en'], true) ? $_POST['default_lang'] : 'id';
    $allowReg = isset($_POST['allow_registration']) ? '1' : '0';
    $maintenance = isset($_POST['maintenance_mode']) ? '1' : '0';
    $maintMsg = trim($_POST['maintenance_message'] ?? '');

    try {
        setting_set('app_name', $appName !== '' ? $appName : APP_NAME);
        setting_set('app_tagline', $tagline !== '' ? $tagline : APP_TAGLINE);
        setting_set('default_lang', $defaultLang);
        setting_set('allow_registration', $allowReg);
        setting_set('maintenance_mode', $maintenance);
        setting_set('maintenance_message', $maintMsg !== '' ? $maintMsg : 'Sistem sedang dalam pemeliharaan. Silakan kembali lagi nanti.');
        header('Location: pengaturan.php?msg=saved');
    } catch (Throwable $e) {
        error_log('[MainPintar] Save settings failed: ' . $e->getMessage());
        header('Location: pengaturan.php?msg=error');
    }
    exit;
}

mp_head([
    'title' => __('Pengaturan Sistem'),
    'active' => 'pengaturan',
    'body_class' => 'mp-admin',
    'base' => '../',
]);
?>
<div class="page-head">
    <h1><?= __('Pengaturan Sistem') ?></h1>
    <p><?= __('Konfigurasi tampilan dan perilaku aplikasi.') ?></p>
</div>

<?php if ($msg === 'saved'): ?>
    <p class="notice notice-ok" role="status"><?= __('Pengaturan berhasil disimpan.') ?></p>
<?php elseif ($msg === 'error'): ?>
    <p class="notice notice-error" role="alert"><?= __('Gagal menyimpan pengaturan. Pastikan migrasi database sudah dijalankan (database-upgrade.sql).') ?></p>
<?php endif; ?>

<form method="POST">
    <?= csrf_field() ?>

    <section class="panel mt-8 reveal">
        <header>
            <h2><?= __('Identitas Aplikasi') ?></h2>
        </header>
        <div class="panel-body">
            <div class="field">
                <label class="field-label" for="app_name"><?= __('Nama Aplikasi') ?></label>
                <input id="app_name" type="text" name="app_name" maxlength="60" value="<?= e(setting('app_name', APP_NAME)) ?>" required>
                <span class="hint"><?= __('Nama aplikasi yang tampil di header dan judul browser.') ?></span>
            </div>
            <div class="field">
                <label class="field-label" for="app_tagline"><?= __('Tagline') ?></label>
                <input id="app_tagline" type="text" name="app_tagline" maxlength="120" value="<?= e(setting('app_tagline', APP_TAGLINE)) ?>">
                <span class="hint"><?= __('Tagline di halaman beranda.') ?></span>
            </div>
            <div class="field">
                <label class="field-label" for="default_lang"><?= __('Bahasa') ?> (<?= __('default') ?>)</label>
                <select id="default_lang" name="default_lang">
                    <?php foreach (mp_lang_options() as $kode => $label): ?>
                        <option value="<?= $kode ?>"<?= setting('default_lang', 'id') === $kode ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="hint"><?= __('Bahasa default pengunjung baru.') ?></span>
            </div>
        </div>
    </section>

    <section class="panel mt-16 reveal">
        <header>
            <h2><?= __('Perilaku Aplikasi') ?></h2>
        </header>
        <div class="panel-body">
            <div class="field agree">
                <input type="checkbox" id="allow_registration" name="allow_registration"<?= registration_enabled() ? ' checked' : '' ?>>
                <label for="allow_registration"><?= __('Izinkan registrasi akun baru') ?></label>
            </div>
            <p class="hint" style="margin:-4px 0 var(--s4) 32px;"><?= __('Saat mati, halaman pendaftaran ditutup dan tombol Daftar disembunyikan.') ?></p>

            <div class="field agree">
                <input type="checkbox" id="maintenance_mode" name="maintenance_mode"<?= maintenance_enabled() ? ' checked' : '' ?>>
                <label for="maintenance_mode"><?= __('Mode pemeliharaan') ?></label>
            </div>
            <p class="hint" style="margin:-4px 0 var(--s4) 32px;"><?= __('Saat aktif, hanya admin yang dapat mengakses aplikasi.') ?></p>

            <div class="field">
                <label class="field-label" for="maintenance_message"><?= __('Pesan pemeliharaan') ?></label>
                <textarea id="maintenance_message" name="maintenance_message" rows="2" maxlength="300"><?= e(setting('maintenance_message', 'Sistem sedang dalam pemeliharaan. Silakan kembali lagi nanti.')) ?></textarea>
            </div>

            <div class="btn-row">
                <button class="btn btn-primary" name="save_settings" value="1" type="submit"><?= __('Simpan') ?></button>
            </div>
        </div>
    </section>
</form>

<?php mp_foot(['base' => '../']); ?>
