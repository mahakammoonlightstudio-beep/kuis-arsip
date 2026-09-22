<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layout.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$result_id = (int)($_GET['result_id'] ?? 0);
if ($result_id === 0) {
    header('Location: index.php');
    exit;
}

$sql = "
    SELECT res.id AS result_id, res.user_id, res.room_id, res.survey_id,
           res.score, res.total_questions, res.answers_json, res.proctoring_log, res.created_at,
           u.nama, u.nim,
           r.room_name, s.title AS survey_title
    FROM results res
    JOIN users u ON res.user_id = u.id
    LEFT JOIN rooms r ON res.room_id = r.id
    LEFT JOIN surveys s ON res.survey_id = s.id
    WHERE res.id = ?
    LIMIT 1
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$result_id]);
$result = $stmt->fetch();

if (!$result) {
    header('Location: index.php');
    exit;
}

$detail = json_decode($result['answers_json'], true);
if (!is_array($detail)) $detail = [];

$percentage = $result['total_questions'] > 0 ? round(($result['score'] / $result['total_questions']) * 100) : 0;

$proctoring = json_decode($result['proctoring_log'] ?? '[]', true);
$tab_switches = 0;
$window_blurs = 0;
$total_away_ms = 0;
if (is_array($proctoring) && isset($proctoring['tab_switches'])) {
    $tab_switches = (int)$proctoring['tab_switches'];
    $window_blurs = (int)$proctoring['window_blurs'];
    $total_away_ms = (int)$proctoring['total_away_ms'];
}
$total_away_sec = round($total_away_ms / 1000, 1);

$open_tickets = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'open'")->fetchColumn();

layout_header([
    'title'  => 'Detail Jawaban',
    'active' => 'dashboard',
    'role'   => 'admin',
    'tickets' => $open_tickets,
]);
?>


<section class="panel">
    <header>
        <h2>Detail Jawaban Peserta</h2>
    </header>
    <div class="panel-body">
        <a class="btn-sm btn-quiet" href="index.php">Kembali ke dasbor</a>

        <dl class="facts mt-16">
            <dt>Nama</dt><dd><?= e($result['nama']) ?></dd>
            <dt>NIM / NIP</dt><dd><?= e($result['nim']) ?></dd>
            <dt>Kegiatan</dt><dd><?= e($result['room_name'] ?: $result['survey_title']) ?></dd>
            <dt>Tipe</dt><dd><?= $result['room_name'] ? '<span class="tag tag-ok">Ruangan</span>' : '<span class="tag tag-info">Individu</span>' ?></dd>
            <dt>Skor</dt><dd><?= $result['score'] ?> / <?= $result['total_questions'] ?> (<strong><?= $percentage ?>%</strong>)</dd>
            <dt>Waktu submit</dt><dd><?= date('d M Y, H:i', strtotime($result['created_at'])) ?></dd>
        </dl>

        <?php
        $correct = 0; $wrong = 0;
        foreach ($detail as $d) {
            if ($d['is_correct']) $correct++; else $wrong++;
        }
        ?>
        <div class="metrics mt-16">
            <div class="metric"><dt>Benar</dt><dd><?= $correct ?></dd></div>
            <div class="metric"><dt>Salah</dt><dd><?= $wrong ?></dd></div>
            <div class="metric"><dt>Tidak dijawab</dt><dd><?= $result['total_questions'] - count($detail) ?></dd></div>
        </div>

        <?php if ($tab_switches > 0 || $window_blurs > 0): ?>
            <p class="notice notice-warn mt-16">Peringatan proctoring: berpindah tab <?= $tab_switches ?> kali, jendela kehilangan fokus <?= $window_blurs ?> kali, total waktu di luar halaman <?= $total_away_sec ?> detik.</p>
        <?php else: ?>
            <p class="notice notice-ok mt-16">Proctoring: tidak ada aktivitas mencurigakan terdeteksi.</p>
        <?php endif; ?>

        <hr>

        <h3>Rincian Jawaban per Soal</h3>
        <?php if (empty($detail)): ?>
            <p class="empty">Tidak ada data jawaban.</p>
        <?php else: ?>
            <?php $no = 1; foreach ($detail as $d): ?>
            <div class="question <?= ($d['is_correct'] ? 'is-correct' : 'is-wrong') ?>">
                <div class="q-head">
                    <p class="q-text"><span class="q-no"><?= $no++ ?>.</span><?= e($d['question']) ?></p>
                    <span class="tag <?= $d['is_correct'] ? 'tag-ok' : 'tag-error' ?>"><?= $d['is_correct'] ? 'Benar' : 'Salah' ?></span>
                </div>
                <div class="options">
                    <?php foreach (['A','B','C','D','E'] as $letter):
                        $cls = '';
                        if ($letter === $d['correct']) $cls = 'opt-key-answer';
                        elseif ($letter === $d['user_answer'] && !$d['is_correct']) $cls = 'opt-user-wrong';
                    ?>
                        <div class="option option-static <?= $cls ?>">
                            <span class="opt-key"><?= $letter ?></span>
                            <span><?= e($d['option_' . strtolower($letter)]) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (!empty($d['explanation'])): ?>
                    <div class="explain"><strong>Pembahasan:</strong><br><?= nl2br(e($d['explanation'])) ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="btn-row mt-16">
            <a class="btn-sm btn-quiet" href="index.php">Kembali</a>
            <button data-print class="btn-sm">Cetak</button>
        </div>
    </div>
</section>


<?php layout_footer(['base' => '..']); ?>
