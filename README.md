# Main Pintar — Kuis Edukatif Kearsipan

> Kuis edukatif bertema kearsipan untuk **Dinas Kearsipan dan Perpustakaan (Diarpus) Kabupaten Kutai Kartanegara**.
> Stack: **PHP 8+ native (tanpa framework) + MySQL/MariaDB + Vanilla JS + CSS murni** — target hosting InfinityFree.

🔗 **Situs resmi:** https://mainpintar.rf.gd/

## ✨ Fitur

- **Dua mode permainan**:
  1. **Mode Solo** — 10 soal acak per kategori, timer per soal + **bonus kecepatan**, skor tersimpan.
  2. **Mode Live Multiplayer** — host membuat sesi dengan **kode ruangan 6 karakter**; peserta join via `join.php`, soal tersinkron real-time via polling `api/poll.php` (3–4 detik, aman untuk limit hit InfinityFree).
- **Tiga role pengguna**:
  | Role | Akses |
  |---|---|
  | `admin` | Dasbor, kelola soal/kategori/user, monitor sesi live, broadcast, analitik |
  | `peserta` | Main kuis, leaderboard, statistik, notifikasi, pengaturan |
  | `guest` | Main tanpa akun (skor tidak masuk leaderboard) |
- **Multi-bahasa (ID/EN)** — prioritas: akun user → cookie → setelan admin.
- **Kategori & soal aktif/nonaktif** — arsipkan tanpa hapus; otomatis disembunyikan dari semua mode.
- **Leaderboard & statistik** — per peserta dan global.
- **Pengaturan sistem** — nama aplikasi, tagline, registrasi on/off, mode pemeliharaan.
- **PWA** — manifest + service worker, splash screen, offline page.

## 🛠️ Teknologi

- PHP 8+ native (tanpa framework)
- MySQL / MariaDB (PDO)
- Vanilla JavaScript, CSS murni
- Service Worker + Web App Manifest (PWA)

## 🚀 Menjalankan Secara Lokal (XAMPP)

1. Salin folder ini ke `htdocs/`.
2. Import `database.sql` ke database baru via phpMyAdmin, lalu jalankan `database-upgrade.sql`.
3. Salin template konfigurasi:

   ```bash
   cp includes/config.local.example.php includes/config.local.php
   ```

   lalu isi `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.
4. Buka `http://localhost/main-pintarNew/` dan login dengan akun admin dari seed `database.sql` — **segera ganti password default setelah login**.

## 📚 Dokumentasi

- **[ARCHITECTURE.md](ARCHITECTURE.md)** — panduan lengkap: struktur folder, fungsi tiap file, alur data, dan konvensi kode.
- **[DEPLOY.md](DEPLOY.md)** — langkah deploy ke InfinityFree.
- **[lisensi.md](lisensi.md)** — status hak cipta & kepemilikan.

## 📁 Struktur Utama

```
main-pintarNew/
├── index.php          # Beranda: hero, kategori, CTA sesuai status login
├── join.php           # Join sesi live via kode ruangan
├── kuis.php           # Halaman permainan
├── hasil.php          # Hasil kuis
├── leaderboard.php    # Papan peringkat
├── main.php           # Mode live multiplayer
├── admin/             # Dasbor, kelola soal/kategori/user, analitik
├── auth/              # Login, register, profil, notifikasi, statistik
├── api/               # poll.php (real-time), health.php
├── includes/          # config.php + config.local.php (tidak di-commit), functions, session, lang
├── assets/            # CSS & JS
├── database.sql       # Skema + seed
├── database-upgrade.sql
└── sw.js              # Service worker
```

## ☁️ Deploy ke InfinityFree

Panduan lengkap ada di **[DEPLOY.md](DEPLOY.md)** — ringkasnya: upload semua file ke `htdocs/`, buat `includes/config.local.php` langsung di server, import kedua file SQL, selesai.

## 🔒 Catatan Keamanan

- `includes/config.local.php` **tidak pernah di-commit** — dicegah lewat `.gitignore`.
- File `includes/hits.txt` (counter runtime) juga dikecualikan dari repo.

---

Hak cipta © 2024–2026 — dimiliki oleh **Dinas Kearsipan dan Perpustakaan Kab. Kutai Kartanegara**. Dikembangkan oleh Muhammad Fauzan Raffa Al-Habsy — SMKN 1 Tenggarong, RPL. Lihat [lisensi.md](lisensi.md).
