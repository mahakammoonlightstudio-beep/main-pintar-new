<?php
/**
 * Main Pintar - admin/kelola-user.php
 * Kelola pengguna: cari, tambah user baru, ubah role (peserta<->admin), hapus.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';

if (!mp_is_admin()) {
    header('Location: login.php');
    exit;
}

$pdo = db();
$msg = $_GET['msg'] ?? '';
$q = trim($_GET['q'] ?? '');
$roleFilter = $_GET['role'] ?? '';

// ===== POST actions =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $uid = (int)($_POST['user_id'] ?? 0);

    if (isset($_POST['set_role']) && $uid > 0) {
        $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'peserta';
        // Jangan biarkan admin terakhir kehilangan role admin
        if ($role === 'peserta') {
            $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
            $st = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
            $st->execute([$uid]);
            if ((string)$st->fetchColumn() === 'admin' && $adminCount <= 1) {
                header('Location: kelola-user.php?msg=last_admin');
                exit;
            }
        }
        $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$role, $uid]);
        header('Location: kelola-user.php?msg=role_updated');
        exit;
    }

    if (isset($_POST['delete_user']) && $uid > 0) {
        if ($uid === mp_get_user_id()) {
            header('Location: kelola-user.php?msg=self_delete');
            exit;
        }
        try {
            // Hapus data terkait dulu (FK tidak memakai ON DELETE CASCADE)
            $pdo->beginTransaction();
            foreach (
                [
                    "DELETE FROM jawaban WHERE user_id = ?",
                    "DELETE FROM skor WHERE user_id = ?",
                    "DELETE FROM peserta_sesi WHERE user_id = ?",
                    "DELETE FROM notifikasi WHERE user_id = ?",
                ] as $sql
            ) {
                $pdo->prepare($sql)->execute([$uid]);
            }
            $pdo->prepare("DELETE FROM users WHERE id = ? AND role IN ('peserta','guest')")->execute([$uid]);
            $pdo->commit();
            header('Location: kelola-user.php?msg=deleted');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[MainPintar] Delete user failed: ' . $e->getMessage());
            header('Location: kelola-user.php?msg=error');
        }
        exit;
    }

    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username'] ?? '');
        $nama = trim($_POST['nama'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'peserta';

        if ($username === '' || $nama === '' || $password === '') {
            header('Location: kelola-user.php?msg=missing');
            exit;
        }
        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
            header('Location: kelola-user.php?msg=bad_username');
            exit;
        }
        if (strlen($password) < 6) {
            header('Location: kelola-user.php?msg=weak_password');
            exit;
        }
        $st = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $st->execute([$username]);
        if ($st->fetch()) {
            header('Location: kelola-user.php?msg=dup&n=1');
            exit;
        }
        try {
            $pdo->prepare("INSERT INTO users (username, nama_lengkap, password, role) VALUES (?, ?, ?, ?)")
                ->execute([$username, $nama, password_hash($password, PASSWORD_DEFAULT), $role]);
            header('Location: kelola-user.php?msg=added');
        } catch (Throwable $e) {
            header('Location: kelola-user.php?msg=dup');
        }
        exit;
    }
}

// ===== Daftar user (dengan pencarian & filter role) =====
$where = [];
$params = [];
if ($q !== '') {
    $where[] = "(u.username LIKE ? OR u.nama_lengkap LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if (in_array($roleFilter, ['admin', 'peserta', 'guest'], true)) {
    $where[] = "u.role = ?";
    $params[] = $roleFilter;
}
$sql = "SELECT u.id, u.username, u.nama_lengkap, u.role, u.jabatan, u.unit_kerja, u.created_at,
        (SELECT COUNT(*) FROM skor s WHERE s.user_id = u.id) AS jumlah_main
        FROM users u";
if ($where) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY u.created_at DESC LIMIT 100";
$st = $pdo->prepare($sql);
$st->execute($params);
$users = $st->fetchAll();

$pesan = [
    'role_updated' => __('Role pengguna diperbarui.'),
    'deleted'      => __('Akun berhasil dihapus.'),
    'added'        => __('User baru berhasil dibuat.'),
    'dup'          => __('Username sudah digunakan.'),
    'last_admin'   => __('Tidak bisa menurunkan satu-satunya admin.'),
    'self_delete'  => __('Tidak bisa menghapus akun sendiri di sini.'),
    'missing'      => __('Semua field wajib diisi.'),
    'bad_username' => __('Username 3-20 karakter, hanya huruf, angka, underscore.'),
    'weak_password'=> __('Password minimal 6 karakter.'),
    'error'        => __('Gagal menghapus. Pastikan migrasi database sudah dijalankan.'),
];

mp_head([
    'title' => __('Kelola Pengguna'),
    'active' => 'users',
    'body_class' => 'mp-admin',
    'base' => '../',
]);
?>
<div class="page-head">
    <h1><?= __('Kelola Pengguna') ?></h1>
    <p><?= __('Kelola akun peserta & admin: cari, ubah role, hapus, atau tambah baru.') ?></p>
</div>

<?php if (isset($pesan[$msg])): ?>
    <p class="notice notice-ok" role="status"><?= e($pesan[$msg]) ?></p>
<?php endif; ?>

<section class="panel mt-8 reveal">
    <header>
        <h2><?= __('Tambah User Baru') ?></h2>
    </header>
    <div class="panel-body">
        <form method="POST" class="form-inline-grid">
            <?= csrf_field() ?>
            <div class="field">
                <label class="field-label" for="nu_username"><?= __('Username') ?></label>
                <input id="nu_username" type="text" name="username" required pattern="[a-zA-Z0-9_]{3,20}">
            </div>
            <div class="field">
                <label class="field-label" for="nu_nama"><?= __('Nama Lengkap / Nama Tampil') ?></label>
                <input id="nu_nama" type="text" name="nama" required maxlength="100">
            </div>
            <div class="field">
                <label class="field-label" for="nu_password"><?= __('Password') ?></label>
                <input id="nu_password" type="text" name="password" required minlength="6" placeholder="min. 6 karakter">
            </div>
            <div class="field">
                <label class="field-label" for="nu_role"><?= __('Role') ?></label>
                <select id="nu_role" name="role">
                    <option value="peserta"><?= __('Peserta') ?></option>
                    <option value="admin"><?= __('Administrator') ?></option>
                </select>
            </div>
            <div class="field">
                <button class="btn btn-primary" name="add_user" value="1" type="submit"><?= __('Tambah') ?></button>
            </div>
        </form>
    </div>
</section>

<section class="panel mt-16 reveal">
    <header>
        <h2><?= __('Daftar Pengguna') ?></h2>
        <form method="GET" class="table-filter">
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= __('Cari username / nama…') ?>" aria-label="<?= __('Cari') ?>">
            <select name="role" aria-label="Role">
                <option value=""><?= __('Semua') ?></option>
                <option value="peserta"<?= $roleFilter === 'peserta' ? ' selected' : '' ?>><?= __('Peserta') ?></option>
                <option value="admin"<?= $roleFilter === 'admin' ? ' selected' : '' ?>><?= __('Administrator') ?></option>
                <option value="guest"<?= $roleFilter === 'guest' ? ' selected' : '' ?>>Tamu</option>
            </select>
            <button class="btn-sm" type="submit"><?= __('Cari') ?></button>
        </form>
    </header>
    <div class="panel-body panel-flush">
        <div class="table-scroll">
            <table class="data">
                <thead>
                    <tr>
                        <th><?= __('Username') ?></th>
                        <th><?= __('Nama Lengkap') ?></th>
                        <th><?= __('Data Pekerja') ?></th>
                        <th>Role</th>
                        <th class="num"><?= __('Main') ?></th>
                        <th><?= __('Terdaftar') ?></th>
                        <th><?= __('Aksi') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="7" class="empty"><?= __('Tidak ada pengguna yang cocok.') ?></td></tr>
                <?php else: foreach ($users as $usr): ?>
                    <tr>
                        <td class="mono"><?= e($usr['username']) ?><?= (int)$usr['id'] === mp_get_user_id() ? ' <span class="tag tag-info">' . __('Anda') . '</span>' : '' ?></td>
                        <td><?= e($usr['nama_lengkap']) ?></td>
                        <td>
                            <?php if ($usr['jabatan'] || $usr['unit_kerja']): ?>
                                <small><?= e(trim(($usr['jabatan'] ?? '') . ($usr['jabatan'] && $usr['unit_kerja'] ? ' — ' : '') . ($usr['unit_kerja'] ?? ''))) ?></small>
                            <?php else: ?><span class="muted">—</span><?php endif; ?>
                        </td>
                        <td>
                            <span class="tag <?= $usr['role'] === 'admin' ? 'tag-ok' : ($usr['role'] === 'guest' ? '' : 'tag-info') ?>">
                                <?= $usr['role'] === 'admin' ? __('Administrator') : ($usr['role'] === 'guest' ? __('Tamu') : __('Peserta')) ?>
                            </span>
                        </td>
                        <td class="num"><?= (int)$usr['jumlah_main'] ?></td>
                        <td><?= date('d M Y', strtotime($usr['created_at'])) ?></td>
                        <td>
                            <?php if ((int)$usr['id'] !== mp_get_user_id()): ?>
                            <div class="btn-row" style="gap:6px;flex-wrap:wrap;">
                                <form method="POST" style="margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="user_id" value="<?= (int)$usr['id'] ?>">
                                    <input type="hidden" name="role" value="<?= $usr['role'] === 'admin' ? 'peserta' : 'admin' ?>">
                                    <button class="btn-sm btn-quiet" name="set_role" value="1" type="submit">
                                        <?= $usr['role'] === 'admin' ? __('Jadikan Peserta') : __('Jadikan Admin') ?>
                                    </button>
                                </form>
                                <form method="POST" style="margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="user_id" value="<?= (int)$usr['id'] ?>">
                                    <button class="btn-sm btn-danger" name="delete_user" value="1" type="submit"
                                            data-confirm="<?= e(__('Yakin ingin menghapus akun ini? Semua skor & jawaban ikut terhapus.')) ?>"><?= __('Hapus') ?></button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php mp_foot(['base' => '../']); ?>
