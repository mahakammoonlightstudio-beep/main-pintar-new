<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';

if (!mp_is_admin()) {
    header('Location: login.php');
    exit;
}

$pdo = db();
$sesiId = (int)($_GET['sesi'] ?? 0);
if ($sesiId <= 0) {
    header('Location: dashboard.php');
    exit;
}

$st = $pdo->prepare(
    "SELECT s.*, k.nama_kategori,
            (SELECT COUNT(*) FROM soal q WHERE q.kategori_id = s.kategori_id) AS total_soal
     FROM sesi_kuis s
     LEFT JOIN kategori k ON k.id = s.kategori_id
     WHERE s.id = ? LIMIT 1"
);
$st->execute([$sesiId]);
$sesi = $st->fetch();
if (!$sesi) {
    header('Location: dashboard.php');
    exit;
}

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=hasil-mainpintar-' . $sesi['kode_ruangan'] . '.csv');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, ['Peringkat', 'Nama Peserta', 'Username', 'Jabatan', 'Unit Kerja', 'No. WhatsApp', 'Total Poin', 'Jawaban Benar', 'Soal Dijawab']);
    $stE = $pdo->prepare(
        "SELECT u.nama_lengkap, u.username, u.jabatan, u.unit_kerja, u.no_whatsapp,
                SUM(j.poin_didapat) AS total_poin,
                SUM(j.benar) AS jumlah_benar,
                COUNT(*) AS jumlah_jawab
         FROM jawaban j JOIN users u ON u.id = j.user_id
         WHERE j.sesi_id = ?
         GROUP BY j.user_id, u.nama_lengkap, u.username, u.jabatan, u.unit_kerja, u.no_whatsapp
         ORDER BY total_poin DESC"
    );
    $stE->execute([$sesiId]);
    $rank = 1;
    foreach ($stE->fetchAll() as $row) {
        fputcsv($out, [
            $rank++,
            $row['nama_lengkap'],
            $row['username'],
            $row['jabatan'] ?? '',
            $row['unit_kerja'] ?? '',
            $row['no_whatsapp'] ?? '',
            $row['total_poin'],
            (int)$row['jumlah_benar'],
            (int)$row['jumlah_jawab'],
        ]);
    }
    fclose($out);
    exit;
}

mp_head([
    'title' => 'Monitor Sesi',
    'active' => 'hasil',
    'body_class' => 'mp-admin',
    'base' => '../',
]);
?>
<div class="page-head">
    <h1>Monitor Sesi Live</h1>
    <p>Kategori: <?= e($sesi['nama_kategori'] ?? '-') ?> &middot; <?= (int)$sesi['total_soal'] ?> soal</p>
    <div class="page-head-actions">
        <a class="btn btn-quiet" href="dashboard.php">Kembali ke Dasbor</a>
        <a class="btn btn-quiet" href="lihat-hasil.php?sesi=<?= $sesiId ?>&export=csv">Export CSV</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
            <div>
                <dt class="muted">Kode Ruangan</dt>
                <dd class="mono" style="font-size:var(--t-3xl);font-weight:700;color:var(--purple-700);letter-spacing:0.15em;"><?= e($sesi['kode_ruangan']) ?></dd>
            </div>
            <button type="button" class="btn btn-quiet" data-copy="<?= e($sesi['kode_ruangan']) ?>">Salin Kode</button>
            <span class="tag tag-info" id="hostStatus">Memuat…</span>
        </div>
        <p class="muted mt-8">Bagikan kode ini ke peserta. Mereka masuk via <a href="../join.php">join.php</a> dengan nickname.</p>
    </div>
</div>

<div class="panel mt-16">
    <div class="panel-body">
        <div class="btn-row">
            <button type="button" class="btn btn-primary" id="btnStart">Mulai Kuis</button>
            <button type="button" class="btn btn-gold" id="btnNext">Lanjut Soal Berikutnya</button>
            <button type="button" class="btn btn-danger" id="btnFinish">Selesai & Simpan Skor</button>
        </div>
        <p class="muted mt-8">Polling tiap <?= POLL_INTERVAL_HOST / 1000 ?> detik. Soal & jawaban peserta tampil real-time.</p>
    </div>
</div>

<section class="panel mt-16" id="hostMonitor" data-sesi="<?= $sesiId ?>">
    <header>
        <h2>Soal Aktif</h2>
    </header>
    <div class="panel-body" id="hostSoal">
        <p class="empty">Memuat…</p>
    </div>
</section>

<section class="panel mt-16">
    <header>
        <h2>Peserta Bergabung</h2>
    </header>
    <div class="panel-body" id="hostPeserta">
        <p class="empty">Memuat…</p>
    </div>
</section>

<section class="panel mt-16">
    <header>
        <h2>Ranking Akhir</h2>
    </header>
    <div class="panel-body" id="hostRanking">
        <p class="empty">Ranking tampil setelah kuis selesai.</p>
    </div>
</section>

<?php mp_foot(['base' => '../']); ?>