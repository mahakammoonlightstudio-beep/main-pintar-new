<?php
/**
 * Main Pintar - join.php (OPTIMIZED)
 * Gabung ke sesi live dengan kode ruangan.
 * Support: logged-in users, anonymous guests.
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/session.php';

$pdo = db();
$error = '';

// Ensure guest session exists
$guestUid = mp_start_guest_session();
if ($guestUid === 0) {
    $error = 'Gagal membuat sesi tamu. Periksa koneksi database.';
}

mp_maintenance_gate();
mp_boot_lang();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $kode = strtoupper(trim($_POST['kode'] ?? ''));
    $nickname = trim($_POST['nickname'] ?? '');

    if ($kode === '' || $nickname === '') {
        $error = __('Kode ruangan') . ' & ' . __('Nickname') . ' wajib diisi.';
    } else {
        $st = $pdo->prepare("SELECT * FROM sesi_kuis WHERE kode_ruangan = ? LIMIT 1");
        $st->execute([$kode]);
        $sesi = $st->fetch();

        if (!$sesi) {
            $error = __('Kode Ruangan') . ' tidak ditemukan.';
        } elseif ($sesi['status'] !== 'menunggu') {
            $error = __('Sesi ini sudah dimulai atau selesai.');
        } else {
            // Get or create user with nickname (upgrades guest to peserta)
            $uid = mp_get_or_create_peserta($nickname);

            // Simpan sesi yang diikuti
            $_SESSION['live_sesi_id'] = (int)$sesi['id'];

            // Pastikan tidak duplikat di peserta_sesi (unique index handles this)
            $stI = $pdo->prepare(
                "INSERT IGNORE INTO peserta_sesi (sesi_id, user_id, bergabung_at) VALUES (?, ?, NOW())"
            );
            $stI->execute([(int)$sesi['id'], $uid]);

            header('Location: main.php?live=1&sesi=' . (int)$sesi['id']);
            exit;
        }
    }
}

mp_head([
    'title' => __('Gabung Kuis Live'),
    'active' => '',
]);
?>
<div class="page-head">
    <h1><?= __('Gabung Kuis Live') ?></h1>
    <p><?= __('Masukkan kode ruangan dari host untuk bergabung') ?></p>
</div>

<?php if ($error): ?>
    <p class="notice notice-error" role="alert"><?= e($error) ?></p>
<?php endif; ?>

<div class="join-form">
    <form method="POST">
        <?= csrf_field() ?>
        <div class="field">
            <label class="field-label" for="kode"><?= __('Kode Ruangan') ?></label>
            <input id="kode" type="text" name="kode" placeholder="ABC123" required maxlength="6"
                   style="letter-spacing:0.3em;text-transform:uppercase;font-family:monospace;" autofocus>
        </div>
        <div class="field">
            <label class="field-label" for="nickname"><?= __('Nickname') ?></label>
            <input id="nickname" type="text" name="nickname" placeholder="<?= __('Nama panggilan') ?>" required maxlength="20">
        </div>
        <div class="btn-row">
            <button class="btn btn-primary w-full" type="submit"><?= __('Gabung') ?></button>
        </div>
    </form>
</div>

<?php mp_foot(); ?>
