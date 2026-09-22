-- ============================================================================
-- Kuis Arsip (htdocs) — Struktur Database Lengkap + Data Awal
-- Sistem Kuesioner & Evaluasi Kearsipan — Diarpus Kukar
--
-- Disesuaikan 1:1 dengan phpMyAdmin InfinityFree:
--   8 tabel MyISAM (latin1_swedish_ci) + admin_logs (InnoDB utf8mb4_general_ci)
--   Users: 3 (admin seed), Surveys: 2, Questions: 20, Rooms: 1,
--   Results: 2, Feedback: 2, App_settings: 1, Tickets: 0, Admin_logs: 29
--
-- Kolom setiap tabel mengikuti persis yang dipakai kode PHP aplikasi.
-- Import via phpMyAdmin (pilih database dulu, lalu tab Import).
--
-- CATATAN INFINITYFREE:
--  - InfinityFree memakai MariaDB 10.x dan TIDAK mengizinkan DEFINER,FOREIGN KEYS
--    antar engine campuran (InnoDB -> MyISAM), serta KEY panjang di utf8mb4.
--    File ini memakai MyISAM latin1 persis seperti server live, jadi aman.
--  - Karakter unicode (emoji dll) tidak tersimpan di kolom latin1 — normal,
--    sama seperti server live saat ini.
-- ============================================================================

SET NAMES latin1;
SET time_zone = '+07:00';

-- ============================================================================
-- 1. TABEL USERS (MyISAM, latin1) — login pakai NIM/NIP + password
-- ============================================================================
DROP TABLE IF EXISTS users;
CREATE TABLE users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nama VARCHAR(100) NOT NULL,
    nim VARCHAR(30) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','peserta') NOT NULL DEFAULT 'peserta',
    email VARCHAR(150) DEFAULT NULL,
    photo_path VARCHAR(255) DEFAULT NULL,
    bahasa VARCHAR(5) NOT NULL DEFAULT '',
    tema VARCHAR(10) NOT NULL DEFAULT '',
    jabatan VARCHAR(100) DEFAULT NULL,
    unit_kerja VARCHAR(100) DEFAULT NULL,
    no_whatsapp VARCHAR(20) DEFAULT NULL,
    reset_token VARCHAR(64) DEFAULT NULL,
    reset_token_expiry DATETIME DEFAULT NULL,
    last_login DATETIME DEFAULT NULL,
    last_quiz_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_nim (nim),
    KEY idx_users_nim (nim),
    KEY idx_users_role (role)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- Seed akun admin (password: admin123 — WAJIB diganti setelah login pertama!)
INSERT INTO users (nama, nim, password, role) VALUES
('Administrator Diarpus', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- ============================================================================
-- 2. TABEL APP_SETTINGS (MyISAM, latin1) — selalu ada 1 baris (id = 1)
-- ============================================================================
DROP TABLE IF EXISTS app_settings;
CREATE TABLE app_settings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_name VARCHAR(100) DEFAULT NULL,
    default_lang VARCHAR(5) DEFAULT NULL,
    is_registration_open TINYINT(1) NOT NULL DEFAULT 1,
    is_maintenance TINYINT(1) NOT NULL DEFAULT 0,
    maintenance_message VARCHAR(300) DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

INSERT INTO app_settings (id, site_name, default_lang, is_registration_open, is_maintenance, maintenance_message) VALUES
(1, 'Kuis Arsip - Diarpus Kukar', 'id', 1, 0, 'Sistem sedang dalam pemeliharaan. Silakan kembali lagi nanti.');

-- ============================================================================
-- 3. TABEL SURVEYS (MyISAM, latin1) — kuesioner
-- ============================================================================
DROP TABLE IF EXISTS surveys;
CREATE TABLE surveys (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description VARCHAR(500) DEFAULT NULL,
    type ENUM('survey','ujian') NOT NULL DEFAULT 'survey',
    level ENUM('Dasar','Menengah','Lanjutan','Penting') NOT NULL DEFAULT 'Dasar',
    duration INT NOT NULL DEFAULT 30,
    max_attempts INT NOT NULL DEFAULT 1,
    start_time DATETIME DEFAULT NULL,
    end_time DATETIME DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_surveys_active (is_active),
    KEY idx_surveys_created (created_at)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- Contoh data: sesuaikan dengan kuesioner asli di server live
INSERT INTO surveys (title, description, type, level, duration, max_attempts, is_active) VALUES
('Evaluasi Kearsipan Dasar', 'Kuesioner evaluasi pemahaman kearsipan tingkat dasar.', 'survey', 'Dasar', 30, 1, 1),
('Ujian Resmi Kearsipan', 'Ujian resmi pegawai - jangan ditutup sebelum selesai.', 'ujian', 'Menengah', 45, 0, 1);

-- ============================================================================
-- 4. TABEL QUESTIONS (MyISAM, latin1) — soal per kuesioner (A–E)
-- ============================================================================
DROP TABLE IF EXISTS questions;
CREATE TABLE questions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    survey_id INT UNSIGNED NOT NULL,
    category VARCHAR(100) NOT NULL DEFAULT '',
    question TEXT NOT NULL,
    option_a VARCHAR(255) NOT NULL,
    option_b VARCHAR(255) NOT NULL,
    option_c VARCHAR(255) NOT NULL,
    option_d VARCHAR(255) NOT NULL,
    option_e VARCHAR(255) DEFAULT NULL,
    correct_answer ENUM('A','B','C','D','E') NOT NULL DEFAULT 'A',
    explanation TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_questions_survey (survey_id)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- Contoh soal (2 per kuesioner). Tambahkan sendiri via admin/questions.php
INSERT INTO questions (survey_id, category, question, option_a, option_b, option_c, option_d, option_e, correct_answer, explanation) VALUES
(1, 'Dasar', 'Arsip dinamis adalah arsip yang...', 'Digunakan langsung dalam kegiatan pencipta arsip', 'Sudah tidak digunakan lagi', 'Disimpan di lembaga kearsipan daerah', 'Telah dimusnahkan', NULL, 'A', 'Menurut UU No. 43 Tahun 2009, arsip dinamis digunakan langsung dalam kegiatan pencipta arsip.'),
(1, 'Dasar', 'Kepanjangan dari JRA adalah...', 'Jadwal Retensi Arsip', 'Jaringan Retensi Arsip', 'Jadwal Retensi Administrasi', 'Jurnal Retensi Aktif', NULL, 'A', 'JRA adalah Jadwal Retensi Arsip.'),
(2, 'Menengah', 'Lembaga penampung arsip statis daerah adalah...', 'Lembaga Kearsipan Daerah (LKD)', 'Badan Pusat Statistik', 'Kementerian Dalam Negeri', 'Dinas Pendidikan', NULL, 'A', 'LKD menampung arsip statis daerah.'),
(2, 'Menengah', 'Nilai guna utama arsip statis adalah...', 'Nilai guna kesejarahan', 'Nilai guna informasional biasa', 'Nilai guna administratif harian', 'Nilai guna keuangan', NULL, 'A', 'Arsip statis bernilai guna kesejarahan.');

-- ============================================================================
-- 5. TABEL ROOMS (MyISAM, latin1) — ruangan ujian terjadwal
-- ============================================================================
DROP TABLE IF EXISTS rooms;
CREATE TABLE rooms (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    room_name VARCHAR(100) NOT NULL,
    room_code CHAR(6) NOT NULL,
    max_participants INT NOT NULL DEFAULT 30,
    survey_id INT UNSIGNED NOT NULL,
    start_time DATETIME DEFAULT NULL,
    end_time DATETIME DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rooms_code (room_code),
    KEY idx_rooms_code (room_code),
    KEY idx_rooms_active (is_active)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- Contoh ruangan — sesuaikan dengan data asli di server live
INSERT INTO rooms (room_name, room_code, max_participants, survey_id, is_active) VALUES
('Ruangan Ujian Contoh', 'ABC123', 30, 1, 1);

-- ============================================================================
-- 6. TABEL RESULTS (MyISAM, latin1) — hasil pengerjaan kuesioner
-- ============================================================================
DROP TABLE IF EXISTS results;
CREATE TABLE results (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    survey_id INT UNSIGNED NOT NULL,
    room_id INT UNSIGNED DEFAULT NULL,
    score INT NOT NULL DEFAULT 0,
    total_questions INT NOT NULL DEFAULT 0,
    answers_json MEDIUMTEXT DEFAULT NULL,
    proctoring_log MEDIUMTEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_results_user_survey (user_id, survey_id),
    KEY idx_results_user_room (user_id, room_id),
    KEY idx_results_room (room_id),
    KEY idx_results_survey (survey_id),
    KEY idx_results_created (created_at)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- ============================================================================
-- 7. TABEL FEEDBACK (MyISAM, latin1) — saran/masukan pengguna
-- ============================================================================
DROP TABLE IF EXISTS feedback;
CREATE TABLE feedback (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED DEFAULT NULL,
    name VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_feedback_created (created_at)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- Contoh masukan — sesuaikan/sesuaikan hapus bila tidak perlu
INSERT INTO feedback (user_id, name, message) VALUES
(1, 'Administrator', 'Selamat datang! Gunakan form saran di kanan bawah untuk masukan.');

-- ============================================================================
-- 8. TABEL TICKETS (MyISAM, latin1) — helpdesk (0 baris di server live)
-- ============================================================================
DROP TABLE IF EXISTS tickets;
CREATE TABLE tickets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    admin_reply TEXT DEFAULT NULL,
    status ENUM('open','answered','closed') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_tickets_status (status),
    KEY idx_tickets_user (user_id)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- ============================================================================
-- 9. TABEL ADMIN_LOGS (InnoDB, utf8mb4_general_ci — sesuai server live!)
--    Batch insert audit: admin_id bisa NULL (aksi tamu), INDEX, bukan UNIQUE
--    (batch yang sama bisa berisi >1 baris per admin+aksi).
-- ============================================================================
DROP TABLE IF EXISTS admin_logs;
CREATE TABLE admin_logs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id INT UNSIGNED DEFAULT NULL,
    admin_nama VARCHAR(100) NOT NULL DEFAULT 'unknown',
    action VARCHAR(50) NOT NULL,
    target_table VARCHAR(64) NOT NULL DEFAULT '',
    target_id VARCHAR(64) NOT NULL DEFAULT '',
    notes VARCHAR(500) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_admin_logs_admin (admin_id),
    KEY idx_admin_logs_action (action),
    KEY idx_admin_logs_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================================
-- SELESAI. Setelah import:
--  1. Login admin: NIM 'admin' / password 'admin123' (GANTI SEGERA di profil).
--  2. Jalankan htdocs/database-upgrade.sql HANYA jika belum (menambah kolom
--     users/app_settings yang sudah disertakan di sini — jadi tidak perlu).
--  3. Sesuaikan contoh surveys/questions/rooms/feedback dengan data asli.
-- ============================================================================
