<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layout.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

// Auto-backup harian (lazy cron): berjalan maks. 1x/hari saat dasbor dibuka admin
require_once __DIR__ . '/../includes/backup.php';
$auto_backup_file = hq_backup_maybe_auto($pdo, 'Kuis Arsip - Diarpus Kukar');
$backup_error = hq_backup_last_error(); // banner jika auto-backup terakhir gagal

if (isset($_POST['toggle_reg_action']) && isset($_SESSION['user_id'])) {
    csrf_verify();
    $current = $pdo->query("SELECT is_registration_open FROM app_settings WHERE id = 1")->fetchColumn();
    $new_val = $current ? 0 : 1;
    $pdo->prepare("UPDATE app_settings SET is_registration_open = ? WHERE id = 1")->execute([$new_val]);
    audit_log('toggle', 'app_settings', '1', 'Status pendaftaran: ' . ($new_val ? 'Dibuka' : 'Ditutup'));
    header("Location: index.php?status=reg_updated");
    exit;
}

$reg_status = $pdo->query("SELECT is_registration_open FROM app_settings WHERE id = 1")->fetchColumn();
$filter_room = $_GET['room_id'] ?? 'all';

$sql = "
SELECT u.id, u.nama, u.nim, r.room_name, r.room_code, res.id AS result_id, res.score, res.total_questions, res.created_at, res.room_id
FROM users u
LEFT JOIN results res ON u.id = res.user_id
LEFT JOIN rooms r ON res.room_id = r.id
WHERE u.role = 'peserta'
";
$params = [];

if ($filter_room === 'individual') {
    $sql .= " AND res.room_id IS NULL";
} elseif ($filter_room !== 'all') {
    $sql .= " AND res.room_id = ?";
    $params[] = $filter_room;
}
$sql .= " ORDER BY res.score DESC, res.created_at ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll();

$rooms_list = $pdo->query("SELECT * FROM rooms ORDER BY created_at DESC")->fetchAll();
$total_peserta = $pdo->query("SELECT COUNT(*) FROM users WHERE role='peserta'")->fetchColumn();

$total_selesai = 0;
$total_lulus = 0;
$total_gagal = 0;
$sum_score = 0;
$dist_values = [0, 0, 0, 0, 0];

$cat_data = [];
$stmt_cat = $pdo->query("SELECT answers_json FROM results WHERE answers_json IS NOT NULL");
$all_results = $stmt_cat->fetchAll();

foreach ($all_results as $res) {
    $answers = json_decode($res['answers_json'], true);
    if (is_array($answers)) {
        foreach ($answers as $ans) {
            $cat = $ans['category'] ?? 'Umum';
            if (!isset($cat_data[$cat])) {
                $cat_data[$cat] = ['correct' => 0, 'total' => 0];
            }
            $cat_data[$cat]['total']++;
            if (!empty($ans['is_correct'])) {
                $cat_data[$cat]['correct']++;
            }
        }
    }
}

$cat_labels = [];
$cat_values = [];
$cat_table_rows = [];
foreach ($cat_data as $cat_name => $stats) {
    if ($stats['total'] > 0) {
        $pct = round(($stats['correct'] / $stats['total']) * 100);
        $cat_labels[] = $cat_name;
        $cat_values[] = $pct;
        $cat_table_rows[] = [
            'name' => $cat_name,
            'total' => $stats['total'],
            'correct' => $stats['correct'],
            'pct' => $pct,
            'status' => $pct >= 70 ? 'good' : ($pct >= 50 ? 'warn' : 'poor')
        ];
    }
}

foreach ($results as $r) {
    if ($r['score'] !== null && $r['total_questions'] > 0) {
        $total_selesai++;
        $pct = round(($r['score'] / $r['total_questions']) * 100);
        $sum_score += $pct;
        if ($pct >= 70) $total_lulus++; else $total_gagal++;
        if ($pct <= 20) $dist_values[0]++;
        elseif ($pct <= 40) $dist_values[1]++;
        elseif ($pct <= 60) $dist_values[2]++;
        elseif ($pct <= 80) $dist_values[3]++;
        else $dist_values[4]++;
    }
}

$rata_rata = $total_selesai > 0 ? round($sum_score / $total_selesai) : 0;
$persentase_lulus = $total_selesai > 0 ? round(($total_lulus / $total_selesai) * 100) : 0;
$participation_rate = $total_peserta > 0 ? round(($total_selesai / $total_peserta) * 100) : 0;

$stmt_lb = $pdo->query("
    SELECT u.nama, u.nim,
           MAX(ROUND((r.score / r.total_questions) * 100)) as best_pct,
           MAX(r.score) as best_score
    FROM users u
    JOIN results r ON u.id = r.user_id
    WHERE u.role = 'peserta' AND r.total_questions > 0
    GROUP BY u.id
    ORDER BY best_pct DESC, best_score DESC, u.nama ASC
    LIMIT 10
");
$leaderboard = $stmt_lb->fetchAll();

$open_tickets = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'open'")->fetchColumn();

$reg_status_text = $reg_status ? 'dibuka' : 'ditutup';
$reg_status_class = $reg_status ? 'notice-ok' : 'notice-warn';
$reg_button_text = $reg_status ? 'Tutup pendaftaran' : 'Buka pendaftaran';
$reg_button_class = $reg_status ? 'btn-danger' : 'btn-gold';
$reg_confirm_msg = $reg_status ? 'Tutup pendaftaran?' : 'Buka pendaftaran?';

$chart_data = json_encode([
    'passFail' => [$total_lulus, $total_gagal],
    'distribution' => $dist_values,
    'categories' => ['labels' => $cat_labels, 'values' => $cat_values],
    'metrics' => [
        'total' => $total_peserta,
        'completed' => $total_selesai,
        'avg' => $rata_rata,
        'passRate' => $persentase_lulus,
        'participation' => $participation_rate
    ]
]);

layout_header([
    'title'  => 'Dasbor Admin',
    'active' => 'dashboard',
    'role'   => 'admin',
    'tickets' => $open_tickets,
    'head' => '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>',
]);
?>


<?php if (isset($reg_status)): ?>
<div class="reg-toggle-card panel" style="margin-bottom: var(--s6);">
    <div class="panel-body" style="display: flex; align-items: center; justify-content: space-between; gap: var(--s4); flex-wrap: wrap;">
        <div>
            <div style="display: flex; align-items: center; gap: var(--s3);">
                <span class="reg-toggle-icon" aria-hidden="true">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 12.79A9 9 0 1 1 12.21 3 7 7 0 0 0 21 12.79z"/>
                    </svg>
                </span>
                <div>
                    <strong>Pendaftaran Peserta</strong>
                    <p class="muted text-sm" style="margin: 0;">Kontrol akses pendaftaran peserta baru</p>
                </div>
            </div>
            <div class="reg-status-badge" style="margin-top: var(--s2);">
                <span class="tag tag-<?= $reg_status ? 'ok' : 'warn' ?>" style="font-size: var(--t-sm);">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                        <?php if ($reg_status): ?>
                            <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
                        <?php else: ?>
                            <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                        <?php endif; ?>
                    </svg>
                    <?= $reg_status ? 'Dibuka' : 'Ditutup' ?>
                </span>
            </div>
        </div>
        <form method="post" class="form-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="toggle_reg_action" value="1">
            <button type="submit" class="btn-sm <?= $reg_button_class ?>" onclick="return confirm('<?= $reg_confirm_msg ?>')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: var(--s1); vertical-align: middle;">
                    <?php if ($reg_status): ?>
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>
                    <?php else: ?>
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>
                    <?php endif; ?>
                </svg>
                <?= $reg_button_text ?>
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if (isset($_GET['status']) && $_GET['status'] === 'reg_updated'): ?>
<p class="notice notice-ok" role="status">Status pendaftaran berhasil diubah.</p>
<?php endif; ?>

<?php if (!empty($auto_backup_file)): ?>
<p class="notice notice-ok" role="status">
    Backup otomatis harian tersimpan: <code><?= e($auto_backup_file) ?></code>
    &middot; <a href="backup.php">Lihat riwayat backup</a>
</p>
<?php endif; ?>

<?php if (!empty($backup_error)): ?>
<p class="notice notice-error" role="alert">
    <strong>⚠️ Backup otomatis GAGAL</strong>
    (<?= date('d M Y, H:i', $backup_error['time']) ?> WITA &middot; <?= e($backup_error['stage']) ?>):
    <?= e($backup_error['message']) ?>
    &middot; <a href="backup.php">Coba backup manual</a>
    atau <a href="restore.php">pulihkan dari backup terakhir</a>.
</p>
<?php endif; ?>

<section aria-labelledby="metrics-heading" class="metrics" style="margin-bottom: var(--s4);">
    <h2 id="metrics-heading" class="visually-hidden">Metrik Utama</h2>
    <article class="metric">
        <dt>Total Peserta</dt>
        <dd><?= $total_peserta ?><span class="metric-note">terdaftar di sistem</span></dd>
    </article>
    <article class="metric">
        <dt>Sudah Mengerjakan</dt>
        <dd><?= $total_selesai ?><span class="metric-note">dari total peserta</span></dd>
    </article>
    <article class="metric">
        <dt>Partisipasi</dt>
        <dd><?= $participation_rate ?>%<span class="metric-note">peserta aktif</span></dd>
    </article>
    <article class="metric">
        <dt>Rata-rata Kelas</dt>
        <dd><?= $rata_rata ?>%<span class="metric-note">skor rata-rata</span></dd>
    </article>
    <article class="metric">
        <dt>Tingkat Kelulusan</dt>
        <dd><?= $persentase_lulus ?>%<span class="metric-note">standar minimal 70%</span></dd>
    </article>
</section>

<section class="cols-2" aria-labelledby="charts-heading">
    <h2 id="charts-heading" class="visually-hidden">Grafik Analisis</h2>

    <article class="panel">
        <header>
            <h2>Lulus vs Belum Lulus</h2>
            <p class="muted">Perbandingan peserta lulus dan belum lulus</p>
        </header>
        <div class="panel-body">
            <div class="chart-wrap">
                <canvas id="passFailChart" aria-label="Diagram lingkaran persentase kelulusan"></canvas>
                <div class="chart-center" id="passFailCenter">
                    <div class="center-value"><?= $persentase_lulus ?>%</div>
                    <div class="center-label">Kelulusan</div>
                </div>
            </div>
            <div class="chart-legend" id="passFailLegend"></div>
        </div>
    </article>

    <article class="panel">
        <header>
            <h2>Distribusi Nilai</h2>
            <p class="muted">Sebaran skor peserta per rentang nilai</p>
        </header>
        <div class="panel-body">
            <div class="chart"><canvas id="distChart" aria-label="Diagram batang distribusi nilai"></canvas></div>
            <div class="chart-summary" id="distSummary"></div>
        </div>
    </article>
</section>

<?php if (!empty($cat_labels)): ?>
<section class="panel" aria-labelledby="category-heading">
    <header>
        <h2 id="category-heading">Analisis per Kategori Materi</h2>
        <p>Persentase pemahaman peserta untuk tiap kategori soal</p>
    </header>
    <div class="panel-body">
        <div class="cols-2" style="align-items: stretch;">
            <div>
                <div class="chart chart-tall"><canvas id="categoryChart" aria-label="Diagram radar pemahaman per kategori"></canvas></div>
            </div>
            <div>
                <h3 style="margin-bottom: var(--s3); font-size: var(--t-md);">Ringkasan Kategori</h3>
                <div class="table-scroll" style="max-height: 380px;">
                    <table class="data" id="categoryTable">
                        <thead>
                            <tr><th>Kategori</th><th class="num">Total Soal</th><th class="num">Benar</th><th class="num">Persentase</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cat_table_rows as $row): ?>
                            <tr>
                                <td><strong><?= e($row['name']) ?></strong></td>
                                <td class="num"><?= $row['total'] ?></td>
                                <td class="num"><?= $row['correct'] ?></td>
                                <td class="num"><strong><?= $row['pct'] ?>%</strong></td>
                                <td>
                                    <span class="tag tag-<?= $row['status'] === 'good' ? 'ok' : ($row['status'] === 'warn' ? 'warn' : 'error') ?>">
                                        <?= $row['status'] === 'good' ? 'Baik' : ($row['status'] === 'warn' ? 'Perlu Perhatian' : 'Kurang') ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="panel" aria-labelledby="leaderboard-heading">
    <header>
        <h2 id="leaderboard-heading">Papan Peringkat (10 Teratas)</h2>
    </header>
    <div class="panel-body panel-flush">
        <div class="table-scroll">
            <table class="data">
                <thead><tr><th class="num">Peringkat</th><th>Peserta</th><th>NIM / NIP</th><th class="num">Skor</th><th class="num">Persentase</th></tr></thead>
                <tbody>
                <?php if (empty($leaderboard)): ?>
                    <tr><td colspan="5" class="empty">Belum ada peserta yang mengerjakan.</td></tr>
                <?php else: $rank = 1; foreach ($leaderboard as $lb): ?>
                    <tr>
                        <td class="num rank"><?= $rank++ ?></td>
                        <td><strong><?= e($lb['nama']) ?></strong></td>
                        <td><?= e($lb['nim']) ?></td>
                        <td class="num"><?= $lb['best_score'] ?></td>
                        <td class="num"><span class="tag <?= $lb['best_pct'] >= 70 ? 'tag-ok' : 'tag-error' ?>"><?= $lb['best_pct'] ?>%</span></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="panel" aria-labelledby="recap-heading">
    <header>
        <h2 id="recap-heading">Rekap Hasil Kuesioner</h2>
        <p class="muted"><span data-search-count="recapTable">—</span> &middot; tekan <kbd>/</kbd> untuk mencari</p>
    </header>
    <div class="panel-body">
        <?php if (isset($_GET['status']) && $_GET['status'] == 'reset_success'): ?>
        <p class="notice notice-ok" role="status">Skor peserta berhasil direset.</p>
        <?php endif; ?>

        <form method="get" class="filterbar">
            <div class="field">
                <label class="field-label" for="room_id">Kegiatan</label>
                <select id="room_id" name="room_id" onchange="this.form.submit()">
                    <option value="all">Semua Kegiatan</option>
                    <option value="individual" <?= $filter_room == 'individual' ? 'selected' : '' ?>>Individu (Kuesioner)</option>
                    <?php if (!empty($rooms_list)): ?>
                        <optgroup label="Ruangan">
                        <?php foreach ($rooms_list as $rl): ?>
                            <option value="<?= $rl['id'] ?>" <?= $filter_room == $rl['id'] ? 'selected' : '' ?>><?= e($rl['room_name']) ?> (<?= e($rl['room_code']) ?>)</option>
                        <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                </select>
            </div>
            <div class="field">
                <label class="field-label" for="recapSearch">Cari peserta</label>
                <input id="recapSearch" type="search" data-table-search="recapTable" placeholder="Nama atau NIM..." autocomplete="off">
            </div>
        </form>
    </div>
    <div class="panel-flush">
        <div class="table-scroll">
            <table class="data" id="recapTable">
                <thead>
                    <tr><th class="num">#</th><th>Peserta</th><th>NIM / NIP</th><th>Tipe</th><th class="num">Skor</th><th class="num">%</th><th>Waktu</th><th></th><th></th></tr>
                </thead>
                <tbody>
                <?php if (empty($results)): ?>
                    <tr><td colspan="9" class="empty">Belum ada data untuk filter ini.</td></tr>
                <?php else: $no = 1; foreach ($results as $r): ?>
                    <tr>
                        <td class="num"><?= $no++ ?></td>
                        <td><?= e($r['nama']) ?></td>
                        <td><?= e($r['nim']) ?></td>
                        <td><?= $r['room_id'] === null ? '<span class="tag tag-info">Individu</span>' : '<span class="tag tag-ok">Ruangan</span>' ?></td>
                        <td class="num"><?= $r['score'] !== null ? $r['score'] . ' / ' . $r['total_questions'] : '<span class="muted">Belum</span>' ?></td>
                        <td class="num"><?php if ($r['score'] !== null): $pct = round(($r['score'] / $r['total_questions']) * 100); ?><span class="tag <?= $pct >= 70 ? 'tag-ok' : 'tag-error' ?>"><?= $pct ?>%</span><?php else: ?>-<?php endif; ?></td>
                        <td><?= $r['created_at'] ? date('d M Y, H:i', strtotime($r['created_at'])) : '-' ?></td>
                        <td class="row-actions">
                            <?php if ($r['score'] !== null): ?>
                                <form method="post" action="reset_result.php" class="form-inline" onsubmit="return confirm('Reset skor peserta ini?')">
                                    <?= csrf_field() ?><input type="hidden" name="reset_user_id" value="<?= $r['id'] ?>">
                                    <button type="submit" class="btn-sm btn-quiet">Reset</button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td class="row-actions">
                            <?php if ($r['result_id'] > 0): ?>
                                <a class="btn-sm btn-quiet" href="result_detail.php?result_id=<?= $r['result_id'] ?>">Detail</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>


<?php layout_footer(['base' => '..', 'scripts' => '
<script>
(function () {
    "use strict";

    var chartData = ' . $chart_data . ';

    function waitForChartJS() {
        return new Promise(function (resolve) {
            if (typeof Chart !== "undefined") { resolve(); }
            else {
                var check = setInterval(function () {
                    if (typeof Chart !== "undefined") { clearInterval(check); resolve(); }
                }, 100);
                setTimeout(function () { clearInterval(check); resolve(); }, 10000);
            }
        });
    }

    waitForChartJS().then(function () {
        if (typeof Chart === "undefined") return;

        var rootStyles = getComputedStyle(document.documentElement);
        var colors = {
            primary: rootStyles.getPropertyValue("--green-600").trim(),
            secondary: rootStyles.getPropertyValue("--gold-500").trim(),
            success: rootStyles.getPropertyValue("--ok-fg").trim(),
            danger: rootStyles.getPropertyValue("--err-fg").trim(),
            background: rootStyles.getPropertyValue("--canvas-2").trim(),
            grid: rootStyles.getPropertyValue("--line").trim(),
            text: rootStyles.getPropertyValue("--ink-2").trim(),
            paper: rootStyles.getPropertyValue("--paper").trim(),
            green100: rootStyles.getPropertyValue("--green-100").trim(),
            green050: rootStyles.getPropertyValue("--green-050").trim(),
            gold100: rootStyles.getPropertyValue("--gold-100").trim()
        };

        var defaults = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    padding: 12,
                    titleFont: { size: 13, weight: "600" },
                    bodyFont: { size: 12 },
                    backgroundColor: colors.background,
                    titleColor: colors.text,
                    bodyColor: colors.text,
                    borderColor: colors.grid,
                    borderWidth: 1,
                    cornerRadius: 8,
                    displayColors: true,
                    usePointStyle: true
                }
            },
            animation: { duration: 1000, easing: "easeOutQuart" },
            interaction: { intersect: false, mode: "index" }
        };

        function observeTheme() {
            var observer = new MutationObserver(function () {
                var newColors = {
                    background: getComputedStyle(document.documentElement).getPropertyValue("--canvas-2").trim(),
                    grid: getComputedStyle(document.documentElement).getPropertyValue("--line").trim(),
                    text: getComputedStyle(document.documentElement).getPropertyValue("--ink-2").trim(),
                    paper: getComputedStyle(document.documentElement).getPropertyValue("--paper").trim(),
                    green100: getComputedStyle(document.documentElement).getPropertyValue("--green-100").trim(),
                    green050: getComputedStyle(document.documentElement).getPropertyValue("--green-050").trim(),
                    gold100: getComputedStyle(document.documentElement).getPropertyValue("--gold-100").trim()
                };
                Object.values(Chart.instances).forEach(function (chart) {
                    chart.options.scales = chart.options.scales || {};
                    if (chart.options.scales.y) chart.options.scales.y.grid.color = newColors.grid;
                    if (chart.options.scales.x) chart.options.scales.x.grid.color = newColors.grid;
                    if (chart.options.scales.r) {
                        chart.options.scales.r.grid.color = newColors.grid;
                        chart.options.scales.r.angleLines.color = newColors.grid;
                        chart.options.scales.r.pointLabels.color = newColors.text;
                        chart.options.scales.r.ticks.color = newColors.text;
                    }
                    if (chart.options.plugins && chart.options.plugins.tooltip) {
                        chart.options.plugins.tooltip.backgroundColor = newColors.background;
                        chart.options.plugins.tooltip.titleColor = newColors.text;
                        chart.options.plugins.tooltip.bodyColor = newColors.text;
                        chart.options.plugins.tooltip.borderColor = newColors.grid;
                    }
                    chart.update("none");
                });
            });
            observer.observe(document.documentElement, { attributes: true, attributeFilter: ["data-theme"] });
        }
        observeTheme();

        var passFailEl = document.getElementById("passFailChart");
        if (passFailEl) {
            var ctx = passFailEl.getContext("2d");
            var gradientPass = ctx.createRadialGradient(0, 0, 0, 0, 0, 120);
            gradientPass.addColorStop(0, colors.success);
            gradientPass.addColorStop(1, colors.primary);
            var gradientFail = ctx.createRadialGradient(0, 0, 0, 0, 0, 120);
            gradientFail.addColorStop(0, colors.danger);
            gradientFail.addColorStop(1, "#8b2a20");

            var passFailChart = new Chart(passFailEl, {
                type: "doughnut",
                data: {
                    labels: ["Lulus (>=70%)", "Belum Lulus (<70%)"],
                    datasets: [{
                        data: chartData.passFail,
                        backgroundColor: [gradientPass, gradientFail],
                        borderColor: colors.paper,
                        borderWidth: 4,
                        hoverOffset: 12,
                        borderAlign: "inner"
                    }]
                },
                options: Object.assign({}, defaults, {
                    cutout: "70%",
                    plugins: Object.assign({}, defaults.plugins, {
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    var total = ctx.chart.data.datasets[0].data.reduce(function(a,b){return a+b;},0);
                                    var pct = total ? Math.round((ctx.raw/total)*100) : 0;
                                    return ctx.label + ": " + ctx.raw + " peserta (" + pct + "%)";
                                }
                            }
                        }
                    }),
                    layout: { padding: 20 }
                }),
                plugins: [{
                    id: "centerText",
                    beforeDraw: function(chart) {
                        var width = chart.width, height = chart.height, ctx = chart.ctx;
                        ctx.restore();
                        var fontSize = Math.min(width, height) / 6;
                        ctx.font = "bold " + fontSize + "px Inter, sans-serif";
                        ctx.fillStyle = colors.success;
                        ctx.textAlign = "center";
                        ctx.textBaseline = "middle";
                        var total = chart.data.datasets[0].data.reduce(function(a,b){return a+b;},0);
                        var pct = total ? Math.round((chart.data.datasets[0].data[0]/total)*100) : 0;
                        ctx.fillText(pct + "%", width/2, height/2 - fontSize/4);
                        ctx.font = "500 " + (fontSize/2.2) + "px Inter, sans-serif";
                        ctx.fillStyle = colors.text;
                        ctx.fillText("Kelulusan", width/2, height/2 + fontSize/1.8);
                        ctx.save();
                    }
                }]
            });

            var legendContainer = document.getElementById("passFailLegend");
            if (legendContainer) {
                var data = passFailChart.data;
                var total = data.datasets[0].data.reduce(function(a,b){return a+b;},0);
                legendContainer.innerHTML = data.labels.map(function(label, i) {
                    var val = data.datasets[0].data[i];
                    var pct = total ? Math.round((val/total)*100) : 0;
                    var color = data.datasets[0].backgroundColor[i];
                    return "<div class=\"legend-item\"><span class=\"legend-color\" style=\"background:" + color + "\"></span><span class=\"legend-label\">" + label + "</span><span class=\"legend-value\">" + val + " (" + pct + "%)" + "</span></div>";
                }).join("");
            }
        }

        var distEl = document.getElementById("distChart");
        if (distEl) {
            var ctx2 = distEl.getContext("2d");
            var gradientBar = ctx2.createLinearGradient(0, 0, 0, 280);
            gradientBar.addColorStop(0, colors.primary);
            gradientBar.addColorStop(0.5, colors.secondary);
            gradientBar.addColorStop(1, colors.gold100);

            var distChart = new Chart(distEl, {
                type: "bar",
                data: {
                    labels: ["0-20", "21-40", "41-60", "61-80", "81-100"],
                    datasets: [{
                        label: "Jumlah Peserta",
                        data: chartData.distribution,
                        backgroundColor: gradientBar,
                        borderColor: colors.secondary,
                        borderWidth: 2,
                        borderRadius: 6,
                        borderSkipped: false,
                        maxBarThickness: 48
                    }]
                },
                options: Object.assign({}, defaults, {
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1, color: colors.text, font: { size: 11 } },
                            grid: { color: colors.grid, drawBorder: false }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: colors.text, font: { size: 11 } }
                        }
                    },
                    plugins: Object.assign({}, defaults.plugins, {
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    var total = ctx.chart.data.datasets[0].data.reduce(function(a,b){return a+b;},0);
                                    var pct = total ? Math.round((ctx.raw/total)*100) : 0;
                                    return "Peserta: " + ctx.raw + " (" + pct + "%)";
                                }
                            }
                        }
                    })
                })
            });

            var summaryEl = document.getElementById("distSummary");
            if (summaryEl) {
                var totalDist = chartData.distribution.reduce(function(a,b){return a+b;},0);
                var avgRange = 0;
                if (totalDist > 0) {
                    var midpoints = [10, 30, 50, 70, 90];
                    var weighted = 0;
                    chartData.distribution.forEach(function(v, i) { weighted += v * midpoints[i]; });
                    avgRange = Math.round(weighted / totalDist);
                }
                summaryEl.innerHTML = "<div class=\"summary-row\"><span>Total Peserta:</span><strong>" + totalDist + "</strong></div>" +
                    "<div class=\"summary-row\"><span>Rata-rata Rentang:</span><strong>" + avgRange + "%</strong></div>" +
                    "<div class=\"summary-row\"><span>Tertinggi:</span><strong>" + (chartData.distribution[4] || 0) + " peserta (81-100)</strong></div>" +
                    "<div class=\"summary-row\"><span>Terendah:</span><strong>" + (chartData.distribution[0] || 0) + " peserta (0-20)</strong></div>";
            }
        }

        var catEl = document.getElementById("categoryChart");
        if (catEl && chartData.categories.labels.length > 0) {
            var ctx3 = catEl.getContext("2d");
            var gradientRadar = ctx3.createRadialGradient(0, 0, 0, 0, 0, 200);
            gradientRadar.addColorStop(0, "rgba(22,105,74,0.25)");
            gradientRadar.addColorStop(1, "rgba(22,105,74,0.05)");

            new Chart(catEl, {
                type: "radar",
                data: {
                    labels: chartData.categories.labels,
                    datasets: [{
                        label: "Pemahaman (%)",
                        data: chartData.categories.values,
                        backgroundColor: gradientRadar,
                        borderColor: colors.primary,
                        pointBackgroundColor: colors.secondary,
                        pointBorderColor: colors.paper,
                        pointBorderWidth: 3,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        borderWidth: 3
                    }]
                },
                options: Object.assign({}, defaults, {
                    scales: {
                        r: {
                            beginAtZero: true,
                            max: 100,
                            min: 0,
                            ticks: { stepSize: 20, color: colors.text, backdropColor: "transparent", font: { size: 10 } },
                            grid: { color: colors.grid },
                            angleLines: { color: colors.grid },
                            pointLabels: { font: { size: 12, weight: "500" }, color: colors.text, padding: 12 }
                        }
                    },
                    plugins: Object.assign({}, defaults.plugins, {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    return ctx.label + ": " + ctx.raw + "%";
                                }
                            }
                        }
                    })
                })
            });
        }
    });
})();
</script>
']); ?>