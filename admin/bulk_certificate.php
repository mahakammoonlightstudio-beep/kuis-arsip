<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layout.php';
require_login();

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$survey_id = (int)($_GET['survey_id'] ?? $_POST['survey_id'] ?? 0);
$room_id   = (int)($_GET['room_id'] ?? $_POST['room_id'] ?? 0);

$surveys = $pdo->query("SELECT id, title FROM surveys ORDER BY created_at DESC")->fetchAll();
$rooms   = $pdo->query("SELECT id, room_name, room_code FROM rooms WHERE is_active = 1 ORDER BY created_at DESC")->fetchAll();

$sql = "
    SELECT r.id AS result_id, r.survey_id, r.room_id, r.user_id, u.nama, u.nim, s.title AS survey_title,
           ro.room_name, r.score, r.total_questions, r.created_at
    FROM results r
    JOIN users u ON r.user_id = u.id
    LEFT JOIN surveys s ON r.survey_id = s.id
    LEFT JOIN rooms ro ON r.room_id = ro.id
    WHERE r.total_questions > 0 AND (r.score / r.total_questions) * 100 >= 70
";
$params = [];

if ($survey_id > 0) {
    $sql .= " AND r.survey_id = ?";
    $params[] = $survey_id;
}
if ($room_id > 0) {
    $sql .= " AND r.room_id = ?";
    $params[] = $room_id;
}
$sql .= " ORDER BY r.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$passing_results = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['download_zip'])) {
    csrf_verify();

    if (empty($passing_results)) {
        die("Tidak ada sertifikat yang bisa diunduh.");
    }

    if (!class_exists('ZipArchive')) {
        die("Fitur unduh ZIP tidak tersedia di server ini.");
    }

    $zip_name = 'sertifikat_' . date('Ymd_His') . '.zip';
    $zip_path = sys_get_temp_dir() . '/' . $zip_name;
    $zip = new ZipArchive;

    if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        die("Gagal membuat berkas ZIP.");
    }

    // Root situs (bukan /admin): dipakai untuk URL verifikasi QR & aset logo
    $base_url = "https://" . $_SERVER['HTTP_HOST'];

    foreach ($passing_results as $res) {
        $sid = $res['survey_id'];
        $rid = $res['room_id'] ?? null;

        $score = $res['score'];
        $total = $res['total_questions'];
        $pct = $total > 0 ? round(($score / $total) * 100) : 0;
        if ($pct < 70) continue;

        $stmt_s = $pdo->prepare("SELECT title FROM surveys WHERE id = ?");
        $stmt_s->execute([$sid]);
        $survey_title = $stmt_s->fetchColumn() ?: 'Kuesioner';

        $year = date('Y', strtotime($res['created_at']));
        $bulan_indo = [
            'January'=>'Januari','February'=>'Februari','March'=>'Maret','April'=>'April',
            'May'=>'Mei','June'=>'Juni','July'=>'Juli','August'=>'Agustus',
            'September'=>'September','October'=>'Oktober','November'=>'November','December'=>'Desember'
        ];
        $tanggal_raw = date('d F Y', strtotime($res['created_at']));
        $tanggal = str_replace(array_keys($bulan_indo), array_values($bulan_indo), $tanggal_raw);

        $verify_url = $base_url . "/quiz/verify.php?uid=" . $res['user_id']
            . "&sid=" . $sid . "&rid=" . ($rid ? $rid : '0');
        $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($verify_url);
        $cert_number = sprintf("%03d", $res['user_id']) . "/HRK-KUKAR/" . $year;

        $filename = 'sertifikat_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $res['nama']) . '_' . $res['result_id'] . '.html';

        $html = '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Sertifikat - ' . htmlspecialchars($res['nama']) . '</title>';
        $html .= '<style>body{background:#e0e0e0;padding:20px;margin:0;font-family:Georgia,serif;}';
        $html .= '.certificate-wrapper{width:297mm;min-height:190mm;background:#fff;margin:0 auto;padding:50px 60px;box-shadow:0 0 30px rgba(0,0,0,0.2);position:relative;border:12px solid #0f4d33;outline:3px solid #c39b2c;outline-offset:-20px;display:flex;flex-direction:column;}';
        $html .= '.watermark{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:400px;height:400px;background-image:url(\'' . $base_url . '/assets/img/logo-kukar.png\');background-size:contain;background-repeat:no-repeat;background-position:center;opacity:0.06;z-index:0;pointer-events:none;}';
        $html .= '.cert-content{position:relative;z-index:1;text-align:center;flex:1;display:flex;flex-direction:column;justify-content:space-between;}';
        $html .= '.cert-header{display:flex;align-items:center;justify-content:center;gap:20px;margin-bottom:20px;}';
        $html .= '.cert-logo{width:70px;height:70px;background-image:url(\'' . $base_url . '/assets/img/logo-kukar.png\');background-size:contain;background-repeat:no-repeat;background-position:center;}';
        $html .= '.cert-header-text h2{color:#0f4d3b;font-size:22px;margin:0;text-transform:uppercase;letter-spacing:1px;}';
        $html .= '.cert-header-text p{font-size:14px;color:#555;margin:0;font-family:Arial,sans-serif;}';
        $html .= '.cert-title{font-size:40px;font-weight:bold;color:#0f4d3b;letter-spacing:8px;margin:10px 0 5px;text-transform:uppercase;}';
        $html .= '.cert-subtitle{font-size:14px;color:#555;margin-bottom:30px;font-style:italic;}';
        $html .= '.cert-name{font-size:36px;font-weight:bold;color:#000;margin:10px 0 5px;font-family:"Brush Script MT",cursive;}';
        $html .= '.cert-line{width:400px;height:2px;background:#c39b2c;margin:0 auto 20px;}';
        $html .= '.cert-desc{font-size:16px;color:#333;line-height:1.8;margin-bottom:10px;}';
        $html .= '.cert-score{display:inline-block;margin-top:15px;padding:8px 25px;background:#0f4d3b;color:#c39b2c;font-weight:bold;font-size:18px;border-radius:4px;}';
        $html .= '.cert-footer{display:flex;justify-content:space-between;align-items:flex-end;margin-top:40px;text-align:center;}';
        $html .= '.cert-footer-block{width:200px;}';
        $html .= '.cert-footer-block p{margin:0;font-size:13px;color:#333;}';
        $html .= '.cert-sign-name{font-weight:bold;text-decoration:underline;margin-bottom:2px!important;font-size:15px!important;}';
        $html .= '.cert-qr{width:90px;height:90px;margin:0 auto 5px;display:block;}';
        $html .= '.cert-qr-desc{font-size:10px;color:#777;font-family:Arial,sans-serif;}';
        $html .= '@media print{body{background:#fff;padding:0;margin:0;}.certificate-wrapper{box-shadow:none;width:100%;height:100vh;border:12px solid #0f4d33;outline:3px solid #c39b2c;outline-offset:-20px;}@page{size:A4 landscape;margin:0;}}';
        $html .= '</style></head><body><div class="certificate-wrapper"><div class="watermark"></div><div class="cert-content">';
        $html .= '<div><div class="cert-header"><div class="cert-logo"></div><div class="cert-header-text"><h2>Pemerintah Kabupaten Kutai Kartanegara</h2><p>DINAS KEARSIPAN DAN PERPUSTAKAAN<br>Jl. Panji No.47, Tenggarong, Kalimantan Timur</p></div></div>';
        $html .= '<div class="cert-title">Sertifikat</div><div class="cert-subtitle">Nomor: ' . $cert_number . '</div>';
        $html .= '<p class="cert-desc">Diberikan kepada:</p><div class="cert-name">' . htmlspecialchars($res['nama']) . '</div><div class="cert-line"></div>';
        $html .= '<p class="cert-desc">Atas partisipasi dan keberhasilan dalam menyelesaikan:<br><strong>' . htmlspecialchars($survey_title) . '</strong></p>';
        $html .= '<div class="cert-score">Skor: ' . $score . ' / ' . $total . ' (' . $pct . '%)</div></div>';
        $html .= '<div class="cert-footer"><div class="cert-footer-block"><p>Tenggarong, ' . $tanggal . '</p></div>';
        $html .= '<div class="cert-footer-block"><img src="' . $qr_url . '" alt="QR" class="cert-qr"><p class="cert-qr-desc">Pindai untuk verifikasi keaslian</p></div>';
        $html .= '<div class="cert-footer-block"><p class="cert-sign-name">H. ......., M.Ap</p><p>Kepala Dinas Kearsipan<br>dan Perpustakaan</p></div></div>';
        $html .= '</div></div></body></html>';

        $zip->addFromString($filename, $html);
    }

    $zip->close();

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zip_name . '"');
    header('Content-Length: ' . filesize($zip_path));
    readfile($zip_path);
    unlink($zip_path);
    exit;
}

$open_tickets = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'open'")->fetchColumn();

layout_header([
    'title'  => 'Unduh Sertifikat',
    'active' => 'dashboard',
    'role'   => 'admin',
    'tickets' => $open_tickets,
]);
?>


<section class="panel">
    <header>
        <h2>Unduh Massal Sertifikat</h2>
        <p>Peserta yang lulus (≥70%) dapat diunduh dalam berkas ZIP.</p>
    </header>
    <div class="panel-body">
        <form method="get" class="filterbar">
            <div class="field">
                <label class="field-label">Kuesioner</label>
                <select name="survey_id">
                    <option value="0" <?= $survey_id === 0 ? 'selected' : '' ?>>Semua</option>
                    <?php foreach ($surveys as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $survey_id == $s['id'] ? 'selected' : '' ?>><?= e($s['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field-label">Ruangan</label>
                <select name="room_id">
                    <option value="0" <?= $room_id === 0 ? 'selected' : '' ?>>Semua</option>
                    <?php foreach ($rooms as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= $room_id == $r['id'] ? 'selected' : '' ?>><?= e($r['room_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-sm">Terapkan</button>
            <a class="btn-sm btn-quiet" href="bulk_certificate.php">Atur ulang</a>
        </form>
    </div>
</section>

<section class="panel">
    <header>
        <h2>Daftar Peserta Lulus</h2>
    </header>
    <div class="panel-body">
        <?php if (!empty($passing_results)): ?>
            <form method="post" class="btn-row mb-16" onsubmit="return confirm('Unduh <?= count($passing_results) ?> sertifikat?')">
                <?= csrf_field() ?>
                <input type="hidden" name="download_zip" value="1">
                <input type="hidden" name="survey_id" value="<?= (int)$survey_id ?>">
                <input type="hidden" name="room_id" value="<?= (int)$room_id ?>">
                <button type="submit" class="btn">Unduh ZIP (<?= count($passing_results) ?> berkas)</button>
            </form>
        <?php endif; ?>

        <div class="table-scroll">
            <table class="data">
                <thead><tr><th class="num">#</th><th>Nama</th><th>NIM</th><th>Kuesioner</th><th>Ruangan</th><th class="num">Skor</th><th>Tanggal</th></tr></thead>
                <tbody>
                <?php if (empty($passing_results)): ?>
                    <tr><td colspan="7" class="empty">Tidak ada peserta yang lulus untuk filter ini.</td></tr>
                <?php else: $no = 1; foreach ($passing_results as $p): ?>
                    <tr>
                        <td class="num"><?= $no++ ?></td>
                        <td><strong><?= e($p['nama']) ?></strong></td>
                        <td><?= e($p['nim']) ?></td>
                        <td><?= e($p['survey_title']) ?></td>
                        <td><?= e($p['room_name'] ?: '-') ?></td>
                        <td class="num"><span class="tag tag-ok"><?= $p['score'] ?> / <?= $p['total_questions'] ?></span></td>
                        <td><?= date('d M Y, H:i', strtotime($p['created_at'])) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>


<?php layout_footer(['base' => '..']); ?>
