<?php
/**
 * Kuis Arsip (htdocs) - Sistem Multi-Bahasa (Indonesia / English)
 * Prioritas: preferensi user login (DB/session) > cookie > default aplikasi.
 * Pemakaian: <?= __('Selamat datang') ?> atau <?= __('Hai, :nama', ['nama' => $x]) ?>
 *
 * Kamus memakai kunci = teks Indonesia asli; file en.php berisi terjemahan.
 * Bahasa ID selalu tampil identik dengan sebelum ada sistem ini (fallback = kunci).
 */

if (!defined('MP_LANG_LOADED')) {
    define('MP_LANG_LOADED', true);

    $GLOBALS['HQ_LANG'] = null;
    $GLOBALS['HQ_LANG_STRINGS'] = null;

    /** Muat kamus (includes/lang/id.php | en.php) dengan cache statis per request. */
    function hq_lang_strings(string $lang): array
    {
        static $cache = [];
        if (isset($cache[$lang])) {
            return $cache[$lang];
        }
        $file = __DIR__ . '/lang/' . $lang . '.php';
        $strings = is_file($file) ? (array)require $file : [];
        $cache[$lang] = $strings;
        return $strings;
    }

    /** Default bahasa: sesi pengaturan admin (diset admin/settings.php) > 'id'. */
    function hq_lang_default(): string
    {
        $d = $_SESSION['hq_default_lang'] ?? 'id';
        return in_array($d, ['id', 'en'], true) ? $d : 'id';
    }

    /** Bahasa aktif (booting malas; aman dipanggil berulang tanpa I/O). */
    function hq_lang(): string
    {
        if ($GLOBALS['HQ_LANG'] !== null) {
            return $GLOBALS['HQ_LANG'];
        }
        $lang = hq_lang_default();

        // 1. Preferensi user login
        if (!empty($_SESSION['hq_bahasa']) && in_array($_SESSION['hq_bahasa'], ['id', 'en'], true)) {
            $lang = $_SESSION['hq_bahasa'];
        }
        // 2. Cookie (publik / sebelum login)
        if (!empty($_COOKIE['hq_lang']) && in_array($_COOKIE['hq_lang'], ['id', 'en'], true)) {
            $lang = $_COOKIE['hq_lang'];
        }

        $GLOBALS['HQ_LANG'] = $lang;
        $GLOBALS['HQ_LANG_STRINGS'] = hq_lang_strings($lang);
        return $lang;
    }

    /**
     * Terjemahkan string. Fallback: kunci asli (Indonesia) bila kamus tidak memuatnya.
     * Placeholder: __('Hai, :nama', ['nama' => 'Budi']) => 'Hai, Budi'.
     */
    function __(string $key, array $replace = []): string
    {
        if ($GLOBALS['HQ_LANG_STRINGS'] === null) {
            hq_lang();
        }
        $strings = $GLOBALS['HQ_LANG_STRINGS'] ?? [];
        $out = $strings[$key] ?? $key;
        foreach ($replace as $k => $v) {
            $out = str_replace(':' . $k, (string)$v, $out);
        }
        return $out;
    }

    /** Pilihan bahasa untuk dropdown. */
    function hq_lang_options(): array
    {
        return ['id' => 'Bahasa Indonesia', 'en' => 'English'];
    }

    /** URL untuk tombol ganti bahasa di header. */
    function hq_lang_switch_url(string $to): string
    {
        $path = strtok($_SERVER['REQUEST_URI'] ?? '', '?') ?: '';
        $qs = $_GET;
        $qs['lang'] = $to;
        return $path . '?' . http_build_query($qs);
    }

    /**
     * Tangani ?lang= dari URL: simpan ke DB (user login) + cookie, lalu redirect
     * bersih tanpa parameter. Dipanggil dari layout_header() SEBELUM output apa pun.
     */
    function hq_handle_lang_switch(): void
    {
        if (!isset($_GET['lang'])) {
            return;
        }
        $pilih = strtolower(trim((string)$_GET['lang']));
        if (!in_array($pilih, ['id', 'en'], true)) {
            return;
        }
        if (!empty($_SESSION['user_id'])) {
            try {
                global $pdo;
                $pdo->prepare("UPDATE users SET bahasa = ? WHERE id = ?")->execute([$pilih, (int)$_SESSION['user_id']]);
                $_SESSION['hq_bahasa'] = $pilih;
            } catch (Throwable $e) {
                // kolom bahasa belum dimigrasi — cookie tetap disimpan
            }
        }
        @setcookie('hq_lang', $pilih, [
            'expires'  => time() + 365 * 24 * 60 * 60,
            'path'     => '/',
            'samesite' => 'Lax',
            'httponly' => true,
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        ]);
        $qs = $_GET;
        unset($qs['lang']);
        $target = strtok($_SERVER['REQUEST_URI'] ?? '', '?') ?: '';
        if ($qs) {
            $target .= '?' . http_build_query($qs);
        }
        header('Location: ' . $target);
        exit;
    }

    /** Baca pengaturan situs (app_settings id=1) sekali per request. */
    function hq_site_settings(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $cached = [];
        try {
            global $pdo;
            $row = $pdo->query(
                "SELECT site_name, default_lang, is_registration_open, maintenance_message
                 FROM app_settings WHERE id = 1 LIMIT 1"
            )->fetch();
            if ($row) {
                $cached = $row;
            }
        } catch (Throwable $e) {
            // app_settings / kolom belum ada — pakai default
        }
        // Terapkan default bahasa dari DB ke sesi (dipakai hq_lang_default)
        if (!empty($cached['default_lang']) && in_array($cached['default_lang'], ['id', 'en'], true)) {
            $_SESSION['hq_default_lang'] = $cached['default_lang'];
        }
        return $cached;
    }
}
