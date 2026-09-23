# Kebijakan Keamanan

## Versi yang Didukung

| Versi | Didukung |
| --- | --- |
| 1.x | Ya |
| < 1.0 | Tidak |

## Melaporkan Kerentanan

Kirim laporan kerentanan secara **pribadi** ke:

- **mahakammoonlightstudio@gmail.com** (pengembang)
- **diarpuskukar@gmail.com** (pemilik sistem - Diarpus Kab. Kutai Kartanegara, Bidang P2A)

Jangan buat issue publik untuk kerentanan keamanan. Sertakan deskripsi, langkah reproduksi, versi yang terdampak, dan saran perbaikan bila ada.

Respons diupayakan dalam **7 hari kerja**. Perubahan kode yang menyentuh autentikasi, sesi, atau akses database wajib mendapat persetujuan pemilik sebelum diterapkan pada instansi produksi.

## Cakupan

Berlaku untuk kode di repositori ini:

- Injeksi SQL, XSS, CSRF;
- Bypass autentikasi, otorisasi antar role, atau rate limit login;
- Kebocoran data peserta (jawaban, skor, data pribadi);
- Manipulasi hasil penilaian, sertifikat, atau verifikasi publik;
- Kelemahan proctoring atau seed pengacakan soal.

Di luar cakupan: konfigurasi server/hosting (InfinityFree) dan serangan brute force skala besar.

## Panduan Deploy Aman

- Jalankan di atas HTTPS (InfinityFree menyediakan SSL gratis).
- `config/config.local.php` tidak boleh ikut ke repositori; templatennya tersedia sebagai `config/config.local.example.php`.
- Ganti password akun admin default segera setelah instalasi.
- Folder `backups/` dilindungi `.htaccess` (deny all) dan isinya tidak boleh dibagikan.
