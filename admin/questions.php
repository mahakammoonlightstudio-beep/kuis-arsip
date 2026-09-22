<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layout.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$survey_id = (int)($_GET['survey_id'] ?? 0);
if ($survey_id === 0) {
    header('Location: surveys.php');
    exit;
}

$stmt_survey = $pdo->prepare("SELECT * FROM surveys WHERE id = ?");
$stmt_survey->execute([$survey_id]);
$survey = $stmt_survey->fetch();
if (!$survey) {
    header('Location: surveys.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    csrf_verify();
    $cat = $_POST['category'] ?? 'Umum';
    $q = $_POST['question'];
    $a = $_POST['option_a'];
    $b = $_POST['option_b'];
    $c = $_POST['option_c'];
    $d = $_POST['option_d'];
    $e = $_POST['option_e'];
    $correct = $_POST['correct_answer'];
    $exp = $_POST['explanation'] ?? null;

    $stmt = $pdo->prepare("INSERT INTO questions (survey_id, category, question, option_a, option_b, option_c, option_d, option_e, correct_answer, explanation) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$survey_id, $cat, $q, $a, $b, $c, $d, $e, $correct, $exp]);
    audit_log('insert', 'questions', $stmt->rowCount(), "Soal baru ditambahkan ke survey #$survey_id");
    header("Location: questions.php?survey_id=$survey_id&status=added");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_question_action'])) {
    csrf_verify();
    $del_id = (int)$_POST['delete_question_id'];
    $pdo->prepare("DELETE FROM questions WHERE id = ?")->execute([$del_id]);
    audit_log('delete', 'questions', $del_id, 'Soal dihapus');
    header("Location: questions.php?survey_id=$survey_id&status=deleted");
    exit;
}

$stmt_q = $pdo->prepare("SELECT * FROM questions WHERE survey_id = ? ORDER BY id ASC");
$stmt_q->execute([$survey_id]);
$questions = $stmt_q->fetchAll();

$open_tickets = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'open'")->fetchColumn();

layout_header([
    'title'  => 'Kelola Soal',
    'active' => 'surveys',
    'role'   => 'admin',
    'tickets' => $open_tickets,
]);
?>


<section class="panel">
    <header>
        <h2>Soal: <?= e($survey['title']) ?></h2>
        <p><?= e($survey['description']) ?></p>
    </header>
    <div class="panel-body">
        <a class="btn-sm btn-quiet" href="surveys.php">Kembali ke daftar kuesioner</a>
        <?php if (isset($_GET['status'])): ?>
            <p class="notice notice-ok"><?= $_GET['status'] == 'added' ? 'Soal berhasil ditambahkan.' : 'Soal berhasil dihapus.' ?></p>
        <?php endif; ?>
    </div>
</section>

<section class="panel">
    <header>
        <h2>Tambah Soal Baru</h2>
    </header>
    <div class="panel-body">
        <form method="post">
            <?= csrf_field() ?>
            <div class="field">
                <label class="field-label" for="category">Kategori soal</label>
                <input id="category" type="text" name="category" placeholder="Ujian, Penting, Dasar Hukum" required>
            </div>
            <div class="field">
                <label class="field-label" for="question">Pertanyaan</label>
                <textarea id="question" name="question" required></textarea>
            </div>
            <div class="field-inline">
                <div class="field"><label class="field-label">Opsi A</label><input type="text" name="option_a" required></div>
                <div class="field"><label class="field-label">Opsi B</label><input type="text" name="option_b" required></div>
                <div class="field"><label class="field-label">Opsi C</label><input type="text" name="option_c" required></div>
                <div class="field"><label class="field-label">Opsi D</label><input type="text" name="option_d" required></div>
                <div class="field"><label class="field-label">Opsi E</label><input type="text" name="option_e" required></div>
            </div>
            <div class="field">
                <label class="field-label" for="correct_answer">Kunci jawaban</label>
                <select id="correct_answer" name="correct_answer" required>
                    <option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option><option value="E">E</option>
                </select>
            </div>
            <div class="field">
                <label class="field-label" for="explanation">Pembahasan (opsional)</label>
                <textarea id="explanation" name="explanation"></textarea>
            </div>
            <div class="btn-row">
                <button type="submit" name="add_question" class="btn">Tambah soal</button>
            </div>
        </form>
    </div>
</section>

<section class="panel">
    <header>
        <h2>Daftar Soal (<?= count($questions) ?>)</h2>
    </header>
    <div class="panel-body">
        <?php if (empty($questions)): ?>
            <p class="empty">Belum ada soal.</p>
        <?php else: $no = 1; foreach ($questions as $q): ?>
            <div class="question">
                <div class="q-head">
                    <p class="q-text"><span class="q-no"><?= $no++ ?>.</span><?= e($q['question']) ?></p>
                    <span class="tag tag-ok">Jawaban: <?= e($q['correct_answer']) ?></span>
                </div>
                <ul class="option-list">
                    <?php foreach (['A', 'B', 'C', 'D', 'E'] as $key):
                        $opt_field = 'option_' . strtolower($key);
                        $is_answer = ($key === $q['correct_answer']);
                    ?>
                        <li class="<?= $is_answer ? 'is-answer' : '' ?>">
                            <span class="opt-key"><?= $key ?></span>
                            <span><?= e($q[$opt_field]) ?></span>
                            <?php if ($is_answer): ?><span class="tag tag-ok">Kunci</span><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($q['explanation']): ?>
                    <div class="explain"><strong>Pembahasan:</strong><br><?= nl2br(e($q['explanation'])) ?></div>
                <?php endif; ?>
                <form method="post" class="form-inline" onsubmit="return confirm('Hapus soal ini?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="delete_question_action" value="1">
                    <input type="hidden" name="delete_question_id" value="<?= $q['id'] ?>">
                    <button type="submit" class="btn-sm btn-danger">Hapus</button>
                </form>
            </div>
        <?php endforeach; endif; ?>
    </div>
</section>


<?php layout_footer(['base' => '..']); ?>
