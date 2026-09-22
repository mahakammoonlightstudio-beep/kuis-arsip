<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layout.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

if (is_logged_in()) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? '../admin/index.php' : '../quiz/index.php'));
    exit;
}

$error = '';
$success = '';
$user_data = null;
$token_valid = false;

$token = $_GET['token'] ?? '';

if (!empty($token)) {
    $stmt = $pdo->prepare("SELECT id, nama, nim, reset_token, reset_token_expiry FROM users WHERE reset_token = ? AND role = 'peserta' LIMIT 1");
    $stmt->execute([$token]);
    $user_data = $stmt->fetch();

    if ($user_data) {
        if (strtotime($user_data['reset_token_expiry']) > time()) {
            $token_valid = true;
        } else {
            $error = 'Tautan sudah kedaluwarsa. Silakan minta tautan baru.';
        }
    } else {
        $error = 'Token tidak valid. Silakan minta tautan baru.';
    }
} else {
    $error = 'Token tidak ditemukan.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valid) {
    csrf_verify();
    $new_pass = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($new_pass) < 6) {
        $error = 'Kata sandi minimal 6 karakter.';
    } elseif ($new_pass !== $confirm) {
        $error = 'Konfirmasi kata sandi tidak cocok.';
    } else {
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?")->execute([$hash, $user_data['id']]);
        $success = 'Kata sandi berhasil diubah.';
        $token_valid = false;
    }
}

layout_header([
    'title' => 'Atur Ulang Kata Sandi',
    'active' => 'login',
    'role' => 'guest',
    'main_narrow' => true,
]);
?>


<section class="panel">
    <header>
        <h2>Atur Ulang Kata Sandi</h2>
    </header>
    <div class="panel-body">
        <?php if ($success): ?>
            <p class="notice notice-ok"><strong>Kata sandi berhasil diubah.</strong> Anda dapat masuk dengan kata sandi baru.</p>
            <div class="btn-row">
                <a class="btn-sm" href="login.php">Masuk sekarang</a>
            </div>

        <?php elseif (!$token_valid && !$error): ?>
            <p class="notice notice-info">Token tidak ditemukan. <a href="forgot_password.php">Minta tautan baru</a>.</p>

        <?php elseif (!$token_valid): ?>
            <p class="notice notice-error"><?= e($error) ?></p>
            <div class="btn-row">
                <a class="btn-sm btn-quiet" href="forgot_password.php">Minta tautan baru</a>
            </div>

        <?php else: ?>
            <p class="notice notice-info">Halo, <strong><?= e($user_data['nama']) ?></strong>. Silakan buat kata sandi baru.</p>
            <?php if ($error): ?>
                <p class="notice notice-error"><?= e($error) ?></p>
            <?php endif; ?>

            <form method="post">
                <?= csrf_field() ?>
                <div class="field">
                    <label class="field-label" for="new_password">Kata sandi baru (min. 6 karakter)</label>
                    <input id="new_password" type="password" name="new_password" required minlength="6">
                </div>
                <div class="field">
                    <label class="field-label" for="confirm">Konfirmasi kata sandi</label>
                    <input id="confirm" type="password" name="confirm_password" required>
                </div>
                <div class="btn-row">
                    <button type="submit" class="btn">Ubah kata sandi</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>


<?php layout_footer(['base' => '..']); ?>
