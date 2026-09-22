# Kuis Arsip — Panduan Arsitektur & Developer Guide

> **Kuis Arsip** — Sistem Kuesioner & Evaluasi Kearsipan (folder `htdocs/`)
> Dinas Kearsipan dan Perpustakaan Kabupaten Kutai Kartanegara (Diarpus Kukar)
> Stack: **PHP 8+ native + MySQL/MariaDB + Vanilla JS + CSS murni**.
> Target hosting: InfinityFree (shared, Apache, MariaDB).

Dokumen ini menjelaskan **isi setiap folder, fungsi setiap file, alur data, dan konvensi kode** agar developer baru dapat memperbaiki bug atau mengembangkan fitur tanpa membaca seluruh kode.

---

## 1. Gambaran Umum

Kuis Arsip adalah sistem **kuesioner/evaluasi kearsipan** (bukan kuis santai): peserta mengerjakan survei secara individu atau di ruangan terjadwal, hasil dinilai otomatis, dan **sertifikat** dapat diterbitkan serta diverifikasi publik.

**Dua jenis pengguna:**
| Role | Akses |
|---|---|
| `admin` | Dasbor, kelola peserta/kuesioner/ruangan/soal, analitik, helpdesk, masukan, broadcast, audit, pengaturan sistem, ekspor laporan |
| `peserta` | Beranda kuesioner, kerjakan survei, riwayat, sertifikat, bantuan, notifikasi, pengaturan |

**Fitur lintas pengguna:**
- **Multi-bahasa (ID/EN)** — `includes/lang.php`; prioritas: akun user → cookie → setelan admin.
- **Notifikasi** — broadcast admin; lonceng + badge di header.
- **Pengaturan Sistem** — nama situs, registrasi on/off, mode pemeliharaan, bahasa default (tabel `app_settings`).
- **Preferensi user** — bahasa, tema, data pekerja (jabatan, unit kerja, WhatsApp).
- **Sertifikat** — terbit otomatis setelah lulus, verifikasi publik via `quiz/verify.php`.
- **Audit log** — aksi admin tercatat batch ke `admin_logs`.
- **Keamanan** — CSRF, rate limit login, security headers, prepared statements.

---

## 2. Struktur Folder

```
htdocs/
├── index.php               # Router masuk: redirect ke admin (jika admin) / quiz (lainnya)
├── .htaccess               # Kompresi gzip + cache browser aset statis
├── database-upgrade.sql    # Migrasi fitur baru (preferensi, notifikasi, app_settings)
├── backups/                # Backup database otomatis (dilindungi .htaccess, jangan di-commit)
├── LISENSI.md              # Lisensi
├── manifest.json / sw.js / offline.html   # PWA
│
├── config/                 # ===== KONFIGURASI INTI =====
│   ├── database.php        # Sesi, security headers, PDO (get_pdo), cache query (APCu/session),
│   │                       # CSRF, rate limit, audit log, shuffle opsi deterministik,
│   │                       # is_logged_in/require_login/require_admin, hq_maintenance_gate()
│   └── config.local.php    # Kredensial DB (TIDAK di-commit; ada .example)
│
├── includes/
│   ├── layout.php          # nav_items(), layout_header() / layout_footer() (seluruh kerangka HTML)
│   ├── backup.php          # Helper backup: dump SQL, simpan/list/hapus, auto-backup harian
│   ├── lang.php            # Sistem i18n: __(), hq_lang(), hq_handle_lang_switch(), hq_site_settings()
│   └── lang/id.php, en.php # Kamus (kunci = teks ID asli; en.php berisi terjemahan)
│
├── auth/                   # ===== AKUN =====
│   ├── login.php           # Login NIM/NIP + password (rate limit, audit, simpan preferensi bahasa)
│   ├── register.php        # Registrasi (dikendalikan is_registration_open; ada maintenance gate)
│   ├── logout.php
│   ├── profile.php         # Profil & ubah password
│   ├── settings.php        # PENGATURAN: bahasa, tema, data pekerja (jabatan/unit/WA)
│   ├── notifikasi.php      # Inbox notifikasi (tandai dibaca / hapus)
│   ├── forgot_password.php / reset_password.php
│
├── quiz/                   # ===== ALUR PESERTA =====
│   ├── index.php           # Beranda: daftar kuesioner aktif, masuk ruangan, riwayat, leaderboard, badge
│   ├── start.php           # Halaman pengerjaan soal (opsi di-shuffle deterministik per peserta)
│   ├── result.php          # Penilaian & hasil (+ koreksi)
│   ├── certificate.php     # Sertifikat peserta
│   ├── verify.php          # Verifikasi sertifikat publik
│   ├── helpdesk.php        # Tiket bantuan peserta
│   └── submit_feedback.php # Kirim saran (dialog di semua halaman)
│
├── admin/                  # ===== PANEL ADMIN =====
│   ├── index.php           # Dasbor: statistik, toggle pendaftaran
│   ├── users.php           # Kelola peserta
│   ├── surveys.php         # Kelola kuesioner
│   ├── questions.php       # Kelola soal per kuesioner
│   ├── rooms.php           # Kelola ruangan terjadwal (kode, kuota)
│   ├── import_questions.php / import_users.php   # Impor massal
│   ├── quiz_analytics.php  # Analitik soal (tingkat kesulitan, distribusi jawaban)
│   ├── report.php / export.php / export_users.php# Laporan & ekspor (Excel XML)
│   ├── bulk_certificate.php / reset_result.php / result_detail.php
│   ├── helpdesk.php / feedback.php               # Tiket & masukan
│   ├── broadcast.php       # Broadcast notifikasi (semua/peserta/user tertentu) + riwayat
│   ├── settings.php        # PENGATURAN SISTEM (app_settings: situs, registrasi, maintenance, bahasa)
│   ├── audit_logs.php      # Jejak audit
│   ├── backup.php          # Pusat backup: unduh SQL (lengkap/data asli), simpan ke server, riwayat
│   └── restore.php         # Restore DB: konfirmasi RESTORE + snapshot keamanan otomatis
│
├── api/news.php            # Endpoint berita/pengumuman
└── assets/
    ├── style.css           # Desain sistem lengkap (hijau tua #0f4d33, emas #a67c15, dark mode)
    ├── professional.css    # Layer polemik profesional: tipografi, panel, tabel, notifikasi
    ├── app.js              # Tema, splash, nav mobile, dialog, toast, konfirmasi, toggle target
    └── img/                # Logo & aset
```

---

## 3. Alur Data Utama

### 3.1 Kuesioner Individu
```
quiz/index.php (pilih kuesioner aktif) → quiz/start.php (sesi jawaban, opsi di-shuffle
dengan seed user|survey|room — result.php menghitung dengan seed yang SAMA)
→ quiz/result.php (skor, koreksi) → sertifikat bila lulus.
```

### 3.2 Ruangan Terjadwal
```
admin/rooms.php (buat ruangan: kode, kuesioner, kuota) → peserta memasukkan kode di
quiz/index.php → cek kuota & duplikasi → quiz/start.php → result tercatat dengan room_id.
```

### 3.3 Bahasa
```
layout_header() memanggil hq_handle_lang_switch() (tangani ?lang= → simpan DB/cookie → redirect)
lalu hq_site_settings() (app_settings, set default_lang) → hq_lang() menentukan bahasa aktif.
__() dipakai saat render; kunci tak ditemukan = tampil teks ID asli (fallback aman).
```

### 3.4 Pengaturan Sistem
```
admin/settings.php → UPDATE app_settings (id=1): is_registration_open, is_maintenance,
maintenance_message, site_name, default_lang (kolom baru dari database-upgrade.sql).
hq_maintenance_gate() (dipanggil login/register & halaman publik) → 503 + pesan; admin lolos.
```

### 3.5 Notifikasi
```
admin/broadcast.php → INSERT notifikasi (batch, transaksi) → peserta melihat badge di header
(hq_unread_notif_count) → auth/notifikasi.php (tandai dibaca / hapus).
```

---

## 4. Skema Database (ringkas)

- `users` — nama, nim (unik), password (hash), role, email, last_login, **bahasa, tema, jabatan, unit_kerja, no_whatsapp**
- `surveys` — kuesioner (judul, deskripsi, is_active)
- `questions` — soal per survey: option_a..option_e, correct_option, poin
- `rooms` — room_code, survey_id, max_participants, is_active, jadwal
- `results` — user_id, survey_id, room_id (NULL = individu), score, total_questions, created_at
- `certificates` — nomor sertifikat untuk verifikasi
- `admin_logs` — jejak audit (batch insert)
- `helpdesk_tickets`, `feedback`
- `app_settings` (id=1) — is_registration_open, **is_maintenance, maintenance_message, site_name, default_lang**
- `notifikasi` (migrasi) — user_id, judul, pesan, jenis, dibaca, created_at

**Migrasi:** jalankan `database-upgrade.sql` sekali (aman diulang di MariaDB; MySQL 8: hapus `IF NOT EXISTS` pada ALTER).

---

## 5. Konvensi Kode (anti-spageti)

1. **Urutan wajib tiap halaman:**
   ```php
   require config/database.php;      // sesi + header keamanan + helper
   require includes/layout.php;      // nav + i18n
   // cek is_logged_in()/require_admin() bila perlu
   hq_maintenance_gate();            // halaman publik
   // logika POST: csrf_verify() dulu, lalu redirect
   layout_header([...]); ... layout_footer();
   ```
2. **Escape output** — selalu `e()`; teks UI lewat `__()`.
3. **CSRF & rate limit** — semua POST `csrf_verify()`; login pakai `rate_limit_check()`.
4. **Query** — prepared statements; hindari N+1 (contoh: badge beranda sudah digabung 1 query); agregasi di SQL.
5. **Cache** — data yang jarang berubah pakai `cached_query()` (APCu bila ada, fallback sesi); jangan cache data per-user.
6. **Audit** — aksi admin wajib `audit_log()` (otomatis batch + flush on shutdown).
7. **Shuffle deterministik** — jangan ubah `quiz_shuffle_options()`/`quiz_seed_str()` tanpa memahami bahwa start & result HARUS menghasilkan permutasi identik.
8. **CSS** — pakai token/kelas yang ada; polemik profesional masuk `assets/professional.css`.
9. **JS** — fungsi `initXxx()` dipanggil dari `DOMContentLoaded`; tidak ada inline handler baru.
10. **Aman gagal** — fitur tabel baru dibungkus try-catch agar aplikasi tetap jalan sebelum migrasi.

---

## 6. Fitur → File (peta cepat)

| Fitur | File |
|---|---|
| Ganti bahasa ID/EN | `includes/lang.php` + tombol di `includes/layout.php` |
| Notifikasi user | `auth/notifikasi.php`, badge di `includes/layout.php` |
| Broadcast admin | `admin/broadcast.php` |
| Pengaturan user | `auth/settings.php` |
| Pengaturan sistem | `admin/settings.php` + `hq_maintenance_gate()` di `config/database.php` |
| Shuffle opsi jawaban | `config/database.php` (`quiz_seed_str`, `quiz_shuffle_options`) |
| Sertifikat & verifikasi | `quiz/certificate.php`, `quiz/verify.php` |
| Ekspor laporan | `admin/export.php`, `admin/export_users.php` |
| Audit | `config/database.php` (`audit_log`, `audit_flush`) + `admin/audit_logs.php` |
| Backup & restore DB | `admin/backup.php` + `includes/backup.php` (auto-backup harian dari dasbor) |

## 7. Kecepatan

- `.htaccess`: gzip + cache 1 bulan untuk aset statis; PHP selalu no-store.
- Cache query bawaan: `cached_query()` (APCu bila tersedia).
- Polling/beban berulang sudah dijaga dari penumpukan request.
- Rekomendasi: aktifkan OPcache (aktif secara default di InfinityFree).

## 8. Backup & Restore Database

- **Manual** — `admin/backup.php`: unduh dump SQL (mode *lengkap* atau *data asli saja* tanpa `admin_logs`) atau simpan ke folder `backups/`.
- **Otomatis harian** — InfinityFree tidak punya cron, jadi `hq_backup_maybe_auto()` dipanggil `admin/index.php`: maks. 1× per hari (jarak > 20 jam dari backup terakhir) saat admin membuka dasbor. Retensi 14 berkas terakhir; gagal backup tidak pernah mengganggu dasbor (try-catch + error_log).
- **Notifikasi gagal backup** — bila auto-backup gagal: banner merah muncul di dasbor (`hq_backup_last_error()` membaca `backups/last_error.json`), kejadian tercatat ke `admin_logs` (action `backup_failed`), dan notifikasi `danger` dikirim ke inbox semua admin (lonceng header). Penanda dihapus otomatis saat backup/restore sukses.
- **Restore** — `admin/restore.php`: sumber dari riwayat backup server atau upload `.sql` (maks. 12 MB), wajib ketik `RESTORE` untuk konfirmasi, **snapshot keamanan dibuat otomatis sebelum eksekusi**. Parser `hq_restore_statements()` memecah statement dengan aman (tanda `;` dan kutip di dalam string data diabaikan), log statement gagal ditampilkan. Alternatif: import `.sql` via phpMyAdmin.
- **Keamanan** — folder `backups/` ditutup `.htaccess` (Deny from all) + `index.html`; nama file divalidasi regex (anti path traversal); semua aksi lewat CSRF + `audit_log()`.
