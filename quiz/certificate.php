<?php
require_once __DIR__ . '/../config/database.php';
require_login();

date_default_timezone_set('Asia/Makassar');

$survey_id = $_GET['survey_id'] ?? null;
$room_id = $_GET['room_id'] ?? null;
if ($room_id === '0') $room_id = null;

if (!$survey_id) { die("Parameter survey_id tidak ditemukan."); }

$sql = "SELECT score, total_questions, created_at FROM results WHERE user_id = ?";
$params = [$_SESSION['user_id']];

if ($survey_id) { $sql .= " AND survey_id = ?"; $params[] = $survey_id; }
if ($room_id) { $sql .= " AND room_id = ?"; $params[] = $room_id; } else { $sql .= " AND room_id IS NULL"; }

$sql .= " ORDER BY created_at DESC LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$res = $stmt->fetch();

if (!$res) { die("Data hasil tidak ditemukan."); }

$score = $res['score'];
$total = $res['total_questions'];
$pct = $total > 0 ? round(($score / $total) * 100) : 0;

if ($pct < 70) { die("Maaf, Anda belum lulus sehingga tidak mendapatkan sertifikat."); }

$stmt_s = $pdo->prepare("SELECT title FROM surveys WHERE id = ?");
$stmt_s->execute([$survey_id]);
$survey_title = $stmt_s->fetchColumn() ?: 'Kuesioner Kearsipan';

$nama = $_SESSION['nama'];
$user_id = $_SESSION['user_id'];
$stmt_photo = $pdo->prepare("SELECT photo_path FROM users WHERE id = ?");
$stmt_photo->execute([$user_id]);
$photo_path = $stmt_photo->fetchColumn();
$year = date('Y', strtotime($res['created_at']));
$month_num = (int)date('m', strtotime($res['created_at']));
$day_num = (int)date('d', strtotime($res['created_at']));

$nama_bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$tanggal = $day_num . ' ' . $nama_bulan[$month_num] . ' ' . $year;

$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/');
$verify_url = $base_url . "/verify.php?uid=" . $user_id . "&sid=" . $survey_id . "&rid=" . ($room_id ? $room_id : '0');
$qr_code_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($verify_url);
$cert_number = sprintf("%03d", $user_id) . "/HRK-KUKAR/" . $year;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sertifikat - <?= e($nama) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Serif+4:opsz,wght@8..60,400;8..60,600&display=swap">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #d7dad3; padding: 30px; margin: 0; font-family: 'Source Serif 4', Georgia, serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .certificate-wrapper { width: 297mm; min-height: 210mm; background: #fffef8; margin: 0 auto; padding: 0; box-shadow: 0 8px 40px rgba(0,0,0,0.25); position: relative; overflow: hidden; }
        .cert-border-outer { position: absolute; inset: 6mm; border: 4px solid #0f4d33; pointer-events: none; z-index: 2; }
        .cert-border-middle { position: absolute; inset: 9mm; border: 2px solid #8b6914; pointer-events: none; z-index: 2; }
        .cert-border-inner { position: absolute; inset: 11mm; border: 1px solid #c39b2c; pointer-events: none; z-index: 2; }
        .corner { position: absolute; width: 60px; height: 60px; z-index: 3; pointer-events: none; }
        .corner::before, .corner::after { content: ''; position: absolute; background: #0f4d33; }
        .corner-tl { top: 12mm; left: 12mm; }
        .corner-tl::before { width: 40px; height: 3px; top: 0; left: 0; }
        .corner-tl::after { width: 3px; height: 40px; top: 0; left: 0; }
        .corner-tr { top: 12mm; right: 12mm; }
        .corner-tr::before { width: 40px; height: 3px; top: 0; right: 0; }
        .corner-tr::after { width: 3px; height: 40px; top: 0; right: 0; }
        .corner-bl { bottom: 12mm; left: 12mm; }
        .corner-bl::before { width: 40px; height: 3px; bottom: 0; left: 0; }
        .corner-bl::after { width: 3px; height: 40px; bottom: 0; left: 0; }
        .corner-br { bottom: 12mm; right: 12mm; }
        .corner-br::before { width: 40px; height: 3px; bottom: 0; right: 0; }
        .corner-br::after { width: 3px; height: 40px; bottom: 0; right: 0; }
        .watermark { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 380px; height: 380px; background-image: url('../assets/img/logo-kukar.png'); background-size: contain; background-repeat: no-repeat; background-position: center; opacity: 0.055; z-index: 0; pointer-events: none; }
        .cert-content { position: relative; z-index: 1; text-align: center; height: 100%; display: flex; flex-direction: column; justify-content: space-between; padding: 18mm 22mm 14mm; }
        .cert-header { display: flex; align-items: center; justify-content: center; gap: 24px; margin-bottom: 6mm; }
        .cert-logo { width: 65px; height: 65px; flex-shrink: 0; background-image: url('../assets/img/logo-kukar.png'); background-size: contain; background-repeat: no-repeat; background-position: center; }
        .cert-header-text { text-align: center; }
        .cert-header-text h2 { color: #0f4d3b; font-size: 16px; margin: 0; text-transform: uppercase; letter-spacing: 2px; font-weight: bold; border: none; padding: 0; line-height: 1.4; }
        .cert-header-text p { font-size: 10px; color: #444; margin: 3px 0 0 0; letter-spacing: 0.5px; }
        .cert-title { font-size: 42px; font-weight: bold; color: #0f4d3b; letter-spacing: 10px; margin: 4px 0 2px 0; text-transform: uppercase; }
        .cert-title-sub { font-size: 11px; color: #888; letter-spacing: 3px; text-transform: uppercase; margin-bottom: 6mm; }
        .cert-number-bar { display: inline-block; background: #0f4d33; color: #c39b2c; padding: 3px 20px; font-size: 10px; letter-spacing: 1px; border-radius: 2px; margin-bottom: 5mm; }
        .cert-body { flex: 1; display: flex; flex-direction: column; justify-content: center; gap: 3mm; }
        .cert-desc-top { font-size: 13px; color: #333; }
        .cert-name { font-size: 32px; font-weight: bold; color: #1a1a1a; margin: 2px 0; font-family: 'Brush Script MT', 'Great Vibes', cursive; border-bottom: 2px solid #c39b2c; padding-bottom: 2px; display: inline-block; min-width: 300px; }
        .cert-desc-middle { font-size: 13px; color: #444; line-height: 1.7; }
        .cert-desc-middle strong { color: #0f4d3b; font-size: 14px; }
        .cert-score-badge { display: inline-block; margin-top: 4mm; padding: 5px 28px; background: #0f4d33; color: #c39b2c; font-weight: bold; font-size: 16px; border-radius: 4px; letter-spacing: 1px; }
        .cert-footer { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 5mm; padding-top: 3mm; }
        .cert-footer-block { width: 200px; }
        .cert-footer-block p { margin: 0; font-size: 11px; color: #333; line-height: 1.5; }
        .cert-sign-name { font-weight: bold; text-decoration: underline; margin-bottom: 2px !important; font-size: 13px !important; }
        .cert-qr { width: 85px; height: 85px; margin: 0 auto 3px auto; display: block; border: 1px solid #ddd; border-radius: 4px; }
        .cert-qr-desc { font-size: 8px; color: #888; }
        .print-btn { position: fixed; top: 20px; right: 20px; background: #0f4d33; color: #fff; padding: 12px 24px; border-radius: 8px; cursor: pointer; text-decoration: none; z-index: 999; font-weight: bold; border: none; font-family: Inter, sans-serif; }
        .print-btn:hover { background: #16694a; }
        @media print { body { background: #fff; padding: 0; margin: 0; } .print-btn { display: none !important; } .certificate-wrapper { box-shadow: none; width: 100%; height: 100vh; margin: 0; } @page { size: A4 landscape; margin: 0; } }
    </style>
</head>
<body>
    <a href="#" onclick="window.print()" class="print-btn">Cetak / simpan PDF</a>
    <div class="certificate-wrapper">
        <div class="cert-border-outer"></div>
        <div class="cert-border-middle"></div>
        <div class="cert-border-inner"></div>
        <div class="corner corner-tl"></div>
        <div class="corner corner-tr"></div>
        <div class="corner corner-bl"></div>
        <div class="corner corner-br"></div>
        <div class="watermark"></div>
        <div class="cert-content">
            <div>
                <div class="cert-header">
                    <div class="cert-logo"></div>
                    <div class="cert-header-text">
                        <h2>Pemerintah Kabupaten<br>Kutai Kartanegara</h2>
                        <p>DINAS KEARSIPAN DAN PERPUSTAKAAN<br>Jl. Panji No.47, Tenggarong, Kalimantan Timur 75513</p>
                    </div>
                    <div class="cert-logo"></div>
                </div>
                <div class="cert-title">Sertifikat</div>
                <div class="cert-title-sub">Certificate of Achievement</div>
                <div class="cert-number-bar">Nomor: <?= e($cert_number) ?></div>
            </div>
            <div class="cert-body">
                <p class="cert-desc-top">Diberikan kepada / Presented to:</p>
                <div style="display:flex;align-items:center;justify-content:center;gap:15px;margin:4px 0;">
                    <?php if (!empty($photo_path) && file_exists(__DIR__ . '/../assets/' . ltrim($photo_path, '/'))): ?>
                        <img src="../assets/<?= e(ltrim($photo_path, '/')) ?>" style="width:55px;height:55px;border-radius:50%;object-fit:cover;border:3px solid #c39b2c;flex-shrink:0;" alt="foto">
                    <?php endif; ?>
                    <div class="cert-name" style="margin:0;"><?= e($nama) ?></div>
                </div>
                <p class="cert-desc-middle">
                    Atas partisipasi dan keberhasilan dalam menyelesaikan<br>
                    <strong><?= e($survey_title) ?></strong><br>
                    yang diselenggarakan oleh Dinas Kearsipan dan Perpustakaan<br>
                    Kabupaten Kutai Kartanegara
                </p>
                <div class="cert-score-badge">Skor: <?= $score ?> / <?= $total ?>  (<?= $pct ?>%)</div>
            </div>
            <div class="cert-footer">
                <div class="cert-footer-block">
                    <p>Tenggarong, <?= e($tanggal) ?></p>
                    <p>Kepala Dinas Kearsipan</p>
                    <p>dan Perpustakaan</p>
                    <div style="height:60px;"></div>
                    <p class="cert-sign-name">H. ......., M.Ap</p>
                    <p>Pangkat Golongan: IV/e</p>
                </div>
                <div class="cert-footer-block" style="text-align:center;">
                    <img src="<?= $qr_code_url ?>" alt="QR Verifikasi" class="cert-qr">
                    <p class="cert-qr-desc">Pindai untuk verifikasi keaslian</p>
                </div>
                <div class="cert-footer-block" style="text-align:right;">
                    <p style="font-size:9px; color:#888;">
                        Sertifikat ini diterbitkan secara digital<br>
                        dan dapat diverifikasi melalui QR Code di atas.
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
