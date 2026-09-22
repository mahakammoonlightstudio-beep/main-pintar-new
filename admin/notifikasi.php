<?php
/**
 * Main Pintar - admin/notifikasi.php
 * Broadcast pengumuman: ke semua user, semua peserta, atau user tertentu.
 * Menyimpan ke tabel `notifikasi`; tampilkan riwayat broadcast global.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';

if (!mp_is_admin()) {
    header('Location: login.php');
    exit;
}

$pdo = db();
$okMsg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kirim'])) {
    csrf_verify();
    $target = $_POST['target'] ?? 'semua';
    $userId = (int)($_POST['user_id'] ?? 0);
    $judul = trim($_POST['judul'] ?? '');
    $isi = trim($_POST['pesan'] ?? '');
    $jenis = in_array($_POST['jenis'] ?? 'info', ['info', 'success', 'warning', 'danger'], true) ? $_POST['jenis'] : 'info';

    if ($judul === '' || $isi === '') {
        $error = __('Judul dan isi pesan wajib diisi.');
    } else {
        // Tentukan penerima
        $penerima = [];
        if ($target === 'user' && $userId > 0) {
            $st = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role IN ('peserta','admin')");
            $st->execute([$userId]);
            $penerima = $st->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $sql = $target === 'peserta'
                ? "SELECT id FROM users WHERE role = 'peserta'"
                : "SELECT id FROM users WHERE role IN ('peserta','admin')";
            $penerima = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        }

        if (empty($penerima)) {
            $error = __('Tidak ada penerima yang cocok.');
        } else {
            try {
                $stI = $pdo->prepare(
                    "INSERT INTO notifikasi (user_id, judul, pesan, jenis) VALUES (?, ?, ?, ?)"
                );
                $pdo->beginTransaction();
                foreach ($penerima as $pid) {
                    $stI->execute([(int)$pid, $judul, $isi, $jenis]);
                }
                $pdo->commit();
                $okMsg = __('Notifikasi berhasil dikirim ke :n pengguna.', ['n' => count($penerima)]);
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('[MainPintar] Broadcast failed: ' . $e->getMessage());
                $error = __('Gagal mengirim. Pastikan migrasi database sudah dijalankan (database-upgrade.sql).');
            }
        }
    }
}

// Daftar user untuk pilihan target
$users = $pdo->query(
    "SELECT id, nama_lengkap, username FROM users WHERE role IN ('peserta','admin') ORDER BY nama_lengkap ASC LIMIT 500"
)->fetchAll();

// Riwayat broadcast: kelompokkan notifikasi identik yang dikirim di menit yang sama
$riwayat = [];
try {
    $riwayat = $pdo->query(
        "SELECT judul, pesan, jenis, MAX(created_at) AS created_at, COUNT(*) AS jumlah
         FROM notifikasi
         GROUP BY judul, pesan, jenis, DATE_FORMAT(created_at, '%Y%m%d%H%i')
         HAVING COUNT(*) > 1
         ORDER BY created_at DESC LIMIT 20"
    )->fetchAll();
} catch (Throwable $e) {
    $riwayat = [];
}

mp_head([
    'title' => __('Kirim Notifikasi'),
    'active' => 'broadcast',
    'body_class' => 'mp-admin',
    'base' => '../',
]);
?>
<div class="page-head">
    <h1><?= __('Kirim Notifikasi') ?></h1>
    <p><?= __('Kirim pengumuman ke seluruh peserta atau user tertentu.') ?></p>
</div>

<?php if ($okMsg): ?>
    <p class="notice notice-ok" role="status"><?= e($okMsg) ?></p>
<?php elseif ($error): ?>
    <p class="notice notice-error" role="alert"><?= e($error) ?></p>
<?php endif; ?>

<section class="panel mt-8 reveal">
    <header>
        <h2><?= __('Pengumuman Baru') ?></h2>
    </header>
    <div class="panel-body">
        <form method="POST">
            <?= csrf_field() ?>
            <div class="grid-2">
                <div class="field">
                    <label class="field-label" for="target"><?= __('Penerima') ?></label>
                    <select id="target" name="target" data-toggle-target>
                        <option value="semua"><?= __('Semua pengguna') ?></option>
                        <option value="peserta"><?= __('Semua peserta') ?></option>
                        <option value="user"><?= __('User tertentu') ?></option>
                    </select>
                </div>
                <div class="field" id="field-user" hidden>
                    <label class="field-label" for="user_id"><?= __('Pilih user') ?></label>
                    <select id="user_id" name="user_id">
                        <option value="">-- <?= __('Pilih user') ?> --</option>
                        <?php foreach ($users as $usr): ?>
                            <option value="<?= (int)$usr['id'] ?>"><?= e($usr['nama_lengkap']) ?> (@<?= e($usr['username']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field-label" for="jenis"><?= __('Jenis') ?></label>
                    <select id="jenis" name="jenis">
                        <option value="info"><?= __('Informasi') ?></option>
                        <option value="success"><?= __('Sukses') ?></option>
                        <option value="warning"><?= __('Peringatan') ?></option>
                        <option value="danger"><?= __('Penting') ?></option>
                    </select>
                </div>
            </div>
            <div class="field">
                <label class="field-label" for="judul"><?= __('Judul Notifikasi') ?></label>
                <input id="judul" type="text" name="judul" maxlength="150" required placeholder="<?= __('cth: Jadwal kuis live bulan depan') ?>">
            </div>
            <div class="field">
                <label class="field-label" for="pesan"><?= __('Isi Pesan') ?></label>
                <textarea id="pesan" name="pesan" rows="4" required placeholder="<?= __('Tulis isi pengumuman di sini…') ?>"></textarea>
            </div>
            <div class="btn-row">
                <button class="btn btn-primary" name="kirim" value="1" type="submit"><?= __('Kirim Notifikasi') ?></button>
            </div>
        </form>
    </div>
</section>

<?php if (!empty($riwayat)): ?>
<section class="panel mt-16 reveal">
    <header>
        <h2><?= __('Riwayat Broadcast') ?></h2>
    </header>
    <div class="panel-body panel-flush">
        <ul class="list-tile">
            <?php foreach ($riwayat as $r): ?>
            <li>
                <span class="tile-icon jenis-<?= e($r['jenis']) ?>">&#128226;</span>
                <div class="tile-main">
                    <div class="tile-title"><?= e($r['judul']) ?></div>
                    <div class="tile-sub"><?= date('d M Y H:i', strtotime($r['created_at'])) ?></div>
                </div>
                <span class="tag"><?= (int)$r['jumlah'] ?> <?= __('penerima') ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php elseif (!$okMsg): ?>
<p class="muted mt-16 text-center"><?= __('Belum ada broadcast terkirim.') ?></p>
<?php endif; ?>

<?php mp_foot(['base' => '../']); ?>
