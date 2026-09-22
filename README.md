# Kuis Arsip — Sistem Kuesioner & Evaluasi Kearsipan

> Sistem kuesioner & evaluasi kearsipan untuk **Dinas Kearsipan dan Perpustakaan (Diarpus) Kabupaten Kutai Kartanegara**.
> Stack: **PHP 8+ native + MySQL/MariaDB + Vanilla JS + CSS murni** — tanpa framework, target hosting InfinityFree.

🔗 **Situs resmi:** https://kuis-arsip.rf.gd/

## ✨ Fitur

- **Kuesioner kearsipan terstruktur** — peserta mengerjakan survei secara individu atau di **ruangan terjadwal**; hasil dinilai otomatis.
- **Sertifikat otomatis** — terbit setelah lulus, dengan **verifikasi publik** via `quiz/verify.php`.
- **Dua role pengguna**:
  | Role | Akses |
  |---|---|
  | `admin` | Dasbor, kelola peserta/kuesioner/ruangan/soal, analitik, helpdesk, broadcast, audit log, ekspor laporan |
  | `peserta` | Beranda kuesioner, kerjakan survei, riwayat, sertifikat, bantuan, pengaturan |
- **Multi-bahasa (ID/EN)** — prioritas: akun user → cookie → setelan admin.
- **Notifikasi & broadcast** — lonceng + badge di header.
- **Pengaturan sistem** — nama situs, registrasi on/off, mode pemeliharaan, bahasa default.
- **Keamanan** — CSRF, rate limit login, security headers, prepared statements, audit log batch.
- **PWA** — installable & offline-ready.

## 🛠️ Teknologi

- PHP 8+ native (tanpa framework)
- MySQL / MariaDB
- Vanilla JavaScript, CSS murni
- Service Worker + Web App Manifest (PWA)

## 🚀 Menjalankan Secara Lokal (XAMPP)

1. Salin folder ini ke `htdocs/`.
2. Import `database.sql` ke database baru via phpMyAdmin, lalu jalankan `database-upgrade.sql` dan `database_indexes.sql`.
3. Salin template konfigurasi:

   ```bash
   cp config/config.local.example.php config/config.local.php
   ```

   lalu isi `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.
4. Buka `http://localhost/kuis arsip/` dan login dengan akun admin dari seed `database.sql` — **segera ganti password default setelah login**.

## 📚 Dokumentasi

- **[ARCHITECTURE.md](ARCHITECTURE.md)** — panduan lengkap: isi setiap folder, fungsi setiap file, alur data, dan konvensi kode. Wajib dibaca sebelum mengembangkan.
- **[LISENSI.md](LISENSI.md)** — status hak cipta & kepemilikan.

## 📁 Struktur Utama

```
kuis arsip/
├── index.php          # Router masuk: admin → dasbor, lainnya → quiz
├── admin/             # Modul admin (users, questions, rooms, analitik, dst.)
├── auth/              # Login, register, profil, reset password, notifikasi
├── quiz/              # Pengerjaan survei, hasil, sertifikat, verifikasi
├── api/               # Endpoint berita
├── config/            # database.php + config.local.php (tidak di-commit)
├── includes/          # Layout, bahasa, backup
├── assets/            # CSS & JS
├── backups/           # Backup DB otomatis (dilindungi .htaccess, tidak di-commit)
├── database.sql       # Skema + seed
├── database-upgrade.sql & database_indexes.sql
└── sw.js              # Service worker
```

## ☁️ Deploy ke InfinityFree

1. Upload semua file ke `htdocs/`, buat `config/config.local.php` langsung di server.
2. Buat database MySQL, import ketiga file SQL.
3. Aktifkan mode pemeliharaan dulu saat setup awal jika perlu (dari Pengaturan Sistem).

## 🔒 Catatan Keamanan

- `config/config.local.php` **tidak pernah di-commit** — dicegah lewat `.gitignore`.
- Isi folder `backups/` (dump database berisi data pribadi peserta) juga dikecualikan dari repo.

---

Hak cipta © 2026 — dimiliki oleh **Dinas Kearsipan dan Perpustakaan Kab. Kutai Kartanegara** (pemilik: Varia Fadillah, S.P., M.M.). Dikembangkan oleh Muhammad Fauzan Raffa Al-Habsy — SMKN 1 Tenggarong, RPL. Lihat [LISENSI.md](LISENSI.md).
