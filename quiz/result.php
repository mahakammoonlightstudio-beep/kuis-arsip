<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layout.php';
require_login();

if ($_SESSION['role'] === 'admin') {
    header('Location: ../admin/index.php');
    exit;
}

$detail = [];
$score = 0;
$total = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['answer'])) {
    $answers   = $_POST['answer'];
    $room_id   = $_SESSION['active_room_id'] ?? null;
    $survey_id = $_SESSION['active_survey_id'] ?? null;

    $stmt_all = $pdo->prepare("SELECT id, correct_answer, question, option_a, option_b, option_c, option_d, option_e, explanation, category FROM questions WHERE survey_id = ? ORDER BY id ASC");
    $stmt_all->execute([$survey_id]);
    $questions = $stmt_all->fetchAll();

    // Seed yang sama dengan quiz/start.php: tiru permutasi opsi yang dilihat
    // peserta agar label yang dikirim dinilai terhadap posisi kunci yang benar.
    $seed_str = quiz_seed_str($_SESSION['user_id'], $survey_id, $room_id);
    $labels   = ['A', 'B', 'C', 'D', 'E'];

    foreach ($questions as $q) {
        $user_answer = $answers[$q['id']] ?? null;

        quiz_shuffle_options($q, $seed_str);

        $correct_text = $q['option_' . strtolower((string)$q['correct_answer'])] ?? null;
        $idx = $correct_text !== null ? array_search($correct_text, [$q['option_a'], $q['option_b'], $q['option_c'], $q['option_d'], $q['option_e']], true) : false;
        $effective_key = $idx !== false ? $labels[$idx] : $q['correct_answer'];

        $is_correct  = ($user_answer === $effective_key);
        if ($is_correct) $score++;

        $detail[] = [
            'id'           => $q['id'],
            'category'     => $q['category'],
            'question'     => $q['question'],
            'user_answer'  => $user_answer,
            'correct'      => $effective_key,
            'is_correct'   => $is_correct,
            'option_a'     => $q['option_a'],
            'option_b'     => $q['option_b'],
            'option_c'     => $q['option_c'],
            'option_d'     => $q['option_d'],
            'option_e'     => $q['option_e'],
            'explanation'  => $q['explanation'],
        ];
    }

    $total = count($questions);

    if ($room_id === null) {
        $pdo->prepare("DELETE FROM results WHERE user_id = ? AND survey_id = ? AND room_id IS NULL")->execute([$_SESSION['user_id'], $survey_id]);
    } else {
        $pdo->prepare("DELETE FROM results WHERE user_id = ? AND room_id = ?")->execute([$_SESSION['user_id'], $room_id]);
    }

    $proctoring = $_POST['proctoring_log'] ?? null;
    $insert = $pdo->prepare("INSERT INTO results (user_id, room_id, survey_id, score, total_questions, answers_json, proctoring_log) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $insert->execute([
        $_SESSION['user_id'], $room_id, $survey_id, $score, $total, json_encode($detail), $proctoring
    ]);

    unset($_SESSION['active_room_id'], $_SESSION['active_survey_id']);
    $pdo->prepare("UPDATE users SET last_quiz_at = NOW() WHERE id = ?")->execute([$_SESSION['user_id']]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $room_id = $_GET['room_id'] ?? null;
    $survey_id = $_GET['survey_id'] ?? null;
    if ($room_id === '0') $room_id = null;

    $sql = "SELECT score, total_questions, answers_json FROM results WHERE user_id = ?";
    $params = [$_SESSION['user_id']];

    if ($survey_id) { $sql .= " AND survey_id = ?"; $params[] = $survey_id; }
    if ($room_id) { $sql .= " AND room_id = ?"; $params[] = $room_id; } else { $sql .= " AND room_id IS NULL"; }

    $sql .= " ORDER BY created_at DESC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result_data = $stmt->fetch();

    if ($result_data) {
        $score = $result_data['score'];
        $total = $result_data['total_questions'];
        $detail = json_decode($result_data['answers_json'], true);
        /* Jaga-jaga: data lama bisa berupa non-array (mis. hasil migrasi) */
        if (!is_array($detail)) { $detail = []; }
    } else {
        header('Location: index.php');
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}

$percentage = $total > 0 ? round(($score / $total) * 100) : 0;
$pass = $percentage >= 70;

layout_header([
    'title' => 'Hasil Kuesioner',
    'active' => 'home',
    'role' => 'peserta',
]);
?>


<section class="panel">
    <header>
        <h2>Hasil Pengerjaan</h2>
        <p>Ringkasan nilai dan rincian jawaban Anda.</p>
    </header>
    <div class="panel-body">
        <div class="score <?= $pass ? '' : 'score-fail' ?>"<?= $pass ? ' data-confetti="1"' : '' ?>>
            <span class="score-value"><span data-countup="<?= (int)$score ?>">0</span> / <?= $total ?></span>
            <span class="score-pct"><span data-countup="<?= $percentage ?>" data-suffix="%">0</span></span>
            <span class="score-verdict"><?= $pass ? 'Lulus. Selamat atas pencapaian Anda.' : 'Belum lulus. Pelajari kembali materi dan coba lagi.' ?></span>
        </div>

        <h3>Rincian Jawaban &amp; Pembahasan</h3>
        <?php $no = 1; foreach ($detail as $d): ?>
            <div class="question <?= $d['is_correct'] ? 'is-correct' : 'is-wrong' ?>">
                <div class="q-head">
                    <p class="q-text"><span class="q-no"><?= $no++ ?>.</span><?= e($d['question']) ?></p>
                    <span class="tag <?= $d['is_correct'] ? 'tag-ok' : 'tag-error' ?>"><?= $d['is_correct'] ? 'Benar' : 'Salah' ?></span>
                </div>
                <div class="options">
                <?php foreach (['a','b','c','d','e'] as $opt):
                    $val = strtoupper($opt);
                    $cls = '';
                    if ($val === $d['correct']) $cls = 'opt-key-answer';
                    elseif ($val === $d['user_answer'] && !$d['is_correct']) $cls = 'opt-user-wrong';
                ?>
                    <div class="option option-static <?= $cls ?>">
                        <span class="opt-key"><?= $val ?></span>
                        <span><?= e($d['option_' . $opt]) ?></span>
                    </div>
                <?php endforeach; ?>
                </div>
                <?php if (!empty($d['explanation'])): ?>
                    <div class="explain">
                        <strong>Pembahasan:</strong>
                        <?= nl2br(e($d['explanation'])) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <div class="btn-row">
            <button data-print class="btn-sm btn-quiet">Cetak hasil</button>
            <?php if ($pass):
                $cert_room_id = $room_id ?? ($_GET['room_id'] ?? null);
                if ($cert_room_id === '0') $cert_room_id = null;
                $cert_survey_id = $survey_id ?? ($_GET['survey_id'] ?? null);
                $cert_url = "certificate.php?survey_id=" . urlencode($cert_survey_id);
                if ($cert_room_id !== null) $cert_url .= "&room_id=" . urlencode($cert_room_id);
            ?>
                <a class="btn-sm" href="<?= $cert_url ?>" target="_blank" rel="noopener">Unduh sertifikat</a>
            <?php endif; ?>
            <a class="btn-sm btn-quiet" href="index.php">Kembali ke beranda</a>
        </div>
    </div>
</section>


<?php layout_footer(['base' => '..']); ?>
