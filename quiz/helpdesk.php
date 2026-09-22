<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layout.php';
require_login();

if ($_SESSION['role'] === 'admin') {
    header('Location: ../admin/index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ticket'])) {
    csrf_verify();
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);

    if (!empty($subject) && !empty($message)) {
        $stmt = $pdo->prepare("INSERT INTO tickets (user_id, subject, message) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $subject, $message]);
        $success = "Pesan berhasil dikirim. Admin akan segera membalas.";
    }
}

$stmt_tickets = $pdo->prepare("SELECT * FROM tickets WHERE user_id = ? ORDER BY created_at DESC");
$stmt_tickets->execute([$user_id]);
$tickets = $stmt_tickets->fetchAll();

layout_header([
    'title' => 'Pusat Bantuan',
    'active' => 'helpdesk',
    'role' => 'peserta',
]);
?>


<section class="cols-2 stack">
    <div class="panel">
        <header>
            <h2>Kirim Pesan ke Admin</h2>
            <p>Jelaskan pertanyaan atau kendala yang Anda alami.</p>
        </header>
        <div class="panel-body">
            <?php if ($success): ?>
                <p class="notice notice-ok"><?= e($success) ?></p>
            <?php endif; ?>
            <form method="post">
                <?= csrf_field() ?>
                <div class="field">
                    <label class="field-label" for="subject">Subjek</label>
                    <input id="subject" type="text" name="subject" required>
                </div>
                <div class="field">
                    <label class="field-label" for="message">Isi pesan</label>
                    <textarea id="message" name="message" rows="5" required></textarea>
                </div>
                <div class="btn-row">
                    <button type="submit" name="submit_ticket" class="btn">Kirim pesan</button>
                </div>
            </form>
        </div>
    </div>

    <div class="panel">
        <header>
            <h2>Riwayat Tiket</h2>
            <p>Pesan yang pernah Anda kirim beserta balasan admin.</p>
        </header>
        <div class="panel-body">
        <?php if (empty($tickets)): ?>
            <p class="empty">Anda belum pernah mengirim pesan.</p>
        <?php else: ?>
            <?php foreach ($tickets as $t): ?>
                <article class="thread thread-<?= e($t['status']) ?>">
                    <div class="thread-head">
                        <strong><?= e($t['subject']) ?></strong>
                        <span class="tag <?= $t['status'] == 'open' ? 'tag-error' : ($t['status'] == 'answered' ? 'tag-ok' : '') ?>"><?= ucfirst(e($t['status'])) ?></span>
                    </div>
                    <p class="muted text-sm"><?= date('d M Y, H:i', strtotime($t['created_at'])) ?></p>
                    <div class="message"><?= nl2br(e($t['message'])) ?></div>
                    <?php if ($t['admin_reply']): ?>
                        <div class="message message-reply">
                            <strong>Balasan admin:</strong>
                            <?= nl2br(e($t['admin_reply'])) ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>
    </div>
</section>


<?php layout_footer(['base' => '..']); ?>
