-- ============================================================================
-- Kuis Arsip (htdocs) - UPGRADE DATABASE — jalankan sekali di phpMyAdmin
-- Fitur baru: Preferensi user (bahasa/tema), Data Pekerja, Notifikasi,
--             Pengaturan Sistem (app_settings diperluas)
--
-- CATATAN PENTING (InfinityFree):
--  - Tabel users di server live bertipe MyISAM. FOREIGN KEY dari tabel InnoDB
--    ke tabel MyISAM TIDAK didukung (errno 150) — versi lama file ini gagal
--    persis di situ. Tabel notifikasi sekarang dibuat TANPA foreign key.
--  - Kolom users & app_settings di file ini SUDAH TERMASUK di database.sql
--    terbaru. File ini hanya perlu dijalankan jika database dibuat dari
--    schema LAMA (sebelum ada fitur preferensi/notifikasi/pengaturan).
--  - MariaDB (InfinityFree) mendukung ADD COLUMN IF NOT EXISTS → aman
--    dijalankan berulang. MySQL 8: hapus "IF NOT EXISTS" tiap baris ADD COLUMN.
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- 1. PENGGUNA: preferensi + data pekerja
--    (lewati jika users sudah punya kolom ini — IF NOT EXISTS akan melewati)
-- ----------------------------------------------------------------------------
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS bahasa VARCHAR(5) NOT NULL DEFAULT '' AFTER role,
    ADD COLUMN IF NOT EXISTS tema VARCHAR(10) NOT NULL DEFAULT '' AFTER bahasa,
    ADD COLUMN IF NOT EXISTS jabatan VARCHAR(100) NULL DEFAULT NULL AFTER tema,
    ADD COLUMN IF NOT EXISTS unit_kerja VARCHAR(100) NULL DEFAULT NULL AFTER jabatan,
    ADD COLUMN IF NOT EXISTS no_whatsapp VARCHAR(20) NULL DEFAULT NULL AFTER unit_kerja;

-- ----------------------------------------------------------------------------
-- 2. NOTIFIKASI: broadcast admin + pesan sistem
--    InnoDB utf8mb4 TANPA foreign key (users live = MyISAM, FK akan gagal).
--    Aplikasi tidak bergantung pada FK: pembersihan data lama tidak otomatis,
--    baris notifikasi milik user terhapus tidak berdampak ke user lain.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifikasi (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    judul VARCHAR(150) NOT NULL,
    pesan TEXT NOT NULL,
    jenis ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
    dibaca TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notif_user (user_id, dibaca)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- 3. APP_SETTINGS: pengaturan sistem tambahan (id = 1 sudah ada)
--    Kolom site_name & default_lang dipakai Pengaturan Sistem.
-- ----------------------------------------------------------------------------
ALTER TABLE app_settings
    ADD COLUMN IF NOT EXISTS site_name VARCHAR(100) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS default_lang VARCHAR(5) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS is_maintenance TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS maintenance_message VARCHAR(300) NULL DEFAULT NULL;

-- Isi default nama situs & bahasa jika masih kosong (id = 1)
UPDATE app_settings
SET site_name = COALESCE(site_name, 'Kuis Arsip - Diarpus Kukar'),
    default_lang = COALESCE(default_lang, 'id')
WHERE id = 1;

-- Selesai. Struktur baru otomatis dipakai aplikasi tanpa perlu ubah kode lain.
