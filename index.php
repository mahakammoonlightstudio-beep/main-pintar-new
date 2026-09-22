<?php
/**
 * Main Pintar - index.php (OPTIMIZED)
 * Beranda dengan CTA yang disesuaikan status login.
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/session.php';

// Ensure guest session for anonymous play
mp_start_guest_session();
mp_maintenance_gate();
mp_boot_lang();

$pdo = db();

// Optimized: single query for kategori + soal count
$kats = mp_get_kategori_with_count($pdo);

mp_head([
    'title' => 'Beranda',
    'active' => 'beranda',
]);
?>
<section class="hero reveal">
    <h1><?= e(app_tagline()) ?></h1>
    <p class="subtitle"><?= APP_SUBTITLE ?> &mdash; platform kuis edukatif bertema kearsipan untuk Dinas Kearsipan dan Perpustakaan Kabupaten Kutai Kartanegara.</p>
    <div class="hero-actions">
        <a class="btn btn-primary" href="kuis.php"><?= __('Main Sendiri') ?></a>
        <a class="btn btn-quiet" href="join.php"><?= __('Gabung Kuis Live') ?></a>
    </div>
</section>

<?php if (mp_is_guest()): ?>
<!-- Guest CTA Panel -->
<section class="panel mt-16 reveal">
    <div class="panel-body text-center">
        <h2><?= __('Ingin Skor Tersimpan & Bisa Masuk Peringkat?') ?></h2>
        <p class="muted mb-16"><?= __('Kamu bermain sebagai') ?> <strong><?= e(mp_get_user_nama()) ?></strong> (<?= __('Mode Tamu') ?>).<br><?= __('Skor tidak tersimpan ke peringkat.') ?></p>
        <div class="btn-row" style="justify-content:center; flex-wrap:wrap; gap:var(--s3);">
            <a class="btn btn-primary" href="auth/register.php"><?= __('Daftar Gratis') ?></a>
            <a class="btn btn-quiet" href="auth/login.php"><?= __('Masuk') ?></a>
        </div>
        <p class="muted mt-8"><?= __('Atau lanjut main sebagai Tamu') ?> &rarr;</p>
    </div>
</section>
<?php elseif (mp_is_logged_in() && !mp_is_admin()): ?>
<!-- Logged-in user welcome -->
<section class="panel mt-16 reveal">
    <div class="panel-body text-center">
        <h2><?= __('Selamat datang kembali') ?>, <strong><?= e(mp_get_user_nama()) ?></strong>! 🎉</h2>
        <p class="muted mb-16"><?= __('Skor kamu akan tersimpan otomatis dan bisa bersaing di Peringkat.') ?></p>
        <div class="btn-row" style="justify-content:center; flex-wrap:wrap; gap:var(--s3);">
            <a class="btn btn-primary" href="kuis.php"><?= __('Main Lagi') ?></a>
            <a class="btn btn-quiet" href="leaderboard.php"><?= __('Lihat Peringkat') ?></a>
            <a class="btn btn-gold" href="auth/profile.php"><?= __('Lihat Statistik Saya') ?></a>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="mt-16">
    <div class="page-head">
        <h2><?= __('Pilih Kategori Kuis') ?></h2>
        <p><?= count($kats) ?> <?= __('kategori tersedia') ?></p>
    </div>

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
</section>

<section class="panel mt-16 reveal">
    <header>
        <h2><?= __('Mode Live Multiplayer') ?></h2>
        <p><?= __('Host membuat sesi dan membagikan kode ruangan 6 karakter. Peserta masuk dengan kode + nickname.') ?></p>
    </header>
    <div class="panel-body">
        <div class="btn-row">
            <a class="btn btn-primary" href="join.php"><?= __('Gabung Sesi Live') ?></a>
            <a class="btn btn-quiet" href="leaderboard.php"><?= __('Lihat Peringkat') ?></a>
        </div>
    </div>
</section>

<section class="panel mt-16 reveal" aria-labelledby="rekomendasi-heading">
    <header>
        <h2 id="rekomendasi-heading"><?= __('Rekomendasi Untukmu') ?></h2>
        <p><?= __('Platform kuis dan aplikasi lain dari Diarpus Kukar.') ?></p>
    </header>
    <div class="panel-body">
        <div class="rec-item">
            <div class="rec-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 19c-1.5 1.5-4 1.5-5.5 0S2 15 3.5 13.5L7 10"/><path d="M15 5c1.5-1.5 4-1.5 5.5 0s1.5 4 0 5.5L17 14"/><path d="M4 20l6-6"/></svg>
            </div>
            <div class="rec-item-main">
                <div class="tag-row">
                    <span class="tag tag-info">Website Kuis</span>
                    <span class="tag">Gratis</span>
                </div>
                <h3 class="mt-8">Kuis Arsip &mdash; Kuesioner &amp; Evaluasi Kearsipan</h3>
                <p class="muted rec-desc">Ikuti kuesioner dan evaluasi kearsipan resmi: mode individu dengan kode ruangan dari pembimbing, sertifikat, dan papan peringkat.</p>
            </div>
            <div class="btn-row">
                <a class="btn btn-primary" href="https://kuis-arsip.rf.gd/" target="_blank" rel="noopener">Buka Kuis Arsip</a>
            </div>
        </div>
        <div class="rec-item">
            <div class="rec-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="2" width="10" height="20" rx="2"/><line x1="11" y1="18" x2="13" y2="18"/></svg>
            </div>
            <div class="rec-item-main">
                <div class="tag-row">
                    <span class="tag tag-ok">Aplikasi</span>
                    <span class="tag">Android &amp; iOS</span>
                </div>
                <h3 class="mt-8">Aplikasi IKukar</h3>
                <p class="muted rec-desc">Akses layanan perpustakaan digital Kabupaten Kutai Kartanegara langsung dari ponsel Anda.</p>
            </div>
            <div class="btn-row">
                <a class="btn-sm btn-quiet" href="https://play.google.com/store/apps/details?id=id.kubuku.kbk12535c5" target="_blank" rel="noopener">Google Play</a>
                <a class="btn-sm btn-quiet" href="https://apps.apple.com/id/app/ikukar/id6476895578" target="_blank" rel="noopener">App Store</a>
            </div>
        </div>
    </div>
</section>

<?php mp_foot(); ?>