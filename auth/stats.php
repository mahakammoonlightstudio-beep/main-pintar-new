<?php
/**
 * Main Pintar - auth/stats.php
 * Statistik detail pengguna: grafik performa, heatmap kategori, tren skor.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';

if (!mp_is_logged_in()) {
    header('Location: login.php?redirect=' . urlencode('stats.php'));
    exit;
}

$pdo = db();
$uid = mp_get_user_id();

// Overall stats
$st = $pdo->prepare(
    "SELECT COUNT(*) AS total_main, SUM(total_poin) AS total_poin, AVG(total_poin) AS avg_poin, MAX(total_poin) AS best_poin
     FROM skor WHERE user_id = ?"
);
$st->execute([$uid]);
$overall = $st->fetch();

// Per category
$st = $pdo->prepare(
    "SELECT k.id, k.nama_kategori, k.warna_tema,
            COUNT(s.id) AS jumlah_main, MAX(s.total_poin) AS best_poin, AVG(s.total_poin) AS avg_poin,
            MAX(s.tanggal_main) AS terakhir_main
     FROM skor s
     JOIN kategori k ON k.id = s.kategori_id
     WHERE s.user_id = ?
     GROUP BY k.id, k.nama_kategori, k.warna_tema
     ORDER BY best_poin DESC"
);
$st->execute([$uid]);
$perKategori = $st->fetchAll();

// Recent activity (last 10)
$st = $pdo->prepare(
    "SELECT s.*, k.nama_kategori, k.warna_tema
     FROM skor s
     JOIN kategori k ON k.id = s.kategori_id
     WHERE s.user_id = ?
     ORDER BY s.tanggal_main DESC
     LIMIT 10"
);
$st->execute([$uid]);
$riwayat = $st->fetchAll();

// Monthly trend (last 6 months)
$st = $pdo->prepare(
    "SELECT DATE_FORMAT(tanggal_main, '%Y-%m') AS bulan, COUNT(*) AS main_count, AVG(total_poin) AS avg_poin, MAX(total_poin) AS max_poin
     FROM skor
     WHERE user_id = ? AND tanggal_main >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY DATE_FORMAT(tanggal_main, '%Y-%m')
     ORDER BY bulan ASC"
);
$st->execute([$uid]);
$trend = $st->fetchAll();

// Accuracy stats (from jawaban if available)
$st = $pdo->prepare(
    "SELECT 
        SUM(CASE WHEN j.benar = 1 THEN 1 ELSE 0 END) AS benar,
        COUNT(*) AS total,
        ROUND(SUM(CASE WHEN j.benar = 1 THEN 1 ELSE 0 END) / COUNT(*) * 100) AS akurasi
     FROM jawaban j
     JOIN sesi_kuis sk ON sk.id = j.sesi_id
     WHERE j.user_id = ?"
);
$st->execute([$uid]);
$accuracy = $st->fetch();

// Live session stats
$st = $pdo->prepare(
    "SELECT COUNT(*) AS live_count, SUM(s.total_poin) AS live_poin
     FROM skor s
     JOIN sesi_kuis sk ON sk.id = s.sesi_id
     WHERE s.user_id = ? AND s.sesi_id IS NOT NULL"
);
$st->execute([$uid]);
$liveStats = $st->fetch();

mp_head([
    'title' => 'Statistik Saya',
    'active' => 'profile',
    'base' => '../',
]);
?>
<div class="page-head">
    <h1>Statistik Detail</h1>
    <p>Analisis performa kuis Anda secara menyeluruh</p>
</div>

<!-- Overall Stats -->
<div class="metrics">
    <div class="metric"><dt>Total Main</dt><dd><?= (int)($overall['total_main'] ?? 0) ?></dd></div>
    <div class="metric" style="border-top-color:var(--gold-500);"><dt>Total Poin</dt><dd><?= number_format((int)($overall['total_poin'] ?? 0)) ?></dd></div>
    <div class="metric" style="border-top-color:var(--green-600);"><dt>Rata-rata Skor</dt><dd><?= (int)($overall['avg_poin'] ?? 0) ?></dd></div>
    <div class="metric" style="border-top-color:var(--purple-600);"><dt>Skor Tertinggi</dt><dd><?= (int)($overall['best_poin'] ?? 0) ?></dd></div>
</div>

<!-- Accuracy & Live -->
<div class="metrics mt-16">
    <div class="metric"><dt>Akurasi Jawaban</dt><dd><?= (int)($accuracy['akurasi'] ?? 0) ?>%</dd></div>
    <div class="metric" style="border-top-color:var(--gold-500);"><dt>Benar / Total</dt><dd><?= (int)($accuracy['benar'] ?? 0) ?> / <?= (int)($accuracy['total'] ?? 0) ?></dd></div>
    <div class="metric" style="border-top-color:var(--green-600);"><dt>Sesi Live</dt><dd><?= (int)($liveStats['live_count'] ?? 0) ?></dd></div>
    <div class="metric" style="border-top-color:var(--purple-600);"><dt>Poin Live</dt><dd><?= (int)($liveStats['live_poin'] ?? 0) ?></dd></div>
</div>

<!-- Per Category Cards -->
<?php if (!empty($perKategori)): ?>
<section class="panel mt-16">
    <header><h2>Performa per Kategori</h2></header>
    <div class="panel-body">
        <div class="kuis-list">
            <?php foreach ($perKategori as $k): ?>
            <article class="kuis-card">
                <div class="kuis-icon" style="background:<?= e($k['warna_tema'] ?: '#46178f') ?>"></div>
                <h3><?= e($k['nama_kategori']) ?></h3>
                <div class="metrics" style="margin-top:var(--s3);">
                    <div class="metric"><dt>Main</dt><dd><?= (int)$k['jumlah_main'] ?></dd></div>
                    <div class="metric"><dt>Best</dt><dd><?= (int)$k['best_poin'] ?></dd></div>
                    <div class="metric"><dt>Avg</dt><dd><?= (int)$k['avg_poin'] ?></dd></div>
                </div>
                <p class="muted" style="margin-top:var(--s2);">Terakhir: <?= date('d M Y', strtotime($k['terakhir_main'])) ?></p>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Monthly Trend Chart -->
<?php if (!empty($trend)): ?>
<section class="panel mt-16">
    <header><h2>Tren Bulanan (6 Bulan Terakhir)</h2></header>
    <div class="panel-body">
        <div class="answer-dist" style="height:200px;">
            <?php
            $maxAvg = max(array_column($trend, 'avg_poin')) ?: 1;
            foreach ($trend as $t):
                $pct = ($t['avg_poin'] / $maxAvg) * 100;
            ?>
            <div class="dist-col">
                <span class="dist-count"><?= (int)$t['avg_poin'] ?></span>
                <div class="dist-bar" style="background:var(--purple-600); height:<?= max($pct, 5) ?>%"></div>
                <span class="dist-label" style="background:var(--purple-600);"><?= date('M Y', strtotime($t['bulan'] . '-01')) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="muted text-center mt-8">Rata-rata skor per bulan</p>
    </div>
</section>
<?php endif; ?>

<!-- Recent History -->
<?php if (!empty($riwayat)): ?>
<section class="panel mt-16">
    <header><h2>Riwayat Terbaru</h2></header>
    <div class="panel-body panel-flush">
        <div class="table-scroll">
            <table class="data">
                <thead><tr><th>Tanggal</th><th>Kategori</th><th class="num">Skor</th><th>Mode</th></tr></thead>
                <tbody>
                    <?php foreach ($riwayat as $r): ?>
                    <tr>
                        <td><?= date('d M Y H:i', strtotime($r['tanggal_main'])) ?></td>
                        <td><span class="tag" style="background:<?= e($r['warna_tema'] ?: '#46178f') ?>;border-color:<?= e($r['warna_tema'] ?: '#46178f') ?>;color:#fff;"><?= e($r['nama_kategori']) ?></span></td>
                        <td class="num"><strong><?= (int)$r['total_poin'] ?></strong></td>
                        <td><?= $r['sesi_id'] ? '<span class="tag tag-info">Live</span>' : '<span class="tag">Solo</span>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php endif; ?>

<?php mp_foot(['base' => '../']); ?>