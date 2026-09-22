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

hq_maintenance_gate();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (rate_limit_check('login', 5, 300)) {
        $reset_at = $_SESSION['rate_login_' . ($_SERVER['REMOTE_ADDR'] ?? '')]['reset'] ?? time();
        $wait_minutes = max(1, (int)ceil((max(0, $reset_at - time())) / 60));
        $error = 'Terlalu banyak percobaan login. Silakan coba lagi dalam ' . $wait_minutes . ' menit.';
    } else {
        $nim = trim($_POST['nim'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($nim === '' || $password === '') {
            $error = 'NIM dan kata sandi wajib diisi.';
        } else {
            $stmt = $pdo->prepare("SELECT id, nama, nim, password, role, bahasa FROM users WHERE nim = ? LIMIT 1");
            $stmt->execute([$nim]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['nama']    = $user['nama'];
                $_SESSION['nim']     = $user['nim'];
                $_SESSION['role']    = $user['role'];
                $_SESSION['hq_bahasa'] = $user['bahasa'] ?? '';
                $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                audit_log('login', 'users', $user['id'], 'Login berhasil');
                unset($_SESSION['rate_login_' . ($_SERVER['REMOTE_ADDR'] ?? '')]);
                $time = time();
                if ($user['role'] === 'admin') {
                    header("Location: ../admin/index.php?login=1&t=$time");
                } else {
                    header("Location: ../quiz/index.php?login=1&t=$time");
                }
                exit;
            } else {
                $error = 'NIM atau kata sandi salah.';
            }
        }
    }
}

layout_header([
    'title' => 'Masuk',
    'active' => 'login',
    'role' => 'guest',
    'main_narrow' => true,
]);
?>


<section class="panel">
    <header>
        <h2>Masuk Peserta</h2>
        <p>Gunakan NIM/NIP dan kata sandi yang terdaftar.</p>
    </header>
    <div class="panel-body">
        <?php if (isset($_GET['status']) && $_GET['status'] === 'login_required'): ?>
            <p class="notice notice-info">Silakan masuk terlebih dahulu untuk memulai kuesioner.</p>
        <?php elseif (isset($_GET['logged_out'])): ?>
            <p class="notice notice-ok">Anda telah berhasil keluar.</p>
        <?php elseif (isset($_GET['registered'])): ?>
            <p class="notice notice-ok">Pendaftaran berhasil. Silakan masuk.</p>
        <?php elseif ($error): ?>
            <p class="notice notice-error"><?= e($error) ?></p>
        <?php endif; ?>

        <form method="post" id="loginForm" novalidate>
            <div class="field">
                <label class="field-label" for="nim">NIM / NIP</label>
                <input id="nim" type="text" name="nim" required autofocus value="<?= isset($nim) ? e($nim) : '' ?>" autocomplete="username">
            </div>
            <div class="field">
                <label class="field-label" for="password">Kata sandi</label>
                <input id="password" type="password" name="password" required autocomplete="current-password">
            </div>
            <div class="btn-row">
                <button type="submit" class="btn w-full" id="loginBtn">Masuk</button>
            </div>
        </form>
        <p class="muted text-sm" style="margin-top: var(--s4); text-align: center;">Belum punya akun? <a href="register.php">Daftar di sini</a></p>
        <p class="muted text-sm" style="text-align: center;">Lupa kata sandi? <a href="forgot_password.php">Atur ulang di sini</a></p>
    </div>
</section>


<?php layout_footer(['base' => '..', 'scripts' => '
<script>
document.getElementById("loginForm").addEventListener("submit", function (e) {
    var b = document.getElementById("loginBtn");
    b.textContent = "Memproses...";
    b.disabled = true;
});
</script>
']); ?>