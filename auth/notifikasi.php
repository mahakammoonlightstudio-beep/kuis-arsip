<?php
/**
 * Kuis Arsip (htdocs) - auth/notifikasi.php
 * Kotak masuk notifikasi peserta: broadcast admin + pesan sistem.
 * Aksi: tandai satu dibaca, tandai semua dibaca, hapus satu.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layout.php';

if (!is_logged_in()) {
    header('Location: login.php?redirect=notifikasi.php');
    exit;
}

$uid = (int)$_SESSION['user_id'];
$msg = $_GET['msg'] ?? '';

// ===== POST actions =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $notifId = (int)($_POST['id'] ?? 0);

    if (isset($_POST['read_all'])) {
        $pdo->prepare("UPDATE notifikasi SET dibaca = 1 WHERE user_id = ? AND dibaca = 0")->execute([$uid]);
        header('Location: notifikasi.php?msg=read_all');
        exit;
    }
    if (isset($_POST['read_one']) && $notifId > 0) {
        $pdo->prepare("UPDATE notifikasi SET dibaca = 1 WHERE id = ? AND user_id = ?")->execute([$notifId, $uid]);
        header('Location: notifikasi.php?msg=read_one');
        exit;
    }
    if (isset($_POST['delete_one']) && $notifId > 0) {
        $pdo->prepare("DELETE FROM notifikasi WHERE id = ? AND user_id = ?")->execute([$notifId, $uid]);
        header('Location: notifikasi.php?msg=deleted');
        exit;
    }
}

$st = $pdo->prepare(
    "SELECT id, judul, pesan, jenis, dibaca, created_at
     FROM notifikasi WHERE user_id = ?
     ORDER BY dibaca ASC, created_at DESC LIMIT 50"
);
$st->execute([$uid]);
$items = $st->fetchAll();
$belumBaca = count(array_filter($items, fn($n) => !(int)$n['dibaca']));

layout_header([
    'title' => __('Notifikasi'),
    'active' => 'notifikasi',
    'role' => $_SESSION['role'] ?? 'guest',
]);
?>
<div class="page-head">
    <h1><?= __('Notifikasi') ?></h1>
    <p><?= __('Pengumuman resmi dari admin.') ?></p>
</div>

<?php if ($msg === 'read_all'): ?>
    <p class="notice notice-ok" role="status"><?= __('Semua notifikasi ditandai sudah dibaca.') ?></p>
<?php elseif ($msg === 'read_one'): ?>
    <p class="notice notice-ok" role="status"><?= __('Notifikasi ditandai sudah dibaca.') ?></p>
<?php elseif ($msg === 'deleted'): ?>
    <p class="notice notice-ok" role="status"><?= __('Notifikasi dihapus.') ?></p>
<?php endif; ?>

<section class="panel">
    <header>
        <h2><?= __('Kotak Masuk') ?><?= $belumBaca > 0 ? ' (' . $belumBaca . ' ' . __('Baru') . ')' : '' ?></h2>
        <?php if ($belumBaca > 0): ?>
        <form method="POST" style="margin:0;">
            <?= csrf_field() ?>
            <button class="btn-sm btn-quiet" name="read_all" value="1" type="submit"><?= __('Tandai semua dibaca') ?></button>
        </form>
        <?php endif; ?>
    </header>
    <div class="panel-body">
        <?php if (empty($items)): ?>
            <p class="muted text-center"><?= __('Belum ada notifikasi.') ?></p>
        <?php else: ?>
        <ul class="notif-list">
            <?php foreach ($items as $n): ?>
            <li class="notif-item jenis-<?= e($n['jenis']) ?><?= $n['dibaca'] ? '' : ' unread' ?>">
                <span class="notif-icon" aria-hidden="true">
                    <?php if ($n['jenis'] === 'success'): ?>
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <?php elseif ($n['jenis'] === 'warning'): ?>
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <?php elseif ($n['jenis'] === 'danger'): ?>
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <?php else: ?>
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <?php endif; ?>
                </span>
                <div class="notif-main">
                    <div class="notif-title">
                        <?= e($n['judul']) ?>
                        <?php if (!$n['dibaca']): ?><span class="tag tag-info"><?= __('Baru') ?></span><?php endif; ?>
                    </div>
                    <div class="notif-body"><?= nl2br(e($n['pesan'])) ?></div>
                    <div class="notif-time"><?= date('d M Y H:i', strtotime($n['created_at'])) ?></div>
                </div>
                <div class="notif-actions">
                    <?php if (!$n['dibaca']): ?>
                    <form method="POST" style="margin:0;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                        <button class="btn-sm btn-quiet" name="read_one" value="1" type="submit"><?= __('Tandai dibaca') ?></button>
                    </form>
                    <?php endif; ?>
                    <form method="POST" style="margin:0;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                        <button class="btn-sm btn-danger" name="delete_one" value="1" type="submit"
                                data-confirm="<?= e(__('Yakin ingin menghapus notifikasi ini?')) ?>"><?= __('Hapus') ?></button>
                    </form>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</section>

<?php layout_footer(); ?>
