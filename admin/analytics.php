<?php
/**
 * Main Pintar - admin/analytics.php
 * Analitik soal & performa peserta untuk admin.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';

if (!mp_is_admin()) {
    header('Location: login.php');
    exit;
}

$pdo = db();

// Overall stats
$totalSoal = (int)$pdo->query("SELECT COUNT(*) FROM soal")->fetchColumn();
$totalKategori = (int)$pdo->query("SELECT COUNT(*) FROM kategori")->fetchColumn();
$totalPeserta = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'peserta'")->fetchColumn();
$totalGuest = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'guest'")->fetchColumn();
$totalSkor = (int)$pdo->query("SELECT COUNT(*) FROM skor")->fetchColumn();
$totalSesi = (int)$pdo->query("SELECT COUNT(*) FROM sesi_kuis")->fetchColumn();
$rataSkor = (int)$pdo->query("SELECT ROUND(AVG(total_poin)) FROM skor")->fetchColumn();

// Soal paling sering salah
$stSulit = $pdo->query(
    "SELECT s.pertanyaan, k.nama_kategori, s.jawaban_benar,
            COUNT(*) AS total_jawab,
            SUM(CASE WHEN j.benar = 0 THEN 1 ELSE 0 END) AS salah,
            ROUND(SUM(CASE WHEN j.benar = 0 THEN 1 ELSE 0 END) / COUNT(*) * 100) AS pct_salah
     FROM jawaban j
     JOIN soal s ON s.id = j.soal_id
     JOIN kategori k ON k.id = s.kategori_id
     GROUP BY s.id
     HAVING total_jawab >= 3
     ORDER BY pct_salah DESC, salah DESC
     LIMIT 10"
)->fetchAll();

// Distribusi jawaban per kategori
$stDist = $pdo->query(
    "SELECT k.nama_kategori, k.warna_tema,
            COUNT(DISTINCT s.id) AS soal_count,
            COUNT(DISTINCT j.user_id) AS peserta_unik,
            COUNT(j.id) AS total_jawab,
            SUM(j.poin_didapat) AS total_poin,
            AVG(j.poin_didapat) AS avg_poin_per_jawab
     FROM kategori k
     LEFT JOIN soal s ON s.kategori_id = k.id
     LEFT JOIN jawaban j ON j.soal_id = s.id
     GROUP BY k.id
     ORDER BY total_jawab DESC"
);
$perKategori = $stDist->fetchAll();

// Monthly activity
$stMonthly = $pdo->query(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS bulan, COUNT(*) AS sesi_count
     FROM sesi_kuis
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
     GROUP BY DATE_FORMAT(created_at, '%Y-%m')
     ORDER BY bulan ASC"
);
$monthly = $stMonthly->fetchAll();

// Top performers
$stTop = $pdo->query(
    "SELECT u.nama_lengkap, COUNT(s.id) AS total_main, SUM(s.total_poin) AS total_poin, MAX(s.total_poin) AS best_poin
     FROM skor s
     JOIN users u ON u.id = s.user_id
     WHERE u.role = 'peserta'
     GROUP BY u.id
     ORDER BY total_poin DESC
     LIMIT 10"
);
$topPerformers = $stTop->fetchAll();

// Soal difficulty analysis
$stDiff = $pdo->query(
    "SELECT k.nama_kategori,
            COUNT(DISTINCT s.id) AS total_soal,
            AVG(CASE WHEN j.benar = 1 THEN 100 ELSE 0 END) AS avg_accuracy,
            COUNT(j.id) AS total_jawab
     FROM kategori k
     LEFT JOIN soal s ON s.kategori_id = k.id
     LEFT JOIN jawaban j ON j.soal_id = s.id
     GROUP BY k.id
     ORDER BY avg_accuracy ASC"
);
$difficulty = $stDiff->fetchAll();

mp_head([
    'title' => 'Analitik Soal & Peserta',
    'active' => 'analytics',
    'body_class' => 'mp-admin',
    'base' => '../',
]);
?>
<div class="page-head">
    <h1>Analitik & Statistik</h1>
    <p>Performa soal, kesulitan, dan aktivitas peserta</p>
</div>

<!-- Overview Metrics -->
<div class="metrics">
    <div class="metric"><dt>Total Soal</dt><dd><?= $totalSoal ?></dd></div>
    <div class="metric" style="border-top-color:var(--gold-500);"><dt>Kategori</dt><dd><?= $totalKategori ?></dd></div>
    <div class="metric" style="border-top-color:var(--green-600);"><dt>Peserta Terdaftar</dt><dd><?= $totalPeserta ?></dd></div>
    <div class="metric" style="border-top-color:var(--purple-600);"><dt>Tamu (Guest)</dt><dd><?= $totalGuest ?></dd></div>
    <div class="metric"><dt>Sesi Live</dt><dd><?= $totalSesi ?></dd></div>
    <div class="metric" style="border-top-color:var(--gold-500);"><dt>Permainan Selesai</dt><dd><?= $totalSkor ?></dd></div>
    <div class="metric" style="border-top-color:var(--green-600);"><dt>Rata-rata Skor</dt><dd><?= $rataSkor ?></dd></div>
    <div class="metric" style="border-top-color:var(--purple-600);"><dt>Total Poin</dt><dd><?= number_format($pdo->query("SELECT SUM(total_poin) FROM skor")->fetchColumn() ?? 0) ?></dd></div>
</div>

<!-- Difficulty Analysis -->
<div class="panel mt-16">
    <header><h2>Tingkat Kesulitan per Kategori</h2></header>
    <div class="panel-body panel-flush">
        <div class="table-scroll">
            <table class="data">
                <thead>
                    <tr><th>Kategori</th><th class="num">Total Soal</th><th class="num">Total Jawaban</th><th class="num">Akurasi Rata-rata</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($difficulty as $d): 
                        $acc = (float)($d['avg_accuracy'] ?? 0);
                        $status = $acc >= 70 ? ['Mudah', 'tag-ok'] : ($acc >= 40 ? ['Sedang', 'tag-warn'] : ['Sulit', 'tag-error']);
                    ?>
                    <tr>
                        <td><span class="tag" style="background:<?= e($d['warna_tema'] ?? '#46178f') ?>;border-color:<?= e($d['warna_tema'] ?? '#46178f') ?>;color:#fff;"><?= e($d['nama_kategori']) ?></span></td>
                        <td class="num"><?= (int)$d['total_soal'] ?></td>
                        <td class="num"><?= (int)$d['total_jawab'] ?></td>
                        <td class="num"><strong><?= round($acc, 1) ?>%</strong></td>
                        <td><span class="tag <?= $status[1] ?>"><?= $status[0] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Soal Paling Sering Salah -->
<?php if (!empty($stSulit)): ?>
<div class="panel mt-16">
    <header><h2>Soal Paling Sering Salah (Min. 3 jawaban)</h2></header>
    <div class="panel-body">
        <ul class="lb-list">
            <?php foreach ($stSulit as $i => $q): ?>
            <li>
                <span class="lb-rank"><?= $i + 1 ?></span>
                <span class="lb-name">
                    <?= e(mb_substr($q['pertanyaan'], 0, 100)) ?><?= mb_strlen($q['pertanyaan']) > 100 ? '…' : '' ?>
                    <br><small class="muted"><?= e($q['nama_kategori']) ?> · Kunci: <?= e($q['jawaban_benar']) ?></small>
                </span>
                <span class="tag tag-error"><?= (int)$q['pct_salah'] ?>% salah (<?= (int)$q['salah'] ?>/<?= (int)$q['total_jawab'] ?>)</span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<!-- Per Kategori Stats -->
<div class="panel mt-16">
    <header><h2>Statistik per Kategori</h2></header>
    <div class="panel-body panel-flush">
        <div class="table-scroll">
            <table class="data">
                <thead>
                    <tr><th>Kategori</th><th class="num">Soal</th><th class="num">Peserta Unik</th><th class="num">Total Jawaban</th><th class="num">Total Poin</th><th class="num">Avg/Jawab</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($perKategori as $k): ?>
                    <tr>
                        <td><span class="tag" style="background:<?= e($k['warna_tema'] ?? '#46178f') ?>;border-color:<?= e($k['warna_tema'] ?? '#46178f') ?>;color:#fff;"><?= e($k['nama_kategori']) ?></span></td>
                        <td class="num"><?= (int)$k['soal_count'] ?></td>
                        <td class="num"><?= (int)$k['peserta_unik'] ?></td>
                        <td class="num"><?= (int)$k['total_jawab'] ?></td>
                        <td class="num"><?= number_format((int)$k['total_poin']) ?></td>
                        <td class="num"><?= round((float)($k['avg_poin_per_jawab'] ?? 0), 1) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Monthly Activity -->
<?php if (!empty($monthly)): ?>
<div class="panel mt-16">
    <header><h2>Aktivitas Bulanan (12 Bulan)</h2></header>
    <div class="panel-body">
        <div class="answer-dist" style="height:180px;">
            <?php
            $maxCount = max(array_column($monthly, 'sesi_count')) ?: 1;
            foreach ($monthly as $m):
                $pct = ($m['sesi_count'] / $maxCount) * 100;
            ?>
            <div class="dist-col">
                <span class="dist-count"><?= (int)$m['sesi_count'] ?></span>
                <div class="dist-bar dist-a" style="height:<?= max($pct, 5) ?>%"></div>
                <span class="dist-label dist-a"><?= date('M Y', strtotime($m['bulan'] . '-01')) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="muted text-center mt-8">Jumlah sesi live dibuat per bulan</p>
    </div>
</div>
<?php endif; ?>

<!-- Top Performers -->
<?php if (!empty($topPerformers)): ?>
<div class="panel mt-16">
    <header><h2>Peserta Teratas (Overall)</h2></header>
    <div class="panel-body">
        <ul class="lb-list">
            <?php $rank = 1; foreach ($topPerformers as $p): ?>
            <li class="<?= $rank === 1 ? 'lb-top1' : '' ?>">
                <span class="lb-rank"><?= $rank === 1 ? '&#127942;' : $rank ?></span>
                <span class="lb-name"><?= e($p['nama_lengkap']) ?></span>
                <span class="tag"><?= (int)$p['total_main'] ?>x main</span>
                <span class="lb-score"><?= (int)$p['total_poin'] ?> poin</span>
            </li>
            <?php $rank++; endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<?php mp_foot(['base' => '../']); ?>