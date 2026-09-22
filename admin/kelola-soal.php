<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';

if (!mp_is_admin()) {
    header('Location: login.php');
    exit;
}

$pdo = db();
$kats = $pdo->query("SELECT id, nama_kategori FROM kategori ORDER BY id ASC")->fetchAll();

// Tambah soal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah'])) {
    csrf_verify();
    $katId = (int)($_POST['kategori_id'] ?? 0);
    $pertanyaan = trim($_POST['pertanyaan'] ?? '');
    $a = trim($_POST['pilihan_a'] ?? '');
    $b = trim($_POST['pilihan_b'] ?? '');
    $c = trim($_POST['pilihan_c'] ?? '');
    $d = trim($_POST['pilihan_d'] ?? '');
    $jawaban = strtoupper(trim($_POST['jawaban'] ?? ''));
    $penjelasan = trim($_POST['penjelasan'] ?? '');
    $poin = max(1, (int)($_POST['poin'] ?? 100));
    $waktu = max(5, (int)($_POST['waktu_jawab'] ?? 20));

    if ($katId && $pertanyaan && $a && $b && $c && $d && in_array($jawaban, ['A','B','C','D'], true)) {
        $st = $pdo->prepare(
            "INSERT INTO soal (kategori_id, pertanyaan, pilihan_a, pilihan_b, pilihan_c, pilihan_d, jawaban_benar, penjelasan, poin, waktu_jawab)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $st->execute([$katId, $pertanyaan, $a, $b, $c, $d, $jawaban, $penjelasan, $poin, $waktu]);
        header('Location: kelola-soal.php?msg=added');
        exit;
    }
}

// Hapus soal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus'])) {
    csrf_verify();
    $pdo->prepare("DELETE FROM soal WHERE id = ?")->execute([(int)($_POST['soal_id'] ?? 0)]);
    header('Location: kelola-soal.php?msg=deleted');
    exit;
}

// Toggle aktif soal (nonaktifkan tanpa menghapus)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_aktif'])) {
    csrf_verify();
    $pdo->prepare("UPDATE soal SET aktif = 1 - aktif WHERE id = ?")->execute([(int)($_POST['soal_id'] ?? 0)]);
    header('Location: kelola-soal.php?msg=toggled');
    exit;
}

// Ambil semua soal dengan nama kategori
$soalList = $pdo->query(
    "SELECT q.*, k.nama_kategori
     FROM soal q
     LEFT JOIN kategori k ON k.id = q.kategori_id
     ORDER BY q.kategori_id ASC, q.id ASC"
)->fetchAll();

$msg = $_GET['msg'] ?? '';

mp_head([
    'title' => 'Kelola Soal',
    'active' => 'soal',
    'body_class' => 'mp-admin',
    'base' => '../',
]);
?>
<div class="page-head">
    <h1>Kelola Soal</h1>
    <p><?= count($soalList) ?> soal tersedia</p>
    <div class="page-head-actions">
        <a class="btn btn-quiet" href="dashboard.php">Kembali ke Dasbor</a>
    </div>
</div>

<?php if ($msg === 'added'): ?>
    <p class="notice notice-ok" role="status">Soal berhasil ditambahkan.</p>
<?php elseif ($msg === 'deleted'): ?>
    <p class="notice notice-ok" role="status">Soal berhasil dihapus.</p>
<?php elseif ($msg === 'toggled'): ?>
    <p class="notice notice-ok" role="status">Status soal diperbarui. Soal nonaktif tidak akan muncul dalam kuis.</p>
<?php endif; ?>

<section class="panel mt-16">
    <header>
        <h2>Tambah Soal Baru</h2>
    </header>
    <div class="panel-body">
        <form method="POST">
            <?= csrf_field() ?>
            <div class="field">
                <label class="field-label" for="kategori_id">Kategori</label>
                <select id="kategori_id" name="kategori_id" required>
                    <option value="">-- Pilih kategori --</option>
                    <?php foreach ($kats as $k): ?>
                        <option value="<?= (int)$k['id'] ?>"><?= e($k['nama_kategori']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field-label" for="pertanyaan">Pertanyaan</label>
                <textarea id="pertanyaan" name="pertanyaan" rows="3" required></textarea>
            </div>
            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;">
                <div class="field"><label class="field-label">Opsi A</label><input type="text" name="pilihan_a" required></div>
                <div class="field"><label class="field-label">Opsi B</label><input type="text" name="pilihan_b" required></div>
                <div class="field"><label class="field-label">Opsi C</label><input type="text" name="pilihan_c" required></div>
                <div class="field"><label class="field-label">Opsi D</label><input type="text" name="pilihan_d" required></div>
            </div>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:16px;">
                <div class="field">
                    <label class="field-label" for="jawaban">Kunci Jawaban</label>
                    <select id="jawaban" name="jawaban" required>
                        <option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option>
                    </select>
                </div>
                <div class="field">
                    <label class="field-label">Poin</label>
                    <input type="number" name="poin" value="100" min="1" max="1000">
                </div>
                <div class="field">
                    <label class="field-label">Waktu (detik)</label>
                    <input type="number" name="waktu_jawab" value="20" min="5" max="120">
                </div>
            </div>
            <div class="field" style="margin-top:16px;">
                <label class="field-label">Penjelasan (opsional)</label>
                <textarea name="penjelasan" rows="2" placeholder="Penjelasan singkat mengapa jawaban ini benar..."></textarea>
            </div>
            <div class="btn-row" style="margin-top:16px;">
                <button class="btn btn-primary" name="tambah" value="1">Tambah Soal</button>
            </div>
        </form>
    </div>
</section>

<section class="panel mt-16">
    <header>
        <h2>Daftar Soal</h2>
        <p>Tekan / untuk mencari</p>
    </header>
    <div class="panel-body panel-flush">
        <input type="search" data-table-search="soalTable" placeholder="Cari soal..." style="margin-bottom:var(--s4);" autocomplete="off">
        <div class="table-scroll">
            <table class="data" id="soalTable">
                <thead>
                    <tr><th class="num">#</th><th>Kategori</th><th>Pertanyaan</th><th>Kunci</th><th class="num">Poin</th><th class="num">Waktu</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                <?php if (empty($soalList)): ?>
                    <tr><td colspan="8" class="empty">Belum ada soal.</td></tr>
                <?php else: $no = 1; foreach ($soalList as $q): ?>
                    <tr>
                        <td class="num"><?= $no++ ?></td>
                        <td><span class="tag"><?= e($q['nama_kategori'] ?? '-') ?></span></td>
                        <td><?= e(mb_substr($q['pertanyaan'], 0, 80)) ?><?= mb_strlen($q['pertanyaan']) > 80 ? '…' : '' ?></td>
                        <td><span class="tag tag-ok"><?= e($q['jawaban_benar']) ?></span></td>
                        <td class="num"><?= (int)$q['poin'] ?></td>
                        <td class="num"><?= (int)$q['waktu_jawab'] ?>s</td>
                        <td>
                            <?php $aktif = !isset($q['aktif']) || (int)$q['aktif'] === 1; ?>
                            <span class="tag <?= $aktif ? 'tag-ok' : 'tag-error' ?>"><?= $aktif ? 'Aktif' : 'Nonaktif' ?></span>
                        </td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="soal_id" value="<?= (int)$q['id'] ?>">
                                <button class="btn-sm btn-quiet" name="toggle_aktif" value="1"><?= $aktif ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                            </form>
                            <form method="POST" onsubmit="return confirm('Hapus soal ini?')" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="soal_id" value="<?= (int)$q['id'] ?>">
                                <button class="btn-sm btn-danger" name="hapus" value="1">Hapus</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php mp_foot(['base' => '../']); ?>