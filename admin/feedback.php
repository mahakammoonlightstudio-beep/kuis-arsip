<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layout.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (isset($_POST['delete_feedback_action'])) {
        $id = (int)$_POST['delete_feedback_id'];
        $pdo->prepare("DELETE FROM feedback WHERE id = ?")->execute([$id]);
        audit_log('delete', 'feedback', $id, 'Masukan pengguna dihapus');
        header('Location: feedback.php?status=deleted');
        exit;
    }
}

$feedbacks = $pdo->query("SELECT * FROM feedback ORDER BY created_at DESC")->fetchAll();

$open_tickets = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'open'")->fetchColumn();

layout_header([
    'title'  => 'Masukan Pengguna',
    'active' => 'feedback',
    'role'   => 'admin',
    'tickets' => $open_tickets,
]);
?>


<section class="panel">
    <header>
        <h2>Masukan &amp; Saran Pengguna</h2>
        <p>Pesan yang dikirim melalui dialog saran di beranda.</p>
    </header>
    <div class="panel-body">
        <?php if (isset($_GET['status']) && $_GET['status'] == 'deleted'): ?>
            <p class="notice notice-ok">Masukan berhasil dihapus.</p>
        <?php endif; ?>

        <?php if (empty($feedbacks)): ?>
            <p class="empty">Belum ada masukan yang masuk.</p>
        <?php else: ?>
            <?php foreach ($feedbacks as $f): ?>
                <article class="thread">
                    <div class="thread-head">
                        <strong><?= e($f['name']) ?></strong>
                        <span class="muted text-sm"><?= date('d M Y, H:i', strtotime($f['created_at'])) ?></span>
                    </div>
                    <div class="message"><?= nl2br(e($f['message'])) ?></div>
                    <form method="post" class="form-inline" onsubmit="return confirm('Hapus masukan ini?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="delete_feedback_action" value="1">
                        <input type="hidden" name="delete_feedback_id" value="<?= $f['id'] ?>">
                        <button type="submit" class="btn-sm btn-danger">Hapus</button>
                    </form>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>


<?php layout_footer(['base' => '..']); ?>
