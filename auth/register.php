<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layout.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

if (is_logged_in()) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: ../admin/index.php');
    } else {
        header('Location: ../quiz/index.php');
    }
    exit;
}

$stmt_set = $pdo->query("SELECT is_registration_open FROM app_settings WHERE id = 1");
$setting = $stmt_set->fetch();
$is_open = $setting ? (bool)$setting['is_registration_open'] : true;

hq_maintenance_gate();

$error = '';

if ($is_open && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $nama     = trim($_POST['nama'] ?? '');
    $nim      = trim($_POST['nim'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (!preg_match("/^[a-zA-ZÀ-ÿ\s'-]+$/", $nama)) {
        $error = 'Nama tidak valid. Hanya boleh huruf dan spasi.';
    } elseif (strlen($nama) < 3) {
        $error = 'Nama minimal 3 huruf.';
    } elseif ($nim === '' || $password === '') {
        $error = 'NIM dan kata sandi wajib diisi.';
    } elseif (!ctype_alnum($nim)) {
        $error = 'NIM/NIP hanya boleh huruf dan angka tanpa spasi.';
    } elseif ($password !== $confirm) {
        $error = 'Konfirmasi kata sandi tidak cocok.';
    } elseif (strlen($password) < 6) {
        $error = 'Kata sandi minimal 6 karakter.';
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE nim = ?");
        $check->execute([$nim]);
        if ($check->fetch()) {
            $error = 'NIM/NIP sudah terdaftar.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (nama, nim, password) VALUES (?, ?, ?)");
            $stmt->execute([$nama, $nim, $hash]);
            header('Location: login.php?registered=1');
            exit;
        }
    }
}

layout_header([
    'title' => 'Daftar',
    'active' => 'register',
    'role' => 'guest',
    'main_narrow' => true,
]);
?>


<section class="panel">
    <header>
        <h2>Pendaftaran Peserta</h2>
        <p>Buat akun baru untuk mengikuti kuesioner.</p>
    </header>
    <div class="panel-body">
        <?php if (!$is_open): ?>
            <p class="notice notice-error"><strong>Pendaftaran ditutup.</strong> Saat ini sistem tidak menerima peserta baru. Hubungi admin untuk informasi lebih lanjut.</p>
            <div class="btn-row">
                <a class="btn-sm btn-quiet" href="login.php">Kembali ke masuk</a>
            </div>
        <?php else: ?>
            <?php if (isset($_GET['registered'])): ?>
                <p class="notice notice-ok">Pendaftaran berhasil. Silakan masuk.</p>
            <?php elseif ($error): ?>
                <p class="notice notice-error"><?= e($error) ?></p>
            <?php endif; ?>

            <form method="post">
                <?= csrf_field() ?>
                <div class="field">
                    <label class="field-label" for="nama">Nama lengkap</label>
                    <input id="nama" type="text" name="nama" required autofocus value="<?= isset($nama) ? e($nama) : '' ?>">
                </div>
                <div class="field">
                    <label class="field-label" for="nim">NIM / NIP</label>
                    <input id="nim" type="text" name="nim" required value="<?= isset($nim) ? e($nim) : '' ?>">
                </div>
                <div class="field">
                    <label class="field-label" for="password">Kata sandi (min. 6 karakter)</label>
                    <input id="password" type="password" name="password" required>
                </div>
                <div class="field">
                    <label class="field-label" for="confirm">Konfirmasi kata sandi</label>
                    <input id="confirm" type="password" name="confirm" required>
                </div>
                <div class="btn-row">
                    <button type="submit" class="btn">Daftar</button>
                </div>
            </form>
            <p class="muted text-sm">Sudah punya akun? <a href="login.php">Masuk</a></p>
        <?php endif; ?>
    </div>
</section>


<?php layout_footer(['base' => '..']); ?>
