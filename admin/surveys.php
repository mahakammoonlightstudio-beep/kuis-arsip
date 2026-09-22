<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layout.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_survey'])) {
    csrf_verify();
    $id = (int)$_POST['survey_id'];
    $title = trim($_POST['title']);
    $desc = trim($_POST['description']);
    $type = $_POST['type'];
    $level = $_POST['level'];
    $duration = (int)$_POST['duration'];
    $max_attempts = (int)($_POST['max_attempts'] ?? 1);

    $pdo->prepare("UPDATE surveys SET title = ?, description = ?, type = ?, level = ?, duration = ?, max_attempts = ? WHERE id = ?")->execute([$title, $desc, $type, $level, $duration, $max_attempts, $id]);
    audit_log('update', 'surveys', $id, "Survey diperbarui, max_attempts=$max_attempts");
    header('Location: surveys.php?status=updated');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_survey'])) {
    csrf_verify();
    $type = $_POST['type'];
    $level = $_POST['level'];
    $duration = (int)$_POST['duration'];
    $desc = trim($_POST['description'] ?? '');
    $max_attempts = (int)($_POST['max_attempts'] ?? 1);

    $title = ucfirst($type) . " Kearsipan Level " . $level;
    if (!empty($desc)) { $title .= " (" . $desc . ")"; }

    $stmt = $pdo->prepare("INSERT INTO surveys (title, description, type, level, duration, max_attempts) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$title, $desc, $type, $level, $duration, $max_attempts]);
    audit_log('insert', 'surveys', $stmt->rowCount(), "Survey '$title' dibuat, max_attempts=$max_attempts");
    header('Location: surveys.php?status=created');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_survey_action'])) {
    csrf_verify();
    $id = (int)$_POST['toggle_survey_id'];
    $pdo->exec("UPDATE surveys SET is_active = 1 - is_active WHERE id = $id");
    audit_log('toggle', 'surveys', $id, 'Status survey diubah');
    header('Location: surveys.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_survey_action'])) {
    csrf_verify();
    $id = (int)$_POST['delete_survey_id'];
    $pdo->prepare("DELETE FROM questions WHERE survey_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM surveys WHERE id = ?")->execute([$id]);
    audit_log('delete', 'surveys', $id, 'Kuesioner dan semua soalnya dihapus');
    header('Location: surveys.php?status=deleted');
    exit;
}

$edit_data = null;
if (isset($_GET['edit'])) {
    $id_edit = (int)$_GET['edit'];
    $stmt_edit = $pdo->prepare("SELECT * FROM surveys WHERE id = ?");
    $stmt_edit->execute([$id_edit]);
    $edit_data = $stmt_edit->fetch();
}

$surveys = $pdo->query("SELECT s.*, COUNT(q.id) as total_questions FROM surveys s LEFT JOIN questions q ON s.id = q.survey_id GROUP BY s.id ORDER BY s.created_at DESC")->fetchAll();

$open_tickets = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'open'")->fetchColumn();

layout_header([
    'title'  => 'Kelola Kuesioner',
    'active' => 'surveys',
    'role'   => 'admin',
    'tickets' => $open_tickets,
]);
?>


<section class="panel">
    <header>
        <h2><?= $edit_data ? 'Edit Kuesioner' : 'Buat Kuesioner Baru' ?></h2>
    </header>
    <div class="panel-body">
        <?php if (isset($_GET['status']) && !$edit_data): ?>
            <p class="notice notice-ok">
                <?php
                if ($_GET['status'] == 'created') echo 'Kuesioner baru berhasil dibuat.';
                elseif ($_GET['status'] == 'updated') echo 'Kuesioner berhasil diperbarui.';
                else echo 'Kuesioner berhasil dihapus.';
                ?>
            </p>
        <?php endif; ?>

        <?php if ($edit_data):
            $current_max = $edit_data['max_attempts'] ?? 1;
        ?>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="survey_id" value="<?= $edit_data['id'] ?>">
                <div class="field">
                    <label class="field-label" for="title">Judul kuesioner</label>
                    <input id="title" type="text" name="title" value="<?= e($edit_data['title']) ?>" required>
                </div>
                <div class="field">
                    <label class="field-label" for="description">Keterangan singkat</label>
                    <input id="description" type="text" name="description" value="<?= e($edit_data['description']) ?>">
                </div>
                <div class="field-inline">
                    <div class="field">
                        <label class="field-label">Tipe</label>
                        <select name="type" required>
                            <option value="survey" <?= $edit_data['type'] == 'survey' ? 'selected' : '' ?>>Kuesioner</option>
                            <option value="ujian" <?= $edit_data['type'] == 'ujian' ? 'selected' : '' ?>>Ujian Resmi</option>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label">Level</label>
                        <select name="level" required>
                            <option value="Dasar" <?= $edit_data['level'] == 'Dasar' ? 'selected' : '' ?>>Dasar</option>
                            <option value="Menengah" <?= $edit_data['level'] == 'Menengah' ? 'selected' : '' ?>>Menengah</option>
                            <option value="Lanjutan" <?= $edit_data['level'] == 'Lanjutan' ? 'selected' : '' ?>>Lanjutan</option>
                            <option value="Penting" <?= $edit_data['level'] == 'Penting' ? 'selected' : '' ?>>Penting</option>
                        </select>
                    </div>
                </div>
                <div class="field-inline">
                    <div class="field"><label class="field-label">Durasi (menit)</label><input type="number" name="duration" value="<?= (int)$edit_data['duration'] ?>" min="1" required></div>
                    <div class="field"><label class="field-label">Batas pengulangan</label><input type="number" name="max_attempts" value="<?= $current_max ?>" min="-1" required></div>
                </div>
                <div class="btn-row">
                    <button type="submit" name="update_survey" class="btn">Simpan perubahan</button>
                    <a class="btn-sm btn-quiet" href="surveys.php">Batal</a>
                </div>
            </form>
        <?php else: ?>
            <form method="post">
                <?= csrf_field() ?>
                <div class="field-inline">
                    <div class="field">
                        <label class="field-label">Tipe</label>
                        <select name="type" required>
                            <option value="survey">Kuesioner</option>
                            <option value="ujian">Ujian Resmi</option>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label">Level</label>
                        <select name="level" required>
                            <option value="Dasar">Dasar</option>
                            <option value="Menengah">Menengah</option>
                            <option value="Lanjutan">Lanjutan</option>
                            <option value="Penting">Penting</option>
                        </select>
                    </div>
                </div>
                <div class="field">
                    <label class="field-label" for="description">Keterangan singkat (opsional)</label>
                    <input id="description" type="text" name="description" placeholder="Untuk Batch PKL Sept 2024">
                </div>
                <div class="field-inline">
                    <div class="field"><label class="field-label">Durasi (menit)</label><input type="number" name="duration" value="10" min="1" required></div>
                    <div class="field"><label class="field-label">Batas pengulangan</label><input type="number" name="max_attempts" value="1" min="-1" required></div>
                </div>
                <div class="btn-row">
                    <button type="submit" name="create_survey" class="btn">Buat kuesioner</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>

<section class="panel">
    <header>
        <h2>Daftar Kuesioner</h2>
    </header>
    <div class="panel-body panel-flush">
        <div class="table-scroll">
            <table class="data">
                <thead>
                    <tr><th>Judul</th><th>Tipe</th><th>Level</th><th class="num">Durasi</th><th>Batas</th><th class="num">Soal</th><th>Status</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                <?php foreach ($surveys as $s): ?>
                    <tr>
                        <td>
                            <strong><?= e($s['title']) ?></strong><br>
                            <span class="muted"><?= e($s['description']) ?></span>
                        </td>
                        <td><?= $s['type'] == 'ujian' ? '<span class="tag tag-warn">Ujian</span>' : '<span class="tag tag-info">Kuesioner</span>' ?></td>
                        <td><span class="tag"><?= e($s['level']) ?></span></td>
                        <td class="num"><?= (int)$s['duration'] ?> mnt</td>
                        <td>
                            <?php $ma = $s['max_attempts'] ?? 1; ?>
                            <?php if ($ma < 0): ?><span class="tag tag-ok">Tak terbatas</span>
                            <?php elseif ($ma === 1): ?><span class="tag">1x</span>
                            <?php else: ?><span class="tag"><?= $ma ?>x</span><?php endif; ?>
                        </td>
                        <td class="num"><?= $s['total_questions'] ?></td>
                        <td><?= $s['is_active'] ? '<span class="tag tag-ok">Aktif</span>' : '<span class="tag tag-error">Nonaktif</span>' ?></td>
                        <td class="row-actions">
                            <a class="btn-sm" href="questions.php?survey_id=<?= $s['id'] ?>">Kelola soal</a>
                            <a class="btn-sm btn-quiet" href="surveys.php?edit=<?= $s['id'] ?>">Edit</a>
                            <form method="post" class="form-inline" onsubmit="return confirm('Ubah status kuesioner ini?')">
                                <?= csrf_field() ?><input type="hidden" name="toggle_survey_action" value="1"><input type="hidden" name="toggle_survey_id" value="<?= $s['id'] ?>">
                                <button type="submit" class="btn-sm btn-quiet"><?= $s['is_active'] ? 'Tutup' : 'Buka' ?></button>
                            </form>
                            <form method="post" class="form-inline" onsubmit="return confirm('Hapus kuesioner ini? Semua soal dan nilai di dalamnya akan terhapus.')">
                                <?= csrf_field() ?><input type="hidden" name="delete_survey_action" value="1"><input type="hidden" name="delete_survey_id" value="<?= $s['id'] ?>">
                                <button type="submit" class="btn-sm btn-danger">Hapus</button>
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
