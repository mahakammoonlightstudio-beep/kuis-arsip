<?php
require_once __DIR__ . '/lang.php';

/** Jumlah notifikasi belum dibaca (lonceng header). 0 bila tabel belum dimigrasi. */
function hq_unread_notif_count(): int
{
    static $count = null;
    if ($count !== null) return $count;
    $count = 0;
    try {
        if (!empty($_SESSION['user_id'])) {
            global $pdo;
            $st = $pdo->prepare("SELECT COUNT(*) FROM notifikasi WHERE user_id = ? AND dibaca = 0");
            $st->execute([(int)$_SESSION['user_id']]);
            $count = (int)$st->fetchColumn();
        }
    } catch (Throwable $e) {
        $count = 0;
    }
    return $count;
}

function nav_items(string $role, string $base, int $tickets = 0): array
{
    if ($role === 'admin') {
        return [
            'dashboard'  => ['Dasbor', $base . '/admin/index.php'],
            'users'      => ['Peserta', $base . '/admin/users.php'],
            'surveys'    => ['Kuesioner', $base . '/admin/surveys.php'],
            'rooms'      => ['Ruangan', $base . '/admin/rooms.php'],
            'analytics'  => ['Analisis Soal', $base . '/admin/quiz_analytics.php'],
            'helpdesk'   => ['Bantuan', $base . '/admin/helpdesk.php', $tickets],
            'feedback'   => ['Masukan', $base . '/admin/feedback.php'],
            'broadcast'  => ['Broadcast', $base . '/admin/broadcast.php'],
            'settings'   => ['Pengaturan Sistem', $base . '/admin/settings.php'],
            'audit'      => ['Audit', $base . '/admin/audit_logs.php'],
            'backup'     => ['Backup', $base . '/admin/backup.php'],
            'profile'    => ['Profil Saya', $base . '/auth/profile.php'],
        ];
    }

    if ($role === 'peserta') {
        return [
            'home'       => ['Beranda', $base . '/quiz/index.php'],
            'helpdesk'   => ['Bantuan', $base . '/quiz/helpdesk.php'],
            'notifikasi' => ['Notifikasi', $base . '/auth/notifikasi.php'],
            'settings'   => ['Pengaturan', $base . '/auth/settings.php'],
            'profile'    => ['Akun', $base . '/auth/profile.php'],
        ];
    }

    return [
        'home'     => ['Beranda', $base . '/quiz/index.php'],
        'login'    => ['Masuk', $base . '/auth/login.php'],
        'register' => ['Daftar', $base . '/auth/register.php'],
    ];
}

function layout_header(array $o): void
{
    $base    = $o['base'] ?? '..';
    $role    = $o['role'] ?? 'guest';
    $active  = $o['active'] ?? '';
    $tickets = (int)($o['tickets'] ?? 0);
    $items   = nav_items($role, $base, $tickets);
    $user    = $o['user'] ?? ($_SESSION['nama'] ?? '');
    $show_feedback = $role !== 'guest' && (!empty($o['feedback']) || $role === 'peserta');
    $main_class = trim(($o['main_class'] ?? '') . ' ' . (($o['main_narrow'] ?? false) ? 'container-narrow' : ''));
    $main_style = $o['main_style'] ?? '';

    // Bahasa & pengaturan situs (sekali per request)
    hq_handle_lang_switch();
    hq_site_settings();
    $lang = hq_lang();
    $siteName = (string)(hq_site_settings()['site_name'] ?? '') ?: 'Kearsipan Kutai Kartanegara';
    $notifCount = hq_unread_notif_count();
    ?>
<!DOCTYPE html>
<html lang="<?= $lang === 'en' ? 'en' : 'id' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($o['title']) ?> &middot; <?= e($siteName) ?></title>
<meta name="description" content="Sistem kuesioner dan evaluasi kearsipan Dinas Kearsipan dan Perpustakaan Kabupaten Kutai Kartanegara.">
<meta name="theme-color" media="(prefers-color-scheme: light)" content="#0f4d33">
<meta name="theme-color" media="(prefers-color-scheme: dark)" content="#082d1e">
<link rel="icon" type="image/png" href="<?= $base ?>/assets/img/logo-kukar.png">
<link rel="manifest" href="<?= $base ?>/manifest.json">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Serif+4:opsz,wght@8..60,400;8..60,600&display=swap">
<link rel="stylesheet" href="<?= $base ?>/assets/style.css">
<link rel="stylesheet" href="<?= $base ?>/assets/professional.css">
<script>(function(){try{var t=localStorage.getItem('theme');if(t){document.documentElement.dataset.theme=t;}else if(window.matchMedia&&matchMedia('(prefers-color-scheme: dark)').matches){document.documentElement.dataset.theme='dark';}}catch(e){}})();</script>
<?= $o['head'] ?? '' ?>
</head>
<body>

<!-- Splash sinematik sekali per sesi browser -->
<div id="splash" class="splash" aria-hidden="true">
    <div class="splash-core">
        <img class="splash-logo" src="<?= $base ?>/assets/img/logo-kukar.png" alt="" width="72" height="72">
        <div class="splash-title">Kuis Arsip</div>
        <div class="splash-sub">Sistem Kuesioner &amp; Evaluasi Kearsipan</div>
        <div class="splash-bar"><span></span></div>
    </div>
</div>

<a class="skip-link" href="#main"><?= __('Lewati ke isi utama') ?></a>

<header class="masthead" role="banner">
    <div class="masthead-inner">
        <img class="masthead-logo" src="<?= $base ?>/assets/img/logo-kukar.png" alt="Lambang Kabupaten Kutai Kartanegara" width="48" height="48">
        <div class="masthead-id">
            <span class="org">Dinas Kearsipan dan Perpustakaan &middot; Kabupaten Kutai Kartanegara</span>
            <span class="system">Sistem Kuesioner & Evaluasi Kearsipan</span>
        </div>
        <div class="masthead-side">
            <?php if ($role === 'guest'): ?>
                <span class="who"><?= __('Mode publik') ?></span>
            <?php else: ?>
                <span class="who"><?= $role === 'admin' ? __('Administrator') : __('Peserta') ?>: <strong><?= e($user) ?></strong></span>
                <?php if ($role !== 'admin'): ?>
                    <a class="icon-btn" href="<?= $base ?>/auth/notifikasi.php" aria-label="<?= e(__('Notifikasi')) ?>" title="<?= e(__('Notifikasi')) ?>">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        <?php if ($notifCount > 0): ?><span class="notif-dot"><?= $notifCount > 9 ? '9+' : $notifCount ?></span><?php endif; ?>
                    </a>
                <?php endif; ?>
                <?php if ($show_feedback): ?>
                    <button type="button" class="btn-sm btn-quiet" data-open-dialog="feedbackDialog" aria-haspopup="dialog"><?= __('Saran') ?></button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</header>

<nav class="topbar" aria-label="Navigasi utama" role="navigation">
    <div class="topbar-inner">
        <button type="button" class="nav-toggle" aria-expanded="false" aria-controls="navLinks" aria-label="Buka menu navigasi">
            <span class="nav-toggle-icon" aria-hidden="true"></span>
        </button>
        <div class="nav-links" id="navLinks" role="menubar">
            <?php foreach ($items as $key => $item): ?>
                <a href="<?= $item[1] ?>" role="menuitem"<?= $key === $active ? ' aria-current="page"' : '' ?>>
                    <?= e(__($item[0])) ?><?= !empty($item[2]) ? '<span class="nav-count" title="tiket terbuka">' . (int)$item[2] . '</span>' : '' ?><?= ($key === 'notifikasi' && $notifCount > 0) ? ' <span class="nav-badge">' . $notifCount . '</span>' : '' ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="nav-tools">
            <button type="button" class="theme-toggle" data-theme-toggle aria-pressed="false" aria-label="<?= e(__('Mode gelap')) ?>"><?= __('Mode gelap') ?></button>
            <a class="lang-toggle" href="<?= e(hq_lang_switch_url($lang === 'id' ? 'en' : 'id')) ?>" aria-label="<?= e(__('Ganti bahasa')) ?>" title="<?= e(__('Ganti bahasa')) ?>"><?= $lang === 'id' ? 'EN' : 'ID' ?></a>
            <?php if ($role !== 'guest'): ?>
                <a class="btn-sm btn-quiet" href="<?= $base ?>/auth/logout.php"><?= __('Keluar') ?></a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<main id="main" class="container<?= $main_class ? ' ' . e($main_class) : '' ?>" role="main"<?= $main_style ? ' style="' . e($main_style) . '"' : '' ?>>
<?php
}

function layout_footer(array $o = []): void
{
    $base = $o['base'] ?? '..';
    $map  = !empty($o['map']);
    ?>
</main>

<footer class="site-footer" role="contentinfo">
    <div class="footer-container">
        <div class="footer-col">
            <h2><?= __('Alamat Kantor') ?></h2>
            <p class="footer-addr">Jl. Panji No.47, Panji, Kecamatan Tenggarong, Kabupaten Kutai Kartanegara, Kalimantan Timur 75513</p>
            <?php if ($map): ?>
            <div class="map-embed">
                <iframe src="https://www.google.com/maps?q=Jl.+Panji+No.47,+Panji,+Kec.+Tenggarong,+Kabupaten+Kutai+Kartanegara,+Kalimantan+Timur+75513&output=embed" title="Peta lokasi kantor Diarpus Kukar" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
            <?php endif; ?>
        </div>
        <div class="footer-col">
            <h2><?= __('Kontak Resmi') ?></h2>
            <p><?= __('Surel') ?>: <a href="mailto:diarpuskukar@gmail.com">diarpuskukar@gmail.com</a></p>
            <p><?= __('Situs') ?>: <a href="https://diarpus.kukarkab.go.id/" target="_blank" rel="noopener">diarpus.kukarkab.go.id</a></p>
        </div>
        <div class="footer-col">
            <h2><?= __('Kanal Informasi') ?></h2>
            <ul class="footer-links">
                <li><a href="https://www.instagram.com/diarpus_kukar/" target="_blank" rel="noopener">Instagram</a></li>
                <li><a href="https://www.facebook.com/dinaskearsipan.danperpustakaan.9/" target="_blank" rel="noopener">Facebook</a></li>
                <li><a href="https://www.youtube.com/@diarpuskukar216" target="_blank" rel="noopener">YouTube</a></li>
                <li><a href="<?= $base ?>/quiz/verify.php"><?= __('Verifikasi sertifikat') ?></a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="credit-row">
            <span class="credit-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                &copy; <?= date('Y') ?> Dinas Kearsipan dan Perpustakaan Kabupaten Kutai Kartanegara (Bidang P2A). Seluruh hak cipta dilindungi - perangkat lunak milik instansi dan dilarang digunakan tanpa izin tertulis.
            </span>
            <span class="credit-separator" aria-hidden="true">|</span>
            <span class="credit-item">
                Dikembangkan oleh <strong>Muhammad Fauzan Raffa Al-Habsy</strong>
                <span class="muted">(Siswa SMK Negeri 1 Tenggarong, RPL Kelas 12)</span>
            </span>
            <span class="credit-separator" aria-hidden="true">|</span>
            <span class="credit-item">
                Hak Cipta Data &amp; Konten: <strong>Varia Fadillah, S.P., M.M.</strong>
                <span class="muted">(Kepala Bidang P2A Diarpus Kukar)</span>
            </span>
            <span class="credit-separator" aria-hidden="true">|</span>
            <a class="credit-link" href="<?= $base ?>/LISENSI.md" target="_blank" rel="noopener">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                Lisensi Lengkap
            </a>
        </div>
    </div>
</footer>

<div id="toastContainer" class="toast-container" aria-live="polite" aria-atomic="true"></div>

<dialog id="feedbackDialog" aria-labelledby="feedbackTitle" class="dialog-panel" role="dialog">
    <form method="post" action="<?= $base ?>/quiz/submit_feedback.php" class="dialog-form">
        <header class="dialog-header">
            <h2 id="feedbackTitle"><?= __('Saran') ?> &amp; Masukan</h2>
            <button type="button" class="dialog-close" data-close-dialog="feedbackDialog" aria-label="Tutup dialog">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </header>
        <div class="dialog-body">
            <p class="muted">Kritik dan saran Anda membantu kami memperbaiki sistem.</p>
            <div class="field">
                <label class="field-label" for="fbmsg">Pesan</label>
                <textarea id="fbmsg" name="message" required maxlength="400" rows="4" placeholder="Tulis saran Anda di sini..."></textarea>
            </div>
            <?= csrf_field() ?>
        </div>
        <footer class="dialog-footer">
            <button type="button" class="btn-sm btn-quiet" data-close-dialog="feedbackDialog">Batal</button>
            <button type="submit" class="btn-sm">Kirim</button>
        </footer>
    </form>
</dialog>

<script src="<?= $base ?>/assets/app.js" defer></script>
<?= $o['scripts'] ?? '' ?>
</body>
</html>
<?php
}