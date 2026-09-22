<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';

if (!mp_is_admin()) {
    header('Location: login.php');
    exit;
}

$pdo = db();

// Statistik ringkas
$totalSoal   = (int)$pdo->query("SELECT COUNT(*) FROM soal")->fetchColumn();
$totalKategori = (int)$pdo->query("SELECT COUNT(*) FROM kategori")->fetchColumn();
$totalPeserta = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'peserta'")->fetchColumn();
$totalSkor   = (int)$pdo->query("SELECT COUNT(*) FROM skor")->fetchColumn();

// Soal paling sering salah (dari jawaban, benar=0)
$stSulit = $pdo->query(
    "SELECT s.pertanyaan, k.nama_kategori, COUNT(*) AS salah
     FROM jawaban j
     JOIN soal s ON s.id = j.soal_id
     JOIN kategori k ON k.id = s.kategori_id
     WHERE j.benar = 0
     GROUP BY s.id
     ORDER BY salah DESC
     LIMIT 5"
)->fetchAll();

// Rata-rata skor
$rata = (int)$pdo->query("SELECT ROUND(AVG(total_poin)) FROM skor")->fetchColumn();

// ===== Buat sesi baru (host) =====
$msg = $_GET['msg'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buat_sesi'])) {
    csrf_verify();
    $katId = (int)$_POST['kategori_id'];
    if ($katId > 0) {
        $kode = generate_kode_ruangan($pdo);
        $stI = $pdo->prepare(
            "INSERT INTO sesi_kuis (kode_ruangan, host_user_id, kategori_id, status, nomor_soal_sekarang, created_at)
             VALUES (?, ?, ?, 'menunggu', 0, NOW())"
        );
        $stI->execute([$kode, (int)$_SESSION['user_id'], $katId]);
        $sesiBaru = (int)$pdo->lastInsertId();
        header('Location: lihat-hasil.php?sesi=' . $sesiBaru);
        exit;
    }
}

// Daftar sesi terbaru
$sesiList = $pdo->query(
    "SELECT s.*, k.nama_kategori,
            (SELECT COUNT(*) FROM peserta_sesi ps WHERE ps.sesi_id = s.id) AS jumlah_peserta
     FROM sesi_kuis s
     LEFT JOIN kategori k ON k.id = s.kategori_id
     ORDER BY s.id DESC LIMIT 10"
)->fetchAll();

$kats = $pdo->query("SELECT id, nama_kategori FROM kategori ORDER BY id ASC")->fetchAll();

mp_head([
    'title' => 'Dashboard Admin',
    'active' => 'dashboard',
    'body_class' => 'mp-admin',
    'base' => '../',
]);
?>
<div class="page-head">
    <h1>Dashboard Admin</h1>
    <p>Kelola konten kuis dan pantau sesi live</p>
    <div class="page-head-actions">
        <a class="btn btn-quiet" href="../index.php">Buka Beranda</a>
        <a class="btn btn-gold" href="analytics.php">Lihat Analitik</a>
        <a class="btn btn-danger" href="../auth/logout.php">Keluar</a>
    </div>
</div>

<?php if ($msg): ?>
    <p class="notice notice-ok" role="status"><?= e($msg) ?></p>
<?php endif; ?>

<section class="metrics">
    <div class="metric"><dt>Soal</dt><dd><?= $totalSoal ?></dd></div>
    <div class="metric"><dt>Kategori</dt><dd><?= $totalKategori ?></dd></div>
    <div class="metric"><dt>Peserta</dt><dd><?= $totalPeserta ?></dd></div>
    <div class="metric"><dt>Sesi Dimainkan</dt><dd><?= $totalSkor ?></dd></div>
    <div class="metric"><dt>Rata-rata Skor</dt><dd><?= $rata ?></dd></div>
</section>

<div class="panel mt-16">
    <header>
        <h2>Buat Sesi Live (Host)</h2>
        <p>Buat kode ruangan untuk peserta bergabung secara real-time.</p>
    </header>
    <div class="panel-body">
        <form method="POST">
            <?= csrf_field() ?>
            <div class="field">
                <label class="field-label" for="kategori_id">Kategori Kuis</label>
                <select id="kategori_id" name="kategori_id" required>
                    <option value="">-- Pilih kategori --</option>
                    <?php foreach ($kats as $k): ?>
                        <option value="<?= (int)$k['id'] ?>"><?= e($k['nama_kategori']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="btn-row">
                <button class="btn btn-primary" type="submit" name="buat_sesi" value="1">Buat Kode Ruangan</button>
            </div>
        </form>
    </div>
</div>

<section class="mt-16">
    <div class="page-head">
        <h2>Sesi Terbaru</h2>
    </div>
    <div class="table-scroll">
        <table class="data">
            <thead>
                <tr><th>Kode</th><th>Kategori</th><th>Status</th><th class="num">Peserta</th><th>Dibuat</th><th></th></tr>
            </thead>
            <tbody>
            <?php if (empty($sesiList)): ?>
                <tr><td colspan="6" class="empty">Belum ada sesi.</td></tr>
            <?php else: foreach ($sesiList as $s): ?>
                <tr>
                    <td class="mono"><strong><?= e($s['kode_ruangan']) ?></strong></td>
                    <td><?= e($s['nama_kategori']) ?></td>
                    <td><span class="tag tag-<?= $s['status'] === 'selesai' ? 'ok' : ($s['status'] === 'berjalan' ? 'warn' : 'info') ?>"><?= e($s['status']) ?></span></td>
                    <td class="num"><?= (int)$s['jumlah_peserta'] ?></td>
                    <td><?= date('d M Y H:i', strtotime($s['created_at'])) ?></td>
                    <td>
                        <?php if ($s['status'] === 'menunggu'): ?>
                            <a class="btn-sm" href="lihat-hasil.php?sesi=<?= (int)$s['id'] ?>">Monitor</a>
                        <?php else: ?>
                            <a class="btn-sm btn-quiet" href="lihat-hasil.php?sesi=<?= (int)$s['id'] ?>">Lihat</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel mt-16">
    <header>
        <h2>Kelola Konten</h2>
        <p>Navigasi cepat untuk mengelola soal dan kategori.</p>
    </header>
    <div class="panel-body">
        <div class="btn-row">
            <a class="btn btn-quiet" href="analytics.php">Analitik Soal</a>
            <a class="btn btn-quiet" href="kelola-soal.php">Kelola Soal</a>
            <a class="btn btn-quiet" href="kelola-kategori.php">Kelola Kategori</a>
        </div>
    </div>
</section>

<?php if (!empty($stSulit)): ?>
<section class="panel mt-16">
    <header>
        <h2>Soal Paling Sering Salah</h2>
        <p>Dari data jawaban peserta (untuk evaluasi materi).</p>
    </header>
    <div class="panel-body">
        <ul class="lb-list">
            <?php foreach ($stSulit as $i => $q): ?>
            <li>
                <span class="lb-rank"><?= $i + 1 ?></span>
                <span class="lb-name"><?= e($q['pertanyaan']) ?><br><small class="muted"><?= e($q['nama_kategori']) ?></small></span>
                <span class="tag tag-error"><?= (int)$q['salah'] ?>x salah</span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>

<?php mp_foot(['base' => '../']); ?>