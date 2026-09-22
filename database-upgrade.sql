-- ============================================================================
-- Main Pintar - UPGRADE DATABASE (jalankan sekali di phpMyAdmin)
-- Fitur: Pengaturan Sistem, Broadcast Notifikasi, Preferensi User
--        (bahasa/tema/jabatan), Flag aktif untuk Kategori & Soal.
--
-- CATATAN:
--  - Semua ENGINE=InnoDB / charset utf8mb4 / collation utf8mb4_general_ci
--    sama seperti tabel inti di database.sql → cocok dengan server live
--    InfinityFree dan tidak ada risiko foreign key antar engine.
--  - Kolom users (bahasa, tema, jabatan, unit_kerja, no_whatsapp) serta flag
--    aktif (kategori, soal) SUDAH TERMASUK di database.sql terbaru. File ini
--    hanya perlu dijalankan jika database dibuat dari schema LAMA.
--  - Dioptimalkan untuk MariaDB (InfinityFree): mendukung ADD COLUMN IF NOT EXISTS.
--    Jika memakai MySQL 8 (tidak mendukung ADD COLUMN IF NOT EXISTS), hapus
--    kata "IF NOT EXISTS" pada tiap baris ADD COLUMN.
--  - Aman dijalankan berulang (CREATE ... IF NOT EXISTS + ON DUPLICATE KEY).
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- 1. TABEL SETTINGS (konfigurasi aplikasi yang bisa diubah admin)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(50) NOT NULL,
    setting_value TEXT NULL,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO settings (setting_key, setting_value) VALUES
('app_name', 'Main Pintar'),
('app_tagline', 'Asah Pengetahuan Kearsipanmu, Menangkan Predikat!'),
('default_lang', 'id'),
('allow_registration', '1'),
('maintenance_mode', '0'),
('maintenance_message', 'Sistem sedang dalam pemeliharaan. Silakan kembali lagi nanti.')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- ----------------------------------------------------------------------------
-- 2. TABEL NOTIFIKASI (inbox peserta; user_id NULL = broadcast global)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifikasi (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL,
    judul VARCHAR(150) NOT NULL,
    pesan TEXT NOT NULL,
    jenis ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
    dibaca TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notif_user (user_id, dibaca),
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- 3. KOLOM BARU TABEL USERS (preferensi bahasa/tema + profil pekerja)
-- ----------------------------------------------------------------------------
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS bahasa VARCHAR(5) NOT NULL DEFAULT '' AFTER role,
    ADD COLUMN IF NOT EXISTS tema VARCHAR(10) NOT NULL DEFAULT '' AFTER bahasa,
    ADD COLUMN IF NOT EXISTS jabatan VARCHAR(100) NULL DEFAULT NULL AFTER tema,
    ADD COLUMN IF NOT EXISTS unit_kerja VARCHAR(100) NULL DEFAULT NULL AFTER jabatan,
    ADD COLUMN IF NOT EXISTS no_whatsapp VARCHAR(20) NULL DEFAULT NULL AFTER unit_kerja;

-- ----------------------------------------------------------------------------
-- 4. KOLOM AKTIF untuk Kategori & Soal (arsipkan tanpa menghapus)
-- ----------------------------------------------------------------------------
ALTER TABLE kategori
    ADD COLUMN IF NOT EXISTS aktif TINYINT(1) NOT NULL DEFAULT 1 AFTER warna_tema;

ALTER TABLE soal
    ADD COLUMN IF NOT EXISTS aktif TINYINT(1) NOT NULL DEFAULT 1 AFTER waktu_jawab;

-- Selesai. Struktur baru otomatis dipakai aplikasi tanpa perlu ubah kode.
