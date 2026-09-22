<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layout.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_ticket'])) {
    csrf_verify();
    $ticket_id = (int)$_POST['ticket_id'];
    $reply = trim($_POST['admin_reply']);

    if (!empty($reply)) {
        $pdo->prepare("UPDATE tickets SET admin_reply = ?, status = 'answered' WHERE id = ?")->execute([$reply, $ticket_id]);
        audit_log('update', 'tickets', $ticket_id, 'Balasan admin dikirim');
        header('Location: helpdesk.php?status=replied');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_ticket_action'])) {
    csrf_verify();
    $id = (int)$_POST['close_ticket_id'];
    $pdo->prepare("UPDATE tickets SET status = 'closed' WHERE id = ?")->execute([$id]);
    audit_log('toggle', 'tickets', $id, 'Tiket ditutup');
    header('Location: helpdesk.php');
    exit;
}

$stmt_tickets = $pdo->query("
    SELECT t.*, u.nama, u.nim
    FROM tickets t
    JOIN users u ON t.user_id = u.id
    ORDER BY
        CASE WHEN t.status = 'open' THEN 0 ELSE 1 END,
        t.created_at DESC
");
$tickets = $stmt_tickets->fetchAll();

$open_tickets = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'open'")->fetchColumn();

layout_header([
    'title'  => 'Pusat Bantuan',
    'active' => 'helpdesk',
    'role'   => 'admin',
    'tickets' => $open_tickets,
]);
?>


<section class="panel">
    <header>
        <h2>Pusat Bantuan (Helpdesk)</h2>
        <p>Pesan masuk dari peserta dan tanggapan Anda.</p>
    </header>
    <div class="panel-body">
        <?php if (isset($_GET['status']) && $_GET['status'] == 'replied'): ?>
            <p class="notice notice-ok">Balasan berhasil dikirim.</p>
        <?php endif; ?>

        <?php if (empty($tickets)): ?>
            <p class="empty">Belum ada pesan/tiket masuk.</p>
        <?php else: ?>
            <?php foreach ($tickets as $t): ?>
                <article class="thread thread-<?= e($t['status']) ?>">
                    <div class="thread-head">
                        <strong><?= e($t['subject']) ?></strong>
                        <span class="tag <?= $t['status'] == 'open' ? 'tag-error' : ($t['status'] == 'answered' ? 'tag-ok' : '') ?>"><?= ucfirst(e($t['status'])) ?></span>
                    </div>
                    <p class="muted text-sm">
                        Dari: <strong><?= e($t['nama']) ?></strong> (<?= e($t['nim']) ?>) &mdash; <?= date('d M Y, H:i', strtotime($t['created_at'])) ?>
                    </p>
                    <div class="message"><?= nl2br(e($t['message'])) ?></div>

                    <?php if ($t['admin_reply']): ?>
                        <div class="message message-reply">
                            <strong>Balasan Anda:</strong>
                            <?= nl2br(e($t['admin_reply'])) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($t['status'] != 'closed'): ?>
                        <form method="post" class="reply-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                            <div class="field">
                                <label class="field-label" for="admin_reply_<?= $t['id'] ?>">Balasan / solusi</label>
                                <textarea id="admin_reply_<?= $t['id'] ?>" name="admin_reply" rows="3" placeholder="Tulis balasan untuk peserta..."></textarea>
                            </div>
                            <div class="btn-row">
                                <button type="submit" name="reply_ticket" class="btn-sm">Kirim balasan</button>
                                <button type="submit" name="close_ticket_action" class="btn-sm btn-quiet" onclick="return confirm('Tutup tiket ini?')">Tutup tiket</button>
                            </div>
                            <input type="hidden" name="close_ticket_id" value="<?= $t['id'] ?>">
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>


<?php layout_footer(['base' => '..']); ?>
