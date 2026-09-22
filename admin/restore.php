<?php
/**
 * Kuis Arsip (htdocs) - admin/restore.php
 * RESTORE DATABASE (dengan pengaman berlapis):
 *  1. Wajib mengetik kata RESTORE untuk mengonfirmasi
 *  2. Snapshot keamanan otomatis dibuat SEBELUM restore dijalankan
 *  3. Sumber restore: berkas backup di folder backups/ ATAU upload .sql
 *  4. Progress ditampilkan per tabel (SET autocommit, one statement at a time)
 *
 * Dump yang didukung: hasil backup.php / dump phpMyAdmin standar
 * (DROP TABLE IF EXISTS + CREATE TABLE + INSERT) dari 9 tabel sistem.
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
} catch (Throwable $e) { /* default */ }

$msg = '';
$err = '';
$log = [];          // jejak eksekusi per statement penting
$done  = false;

// ---------------------------------------------------------------- RESTORE (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_restore'])) {
    csrf_verify();

    $confirm = trim($_POST['confirm_text'] ?? '');
    $source  = ($_POST['source'] ?? 'server') === 'upload' ? 'upload' : 'server';

    if ($confirm !== 'RESTORE') {
        $err = 'Konfirmasi salah. Ketik persis kata RESTORE pada kotak konfirmasi.';
    } else {
        // --- Ambil isi SQL dari sumber terpilih
        $sql = '';
        $sqlName = '';
        if ($source === 'server') {
            $file = (string)($_POST['file'] ?? '');
            $base = basename($file);
            if (preg_match('/^backup_diarpus_[0-9]{8}_[0-9]{6}(_data)?\.sql$/', $base) && is_file(HQ_BACKUP_DIR . '/' . $base)) {
                $sql = (string)@file_get_contents(HQ_BACKUP_DIR . '/' . $base);
                $sqlName = $base;
            } else {
                $err = 'Berkas backup tidak valid atau tidak ditemukan.';
            }
        } else {
            if (isset($_FILES['sqlfile']) && is_uploaded_file($_FILES['sqlfile']['tmp_name'] ?? '')) {
                $up = $_FILES['sqlfile'];
                if (($up['size'] ?? 0) > 12 * 1024 * 1024) {
                    $err = 'Ukuran berkas melebihi 12 MB. Gunakan mode CLI/phpMyAdmin untuk dump besar.';
                } elseif (!preg_match('/\.sql$/i', (string)$up['name'])) {
                    $err = 'Berkas harus berekstensi .sql.';
                } else {
                    $sql = (string)@file_get_contents($up['tmp_name']);
                    $sqlName = (string)$up['name'];
                }
            } else {
                $err = 'Upload berkas .sql terlebih dahulu.';
            }
        }

        if ($err === '' && trim($sql) === '') {
            $err = 'Isi berkas SQL kosong atau tidak dapat dibaca.';
        }

        // --- Validasi minimal: harus tampak seperti dump sistem ini
        if ($err === '' && stripos($sql, 'CREATE TABLE') === false) {
            $err = 'Berkas tidak tampak seperti dump SQL yang valid (tidak ada CREATE TABLE).';
        }

        // --- 1) SNAPSHOT KEAMANAN sebelum mengubah apa pun (hanya bila input valid)
        if ($err === '') {
            $snapshot = hq_backup_save($pdo, $siteName . ' (pra-restore)', false);
        }
        if ($err !== '') {
            // ada error dari validasi di atas
        } elseif (!isset($snapshot) || $snapshot === null) {
            $err = 'Snapshot keamanan pra-restore GAGAL dibuat. Restore dibatalkan untuk keamanan data. Periksa folder backups/.';
        } else {
            // --- 2) Jalankan restore
            try {
                $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->exec("SET SESSION sql_mode='NO_AUTO_VALUE_ON_ZERO'");

                $stmts = hq_restore_statements($sql);
                $tablesTouched = [];
                $nInsert = 0;
                $nDrop = 0;
                $nCreate = 0;

                foreach ($stmts as $st) {
                    $head = strtoupper(substr(ltrim($st), 0, 12));
                    try {
                        $pdo->exec($st);
                        if (str_starts_with($head, 'DROP TABLE')) {
                            $nDrop++;
                            if (preg_match('/DROP TABLE IF EXISTS\s+`?([a-zA-Z0-9_]+)`?/i', $st, $m)) {
                                $tablesTouched[$m[1]] = true;
                            }
                        } elseif (str_starts_with($head, 'CREATE TAB')) {
                            $nCreate++;
                        } elseif (str_starts_with($head, 'INSERT INTO')) {
                            $nInsert++;
                            if (preg_match('/INSERT INTO\s+`?([a-zA-Z0-9_]+)`?/i', $st, $m) && !isset($tablesTouched[$m[1]])) {
                                $tablesTouched[$m[1]] = true;
                            }
                        }
                    } catch (Throwable $inner) {
                        // Statement tunggal gagal: lanjut, tapi catat (mis. tabel asing dalam dump)
                        $log[] = ['ok' => false, 'sql' => mb_substr($st, 0, 90), 'err' => $inner->getMessage()];
                    }
                }

                $done = true;
                hq_backup_clear_error();
                audit_log('restore', 'database', 0, "Restore dari {$sqlName}; snapshot: {$snapshot}; DROP={$nDrop}, CREATE={$nCreate}, INSERT={$nInsert}");

                $msg = "Restore selesai dari <code>" . e($sqlName) . "</code> — snapshot keamanan: <code>" . e($snapshot) . "</code>. "
                     . "Tabel tersentuh: " . e(implode(', ', array_keys($tablesTouched) ?: ['-'])) . ". "
                     . "Periksa data & lakukan logout-login ulang agar sesi segar.";
                if ($log) {
                    $msg .= ' (' . count($log) . ' statement dilewati karena error — lihat catatan di bawah.)';
                }
            } catch (Throwable $e) {
                $err = 'Restore gagal: ' . $e->getMessage() . ' — snapshot keamanan ' . $snapshot . ' masih tersedia untuk dipulihkan.';
                error_log('[KuisArsip] Restore gagal: ' . $e->getMessage());
            } finally {
                $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
                // Kembalikan sql_mode standar aplikasi (koneksi PDO persistent → mode sesi menempel)
                try {
                    $pdo->exec("SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
                } catch (Throwable $e) { /* abaikan */ }
            }
        }
    }
}

$backups = hq_backup_list();
$openTickets = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'open'")->fetchColumn();

layout_header([
    'title'  => 'Restore Database',
    'active' => 'backup',
    'role'   => 'admin',
    'tickets' => $openTickets,
]);
?>

<?php if ($msg): ?><p class="notice notice-ok" role="status"><?= $msg ?></p><?php endif; ?>
<?php if ($err): ?><p class="notice notice-error" role="alert"><?= e($err) ?></p><?php endif; ?>

<?php if (!$done): ?>
<section class="panel" style="border-color: #b3261e;">
    <header>
        <h2>⚠️ Restore Database — Operasi Berisiko</h2>
        <p class="muted">Restore <strong>menghapus dan menimpa</strong> isi tabel dengan data dari berkas SQL. Data yang belum dibackup akan hilang permanen.</p>
    </header>
    <div class="panel-body">
        <ol style="line-height: 1.8;">
            <li>Snapshot keamanan <strong>otomatis dibuat</strong> sebelum restore dijalankan (disimpan di riwayat Backup).</li>
            <li>Pilih sumber: berkas dari <strong>Riwayat Backup</strong> atau <strong>upload</strong> berkas .sql (maks. 12 MB).</li>
            <li>Ketik <code>RESTORE</code> pada kotak konfirmasi untuk membuka tombol eksekusi.</li>
        </ol>

        <form method="post" enctype="multipart/form-data" id="restoreForm">
            <?= csrf_field() ?>
            <input type="hidden" name="do_restore" value="1">

            <div class="field">
                <label class="field-label" for="source">Sumber restore</label>
                <select id="source" name="source" onchange="toggleSource()">
                    <option value="server">Dari Riwayat Backup di server (<?= count($backups) ?> berkas)</option>
                    <option value="upload">Upload berkas .sql</option>
                </select>
            </div>

            <div class="field" id="row-server">
                <label class="field-label" for="file">Pilih berkas backup</label>
                <select id="file" name="file">
                    <?php if (empty($backups)): ?>
                        <option value="">— Belum ada backup di server —</option>
                    <?php else: foreach ($backups as $b): ?>
                        <option value="<?= e($b['file']) ?>">
                            <?= e($b['file']) ?> (<?= number_format($b['size'] / 1024, 1) ?> KB, <?= date('d M H:i', $b['mtime']) ?>)
                        </option>
                    <?php endforeach; endif; ?>
                </select>
                <?php if (empty($backups)): ?>
                    <p class="muted">Belum ada backup tersimpan — buat dulu di menu <a href="backup.php">Backup</a>.</p>
                <?php endif; ?>
            </div>

            <div class="field" id="row-upload" style="display:none;">
                <label class="field-label" for="sqlfile">Berkas .sql (maks. 12 MB)</label>
                <input id="sqlfile" type="file" name="sqlfile" accept=".sql,text/plain">
            </div>

            <div class="field">
                <label class="field-label" for="confirm_text">Konfirmasi: ketik <code>RESTORE</code> (huruf besar semua)</label>
                <input id="confirm_text" type="text" name="confirm_text" autocomplete="off" placeholder="RESTORE" required
                       oninput="document.getElementById('btnRestore').disabled = (this.value.trim() !== 'RESTORE');">
            </div>

            <div class="btn-row">
                <button id="btnRestore" type="submit" class="btn btn-danger" disabled
                        onclick="return confirm('YAKIN? Semua tabel yang ada di berkas SQL akan DITIMPA. Snapshot keamanan akan dibuat otomatis sebelum restore.')">
                    🔄 Jalankan Restore
                </button>
                <a class="btn-sm btn-quiet" href="backup.php">← Kembali ke Backup</a>
            </div>
        </form>
    </div>
</section>

<script>
function toggleSource() {
    var v = document.getElementById('source').value;
    document.getElementById('row-server').style.display = (v === 'server') ? '' : 'none';
    document.getElementById('row-upload').style.display = (v === 'upload') ? '' : 'none';
}
</script>
<?php endif; ?>

<?php if ($done && !empty($log)): ?>
<section class="panel">
    <header>
        <h2>Catatan Statement yang Dilewati</h2>
        <p class="muted">Statement ini gagal saat restore namun proses dilanjutkan (biasanya tabel di luar skema sistem).</p>
    </header>
    <div class="panel-body">
        <table class="data">
            <thead><tr><th>Statement</th><th>Error</th></tr></thead>
            <tbody>
            <?php foreach ($log as $l): ?>
                <tr><td><code><?= e($l['sql']) ?>…</code></td><td><?= e($l['err']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<section class="panel">
    <header>
        <h2>Bagaimana Restore Bekerja</h2>
    </header>
    <div class="panel-body">
        <ul style="line-height: 1.9;">
            <li>Isi berkas dipecah per statement dengan parser yang memahami tanda <code>;</code> dan kutip <strong>di dalam string data</strong> (aman untuk data peserta yang mengandung tanda baca).</li>
            <li>Urutan dump standar dihormati: <code>DROP TABLE IF EXISTS</code> → <code>CREATE TABLE</code> → <code>INSERT</code> — sama seperti dump phpMyAdmin.</li>
            <li>Mode SQL disetel <code>NO_AUTO_VALUE_ON_ZERO</code> agar ID <code>0</code> pada dump tidak berubah.</li>
            <li>Backup "data asli saja" (tanpa <code>admin_logs</code>) valid untuk restore — tabel <code>admin_logs</code> tidak akan disentuh.</li>
        </ul>
    </div>
</section>

<?php layout_footer(); ?>
