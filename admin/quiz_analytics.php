<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layout.php';
require_login();

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$filter_survey = (int)($_GET['survey_id'] ?? 0);
$filter_room   = (int)($_GET['room_id'] ?? 0);

$surveys = $pdo->query("SELECT id, title FROM surveys ORDER BY created_at DESC")->fetchAll();
$rooms = $pdo->query("SELECT id, room_name, room_code FROM rooms WHERE is_active = 1 ORDER BY created_at DESC")->fetchAll();

$q_sql = "SELECT id, question, category, correct_answer, explanation,
          option_a, option_b, option_c, option_d, option_e FROM questions";
$qparams = [];
if ($filter_survey > 0) {
    $q_sql .= " WHERE survey_id = ?";
    $qparams[] = $filter_survey;
}
$q_sql .= " ORDER BY id ASC";
$q_stmt = $pdo->prepare($q_sql);
$q_stmt->execute($qparams);

// Statistik dihitung di PHP: posisi array pada answers_json tidak identik
// dengan id soal (global AUTO_INCREMENT), jadi pencocokan harus lewat 'id'.
$analytics = [];
foreach ($q_stmt->fetchAll() as $q) {
    $analytics[$q['id']] = [
        'id'             => $q['id'],
        'question'       => $q['question'],
        'category'       => $q['category'],
        'correct_answer' => $q['correct_answer'],
        'explanation'    => $q['explanation'],
        'option_a'       => $q['option_a'],
        'option_b'       => $q['option_b'],
        'option_c'       => $q['option_c'],
        'option_d'       => $q['option_d'],
        'option_e'       => $q['option_e'],
        'total_attempted' => 0,
        'correct_count'   => 0,
        'select_counts'   => ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0],
    ];
}

$r_sql = "SELECT answers_json FROM results WHERE answers_json IS NOT NULL";
$rparams = [];
if ($filter_survey > 0) {
    $r_sql .= " AND survey_id = ?";
    $rparams[] = $filter_survey;
}
if ($filter_room > 0) {
    $r_sql .= " AND room_id = ?";
    $rparams[] = $filter_room;
}
$r_stmt = $pdo->prepare($r_sql);
$r_stmt->execute($rparams);

while ($row = $r_stmt->fetch()) {
    $ans = json_decode($row['answers_json'], true);
    if (!is_array($ans)) continue;
    foreach ($ans as $item) {
        $qid = $item['id'] ?? null;
        if ($qid === null || !isset($analytics[$qid])) continue;
        $analytics[$qid]['total_attempted']++;
        if (!empty($item['is_correct'])) $analytics[$qid]['correct_count']++;
        $ua = (string)($item['user_answer'] ?? '');
        if (isset($analytics[$qid]['select_counts'][$ua])) {
            $analytics[$qid]['select_counts'][$ua]++;
        }
    }
}
$analytics = array_values($analytics);

$overall_stats = $pdo->query("
    SELECT s.id, s.title,
           COUNT(DISTINCT r.user_id) AS total_test_takers,
           AVG(r.score) AS avg_score,
           MAX(r.score) AS best_score,
           MIN(r.score) AS worst_score
    FROM surveys s
    LEFT JOIN results r ON r.survey_id = s.id
    WHERE r.score IS NOT NULL
    GROUP BY s.id
    ORDER BY s.id ASC
")->fetchAll();

$open_tickets = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'open'")->fetchColumn();

layout_header([
    'title'  => 'Analisis Soal',
    'active' => 'analytics',
    'role'   => 'admin',
    'tickets' => $open_tickets,
    'head'   => '<script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>',
]);
?>


<section class="panel">
    <header>
        <h2>Analisis Soal Per Pertanyaan</h2>
        <p> Tingkat keberhasilan peserta untuk tiap soal.</p>
    </header>
    <div class="panel-body">
        <form method="get" class="filterbar">
            <div class="field">
                <label class="field-label">Kuesioner</label>
                <select name="survey_id" onchange="this.form.submit()">
                    <option value="0">Semua</option>
                    <?php foreach ($surveys as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $filter_survey == $s['id'] ? 'selected' : '' ?>><?= e($s['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field-label">Ruangan</label>
                <select name="room_id" onchange="this.form.submit()">
                    <option value="0">Semua</option>
                    <?php foreach ($rooms as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= $filter_room == $r['id'] ? 'selected' : '' ?>><?= e($r['room_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</section>

<?php if (!empty($overall_stats)): ?>
<section class="panel">
    <header><h2>Ringkasan per Kuesioner</h2></header>
    <div class="panel-body panel-flush">
        <div class="table-scroll">
            <table class="data">
                <thead><tr><th>Kuesioner</th><th class="num">Peserta</th><th class="num">Rata-rata</th><th class="num">Tertinggi</th><th class="num">Terendah</th></tr></thead>
                <tbody>
                <?php foreach ($overall_stats as $st): ?>
                    <tr>
                        <td><?= e($st['title']) ?></td>
                        <td class="num"><?= $st['total_test_takers'] ?></td>
                        <td class="num"><?= number_format($st['avg_score'], 1) ?></td>
                        <td class="num"><?= $st['best_score'] ?></td>
                        <td class="num"><?= $st['worst_score'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="panel">
    <header><h2>Rincian per Soal</h2></header>
    <div class="panel-body">
        <?php if (empty($analytics)): ?>
            <p class="empty">Belum ada data hasil untuk menampilkan analisis.</p>
        <?php else: ?>
            <?php foreach ($analytics as $idx => $q): ?>
            <div class="question" style="margin-bottom:20px;">
                <div class="q-head">
                    <p class="q-text"><span class="q-no"><?= $idx + 1 ?>.</span><?= e($q['question']) ?></p>
                    <?php $pctQ = $q['total_attempted'] > 0 ? round(($q['correct_count'] / $q['total_attempted']) * 100) : 0; ?>
                    <span class="tag <?= $pctQ >= 70 ? 'tag-ok' : 'tag-error' ?>"><?= $pctQ ?>% benar</span>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:12px;">
                    <?php
                    $options = ['A' => $q['option_a'], 'B' => $q['option_b'], 'C' => $q['option_c'], 'D' => $q['option_d'], 'E' => $q['option_e']];
                    $correct_letter = $q['correct_answer'];
                    $select_counts = $q['select_counts'];
                    $max_sel = max($select_counts);
                    foreach ($options as $letter => $text):
                        $cls = '';
                        if ($letter === $correct_letter) $cls = ' style="background:var(--ok-bg);border-left:3px solid var(--ok-fg);"';
                        elseif ($select_counts[$letter] == $max_sel && $letter !== $correct_letter && $max_sel > 0) $cls = ' style="background:var(--warn-bg);border-left:3px solid var(--warn-fg);"';
                    ?>
                        <div<?= $cls ?> style="padding:6px 10px;border-radius:4px;border-left:3px solid var(--line);">
                            <strong><?= $letter ?>.</strong> <?= e($text) ?>
                            <?php if ($letter === $correct_letter): ?> <span class="tag tag-ok">Kunci</span>
                            <?php elseif ($select_counts[$letter] > 0): ?> &mdash; <?= $select_counts[$letter] ?> org
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($q['total_attempted'] > 0): ?>
                <div style="margin-top:10px;padding:8px 12px;background:var(--canvas-2);border-radius:4px;font-size:12px;color:var(--ink-2);">
                    <?= $q['total_attempted'] ?> menjawab &middot; <?= $q['correct_count'] ?> benar &middot; <?= (int)$q['total_attempted'] - (int)$q['correct_count'] ?> salah
                </div>
                <?php endif; ?>

                <?php if (!empty($q['explanation'])): ?>
                <div class="explain" style="margin-top:8px;padding:8px 12px;border-radius:4px;font-size:13px;">
                    <strong>Pembahasan:</strong> <?= nl2br(e($q['explanation'])) ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>


<?php layout_footer(['base' => '..']); ?>
