<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layout.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

date_default_timezone_set('Asia/Makassar');

$stmt = $pdo->query("
    SELECT u.nama, u.nim, r.room_name, s.title as survey_title, res.score, res.total_questions, res.created_at
    FROM results res
    JOIN users u ON res.user_id = u.id
    LEFT JOIN rooms r ON res.room_id = r.id
    LEFT JOIN surveys s ON res.survey_id = s.id
    ORDER BY res.created_at DESC
");
$results_all = $stmt->fetchAll();

$total_peserta = $pdo->query("SELECT COUNT(*) FROM users WHERE role='peserta'")->fetchColumn();
$rooms_list = $pdo->query("SELECT id, room_name, room_code FROM rooms ORDER BY created_at DESC")->fetchAll();
$filter_room = $_GET['room_id'] ?? 'all';

if ($filter_room !== 'all' && $filter_room !== '') {
    $stmt_filt = $pdo->prepare("
        SELECT u.nama, u.nim, r.room_name, s.title as survey_title, res.score, res.total_questions, res.created_at
        FROM results res
        JOIN users u ON res.user_id = u.id
        LEFT JOIN rooms r ON res.room_id = r.id
        LEFT JOIN surveys s ON res.survey_id = s.id
        WHERE res.room_id = ?
        ORDER BY res.created_at DESC
    ");
    $stmt_filt->execute([$filter_room]);
    $results = $stmt_filt->fetchAll();
} else {
    $results = $results_all;
}

$total_selesai = count($results);
$sum_pcts = 0;
$total_lulus = 0;
foreach ($results as $r) {
    if ($r['score'] !== null && $r['total_questions'] > 0) {
        $pct = round(($r['score'] / $r['total_questions']) * 100);
        $sum_pcts += $pct;
        if ($pct >= 70) $total_lulus++;
    }
}
$rata_rata = $total_selesai > 0 ? round($sum_pcts / $total_selesai) : 0;

$bulan_indo = [
    'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret',
    'April' => 'April', 'May' => 'Mei', 'June' => 'Juni',
    'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September',
    'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'
];
$tanggal_raw = date('d F Y');
$tanggal_ini = str_replace(array_keys($bulan_indo), array_values($bulan_indo), $tanggal_raw);

$open_tickets = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'open'")->fetchColumn();

layout_header([
    'title'  => 'Laporan Resmi',
    'active' => 'dashboard',
    'role'   => 'admin',
    'tickets' => $open_tickets,
]);
?>


<section class="panel">
    <header>
        <h2>Laporan Rekapitulasi</h2>
        <p>Cetak laporan resmi seluruh hasil kuesioner.</p>
    </header>
    <div class="panel-body">
        <form method="get" class="filterbar">
            <div class="field">
                <label class="field-label">Ruangan</label>
                <select name="room_id" onchange="this.form.submit()">
                    <option value="all" <?= $filter_room == 'all' ? 'selected' : '' ?>>Semua Kegiatan</option>
                    <option value="" <?= $filter_room == '' ? 'selected' : '' ?>>Individu (Kuesioner)</option>
                    <?php foreach ($rooms_list as $rl): ?>
                        <option value="<?= $rl['id'] ?>" <?= $filter_room == $rl['id'] ? 'selected' : '' ?>><?= e($rl['room_name']) ?> (<?= e($rl['room_code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
        <div class="btn-row mt-16">
            <button data-print class="btn-sm">Cetak / simpan PDF</button>
        </div>
    </div>
</section>

<section class="report-wrapper">
    <div class="report-inner">
        <header class="report-head">
            <img class="report-logo" src="../assets/img/logo-kukar.png" alt="Lambang" width="72" height="72">
            <div class="report-head-text">
                <h2>Pemerintah Kabupaten Kutai Kartanegara</h2>
                <h3>Dinas Kearsipan dan Perpustakaan</h3>
                <p>Jl. Panji No.47, Panji, Kec. Tenggarong, Kalimantan Timur 75513<br>Surel: diarpuskukar@gmail.com</p>
            </div>
        </header>

        <h2 class="report-title">Laporan Rekapitulasi Hasil Kuesioner Kearsipan</h2>
        <p class="report-subtitle">Periode: <?= date('d F Y') ?> &middot; Kabupaten Kutai Kartanegara</p>

        <dl class="facts">
            <dt>Total peserta terdaftar</dt><dd><?= $total_peserta ?> orang</dd>
            <dt>Total peserta menyelesaikan</dt><dd><?= $total_selesai ?> orang</dd>
            <dt>Rata-rata nilai</dt><dd><?= $rata_rata ?>%</dd>
            <dt>Persentase lulus (&ge;70%)</dt><dd><?= $total_selesai > 0 ? round(($total_lulus / $total_selesai) * 100) : 0 ?>% (<?= $total_lulus ?> orang)</dd>
        </dl>

        <table class="report-table">
            <thead>
                <tr><th class="num">No</th><th>Nama Peserta</th><th>NIM / NIP</th><th>Kegiatan</th><th class="num">Skor</th><th class="num">Persentase</th></tr>
            </thead>
            <tbody>
            <?php if (empty($results)): ?>
                <tr><td colspan="6" style="text-align:center;padding:20px;">Belum ada data.</td></tr>
            <?php else: $no = 1; foreach ($results as $r):
                $pct = $r['total_questions'] > 0 ? round(($r['score'] / $r['total_questions']) * 100) : 0;
                $kegiatan = $r['room_name'] ? $r['room_name'] : $r['survey_title'];
            ?>
                <tr>
                    <td class="num"><?= $no++ ?></td>
                    <td><?= htmlspecialchars($r['nama']) ?></td>
                    <td><?= htmlspecialchars($r['nim']) ?></td>
                    <td><?= htmlspecialchars($kegiatan) ?></td>
                    <td class="num"><?= $r['score'] ?>/<?= $r['total_questions'] ?></td>
                    <td class="num"><strong><?= $pct ?>%</strong></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>

        <div class="report-sign">
            <p>Tenggarong, <?= $tanggal_ini ?></p>
            <p>Kepala Dinas Kearsipan dan Perpustakaan</p>
            <div style="height:60px;"></div>
            <p style="text-decoration:underline;font-weight:bold;">H. ......., M.Ap</p>
            <p>Pangkat Golongan: IV/e</p>
            <p>NIP. ............................</p>
        </div>
    </div>
</section>


<style>
.report-wrapper {
    width: 210mm;
    min-height: 297mm;
    background: #fff;
    margin: 0 auto;
    padding: 20mm 22mm 25mm 25mm;
    box-shadow: 0 0 25px rgba(0,0,0,0.15);
    box-sizing: border-box;
    border: 1px solid #ccc;
}
.report-inner {
    border: 2px solid #0f4d33;
    outline: 1px solid #c39b2c;
    outline-offset: -8px;
    padding: 16px;
}
.report-head {
    display: flex;
    align-items: center;
    gap: 20px;
    border-bottom: 3px solid #0f4d33;
    padding-bottom: 12px;
    margin-bottom: 24px;
}
.report-logo { width: 72px; height: 72px; object-fit: contain; flex: 0 0 auto; }
.report-head-text { flex: 1; text-align: center; }
.report-head-text h2 { margin: 0; font-size: 18px; letter-spacing: 1px; }
.report-head-text h3 { margin: 2px 0; font-size: 17px; }
.report-head-text p { margin: 4px 0 0; font-size: 11px; color: #333; }
.report-title { text-align: center; font-size: 16px; text-decoration: underline; margin: 20px 0 4px; }
.report-subtitle { text-align: center; font-size: 12px; color: #555; margin-bottom: 20px; }
.report-table { width: 100%; border-collapse: collapse; font-size: 11px; }
.report-table th, .report-table td { border: 1px solid #000; padding: 5px 7px; text-align: left; }
.report-table th { background: #d5e8d4; text-align: center; font-weight: bold; }
.report-table td.num, .report-table th.num { text-align: center; }
.report-sign { margin-top: 40px; text-align: right; font-size: 12px; }
.report-sign p { margin: 2px 0; }
@media print {
    .masthead, .topbar, .site-footer, .skip-link, .no-print, .btn-sm { display: none !important; }
    .container { max-width: none; padding: 0; }
    .report-wrapper { box-shadow: none; border: none; }
}
</style>

<?php layout_footer(['base' => '..']); ?>
