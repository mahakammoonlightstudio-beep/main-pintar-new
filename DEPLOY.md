# Deploy Main Pintar ke InfinityFree

## Prasyarat
- Akun InfinityFree (if0_XXXXXXX)
- Filezilla / panel File Manager InfinityFree
- phpMyAdmin via control panel

---

## Langkah 1: Buat Database MySQL di InfinityFree

1. Login ke [if0.infinityfree.com](https://if0.infinityfree.com) → **Client Area** → **Control Panel**
2. **MySQL Databases** → buat database baru, catat:
   - `DB_HOST`     : `sqlXXX.infinityfree.com` (lihat di halaman MySQL)
   - `DB_NAME`     : format `if0_XXXXXXX_namadb`
   - `DB_USER`     : biasanya sama prefix dengan DB_NAME (`if0_XXXXXXX`)
   - `DB_PASS`     : password yang kamu buat saat membuat database

---

## Langkah 2: Update config.php

Buka `main-pintarNew/includes/config.php`, isikan kredensial:

```php
define('DB_HOST', 'sql312.infinityfree.com'); // <-- sesuaikan
define('DB_NAME', 'if0_41894075_mainpintar');
define('DB_USER', 'if0_41894075');
define('DB_PASS', 'password_db_kamu');
```

---

## Langkah 3: Import database.sql

1. Buka **phpMyAdmin** dari control panel → pilih database kamu
2. Tab **Import** → pilih file `main-pintarNew/database.sql`
3. Pastikan `utf8mb4_unicode_ci` sebagai collation
4. Tekan **Go** — tabel + 4 kategori + 40 soal otomatis ter-create

### Langkah 3b: Import database-upgrade.sql (WAJIB untuk fitur baru)

File `database-upgrade.sql` menambahkan struktur untuk fitur baru:
- Tabel `settings` — Pengaturan Sistem admin (nama app, registrasi, maintenance, bahasa default)
- Tabel `notifikasi` — broadcast/pengumuman ke peserta
- Kolom users: `bahasa`, `tema`, `jabatan`, `unit_kerja`, `no_whatsapp` — preferensi & data pekerja
- Kolom `aktif` pada `kategori` & `soal` — arsipkan tanpa menghapus

1. Masih di **phpMyAdmin** → pilih database yang sama
2. Tab **Import** → pilih `main-pintarNew/database-upgrade.sql` → **Go**
3. Instalasi baru: jalankan **setelah** `database.sql`. Instalasi lama: cukup file ini saja.

> MariaDB (InfinityFree) mendukung `ADD COLUMN IF NOT EXISTS` sehingga file ini
> aman dijalankan berulang. MySQL 8: hapus kata `IF NOT EXISTS` di baris ALTER TABLE.

---

## Langkah 4: Upload File via FileZilla

1. Buka FileZilla → login ke hosting dengan SFTP/FTP
2. Folder tujuan: `htdocs/main-pintar/`
3. Upload seluruh isi folder `main-pintarNew/` (kecuali `q_12.sql` dan `q_34.sql` sudah di-merge)

Struktur akhir di server:
```
htdocs/
└── main-pintar/
    ├── index.php
    ├── kuis.php
    ├── join.php
    ├── main.php
    ├── hasil.php
    ├── leaderboard.php
    ├── api/
    │   └── poll.php
    ├── admin/
    │   ├── login.php
    │   ├── dashboard.php
    │   ├── kelola-soal.php
    │   ├── kelola-kategori.php
    │   └── lihat-hasil.php
    ├── includes/
    │   ├── config.php       ← edit kredensial di sini
    │   ├── functions.php
    │   └── session.php
    ├── assets/
    │   ├── css/
    │   │   ├── style.css
    │   │   └── responsive.css
    │   ├── js/
    │   │   ├── quiz.js
    │   │   └── admin.js
    │   └── img/
    │       ├── icon-arsip.svg
    │       └── logo.png       ← opsional, buat sendiri
    └── database.sql          ← sudah diimport, tidak perlu upload
```

---

## Langkah 5: Ubah Password Admin (WAJIB!)

1. Buka `admin/login.php` → login dengan default:
   - **Username** : `admin`
   - **Password** : `password`
2. Login ke `admin/dashboard.php`
3. **Kelola Soal** → tambah admin baru dengan password baru, atau gunakan phpMyAdmin:

```sql
UPDATE users SET password = PASSWORD('PasswordBaruKuat2026!')
WHERE username = 'admin';
```

> Gunakan `password_hash('PasswordBaru', PASSWORD_DEFAULT)` via PHP kecil untuk generate hash baru.

---

## Langkah 6: Tes

1. Buka `http://[domain]/main-pintar/`
2. Pilih kategori → klik **Mulai Kuis**
3. Login admin → **Dashboard** → **Buat Kode Ruangan**
4. Copy kode → buka tab lain → `join.php` → masukkan kode + nickname

---

## Cara Kerja Polling (api/poll.php)

**Single endpoint, single request JSON** — tidak ada WebSocket:

```
Peserta (AJAX polling tiap 4 detik):
GET api/poll.php?sesi=X
  → {status, soal_aktif, jawaban_saya, peserta[], ranking}
  → JS render tampilan

Peserta jawab (AJAX POST):
POST api/poll.php?sesi=X
  Body: {action:"answer", pilihan:"A"}
  → server hitung poin + bonus kecepatan
  → response {benar, poin_didapat, jawaban_benar, penjelasan}

Host (AJAX polling tiap 3 detik):
GET api/poll.php?sesi=X
  → {status, soal, peserta[], distribusi_A/B/C/D, ranking}

Host aksi (AJAX POST):
POST api/poll.php?sesi=X
  Body: {action:"start"|"next"|"finish"}
  → server update sesi_kuis.nomor_soal_sekarang
  → semua klien polling dapat data baru
```

**Hit limit kalkulasi:**
- 1 halaman = 1 hit
- 1 CSS = 1 hit
- 1 JS = 1 hit
- 1 polling = 1 hit
- 1 halaman + CSS + JS + polling = 4 hit per 4 detik
- 30 peserta × 20 soal × polling 4 detik × durasi 20 detik ≈ 16.800 hit → masih aman dari limit 50.000/hari

**Polling pause saat tab tidak aktif:**
`visibilitychange` event di `quiz.js` dan `admin.js` otomatis pause polling saat tab di-background — hemat hit limit.

---

## Troubleshooting

| Masalah | Solusi |
|--------|--------|
| "Sistem sedang gangguan" | Cek `config.php` — DB_HOST/DB_NAME/DB_PASS salah |
| Halaman tanpa CSS | Pastikan file `style.css` ter-upload di `assets/css/` |
| Login admin gagal | Hash default `password_hash('password')` — cek apakah match |
| Soalnya tidak muncul | Import `database.sql` lewat phpMyAdmin |
| Timer stuck | Pastikan JS tidak error — buka DevTools Console |
| Polling tidak update | Cek `api/poll.php` — error reporting di `config.php` sudah off |
| File upload timeout | Pecah SQL jika >10MB, import per tabel |

## Catatan Penting

- **Limit upload 10MB** — `database.sql` ±40KB, aman.
- **Limit eksekusi 30 detik** — `set_time_limit(25)` di `config.php`.
- **Limit database 400MB** — `cleanup_old_data()` jalankan otomatis saat admin login.
- **Hit limit 50.000/hari** — polling 4 detik per client, hemat hit.
- **Tidak ada WebSocket** — semua real-time via AJAX polling.