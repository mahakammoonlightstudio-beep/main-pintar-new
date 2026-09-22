<?php
/**
 * Main Pintar - Fungsi bersama: escape, JSON, CSRF, layout halaman (OPTIMIZED)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/lang.php';

function e(mixed $str): string
{
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== REDIRECT AMAN =====
/**
 * Normalisasi parameter ?redirect= agar selalu valid & lokal.
 * Dipakai dari halaman /auth/: path aplikasi root otomatis diberi prefix '../',
 * path dalam folder auth (mis. profile.php) dipakai apa adanya.
 * Absolute URL / cross-origin ditolak (cegah open redirect).
 */
function mp_safe_redirect(string $redirect, string $fallback = '../index.php'): string
{
    $redirect = str_replace(['\\', "\0", "\r", "\n"], '', trim($redirect));
    if ($redirect === ''
        || preg_match('#^[a-z][a-z0-9+.\-]*://#i', $redirect)
        || str_starts_with($redirect, '//')
        || str_starts_with($redirect, '/')
    ) {
        return $fallback;
    }
    [$path] = explode('?', $redirect, 2);
    if (is_file(__DIR__ . '/../auth/' . $path)) {
        return $redirect; // file ada di folder auth, pakai apa adanya
    }
    return '../' . $redirect;
}

// ===== CSRF =====
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function csrf_verify(): void
{
    $ok = isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf_token']);
    if (!$ok) {
        http_response_code(403);
        die('<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Akses Ditolak</title></head>'
            . '<body style="font-family:system-ui;text-align:center;padding:60px;background:#f6f4fa;">'
            . '<h1 style="color:#46178f;">Token keamanan tidak valid</h1>'
            . '<p><a href="index.php" style="color:#46178f;">Kembali ke beranda</a></p></body></html>');
    }
}

// ===== KODE RUANGAN 6 KARAKTER =====
function generate_kode_ruangan(PDO $pdo): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $kode = '';
        for ($i = 0; $i < 6; $i++) {
            $kode .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $st = $pdo->prepare("SELECT id FROM sesi_kuis WHERE kode_ruangan = ?");
        $st->execute([$kode]);
    } while ($st->fetch());
    return $kode;
}

// ===== CLEANUP OTOMATIS (optimized with single transaction) =====
function cleanup_old_data(PDO $pdo): void
{
    // Jalankan maksimal SEKALI SEHARI ( tandanya di file penanda ), bukan
    // sekali per login-admin. Menghemat query DELETE berat di hosting bersama.
    $flag = __DIR__ . '/.cleanup_last';
    $today = date('Ymd');
    if (is_file($flag) && (string)file_get_contents($flag) === $today) {
        return;
    }

    if (!isset($_SESSION['cleanup_done'])) {
        try {
            $pdo->beginTransaction();
            $day = CLEANUP_UMUR_HARI;
            $pdo->prepare("DELETE FROM jawaban WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)")->execute([$day]);
            $pdo->prepare("DELETE FROM peserta_sesi WHERE bergabung_at < DATE_SUB(NOW(), INTERVAL ? DAY)")->execute([$day]);
            $pdo->prepare("DELETE FROM sesi_kuis WHERE status = 'selesai' AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)")->execute([$day]);
            // Buang user guest yatim (tidak lagi dipakai data apa pun) agar tabel users tidak membengkak
            $pdo->prepare(
                "DELETE FROM users
                 WHERE role = 'guest' AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
                   AND id NOT IN (SELECT user_id FROM jawaban)
                   AND id NOT IN (SELECT user_id FROM peserta_sesi)
                   AND id NOT IN (SELECT user_id FROM skor)"
            )->execute([$day]);
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('[MainPintar] Cleanup failed: ' . $e->getMessage());
        }
        $_SESSION['cleanup_done'] = true;
        @file_put_contents($flag, $today, LOCK_EX);
    }
}

// ===== NAVIGATION =====
// Label dalam bahasa Indonesia; diterjemahkan saat render lewat __().
function mp_nav_items(string $role): array
{
    if ($role === 'admin') {
        return [
            'dashboard'    => ['Dasbor', 'admin/dashboard.php'],
            'analytics'    => ['Analitik', 'admin/analytics.php'],
            'soal'         => ['Kelola Soal', 'admin/kelola-soal.php'],
            'kategori'     => ['Kelola Kategori', 'admin/kelola-kategori.php'],
            'users'        => ['Kelola Pengguna', 'admin/kelola-user.php'],
            'broadcast'    => ['Kirim Notifikasi', 'admin/notifikasi.php'],
            'hasil'        => ['Lihat Hasil', 'admin/lihat-hasil.php'],
            'pengaturan'   => ['Pengaturan Sistem', 'admin/pengaturan.php'],
            'beranda'      => ['Beranda', 'index.php'],
        ];
    }
    if ($role === 'peserta') {
        return [
            'beranda'     => ['Beranda', 'index.php'],
            'kuis'        => ['Pilih Kuis', 'kuis.php'],
            'leaderboard' => ['Peringkat', 'leaderboard.php'],
            'stats'       => ['Statistik', 'auth/stats.php'],
            'notifikasi'  => ['Notifikasi', 'auth/notifikasi.php'],
            'settings'    => ['Pengaturan', 'auth/settings.php'],
            'profile'     => ['Akun Saya', 'auth/profile.php'],
        ];
    }
    // Guest / public
    $items = [
        'beranda'     => ['Beranda', 'index.php'],
        'kuis'        => ['Pilih Kuis', 'kuis.php'],
        'leaderboard' => ['Peringkat', 'leaderboard.php'],
        'login'       => ['Masuk', 'auth/login.php'],
    ];
    if (registration_enabled()) {
        $items['register'] = ['Daftar', 'auth/register.php'];
    }
    return $items;
}

/**
 * Jumlah notifikasi belum dibaca (untuk lonceng di header). 0 bila gagal.
 */
function mp_unread_notif_count(): int
{
    static $count = null;
    if ($count !== null) return $count;
    $count = 0;
    try {
        if (mp_is_logged_in()) {
            $st = db()->prepare("SELECT COUNT(*) FROM notifikasi WHERE user_id = ? AND dibaca = 0");
            $st->execute([mp_get_user_id()]);
            $count = (int)$st->fetchColumn();
        }
    } catch (Throwable $e) {
        $count = 0;
    }
    return $count;
}

// ===== OPTIMIZED DB HELPERS =====
/**
 * Get kategori with question count in ONE query (hanya kategori aktif)
 */
function mp_get_kategori_with_count(PDO $pdo): array
{
    return $pdo->query(
        "SELECT k.*, COALESCE(q.cnt, 0) AS jumlah_soal
         FROM kategori k
         LEFT JOIN (SELECT kategori_id, COUNT(*) AS cnt FROM soal WHERE aktif = 1 GROUP BY kategori_id) q ON q.kategori_id = k.id
         WHERE k.aktif = 1
         ORDER BY k.id ASC"
    )->fetchAll();
}

/**
 * Get leaderboard - optimized with single query
 */
function mp_get_leaderboard(PDO $pdo, int $limit = 20): array
{
    $st = $pdo->prepare(
        "SELECT u.nama_lengkap, MAX(s.total_poin) AS best_poin, COUNT(*) AS jumlah_main
         FROM skor s
         JOIN users u ON u.id = s.user_id
         WHERE u.role = 'peserta'
         GROUP BY u.id, u.nama_lengkap
         ORDER BY best_poin DESC
         LIMIT ?"
    );
    $st->execute([$limit]);
    return $st->fetchAll();
}

/**
 * Get user's best scores across categories
 */
function mp_get_user_scores(PDO $pdo, int $userId): array
{
    $st = $pdo->prepare(
        "SELECT k.nama_kategori, MAX(s.total_poin) AS best_poin, COUNT(*) AS jumlah_main,
                MAX(s.tanggal_main) AS terakhir_main
         FROM skor s
         JOIN kategori k ON k.id = s.kategori_id
         WHERE s.user_id = ?
         GROUP BY s.kategori_id, k.nama_kategori
         ORDER BY best_poin DESC"
    );
    $st->execute([$userId]);
    return $st->fetchAll();
}

/**
 * Get recent activity for user
 */
function mp_get_user_activity(PDO $pdo, int $userId, int $limit = 10): array
{
    $st = $pdo->prepare(
        "SELECT s.tanggal_main, k.nama_kategori, s.total_poin, s.sesi_id
         FROM skor s
         JOIN kategori k ON k.id = s.kategori_id
         WHERE s.user_id = ?
         ORDER BY s.tanggal_main DESC
         LIMIT ?"
    );
    $st->execute([$userId, $limit]);
    return $st->fetchAll();
}

/**
 * Get random soal IDs for solo quiz (avoids ORDER BY RAND())
 * Hanya soal aktif (aktif = 1) yang diikutkan.
 */
function mp_get_random_soal_ids(PDO $pdo, int $kategoriId, int $limit = 10): array
{
    $stCount = $pdo->prepare("SELECT COUNT(*) FROM soal WHERE kategori_id = ? AND aktif = 1");
    $stCount->execute([$kategoriId]);
    $total = (int)$stCount->fetchColumn();

    if ($total <= $limit) {
        $st = $pdo->prepare("SELECT id FROM soal WHERE kategori_id = ? AND aktif = 1 ORDER BY id");
        $st->execute([$kategoriId]);
        return $st->fetchAll(PDO::FETCH_COLUMN);
    }

    $offset = random_int(0, max(0, $total - $limit));
    $st = $pdo->prepare("SELECT id FROM soal WHERE kategori_id = ? AND aktif = 1 ORDER BY id LIMIT ? OFFSET ?");
    $st->execute([$kategoriId, $limit, $offset]);
    $ids = $st->fetchAll(PDO::FETCH_COLUMN);

    if (count($ids) < $limit) {
        $st2 = $pdo->prepare("SELECT id FROM soal WHERE kategori_id = ? AND aktif = 1 ORDER BY id LIMIT ?");
        $st2->execute([$kategoriId, $limit - count($ids)]);
        $ids = array_merge($ids, $st2->fetchAll(PDO::FETCH_COLUMN));
    }
    shuffle($ids);
    return array_slice($ids, 0, $limit);
}

// ===== LAYOUT =====
function mp_head(array $o): void
{
    $title = $o['title'] ?? app_name();
    $base  = $o['base'] ?? '';
    $role  = $_SESSION['user_role'] ?? 'guest';
    $nama  = $_SESSION['user_nama'] ?? '';
    $lang  = mp_lang();
    $notifCount = mp_unread_notif_count();
    ?>
<!DOCTYPE html>
<html lang="<?= $lang === 'en' ? 'en' : 'id' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> &middot; <?= e(app_name()) ?></title>
<meta name="description" content="<?= APP_SUBTITLE ?> — <?= APP_TAGLINE ?>">
<meta name="theme-color" content="#46178f">
<link rel="icon" type="image/png" href="<?= $base ?>assets/img/logo-kukar.png">
<link rel="manifest" href="<?= $base ?>manifest.json">
<link rel="apple-touch-icon" href="<?= $base ?>assets/img/logo-kukar.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700;800&family=Open+Sans:wght@400;600;700&display=swap">
<link rel="stylesheet" href="<?= $base ?>assets/css/style.css">
<link rel="stylesheet" href="<?= $base ?>assets/css/responsive.css">
<link rel="stylesheet" href="<?= $base ?>assets/css/professional.css">
<script>(function(){try{var t=localStorage.getItem('mp_theme');if(t==='dark'||t==='light'){document.documentElement.dataset.theme=t;}else if(window.matchMedia&&matchMedia('(prefers-color-scheme: dark)').matches){document.documentElement.dataset.theme='dark';}}catch(e){}})();</script>
<?= $o['head'] ?? '' ?>
</head>
<body class="<?= $o['body_class'] ?? '' ?>" data-poll-peserta="<?= POLL_INTERVAL_PESERTA ?>" data-poll-host="<?= POLL_INTERVAL_HOST ?>">

<!-- Splash sinematik sekali per sesi browser -->
<div id="splash" class="splash" aria-hidden="true">
    <div class="splash-core">
        <img class="splash-logo" src="<?= $base ?>assets/img/logo-kukar.png" alt="" width="72" height="72">
        <div class="splash-title"><?= APP_NAME ?></div>
        <div class="splash-sub"><?= APP_SUBTITLE ?></div>
        <div class="splash-bar"><span></span></div>
    </div>
</div>

<a class="skip-link" href="#main"><?= __('Lewati ke isi utama') ?></a>
<header class="mp-masthead">
    <div class="mp-masthead-inner">
        <a class="mp-brand" href="<?= $base ?>index.php">
            <img src="<?= $base ?>assets/img/logo-kukar.png" alt="Lambang Kabupaten Kutai Kartanegara" width="40" height="40">
            <span class="mp-brand-text">
                <strong><?= e(app_name()) ?></strong>
                <span><?= APP_SUBTITLE ?></span>
            </span>
        </a>
        <div class="mp-masthead-side">
            <?php if (mp_is_logged_in()): ?>
                <span class="mp-who"><?= __('Hai,') ?> <strong><?= e($nama) ?></strong></span>
                <?php if (mp_is_admin()): ?>
                    <a class="btn-sm btn-quiet" href="<?= $base ?>admin/dashboard.php"><?= __('Panel Admin') ?></a>
                <?php else: ?>
                    <a class="icon-btn" href="<?= $base ?>auth/notifikasi.php" aria-label="<?= e(__('Notifikasi')) ?>" title="<?= e(__('Notifikasi')) ?>">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        <?php if ($notifCount > 0): ?><span class="notif-dot"><?= $notifCount > 9 ? '9+' : $notifCount ?></span><?php endif; ?>
                    </a>
                    <a class="btn-sm btn-quiet" href="<?= $base ?>auth/settings.php"><?= __('Pengaturan') ?></a>
                    <a class="btn-sm btn-quiet" href="<?= $base ?>auth/profile.php"><?= __('Akun Saya') ?></a>
                    <a class="btn-sm" href="<?= $base ?>auth/logout.php"><?= __('Keluar') ?></a>
                <?php endif; ?>
            <?php elseif (mp_is_guest()): ?>
                <span class="mp-who"><?= __('Mode Tamu:') ?> <strong><?= e($nama) ?></strong></span>
                <a class="btn-sm btn-quiet" href="<?= $base ?>auth/login.php"><?= __('Masuk') ?></a>
                <?php if (registration_enabled()): ?><a class="btn-sm" href="<?= $base ?>auth/register.php"><?= __('Daftar') ?></a><?php endif; ?>
            <?php else: ?>
                <span class="mp-who"><?= __('Mode publik') ?></span>
                <a class="btn-sm btn-quiet" href="<?= $base ?>auth/login.php"><?= __('Masuk') ?></a>
                <?php if (registration_enabled()): ?><a class="btn-sm" href="<?= $base ?>auth/register.php"><?= __('Daftar') ?></a><?php endif; ?>
            <?php endif; ?>
            <button type="button" class="theme-toggle" data-theme-toggle aria-pressed="false"><?= __('Mode gelap') ?></button>
            <a class="lang-toggle" href="<?= e(mp_lang_switch_url($lang === 'id' ? 'en' : 'id')) ?>" aria-label="<?= e(__('Ganti bahasa')) ?>" title="<?= e(__('Ganti bahasa')) ?>"><?= $lang === 'id' ? 'EN' : 'ID' ?></a>
        </div>
    </div>
    <nav class="mp-nav" aria-label="Navigasi utama">
        <div class="mp-nav-inner">
            <?php
            $items = mp_nav_items($role);
            $active = $o['active'] ?? '';
            foreach ($items as $key => $item): ?>
                <a href="<?= $base . $item[1] ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= e(__($item[0])) ?><?= $key === 'notifikasi' && $notifCount > 0 ? ' <span class="nav-badge">' . $notifCount . '</span>' : '' ?></a>
            <?php endforeach; ?>
        </div>
    </nav>
</header>
<main id="main" class="mp-container">
<?php
}

function mp_foot(array $o = []): void
{
    $base = $o['base'] ?? '';
    ?>
</main>
<footer class="mp-footer" role="contentinfo">
    <div class="footer-container">
        <div class="footer-col">
            <h2><?= __('Alamat Kantor') ?></h2>
            <p class="footer-addr"><?= INSTANSI_ALAMAT ?></p>
        </div>
        <div class="footer-col">
            <h2><?= __('Kontak Resmi') ?></h2>
            <p><?= __('Surel') ?>: <a href="mailto:<?= INSTANSI_EMAIL ?>"><?= INSTANSI_EMAIL ?></a></p>
            <p><?= __('Situs') ?>: <a href="<?= INSTANSI_WEB ?>" target="_blank" rel="noopener"><?= INSTANSI_WEB ?></a></p>
        </div>
        <div class="footer-col">
            <h2><?= __('Kanal Informasi') ?></h2>
            <ul class="footer-links">
                <li><a href="https://www.instagram.com/diarpus_kukar/" target="_blank" rel="noopener">Instagram</a></li>
                <li><a href="https://www.facebook.com/dinaskearsipan.danperpustakaan.9/" target="_blank" rel="noopener">Facebook</a></li>
                <li><a href="https://www.youtube.com/@diarpuskukar216" target="_blank" rel="noopener">YouTube</a></li>
                <li><a href="<?= $base ?>kuis.php"><?= __('Pilih Kuis') ?></a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="credit-row">
            <span class="credit-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                &copy; <?= date('Y') ?> Dinas Kearsipan dan Perpustakaan Kabupaten Kutai Kartanegara.
            </span>
            <span class="credit-separator" aria-hidden="true">|</span>
            <span class="credit-item">
                Dikembangkan oleh <strong>Muhammad Fauzan Raffa Al-Habsy</strong>
                <span class="muted">(Siswa SMK Negeri 1 Tenggarong, RPL Kelas 12)</span>
            </span>
            <span class="credit-separator" aria-hidden="true">|</span>
            <span class="credit-item">
                Hak Cipta Data & Konten: <strong>Varia Fadillah, S.P., M.M.</strong>
                <span class="muted">(Kepala Bidang P2A Diarpus Kukar)</span>
            </span>
            <span class="credit-separator" aria-hidden="true">|</span>
            <a class="credit-link" href="<?= $base ?>lisensi.md" target="_blank" rel="noopener">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                Lisensi Lengkap
            </a>
        </div>
    </div>
</footer>
<div id="toastContainer" class="toast-container" aria-live="polite" aria-atomic="true"></div>
<button type="button" class="scroll-top-btn" aria-label="<?= __('Kembali ke atas') ?>" hidden>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M18 15l-6-6-6 6"/></svg>
</button>
<script src="<?= $base ?>assets/js/quiz.js" defer></script>
<?php // admin.js dimuat global: init-nya no-op di halaman publik, tetapi
      // menyediakan konfirmasi data-confirm & toast untuk semua halaman. ?>
<script src="<?= $base ?>assets/js/admin.js" defer></script>
<?= $o['scripts'] ?? '' ?>
<script>
    // Service Worker Registration for PWA
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('<?= $base ?>sw.js')
                .then(function(reg) { console.log('[SW] Registered:', reg.scope); })
                .catch(function(err) { console.log('[SW] Registration failed:', err); });
        });
    }
</script>
</body>
</html>
<?php
}