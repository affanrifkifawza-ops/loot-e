SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+07:00";
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `db_loote` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE `db_loote`;

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- ----------------------------------------------------------
-- Hapus tabel lama jika sudah ada (agar aman saat re-import)
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `ulasan`;
DROP TABLE IF EXISTS `detail_pesanan`;
DROP TABLE IF EXISTS `pesanan`;
DROP TABLE IF EXISTS `riwayat_barter`;
DROP TABLE IF EXISTS `barter`;
DROP TABLE IF EXISTS `riwayat_negosiasi`;
DROP TABLE IF EXISTS `negosiasi`;
DROP TABLE IF EXISTS `antrean`; -- legacy table (dihapus pada ADR-0002)
DROP TABLE IF EXISTS `keranjang`;
DROP TABLE IF EXISTS `produk_foto`;
DROP TABLE IF EXISTS `produk`;
DROP TABLE IF EXISTS `kategori`;
DROP TABLE IF EXISTS `pengguna`;

-- ----------------------------------------------------------
-- 1. Table `pengguna`
-- ----------------------------------------------------------
CREATE TABLE `pengguna` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nama` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `peran` ENUM('user', 'admin') NOT NULL DEFAULT 'user',
  `nik` CHAR(16) DEFAULT NULL COMMENT '16 digit NIK KTP (Diisi saat verifikasi seller)',
  `no_hp` VARCHAR(20) DEFAULT NULL COMMENT 'Opsional, dapat diisi mandiri melalui halaman profil',
  `alamat` TEXT DEFAULT NULL COMMENT 'Opsional, JSON terstruktur dari halaman alamat pengguna',
  `info_rekening` TEXT DEFAULT NULL COMMENT 'JSON terstruktur rekening pencairan Escrow (Diisi saat verifikasi seller)',
  `is_verified` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = Boleh upload produk',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pengguna_email` (`email`),
  UNIQUE KEY `uq_pengguna_nik` (`nik`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------------------------------------
-- 2. Table `kategori`
-- ----------------------------------------------------------
CREATE TABLE `kategori` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nama_kategori` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------------------------------------
-- 3. Table `produk`
-- ----------------------------------------------------------
CREATE TABLE `produk` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_penjual` INT NOT NULL,
  `id_kategori` INT NOT NULL,
  `nama_produk` VARCHAR(255) NOT NULL,
  `foto` VARCHAR(255) DEFAULT NULL,
  `harga` INT NOT NULL,
  `stok` INT NOT NULL DEFAULT 1,
  `kondisi` VARCHAR(100) NOT NULL COMMENT 'Contoh: Bekas - Mulus / Bekas - Ada Minus',
  `deskripsi` TEXT DEFAULT NULL COMMENT 'Hasil gabungan form kondisi fisik, fungsi, pakai, servis, minus & kelengkapan',
  `bisa_nego` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = Centang Bisa Nego, 0 = Tidak',
  `bisa_barter` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = Centang Bisa Barter, 0 = Tidak',
  `status_produk` ENUM('tersedia', 'draft', 'nonaktif', 'habis') NOT NULL DEFAULT 'tersedia' COMMENT 'tersedia=tayang, draft=konsep, nonaktif=disembunyikan seller, habis=stok 0',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_produk_penjual` (`id_penjual`),
  KEY `idx_produk_kategori` (`id_kategori`),
  CONSTRAINT `fk_produk_penjual` FOREIGN KEY (`id_penjual`) REFERENCES `pengguna` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_produk_kategori` FOREIGN KEY (`id_kategori`) REFERENCES `kategori` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------------------------------------
-- 3b. Table `produk_foto`
-- ----------------------------------------------------------
CREATE TABLE `produk_foto` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_produk` INT NOT NULL,
  `path_foto` VARCHAR(255) NOT NULL,
  `urutan` INT NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_produk_foto_produk` (`id_produk`, `urutan`),
  CONSTRAINT `fk_produk_foto_produk` FOREIGN KEY (`id_produk`) REFERENCES `produk` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------------------------------------
-- 4. Table `keranjang`
-- ----------------------------------------------------------
CREATE TABLE `keranjang` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_pembeli` INT NOT NULL,
  `id_produk` INT NOT NULL,
  `kuantitas` INT NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_keranjang_user_produk` (`id_pembeli`, `id_produk`),
  KEY `idx_keranjang_pembeli` (`id_pembeli`),
  KEY `idx_keranjang_produk` (`id_produk`),
  CONSTRAINT `fk_keranjang_pembeli` FOREIGN KEY (`id_pembeli`) REFERENCES `pengguna` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_keranjang_produk` FOREIGN KEY (`id_produk`) REFERENCES `produk` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------------------------------------
-- 5. Table `negosiasi`
-- ----------------------------------------------------------
CREATE TABLE `negosiasi` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_pembeli` INT NOT NULL,
  `id_produk` INT NOT NULL,
  `kuantitas` INT NOT NULL DEFAULT 1,
  `harga_deal` INT DEFAULT NULL,
  `status` ENUM('pending', 'counter_offer', 'diterima', 'ditolak', 'expired', 'gagal') NOT NULL DEFAULT 'pending' COMMENT 'gagal=ditutup otomatis saat stok habis/tidak cukup/produk nonaktif',
  `batas_waktu` DATETIME NOT NULL COMMENT 'Timer 30 menit, reset saat counter_offer',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_negosiasi_pembeli` (`id_pembeli`),
  KEY `idx_negosiasi_produk` (`id_produk`),
  CONSTRAINT `fk_negosiasi_pembeli` FOREIGN KEY (`id_pembeli`) REFERENCES `pengguna` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_negosiasi_produk` FOREIGN KEY (`id_produk`) REFERENCES `produk` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------------------------------------
-- 6. Table `riwayat_negosiasi`
-- ----------------------------------------------------------
CREATE TABLE `riwayat_negosiasi` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_negosiasi` INT NOT NULL,
  `pengirim_tawaran` ENUM('pembeli', 'penjual') NOT NULL,
  `harga` INT NOT NULL,
  `waktu` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_riwayat_negosiasi` (`id_negosiasi`),
  CONSTRAINT `fk_riwayat_negosiasi` FOREIGN KEY (`id_negosiasi`) REFERENCES `negosiasi` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------------------------------------
-- 7. Table `barter`
-- ----------------------------------------------------------
CREATE TABLE `barter` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_pembeli` INT NOT NULL,
  `id_produk_diincar` INT NOT NULL,
  `id_produk_ditawarkan` INT NOT NULL,
  `kuantitas_diincar` INT NOT NULL DEFAULT 1,
  `tambah_dana_deal` INT NOT NULL DEFAULT 0,
  `status` ENUM('pending', 'counter_offer', 'diterima', 'ditolak', 'expired', 'gagal') NOT NULL DEFAULT 'pending' COMMENT 'gagal=ditutup otomatis saat stok habis/tidak cukup/produk nonaktif',
  `batas_waktu` DATETIME NOT NULL COMMENT 'Timer 30 menit, reset saat counter_offer',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_barter_pembeli` (`id_pembeli`),
  KEY `idx_barter_produk_diincar` (`id_produk_diincar`),
  KEY `idx_barter_produk_ditawarkan` (`id_produk_ditawarkan`),
  CONSTRAINT `fk_barter_pembeli` FOREIGN KEY (`id_pembeli`) REFERENCES `pengguna` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_barter_produk_diincar` FOREIGN KEY (`id_produk_diincar`) REFERENCES `produk` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_barter_produk_ditawarkan` FOREIGN KEY (`id_produk_ditawarkan`) REFERENCES `produk` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------------------------------------
-- 8. Table `riwayat_barter`
-- ----------------------------------------------------------
CREATE TABLE `riwayat_barter` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_barter` INT NOT NULL,
  `pengirim_tawaran` ENUM('pembeli', 'penjual') NOT NULL,
  `tambah_dana` INT NOT NULL DEFAULT 0,
  `waktu` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_riwayat_barter` (`id_barter`),
  CONSTRAINT `fk_riwayat_barter` FOREIGN KEY (`id_barter`) REFERENCES `barter` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------------------------------------
-- 9. Table `pesanan`
-- ----------------------------------------------------------
CREATE TABLE `pesanan` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_pembeli` INT NOT NULL,
  `tipe_transaksi` ENUM('beli_langsung', 'hasil_nego', 'hasil_barter') NOT NULL,
  `biaya_layanan` INT NOT NULL DEFAULT 5000 COMMENT 'Keuntungan platform / biaya proteksi rekber',
  `total_harga` INT NOT NULL COMMENT 'Harga barang (atau tambah dana) + biaya_layanan',
  `metode_bayar` ENUM('qris', 'transfer_bank', 'ewallet', 'tanpa_pembayaran') NOT NULL,
  `alamat_kirim` TEXT NOT NULL,
  `nomor_resi` VARCHAR(100) DEFAULT NULL COMMENT 'Diisi seller saat kirim barang',
  `status_pesanan` ENUM('menunggu_pembayaran', 'dibayar', 'dikirim', 'selesai', 'dibatalkan') NOT NULL DEFAULT 'menunggu_pembayaran',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pesanan_pembeli` (`id_pembeli`),
  CONSTRAINT `fk_pesanan_pembeli` FOREIGN KEY (`id_pembeli`) REFERENCES `pengguna` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------------------------------------
-- 10. Table `detail_pesanan`
-- ----------------------------------------------------------
CREATE TABLE `detail_pesanan` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_pesanan` INT NOT NULL,
  `id_produk` INT NOT NULL,
  `kuantitas` INT NOT NULL DEFAULT 1,
  `harga_satuan` INT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_detail_pesanan` (`id_pesanan`),
  KEY `idx_detail_produk` (`id_produk`),
  CONSTRAINT `fk_detail_pesanan` FOREIGN KEY (`id_pesanan`) REFERENCES `pesanan` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_detail_produk` FOREIGN KEY (`id_produk`) REFERENCES `produk` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------------------------------------
-- 11. Table `ulasan`
-- ----------------------------------------------------------
CREATE TABLE `ulasan` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_pesanan` INT NOT NULL,
  `id_produk` INT NOT NULL,
  `rating` TINYINT NOT NULL COMMENT '1 sampai 5',
  `komentar` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ulasan_pesanan` (`id_pesanan`),
  KEY `idx_ulasan_produk` (`id_produk`),
  CONSTRAINT `fk_ulasan_pesanan` FOREIGN KEY (`id_pesanan`) REFERENCES `pesanan` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_ulasan_produk` FOREIGN KEY (`id_produk`) REFERENCES `produk` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ==========================================================
-- DATA AWAL (SEEDER) SIAP TEST DI LOCALHOST
-- ==========================================================

-- Kategori awal
INSERT INTO `kategori` (`id`, `nama_kategori`) VALUES
(1, 'GPU'),
(2, 'CPU'),
(3, 'RAM'),
(4, 'Laptop'),
(5, 'Phone'),
(6, 'Monitor'),
(7, 'Headphones'),
(8, 'Aksesoris');

-- Akun awal (Semua akun dummy menggunakan password: "password")
-- Hash bcrypt $2y$10$ kompatibel langsung dengan PHP password_verify()
-- 1. Admin          : admin@loote.com  | pass: password
-- 2. Verified User  : seller@loote.com | pass: password (is_verified = 1, bisa jual barang)
-- 3. Regular User   : rizky@gmail.com  | pass: password (is_verified = 0, wajib verifikasi dulu untuk jual)
INSERT INTO `pengguna` (`id`, `nama`, `email`, `password`, `peran`, `nik`, `no_hp`, `alamat`, `info_rekening`, `is_verified`) VALUES
(1, 'Admin Loot-E', 'admin@loote.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NULL, NULL, NULL, NULL, 0),
(2, 'Loot-E Official Store', 'seller@loote.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', '3174011505000001', '081234567890', 'Jl. Teknologi No. 10, Jakarta Selatan', '{"nama_bank":"BCA","nama_pemilik":"Loot-E Official Store","nomor_rekening":"1234567890"}', 1),
(3, 'Rizky', 'rizky@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', NULL, NULL, NULL, NULL, 0);

-- Produk contoh (milik user id = 2 / Loot-E Official Store)
INSERT INTO `produk` (`id`, `id_penjual`, `id_kategori`, `nama_produk`, `foto`, `harga`, `stok`, `kondisi`, `deskripsi`, `bisa_nego`, `bisa_barter`, `status_produk`) VALUES
(1, 2, 1, 'GTX 1660 Super 6GB GDDR6', NULL, 2400000, 5, 'Bekas - Seperti Baru', '{"ringkasan":"GTX 1660 Super 6GB GDDR6 bekas pemakaian gaming ringan, suhu adem dan siap langsung pakai.","spesifikasi":[{"label":"Graphics Engine","nilai":"NVIDIA GeForce GTX 1660 Super"},{"label":"Video Memory","nilai":"6GB GDDR6 192-bit"},{"label":"Bus Standard","nilai":"PCI Express 3.0"},{"label":"Min. PSU","nilai":"450 W"}],"riwayat":{"fisik":"Mulus terawat","fungsi":"Normal 100%, suhu adem","lama_pakai":"1 tahun pemakaian gaming ringan","servis":"Belum pernah servis","minus":"Tidak ada","kelengkapan":"Fullset Box Original"}}', 1, 1, 'tersedia'),
(2, 2, 2, 'AMD Ryzen 5 5600X 6-Core 12-Thread', NULL, 2100000, 7, 'Baru', '{"ringkasan":"Prosesor AMD Ryzen 5 5600X kondisi baru segel resmi, cocok untuk gaming dan produktivitas.","spesifikasi":[{"label":"Socket","nilai":"AM4"},{"label":"Cores / Threads","nilai":"6 Cores / 12 Threads"},{"label":"Base / Boost Clock","nilai":"3.7 GHz / 4.6 GHz"},{"label":"TDP","nilai":"65 W"}],"riwayat":{"fisik":"Baru segel","fungsi":"Normal 100%","lama_pakai":"Baru","servis":"Tidak pernah","minus":"Tidak ada","kelengkapan":"Fullset dengan Wraith Stealth cooler bawaan"}}', 1, 0, 'tersedia'),
(3, 2, 3, 'Team T-Force Delta RGB 16GB (2x8GB) DDR4 3200MHz', NULL, 850000, 10, 'Baru', '{"ringkasan":"RAM DDR4 dual channel 16GB dengan pencahayaan RGB, kondisi baru segel dan garansi resmi.","spesifikasi":[{"label":"Kapasitas","nilai":"16GB (2x8GB)"},{"label":"Tipe","nilai":"DDR4 3200MHz"},{"label":"Garansi","nilai":"Garansi Resmi Lifetime Warranty"}],"riwayat":{"fisik":"Baru segel","fungsi":"Normal 100%","lama_pakai":"Baru","servis":"Tidak pernah","minus":"Tidak ada","kelengkapan":"Fullset, garansi resmi lifetime warranty"}}', 0, 0, 'tersedia'),
(4, 2, 4, 'Lenovo Ideapad S145-15AST', NULL, 1725000, 3, 'Bekas - Mulus', '{"ringkasan":"Laptop harian ringan untuk kerja, kuliah, dan browsing. Kondisi terawat siap pakai.","spesifikasi":[{"label":"Layar","nilai":"15.6 inch HD"},{"label":"Storage","nilai":"SSD 256GB"},{"label":"RAM","nilai":"8GB DDR4"}],"riwayat":{"fisik":"Mulus 95%, lecet halus wajar","fungsi":"Normal, baterai tahan 3-4 jam","lama_pakai":"2 tahun","servis":"Belum pernah servis","minus":"Tidak ada","kelengkapan":"Unit + Charger Original"}}', 1, 1, 'tersedia'),
(5, 2, 5, 'iPhone 11 128GB Black iBox', NULL, 3150000, 4, 'Bekas - Mulus', '{"ringkasan":"iPhone 11 resmi iBox 128GB warna hitam, layar bening dan seluruh fitur biometrik aktif normal.","spesifikasi":[{"label":"Kapasitas","nilai":"128GB"},{"label":"Garansi / Asal","nilai":"Resmi iBox Indonesia"},{"label":"Battery Health","nilai":"85%"}],"riwayat":{"fisik":"Mulus, layar bening","fungsi":"True Tone dan Face ID normal lancar, BH 85%","lama_pakai":"2 tahun","servis":"Belum pernah bongkar","minus":"Tidak ada","kelengkapan":"Fullset resmi iBox"}}', 1, 1, 'tersedia'),
(6, 2, 6, 'ASUS ROG Strix XG27AQM 270Hz', NULL, 6441000, 2, 'Baru', '{"ringkasan":"Monitor gaming kompetitif 27 inch WQHD dengan refresh rate 270Hz dan panel Fast IPS.","spesifikasi":[{"label":"Ukuran Layar","nilai":"27 inch WQHD (2560x1440)"},{"label":"Panel","nilai":"Fast IPS"},{"label":"Refresh Rate","nilai":"270Hz"},{"label":"Response Time","nilai":"0.5ms (GTG)"}],"riwayat":{"fisik":"Baru segel","fungsi":"27 inch Fast IPS 2K WQHD 270Hz 0.5ms, no dead pixel","lama_pakai":"Baru","servis":"Tidak pernah","minus":"Tidak ada","kelengkapan":"Fullset Box Original"}}', 0, 0, 'tersedia'),
(7, 2, 7, 'Sony WH-1000XM4 Wireless Noise Cancelling', NULL, 3200000, 3, 'Bekas - Seperti Baru', '{"ringkasan":"Headphone wireless flagship dengan Active Noise Cancelling, nyaman dipakai lama dan suara jernih.","spesifikasi":[{"label":"Konektivitas","nilai":"Bluetooth 5.0 / Aux 3.5mm"},{"label":"Fitur Utama","nilai":"Active Noise Cancelling (ANC)"},{"label":"Port Pengisian","nilai":"USB Type-C"}],"riwayat":{"fisik":"Earpad mulus tidak mengelupas","fungsi":"Active Noise Cancelling normal 100%","lama_pakai":"8 bulan","servis":"Belum pernah servis","minus":"Tidak ada","kelengkapan":"Unit, Carrying Case, Kabel USB-C & Aux"}}', 1, 0, 'tersedia'),
(8, 2, 8, 'Logitech G Pro X Superlight Wireless', NULL, 1450000, 6, 'Bekas - Mulus', '{"ringkasan":"Mouse gaming wireless ultra-ringan dengan sensor HERO 25K, responsif untuk FPS kompetitif.","spesifikasi":[{"label":"Sensor","nilai":"HERO 25K"},{"label":"Konektivitas","nilai":"LIGHTSPEED Wireless"},{"label":"Bobot","nilai":"< 63 gram"}],"riwayat":{"fisik":"Mulus, grip bersih","fungsi":"Sensor Hero 25K normal, baterai tahan hingga 70 jam","lama_pakai":"6 bulan","servis":"Belum pernah servis","minus":"Tidak ada","kelengkapan":"Fullset Dongle + Kabel + Box"}}', 1, 1, 'tersedia');

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
