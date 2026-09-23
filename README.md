# Kuis Arsip - Sistem Kuesioner dan Evaluasi Kearsipan

Sistem kuesioner dan evaluasi kearsipan untuk Dinas Kearsipan dan Perpustakaan (Diarpus) Kabupaten Kutai Kartanegara. Peserta mengerjakan survei secara individu atau di ruangan terjadwal, hasil dinilai otomatis, dan sertifikat dapat diterbitkan serta diverifikasi publik. Dibangun dengan PHP 8+ native, MySQL/MariaDB, Vanilla JS, dan CSS murni tanpa framework.

**Live: <https://kuis-arsip.rf.gd/>**

![PHP](https://img.shields.io/badge/PHP-8%2B-777bb3) ![MySQL](https://img.shields.io/badge/DB-MySQL%20%2F%20MariaDB-4479a1) ![PWA](https://img.shields.io/badge/PWA-ready-5a0fc8)

## Fitur

- **Kuesioner terstruktur** - pengerjaan individu atau ruangan terjadwal; penilaian otomatis.
- **Sertifikat otomatis** - terbit setelah lulus, dengan verifikasi publik melalui `quiz/verify.php`.
- **Dua role pengguna**:
  | Role | Akses |
  |---|---|
  | `admin` | Dasbor, kelola peserta/kuesioner/ruangan/soal, analitik, helpdesk, broadcast, audit log, ekspor laporan |
  | `peserta` | Beranda kuesioner, pengerjaan survei, riwayat, sertifikat, bantuan, pengaturan |
- **Multi-bahasa (ID/EN)** - prioritas: akun pengguna, cookie, lalu setelan admin.
- **Notifikasi dan broadcast** - lonceng dengan badge di header.
- **Pengaturan sistem** - nama situs, registrasi on/off, mode pemeliharaan, bahasa default.
- **Keamanan** - CSRF, rate limit login, security headers, prepared statements, audit log.
- **PWA** - dapat dipasang dan berfungsi offline.

## Persyaratan

- PHP 8.0 atau lebih baru dengan ekstensi `pdo_mysql`, `mbstring`
- MySQL 5.7 / MariaDB 10.4 atau lebih baru

## Instalasi

1. Salin proyek ke folder web server:

   ```bash
   git clone https://github.com/mahakammoonlightstudio-beep/kuis-arsip.git
   ```

2. Impor skema ke database baru:

   ```bash
   mysql -u USER -p NAMA_DATABASE < database.sql
   mysql -u USER -p NAMA_DATABASE < database-upgrade.sql
   mysql -u USER -p NAMA_DATABASE < database_indexes.sql
   ```

3. Salin template konfigurasi dan isi kredensial:

   ```bash
   cp config/config.local.example.php config/config.local.php
   ```

   Berkas `config.local.php` terdaftar di `.gitignore` dan tidak boleh ikut ke repositori.

4. Buka aplikasi, login dengan akun admin dari seed `database.sql`, dan segera ganti password default.

## Menjalankan secara lokal

PHP bawaan cukup untuk pengembangan:

```bash
php -S localhost:8000
```

Pengguna Laravel Herd (macOS/Windows) dapat memakai biner PHP yang terpasang, misalnya `~/.config/herd/bin/php84/php.exe` pada Windows.

## Dokumentasi

- [ARCHITECTURE.md](ARCHITECTURE.md) - panduan arsitektur: isi tiap folder, fungsi tiap berkas, alur data, dan konvensi kode.
- [LISENSI.md](LISENSI.md) - status hak cipta dan kepemilikan.

## Struktur Proyek

```
kuis arsip/
├── index.php          # Router masuk: admin ke dasbor, lainnya ke quiz
├── admin/             # Modul admin (users, questions, rooms, analitik, dll.)
├── auth/              # Login, register, profil, reset password, notifikasi
├── quiz/              # Pengerjaan survei, hasil, sertifikat, verifikasi
├── api/               # Endpoint berita
├── config/            # database.php + config.local.php (tidak di-commit)
├── includes/          # Layout, bahasa, backup
├── assets/            # CSS dan JS
├── backups/           # Backup DB otomatis (dilindungi .htaccess, tidak di-commit)
├── database.sql       # Skema dan seed
├── database-upgrade.sql
├── database_indexes.sql
└── sw.js              # Service worker
```

## Catatan Keamanan

- `config/config.local.php` tidak pernah di-commit; dicegah lewat `.gitignore`.
- Isi folder `backups/` (dump database berisi data pribadi peserta) juga dikecualikan dari repositori.

## Lisensi

Hak cipta 2026 - dimiliki Dinas Kearsipan dan Perpustakaan Kab. Kutai Kartanegara (pemilik: Varia Fadillah, S.P., M.M.). Dikembangkan oleh Muhammad Fauzan Raffa Al-Habsy, SMKN 1 Tenggarong. Lihat [LISENSI.md](LISENSI.md).
