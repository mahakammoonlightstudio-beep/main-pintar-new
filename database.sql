-- ============================================================================
-- Main Pintar — Struktur Database + Data Awal (40 soal)
-- Kuis Edukatif Kearsipan Kutai Kartanegara — Diarpus Kukar
--
-- Disesuaikan 1:1 dengan phpMyAdmin InfinityFree:
--   7 tabel InnoDB, charset utf8mb4, collation utf8mb4_general_ci
--   users: 13 baris, kategori: 4, soal: 40,
--   sesi_kuis / peserta_sesi / jawaban / skor: 0 baris
--
-- Perbedaan penting vs versi lama file ini:
--   - Collation di-set eksplisit utf8mb4_general_ci (persis server live).
--   - Index (user_id, total_poin DESC) diganti (user_id, total_poin):
--     index DESC tidak didukung MariaDB 10.1 (InfinityFree) dan tidak perlu.
--   - Import via phpMyAdmin: pilih database dulu, lalu tab Import.
-- ============================================================================

SET NAMES utf8mb4;
SET time_zone = '+08:00';

-- ============================================================================
-- TABEL
-- ============================================================================

-- Hapus tabel lama bila ada (urutan child dulu karena FK) — agar re-import bersih
DROP TABLE IF EXISTS jawaban;
DROP TABLE IF EXISTS skor;
DROP TABLE IF EXISTS peserta_sesi;
DROP TABLE IF EXISTS sesi_kuis;
DROP TABLE IF EXISTS soal;
DROP TABLE IF EXISTS kategori;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','peserta','guest') NOT NULL DEFAULT 'peserta',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE kategori (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nama_kategori VARCHAR(100) NOT NULL,
    deskripsi TEXT NULL,
    ikon VARCHAR(50) NULL,
    warna_tema VARCHAR(10) NULL DEFAULT '#46178f',
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE soal (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    kategori_id INT UNSIGNED NOT NULL,
    pertanyaan TEXT NOT NULL,
    pilihan_a VARCHAR(255) NOT NULL,
    pilihan_b VARCHAR(255) NOT NULL,
    pilihan_c VARCHAR(255) NOT NULL,
    pilihan_d VARCHAR(255) NOT NULL,
    jawaban_benar ENUM('A','B','C','D') NOT NULL,
    penjelasan TEXT NULL,
    poin INT NOT NULL DEFAULT 100,
    waktu_jawab INT NOT NULL DEFAULT 20,
    PRIMARY KEY (id),
    KEY idx_soal_kategori (kategori_id),
    KEY idx_soal_kategori_id (kategori_id, id),
    CONSTRAINT fk_soal_kategori FOREIGN KEY (kategori_id) REFERENCES kategori(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE sesi_kuis (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    kode_ruangan CHAR(6) NOT NULL,
    host_user_id INT UNSIGNED NOT NULL,
    kategori_id INT UNSIGNED NOT NULL,
    status ENUM('menunggu','berjalan','selesai') NOT NULL DEFAULT 'menunggu',
    nomor_soal_sekarang INT NOT NULL DEFAULT 0,
    soal_mulai_at DATETIME NULL,
    selesai_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_kode (kode_ruangan),
    KEY idx_status (status),
    KEY idx_kode (kode_ruangan),
    KEY fk_sesi_host (host_user_id),
    KEY fk_sesi_kategori (kategori_id),
    CONSTRAINT fk_sesi_host FOREIGN KEY (host_user_id) REFERENCES users(id),
    CONSTRAINT fk_sesi_kategori FOREIGN KEY (kategori_id) REFERENCES kategori(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE peserta_sesi (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sesi_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    bergabung_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sesi_user (sesi_id, user_id),
    KEY idx_peserta_sesi (sesi_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE jawaban (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sesi_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    soal_id INT UNSIGNED NOT NULL,
    pilihan_user ENUM('A','B','C','D') NOT NULL,
    benar TINYINT(1) NOT NULL DEFAULT 0,
    waktu_jawab_detik INT NOT NULL DEFAULT 0,
    poin_didapat INT NOT NULL DEFAULT 0,
    timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_jawaban (sesi_id, user_id, soal_id),
    KEY idx_sesi (sesi_id),
    KEY idx_user_soal (user_id, soal_id),
    KEY idx_soal_sesi (soal_id, sesi_id),
    CONSTRAINT fk_jawaban_sesi FOREIGN KEY (sesi_id) REFERENCES sesi_kuis(id) ON DELETE CASCADE,
    CONSTRAINT fk_jawaban_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_jawaban_soal FOREIGN KEY (soal_id) REFERENCES soal(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE skor (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    kategori_id INT UNSIGNED NOT NULL,
    total_poin INT NOT NULL DEFAULT 0,
    tanggal_main TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sesi_id INT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_user_kategori (user_id, kategori_id),
    KEY idx_skor_sesi (sesi_id),
    KEY idx_skor_user_poin (user_id, total_poin),
    CONSTRAINT fk_skor_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_skor_kategori FOREIGN KEY (kategori_id) REFERENCES kategori(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================================
-- SEED: ADMIN (username: admin, password: password - GANTI setelah login pertama!)
-- Hash di bawah = password_hash('password', PASSWORD_DEFAULT)
-- ============================================================================
INSERT INTO users (username, nama_lengkap, password, role) VALUES
('admin', 'Administrator Diarpus', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- ============================================================================
-- SEED: KATEGORI
-- ============================================================================
INSERT INTO kategori (id, nama_kategori, deskripsi, ikon, warna_tema) VALUES
(1, 'Arsip Dinamis', 'Pengertian, peredaran, dan pengelolaan arsip aktif dalam instansi.', 'folder', '#e21b3c'),
(2, 'Arsip Statis', 'Arsip bernilai guna kesejarahan yang diserahkan ke LKD.', 'archive', '#1368ce'),
(3, 'Klasifikasi & Kode Klasifikasi', 'Sistem penggolongan arsip dan penentuan kode klasifikasi.', 'file-tree', '#d89e00'),
(4, 'Sistem Retensi Arsip', 'Jadwal retensi, penilaian, dan pemusnahan arsip.', 'clock', '#26890c');

-- Category 1: Arsip Dinamis (10 soal)
INSERT INTO soal (kategori_id, pertanyaan, pilihan_a, pilihan_b, pilihan_c, pilihan_d, jawaban_benar, penjelasan, poin, waktu_jawab) VALUES
(1, 'Apa pengertian arsip dinamis menurut UU No. 43 Tahun 2009?',
 'Arsip yang digunakan secara langsung dalam kegiatan pencipta arsip',
 'Arsip yang sudah tidak digunakan dan bernilai kesejarahan',
 'Arsip yang disimpan di lembaga kearsipan daerah',
 'Arsip yang telah dimusnahkan',
 'A', 'UU No. 43 Tahun 2009 mendefinisikan arsip dinamis sebagai arsip yang digunakan secara langsung dalam kegiatan pencipta arsip dan disimpan selama masa retensi.', 150, 20),
(1, 'Kapan arsip dinamis berpindah menjadi arsip statis?',
 'Saat arsip tersebut sudah tidak digunakan lagi dalam kegiatan penyelenggaraan',
 'Saat arsip tersebut baru saja dibuat',
 'Saat arsip tersebut disalin ke media digital',
 'Saat arsip tersebut masih aktif digunakan',
 'A', 'Arsip dinamis menjadi arsip statis ketika tidak lagi digunakan dalam kegiatan penyelenggaraan dan memiliki nilai guna kesejarahan.', 150, 20),
(1, 'Apa yang dimaksud dengan daftar arsip aktif?',
 'Daftar yang memuat keterangan tentang arsip aktif yang dikelola unit pengolah',
 'Daftar arsip yang sudah dimusnahkan',
 'Daftar arsip statis di lembaga kearsipan',
 'Daftar inventaris peralatan kantor',
 'A', 'Daftar arsip aktif merupakan sarana penemuan kembali arsip aktif yang dikelola oleh unit pengolah/pencipta arsip.', 150, 20),
(1, 'Apa kepanjangan dari JRA?',
 'Jadwal Retensi Arsip',
 'Jaringan Retensi Arsip',
 'Jadwal Retensi Administrasi',
 'Jurnal Retensi Aktif',
 'A', 'JRA adalah singkatan dari Jadwal Retensi Arsip, yaitu jadwal yang berisi jangka waktu penyimpanan arsip.', 100, 15),
(1, 'Contoh arsip dinamis di instansi pemerintah adalah…',
 'Surat keputusan pejabat yang masih berlaku dan digunakan',
 'Dokumen sejarah berdirinya kabupaten',
 'Manuskrip kuno museum',
 'Batu prasasti peninggalan kerajaan',
 'A', 'Surat keputusan yang masih berlaku dan digunakan sehari-hari adalah contoh arsip dinamis di instansi pemerintah.', 100, 15),
(1, 'Arsip dinamis aktif adalah arsip yang…',
 'Frekuensi penggunaannya tinggi dan terus-menerus',
 'Tidak pernah digunakan sama sekali',
 'Sudah habis masa retensinya',
 'Telah diserahkan ke LKD',
 'A', 'Arsip aktif adalah arsip yang frekuensi penggunaannya tinggi dan terus-menerus digunakan dalam kegiatan.', 100, 15),
(1, 'Arsip dinamis inaktif adalah arsip yang…',
 'Frekuensi penggunaannya sudah menurun dan disimpan di unit kearsipan',
 'Selalu digunakan setiap hari',
 'Baru saja diciptakan',
 'Tidak memiliki nilai guna',
 'A', 'Arsip inaktif frekuensi penggunaannya menurun dan dikelola oleh unit kearsipan.', 100, 15),
(1, 'Pencipta arsip adalah…',
 'Pihak yang mempunyai kemandirian dan otoritas dalam pelaksanaan fungsi untuk menciptakan arsip',
 'Orang yang membaca arsip',
 'Lembaga yang hanya memusnahkan arsip',
 'Petugas keamanan arsip',
 'A', 'Pencipta arsip adalah pihak yang berwenang menciptakan arsip dalam pelaksanaan fungsinya. Pada UU 43/2009, termasuk instansi dan perseorangan.', 100, 15),
(1, 'Jenis media arsip dinamis dapat berupa…',
 'Media kertas, elektronik, dan audiovisual',
 'Hanya media kertas',
 'Hanya media elektronik',
 'Hanya media cetak',
 'A', 'Arsip dinamis dapat terekam dalam berbagai media: kertas, elektronik, dan audiovisual sesuai perkembangannya.', 100, 15),
(1, 'Tujuan utama pengelolaan arsip dinamis adalah…',
 'Terselenggaranya pengelolaan arsip yang andal, sistematis, dan akuntabel',
 'Menghabiskan anggaran lembaga',
 'Menimbun arsip sebanyak mungkin',
 'Menghilangkan jejak kegiatan',
 'A', 'Pengelolaan arsip dinamis bertujuan agar arsip dikelola secara andal, sistematis, dan akuntabel.', 100, 15);

-- Category 2: Arsip Statis (10 soal)
INSERT INTO soal (kategori_id, pertanyaan, pilihan_a, pilihan_b, pilihan_c, pilihan_d, jawaban_benar, penjelasan, poin, waktu_jawab) VALUES
(2, 'Apa pengertian arsip statis menurut UU No. 43 Tahun 2009?',
 'Arsip yang tidak lagi digunakan secara langsung dan memiliki nilai guna kesejarahan',
 'Arsip yang digunakan setiap hari',
 'Arsip yang masih dalam masa retensi',
 'Arsip yang akan dimusnahkan',
 'A', 'Arsip statis adalah arsip yang tidak lagi digunakan secara langsung dalam kegiatan dan memiliki nilai guna kesejarahan.', 150, 20),
(2, 'Lembaga yang menampung arsip statis daerah adalah…',
 'Lembaga Kearsipan Daerah (LKD)',
 'Badan Pusat Statistik',
 'Kementerian Dalam Negeri',
 'Dinas Pendidikan',
 'A', 'LKD adalah lembaga kearsipan berbentuk perangkat daerah yang menampung arsip statis daerah.', 150, 20),
(2, 'Proses serah terima arsip statis dari pencipta arsip kepada LKD disebut…',
 'Penyerahan arsip statis',
 'Pemusnahan arsip',
 'Alih media arsip',
 'Digitasi arsip',
 'A', 'Penyerahan arsip statis adalah proses penyerahan arsip statis dari pencipta arsip kepada lembaga kearsipan.', 100, 15),
(2, 'Apa yang dilakukan untuk mencegah arsip statis rusak oleh jamur dan kutu?',
 'Preservasi dan restorasi arsip',
 'Membiarkan arsip di ruangan lembab',
 'Menyimpan arsip di lantai',
 'Menjemur arsip di bawah sinar matahari langsung',
 'A', 'Preservasi (pencegahan) dan restorasi (perbaikan) dilakukan agar arsip statis tidak rusak oleh jamur, kutu, dan faktor perusak lainnya.', 150, 20),
(2, 'Nilai guna arsip statis yang utama adalah…',
 'Nilai guna kesejarahan',
 'Nilai guna informasional biasa',
 'Nilai guna administratif harian',
 'Nilai guna keuangan',
 'A', 'Arsip statis memiliki nilai guna kesejarahan yang menjadi pertimbangan utama penentuannya.', 100, 15),
(2, 'Yang termasuk contoh arsip statis adalah…',
 'Dokumen sejarah pendirian kantor dan perjuangan daerah',
 'Surat dinas edaran bulanan',
 'Undangan rapat mingguan',
 'Kwitansi pembelian alat tulis',
 'A', 'Dokumen sejarah pendirian lembaga dan perjuangan daerah termasuk arsip statis karena bernilai kesejarahan.', 100, 15),
(2, 'Arsip statis dapat terbentuk ketika arsip dinamis…',
 'Telah diserahkan karena tidak digunakan dan bernilai kesejarahan',
 'Masih aktif digunakan',
 'Baru dibuat hari ini',
 'Sedang dalam pengiriman',
 'A', 'Arsip statis terbentuk dari arsip dinamis yang telah diserahkan kepada lembaga kearsipan karena nilai kesejarahannya.', 100, 15),
(2, 'Kewenangan pengelolaan arsip statis tingkat kabupaten/kota berada pada…',
 'Lembaga Kearsipan Daerah Kabupaten/Kota',
 'Kecamatan',
 'Kelurahan',
 'Komisi Pemilihan Umum',
 'A', 'Pengelolaan arsip statis tingkat kabupaten/kota menjadi kewenangan LKD kabupaten/kota.', 100, 15),
(2, 'Tujuan pelestarian (preservasi) arsip statis adalah…',
 'Mencegah kerusakan dan memperpanjang usia arsip',
 'Menghilangkan isi arsip',
 'Mengganti arsip asli',
 'Mempercepat pemusnahan',
 'A', 'Preservasi bertujuan mencegah kerusakan serta memperpanjang usia dan keawetan arsip statis.', 100, 15),
(2, 'Arsip statis yang sudah rusak diperbaiki melalui kegiatan…',
 'Restorasi',
 'Pemusnahan',
 'Alih media tanpa perawatan',
 'Pencucian menggunakan air banyak',
 'A', 'Restorasi adalah kegiatan perbaikan arsip statis yang mengalami kerusakan.', 100, 15);

-- Category 3: Klasifikasi & Kode Klasifikasi (10 soal)
INSERT INTO soal (kategori_id, pertanyaan, pilihan_a, pilihan_b, pilihan_c, pilihan_d, jawaban_benar, penjelasan, poin, waktu_jawab) VALUES
(3, 'Apa yang dimaksud klasifikasi arsip?',
 'Sistem penggolongan arsip secara teratur menurut kategori atau urusan tertentu',
 'Proses pemindaian arsip',
 'Penyimpanan arsip secara acak',
 'Pencetakan arsip baru',
 'A', 'Klasifikasi arsip adalah sistem penggolongan arsip secara teratur menurut kategori atau urusan tertentu.', 150, 20),
(3, 'Sistem klasifikasi arsip diinstansi pemerintah Indonesia umumnya berdasarkan…',
 'Fungsi dan urusan pemerintahan',
 'Abjad alfabetis saja',
 'Warna sampul arsip',
 'Ukuran fisik arsip saja',
 'A', 'Sistem klasifikasi diinstansi pemerintah berdasarkan fungsi dan urusan untuk memudahkan pengelolaan.', 150, 20),
(3, 'Fungsi utama kode klasifikasi arsip adalah…',
 'Memudahkan penemuan kembali dan pengelolaan arsip',
 'Memperbesar ukuran arsip',
 'Menambah pekerjaan arsiparis',
 'Mengurangi jumlah arsip',
 'A', 'Kode klasifikasi berfungsi sebagai petunjuk lokasi arsip agar mudah ditemukan kembali.', 100, 15),
(3, 'Contoh kode klasifikasi untuk urusan keuangan yang umum adalah…',
 '01.UMUM, 02.KEUANGAN',
 'KODE-A, KODE-B',
 'NOMOR-1, NOMOR-2',
 'SERI-A, SERI-B',
 'A', 'Klasifikasi keuangan umumnya menggunakan kode seperti 01.UMUM, 02.KEUANGAN sebagai contoh.', 100, 15),
(3, 'Klasifikasi arsip menurut fungsi dikenal dengan sistem…',
 'Klasifikasi fungsional',
 'Klasifikasi abjad',
 'Klasifikasi kronologis',
 'Klasifikasi geografis',
 'A', 'Klasifikasi fungsional menggolongkan arsip berdasarkan fungsi/unit kerja yang menghasilkan.', 100, 15),
(3, 'Penomoran kode klasifikasi dalam filing cabinet folder menggunakan…',
 'Folder bermerek standar dan angka urut alfabetis',
 'Hanya angka Romawi',
 'Hanya huruf alfabet',
 'Warna sampul folder',
 'A', 'Folder dalam filing cabinet menggunakan penomoran kombinasi angka urut untuk penemuan kembali.', 100, 15),
(3, 'Filing equipment yang umum digunakan untuk arsip dinamis inaktif meliputi…',
 'Folder, box file, dan rodex',
 'Meja dan kursi',
 'Komputer dan printer',
 'AC dan dehumidifier',
 'A', 'Folder, box file, dan rodex adalah filing equipment utama untuk menyimpan arsip dinamis inaktif.', 100, 15),
(3, 'Sistem penataan arsip yang disusun berdasarkan kronologis (tanggal) disebut klasifikasi…',
 'Kronologis',
 'Abjad',
 'Subjek',
 'Geografis',
 'A', 'Klasifikasi kronologis menyusun arsip berdasarkan tanggal atau waktu pembuatan.', 100, 15),
(3, 'Sistem penataan arsip berdasarkan wilayah atau lokasi disebut klasifikasi…',
 'Geografis',
 'Alfabetis',
 'Numeris',
 'Fungsional',
 'A', 'Klasifikasi geografis mengatur arsip berdasarkan wilayah atau lokasi geografis.', 100, 15),
(3, 'Retensi arsip yang frekuensinya tinggi ditempatkan dalam filing equipment…',
 'Filing cabinet aktif/rolling',
 'Archive box',
 'Map gantung',
 'Baki arsip',
 'A', 'Arsip dengan frekuensi tinggi ditempatkan di filing cabinet aktif agar mudah dijangkau.', 100, 15);

-- Category 4: Sistem Retensi Arsip (10 soal)
INSERT INTO soal (kategori_id, pertanyaan, pilihan_a, pilihan_b, pilihan_c, pilihan_d, jawaban_benar, penjelasan, poin, waktu_jawab) VALUES
(4, 'Apa tujuan utama Jadwal Retensi Arsip (JRA)?',
 'Menentukan jangka waktu penyimpanan dan nasib arsip',
 'Menambah jumlah arsip',
 'Mengurangi jumlah karyawan',
 'Mempercepat pemusnahan saja',
 'A', 'JRA menentukan jangka waktu penyimpanan dan nasib arsip, apakah terus disimpan, direkam ulang, atau dimusnahkan.', 150, 20),
(4, 'Proses penilaian arsip untuk menentukan apakah arsip disimpan, direkam ulang, atau dimusnahkan disebut…',
 'Penilaian arsip (appraisal)',
 'Preservasi',
 'Alih media',
 'Inventarisasi',
 'A', 'Appraisal adalah proses menentukan nilai dan nasib arsip, apakah disimpan permanen, direkam, atau dimusnahkan.', 150, 20),
(4, 'Apa yang dimaksud pemusnahan arsip?',
 'Tindakan menghilangkan arsip yang tidak memiliki nilai guna secara permanen',
 'Proses merawat arsip',
 'Proses menyalin arsip',
 'Proses mengirim arsip ke daerah lain',
 'A', 'Pemusnahan arsip adalah menghilangkan secara permanen arsip yang tidak memiliki nilai guna lagi.', 150, 20),
(4, 'Sebelum arsip dimusnahkan, proses yang wajib dilakukan adalah…',
 'Meminta persetujuan dari pimpinan dan membuat Berita Acara Pemusnahan',
 'Langsung membakarnya saja',
 'Menjual ke pabrik daur ulang',
 'Mengirim ke museum',
 'A', 'Pemusnahan arsip wajib didokumentasikan dengan Berita Acara dan persetujuan pimpinan.', 100, 15),
(4, 'Nilai guna yang menentukan arsip dinamis dapat dimusnahkan atau disimpan adalah…',
 'Nilai guna primer dan sekunder',
 'Hanya nilai jual',
 'Hanya nilai estetika',
 'Hanya nilai koleksi',
 'A', 'Penilaian berdasarkan nilai guna primer (administratif, legal, fiskal) dan sekunder (sejarah, ilmu pengetahuan, budaya).', 100, 15),
(4, 'Alih media arsip dilakukan untuk…',
 'Memindahkan informasi dari media lama ke media baru agar awet',
 'Menghilangkan isi arsip',
 'Memperbesar ukuran arsip',
 'Mengganti isi arsip',
 'A', 'Alih media memindahkan informasi arsip ke media baru agar tidak hilang dan awet, terutama untuk media yang sudah rapuh.', 100, 15),
(4, 'JRA diterbitkan oleh…',
 'Lembaga kearsipan nasional bersama pencipta arsip',
 'Museum nasional',
 'Perpustakaan nasional',
 'Kementerian keuangan',
 'A', 'JRA ditetapkan oleh Kepala ANRI bersama pencipta arsip nasional, dan oleh Kepala Daerah untuk instansi daerah.', 100, 15),
(4, 'Filing equipment yang digunakan untuk menyimpan arsip yang sudah jarang digunakan tetapi belum dimusnahkan disebut…',
 'Archive box / storage box',
 'Filing cabinet aktif',
 'Box file surat masuk',
 'Baki sortir',
 'A', 'Archive box digunakan untuk menyimpan arsip inaktif/semi aktif yang belum dimusnahkan.', 100, 15),
(4, 'SOP pengelolaan arsip diinstansi pemerintah daerah mengatur tentang…',
 'Prosedur menciptakan, merawat, dan disposisi arsip secara benar',
 'Cara menggunakan komputer',
 'Prosedur pembayaran gaji',
 'Cara membuat laporan keuangan',
 'A', 'SOP arsip mengatur seluruh siklus arsip dari penciptaan hingga disposisi.', 100, 15),
(4, 'Penataan arsip yang menyusun berdasarkan subjek/materi disebut klasifikasi…',
 'Subjek',
 'Kronologis',
 'Abjad',
 'Geografis',
 'A', 'Klasifikasi subjek/materi menggolongkan arsip berdasarkan pokok masalah atau materi isi arsip.', 100, 15);

-- Catatan: semua INDEX sudah didefinisikan inline di CREATE TABLE (KEY idx_...).
-- Tidak perlu ALTER TABLE ADD INDEX — akan menyebabkan error #1061 duplicate key.
