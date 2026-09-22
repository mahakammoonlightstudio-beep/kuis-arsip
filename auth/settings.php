<?php
/**
 * Kuis Arsip (htdocs) - auth/settings.php
 * Pusat Pengaturan untuk peserta/pekerja:
 *  - Preferensi: bahasa & tema tampilan (tersimpan ke akun)
 *  - Data pekerja: jabatan, unit kerja, nomor WhatsApp
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layout.php';

if (!is_logged_in()) {
    header('Location: login.php?redirect=settings.php');
    exit;
}

$uid = (int)$_SESSION['user_id'];
$msg = $_GET['msg'] ?? '';
$error = '';

// ===== Simpan preferensi =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_prefs'])) {
    csrf_verify();
    $bahasa = in_array($_POST['bahasa'] ?? 'id', ['id', 'en'], true) ? $_POST['bahasa'] : 'id';
    $tema = in_array($_POST['tema'] ?? '', ['system', 'light', 'dark'], true) ? $_POST['tema'] : '';

    try {
        $pdo->prepare("UPDATE users SET bahasa = ?, tema = ? WHERE id = ?")->execute([$bahasa, $tema, $uid]);
        $_SESSION['hq_bahasa'] = $bahasa;
        @setcookie('hq_lang', $bahasa, [
            'expires'  => time() + 365 * 24 * 60 * 60,
            'path'     => '/',
            'samesite' => 'Lax',
            'httponly' => true,
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        ]);
        header('Location: settings.php?msg=prefs');
    } catch (Throwable $e) {
        error_log('[KuisArsip] Save prefs failed: ' . $e->getMessage());
        header('Location: settings.php?msg=error');
    }
    exit;
}

// ===== Simpan data pekerja =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_pekerja'])) {
    csrf_verify();
    $jabatan = trim($_POST['jabatan'] ?? '');
    $unit = trim($_POST['unit_kerja'] ?? '');
    $wa = trim($_POST['no_whatsapp'] ?? '');

    if (mb_strlen($jabatan) > 100 || mb_strlen($unit) > 100) {
        $error = __('Data terlalu panjang (maks 100 karakter).');
    } elseif ($wa !== '' && !preg_match('/^[0-9+\-\s]{6,20}$/', $wa)) {
        $error = __('Nomor WhatsApp tidak valid.');
    } else {
        try {
            $pdo->prepare("UPDATE users SET jabatan = ?, unit_kerja = ?, no_whatsapp = ? WHERE id = ?")
                ->execute([
                    $jabatan !== '' ? $jabatan : null,
                    $unit !== '' ? $unit : null,
                    $wa !== '' ? $wa : null,
                    $uid,
                ]);
            header('Location: settings.php?msg=pekerja');
        } catch (Throwable $e) {
            error_log('[KuisArsip] Save pekerja failed: ' . $e->getMessage());
            header('Location: settings.php?msg=error');
        }
        exit;
    }
}

// Data user saat ini
$stU = $pdo->prepare("SELECT nama, nim, bahasa, tema, jabatan, unit_kerja, no_whatsapp FROM users WHERE id = ? LIMIT 1");
$stU->execute([$uid]);
$u = $stU->fetch() ?: [];
$bahasaUser = ($u['bahasa'] ?? '') ?: hq_lang();
$temaUser = $u['tema'] ?? '';

layout_header([
    'title' => __('Pengaturan'),
    'active' => 'settings',
    'role' => $_SESSION['role'] ?? 'guest',
    'main_narrow' => true,
]);
?>
<div class="page-head">
    <h1><?= __('Pengaturan') ?></h1>
    <p><?= __('Atur preferensi tampilan, bahasa, dan data dirimu di sini.') ?></p>
</div>

<?php if ($msg === 'prefs'): ?>
    <p class="notice notice-ok" role="status"><?= __('Preferensi berhasil disimpan.') ?></p>
<?php elseif ($msg === 'pekerja'): ?>
    <p class="notice notice-ok" role="status"><?= __('Data pekerja berhasil disimpan.') ?></p>
<?php elseif ($msg === 'error'): ?>
    <p class="notice notice-error" role="alert"><?= __('Gagal menyimpan. Pastikan migrasi database sudah dijalankan (database-upgrade.sql).') ?></p>
<?php elseif ($error): ?>
    <p class="notice notice-error" role="alert"><?= e($error) ?></p>
<?php endif; ?>

<section class="panel">
    <header>
        <h2><?= __('Preferensi Saya') ?></h2>
        <p><?= __('Bahasa dan tema tersimpan ke akunmu, jadi tampilan selalu sama di perangkat mana pun.') ?></p>
    </header>
    <div class="panel-body">
        <form method="POST">
            <?= csrf_field() ?>
            <div class="field">
                <label class="field-label" for="bahasa"><?= __('Bahasa') ?></label>
                <select id="bahasa" name="bahasa">
                    <?php foreach (hq_lang_options() as $kode => $label): ?>
                        <option value="<?= $kode ?>"<?= $bahasaUser === $kode ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field-label" for="tema"><?= __('Tema Tampilan') ?></label>
                <select id="tema" name="tema">
                    <option value="system"<?= ($temaUser === 'system' || $temaUser === '') ? ' selected' : '' ?>><?= __('Mengikuti Sistem') ?></option>
                    <option value="light"<?= $temaUser === 'light' ? ' selected' : '' ?>><?= __('Mode Terang') ?></option>
                    <option value="dark"<?= $temaUser === 'dark' ? ' selected' : '' ?>><?= __('Mode Gelap') ?></option>
                </select>
            </div>
            <div class="btn-row">
                <button class="btn" name="save_prefs" value="1" type="submit"><?= __('Simpan') ?></button>
            </div>
        </form>
    </div>
</section>

<section class="panel">
    <header>
        <h2><?= __('Data Pekerja') ?></h2>
        <p><?= __('Lengkapi data ini agar admin dapat mengenalmu di laporan hasil kuesioner.') ?></p>
    </header>
    <div class="panel-body">
        <form method="POST">
            <?= csrf_field() ?>
            <div class="field">
                <label class="field-label" for="jabatan"><?= __('Jabatan') ?></label>
                <input id="jabatan" type="text" name="jabatan" maxlength="100"
                       value="<?= e($u['jabatan'] ?? '') ?>" placeholder="<?= __('cth: Arsiparis Muda') ?>">
            </div>
            <div class="field">
                <label class="field-label" for="unit_kerja"><?= __('Unit Kerja') ?></label>
                <input id="unit_kerja" type="text" name="unit_kerja" maxlength="100"
                       value="<?= e($u['unit_kerja'] ?? '') ?>" placeholder="<?= __('cth: Bidang P2A') ?>">
            </div>
            <div class="field">
                <label class="field-label" for="no_whatsapp"><?= __('No. WhatsApp') ?></label>
                <input id="no_whatsapp" type="tel" name="no_whatsapp" maxlength="20"
                       value="<?= e($u['no_whatsapp'] ?? '') ?>" placeholder="<?= __('cth: 0812xxxxxxx') ?>">
            </div>
            <div class="btn-row">
                <button class="btn" name="save_pekerja" value="1" type="submit"><?= __('Simpan') ?></button>
            </div>
        </form>
    </div>
</section>

<?php layout_footer(); ?>
