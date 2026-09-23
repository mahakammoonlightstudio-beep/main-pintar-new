# Kebijakan Keamanan

## Versi yang Didukung

| Versi | Didukung |
| --- | --- |
| 2.0.x | Ya |
| < 2.0 | Tidak |

## Melaporkan Kerentanan

Kirim laporan kerentanan secara **pribadi** ke:

- **mahakammoonlightstudio@gmail.com** (pengembang)
- **diarpuskukar@gmail.com** (pemilik sistem - Diarpus Kab. Kutai Kartanegara, Bidang P2A)

Jangan buat issue publik untuk kerentanan keamanan. Sertakan deskripsi, langkah reproduksi, versi yang terdampak, dan saran perbaikan bila ada.

Respons diupayakan dalam **7 hari kerja**. Perubahan kode yang menyentuh autentikasi, sesi, atau akses database wajib mendapat persetujuan pemilik sebelum diterapkan pada instansi produksi.

## Cakupan

Berlaku untuk kode di repositori ini:

- Injeksi SQL, XSS, CSRF;
- Bypass autentikasi, otorisasi antar role (admin/peserta/guest), atau rate limit login;
- Manipulasi skor, leaderboard, atau state sesi live multiplayer;
- Kebocoran data pengguna (jawaban, statistik, data pribadi);
- Kelemahan endpoint polling (`api/poll.php`) atau transfer data mode tamu.

Di luar cakupan: konfigurasi server/hosting (InfinityFree) dan serangan brute force skala besar.

## Panduan Deploy Aman

- Jalankan di atas HTTPS (InfinityFree menyediakan SSL gratis).
- `includes/config.local.php` tidak boleh ikut ke repositori; templatennya tersedia sebagai `includes/config.local.example.php`.
- Ganti password akun admin default segera setelah instalasi.
- `APP_SECRET` wajib diisi dengan nilai acak yang kuat di `config.local.php`.
