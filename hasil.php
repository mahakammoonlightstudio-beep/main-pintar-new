<?php
/**
 * Main Pintar - hasil.php (OPTIMIZED)
 * Hasil kuis dengan prompt login untuk guest.
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/session.php';

mp_start_guest_session();
mp_maintenance_gate();
mp_boot_lang();

header('Cache-Control: no-store, no-cache, must-revalidate');

$hasil = $_SESSION['hasil_terakhir'] ?? null;
unset($_SESSION['hasil_terakhir']);

mp_head(['title' => __('Hasil Kuis'), 'active' => '']);
?>

<?php $akurasi = ($hasil && $hasil['total'] > 0) ? round($hasil['benar'] / $hasil['total'] * 100) : 0; ?>
<?php if ($hasil): ?>
<div class="result-hero reveal"<?= $akurasi >= 70 ? ' data-confetti="1"' : '' ?>>
    <h1><?= __('Kuis Selesai!') ?></h1>
    <p><?= e($hasil['kategori']) ?></p>
    <div class="score-value"><span data-countup="<?= (int)$hasil['total_poin'] ?>">0</span></div>
    <p><?= (int)$hasil['benar'] ?> <?= __('dari') ?> <?= (int)$hasil['total'] ?> <?= __('soal benar') ?></p>
</div>

<div class="result-cards">
    <dl class="result-card reveal"><dt><?= __('Total Poin') ?></dt><dd><?= (int)$hasil['total_poin'] ?></dd></dl>
    <dl class="result-card reveal"><dt><?= __('Benar') ?></dt><dd><?= (int)$hasil['benar'] ?></dd></dl>
    <dl class="result-card reveal"><dt><?= __('Salah') ?></dt><dd><?= (int)($hasil['total'] - $hasil['benar']) ?></dd></dl>
    <dl class="result-card reveal"><dt><?= __('Akurasi') ?></dt><dd><?= $akurasi ?>%</dd></dl>
</div>

<?php if (mp_is_guest()): ?>
<div class="panel mt-16 reveal">
    <header>
        <h2><?= __('Simpan Skor & Masuk Peringkat') ?></h2>
        <p><?= __('Kamu bermain sebagai') ?> <strong><?= e(mp_get_user_nama()) ?></strong> (<?= __('Mode Tamu') ?>).</p>
    </header>
    <div class="panel-body text-center">
        <p class="muted mb-16"><?= __('Skor tidak tersimpan ke peringkat.') ?><br><?= __('Daftar') ?> <?= __('atau') ?> <?= __('Masuk') ?> <?= __('untuk menyimpan skor.') ?> <strong><?= (int)$hasil['total_poin'] ?> <?= __('poin') ?></strong>!</p>
        <div class="btn-row" style="justify-content:center; flex-wrap:wrap; gap:var(--s3);">
            <a class="btn btn-primary" href="auth/register.php"><?= __('Daftar & Simpan Skor') ?></a>
            <a class="btn btn-quiet" href="auth/login.php"><?= __('Masuk & Simpan Skor') ?></a>
        </div>
        <p class="muted mt-8"><?= __('Atau lanjut main sebagai Tamu') ?></p>
    </div>
</div>
<?php else: ?>
<div class="panel mt-16 reveal">
    <div class="panel-body text-center">
        <p class="mb-16"><?= e(app_tagline()) ?></p>
        <div class="btn-row" style="justify-content:center;">
            <a class="btn btn-gold" href="kuis.php"><?= __('Main Lagi') ?></a>
            <a class="btn btn-quiet" href="leaderboard.php"><?= __('Lihat Peringkat') ?></a>
            <a class="btn btn-primary" href="auth/profile.php"><?= __('Lihat Statistik Saya') ?></a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php else: ?>
<div class="page-head reveal">
    <h1><?= __('Hasil Kuis') ?></h1>
    <p><?= __('Belum ada hasil kuis untuk ditampilkan') ?></p>
</div>
<div class="panel reveal">
    <div class="panel-body">
        <p class="notice notice-info"><?= __('Kerjakan kuis terlebih dahulu untuk melihat hasil.') ?></p>
        <div class="btn-row mt-16">
            <a class="btn btn-primary" href="kuis.php"><?= __('Pilih Kuis') ?></a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php mp_foot(); ?>
