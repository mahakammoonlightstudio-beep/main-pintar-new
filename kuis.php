<?php
/**
 * Main Pintar - kuis.php (OPTIMIZED)
 * Halaman pilih kuis dengan CTA login untuk guest.
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/session.php';

mp_start_guest_session();
mp_maintenance_gate();
mp_boot_lang();

$pdo = db();
$kats = mp_get_kategori_with_count($pdo);

mp_head([
    'title' => __('Pilih Kuis'),
    'active' => 'kuis',
]);
?>
<div class="page-head">
    <h1><?= __('Pilih Kuis') ?></h1>
    <p><?= __('Halaman pilih kuis edukatif kearsipan yang ingin kamu kerjakan') ?></p>
</div>

<?php if (mp_is_guest()): ?>
<div class="panel mb-16 reveal">
    <div class="panel-body text-center">
        <p class="muted mb-12"><?= __('Kamu bermain sebagai') ?> <strong><?= e(mp_get_user_nama()) ?></strong> (<?= __('Mode Tamu') ?>).</p>
        <p class="muted mb-12"><?= __('Skor tidak tersimpan ke peringkat.') ?> <a href="auth/register.php?redirect=<?= urlencode('kuis.php') ?>"><?= __('Daftar') ?></a> <?= __('atau') ?> <a href="auth/login.php?redirect=<?= urlencode('kuis.php') ?>"><?= __('Masuk') ?></a> <?= __('untuk menyimpan skor.') ?></p>
    </div>
</div>
<?php endif; ?>

<div class="kuis-list">
    <?php foreach ($kats as $i => $k): ?>
    <article class="kuis-card reveal">
        <div class="kuis-icon" style="background:<?= e($k['warna_tema'] ?: '#46178f') ?>"></div>
        <h3><?= e($k['nama_kategori']) ?></h3>
        <p><?= e($k['deskripsi']) ?></p>
        <div class="tag-row mb-8">
            <span class="tag"><?= (int)$k['jumlah_soal'] ?> <?= __('soal') ?></span>
            <span class="tag"><?= DEFAULT_POIN ?> <?= __('poin dasar') ?></span>
        </div>
        <a class="btn btn-primary w-full" href="main.php?kategori=<?= (int)$k['id'] ?>"><?= __('Mulai Kuis') ?></a>
    </article>
    <?php endforeach; ?>
</div>

<?php mp_foot(); ?>
