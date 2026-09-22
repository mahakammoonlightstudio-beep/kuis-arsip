<?php
/**
 * Kuis Arsip (htdocs) - includes/backup.php
 * Helper backup database:
 *  - hq_backup_dump()        : buat dump SQL lengkap (struktur + data)
 *  - hq_backup_save()        : simpan dump ke folder backups/ di server
 *  - hq_backup_list()        : daftar file backup tersimpan
 *  - hq_backup_delete()      : hapus file backup
 *  - hq_backup_maybe_auto()  : auto-backup harian (lazy cron, dipanggil dasbor)
 *
 * Catatan InfinityFree: tidak ada cron → backup otomatis dipicu kunjungan
 * admin (maks. 1x per hari). Folder backups/ dilindungi .htaccess.
 */

if (!defined('HQ_BACKUP_DIR')) {
    define('HQ_BACKUP_DIR', __DIR__ . '/../backups');
}

/** Tabel yang ikut dibackup (sesuai phpMyAdmin live). */
function hq_backup_tables(): array
{
    return ['users', 'app_settings', 'surveys', 'questions', 'rooms', 'results', 'feedback', 'tickets', 'admin_logs'];
}

/** Tabel yang dilompati pada backup "data asli saja" (bukan input pengguna). */
function hq_backup_non_user_tables(): array
{
    return ['admin_logs'];
}

/** Escape satu nilai untuk INSERT SQL. */
function hq_backup_quote_value(PDO $pdo, mixed $v): string
{
    if ($v === null) return 'NULL';
    if (is_int($v) || is_float($v)) return (string)$v;
    return $pdo->quote((string)$v);
}

/**
 * Buat dump SQL lengkap.
 * $onlyUserTables = true → struktur semua tabel, data hanya tabel user
 * (users, surveys, questions, rooms, results, feedback, tickets, app_settings)
 * → cocok ditempel ke database.sql / diimport ulang tanpa audit log.
 */
function hq_backup_dump(PDO $pdo, bool $onlyUserTables = false, string $siteName = 'Kuis Arsip'): string
{
    $skip = $onlyUserTables ? array_flip(hq_backup_non_user_tables()) : [];
    $out  = "-- ============================================================\n";
    $out .= "-- Backup Database - {$siteName}\n";
    $out .= '-- Dinas Kearsipan dan Perpustakaan Kab. Kutai Kartanegara' . "\n";
    $out .= '-- Dibuat   : ' . date('Y-m-d H:i:s') . ' WITA' . "\n";
    $out .= '-- Mode     : ' . ($onlyUserTables ? 'data asli saja (tanpa admin_logs)' : 'lengkap') . "\n";
    $out .= "-- Engine   : MyISAM (latin1) + admin_logs InnoDB, sesuai server live\n";
    $out .= "-- Import   : phpMyAdmin > pilih database > tab Import\n";
    $out .= "-- ============================================================\n\n";
    // PENTING: file dump tersimpan sebagai UTF-8 (koneksi PDO utf8mb4).
    // Import harus dibuka dengan SET NAMES utf8mb4 agar MySQL mengonversi
    // otomatis utf8mb4 -> latin1 ke kolom tabel. SET NAMES latin1 di sini
    // justru merusak karakter non-ASCII saat re-import.
    $out .= "SET NAMES utf8mb4;\n\n";

    foreach (hq_backup_tables() as $table) {
        $t = $table;
        $out .= "-- ----------------------------\n-- {$t}\n-- ----------------------------\n";
        $out .= "DROP TABLE IF EXISTS `{$t}`;\n";

        // Struktur: bersihkan DEFINER/AUTO_INCREMENT bila ada
        $row = $pdo->query("SHOW CREATE TABLE `{$t}`")->fetch(PDO::FETCH_ASSOC);
        $create = (string)($row['Create Table'] ?? '');
        if ($create === '') {
            $out .= "-- (tabel tidak ditemukan, dilewati)\n\n";
            continue;
        }
        $create = preg_replace('/DEFINER=`[^`]+`@`[^`]+` /i', '', $create);
        $create = preg_replace('/AUTO_INCREMENT=\d+\s*/i', '', $create);
        $out .= $create . ";\n\n";

        if (isset($skip[$t])) {
            $out .= "-- (data dilompati pada mode 'data asli saja')\n\n";
            continue;
        }

        // Data: INSERT multi-baris per 50 record (hemat ukuran & aman limit query)
        $rows = $pdo->query("SELECT * FROM `{$t}`")->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            $out .= "-- (0 baris)\n\n";
            continue;
        }
        $cols = array_map(fn($c) => '`' . $c . '`', array_keys($rows[0]));
        $colList = implode(', ', $cols);
        $out .= "INSERT INTO `{$t}` ({$colList}) VALUES\n";
        $chunks = array_chunk($rows, 50);
        $sqlLines = [];
        foreach ($chunks as $chunk) {
            $values = [];
            foreach ($chunk as $r) {
                $values[] = '(' . implode(', ', array_map(fn($v) => hq_backup_quote_value($pdo, $v), array_values($r))) . ')';
            }
            $sqlLines[] = implode(",\n", $values) . ';';
        }
        $out .= implode("\n", $sqlLines) . "\n\n";
    }

    $out .= "-- Selesai. Backup dibuat otomatis oleh sistem Kuis Arsip.\n";
    return $out;
}

/** Pastikan folder backups/ ada + terlindungi (.htaccess & index.html). */
function hq_backup_ensure_dir(): bool
{
    if (!is_dir(HQ_BACKUP_DIR) && !@mkdir(HQ_BACKUP_DIR, 0755, true)) {
        return false;
    }
    $ht = HQ_BACKUP_DIR . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "Order deny,allow\nDeny from all\n");
    }
    $idx = HQ_BACKUP_DIR . '/index.html';
    if (!is_file($idx)) {
        @file_put_contents($idx, 'Forbidden');
    }
    return is_dir(HQ_BACKUP_DIR) && is_writable(HQ_BACKUP_DIR);
}

/** Simpan dump ke backups/<nama>.sql. Mengembalikan nama file atau null. */
function hq_backup_save(PDO $pdo, string $siteName = 'Kuis Arsip', bool $onlyUserTables = false): ?string
{
    if (!hq_backup_ensure_dir()) return null;
    $sql = hq_backup_dump($pdo, $onlyUserTables, $siteName);
    $name = 'backup_diarpus_' . date('Ymd_His') . ($onlyUserTables ? '_data' : '') . '.sql';
    if (@file_put_contents(HQ_BACKUP_DIR . '/' . $name, $sql) === false) {
        return null;
    }
    hq_backup_prune(14);
    return $name;
}

/** Hapus backup terlama, sisakan $keep file (retensi). */
function hq_backup_prune(int $keep = 14): void
{
    $files = glob(HQ_BACKUP_DIR . '/backup_diarpus_*.sql');
    if (!is_array($files) || count($files) <= $keep) return;
    sort($files); // nama mengandung timestamp → urut = terlama dulu
    foreach (array_slice($files, 0, count($files) - $keep) as $old) {
        @unlink($old);
    }
}

/** Daftar file backup tersimpan (terbaru dulu). */
function hq_backup_list(): array
{
    if (!is_dir(HQ_BACKUP_DIR)) return [];
    $files = glob(HQ_BACKUP_DIR . '/backup_diarpus_*.sql') ?: [];
    rsort($files);
    $out = [];
    foreach ($files as $f) {
        $out[] = [
            'file' => basename($f),
            'size' => (int)@filesize($f),
            'mtime' => (int)@filemtime($f),
        ];
    }
    return $out;
}

/** Hapus satu file backup (anti path traversal). */
function hq_backup_delete(string $file): bool
{
    $base = basename($file);
    if (!preg_match('/^backup_diarpus_[0-9]{8}_[0-9]{6}(_data)?\.sql$/', $base)) return false;
    $path = HQ_BACKUP_DIR . '/' . $base;
    return is_file($path) && @unlink($path);
}

/**
 * Auto-backup harian (lazy cron): hanya jalan bila backup terakhir > 20 jam.
 * Dipanggil dari admin/index.php. Gagal backup TIDAK boleh mengganggu dasbor.
 */
function hq_backup_maybe_auto(PDO $pdo, string $siteName = 'Kuis Arsip'): ?string
{
    try {
        if (!hq_backup_ensure_dir()) {
            hq_backup_record_error($pdo, 'auto-backup', 'Folder backups/ tidak ada atau tidak dapat ditulis.');
            return null;
        }
        $last = glob(HQ_BACKUP_DIR . '/backup_diarpus_*.sql');
        if (is_array($last) && $last) {
            $newest = max(array_map('filemtime', $last));
            if ((time() - $newest) < 20 * 3600) return null; // belum waktunya
        }
        $name = hq_backup_save($pdo, $siteName, false);
        if ($name === null) {
            hq_backup_record_error($pdo, 'auto-backup', 'Gagal menulis berkas backup ke folder backups/. Periksa kuota/izin tulis hosting.');
            return null;
        }
        hq_backup_clear_error(); // sukses → hapus penanda error lama
        return $name;
    } catch (Throwable $e) {
        error_log('[KuisArsip] Auto-backup gagal: ' . $e->getMessage());
        hq_backup_record_error($pdo, 'auto-backup', $e->getMessage());
        return null;
    }
}

// ============================================================
// NOTIFIKASI GAGAL BACKUP
// Saat auto-backup gagal: simpan penanda error (marker) agar dasbor
// menampilkan banner, catat ke admin_logs, dan kirim notifikasi (inbox
// lonceng) ke semua admin. Semua di try-catch — tidak pernah melempar.
// ============================================================

function hq_backup_admin_ids(PDO $pdo): array
{
    try {
        return $pdo->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function hq_backup_record_error(PDO $pdo, string $stage, string $message): void
{
    $message = mb_substr((string)$message, 0, 400);
    @file_put_contents(HQ_BACKUP_DIR . '/last_error.json', json_encode([
        'time' => time(), 'stage' => $stage, 'message' => $message,
    ], JSON_UNESCAPED_UNICODE) ?: '');

    try {
        $st = $pdo->prepare(
            "INSERT INTO admin_logs (admin_id, admin_nama, action, target_table, target_id, notes, ip_address, user_agent, created_at)
             VALUES (NULL, 'system', 'backup_failed', 'database', '', ?, NULL, NULL, NOW())"
        );
        $st->execute([substr($stage . ': ' . $message, 0, 500)]);

        $judul = 'Backup database GAGAL';
        $pesan = "Backup otomatis ({$stage}) gagal pada " . date('d M Y H:i') . " WITA.\n"
               . 'Penyebab: ' . $message . "\n"
               . 'Segera periksa kuota hosting/izin folder backups/, lalu jalankan backup manual di menu Backup.';
        $ins = $pdo->prepare("INSERT INTO notifikasi (user_id, judul, pesan, jenis) VALUES (?, ?, ?, 'danger')");
        foreach (hq_backup_admin_ids($pdo) as $aid) {
            $ins->execute([(int)$aid, $judul, $pesan]);
        }
    } catch (Throwable $e) {
        // Fallback minimal: penangan error tidak boleh melempar error
        error_log('[KuisArsip] Gagal mencatat error backup: ' . $e->getMessage());
    }
}

/** Penanda error backup terakhir (dibaca dasbor) atau null bila tidak ada. */
function hq_backup_last_error(): ?array
{
    $f = HQ_BACKUP_DIR . '/last_error.json';
    if (!is_file($f)) return null;
    $raw = @file_get_contents($f);
    if ($raw === false || $raw === '') return null;
    $d = json_decode($raw, true);
    if (!is_array($d) || empty($d['time'])) return null;
    return ['time' => (int)$d['time'], 'stage' => (string)($d['stage'] ?? ''), 'message' => (string)($d['message'] ?? '')];
}

/** Hapus penanda error (dipanggil saat backup sukses / restore sukses). */
function hq_backup_clear_error(): void
{
    $f = HQ_BACKUP_DIR . '/last_error.json';
    if (is_file($f)) @unlink($f);
}

// ============================================================
// RESTORE: PARSER SQL AMAN
// Memecah dump menjadi statement per statement dengan mengabaikan
// titik-koma di DALAM string terbungkus kutip (data peserta bisa
// mengandung tanda ; dan '). Komentar baris & blok dibuang.
// ============================================================

function hq_restore_statements(string $sql): array
{
    $lines = preg_split('/\R/', $sql) ?: [];
    $clean = [];
    foreach ($lines as $line) {
        if (preg_match('/^\s*(--|#)/', $line)) continue; // komentar baris
        $clean[] = $line;
    }
    $body = implode("\n", $clean);
    $body = preg_replace('/\/\*.*?\*\//s', ' ', $body) ?? $body; // komentar blok

    $stmts = [];
    $cur = '';
    $inStr = false;
    $strCh = '';
    $esc = false;
    $len = strlen($body);
    for ($i = 0; $i < $len; $i++) {
        $ch = $body[$i];
        if ($inStr) {
            $cur .= $ch;
            if ($esc) {
                $esc = false;
            } elseif ($ch === '\\') {
                $esc = true;
            } elseif ($ch === $strCh) {
                if (isset($body[$i + 1]) && $body[$i + 1] === $strCh) { // kutip ganda '' atau ""
                    $cur .= $strCh;
                    $i++;
                } else {
                    $inStr = false;
                }
            }
            continue;
        }
        if ($ch === "'" || $ch === '"') {
            $inStr = true;
            $strCh = $ch;
            $cur .= $ch;
            continue;
        }
        if ($ch === ';') {
            if (trim($cur) !== '') $stmts[] = trim($cur);
            $cur = '';
            continue;
        }
        $cur .= $ch;
    }
    if (trim($cur) !== '') $stmts[] = trim($cur);
    return $stmts;
}
