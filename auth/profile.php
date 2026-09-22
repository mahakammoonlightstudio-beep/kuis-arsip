<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layout.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    csrf_verify();
    $nama = trim($_POST['nama'] ?? '');
    $nim  = trim($_POST['nim'] ?? '');

    if (!preg_match("/^[a-zA-ZÀ-ÿ\s'-]+$/", $nama)) {
        $error = "Nama tidak valid. Hanya boleh huruf dan spasi.";
    } elseif (mb_strlen($nama) < 3) {
        $error = "Nama minimal 3 huruf.";
    } elseif (!ctype_alnum($nim)) {
        $error = "NIM/NIP hanya boleh huruf dan angka.";
    } else {
        if ($nim !== $user['nim']) {
            $check_nim = $pdo->prepare("SELECT id FROM users WHERE nim = ? AND id != ?");
            $check_nim->execute([$nim, $user_id]);
            if ($check_nim->fetch()) {
                $error = "NIM/NIP sudah dipakai akun lain.";
            }
        }

        if (empty($error)) {
            $photo_path = $user['photo_path'];
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $allowed = ['image/jpeg', 'image/png', 'image/webp'];
                $ftype = $_FILES['photo']['type'];
                $fsize = $_FILES['photo']['size'];
                if (in_array($ftype, $allowed) && $fsize <= 2 * 1024 * 1024) {
                    $ext = match($ftype) {
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                        default => 'jpg'
                    };
                    $filename = 'photos/' . $user_id . '_' . time() . '.' . $ext;
                    $upload_dir = __DIR__ . '/../assets/';
                    if (!is_dir($upload_dir . 'photos')) {
                        mkdir($upload_dir . 'photos', 0755, true);
                    }
                    $dest = $upload_dir . $filename;
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
                        if (!empty($photo_path) && file_exists(__DIR__ . '/../assets/' . $photo_path)) {
                            @unlink(__DIR__ . '/../assets/' . $photo_path);
                        }
                        $photo_path = $filename;
                    }
                }
            }

            $pdo->prepare("UPDATE users SET nama = ?, nim = ?, photo_path = ? WHERE id = ?")->execute([$nama, $nim, $photo_path, $user_id]);
            $_SESSION['nama'] = $nama;
            $_SESSION['nim']  = $nim;
            if (!empty($photo_path)) $_SESSION['photo_path'] = $photo_path;
            header('Location: profile.php?msg=updated');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    csrf_verify();
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($current) || empty($new) || empty($confirm)) {
        $error = "Semua kolom wajib diisi.";
    } elseif (!password_verify($current, $user['password'])) {
        $error = "Kata sandi lama salah.";
    } elseif (strlen($new) < 6) {
        $error = "Kata sandi baru minimal 6 karakter.";
    } elseif ($new !== $confirm) {
        $error = "Konfirmasi kata sandi baru tidak cocok.";
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $user_id]);
        header('Location: profile.php?msg=password_changed');
        exit;
    }
}

// ===== Statistik & pencapaian =====
$stStats = $pdo->prepare(
    "SELECT COUNT(*) AS total_coba,
            SUM(score = total_questions) AS sempurna,
            MAX(ROUND((score / NULLIF(total_questions,0)) * 100)) AS best_pct,
            MAX(created_at) AS terakhir
     FROM results WHERE user_id = ?"
);
$stStats->execute([$user_id]);
$stats = $stStats->fetch() ?: [];

$totalCoba = (int)($stats['total_coba'] ?? 0);
$bestPct   = (int)($stats['best_pct'] ?? 0);
$sempurna  = (int)($stats['sempurna'] ?? 0);

$stRiwayat = $pdo->prepare(
    "SELECT r.score, r.total_questions, r.created_at,
            COALESCE(rm.room_name, sv.title) AS kegiatan,
            (r.room_id IS NOT NULL) AS is_room
     FROM results r
     LEFT JOIN rooms rm ON rm.id = r.room_id
     LEFT JOIN surveys sv ON sv.id = r.survey_id
     WHERE r.user_id = ?
     ORDER BY r.created_at DESC
     LIMIT 8"
);
$stRiwayat->execute([$user_id]);
$riwayat = $stRiwayat->fetchAll();

// Foto profil (fallback avatar inisial)
$photo = !empty($user['photo_path']) && file_exists(__DIR__ . '/../assets/' . $user['photo_path'])
    ? '../assets/' . e($user['photo_path']) : '';
$inisial = mb_strtoupper(mb_substr((string)$user['nama'], 0, 1));
if ($inisial === '') $inisial = '?';

$msg = $_GET['msg'] ?? '';

layout_header([
    'title' => 'Pengaturan Akun',
    'active' => 'profile',
    'role' => $_SESSION['role'] ?? 'peserta',
]);
?>

<!-- Hero profil -->
<div class="profile-hero">
    <?php if ($photo !== ''): ?>
        <img class="avatar-badge" src="<?= $photo ?>" alt="Foto profil" width="72" height="72" style="object-fit:cover;">
    <?php else: ?>
        <span class="avatar-badge<?= $bestPct >= 90 ? ' gold' : '' ?>"><?= e($inisial) ?></span>
    <?php endif; ?>
    <div class="profile-hero-main">
        <div class="profile-hero-name"><?= e($user['nama']) ?></div>
        <div class="profile-hero-sub">NIM/NIP <?= e($user['nim']) ?> &middot; <?= ucfirst(e($user['role'])) ?> &middot;
            anggota sejak <?= !empty($user['created_at']) ? date('M Y', strtotime($user['created_at'])) : '-' ?></div>
        <div class="tag-row" style="margin-top:var(--s2);">
            <?php if ($bestPct >= 90): ?><span class="badge-chip chip-gold">&#11088; Bintang Kearsipan</span><?php endif; ?>
            <?php if ($sempurna > 0): ?><span class="badge-chip chip-ok">&#127941; Skor Sempurna</span><?php endif; ?>
            <?php if ($totalCoba >= 5): ?><span class="badge-chip chip-info">&#128218; Rajin</span><?php endif; ?>
            <?php if ($totalCoba === 0): ?><span class="badge-chip">&#127919; Belum memulai</span><?php endif; ?>
        </div>
    </div>
    <div class="profile-hero-stats">
        <div class="profile-hero-stat"><b><?= $totalCoba ?></b><span>Pengerjaan</span></div>
        <div class="profile-hero-stat"><b><?= $bestPct ?>%</b><span>Terbaik</span></div>
        <div class="profile-hero-stat"><b><?= $sempurna ?></b><span>Sempurna</span></div>
    </div>
</div>

<?php if ($msg === 'updated'): ?>
    <p class="notice notice-ok" role="status">Profil berhasil diperbarui.</p>
<?php elseif ($msg === 'password_changed'): ?>
    <p class="notice notice-ok" role="status">Kata sandi berhasil diganti.</p>
<?php elseif ($error): ?>
    <p class="notice notice-error" role="alert"><?= e($error) ?></p>
<?php endif; ?>

<!-- Form: profil + sandi berdampingan -->
<div class="grid-2 section-tight">
    <section class="panel">
        <header>
            <h2>Edit Profil</h2>
            <p>Perbarui nama, NIM/NIP, dan foto Anda.</p>
        </header>
        <div class="panel-body">
            <form method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="field">
                    <label class="field-label" for="nama">Nama lengkap</label>
                    <input id="nama" type="text" name="nama" value="<?= e($user['nama']) ?>" required>
                </div>
                <div class="field">
                    <label class="field-label" for="nim">NIM / NIP</label>
                    <input id="nim" type="text" name="nim" value="<?= e($user['nim']) ?>" required>
                </div>
                <div class="field">
                    <label class="field-label">Peran</label>
                    <input type="text" value="<?= ucfirst(e($user['role'])) ?>" disabled>
                </div>
                <div class="field">
                    <label class="field-label" for="photo">Foto profil (maks 2 MB, JPG/PNG/WEBP)</label>
                    <div class="field-inline">
                        <?php if ($photo !== ''): ?>
                            <img src="<?= $photo ?>" alt="Foto profil" width="56" height="56" class="avatar">
                        <?php endif; ?>
                        <input id="photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp">
                    </div>
                    <span class="upload-hint">Foto tampil di papan peringkat dan sertifikat.</span>
                </div>
                <div class="btn-row">
                    <button type="submit" name="update_profile" class="btn">Simpan perubahan</button>
                </div>
            </form>
        </div>
    </section>

    <section class="panel">
        <header>
            <h2>Ubah Kata Sandi</h2>
            <p>Pastikan kata sandi baru mudah diingat namun sulit ditebak.</p>
        </header>
        <div class="panel-body">
            <form method="post">
                <?= csrf_field() ?>
                <div class="field password-field">
                    <label class="field-label" for="current_password">Kata sandi lama</label>
                    <input id="current_password" type="password" name="current_password" autocomplete="current-password" required>
                    <button type="button" class="password-toggle" data-toggle-for="current_password" aria-label="Tampilkan kata sandi">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
                <div class="field password-field">
                    <label class="field-label" for="new_password">Kata sandi baru</label>
                    <input id="new_password" type="password" name="new_password" autocomplete="new-password" required minlength="6">
                    <button type="button" class="password-toggle" data-toggle-for="new_password" aria-label="Tampilkan kata sandi">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
                <div class="field password-field">
                    <label class="field-label" for="confirm_password">Konfirmasi kata sandi</label>
                    <input id="confirm_password" type="password" name="confirm_password" autocomplete="new-password" required minlength="6">
                    <button type="button" class="password-toggle" data-toggle-for="confirm_password" aria-label="Tampilkan kata sandi">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
                <div class="btn-row">
                    <button type="submit" name="change_password" class="btn btn-quiet">Ubah kata sandi</button>
                </div>
            </form>
        </div>
    </section>
</div>

<!-- Riwayat bergaya tile -->
<?php if (!empty($riwayat)): ?>
<section class="panel section-tight">
    <header>
        <h2>Riwayat Terbaru</h2>
        <p>Delapan pengerjaan terakhir Anda.</p>
    </header>
    <div class="panel-body">
        <ul class="list-tile">
            <?php foreach ($riwayat as $r): ?>
            <li>
                <span class="tile-icon"><?= $r['is_room'] ? '&#127970;' : '&#128218;' ?></span>
                <div class="tile-main">
                    <div class="tile-title"><?= e($r['kegiatan'] ?: 'Kuesioner') ?></div>
                    <div class="tile-sub"><?= date('d M Y, H:i', strtotime($r['created_at'])) ?></div>
                </div>
                <span class="badge-chip<?= ((int)$r['score'] === (int)$r['total_questions']) ? ' chip-ok' : '' ?>"><?= (int)$r['score'] ?>/<?= (int)$r['total_questions'] ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>

<!-- Zona berbahaya -->
<section class="panel danger-zone section-tight">
    <header>
        <h2>Zona Berbahaya</h2>
        <p>Tindakan di sini mengakhiri sesi Anda saat ini.</p>
    </header>
    <div class="panel-body">
        <div class="btn-row">
            <a class="btn btn-danger" href="logout.php">Keluar dari akun</a>
        </div>
    </div>
</section>

<?php layout_footer(['base' => '..', 'scripts' => '
<script>
document.addEventListener("DOMContentLoaded", function () {
    /* Toggle tampil/sembunyi kata sandi */
    document.querySelectorAll(".password-toggle[data-toggle-for]").forEach(function (btn) {
        var input = document.getElementById(btn.getAttribute("data-toggle-for"));
        if (!input) return;
        btn.addEventListener("click", function () {
            var show = input.type === "password";
            input.type = show ? "text" : "password";
            btn.setAttribute("aria-label", show ? "Sembunyikan kata sandi" : "Tampilkan kata sandi");
            btn.classList.toggle("is-on", show);
        });
    });
});
</script>
']); ?>
