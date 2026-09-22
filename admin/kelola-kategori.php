<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';

if (!mp_is_admin()) {
    header('Location: login.php');
    exit;
}

$pdo = db();

// Tambah kategori
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah'])) {
    csrf_verify();
    $nama = trim($_POST['nama_kategori'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $warna = trim($_POST['warna_tema'] ?? '#46178f');

    if ($nama !== '') {
        $st = $pdo->prepare(
            "INSERT INTO kategori (nama_kategori, deskripsi, warna_tema) VALUES (?, ?, ?)"
        );
        $st->execute([$nama, $deskripsi, $warna]);
        header('Location: kelola-kategori.php?msg=added');
        exit;
    }
}

// Toggle aktif kategori (arsipkan tanpa hapus)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_aktif'])) {
    csrf_verify();
    $id = (int)($_POST['kategori_id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare("UPDATE kategori SET aktif = 1 - aktif WHERE id = ?")->execute([$id]);
        header('Location: kelola-kategori.php?msg=toggled');
    }
    exit;
}

// Hapus kategori
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus'])) {
    csrf_verify();
    $id = (int)($_POST['kategori_id'] ?? 0);
    if ($id > 0) {
        // Cek apakah ada soal di kategori ini
        $stC = $pdo->prepare("SELECT COUNT(*) FROM soal WHERE kategori_id = ?");
        $stC->execute([$id]);
        $ada = (int)$stC->fetchColumn() > 0;

        if (!$ada) {
            $pdo->prepare("DELETE FROM kategori WHERE id = ?")->execute([$id]);
            header('Location: kelola-kategori.php?msg=deleted');
        } else {
            header('Location: kelola-kategori.php?msg=has_soal');
        }
        exit;
    }
}

$katList = $pdo->query(
    "SELECT k.*, (SELECT COUNT(*) FROM soal q WHERE q.kategori_id = k.id) AS jumlah_soal
     FROM kategori k ORDER BY k.id ASC"
)->fetchAll();

$msg = $_GET['msg'] ?? '';

mp_head([
    'title' => 'Kelola Kategori',
    'active' => 'kategori',
    'body_class' => 'mp-admin',
    'base' => '../',
]);
?>
<div class="page-head">
    <h1>Kelola Kategori</h1>
    <p><?= count($katList) ?> kategori tersedia</p>
    <div class="page-head-actions">
        <a class="btn btn-quiet" href="dashboard.php">Kembali ke Dasbor</a>
    </div>
</div>

<?php if ($msg === 'added'): ?>
    <p class="notice notice-ok" role="status">Kategori berhasil ditambahkan.</p>
<?php elseif ($msg === 'deleted'): ?>
    <p class="notice notice-ok" role="status">Kategori berhasil dihapus.</p>
<?php elseif ($msg === 'toggled'): ?>
    <p class="notice notice-ok" role="status">Status kategori diperbarui. Kategori nonaktif disembunyikan dari peserta.</p>
<?php elseif ($msg === 'has_soal'): ?>
    <p class="notice notice-error" role="alert">Kategori masih memiliki soal. Hapus soal dulu.</p>
<?php endif; ?>

<div class="panel mt-16">
    <header>
        <h2>Tambah Kategori</h2>
    </header>
    <div class="panel-body">
        <form method="POST">
            <?= csrf_field() ?>
            <div class="field">
                <label class="field-label" for="nama">Nama Kategori</label>
                <input id="nama" type="text" name="nama_kategori" required placeholder="Contoh: Arsip Dinamis">
            </div>
            <div class="field">
                <label class="field-label" for="deskripsi">Deskripsi</label>
                <textarea id="deskripsi" name="deskripsi" rows="2"></textarea>
            </div>
            <div class="field">
                <label class="field-label" for="warna">Warna Tema</label>
                <input id="warna" type="color" name="warna_tema" value="#46178f">
            </div>
            <div class="btn-row">
                <button class="btn btn-primary" name="tambah" value="1">Tambah Kategori</button>
            </div>
        </form>
    </div>
</div>

<div class="table-scroll mt-16">
    <table class="data">
        <thead>
            <tr><th class="num">#</th><th>Nama</th><th>Deskripsi</th><th class="num">Soal</th><th>Warna</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
        <?php if (empty($katList)): ?>
            <tr><td colspan="7" class="empty">Belum ada kategori.</td></tr>
        <?php else: foreach ($katList as $i => $k): ?>
            <tr>
                <td class="num"><?= $i + 1 ?></td>
                <td><strong><?= e($k['nama_kategori']) ?></strong></td>
                <td><?= e(mb_substr($k['deskripsi'] ?? '', 0, 60)) ?></td>
                <td class="num"><?= (int)$k['jumlah_soal'] ?></td>
                <td><span style="display:inline-block;width:24px;height:24px;border-radius:4px;background:<?= e($k['warna_tema'] ?: '#46178f') ?>"></span> <code><?= e($k['warna_tema'] ?: '#46178f') ?></code></td>
                <td>
                    <?php $aktif = !isset($k['aktif']) || (int)$k['aktif'] === 1; ?>
                    <span class="tag <?= $aktif ? 'tag-ok' : 'tag-error' ?>"><?= $aktif ? 'Aktif' : 'Nonaktif' ?></span>
                </td>
                <td>
                    <form method="POST" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="kategori_id" value="<?= (int)$k['id'] ?>">
                        <button class="btn-sm btn-quiet" name="toggle_aktif" value="1"><?= $aktif ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                    </form>
                    <form method="POST" onsubmit="return confirm('Hapus kategori ini?')" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="kategori_id" value="<?= (int)$k['id'] ?>">
                        <button class="btn-sm btn-danger" name="hapus" value="1" <?= (int)$k['jumlah_soal'] > 0 ? 'disabled' : '' ?>>Hapus</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php mp_foot(['base' => '../']); ?>