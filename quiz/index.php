<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layout.php';

if (is_logged_in() && $_SESSION['role'] === 'admin') {
    header('Location: ../admin/index.php');
    exit;
}

$is_guest = !is_logged_in();
$error    = '';
$success  = isset($_GET['status']) && $_GET['status'] === 'done'
    ? 'Kuesioner telah diselesaikan.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['room_code'])) {
    csrf_verify();
    if ($is_guest) {
        header('Location: ../auth/login.php?status=login_required');
        exit;
    }
    $code_input = trim($_POST['room_code']);
    $stmt = $pdo->prepare("SELECT * FROM rooms WHERE room_code = ? AND is_active = 1");
    $stmt->execute([$code_input]);
    $room = $stmt->fetch();

    if (!$room) {
        $error = 'Kode ruangan salah atau ruangan sudah ditutup.';
    } else {
        $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM results WHERE room_id = ?");
        $stmt_count->execute([$room['id']]);
        if ($stmt_count->fetchColumn() >= $room['max_participants']) {
            $error = 'Maaf, kuota peserta di ruangan ini sudah penuh.';
        } else {
            $stmt_check = $pdo->prepare("SELECT id FROM results WHERE user_id = ? AND room_id = ?");
            $stmt_check->execute([$_SESSION['user_id'], $room['id']]);
            if ($stmt_check->fetch()) {
                $error = 'Anda sudah pernah mengerjakan ulangan di ruangan ini.';
            } else {
                $_SESSION['active_room_id']   = $room['id'];
                $_SESSION['active_survey_id'] = $room['survey_id'];
                $_SESSION['active_room_name'] = $room['room_name'];
                header('Location: start.php');
                exit;
            }
        }
    }
}

$surveys = $pdo->query("SELECT * FROM surveys WHERE is_active = 1 ORDER BY created_at DESC")->fetchAll();

$individual_status = [];
if (!$is_guest) {
    // Satu query untuk SEMUA kuesioner (menghindari N+1 query per kuesioner)
    $stAll = $pdo->prepare(
        "SELECT survey_id, id FROM results
         WHERE user_id = ? AND room_id IS NULL AND survey_id IN (" .
        implode(',', array_fill(0, max(count($surveys), 1), '?')) . ")"
    );
    $params = array_merge([$_SESSION['user_id']], array_map(fn($s) => (int)$s['id'], $surveys));
    $stAll->execute($params);
    foreach ($stAll->fetchAll(PDO::FETCH_KEY_PAIR) as $sid => $rid) {
        $individual_status[(int)$sid] = ['id' => $rid];
    }
}

$history = [];
if (!$is_guest) {
    $st = $pdo->prepare("
        SELECT r.room_name, s.title AS survey_title, res.score, res.total_questions, res.created_at, res.room_id, res.survey_id
        FROM results res
        LEFT JOIN rooms r ON res.room_id = r.id
        LEFT JOIN surveys s ON res.survey_id = s.id
        WHERE res.user_id = ?
        ORDER BY res.created_at DESC
    ");
    $st->execute([$_SESSION['user_id']]);
    $history = $st->fetchAll();
}

$leaderboard = $pdo->query("
    SELECT u.id, u.nama, u.nim,
           MAX(ROUND((r.score / r.total_questions) * 100)) AS best_pct,
           MAX(r.score) AS best_score
    FROM users u
    JOIN results r ON u.id = r.user_id
    WHERE u.role = 'peserta'
    GROUP BY u.id
    ORDER BY best_pct DESC, best_score DESC, u.nama ASC
    LIMIT 10
")->fetchAll();

$badges = ['top3' => false, 'perfect' => false, 'diligent' => false, 'passed' => false];
if (!$is_guest) {
    $uid = $_SESSION['user_id'];

    /* Efisiensi: 4 pengecekan badge digabung jadi SATU query
       (sebelumnya 4 query terpisah per kunjungan beranda). */
    $stB = $pdo->prepare(
        "SELECT
            MAX(score = total_questions) AS perfect,
            MAX((score / total_questions) * 100 >= 70) AS passed,
            COUNT(DISTINCT survey_id) AS surveys
         FROM results WHERE user_id = ?"
    );
    $stB->execute([$uid]);
    $b = $stB->fetch() ?: [];
    if (!empty($b['perfect'])) $badges['perfect'] = true;
    if (!empty($b['passed'])) $badges['passed'] = true;
    if ((int)($b['surveys'] ?? 0) >= 3) $badges['diligent'] = true;

    $top = $pdo->query(
        "SELECT user_id FROM results GROUP BY user_id
         ORDER BY MAX(ROUND((score/total_questions)*100)) DESC, MAX(score) DESC LIMIT 3"
    )->fetchAll(PDO::FETCH_COLUMN);
    if (in_array($uid, $top)) $badges['top3'] = true;
}

layout_header([
    'title'  => 'Beranda',
    'active' => 'home',
    'role'   => $is_guest ? 'guest' : 'peserta',
    'feedback' => !$is_guest,
]);
?>


<?php if ($error): ?>
    <p class="notice notice-error" role="alert"><?= e($error) ?></p>
<?php elseif ($success): ?>
    <p class="notice notice-ok" role="status"><?= e($success) ?></p>
<?php endif; ?>

<section class="cols-2 stack" aria-labelledby="quick-access-heading">
    <h2 id="quick-access-heading" class="visually-hidden">Akses Cepat</h2>

    <article class="panel">
        <header>
            <h2>Ulangan dengan Kode Ruangan</h2>
            <p>Masukkan kode dari pembimbing untuk mengikuti sesi.</p>
        </header>
        <div class="panel-body">
            <form method="post" id="roomCodeForm">
                <?= csrf_field() ?>
                <div class="field">
                    <label class="field-label" for="room_code">Kode ruangan</label>
                    <input id="room_code" type="text" name="room_code" class="code-input" placeholder="KUKAR2024" required autocomplete="off" spellcheck="false">
                </div>
                <div class="btn-row">
                    <button type="submit" class="btn w-full">Verifikasi & mulai</button>
                </div>
            </form>
        </div>
    </article>

    <article class="panel">
        <header>
            <h2>Kuesioner Individu</h2>
            <p>Pilih kuesioner mandiri yang sedang dibuka.</p>
        </header>
        <div class="panel-body">
        <?php if (empty($surveys)): ?>
            <p class="notice notice-info">Belum ada kuesioner individu yang dibuka.</p>
        <?php else: ?>
            <ul class="item-list" role="list">
            <?php foreach ($surveys as $s): ?>
                <?php
                $time_valid = true;
                $time_msg   = '';
                $now = time();
                if (!empty($s['start_time']) && strtotime($s['start_time']) > $now) {
                    $time_valid = false;
                    $time_msg = 'Belum dibuka - ' . date('d M Y H:i', strtotime($s['start_time']));
                }
                if (!empty($s['end_time']) && strtotime($s['end_time']) < $now) {
                    $time_valid = false;
                    $time_msg = 'Sudah berakhir - ' . date('d M Y H:i', strtotime($s['end_time']));
                }
                ?>
                <li class="item">
                    <div class="item-main">
                        <div class="tag-row">
                            <?php if ($s['type'] == 'ujian'): ?>
                                <span class="tag tag-warn">Ujian resmi</span>
                            <?php else: ?>
                                <span class="tag tag-info">Kuesioner</span>
                            <?php endif; ?>
                            <span class="tag"><?= e($s['level']) ?></span>
                        </div>
                        <div class="item-title"><?= e($s['title']) ?></div>
                        <div class="item-meta">
                            <?= e($s['description']) ?> &middot; durasi <?= (int)$s['duration'] ?> menit
                        </div>
                    </div>
                    <div>
                    <?php if ($is_guest): ?>
                        <a class="btn-sm btn-quiet" href="../auth/login.php?status=login_required">Masuk untuk mengerjakan</a>
                    <?php elseif (!empty($individual_status[$s['id']])): ?>
                        <a class="btn-sm btn-quiet" href="result.php?survey_id=<?= $s['id'] ?>&room_id=0">Lihat hasil</a>
                    <?php elseif ($time_valid): ?>
                        <a class="btn-sm" href="start.php?mode=individual&survey_id=<?= $s['id'] ?>">Kerjakan</a>
                    <?php else: ?>
                        <span class="tag tag-warn"><?= e($time_msg) ?></span>
                    <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        </div>
    </article>
</section>

<section class="panel reveal" aria-labelledby="rekomendasi-heading">
    <header>
        <h2 id="rekomendasi-heading">Rekomendasi Untukmu</h2>
        <p>Platform kuis dan aplikasi lain dari Diarpus Kukar.</p>
    </header>
    <div class="panel-body">
        <ul class="item-list" role="list">
            <li class="item">
                <div class="rec-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 19c-1.5 1.5-4 1.5-5.5 0S2 15 3.5 13.5L7 10"/><path d="M15 5c1.5-1.5 4-1.5 5.5 0s1.5 4 0 5.5L17 14"/><path d="M4 20l6-6"/></svg>
                </div>
                <div class="item-main">
                    <div class="tag-row">
                        <span class="tag tag-info">Website Kuis</span>
                        <span class="tag">Gratis</span>
                    </div>
                    <div class="item-title">Main Pintar &mdash; Kuis Edukatif Kearsipan</div>
                    <div class="item-meta">Asah pengetahuan kearsipan lewat kuis seru: main sendiri, mode live multiplayer bersama teman, dan papan peringkat.</div>
                </div>
                <div>
                    <a class="btn-sm" href="https://mainpintar.rf.gd/" target="_blank" rel="noopener">Buka Main Pintar</a>
                </div>
            </li>
            <li class="item">
                <div class="rec-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="2" width="10" height="20" rx="2"/><line x1="11" y1="18" x2="13" y2="18"/></svg>
                </div>
                <div class="item-main">
                    <div class="tag-row">
                        <span class="tag tag-ok">Aplikasi</span>
                        <span class="tag">Android &amp; iOS</span>
                    </div>
                    <div class="item-title">Aplikasi IKukar</div>
                    <div class="item-meta">Akses layanan perpustakaan digital Kabupaten Kutai Kartanegara langsung dari ponsel Anda.</div>
                </div>
                <div class="tag-row">
                    <a class="btn-sm btn-quiet" href="https://play.google.com/store/apps/details?id=id.kubuku.kbk12535c5" target="_blank" rel="noopener">Google Play</a>
                    <a class="btn-sm btn-quiet" href="https://apps.apple.com/id/app/ikukar/id6476895578" target="_blank" rel="noopener">App Store</a>
                </div>
            </li>
        </ul>
    </div>
</section>

<?php if (!$is_guest): ?>
<section class="panel reveal" aria-labelledby="achievements-heading">
    <header>
        <h2 id="achievements-heading">Pencapaian</h2>
        <p>Terkumpul seiring penyelesaian kuesioner dan skor tinggi.</p>
    </header>
    <div class="panel-body">
        <ul class="awards" role="list">
            <li class="award" data-earned="<?= $badges['top3'] ? 1 : 0 ?>">
                <span class="award-name">Juara Kelas</span>
                <span class="award-state"><?= $badges['top3'] ? 'Diraih' : 'Belum diraih' ?></span>
                <span class="award-desc">Masuk tiga besar peringkat.</span>
            </li>
            <li class="award" data-earned="<?= $badges['perfect'] ? 1 : 0 ?>">
                <span class="award-name">Sempurna</span>
                <span class="award-state"><?= $badges['perfect'] ? 'Diraih' : 'Belum diraih' ?></span>
                <span class="award-desc">Skor 100% di kuesioner mana pun.</span>
            </li>
            <li class="award" data-earned="<?= $badges['diligent'] ? 1 : 0 ?>">
                <span class="award-name">Rajin</span>
                <span class="award-state"><?= $badges['diligent'] ? 'Diraih' : 'Belum diraih' ?></span>
                <span class="award-desc">Selesaikan minimal tiga kuesioner.</span>
            </li>
            <li class="award" data-earned="<?= $badges['passed'] ? 1 : 0 ?>">
                <span class="award-name">Lulus Ujian</span>
                <span class="award-state"><?= $badges['passed'] ? 'Diraih' : 'Belum diraih' ?></span>
                <span class="award-desc">Lulus (70% atau lebih) di ujian mana pun.</span>
            </li>
        </ul>
    </div>
</section>
<?php endif; ?>

<section class="panel" aria-labelledby="about-heading">
    <header>
        <h2 id="about-heading">Selayang Pandang</h2>
    </header>
    <div class="panel-body">
        <div class="prose">
            <p>Sebelum terbentuk menjadi <strong>Dinas Kearsipan dan Perpustakaan Kabupaten Kutai Kartanegara</strong>, perangkat daerah ini berasal dari dua lembaga: Kantor Perpustakaan Umum Daerah dan Kantor Arsip Daerah yang awalnya berdiri sendiri.</p>
            <p>Kantor Perpustakaan Umum Daerah berdiri berdasarkan SK Bupati KDH Tingkat II Kutai tanggal 16 April 1980 Nomor HUK-131/C/Or-016/1980 tentang pembentukan Perpustakaan Umum Daerah Tingkat II Kutai.</p>
            <p>Sedangkan Kantor Arsip Daerah berdiri sejak tahun 1999 berdasarkan Peraturan Daerah Kabupaten Kutai Nomor 18 Tahun 1997 tentang pembentukan susunan organisasi dan tata kerja Kantor Arsip Daerah Kabupaten Kutai.</p>
            <p>Pada tahun 2016 berdasarkan Peraturan Bupati Kabupaten Kutai Kartanegara Nomor 62 Tahun 2023, <strong>Badan Kearsipan dan Perpustakaan Kabupaten Kutai Kartanegara</strong> berubah nama menjadi <strong>Dinas Kearsipan dan Perpustakaan Kabupaten Kutai Kartanegara</strong>.</p>
        </div>
    </div>
</section>

<?php if (!$is_guest && !empty($history)): ?>
<section class="panel" aria-labelledby="history-heading">
    <header>
        <h2 id="history-heading">Riwayat Kegiatan</h2>
        <p>Catatan pengerjaan kuesioner Anda.</p>
    </header>
    <div class="panel-body panel-flush">
        <div class="table-scroll">
            <table class="data">
                <thead>
                    <tr><th>Tipe</th><th>Kegiatan</th><th class="num">Skor</th><th>Tanggal</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($history as $h): ?>
                    <tr>
                        <td><?= $h['room_id'] ? '<span class="tag tag-ok">Ruangan</span>' : '<span class="tag tag-info">Individu</span>' ?></td>
                        <td><?= e($h['room_name'] ? $h['room_name'] : $h['survey_title']) ?></td>
                        <td class="num"><?= $h['score'] ?> / <?= $h['total_questions'] ?></td>
                        <td><?= date('d M Y, H:i', strtotime($h['created_at'])) ?></td>
                        <td class="row-actions"><a class="btn-sm btn-quiet" href="result.php?survey_id=<?= $h['survey_id'] ?>&room_id=<?= $h['room_id'] ? $h['room_id'] : '0' ?>">Detail</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($leaderboard)): ?>
<section class="panel reveal" aria-labelledby="leaderboard-heading">
    <header>
        <h2 id="leaderboard-heading">Papan Peringkat</h2>
        <p>Sepuluh peserta dengan skor terbaik.</p>
    </header>
    <div class="panel-body panel-flush">
        <div class="table-scroll">
            <table class="data">
                <thead>
                    <tr><th class="num">Peringkat</th><th>Peserta</th><th class="num">Skor</th><th class="num">Persentase</th></tr>
                </thead>
                <tbody>
                <?php $rank = 1; foreach ($leaderboard as $lb): $is_me = !$is_guest && $lb['id'] == $_SESSION['user_id']; ?>
                    <tr class="<?= $is_me ? 'row-me' : '' ?>">
                        <td class="num rank"><?php
                            if ($rank === 1) { echo '<span class="rank-medal" title="Peringkat 1">&#129351;</span>'; }
                            elseif ($rank === 2) { echo '<span class="rank-medal" title="Peringkat 2">&#129352;</span>'; }
                            elseif ($rank === 3) { echo '<span class="rank-medal" title="Peringkat 3">&#129353;</span>'; }
                            else { echo $rank; }
                            $rank++;
                        ?></td>
                        <td><?= e($lb['nama']) ?> <?= $is_me ? '<span class="tag">Anda</span>' : '' ?></td>
                        <td class="num"><?= $lb['best_score'] ?></td>
                        <td class="num"><strong><?= $lb['best_pct'] ?>%</strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="panel reveal" aria-labelledby="news-heading">
    <header>
        <h2 id="news-heading">Berita & Informasi Terbaru</h2>
        <p>Dari situs resmi Diarpus Kukar.</p>
    </header>
    <div class="panel-body">
        <div id="newsContainer" class="news" role="list" aria-live="polite">
            <div class="skeleton skeleton-text" style="width: 100%;"></div>
            <div class="skeleton skeleton-text" style="width: 80%;"></div>
            <div class="skeleton skeleton-text" style="width: 60%;"></div>
        </div>
    </div>
</section>


<?php layout_footer(['base' => '..', 'scripts' => '
<script>
(function () {
    var apiUrl = "../api/news.php";
    var container = document.getElementById("newsContainer");
    if (!container) return;

    function renderSkeleton() {
        container.innerHTML = "";
        for (var i = 0; i < 3; i++) {
            var sk = document.createElement("div");
            sk.className = "skeleton skeleton-text";
            sk.style.width = (100 - i * 20) + "%";
            container.appendChild(sk);
        }
    }

    function renderError() {
        container.innerHTML = "";
        var p = document.createElement("p");
        p.className = "notice notice-info";
        p.innerHTML = "Berita tidak dapat dimuat saat ini. Kunjungi <a href=\"https://diarpus.kukarkab.go.id/\" target=\"_blank\" rel=\"noopener\">situs resmi Diarpus Kukar</a>.";
        container.appendChild(p);
    }

    function renderNews(items) {
        container.innerHTML = "";
        items.forEach(function (news) {
            var li = document.createElement("li");
            li.innerHTML = "<article>" +
                "<time datetime=\"" + news.date_iso + "\">" + news.date + "</time>" +
                "<h3>" + escapeHtml(news.title) + "</h3>" +
                "<p>" + escapeHtml(news.desc) + "...</p>" +
                "<a class=\"btn-sm btn-quiet\" href=\"" + escapeHtml(news.link) + "\" target=\"_blank\" rel=\"noopener\">Baca selengkapnya</a>" +
            "</article>";
            container.appendChild(li);
        });
    }

    function escapeHtml(text) {
        var div = document.createElement("div");
        div.textContent = text;
        return div.innerHTML;
    }

    fetch(apiUrl, { cache: "no-cache" })
        .then(function (resp) { return resp.json(); })
        .then(function (data) {
            if (data.items && data.items.length > 0) {
                renderNews(data.items);
            } else {
                renderError();
            }
        })
        .catch(function () { renderError(); });
})();
</script>
']); ?>