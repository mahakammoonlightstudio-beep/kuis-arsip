<?php
/**
 * Kuis Arsip (htdocs) - admin/settings.php
 * Pengaturan Sistem: identitas situs, pendaftaran, mode pemeliharaan,
 * bahasa default. Nilai disimpan ke tabel `app_settings` (id = 1).
 * Kolom site_name/default_lang/maintenance_message butuh database-upgrade.sql.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layout.php';

if (!is_logged_in() || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$msg = $_GET['msg'] ?? '';

// Baris pengaturan (id = 1)
$row = $pdo->query("SELECT * FROM app_settings WHERE id = 1 LIMIT 1")->fetch() ?: [];
$siteName = (string)($row['site_name'] ?? '');
$defaultLang = (string)($row['default_lang'] ?? 'id');
$isOpen = !empty($row['is_registration_open']);
$maintenance = !empty($row['is_maintenance']);
$maintMsg = (string)($row['maintenance_message'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    csrf_verify();

    $siteNameNew = trim($_POST['site_name'] ?? '');
    $defaultLangNew = in_array($_POST['default_lang'] ?? 'id', ['id', 'en'], true) ? $_POST['default_lang'] : 'id';
    $isOpenNew = isset($_POST['is_registration_open']) ? 1 : 0;
    $maintenanceNew = isset($_POST['is_maintenance']) ? 1 : 0;
    $maintMsgNew = trim($_POST['maintenance_message'] ?? '');

    try {
        // Cek kolom opsional hasil migrasi
        $cols = $pdo->query("SHOW COLUMNS FROM app_settings")->fetchAll(PDO::FETCH_COLUMN);
        $hasSiteName = in_array('site_name', $cols, true);
        $hasDefaultLang = in_array('default_lang', $cols, true);
        $hasMaintMsg = in_array('maintenance_message', $cols, true);

        $sql = "UPDATE app_settings SET
                    is_registration_open = ?,
                    is_maintenance = ?";
        $params = [$isOpenNew, $maintenanceNew];
        if ($hasSiteName) {
            $sql .= ", site_name = ?";
            $params[] = $siteNameNew !== '' ? $siteNameNew : null;
        }
        if ($hasDefaultLang) {
            $sql .= ", default_lang = ?";
            $params[] = $defaultLangNew;
        }
        if ($hasMaintMsg) {
            $sql .= ", maintenance_message = ?";
            $params[] = $maintMsgNew !== '' ? $maintMsgNew : null;
        }
        $sql .= " WHERE id = 1";
        $pdo->prepare($sql)->execute($params);

        audit_log('update', 'app_settings', 1, 'Pengaturan sistem diperbarui');
        header('Location: settings.php?msg=saved');
    } catch (Throwable $e) {
        error_log('[KuisArsip] Save settings failed: ' . $e->getMessage());
        header('Location: settings.php?msg=error');
    }
    exit;
}

layout_header([
    'title' => __('Pengaturan Sistem'),
    'active' => 'settings',
    'role' => 'admin',
]);
?>
<div class="page-head">
    <h1><?= __('Pengaturan Sistem') ?></h1>
    <p><?= __('Konfigurasi tampilan dan perilaku aplikasi.') ?></p>
</div>

<?php if ($msg === 'saved'): ?>
    <p class="notice notice-ok" role="status"><?= __('Pengaturan berhasil disimpan.') ?></p>
<?php elseif ($msg === 'error'): ?>
    <p class="notice notice-error" role="alert"><?= __('Gagal menyimpan pengaturan. Pastikan migrasi database sudah dijalankan (database-upgrade.sql).') ?></p>
<?php endif; ?>

<form method="POST">
    <?= csrf_field() ?>
    <section class="panel">
        <header>
            <h2><?= __('Identitas Situs') ?></h2>
        </header>
        <div class="panel-body">
            <div class="field">
                <label class="field-label" for="site_name"><?= __('Nama Situs') ?></label>
                <input id="site_name" type="text" name="site_name" maxlength="100" value="<?= e($siteName) ?>">
                <span class="hint"><?= __('Nama yang tampil pada judul browser.') ?></span>
            </div>
            <div class="field">
                <label class="field-label" for="default_lang"><?= __('Bahasa') ?> (<?= __('default') ?>)</label>
                <select id="default_lang" name="default_lang">
                    <?php foreach (hq_lang_options() as $kode => $label): ?>
                        <option value="<?= $kode ?>"<?= $defaultLang === $kode ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="hint"><?= __('Bahasa default pengunjung baru.') ?></span>
            </div>
        </div>
    </section>

    <section class="panel">
        <header>
            <h2><?= __('Perilaku Aplikasi') ?></h2>
        </header>
        <div class="panel-body">
            <div class="field agree">
                <input type="checkbox" id="is_registration_open" name="is_registration_open"<?= $isOpen ? ' checked' : '' ?>>
                <label for="is_registration_open"><?= __('Buka pendaftaran akun baru') ?></label>
            </div>
            <p class="hint" style="margin:-4px 0 var(--s4) 32px;"><?= __('Saat mati, halaman pendaftaran ditutup.') ?></p>

            <div class="field agree">
                <input type="checkbox" id="is_maintenance" name="is_maintenance"<?= $maintenance ? ' checked' : '' ?>>
                <label for="is_maintenance"><?= __('Mode pemeliharaan') ?></label>
            </div>
            <p class="hint" style="margin:-4px 0 var(--s4) 32px;"><?= __('Saat aktif, hanya admin yang dapat mengakses aplikasi.') ?></p>

            <div class="field">
                <label class="field-label" for="maintenance_message"><?= __('Pesan pemeliharaan') ?></label>
                <textarea id="maintenance_message" name="maintenance_message" rows="2" maxlength="300"><?= e($maintMsg) ?></textarea>
            </div>

            <div class="btn-row">
                <button class="btn btn-primary" name="save_settings" value="1" type="submit"><?= __('Simpan') ?></button>
            </div>
        </div>
    </section>
</form>

<?php layout_footer(); ?>
