<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layout.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_room'])) {
    csrf_verify();
    $name = $_POST['room_name'];
    $max = (int)$_POST['max_participants'];
    $survey_id = (int)$_POST['survey_id'];
    $start_time = !empty($_POST['start_time']) ? $_POST['start_time'] : null;
    $end_time   = !empty($_POST['end_time'])   ? $_POST['end_time']   : null;

    do {
        $code = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6));
        $check_code = $pdo->prepare("SELECT id FROM rooms WHERE room_code = ?");
        $check_code->execute([$code]);
    } while ($check_code->fetch());

    $stmt = $pdo->prepare("INSERT INTO rooms (room_name, room_code, max_participants, survey_id, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $code, $max, $survey_id, $start_time, $end_time]);
    audit_log('insert', 'rooms', $stmt->rowCount(), "Ruangan '$name' dibuat");
    header('Location: rooms.php?status=created');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_room_action'])) {
    csrf_verify();
    $id = (int)$_POST['toggle_room_id'];
    $pdo->prepare("UPDATE rooms SET is_active = 1 - is_active WHERE id = ?")->execute([$id]);
    audit_log('toggle', 'rooms', $id, 'Status ruangan diubah');
    header('Location: rooms.php');
    exit;
}

$rooms = $pdo->query("
    SELECT r.*, s.title as survey_title, COUNT(res.id) as finished_count
    FROM rooms r
    LEFT JOIN surveys s ON r.survey_id = s.id
    LEFT JOIN results res ON r.id = res.room_id
    GROUP BY r.id
    ORDER BY r.created_at DESC
")->fetchAll();

$surveys_list = $pdo->query("SELECT * FROM surveys WHERE is_active = 1 ORDER BY created_at DESC")->fetchAll();

$open_tickets = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'open'")->fetchColumn();

layout_header([
    'title'  => 'Kelola Ruangan',
    'active' => 'rooms',
    'role'   => 'admin',
    'tickets' => $open_tickets,
]);
?>


<section class="panel">
    <header>
        <h2>Buat Ruangan Baru</h2>
        <p>Ruangan berisi kode akses unik bagi peserta.</p>
    </header>
    <div class="panel-body">
        <form method="post">
            <?= csrf_field() ?>
            <div class="field">
                <label class="field-label" for="survey_id">Kuesioner</label>
                <select id="survey_id" name="survey_id" required>
                    <option value="">-- Pilih Kuesioner --</option>
                    <?php foreach ($surveys_list as $sl): ?>
                        <option value="<?= $sl['id'] ?>"><?= e($sl['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field-label" for="room_name">Nama ruangan / sesi</label>
                <input id="room_name" type="text" name="room_name" placeholder="Ulangan Batch 2" required>
            </div>
            <div class="field-inline">
                <div class="field"><label class="field-label">Kapasitas</label><input type="number" name="max_participants" value="40" required></div>
                <div class="field"><label class="field-label">Jadwal mulai</label><input type="datetime-local" name="start_time"></div>
                <div class="field"><label class="field-label">Jadwal selesai</label><input type="datetime-local" name="end_time"></div>
            </div>
            <div class="btn-row">
                <button type="submit" name="create_room" class="btn">Buat ruangan</button>
            </div>
        </form>
    </div>
</section>

<section class="panel">
    <header>
        <h2>Daftar Ruangan</h2>
    </header>
    <div class="panel-body panel-flush">
        <div class="table-scroll">
            <table class="data">
                <thead>
                    <tr><th>Nama</th><th>Kuesioner</th><th>Kode</th><th>Kapasitas</th><th>Status</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                <?php foreach ($rooms as $r): ?>
                    <tr>
                        <td><?= e($r['room_name']) ?></td>
                        <td><?= e($r['survey_title']) ?></td>
                        <td><span class="room-code"><?= e($r['room_code']) ?><button type="button" class="room-code-copy" data-copy="<?= e($r['room_code']) ?>" aria-label="Salin kode <?= e($r['room_code']) ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg></button></span></td>
                        <td><?= $r['finished_count'] ?> / <?= $r['max_participants'] ?></td>
                        <td>
                            <?php if ($r['is_active']):
                                $now = time();
                                $start_ok = (!$r['start_time'] || strtotime($r['start_time']) <= $now);
                                $end_ok   = (!$r['end_time']   || strtotime($r['end_time'])   >= $now);
                                $time_valid = $start_ok && $end_ok;
                            ?>
                                <span class="tag <?= $time_valid ? 'tag-ok' : 'tag-warn' ?>"><?= $time_valid ? 'Aktif' : 'Di luar jadwal' ?></span>
                            <?php else: ?>
                                <span class="tag tag-error">Ditutup</span>
                            <?php endif; ?>
                        </td>
                        <td class="row-actions">
                            <form method="post" class="form-inline" onsubmit="return confirm('Ubah status ruangan?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="toggle_room_action" value="1">
                                <input type="hidden" name="toggle_room_id" value="<?= $r['id'] ?>">
                                <button type="submit" class="btn-sm <?= $r['is_active'] ? 'btn-danger' : '' ?>"><?= $r['is_active'] ? 'Tutup' : 'Buka' ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>


<?php layout_footer(['base' => '..']); ?>
