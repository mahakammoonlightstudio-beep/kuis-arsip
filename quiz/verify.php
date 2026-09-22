<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layout.php';

$user_id = (int)($_GET['uid'] ?? 0);
$survey_id = (int)($_GET['sid'] ?? 0);
$room_id = $_GET['rid'] ?? null;
if ($room_id === '0') $room_id = null;

$valid = false;
$data = null;

if ($user_id > 0 && $survey_id > 0) {
    $sql = "SELECT u.nama, u.nim, r.score, r.total_questions, r.created_at, s.title
            FROM results r
            JOIN users u ON r.user_id = u.id
            JOIN surveys s ON r.survey_id = s.id
            WHERE r.user_id = ? AND r.survey_id = ?";
    $params = [$user_id, $survey_id];

    if ($room_id) {
        $sql .= " AND r.room_id = ?";
        $params[] = $room_id;
    } else {
        $sql .= " AND r.room_id IS NULL";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetch();

    if ($data) {
        $valid = true;
    }
}

layout_header([
    'title' => 'Verifikasi Sertifikat',
    'active' => 'home',
    'role' => 'guest',
    'head' => '<meta name="robots" content="noindex, nofollow">',
    'main_narrow' => true,
]);
?>




<section class="panel">
    <header>
        <h2>Verifikasi Sertifikat</h2>
        <p>Pastikan keaslian sertifikat dengan memeriksa nomor peserta dan kegiatan.</p>
    </header>
    <div class="panel-body">
    <?php if ($valid && $data):
        $pct = $data['total_questions'] > 0 ? round(($data['score'] / $data['total_questions']) * 100) : 0;
    ?>
        <p class="notice notice-ok"><strong>Sertifikat terverifikasi.</strong> Dokumen ini tercatat dalam database resmi Diarpus Kukar.</p>
        <dl class="facts">
            <dt>Nama Peserta</dt><dd><?= e($data['nama']) ?></dd>
            <dt>NIM / NIP</dt><dd><?= e($data['nim']) ?></dd>
            <dt>Kegiatan</dt><dd><?= e($data['title']) ?></dd>
            <dt>Nilai</dt><dd><?= $data['score'] ?> / <?= $data['total_questions'] ?> (<?= $pct ?>%)</dd>
            <dt>Tanggal Selesai</dt><dd><?= date('d F Y', strtotime($data['created_at'])) ?></dd>
        </dl>
    <?php else: ?>
        <p class="notice notice-error"><strong>Sertifikat tidak valid.</strong> Data tidak ditemukan dalam database kami. Dokumen ini mungkin palsu atau hasil suntingan.</p>
    <?php endif; ?>
        <div class="btn-row">
            <a class="btn-sm btn-quiet" href="../quiz/index.php">Kembali ke beranda</a>
        </div>
    </div>
</section>


<?php layout_footer(['base' => '..']); ?>
