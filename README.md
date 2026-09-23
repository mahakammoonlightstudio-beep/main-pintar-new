# Main Pintar - Kuis Edukatif Kearsipan

Aplikasi kuis edukatif bertema kearsipan untuk Dinas Kearsipan dan Perpustakaan (Diarpus) Kabupaten Kutai Kartanegara. Mendukung mode solo dengan timer dan bonus kecepatan, serta mode live multiplayer dengan kode ruangan. Dibangun dengan PHP 8+ native, MySQL/MariaDB, Vanilla JS, dan CSS murni tanpa framework.

**Live: <https://mainpintar.rf.gd/>**

![PHP](https://img.shields.io/badge/PHP-8%2B-777bb3) ![MySQL](https://img.shields.io/badge/DB-MySQL%20%2F%20MariaDB-4479a1) ![PWA](https://img.shields.io/badge/PWA-ready-5a0fc8)

## Fitur

- **Dua mode permainan**:
  1. **Mode Solo** - sepuluh soal acak per kategori, timer per soal dengan bonus kecepatan, skor tersimpan.
  2. **Mode Live Multiplayer** - host membuat sesi dengan kode ruangan enam karakter; peserta bergabung melalui `join.php`, soal tersinkron melalui polling `api/poll.php` tiap 3-4 detik (aman untuk limit hit shared hosting).
- **Tiga role pengguna**:
  | Role | Akses |
  |---|---|
  | `admin` | Dasbor, kelola soal/kategori/pengguna, monitor sesi live, broadcast, analitik |
  | `peserta` | Main kuis, leaderboard, statistik, notifikasi, pengaturan |
  | `guest` | Main tanpa akun (skor tidak masuk leaderboard) |
- **Multi-bahasa (ID/EN)** - prioritas: akun pengguna, cookie, lalu setelan admin.
- **Kategori dan soal aktif/nonaktif** - arsipkan tanpa menghapus; otomatis disembunyikan dari semua mode.
- **Leaderboard dan statistik** - per peserta dan global.
- **Pengaturan sistem** - nama aplikasi, tagline, registrasi on/off, mode pemeliharaan.
- **PWA** - manifest dan service worker, splash screen, halaman offline.

## Persyaratan

- PHP 8.0 atau lebih baru dengan ekstensi `pdo_mysql`, `mbstring`
- MySQL 5.7 / MariaDB 10.4 atau lebih baru

## Instalasi

1. Salin proyek ke folder web server:

   ```bash
   git clone https://github.com/mahakammoonlightstudio-beep/main-pintar-new.git
   ```

2. Impor skema ke database baru:

   ```bash
   mysql -u USER -p NAMA_DATABASE < database.sql
   mysql -u USER -p NAMA_DATABASE < database-upgrade.sql
   ```

3. Salin template konfigurasi dan isi kredensial:

   ```bash
   cp includes/config.local.example.php includes/config.local.php
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

- [ARCHITECTURE.md](ARCHITECTURE.md) - panduan arsitektur: struktur folder, fungsi tiap berkas, alur data, dan konvensi kode.
- [DEPLOY.md](DEPLOY.md) - langkah deploy ke InfinityFree.
- [lisensi.md](lisensi.md) - status hak cipta dan kepemilikan.

## Struktur Proyek

```
main-pintarNew/
├── index.php          # Beranda: hero, kategori, CTA sesuai status login
├── join.php           # Gabung sesi live via kode ruangan
├── kuis.php           # Halaman permainan
├── hasil.php          # Hasil kuis
├── leaderboard.php    # Papan peringkat
├── main.php           # Mode live multiplayer
├── admin/             # Dasbor, kelola soal/kategori/pengguna, analitik
├── auth/              # Login, register, profil, notifikasi, statistik
├── api/               # poll.php (real-time), health.php
├── includes/          # config.php + config.local.php (tidak di-commit), functions, session, lang
├── assets/            # CSS dan JS
├── database.sql       # Skema dan seed
├── database-upgrade.sql
└── sw.js              # Service worker
```

## Catatan Keamanan

- `includes/config.local.php` tidak pernah di-commit; dicegah lewat `.gitignore`.
- Berkas `includes/hits.txt` (counter runtime) juga dikecualikan dari repositori.

## Lisensi

Hak cipta 2024-2026 - dimiliki Dinas Kearsipan dan Perpustakaan Kab. Kutai Kartanegara. Dikembangkan oleh Muhammad Fauzan Raffa Al-Habsy, SMKN 1 Tenggarong. Lihat [lisensi.md](lisensi.md).
