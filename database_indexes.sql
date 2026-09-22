-- ============================================================
-- Indexes optimasi performa query - Sistem Kuesioner Diarpus Kukar
-- Jalankan sekali di phpMyAdmin > SQL tab
--
-- CATATAN:
--  - database.sql terbaru SUDAH memuat semua index ini (KEY idx_...).
--    File ini hanya untuk database lama yang dibuat sebelum ada index.
--  - Bila muncul error #1061 "Duplicate key name", berarti index sudah ada
--    → aman diabaikan / lewati baris tersebut.
-- ============================================================

-- users: login lookup (nim), filter role, leaderboard
ALTER TABLE users ADD INDEX idx_users_nim (nim);
ALTER TABLE users ADD INDEX idx_users_role (role);

-- results: cek status peserta per survey/room, riwayat, leaderboard
ALTER TABLE results ADD INDEX idx_results_user_survey (user_id, survey_id);
ALTER TABLE results ADD INDEX idx_results_user_room (user_id, room_id);
ALTER TABLE results ADD INDEX idx_results_room (room_id);
ALTER TABLE results ADD INDEX idx_results_survey (survey_id);
ALTER TABLE results ADD INDEX idx_results_created (created_at);

-- Leaderboard composite (role + score aggr)
ALTER TABLE results ADD INDEX idx_results_user_score (user_id, score, total_questions);

-- rooms: lookup by code (verifikasi kode ruangan)
ALTER TABLE rooms ADD INDEX idx_rooms_code (room_code);
ALTER TABLE rooms ADD INDEX idx_rooms_active (is_active);

-- surveys: listing aktif
ALTER TABLE surveys ADD INDEX idx_surveys_active (is_active);
ALTER TABLE surveys ADD INDEX idx_surveys_created (created_at);

-- tickets: hitung tiket terbuka, listing per user
ALTER TABLE tickets ADD INDEX idx_tickets_status (status);
ALTER TABLE tickets ADD INDEX idx_tickets_user (user_id);

-- admin_logs: audit listing
ALTER TABLE admin_logs ADD INDEX idx_admin_logs_created (created_at);
ALTER TABLE admin_logs ADD INDEX idx_admin_logs_action (action);

-- feedback: listing
ALTER TABLE feedback ADD INDEX idx_feedback_created (created_at);
