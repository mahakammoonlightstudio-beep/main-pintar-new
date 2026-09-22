<?php
/**
 * Main Pintar - leaderboard.php (OPTIMIZED)
 * Peringkat pemain - hanya peserta terdaftar (role=peserta) yang tampil.
 * Guest bisa lihat tapi tidak bisa masuk ranking.
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/session.php';

// Ensure guest session
mp_start_guest_session();
mp_maintenance_gate();
mp_boot_lang();

$pdo = db();

// Optimized leaderboard query using helper
$leaderboard = mp_get_leaderboard($pdo, 20);

mp_head([
    'title' => __('Peringkat'),
    'active' => 'leaderboard',
]);
?>
<div class="page-head">
    <h1><?= __('Peringkat Pemain') ?></h1>
    <p><?= __('Skor tertinggi seluruh peserta Main Pintar') ?></p>
</div>

<?php if (mp_is_guest()): ?>
<div class="panel mt-8 reveal">
    <div class="panel-body text-center">
        <p class="mb-16"><?= __('Kamu bermain sebagai') ?> <strong><?= e(mp_get_user_nama()) ?></strong> (<?= __('Mode Tamu') ?>).</p>
        <p class="muted mb-16"><?= __('Skor mode tamu tidak masuk ke peringkat. Daftar atau masuk untuk bersaing!') ?></p>
        <div class="btn-row" style="justify-content:center;">
            <a class="btn btn-primary" href="auth/register.php?redirect=<?= urlencode('leaderboard.php') ?>"><?= __('Daftar') ?></a>
            <a class="btn btn-quiet" href="auth/login.php?redirect=<?= urlencode('leaderboard.php') ?>"><?= __('Masuk') ?></a>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="panel">
    <?php if (empty($leaderboard)): ?>
        <div class="empty"><?= __('Belum ada pemain yang main.') ?></div>
    <?php else: ?>
        <ul class="lb-list">
            <?php $rank = 1; foreach ($leaderboard as $lb): ?>
            <li class="<?= $rank === 1 ? 'lb-top1' : '' ?>">
                <span class="lb-rank"><?= $rank === 1 ? '&#127942;' : $rank ?></span>
                <span class="lb-name"><?= e($lb['nama_lengkap']) ?></span>
                <span class="tag"><?= (int)$lb['jumlah_main'] ?><?= __('x main') ?></span>
                <span class="lb-score"><?= (int)$lb['best_poin'] ?> <?= __('poin') ?></span>
            </li>
            <?php $rank++; endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<?php mp_foot(); ?>
