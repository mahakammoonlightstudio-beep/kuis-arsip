<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layout.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (isset($_POST['reset_pass_action'])) {
        $user_id = (int)$_POST['reset_pass_id'];
        $new_hash = password_hash('123456', PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'peserta'")->execute([$new_hash, $user_id]);
        audit_log('reset', 'users', $user_id, 'Password di-reset ke default 123456');
        header('Location: users.php?status=' . urlencode('Password peserta berhasil di-reset menjadi: 123456'));
        exit;
    }

    if (isset($_POST['delete_user_action'])) {
        $user_id = (int)$_POST['delete_user_id'];
        $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'peserta'")->execute([$user_id]);
        audit_log('delete', 'users', $user_id, 'Akun peserta dihapus permanen');
        header('Location: users.php?status=' . urlencode('Akun peserta berhasil dihapus!'));
        exit;
    }
}

$stmt = $pdo->query("
    SELECT u.id, u.nama, u.nim, u.email, u.created_at, u.last_login, u.last_quiz_at, u.photo_path,
           COUNT(r.id) as total_done
    FROM users u
    LEFT JOIN results r ON u.id = r.user_id
    WHERE u.role = 'peserta'
    GROUP BY u.id
    ORDER BY u.nama ASC
");
$users = $stmt->fetchAll();

$open_tickets = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'open'")->fetchColumn();

layout_header([
    'title'  => 'Manajemen Peserta',
    'active' => 'users',
    'role'   => 'admin',
    'tickets' => $open_tickets,
]);
?>


<section class="panel">
    <header>
        <h2>Manajemen Peserta</h2>
        <p>Total terdaftar: <strong><?= count($users) ?></strong> peserta.</p>
    </header>
    <div class="panel-body">
        <?php if (isset($_GET['status'])): ?>
            <p class="notice notice-ok"><?= e($_GET['status']) ?></p>
        <?php endif; ?>

        <div class="export-actions">
            <a class="export-card" href="export_users.php">
                <span class="export-card-icon" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                </span>
                <span>
                    <span class="export-card-title">Ekspor data peserta <span class="format-badge">Excel</span></span>
                    <span class="export-card-desc">Unduh seluruh data peserta dalam format spreadsheet siap cetak.</span>
                </span>
            </a>
            <a class="export-card" href="import_users.php">
                <span class="export-card-icon" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                </span>
                <span>
                    <span class="export-card-title">Impor massal <span class="format-badge">CSV</span></span>
                    <span class="export-card-desc">Daftarkan banyak peserta sekaligus melalui berkas templat CSV.</span>
                </span>
            </a>
        </div>

        <div class="table-scroll" style="margin-top: var(--s6);">
            <table class="data">
                <thead>
                    <tr>
                        <th class="num">#</th><th>Foto</th><th>Peserta</th><th>NIM / NIP</th><th>Surel</th>
                        <th class="num">Kegiatan</th><th>Login terakhir</th><th>Ujian terakhir</th><th>Terdaftar</th><th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="10" class="empty">Belum ada peserta terdaftar.</td></tr>
                <?php else: $no = 1; foreach ($users as $u): ?>
                    <tr>
                        <td class="num"><?= $no++ ?></td>
                        <td>
                            <?php if (!empty($u['photo_path']) && file_exists(__DIR__ . '/../assets/' . $u['photo_path'])): ?>
                                <img src="../assets/<?= e($u['photo_path']) ?>" alt="Foto <?= e($u['nama']) ?>" width="38" height="38" class="avatar">
                            <?php else: ?>
                                <div class="avatar-fallback" style="width:38px;height:38px;">-</div>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= e($u['nama']) ?></strong></td>
                        <td><?= e($u['nim']) ?></td>
                        <td><?= e($u['email'] ?: '-') ?></td>
                        <td class="num"><?= $u['total_done'] ?></td>
                        <td><?= $u['last_login'] ? date('d M Y, H:i', strtotime($u['last_login'])) : '<span class="muted">Belum</span>' ?></td>
                        <td><?= $u['last_quiz_at'] ? date('d M Y, H:i', strtotime($u['last_quiz_at'])) : '<span class="muted">Belum</span>' ?></td>
                        <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                        <td class="row-actions">
                            <form method="post" class="form-inline" onsubmit="return confirm('Reset kata sandi peserta ini menjadi 123456?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="reset_pass_action" value="1">
                                <input type="hidden" name="reset_pass_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn-sm btn-quiet">Reset sandi</button>
                            </form>
                            <form method="post" class="form-inline" onsubmit="return confirm('Hapus akun peserta ini? Semua data nilai dan riwayat akan terhapus.')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="delete_user_action" value="1">
                                <input type="hidden" name="delete_user_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn-sm btn-danger">Hapus</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>


<?php layout_footer(['base' => '..']); ?>
