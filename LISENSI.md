# LISENSI & HAK CIPTA — Kuis Arsip (Sistem Kuesioner & Evaluasi Kearsipan)

## Identitas Produk

| Item | Keterangan |
|------|------------|
| Nama Perangkat Lunak | Kuis Arsip — Sistem Kuesioner & Evaluasi Kearsipan (Diarpus Kukar) |
| Tahun Pembuatan | 2026 |
| Situs Resmi | https://kuis-arsip.rf.gd/ |
| Instansi | Dinas Kearsipan dan Perpustakaan (Diarpus) Kabupaten Kutai Kartanegara |

---

## Status Lisensi

Repositori ini disediakan terbuka semata-mata untuk keperluan **dokumentasi dan portofolio**. Perangkat lunak ini **TIDAK open source** - lihat berkas [LICENSE](LICENSE) untuk ketentuan lengkap: dilarang menyalin, memodifikasi, mendistribusikan, men-deploy, atau menggunakannya untuk keperluan komersial tanpa izin tertulis dari pemilik hak cipta.

---

## 1. Pemilik Hak Cipta & Data

> **Nama:** Varia Fadillah, S.P., M.M.
>
> **Jabatan:** Kepala Bidang P2A (Perlindungan dan Penyelamatan Arsip)
>
> **Instansi:** Dinas Kearsipan dan Perpustakaan (Diarpus) Kabupaten Kutai Kartanegara

Seluruh hak kepemilikan atas sistem ini berada pada pihak tersebut di atas selaku pemilik, di bawah naungan Dinas Kearsipan dan Perpustakaan Kabupaten Kutai Kartanegara. Seluruh konten soal, materi kearsipan, branding, dan data kuesioner merupakan kekayaan intelektual instansi.

## 2. Pembuat dan Pengembang

> **Nama:** Muhammad Fauzan Raffa Al-Habsy
>
> **Status:** Siswa Kelas 12, Jurusan RPL (Rekayasa Perangkat Lunak)
>
> **Sekolah:** SMK Negeri 1 Tenggarong
>
> **Peran:** Pembuat & Pengembang Utama (Full-Stack Developer)

Kontak pengembang:

- **Surel Pribadi:** mr.fauzan121314@gmail.com
- **Surel Bisnis:** mahakammoonlightstudio@gmail.com

Pembuat berhak atas atribusi sebagaimana tercantum dalam dokumen ini setiap kali sistem ini digunakan, disalin, atau dikembangkan lebih lanjut.

## 3. Hak yang Diberikan

1. Penggunaan, pengelolaan, dan pengembangan sistem ini dilakukan sepenuhnya oleh pemilik atau pihak yang mendapat izin tertulis dari pemilik.
2. Pemilik berhak melakukan perubahan, penambahan fitur, dan pengembangan lebih lanjut atas sistem ini.
3. Pembuat berhak mencantumkan karya ini sebagai portofolio dengan tetap mencantumkan atribusi kepemilikan sebagaimana dimaksud pada Bagian 1.

## 4. Larangan

1. Dilarang menyalin, mendistribusikan, menjual, atau menyewakan seluruh maupun sebagian kode sumber sistem ini tanpa izin tertulis dari pemilik.
2. Dilarang menghapus, mengubah, atau menyembunyikan atribusi pembuat dan pemilik yang tercantum dalam dokumen ini maupun dalam tampilan sistem.
3. Dilarang menggunakan sistem ini untuk kepentingan komersial atau kepentingan lain di luar keperluan Dinas Kearsipan dan Perpustakaan Kabupaten Kutai Kartanegara tanpa persetujuan pemilik.
4. Dilarang menyalin, mendistribusikan, atau memodifikasi soal/materi tanpa izin tertulis dari pemilik hak cipta.

## 5. Fitur Sistem (Cakupan Karya)

Perangkat lunak ini mencakup fitur-fitur berikut yang termasuk dalam cakupan hak cipta:

1. **Kuesioner berkode ruangan & individu** — verifikasi kuota, jadwal buka/tutup, pemeriksaan duplikasi pengerjaan, dan seed deterministik untuk pengacakan soal/opsi per peserta.
2. **Penilaian & sertifikat** — penilaian otomatis dengan pembahasan per soal, verifikasi sertifikat publik, dan pencetakan hasil.
3. **Papan peringkat & pencapaian** — leaderboard dengan medali, lencana pencapaian (Juara Kelas, Sempurna, Rajin, Lulus), dan riwayat pengerjaan.
4. **Proctoring ringan** — pencatatan perpindahan tab dan blur jendela selama ujian berjalan.
5. **Keamanan berlapis** — CSRF token, prepared statements PDO, rate limiting login berbasis sesi, audit log batch, header keamanan HTTP, dan sandbox kredensial (`config.local.php`).
6. **PWA sinematik** — service worker offline yang aman untuk data sesi, splash screen sekali per sesi, tema gelap/terang dengan animasi circular reveal, dan animasi scroll-reveal.
7. **Panel administrasi lengkap** — kelola pengguna, kuesioner, ruangan, impor/ekspor data, analitik soal, helpdesk tiket, umpan balik, dan jejak audit.

## 6. Riwayat Versi (Changelog)

| Versi | Tanggal | Perubahan |
|-------|---------|----------|
| 1.0 | 2026 | Rilis awal: kuesioner ruangan & individu, sertifikat, panel admin. |
| 1.1 | 2026 | Optimasi performa, perbaikan bug service worker & timer ujian, audit keamanan. |
| 1.2 | 2026 | Lapisan desain sinematik (aurora, glass, glow), splash screen, sistem spasi fluid, profil v2 dengan lencana & statistik, sandbox kredensial `config.local.php`, pembersihan cache SW aman-sesi. |

## 7. Kebijakan Keamanan

1. Kredensial database **tidak pernah** disimpan dalam repositori. File `config/config.local.php` dikecualikan lewat `.gitignore`; templatennya tersedia sebagai `config/config.local.example.php`.
2. Laporan kerentanan dapat disampaikan kepada pengembang melalui surel pada Bagian 2. Respons diupayakan dalam 7 hari kerja.
3. Perubahan kode yang menyentuh autentikasi, sesi, atau akses database wajib mendapat persetujuan pemilik sebelum diterapkan pada instansi produksi.

## 8. Aset Visual & Teknologi Pihak Ketiga

- **Logo Diarpus/IKukar:** Hak cipta Dinas Kearsipan dan Perpustakaan Kabupaten Kutai Kartanegara.
- **Font:** Inter & Source Serif 4 (Google Fonts, SIL Open Font License).

| Teknologi | Lisensi | Sumber |
|-----------|---------|--------|
| PHP 8.x | PHP License | php.net |
| MySQL/MariaDB | GPL v2 | mariadb.com |
| Google Fonts (Inter, Source Serif 4) | SIL OFL | fonts.google.com |
| Chart.js | MIT | chartjs.org |

## 9. Penafian (Disclaimer)

Perangkat lunak ini disediakan **"sebagaimana adanya" (as-is)** tanpa jaminan apa pun, baik tersurat maupun tersirat, termasuk namun tidak terbatas pada jaminan kelayakan untuk keperluan tertentu dan ketidakpelanggaran.

Pengguna bertanggung jawab penuh atas penggunaan aplikasi ini. Pembuat dan pemilik hak cipta **tidak bertanggung jawab** atas:

- Kerugian data atau kerusakan sistem akibat penggunaan aplikasi;
- Ketidaksesuaian materi dengan regulasi kearsipan terbaru;
- Penyalahgunaan data peserta oleh pihak ketiga;
- Gangguan layanan akibat batasan hosting (InfinityFree).

## 10. Kebijakan Privasi & Data Peserta

1. **Data yang dikumpulkan:** NIM/NIP, nama, jawaban kuis, skor, dan waktu pengerjaan.
2. **Tujuan:** Penyimpanan hasil, papan peringkat, analitik admin, dan sertifikat.
3. **Hak Peserta:** Dapat meminta penghapusan data melalui kontak admin.
4. **Cookie:** Hanya session cookie (HTTPOnly, Secure, SameSite=Lax).

## 11. Kontak & Dukungan

**Untuk pertanyaan teknis / pengembangan:**

- Muhammad Fauzan Raffa Al-Habsy — mr.fauzan121314@gmail.com (pribadi) / mahakammoonlightstudio@gmail.com (bisnis)

**Untuk izin penggunaan materi / data / kerja sama:**

- Varia Fadillah, S.P., M.M. — Kepala Bidang P2A Diarpus Kukar
- Surel: diarpuskukar@gmail.com
- Situs: https://diarpus.kukarkab.go.id/
- Alamat: Jl. Panji No.47, Panji, Kecamatan Tenggarong, Kabupaten Kutai Kartanegara, Kalimantan Timur 75513

---

*Dokumen ini berlaku sebagai bukti pembuatan dan kepemilikan perangkat lunak. Apabila terdapat perbedaan penafsiran, keputusan pemilik bersifat final.*

**© 2026 Muhammad Fauzan Raffa Al-Habsy (Kode) + Dinas Kearsipan dan Perpustakaan Kabupaten Kutai Kartanegara (Konten & Data). All Rights Reserved.**
