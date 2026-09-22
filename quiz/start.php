<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layout.php';
require_login();

if ($_SESSION['role'] === 'admin') {
    header('Location: ../admin/index.php');
    exit;
}

$is_individual = isset($_GET['mode']) && $_GET['mode'] === 'individual';
$survey_id = 0;

if ($is_individual) {
    $survey_id = (int)($_GET['survey_id'] ?? 0);
    if ($survey_id === 0) { header('Location: index.php'); exit; }

    $stmt_indv = $pdo->prepare("SELECT id FROM results WHERE user_id = ? AND survey_id = ? AND room_id IS NULL");
    $stmt_indv->execute([$_SESSION['user_id'], $survey_id]);
    if ($stmt_indv->fetch()) { header('Location: index.php'); exit; }

    $_SESSION['active_room_id'] = null;
    $_SESSION['active_survey_id'] = $survey_id;

    $stmt_s = $pdo->prepare("SELECT title, duration, max_attempts FROM surveys WHERE id = ? AND is_active = 1");
    $stmt_s->execute([$survey_id]);
    $s_data = $stmt_s->fetch();
    if (!$s_data) { header('Location: index.php'); exit; }

    $max_attempts = $s_data['max_attempts'] ?? 1;
    if ($max_attempts > 0) {
        $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM results WHERE user_id = ? AND survey_id = ? AND room_id IS NULL");
        $stmt_count->execute([$_SESSION['user_id'], $survey_id]);
        if ($stmt_count->fetchColumn() >= $max_attempts) {
            die('<p class="notice notice-error">Kuota pengerjaan kuesioner ini sudah habis.</p>');
        }
    }

    $stmt_survey_time = $pdo->prepare("SELECT start_time, end_time FROM surveys WHERE id = ?");
    $stmt_survey_time->execute([$survey_id]);
    $survey_time = $stmt_survey_time->fetch();
    if ($survey_time) {
        $now = time();
        if ($survey_time['start_time'] && strtotime($survey_time['start_time']) > $now) {
            die('<p class="notice notice-info">Kuesioner ini belum dibuka.</p>');
        }
        if ($survey_time['end_time'] && strtotime($survey_time['end_time']) < $now) {
            die('<p class="notice notice-info">Kuesioner ini sudah berakhir.</p>');
        }
    }
    $_SESSION['active_room_name'] = $s_data['title'];
    $duration_minutes = (int)$s_data['duration'];

} else {
    if (!isset($_SESSION['active_room_id']) || !isset($_SESSION['active_survey_id'])) {
        header('Location: index.php'); exit;
    }

    $room_id = $_SESSION['active_room_id'];
    $survey_id = $_SESSION['active_survey_id'];

    $stmt_room = $pdo->prepare("SELECT * FROM rooms WHERE id = ? AND is_active = 1");
    $stmt_room->execute([$room_id]);
    $room = $stmt_room->fetch();

    if (!$room) {
        unset($_SESSION['active_room_id'], $_SESSION['active_survey_id']);
        header('Location: index.php'); exit;
    }

    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM results WHERE room_id = ?");
    $stmt_count->execute([$room_id]);
    if ($stmt_count->fetchColumn() >= $room['max_participants']) {
        unset($_SESSION['active_room_id'], $_SESSION['active_survey_id']);
        header('Location: index.php'); exit;
    }

    $stmt_check = $pdo->prepare("SELECT id FROM results WHERE user_id = ? AND room_id = ?");
    $stmt_check->execute([$_SESSION['user_id'], $room_id]);
    if ($stmt_check->fetch()) { header('Location: index.php'); exit; }

    $now = time();
    if ($room['start_time'] && strtotime($room['start_time']) > $now) {
        unset($_SESSION['active_room_id'], $_SESSION['active_survey_id']);
        die('<p class="notice notice-info">Ruangan ini belum dibuka.</p>');
    }
    if ($room['end_time'] && strtotime($room['end_time']) < $now) {
        unset($_SESSION['active_room_id'], $_SESSION['active_survey_id']);
        die('<p class="notice notice-info">Ruangan ini sudah ditutup.</p>');
    }

    $_SESSION['active_room_name'] = $room['room_name'];

    $stmt_s = $pdo->prepare("SELECT duration FROM surveys WHERE id = ?");
    $stmt_s->execute([$survey_id]);
    $s_data = $stmt_s->fetch();
    $duration_minutes = $s_data ? (int)$s_data['duration'] : 10;
}

$stmt_q = $pdo->prepare("SELECT * FROM questions WHERE survey_id = ? ORDER BY id ASC");
$stmt_q->execute([$survey_id]);
$questions = $stmt_q->fetchAll();

if (empty($questions)) { die("Belum ada soal pada kuesioner ini."); }

$require_agreement = false;
foreach ($questions as $q) {
    $cat_lower = strtolower($q['category']);
    if (strpos($cat_lower, 'ujian') !== false || strpos($cat_lower, 'penting') !== false) {
        $require_agreement = true;
        break;
    }
}

$total_questions = count($questions);
$page_title = $_SESSION['active_room_name'];

$seed_str = quiz_seed_str($_SESSION['user_id'], $survey_id, $_SESSION['active_room_id'] ?? 'indv');
$seed_num = abs(crc32($seed_str));

mt_srand($seed_num);
shuffle($questions);

foreach ($questions as &$q) {
    quiz_shuffle_options($q, $seed_str);
}
unset($q);

$storage_key = 'quiz_answers_' . $_SESSION['user_id'] . '_' . $survey_id . '_' . ($_SESSION['active_room_id'] ?? 'indv') . '_' . $seed_num;
$proctoring_key = 'proctoring_' . $_SESSION['user_id'] . '_' . $survey_id . '_' . ($_SESSION['active_room_id'] ?? 'indv');

layout_header([
    'title' => 'Pengerjaan',
    'active' => 'home',
    'role' => 'peserta',
    'head' => '<link rel="preload" as="style" href="../assets/style.css">',
    'main_style' => 'padding-top: var(--s4); padding-bottom: var(--s4);',
]);
?>


<?php if ($require_agreement): ?>
<dialog id="agreementDialog" open aria-labelledby="agreeTitle" aria-modal="true" class="dialog-panel" role="dialog">
    <form method="dialog" class="dialog-form">
        <header class="dialog-header">
            <h2 id="agreeTitle">Tata Tertib Ujian</h2>
        </header>
        <div class="dialog-body">
            <p class="muted">Sebelum memulai, pahami ketentuan berikut:</p>
            <ol style="padding-left: var(--s5); margin-top: var(--s3);">
                <li>Waktu pengerjaan adalah <strong><?= $duration_minutes ?> menit</strong>.</li>
                <li>Jawaban tersimpan otomatis. Jangan menyegarkan halaman.</li>
                <li>Dilarang membuka tab lain atau keluar dari halaman ujian.</li>
                <li>Dilarang menyontek atau membuka materi kearsipan.</li>
                <li>Jika waktu habis, jawaban akan otomatis dikumpulkan.</li>
            </ol>
            <label class="agree" style="margin-top: var(--s4); display: block;">
                <input type="checkbox" id="agreeCheckbox" onchange="document.getElementById('startQuizBtn').disabled = !this.checked" required>
                Saya membaca, mengerti, dan menyetujui ketentuan di atas.
            </label>
        </div>
        <footer class="dialog-footer">
            <a class="btn-sm btn-quiet" href="index.php">Kembali</a>
            <button type="button" id="startQuizBtn" class="btn-sm" disabled>Mulai ujian</button>
        </footer>
    </form>
</dialog>
<?php endif; ?>

<dialog id="timeupDialog" aria-labelledby="timeupTitle" aria-modal="true" class="dialog-panel" role="dialog">
    <header class="dialog-header">
        <h2 id="timeupTitle">Waktu Habis</h2>
    </header>
    <div class="dialog-body">
        <p>Waktu pengerjaan telah berakhir. Jawaban Anda akan dikumpulkan secara otomatis.</p>
    </div>
    <footer class="dialog-footer">
        <button type="button" class="btn-sm" id="timeupOk">Mengumpulkan</button>
    </footer>
</dialog>

<dialog id="confirmSubmitDialog" aria-labelledby="confirmSubmitTitle" aria-modal="true" class="dialog-panel" role="dialog">
    <header class="dialog-header">
        <h2 id="confirmSubmitTitle">Konfirmasi Pengumpulan</h2>
    </header>
    <div class="dialog-body">
        <p id="confirmSubmitMsg">Masih ada soal yang belum dijawab. Tetap kumpulkan?</p>
    </div>
    <footer class="dialog-footer">
        <button type="button" class="btn-sm btn-quiet" data-close-dialog="confirmSubmitDialog">Batal</button>
        <button type="button" class="btn-sm btn-danger" id="confirmSubmitOk">Ya, kumpulkan</button>
    </footer>
</dialog>

<dialog id="leaveConfirmDialog" aria-labelledby="leaveConfirmTitle" aria-modal="true" class="dialog-panel" role="dialog">
    <header class="dialog-header">
        <h2 id="leaveConfirmTitle">Keluar dari Ujian?</h2>
    </header>
    <div class="dialog-body">
        <p>Jika Anda keluar sekarang, waktu akan terus berjalan dan jawaban tersimpan. Yakin ingin keluar?</p>
    </div>
    <footer class="dialog-footer">
        <button type="button" class="btn-sm btn-quiet" data-close-dialog="leaveConfirmDialog">Tetap di sini</button>
        <a class="btn-sm btn-danger" href="index.php">Keluar</a>
    </footer>
</dialog>

<div class="quiz-shell" role="region" aria-label="Area pengerjaan kuesioner">
    <div class="panel" style="overflow: hidden;">
        <div class="quiz-bar">
            <h2><?= e($page_title) ?></h2>
            <div class="clocks" role="timer" aria-live="polite" aria-label="Waktu tersisa">
                <span class="clock" id="real-clock" aria-hidden="true">--:--:--</span>
                <span class="clock clock-run" id="timer" aria-live="off"><?= sprintf("%02d:00", $duration_minutes) ?></span>
            </div>
        </div>

        <form id="quizForm" action="result.php" method="post" class="panel-body" style="padding-bottom: var(--s8);">
            <?php $no = 1; foreach ($questions as $q): ?>
                <section class="question" id="question-<?= $q['id'] ?>" aria-labelledby="q-<?= $q['id'] ?>-label">
                    <div class="q-head">
                        <p class="q-text" id="q-<?= $q['id'] ?>-label"><span class="q-no"><?= $no++ ?>.</span><?= e($q['question']) ?></p>
                        <button type="button" class="flag-btn" data-qid="<?= $q['id'] ?>" aria-pressed="false" aria-label="Tandai ragu-ragu">Ragu-ragu</button>
                    </div>
                    <div class="options" role="radiogroup" aria-label="Pilihan jawaban untuk soal <?= $no - 1 ?>">
                    <?php
                    $display_labels = ['A', 'B', 'C', 'D', 'E'];
                    $i = 0;
                    foreach (['option_a','option_b','option_c','option_d','option_e'] as $key):
                        $label = $display_labels[$i++];
                    ?>
                        <label class="option">
                            <input type="radio" name="answer[<?= $q['id'] ?>]" value="<?= $label ?>" data-qid="<?= $q['id'] ?>" aria-label="Pilihan <?= $label ?>">
                            <span class="opt-key"><?= $label ?></span>
                            <span><?= e($q[$key]) ?></span>
                        </label>
                    <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
            <div class="btn-row" style="position: sticky; bottom: 0; background: var(--paper); padding-top: var(--s4); border-top: 1px solid var(--line); margin: 0 calc(-1 * var(--s5)) calc(-1 * var(--s5)); padding-left: var(--s5); padding-right: var(--s5); z-index: 10;">
                <button type="submit" class="btn w-full" style="max-width: 300px;">Kumpulkan jawaban</button>
            </div>
        </form>
    </div>

    <aside class="quiz-aside panel" aria-label="Navigasi soal">
        <header>
            <h2>Navigasi Soal</h2>
        </header>
        <div class="panel-body">
            <div class="nav-grid" id="navGrid" role="navigation" aria-label="Peta soal">
                <?php $no_nav = 1; foreach ($questions as $q): ?>
                    <button type="button" class="nav-btn" id="nav-btn-<?= $q['id'] ?>" onclick="scrollToQuestion(<?= $q['id'] ?>)" aria-label="Soal <?= $no_nav ?>"><?= $no_nav++ ?></button>
                <?php endforeach; ?>
            </div>
            <ul class="legend" aria-label="Legenda status soal">
                <li><span class="swatch swatch-answered" aria-hidden="true"></span> Terjawab</li>
                <li><span class="swatch swatch-flagged" aria-hidden="true"></span> Ragu-ragu</li>
                <li><span class="swatch swatch-empty" aria-hidden="true"></span> Belum diisi</li>
            </ul>
        </div>
    </aside>
</div>


<?php layout_footer(['base' => '..', 'scripts' => '
<script>
(function () {
    "use strict";

    var timerEl = document.getElementById("timer");
    var clockEl = document.getElementById("real-clock");
    var form = document.getElementById("quizForm");
    var storageKey = "' . $storage_key .'";
    var proctoringKey = "' . $proctoring_key .'";
    var durationSeconds = ' . ($duration_minutes * 60) . ';
    var requireAgreement = ' . ($require_agreement ? "true" : "false") . ';
    var timeLeft = durationSeconds;
    var timerInterval = null;
    var isSubmitting = false;
    var proctoringLog = [];

    function loadProctoringLog() {
        try {
            proctoringLog = JSON.parse(localStorage.getItem(proctoringKey) || "[]");
        } catch (e) { proctoringLog = []; }
    }
    function saveProctoringLog() {
        try { localStorage.setItem(proctoringKey, JSON.stringify(proctoringLog)); } catch (e) {}
    }
    function addProctoringEvent(type) {
        proctoringLog.push({ ts: Date.now(), type: type });
        saveProctoringLog();
    }

    function updateClock() {
        var n = new Date();
        clockEl.textContent = String(n.getHours()).padStart(2,"0") + ":" + String(n.getMinutes()).padStart(2,"0") + ":" + String(n.getSeconds()).padStart(2,"0");
    }
    updateClock();
    setInterval(updateClock, 1000);

    function formatTime(seconds) {
        var m = Math.floor(seconds / 60);
        var s = seconds % 60;
        return (m < 10 ? "0" : "") + m + ":" + (s < 10 ? "0" : "") + s;
    }

    function startTimer() {
        if (timerInterval) return;
        timerInterval = setInterval(function () {
            var m = Math.floor(timeLeft / 60);
            var s = timeLeft % 60;
            timerEl.textContent = (m < 10 ? "0" : "") + m + ":" + (s < 10 ? "0" : "") + s;

            if (timeLeft <= 60) {
                /* Satu jalur untuk ≤ 60 detik: kuning lalu merah, dan pastikan
                   class 'clock-run' selalu dilepas (durasi singkat pun aman). */
                timerEl.classList.add("clock-warn");
                if (timeLeft <= 30) {
                    timerEl.classList.add("clock-danger");
                }
                timerEl.classList.remove("clock-run");
            }

            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                timerInterval = null;
                var dlg = document.getElementById("timeupDialog");
                if (dlg) {
                    dlg.showModal();
                    document.getElementById("timeupOk").onclick = function () {
                        localStorage.removeItem(storageKey);
                        submitForm();
                    };
                } else {
                    submitForm();
                }
            }
            timeLeft--;
        }, 1000);
    }

    function submitForm() {
        if (isSubmitting) return;
        isSubmitting = true;

        var prov = document.createElement("input");
        prov.type = "hidden";
        prov.name = "proctoring_log";
        prov.value = JSON.stringify({
            total_events: proctoringLog.length,
            tab_switches: proctoringLog.filter(function(e){return e.type==="blur";}).length,
            window_blurs: proctoringLog.filter(function(e){return e.type==="window_blur";}).length
        });
        form.appendChild(prov);
        localStorage.removeItem(storageKey);
        form.submit();
    }

    function saveAnswer(qid, val) {
        var saved = {};
        try { saved = JSON.parse(localStorage.getItem(storageKey) || "{}"); } catch (e) {}
        saved[qid] = val;
        try { localStorage.setItem(storageKey, JSON.stringify(saved)); } catch (e) {}
    }

    function loadAnswers() {
        var saved = {};
        try { saved = JSON.parse(localStorage.getItem(storageKey) || "{}"); } catch (e) {}
        for (var qid in saved) {
            var radio = document.querySelector("input[name=\"answer[" + qid + "]\"][value=\"" + saved[qid] + "\"]");
            if (radio) { radio.checked = true; updateNav(qid); }
        }
    }

    function scrollToQuestion(qid) {
        var el = document.getElementById("question-" + qid);
        if (el) {
            el.scrollIntoView({ behavior: "smooth", block: "center" });
            el.focus({ preventScroll: true });
        }
    }
    /* Dipakai atribut onclick di .nav-btn, jadi harus diekspos global */
    window.scrollToQuestion = scrollToQuestion;

    function updateNav(qid) {
        var btn = document.getElementById("nav-btn-" + qid);
        if (btn) btn.classList.add("is-answered");
    }

    function toggleFlag(qid) {
        var btn = document.querySelector(".flag-btn[data-qid=\"" + qid + "\"]");
        var navBtn = document.getElementById("nav-btn-" + qid);
        if (!btn || !navBtn) return;
        var pressed = btn.getAttribute("aria-pressed") === "true";
        btn.setAttribute("aria-pressed", pressed ? "false" : "true");
        btn.classList.toggle("is-flagged", !pressed);
        navBtn.classList.toggle("is-flagged", !pressed);
    }

    function showConfirmSubmit(unansweredCount) {
        var dlg = document.getElementById("confirmSubmitDialog");
        var msg = document.getElementById("confirmSubmitMsg");
        var okBtn = document.getElementById("confirmSubmitOk");
        if (!dlg || !msg || !okBtn) return;

        msg.textContent = "Masih ada " + unansweredCount + " soal yang belum dijawab. Tetap kumpulkan?";

        okBtn.onclick = function () {
            dlg.close();
            submitForm();
        };
        dlg.showModal();
    }

    document.querySelectorAll(".flag-btn").forEach(function (btn) {
        btn.addEventListener("click", function () {
            var qid = btn.getAttribute("data-qid");
            toggleFlag(qid);
        });
    });

    form.addEventListener("change", function (e) {
        if (e.target.matches("input[type=radio]")) {
            var qid = e.target.getAttribute("data-qid");
            var val = e.target.value;
            updateNav(qid);
            saveAnswer(qid, val);
        }
    });

    form.addEventListener("submit", function (e) {
        if (isSubmitting) return;
        var unanswered = 0;
        document.querySelectorAll(".nav-btn").forEach(function (b) {
            if (!b.classList.contains("is-answered")) unanswered++;
        });
        if (unanswered > 0) {
            e.preventDefault();
            showConfirmSubmit(unanswered);
        }
    });

    document.addEventListener("visibilitychange", function () {
        if (document.hidden) addProctoringEvent("blur");
    });
    window.addEventListener("blur", function () {
        addProctoringEvent("window_blur");
    });

    window.addEventListener("beforeunload", function (e) {
        if (!isSubmitting && timerInterval) {
            e.preventDefault();
            e.returnValue = "";
        }
    });

    if (requireAgreement) {
        var ad = document.getElementById("agreementDialog");
        var sb = document.getElementById("startQuizBtn");
        if (ad && sb) {
            sb.addEventListener("click", function () {
                ad.close();
                startTimer();
            });
        }
    } else {
        startTimer();
    }

    loadProctoringLog();
    window.addEventListener("load", loadAnswers);
})();
</script>
']); ?>