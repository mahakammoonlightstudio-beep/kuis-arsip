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
$email_sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim($_POST['email'] ?? '');
    $nim = trim($_POST['nim'] ?? '');

    if (empty($email) && empty($nim)) {
        $error = 'Masukkan surel atau NIM/NIP Anda.';
    } else {
        // Bangun kondisi hanya dari field yang diisi — mencari dengan email = ''
        // bisa mencocokkan baris lain yang kebetulan tanpa email.
        $conds = [];
        $lookup_params = [];
        if ($email !== '') { $conds[] = "email = ?"; $lookup_params[] = $email; }
        if ($nim !== '') { $conds[] = "nim = ?"; $lookup_params[] = $nim; }
        $stmt = $pdo->prepare("SELECT id, nama, nim, email FROM users WHERE (" . implode(' OR ', $conds) . ") AND role = 'peserta' LIMIT 1");
        $stmt->execute($lookup_params);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', time() + 3600);
            $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?")->execute([$token, $expiry, $user['id']]);

            $reset_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://')
                . $_SERVER['HTTP_HOST']
                . dirname($_SERVER['PHP_SELF']) . '/reset_password.php?token=' . $token;

            $subject = 'Reset Kata Sandi - Sistem Kearsipan Kukar';
            $body = "Halo {$user['nama']},\n\n";
            $body .= "Anda meminta reset kata sandi untuk akun Sistem Kearsipan Kabupaten Kutai Kartanegara.\n\n";
            $body .= "Klik tautan berikut untuk mengatur kata sandi baru:\n";
            $body .= $reset_link . "\n\n";
            $body .= "Tautan ini berlaku selama 1 jam.\n\n";
            $body .= "Jika Anda tidak meminta reset, abaikan surel ini.\n\n";
            $body .= "—\nDinas Kearsipan dan Perpustakaan Kabupaten Kutai Kartanegara";

            $headers = "From: no-reply@diarpus.kukarkab.go.id\r\nReply-To: diarpuskukar@gmail.com\r\nContent-Type: text/plain; charset=UTF-8\r\n";
            if (!empty($user['email'])) {
                @mail($user['email'], $subject, $body, $headers);
            }
        }
        $email_sent = true;
    }
}

layout_header([
    'title' => 'Lupa Kata Sandi',
    'active' => 'login',
    'role' => 'guest',
    'main_narrow' => true,
]);
?>


<section class="panel">
    <header>
        <h2>Atur Ulang Kata Sandi</h2>
        <p>Masukkan surel atau NIM/NIP untuk menerima tautan pengaturan ulang.</p>
    </header>
    <div class="panel-body">
        <?php if ($email_sent): ?>
            <p class="notice notice-ok"><strong>Tautan terkirim.</strong> Periksa kotak masuk (atau folder spam) surel Anda. Klik tautan untuk membuat kata sandi baru. Tautan berlaku 1 jam.</p>
            <div class="btn-row">
                <a class="btn-sm btn-quiet" href="login.php">Kembali ke masuk</a>
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <p class="notice notice-error"><?= e($error) ?></p>
            <?php endif; ?>

            <form method="post">
                <?= csrf_field() ?>
                <div class="field">
                    <label class="field-label" for="email">Surel terdaftar</label>
                    <input id="email" type="email" name="email" placeholder="contoh@kukarkab.go.id">
                </div>
                <div class="field">
                    <label class="field-label" for="nim">atau NIM / NIP</label>
                    <input id="nim" type="text" name="nim" placeholder="Masukkan NIM/NIP Anda">
                </div>
                <div class="btn-row">
                    <button type="submit" class="btn">Kirim tautan</button>
                </div>
            </form>
            <p class="muted text-sm"><a href="login.php">Kembali ke masuk</a></p>
        <?php endif; ?>
    </div>
</section>


<?php layout_footer(['base' => '..']); ?>
