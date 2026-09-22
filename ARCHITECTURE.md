# Main Pintar — Panduan Arsitektur & Developer Guide

> **Main Pintar** — Kuis Edukatif Kearsipan Kutai Kartanegara
> Dinas Kearsipan dan Perpustakaan Kabupaten Kutai Kartanegara (Diarpus Kukar)
> Stack: **PHP 8+ native (tanpa framework) + MySQL/MariaDB + Vanilla JS + CSS murni**.
> Target hosting: InfinityFree (shared, Apache, MariaDB).

Dokumen ini menjelaskan **isi setiap folder, fungsi setiap file, alur data, dan konvensi kode** agar developer baru dapat memperbaiki bug atau mengembangkan fitur tanpa harus membaca seluruh kode.

---

## 1. Gambaran Umum

Main Pintar adalah aplikasi kuis edukatif bertema kearsipan dengan dua mode permainan:

1. **Mode Solo** — peserta mengerjakan 10 soal acak per kategori, satu per satu, dengan timer per soal + bonus kecepatan. Skor disimpan ke tabel `skor`.
2. **Mode Live Multiplayer** — host (admin) membuat sesi dengan kode ruangan 6 karakter; peserta join via `join.php` dan menerima soal secara real-time via polling `api/poll.php` (tiap 3–4 detik).

**Tiga jenis pengguna:**
| Role | Akses |
|---|---|
| `admin` | Dasbor, kelola soal/kategori/user, monitor sesi live, broadcast notifikasi, pengaturan sistem, analitik |
| `peserta` | Main kuis, leaderboard, statistik, notifikasi, pengaturan pribadi |
| `guest` | Main tanpa akun (skor tidak masuk leaderboard) |

**Fitur lintas pengguna:**
- **Multi-bahasa (ID/EN)** — `includes/lang.php`; prioritas: akun user → cookie → setelan admin.
- **Notifikasi** — broadcast admin ke peserta; lonceng + badge di header.
- **Pengaturan Sistem** — nama aplikasi, tagline, registrasi on/off, mode pemeliharaan, bahasa default (tabel `settings`).
- **Preferensi user** — bahasa, tema (terang/gelap/system), data pekerja (jabatan, unit kerja, WhatsApp).
- **Kategori/Soal aktif-nonaktif** — arsipkan tanpa hapus; otomatis disembunyikan dari semua mode kuis.
- **PWA** — manifest + service worker (`sw.js`), splash screen, offline page.

---

## 2. Struktur Folder

```
main-pintarNew/
├── index.php               # Beranda: hero, daftar kategori, CTA sesuai status login
├── kuis.php                # Pilih kategori kuis (hanya kategori aktif)
├── main.php                # Mesin kuis: mode solo (form POST/PRG) & mode live (polling)
├── join.php                # Gabung sesi live dengan kode ruangan + nickname
├── hasil.php               # Hasil kuis solo (poin, akurasi, CTA daftar untuk guest)
├── leaderboard.php         # Peringkat peserta (MAX(total_poin) per user)
├── manifest.json           # PWA manifest
├── sw.js                   # Service worker
├── offline.html            # Halaman offline PWA
├── .htaccess               # Kompresi gzip + cache browser aset statis
├── database.sql            # Skema awal + seed (admin, 4 kategori, 40 soal)
├── database-upgrade.sql    # Migrasi fitur baru (settings, notifikasi, preferensi, flag aktif)
├── DEPLOY.md               # Panduan deploy ke InfinityFree
├── ARCHITECTURE.md         # Dokumen ini
│
├── includes/               # ===== INTI APLIKASI =====
│   ├── config.php          # Koneksi PDO, konstanta (nama app, poin, polling), hit counter
│   ├── config.local.php    # Kredensial DB (TIDAK di-commit; ada .example-nya)
│   ├── session.php         # Sesi, role check (mp_is_admin dll), guest session, transfer data guest
│   ├── functions.php       # e(), CSRF, layout mp_head/mp_foot, nav, query helper (leaderboard dll)
│   ├── settings.php        # setting()/setting_set(), app_name(), maintenance gate
│   ├── lang.php            # Sistem i18n: mp_boot_lang(), __(), switch URL
│   ├── lang/id.php         # Kamus ID (kunci = teks asli)
│   ├── lang/en.php         # Kamus EN (terjemahan; kunci hilang = fallback ID)
│   └── hits.txt            # Penanda hit counter harian
│
├── auth/                   # ===== AKUN PENGGUNA =====
│   ├── login.php           # Login peserta & admin (+remember 30 hari, transfer skor guest)
│   ├── register.php        # Registrasi peserta (bisa ditutup dari Pengaturan Sistem)
│   ├── logout.php          # Keluar
│   ├── profile.php         # Profil: nama tampil, ubah password, statistik ringkas
│   ├── settings.php        # PENGATURAN: bahasa, tema, data pekerja (jabatan/unit/WA)
│   ├── notifikasi.php      # Inbox notifikasi (tandai dibaca / hapus)
│   └── stats.php           # Statistik detail: tren bulanan, akurasi, performa per kategori
│
├── admin/                  # ===== PANEL ADMIN =====
│   ├── login.php           # Login khusus admin
│   ├── dashboard.php       # Metrik ringkas, buat sesi live, sesi terbaru, soal tersulit
│   ├── kelola-soal.php     # CRUD soal + toggle Aktif/Nonaktif
│   ├── kelola-kategori.php # CRUD kategori + toggle Aktif/Nonaktif
│   ├── kelola-user.php     # Kelola pengguna: cari, tambah, ubah role, hapus
│   ├── notifikasi.php      # Broadcast notifikasi (semua/peserta/user tertentu) + riwayat
│   ├── pengaturan.php      # PENGATURAN SISTEM: nama app, tagline, registrasi, maintenance, bahasa
│   ├── lihat-hasil.php     # Monitor sesi live real-time + export CSV (dengan data pekerja)
│   └── analytics.php       # Analitik soal: distribusi jawaban, tingkat kesulitan
│
├── api/
│   ├── poll.php            # API live: GET=status sesi, POST=answer/start/next/finish (host only)
│   └── health.php          # Health check
│
└── assets/
    ├── css/style.css       # Desain sistem lengkap (token, komponen, dark mode)
    ├── css/responsive.css  # Breakpoint mobile-first
    ├── css/professional.css# Layer polemik profesional (kantor): tipografi, panel, tabel, notif
    ├── js/quiz.js          # Tema (system-aware), timer solo, polling peserta, reveal, confetti
    ├── js/admin.js         # Toast, konfirmasi data-confirm, cari tabel, host monitor polling
    └── img/                # Logo & aset gambar
```

---

## 3. Alur Data Utama

### 3.1 Mode Solo
```
kuis.php (pilih kategori)
  → main.php: buat $_SESSION['solo'] { soal_ids (10 acak, hanya aktif), index, poin }
  → POST jawaban (PRG): hitung poin + bonus kecepatan → $_SESSION['solo_fb'] (feedback)
  → soal terakhir: INSERT INTO skor → redirect hasil.php
```

### 3.2 Mode Live
```
admin/dashboard.php (buat sesi → kode 6 karakter, status 'menunggu')
  → peserta join.php (INSERT peserta_sesi) → main.php?live=1
  → polling api/poll.php?sesi=X tiap 4s:
      host  : start/next/finish (POST) — maju nomor soal, status sesi
      peserta: answer (POST) — INSERT jawaban (unique: sesi+user+soal)
      GET   : status, soal aktif, distribusi jawaban, ranking, jawaban sendiri
  → selesai: skor di-bulk-INSERT dari tabel jawaban
```

### 3.3 Bahasa
```
mp_boot_lang() (sekali per request, lazy):
  default_lang dari tabel settings
  → override: users.bahasa (login) → override: cookie mp_lang → ?lang= menang & persist
__() dipanggil saat render; kunci tidak ada di kamus = tampil teks ID asli (fallback aman)
```

### 3.4 Pengaturan Sistem
```
admin/pengaturan.php → setting_set() upsert ke tabel `settings`
setting('key') dibaca dengan cache statis per request; fallback konstanta bila tabel belum ada
mp_maintenance_gate() dipanggil di tiap halaman publik → 503 + pesan (admin tetap lolos)
```

---

## 4. Skema Database (ringkas)

Tabel inti (lihat `database.sql` untuk lengkap):
- `users` — id, username, nama_lengkap, password (hash), role, **bahasa, tema, jabatan, unit_kerja, no_whatsapp**
- `kategori` — nama, deskripsi, ikon, warna_tema, **aktif**
- `soal` — kategori_id, pertanyaan, pilihan A–D, jawaban_benar, penjelasan, poin, waktu_jawab, **aktif**
- `sesi_kuis` — kode_ruangan (unik 6 char), host_user_id, kategori_id, status (menunggu/berjalan/selesai), nomor_soal_sekarang, soal_mulai_at
- `peserta_sesi` — unik (sesi_id, user_id)
- `jawaban` — unik (sesi_id, user_id, soal_id), pilihan_user, benar, waktu_jawab_detik, poin_didapat
- `skor` — user_id, kategori_id, total_poin, tanggal_main, sesi_id (NULL = mode solo)
- `settings` (migrasi) — setting_key (PK), setting_value: app_name, app_tagline, default_lang, allow_registration, maintenance_mode, maintenance_message
- `notifikasi` (migrasi) — user_id (NULL = broadcast global), judul, pesan, jenis, dibaca, created_at

**Migrasi:** jalankan `database-upgrade.sql` sekali (aman diulang di MariaDB; MySQL 8: hapus `IF NOT EXISTS` pada ALTER).

---

## 5. Konvensi Kode (anti-spageti)

1. **Satu titik masuk per halaman** — tiap file PHP publik punya urutan tetap:
   ```php
   require includes/functions.php + session.php;
   mp_start_guest_session();   // bila butuh sesi
   mp_maintenance_gate();      // halaman publik
   mp_boot_lang();             // halaman yang render HTML
   // ... logika POST (selalu csrf_verify() dulu, lalu redirect PRG)
   mp_head([...]); ... mp_foot();
   ```
2. **Escape output** — selalu `e()` untuk teks dinamis; `__() untuk teks yang bisa diterjemahkan.
3. **CSRF** — semua POST wajib `csrf_verify()` + `csrf_field()` di form.
4. **Query** — prepared statement PDO; tidak ada `ORDER BY RAND()` (pakai `mp_get_random_soal_ids`); agregasi di SQL, bukan loop PHP (hindari N+1).
5. **Teks UI** — jangan hardcode teks baru tanpa `__()`; tambahkan kunci di `lang/id.php` + terjemahan di `lang/en.php`.
6. **Pengaturan** — nilai yang mungkin diubah admin → tabel `settings` via `setting()/setting_set()`, bukan konstanta baru.
7. **Layout** — jangan tulis HTML head/footer manual; selalu `mp_head()/mp_foot()`.
8. **CSS** — warna & spacing dari token `:root` di style.css; komponen baru masuk professional.css hanya bila memang layer polemik.
9. **JS** — modular function `initXxx()` yang dipanggil dari `DOMContentLoaded` di quiz.js/admin.js; tidak ada inline event handler baru.
10. **Aman gagal** — fitur tabel baru (settings/notifikasi) dibungkus try-catch agar aplikasi tetap jalan sebelum migrasi dijalankan.

---

## 6. Fitur → File (peta cepat)

| Fitur | File |
|---|---|
| Ganti bahasa ID/EN | `includes/lang.php`, tombol di `functions.php` (mp_head) |
| Notifikasi user | `auth/notifikasi.php`, badge di `includes/functions.php` |
| Broadcast admin | `admin/notifikasi.php` |
| Pengaturan user | `auth/settings.php` |
| Pengaturan sistem | `admin/pengaturan.php` + `includes/settings.php` |
| Kelola user | `admin/kelola-user.php` |
| Kategori/soal nonaktif | toggle di `admin/kelola-*.php`; filter di `functions.php`, `main.php`, `api/poll.php` |
| Export CSV hasil | `admin/lihat-hasil.php?export=csv` (kolom data pekerja) |
| Mode pemeliharaan | `includes/settings.php` (mp_maintenance_gate) |
| Timer & polling | `assets/js/quiz.js` (initSoloQuiz, initLivePeserta), `assets/js/admin.js` (initHostMonitor) |

## 7. Kecepatan

- `.htaccess`: gzip + cache 1 bulan untuk aset statis, no-store untuk PHP/JSON.
- Polling live sudah dilindungi dari penumpukan request (`inFlight` flag) dan pause saat tab hidden.
- Cleanup data lama otomatis 1×/hari (`cleanup_old_data`).
- Rekomendasi hosting: aktifkan OPcache (InfinityFree sudah aktif secara default).
