<?php
/**
 * Kuis Arsip (htdocs) - admin/backup.php
 * Pusat Backup Database:
 *  - Download dump SQL (lengkap / data asli saja untuk mengisi database.sql)
 *  - Simpan backup ke folder backups/ di server (dilindungi .htaccess)
 *  - Backup otomatis harian berjalan dari dasbor (tanpa cron)
 *  - Riwayat backup tersimpan + hapus manual
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/backup.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$siteName = 'Kuis Arsip - Diarpus Kukar';
try {
    $row = $pdo->query("SELECT site_name FROM app_settings WHERE id = 1 LIMIT 1")->fetch();
    if ($row && !empty($row['site_name'])) $siteName = (string)$row['site_name'];
} catch (Throwable $e) { /* pakai nama default */ }

$msg = '';
$err = '';

// ---------------------------------------------------------------- ACTION: POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $act = $_POST['action'] ?? '';

    if ($act === 'save' || $act === 'save_data') {
        $onlyData = ($act === 'save_data');
        $name = hq_backup_save($pdo, $siteName, $onlyData);
        if ($name !== null) {
            audit_log('backup', 'database', 0, 'Backup disimpan: ' . $name);
            $msg = $onlyData
                ? "Backup \"data asli saja\" tersimpan: {$name}"
                : "Backup lengkap tersimpan: {$name}";
        } else {
            $err = 'Gagal menyimpan backup ke folder backups/. Periksa izin tulis folder.';
        }
    } elseif ($act === 'delete') {
        $file = (string)($_POST['file'] ?? '');
        if (hq_backup_delete($file)) {
            audit_log('delete', 'backup', 0, 'Hapus backup: ' . $file);
            $msg = "Backup {$file} dihapus.";
        } else {
            $err = 'Gagal menghapus backup (file tidak dikenal).';
        }
    } elseif ($act === 'download_saved') {
        $file = (string)($_POST['file'] ?? '');
        $base = basename($file);
        if (preg_match('/^backup_diarpus_[0-9]{8}_[0-9]{6}(_data)?\.sql$/', $base) && is_file(HQ_BACKUP_DIR . '/' . $base)) {
            header('Content-Type: application/sql; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $base . '"');
            header('Content-Length: ' . (int)filesize(HQ_BACKUP_DIR . '/' . $base));
            header('Cache-Control: no-store');
            readfile(HQ_BACKUP_DIR . '/' . $base);
            exit;
        }
        $err = 'File backup tidak ditemukan.';
    }
}

// ---------------------------------------------------------------- ACTION: GET download langsung
if (isset($_GET['download'])) {
    $onlyData = ($_GET['download'] === 'data');
    audit_log('backup', 'database', 0, $onlyData ? 'Download backup data asli' : 'Download backup lengkap');
    $sql = hq_backup_dump($pdo, $onlyData, $siteName);
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="backup_diarpus_' . date('Ymd_His') . ($onlyData ? '_data' : '') . '.sql"');
    header('Cache-Control: no-store');
    echo $sql;
    exit;
}

$backups = hq_backup_list();
$totalSize = array_sum(array_column($backups, 'size'));

layout_header([
    'title'  => 'Backup Database',
    'active' => 'backup',
    'role'   => 'admin',
    'tickets' => (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'open'")->fetchColumn(),
]);
?>

<?php if ($msg): ?><p class="notice notice-ok" role="status"><?= e($msg) ?></p><?php endif; ?>
<?php if ($err): ?><p class="notice notice-error" role="alert"><?= e($err) ?></p><?php endif; ?>

<section class="panel">
    <header>
        <h2>Backup Database</h2>
        <p class="muted">Dump SQL berisi struktur + data, siap diimport ulang via phpMyAdmin.</p>
    </header>
    <div class="panel-body">
        <div class="cols-2" style="align-items: stretch;">
            <div>
                <h3 style="margin-top:0;">Backup Lengkap</h3>
                <p class="muted">Semua tabel beserta seluruh isinya (termasuk log audit). Dipakai untuk pemulihan penuh.</p>
                <div class="btn-row">
                    <a class="btn" href="backup.php?download=full" onclick="return confirm('Unduh backup lengkap sekarang?')">⬇ Unduh SQL Lengkap</a>
                    <form method="post" class="form-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save">
                        <button type="submit" class="btn-sm btn-quiet">💾 Simpan ke server</button>
                    </form>
                </div>
            </div>
            <div>
                <h3 style="margin-top:0;">Data Asli Saja</h3>
                <p class="muted">Struktur semua tabel, data hanya tabel isian (tanpa <code>admin_logs</code>). Cocok untuk memperbarui isi <code>database.sql</code> di repositori.</p>
                <div class="btn-row">
                    <a class="btn" href="backup.php?download=data" onclick="return confirm('Unduh backup data asli sekarang?')">⬇ Unduh Data Asli</a>
                    <form method="post" class="form-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_data">
                        <button type="submit" class="btn-sm btn-quiet">💾 Simpan ke server</button>
                    </form>
                </div>
            </div>
        </div>
        <p class="muted" style="margin-bottom:0;">
            <strong>Backup otomatis:</strong> setiap hari (maks. 1× per hari) sistem menyimpan backup lengkap ke folder
            <code>backups/</code> secara otomatis saat dasbor dibuka admin — tanpa perlu cron. Maksimal 14 backup terakhir disimpan.
        </p>
        <p class="muted" style="margin-bottom:0;">
            Butuh memulihkan data dari berkas backup? Gunakan halaman
            <a href="restore.php"><strong>Restore Database</strong></a> — dilengkapi snapshot keamanan otomatis.
        </p>
    </div>
</section>

<section class="panel">
    <header>
        <h2>Riwayat Backup di Server</h2>
        <p class="muted"><?= count($backups) ?> berkas &middot; total <?= number_format($totalSize / 1024, 1) ?> KB</p>
    </header>
    <div class="panel-flush">
        <div class="table-scroll">
            <table class="data">
                <thead>
                    <tr><th>Berkas</th><th class="num">Ukuran</th><th>Waktu dibuat</th><th></th><th></th></tr>
                </thead>
                <tbody>
                <?php if (empty($backups)): ?>
                    <tr><td colspan="5" class="empty">Belum ada backup tersimpan. Klik "Simpan ke server" di atas, atau buka dasbor besok untuk backup otomatis pertama.</td></tr>
                <?php else: foreach ($backups as $b): ?>
                    <tr>
                        <td><code><?= e($b['file']) ?></code><?= str_ends_with($b['file'], '_data.sql') ? ' <span class="tag tag-info">data asli</span>' : '' ?></td>
                        <td class="num"><?= number_format($b['size'] / 1024, 1) ?> KB</td>
                        <td><?= date('d M Y, H:i', $b['mtime']) ?> WITA</td>
                        <td class="row-actions">
                            <form method="post" class="form-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="download_saved">
                                <input type="hidden" name="file" value="<?= e($b['file']) ?>">
                                <button type="submit" class="btn-sm btn-quiet">Unduh</button>
                            </form>
                        </td>
                        <td class="row-actions">
                            <form method="post" class="form-inline" onsubmit="return confirm('Hapus backup ini?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="file" value="<?= e($b['file']) ?>">
                                <button type="submit" class="btn-sm btn-danger">Hapus</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php layout_footer(); ?>
