<?php
/**
 * Main Pintar - main.php (OPTIMIZED)
 * Mode solo: kuis satu per satu dengan timer + feedback.
 * Mode live peserta: main.php?live=1&sesi=X (render via polling api/poll.php).
 * Support: logged-in users, anonymous guests.
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/session.php';

header('Cache-Control: no-store, no-cache, must-revalidate');
$pdo = db();

// ===== Ensure user session (guest or logged in) =====
mp_start_guest_session();
mp_maintenance_gate();
mp_boot_lang();

// ===== Mode live (peserta sudah join lewat join.php) =====
if (isset($_GET['live'])) {
    $sesiId = (int)($_GET['sesi'] ?? 0);
    if ($sesiId <= 0) { header('Location: join.php'); exit; }
    mp_head(['title' => 'Kuis Live', 'active' => '', 'body_class' => 'mp-live']);
    ?>
    <section id="liveQuiz" data-sesi="<?= $sesiId ?>" class="q-shell" aria-live="polite">
        <div class="question-card text-center"><h2><?= __('Menghubungkan…') ?></h2></div>
    </section>
    <?php
    mp_foot();
    exit;
}

// ===== Mode solo =====
$kategoriId = (int)($_GET['kategori'] ?? 0);
if ($kategoriId <= 0) { header('Location: kuis.php'); exit; }

// Mulai sesi solo baru bila belum ada / kategori berbeda
if (empty($_SESSION['solo']) || (int)$_SESSION['solo']['kategori_id'] !== $kategoriId) {
    $stK = $pdo->prepare("SELECT id, nama_kategori FROM kategori WHERE id = ? AND aktif = 1 LIMIT 1");
    $stK->execute([$kategoriId]);
    $kategori = $stK->fetch();
    if (!$kategori) {
        mp_head(['title' => __('Main Kuis'), 'active' => 'kuis']);
        echo '<p class="notice notice-info">' . e(__('Kategori ini sedang tidak tersedia. Silakan pilih kategori lain.')) . '</p><p><a class="btn btn-quiet" href="kuis.php">' . e(__('Kembali')) . '</a></p>';
        mp_foot();
        exit;
    }

    $ids = mp_get_random_soal_ids($pdo, $kategoriId, 10);
    if (empty($ids)) {
        mp_head(['title' => __('Main Kuis'), 'active' => 'kuis']);
        echo '<p class="notice notice-info">' . e(__('Belum ada soal untuk kategori ini.')) . '</p><p><a class="btn btn-quiet" href="kuis.php">' . e(__('Kembali')) . '</a></p>';
        mp_foot();
        exit;
    }
    $_SESSION['solo'] = [
        'kategori_id' => $kategoriId,
        'nama'        => $kategori['nama_kategori'],
        'soal_ids'    => array_map('intval', $ids),
        'index'       => 0,
        'total_poin'  => 0,
        'benar'       => 0,
    ];
}

// ===== POST jawaban (PRG: proses lalu redirect) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['soal_id'])) {
    csrf_verify();
    $solo = &$_SESSION['solo'];
    $soalId = (int)$_POST['soal_id'];
    $pilihan = strtoupper(trim($_POST['pilihan'] ?? ''));
    $sisa = max(0, min(600, (int)($_POST['sisa_detik'] ?? 0)));

    $curId = (int)($solo['soal_ids'][$solo['index']] ?? 0);
    if ($soalId === $curId) {
        $stJ = $pdo->prepare("SELECT jawaban_benar, poin, waktu_jawab, penjelasan FROM soal WHERE id = ? LIMIT 1");
        $stJ->execute([$soalId]);
        $soalJ = $stJ->fetch();
        if ($soalJ) {
            $valid = in_array($pilihan, ['A', 'B', 'C', 'D'], true);
            $benar = $valid && ($pilihan === $soalJ['jawaban_benar']);
            $poin = $benar ? (int)$soalJ['poin'] : 0;
            // sisa dari client tidak boleh melebihi waktu soal (anti-manipulasi bonus)
            $sisa = min($sisa, (int)$soalJ['waktu_jawab']);
            if ($benar && $sisa > 0) {
                $poin += (int)ceil($sisa / max(1, (int)$soalJ['waktu_jawab']) * BONUS_KECEPATAN_MAX);
            }
            $solo['total_poin'] += $poin;
            if ($benar) $solo['benar']++;
            $_SESSION['solo_fb'] = [
                'benar'      => $benar,
                'poin'       => $poin,
                'kunci'      => $soalJ['jawaban_benar'],
                'penjelasan' => (string)($soalJ['penjelasan'] ?? ''),
            ];
        }
        // Maju ke soal berikutnya apa pun hasilnya — termasuk saat waktu habis
        // tanpa jawaban (pilihan kosong), agar peserta tidak terjebak di soal sama.
        $solo['index']++;
    }

    $selesai = ($_SESSION['solo']['index'] ?? 0) >= count($_SESSION['solo']['soal_ids']);
    if ($selesai) {
        $uid = mp_get_or_create_peserta();
        $pdo->prepare("INSERT INTO skor (user_id, kategori_id, total_poin, tanggal_main) VALUES (?, ?, ?, NOW())")
            ->execute([$uid, (int)$_SESSION['solo']['kategori_id'], (int)$_SESSION['solo']['total_poin']]);
        $_SESSION['hasil_terakhir'] = [
            'kategori'   => $_SESSION['solo']['nama'],
            'total_poin' => (int)$_SESSION['solo']['total_poin'],
            'benar'      => (int)$_SESSION['solo']['benar'],
            'total'      => count($_SESSION['solo']['soal_ids']),
        ];
        unset($_SESSION['solo'], $_SESSION['solo_fb']);
        header('Location: hasil.php');
        exit;
    }
    header('Location: main.php?kategori=' . $kategoriId);
    exit;
}

// ===== Render soal saat ini =====
$solo = $_SESSION['solo'];
$idx = (int)$solo['index'];
$soalId = (int)$solo['soal_ids'][$idx];
$stQ = $pdo->prepare(
    "SELECT id, pertanyaan, pilihan_a, pilihan_b, pilihan_c, pilihan_d, waktu_jawab
     FROM soal WHERE id = ? LIMIT 1"
);
$stQ->execute([$soalId]);
$soalAktif = $stQ->fetch();
if (!$soalAktif) { header('Location: kuis.php'); exit; }

$fb = $_SESSION['solo_fb'] ?? null;
unset($_SESSION['solo_fb']);

// Show login prompt for guests at the end
$showLoginPrompt = mp_is_guest() && $idx === count($solo['soal_ids']) - 1;

mp_head(['title' => 'Main Kuis', 'active' => 'kuis', 'body_class' => 'mp-solo']);
?>
<div class="q-shell">
    <div class="quiz-bar">
        <h2><?= e($solo['nama']) ?></h2>
        <div class="timer-wrap">
            <span class="q-count"><?= __('Soal') ?> <?= $idx + 1 ?> <?= __('dari') ?> <?= count($solo['soal_ids']) ?></span>
            <span class="timer-box" id="timerBox"><?= (int)$soalAktif['waktu_jawab'] ?></span>
            <div class="timer-progress"><div class="timer-progress-bar" id="timerBar"></div></div>
        </div>
    </div>

    <form id="quizForm" method="POST" data-waktu="<?= (int)$soalAktif['waktu_jawab'] ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="soal_id" value="<?= $soalId ?>">
        <input type="hidden" name="pilihan" value="">
        <input type="hidden" name="sisa_detik" value="0">
        <div class="question-card">
            <p class="question-text"><?= e($soalAktif['pertanyaan']) ?></p>
            <div class="answer-options">
                <?php
                $opts = [
                    'A' => $soalAktif['pilihan_a'],
                    'B' => $soalAktif['pilihan_b'],
                    'C' => $soalAktif['pilihan_c'],
                    'D' => $soalAktif['pilihan_d'],
                ];
                $shapes = ['A' => 'shape-triangle', 'B' => 'shape-diamond', 'C' => 'shape-circle', 'D' => 'shape-square'];
                foreach ($opts as $k => $v):
                ?>
                <button type="button" class="answer answer-<?= strtolower($k) ?>" data-pilihan="<?= $k ?>">
                    <span class="shape"><span class="<?= $shapes[$k] ?>"></span></span>
                    <span><?= e($v) ?></span>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
    </form>
</div>

<?php if ($fb): ?>
<div id="feedback" class="feedback <?= $fb['benar'] ? 'feedback-correct' : 'feedback-wrong' ?> show"
     data-next="main.php?kategori=<?= $kategoriId ?>">
    <div style="text-align:center;padding:var(--s5);">
        <h2><?= $fb['benar'] ? __('Benar!') . ' +' . (int)$fb['poin'] . ' ' . __('poin') : __('Salah') ?></h2>
        <?php if (!$fb['benar']): ?>
            <p style="color:#fff;margin-top:var(--s2);"><?= __('Kunci jawaban') ?>: <?= e($fb['kunci']) ?></p>
        <?php endif; ?>
        <?php if ($fb['penjelasan'] !== ''): ?>
            <p style="color:#fff;margin-top:var(--s2);max-width:520px;margin-inline:auto;"><?= e($fb['penjelasan']) ?></p>
        <?php endif; ?>
        <p style="color:#fff;margin-top:var(--s3);"><?= __('Skor sementara') ?>: <?= (int)$solo['total_poin'] ?></p>
    </div>
</div>
<?php endif; ?>

<?php if ($showLoginPrompt): ?>
<div class="panel mt-16 reveal">
    <header>
        <h2><?= __('Simpan Skor & Tampil di Peringkat') ?></h2>
        <p><?= __('Buat akun atau masuk agar skor kamu tersimpan permanen dan bisa bersaing di leaderboard.') ?></p>
    </header>
    <div class="panel-body text-center">
        <a class="btn btn-primary" href="auth/register.php?redirect=<?= urlencode('main.php?kategori=' . $kategoriId) ?>"><?= __('Daftar Sekarang') ?></a>
        <a class="btn btn-quiet" href="auth/login.php?redirect=<?= urlencode('main.php?kategori=' . $kategoriId) ?>"><?= __('Masuk') ?></a>
        <p class="muted mt-8"><?= __('Atau lanjut main sebagai Tamu — skor tidak tersimpan ke peringkat.') ?></p>
    </div>
</div>
<?php endif; ?>

<?php
mp_foot();