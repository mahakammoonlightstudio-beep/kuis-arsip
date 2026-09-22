<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layout.php';
require_login();

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

$filter_action = $_GET['action'] ?? '';
$filter_admin = (int)($_GET['admin_id'] ?? 0);

$where = [];
$params = [];

if ($filter_action !== '') {
    $where[] = "action = ?";
    $params[] = $filter_action;
}
if ($filter_admin > 0) {
    $where[] = "admin_id = ?";
    $params[] = $filter_admin;
}
$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_logs $where_sql");
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = max(1, ceil($total_records / $per_page));

$stmt = $pdo->prepare("SELECT * FROM admin_logs $where_sql ORDER BY created_at DESC LIMIT $per_page OFFSET $offset");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$admin_list = $pdo->query("SELECT id, nama FROM users WHERE role = 'admin' ORDER BY nama")->fetchAll();

$open_tickets = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'open'")->fetchColumn();

layout_header([
    'title'  => 'Audit Log',
    'active' => 'audit',
    'role'   => 'admin',
    'tickets' => $open_tickets,
]);
?>


<section class="panel">
    <header>
        <h2>Riwayat Aktivitas Admin</h2>
    </header>
    <div class="panel-body">
        <form method="get" class="filterbar">
            <div class="field">
                <label class="field-label">Aktivitas</label>
                <select name="action" onchange="this.form.submit()">
                    <option value="">Semua</option>
                    <?php foreach (['login' => 'Login', 'logout' => 'Logout', 'insert' => 'Tambah Data', 'update' => 'Perbarui Data', 'delete' => 'Hapus Data', 'toggle' => 'Ubah Status', 'export' => 'Ekspor', 'reset' => 'Reset'] as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $filter_action === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field-label">Admin</label>
                <select name="admin_id" onchange="this.form.submit()">
                    <option value="0">Semua</option>
                    <?php foreach ($admin_list as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= $filter_admin == $a['id'] ? 'selected' : '' ?>><?= e($a['nama']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
    <div class="panel-flush">
        <div class="table-scroll">
            <table class="data">
                <thead>
                    <tr><th>Waktu</th><th>Admin</th><th>Aktivitas</th><th>Target</th><th>Catatan</th><th>IP</th></tr>
                </thead>
                <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="6" class="empty">Belum ada catatan aktivitas.</td></tr>
                <?php else: foreach ($logs as $log): ?>
                    <tr>
                        <td><?= date('d M Y, H:i', strtotime($log['created_at'])) ?></td>
                        <td><?= e($log['admin_nama']) ?></td>
                        <td><strong><?= e($log['action']) ?></strong></td>
                        <td><?= e($log['target_table']) ?> #<?= e($log['target_id']) ?></td>
                        <td class="cell-truncate" title="<?= e($log['notes']) ?>"><?= e(mb_substr($log['notes'], 0, 60)) ?></td>
                        <td><span class="mono"><?= e($log['ip_address']) ?></span></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($total_pages > 1): ?>
        <nav class="pager" aria-label="Navigasi halaman">
            <?php
            $window = 2;
            $qbase = '?page=:p&action=' . urlencode($filter_action) . '&admin_id=' . $filter_admin;
            if ($page > 1): ?>
                <a class="btn-sm btn-quiet" href="<?= str_replace(':p', $page - 1, $qbase) ?>" rel="prev">&larr; Sebelumnya</a>
            <?php endif;

            $start = max(1, $page - $window);
            $end = min($total_pages, $page + $window);

            if ($start > 1): ?>
                <a class="btn-sm btn-quiet" href="<?= str_replace(':p', 1, $qbase) ?>">1</a>
                <?php if ($start > 2): ?><span class="pager-ellipsis">&hellip;</span><?php endif; ?>
            <?php endif;

            for ($p = $start; $p <= $end; $p++): ?>
                <a class="btn-sm <?= $p === $page ? '' : 'btn-quiet' ?>" href="<?= str_replace(':p', $p, $qbase) ?>"<?= $p === $page ? ' aria-current="page"' : '' ?>><?= $p ?></a>
            <?php endfor;

            if ($end < $total_pages): ?>
                <?php if ($end < $total_pages - 1): ?><span class="pager-ellipsis">&hellip;</span><?php endif; ?>
                <a class="btn-sm btn-quiet" href="<?= str_replace(':p', $total_pages, $qbase) ?>"><?= $total_pages ?></a>
            <?php endif;

            if ($page < $total_pages): ?>
                <a class="btn-sm btn-quiet" href="<?= str_replace(':p', $page + 1, $qbase) ?>" rel="next">Selanjutnya &rarr;</a>
            <?php endif; ?>
        </nav>
        <p class="muted text-center pager-meta">Halaman <?= $page ?> dari <?= $total_pages ?> (<?= $total_records ?> catatan)</p>
        <?php endif; ?>
    </div>
</section>


<?php layout_footer(['base' => '..']); ?>
