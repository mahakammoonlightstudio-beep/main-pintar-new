<?php
/**
 * Main Pintar - api/poll.php (OPTIMIZED)
 * Single query per poll using JOINs. Sub-50ms target on InfinityFree.
 * GET  = poll status. POST = aksi (answer/start/next/finish).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';

header('Cache-Control: no-store');
header('Content-Type: application/json; charset=utf-8');

$pdo = db();

$sesiId = (int)($_GET['sesi'] ?? 0);
if ($sesiId <= 0) { json_out(['error' => 'sesi tidak valid'], 400); }

// Fetch session + kategori in ONE query.
// soal_elapsed dihitung di MySQL (TIMESTAMPDIFF vs NOW()) supaya selalu
// konsisten dengan soal_mulai_at yang juga ditulis oleh MySQL — bebas masalah timezone.
$st = $pdo->prepare(
    "SELECT s.*, k.nama_kategori, k.aktif AS kategori_aktif,
            GREATEST(0, COALESCE(TIMESTAMPDIFF(SECOND, s.soal_mulai_at, NOW()), 0)) AS soal_elapsed
     FROM sesi_kuis s
     LEFT JOIN kategori k ON k.id = s.kategori_id
     WHERE s.id = ? LIMIT 1"
);
$st->execute([$sesiId]);
$sesi = $st->fetch();
if (!$sesi) { json_out(['error' => 'sesi tidak ditemukan'], 404); }

$isHost = mp_is_admin() && (int)$sesi['host_user_id'] === (int)($_SESSION['user_id'] ?? 0);
// Kategori dinonaktifkan di tengah sesi: hanya host masih bisa memonitor
if (($sesi['kategori_aktif'] ?? '1') !== '1' && !$isHost) {
    json_out(['error' => 'kategori kuis ini sudah dinonaktifkan'], 410);
}
$kategoriId = (int)$sesi['kategori_id'];
$status = $sesi['status'];
$nomor = (int)$sesi['nomor_soal_sekarang'];
$soalElapsed = (int)($sesi['soal_elapsed'] ?? 0);
$uid = (int)($_SESSION['user_id'] ?? 0);

// Lepas lock sesi SEDINI: endpoint ini dipoll tiap 3-4 detik oleh banyak
// peserta. Tanpa ini, request yang saling menunggu lock session membuat
// host & peserta lain lambat (serialisasi PHP per-user).
session_write_close();

// ===== POST: actions =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $action = $input['action'] ?? '';

    if ($action === 'answer') {
        if ($uid <= 0) { json_out(['error' => 'sesi tidak valid'], 403); }

        // Verify participant in ONE query with EXISTS
        $stP = $pdo->prepare("SELECT 1 FROM peserta_sesi WHERE sesi_id = ? AND user_id = ? LIMIT 1");
        $stP->execute([$sesiId, $uid]);
        if (!$stP->fetch()) { json_out(['error' => 'kamu tidak terdaftar di sesi ini'], 403); }
        if ($status !== 'berjalan') { json_out(['error' => 'sesi tidak sedang berjalan'], 409); }

        // Get current question ID directly (avoid OFFSET) — hanya soal aktif
        $stQ = $pdo->prepare(
            "SELECT id, jawaban_benar, penjelasan, poin, waktu_jawab
             FROM soal WHERE kategori_id = ? AND aktif = 1 ORDER BY id ASC LIMIT 1 OFFSET ?"
        );
        $stQ->execute([$kategoriId, $nomor - 1]);
        $soal = $stQ->fetch();
        if (!$soal) { json_out(['error' => 'soal tidak ditemukan'], 404); }
        $soalId = (int)$soal['id'];

        // Prevent double answer - unique index handles this, but check first
        $stD = $pdo->prepare("SELECT 1 FROM jawaban WHERE sesi_id = ? AND user_id = ? AND soal_id = ? LIMIT 1");
        $stD->execute([$sesiId, $uid, $soalId]);
        if ($stD->fetch()) { json_out(['error' => 'sudah menjawab soal ini'], 409); }

        $pilihan = strtoupper(trim($input['pilihan'] ?? ''));
        if (!in_array($pilihan, ['A','B','C','D'], true)) { json_out(['error' => 'pilihan tidak valid'], 400); }

        // Server-side timer (dihitung MySQL, bebas selisih timezone)
        $sisa = max(0, (int)$soal['waktu_jawab'] - $soalElapsed);
        if ($sisa <= 0 && $soalElapsed > (int)$soal['waktu_jawab']) {
            json_out(['error' => 'waktu menjawab sudah habis'], 409);
        }
        $benar = ($pilihan === $soal['jawaban_benar']) ? 1 : 0;
        $poinDidapat = 0;
        if ($benar) {
            $poinDidapat = (int)$soal['poin'];
            if ($sisa > 0) {
                $poinDidapat += (int)ceil($sisa / max(1, (int)$soal['waktu_jawab']) * BONUS_KECEPATAN_MAX);
            }
        }

        $stI = $pdo->prepare(
            "INSERT INTO jawaban (sesi_id, user_id, soal_id, pilihan_user, benar, waktu_jawab_detik, poin_didapat, timestamp)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stI->execute([$sesiId, $uid, $soalId, $pilihan, $benar, (int)$soal['waktu_jawab'] - $sisa, $poinDidapat]);

        json_out([
            'ok' => true, 'benar' => (bool)$benar, 'poin_didapat' => $poinDidapat,
            'jawaban_benar' => $soal['jawaban_benar'], 'penjelasan' => $soal['penjelasan'] ?? '',
        ]);
    }

    if (in_array($action, ['start','next','finish'], true)) {
        if (!$isHost) { json_out(['error' => 'khusus host'], 403); }

        // Get total questions once (hanya aktif)
        $stCount = $pdo->prepare("SELECT COUNT(*) FROM soal WHERE kategori_id = ? AND aktif = 1");
        $stCount->execute([$kategoriId]);
        $totalSoal = (int)$stCount->fetchColumn();

        if ($action === 'start') {
            if ($status !== 'menunggu') { json_out(['error' => 'sesi sudah dimulai'], 409); }
            $pdo->prepare("UPDATE sesi_kuis SET status = 'berjalan', nomor_soal_sekarang = 1, soal_mulai_at = NOW() WHERE id = ?")
                ->execute([$sesiId]);
            json_out(['ok' => true]);
        }

        if ($action === 'next') {
            $nomorBaru = $nomor + 1;
            if ($nomorBaru > $totalSoal) {
                // Lewat soal terakhir = kuis selesai. Skor wajib disimpan di sini
                // juga, karena tombol "finish" sudah tidak tampil setelah status selesai.
                if ($status !== 'selesai') {
                    $pdo->prepare("UPDATE sesi_kuis SET status = 'selesai', selesai_at = NOW() WHERE id = ?")->execute([$sesiId]);
                    $pdo->prepare(
                        "INSERT INTO skor (user_id, kategori_id, total_poin, tanggal_main, sesi_id)
                         SELECT j.user_id, ?, SUM(j.poin_didapat), NOW(), ?
                         FROM jawaban j WHERE j.sesi_id = ?
                         GROUP BY j.user_id"
                    )->execute([$kategoriId, $sesiId, $sesiId]);
                }
                json_out(['ok' => true, 'status' => 'selesai']);
            }
            $pdo->prepare("UPDATE sesi_kuis SET nomor_soal_sekarang = ?, soal_mulai_at = NOW() WHERE id = ?")
                ->execute([$nomorBaru, $sesiId]);
            json_out(['ok' => true]);
        }

        if ($action === 'finish') {
            if ($status !== 'selesai') {
                $pdo->prepare("UPDATE sesi_kuis SET status = 'selesai', selesai_at = NOW() WHERE id = ?")->execute([$sesiId]);
                // Bulk insert skor - single query
                $pdo->prepare(
                    "INSERT INTO skor (user_id, kategori_id, total_poin, tanggal_main, sesi_id)
                     SELECT j.user_id, ?, SUM(j.poin_didapat), NOW(), ?
                     FROM jawaban j WHERE j.sesi_id = ?
                     GROUP BY j.user_id"
                )->execute([$kategoriId, $sesiId, $sesiId]);
            }
            json_out(['ok' => true, 'status' => 'selesai']);
        }
    }

    json_out(['error' => 'aksi tidak dikenal'], 400);
}

// ===== GET: poll status - OPTIMIZED with minimal queries =====
// Peserta harus terdaftar (atau host) untuk melihat soal saat sesi berjalan
$isPeserta = false;
if ($uid > 0) {
    $stV = $pdo->prepare("SELECT 1 FROM peserta_sesi WHERE sesi_id = ? AND user_id = ? LIMIT 1");
    $stV->execute([$sesiId, $uid]);
    $isPeserta = (bool)$stV->fetch();
}
if ($status === 'berjalan' && !$isHost && !$isPeserta) {
    json_out(['error' => 'kamu tidak terdaftar di sesi ini'], 403);
}

// 1. Total soal (hanya aktif)
$stCount = $pdo->prepare("SELECT COUNT(*) FROM soal WHERE kategori_id = ? AND aktif = 1");
$stCount->execute([$kategoriId]);
$totalSoal = (int)$stCount->fetchColumn();

// 2. Active question
$soal = null;
$sisaDetik = 0;
if ($status === 'berjalan' && $nomor > 0) {
    $stQ = $pdo->prepare(
        "SELECT id, pertanyaan, pilihan_a, pilihan_b, pilihan_c, pilihan_d, waktu_jawab
         FROM soal WHERE kategori_id = ? AND aktif = 1 ORDER BY id ASC LIMIT 1 OFFSET ?"
    );
    $stQ->execute([$kategoriId, $nomor - 1]);
    $soal = $stQ->fetch();

    if ($soal) {
        $sisaDetik = max(0, (int)$soal['waktu_jawab'] - $soalElapsed);
    }
}

// 3. Participants WITH scores in ONE query
$stPeserta = $pdo->prepare(
    "SELECT u.nama_lengkap AS nama, ps.bergabung_at,
            COALESCE(SUM(j.poin_didapat), 0) AS total_poin
     FROM peserta_sesi ps
     JOIN users u ON u.id = ps.user_id
     LEFT JOIN jawaban j ON j.sesi_id = ps.sesi_id AND j.user_id = ps.user_id
     WHERE ps.sesi_id = ?
     GROUP BY ps.user_id, u.nama_lengkap, ps.bergabung_at
     ORDER BY total_poin DESC, ps.bergabung_at ASC"
);
$stPeserta->execute([$sesiId]);
$peserta = $stPeserta->fetchAll();

// 4. Distribution + answered count (host only)
$distribusi = null;
$jumlahJawab = 0;
$semuaJawab = false;
if ($status === 'berjalan' && $soal) {
    $soalId = (int)$soal['id'];
    $stD = $pdo->prepare(
        "SELECT pilihan_user, COUNT(*) AS jumlah
         FROM jawaban WHERE sesi_id = ? AND soal_id = ?
         GROUP BY pilihan_user"
    );
    $stD->execute([$sesiId, $soalId]);
    $distribusi = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0];
    foreach ($stD->fetchAll() as $row) {
        $key = strtoupper((string)$row['pilihan_user']);
        if (isset($distribusi[$key])) {
            $distribusi[$key] = (int)$row['jumlah'];
        }
    }
    $jumlahJawab = array_sum($distribusi);
    $semuaJawab = ($jumlahJawab >= count($peserta) && count($peserta) > 0);
}

// 5. Final ranking (only when finished)
$ranking = null;
if ($status === 'selesai') {
    $stR = $pdo->prepare(
        "SELECT u.nama_lengkap AS nama, SUM(j.poin_didapat) AS total_poin
         FROM jawaban j
         JOIN users u ON u.id = j.user_id
         WHERE j.sesi_id = ?
         GROUP BY j.user_id, u.nama_lengkap
         ORDER BY total_poin DESC"
    );
    $stR->execute([$sesiId]);
    $ranking = $stR->fetchAll();
}

// 6. Current user's answer for active question
$saya = null;
if ($status === 'berjalan' && $soal && $uid > 0) {
    $soalId = (int)$soal['id'];
    $stS = $pdo->prepare(
        "SELECT j.benar, j.poin_didapat, s.jawaban_benar, s.penjelasan
         FROM jawaban j JOIN soal s ON s.id = j.soal_id
         WHERE j.sesi_id = ? AND j.user_id = ? AND j.soal_id = ? LIMIT 1"
    );
    $stS->execute([$sesiId, $uid, $soalId]);
    $saya = $stS->fetch();
}

json_out([
    'ok' => true,
    'sesi_id' => $sesiId,
    'kode' => $sesi['kode_ruangan'],
    'kategori' => $sesi['nama_kategori'],
    'status' => $status,
    'nomor' => $nomor,
    'total' => $totalSoal,
    'sisa_detik' => $sisaDetik,
    'soal' => $soal,
    'peserta' => $peserta,
    'jumlah_jawab' => $jumlahJawab,
    'semua_jawab' => $semuaJawab,
    'distribusi' => $isHost ? $distribusi : null,
    'ranking' => $ranking,
    'saya' => $saya,
]);