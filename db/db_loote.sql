-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 27, 2026 at 01:06 AM
-- Server version: 8.4.3
-- PHP Version: 8.3.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_loote`
--

-- --------------------------------------------------------

--
-- Table structure for table `antrean`
--

CREATE TABLE `antrean` (
  `id` char(36) NOT NULL,
  `id_pembeli` char(36) DEFAULT NULL,
  `id_produk` char(36) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL COMMENT 'menunggu, aktif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `barter`
--

CREATE TABLE `barter` (
  `id` char(36) NOT NULL,
  `id_pembeli` char(36) DEFAULT NULL,
  `id_produk_diincar` char(36) DEFAULT NULL,
  `id_produk_ditawarkan` char(36) DEFAULT NULL,
  `kuantitas_diincar` int DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL COMMENT 'pending, counter_offer, diterima, ditolak'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `detail_pesanan`
--

CREATE TABLE `detail_pesanan` (
  `id` char(36) NOT NULL,
  `id_pesanan` char(36) DEFAULT NULL,
  `id_produk` char(36) DEFAULT NULL,
  `kuantitas` int DEFAULT NULL,
  `harga_satuan` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kategori`
--

CREATE TABLE `kategori` (
  `id` int NOT NULL,
  `nama_kategori` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `kategori`
--

INSERT INTO `kategori` (`id`, `nama_kategori`) VALUES
(1, 'GPU'),
(2, 'CPU'),
(3, 'RAM'),
(4, 'Laptop'),
(5, 'Phone'),
(6, 'Monitor'),
(7, 'Headphones'),
(8, 'Aksesoris');

-- --------------------------------------------------------

--
-- Table structure for table `negosiasi`
--

CREATE TABLE `negosiasi` (
  `id` char(36) NOT NULL,
  `id_pembeli` char(36) DEFAULT NULL,
  `id_produk` char(36) DEFAULT NULL,
  `kuantitas` int DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL COMMENT 'pending, counter_offer, diterima, ditolak, expired',
  `batas_waktu` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pengguna`
--

CREATE TABLE `pengguna` (
  `id` char(36) NOT NULL,
  `nama` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `peran` varchar(50) DEFAULT NULL COMMENT 'buyer, seller, admin'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `pengguna`
--

INSERT INTO `pengguna` (`id`, `nama`, `email`, `password`, `peran`) VALUES
('2f8b9f39-c0dc-4aec-bfe4-30e6c82e16c4', 'alfi', 'pantathitambergetar69@yahoo.com', '$2y$10$gZbGq4dwvXvhiwOrZTMVKeCkgfRLRNF.bNT/M89KEQ2J3NOxfRcgu', 'buyer'),
('57e37f19-1491-4665-aed5-7577767447f4', 'Loot-E Official Store', 'seller@loote.com', '$2y$10$zCE6nSrFntq5f23oVd/rfOcIvD2LSLpHI1QK9mVlyI4BZQ2eHlWDK', 'seller'),
('846e532e-67f5-4379-99a2-eba03711326c', 'Rizky', 'rizky@gmail.com', '$2y$10$quqO6hf8YKN97vHBwztd7uW5RdjMQmhwdwW/lBqZEJKxQoAfh5RXS', 'buyer');

-- --------------------------------------------------------

--
-- Table structure for table `pesanan`
--

CREATE TABLE `pesanan` (
  `id` char(36) NOT NULL,
  `id_pembeli` char(36) DEFAULT NULL,
  `tipe_transaksi` varchar(50) DEFAULT NULL COMMENT 'beli_langsung, hasil_nego, hasil_barter',
  `total_harga` int DEFAULT NULL,
  `status_pesanan` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `produk`
--

CREATE TABLE `produk` (
  `id` char(36) NOT NULL,
  `id_penjual` char(36) DEFAULT NULL,
  `id_kategori` int DEFAULT NULL,
  `nama_produk` varchar(255) DEFAULT NULL,
  `harga` int DEFAULT NULL,
  `stok` int DEFAULT NULL,
  `deskripsi` text,
  `kondisi` varchar(255) DEFAULT NULL,
  `status_produk` varchar(50) DEFAULT NULL COMMENT 'tersedia, habis'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `produk`
--

INSERT INTO `produk` (`id`, `id_penjual`, `id_kategori`, `nama_produk`, `harga`, `stok`, `deskripsi`, `kondisi`, `status_produk`) VALUES
('0396be61-4e98-4bb3-845e-3638b86be2d1', '57e37f19-1491-4665-aed5-7577767447f4', 5, 'iPhone 11 128GB Black iBox', 3150000, 4, 'iPhone 11 resmi iBox, battery health 85%, True Tone dan Face ID normal lancar.', 'Bekas - Mulus', 'tersedia'),
('0fcdd962-379f-4dee-a989-80f6674cc41b', '57e37f19-1491-4665-aed5-7577767447f4', 4, 'Lenovo Ideapad S145-15AST', 1725000, 3, 'Laptop kerja & sekolah, layar 15.6 inch, baterai masih tahan 3-4 jam.', 'Bekas - Mulus', 'tersedia'),
('2e61db61-9511-4585-b6dc-9307080084b9', '57e37f19-1491-4665-aed5-7577767447f4', 3, 'Team T-Force Delta RGB 16GB (2x8GB) DDR4 3200MHz', 850000, 10, 'RAM Gaming RGB White Edition, lifetime warranty, garansi resmi.', 'Baru', 'tersedia'),
('3e91cfd2-252c-4239-ab9c-222d7abc0c5a', '57e37f19-1491-4665-aed5-7577767447f4', 8, 'Logitech G Pro X Superlight Wireless', 1450000, 6, 'Mouse gaming ultra-ringan 63g sensor Hero 25K, baterai tahan hingga 70 jam.', 'Bekas - Mulus', 'tersedia'),
('65449220-fa37-4a8f-8407-b65d4230d087', '57e37f19-1491-4665-aed5-7577767447f4', 2, 'AMD Ryzen 5 5600X 6-Core 12-Thread', 2100000, 7, 'Processor gaming kencang, lengkap dengan Wraith Stealth cooler bawaan.', 'Baru', 'tersedia'),
('a3d88102-07cd-457a-b3e5-fffd2c92c0ca', '57e37f19-1491-4665-aed5-7577767447f4', 7, 'Sony WH-1000XM4 Wireless Noise Cancelling', 3200000, 3, 'Headphone flagship Sony dengan Active Noise Cancelling terbaik, earpad mulus.', 'Bekas - Seperti Baru', 'tersedia'),
('da1bd27c-386c-4eb0-9498-3d33290d4af7', '57e37f19-1491-4665-aed5-7577767447f4', 1, 'GTX 1660 Super 6GB GDDR6', 2400000, 5, 'Kartu grafis gaming kondisi sangat terawat, suhu adem, pemakaian normal gaming.', 'Bekas - Seperti Baru', 'tersedia'),
('e5ea304b-dc95-45f5-b5ab-5bca4bee07f0', '57e37f19-1491-4665-aed5-7577767447f4', 6, 'ASUS ROG Strix XG27AQM 270Hz', 6441000, 2, 'Monitor gaming 27 inch Fast IPS 2K WQHD 270Hz 0.5ms G-Sync Compatible, no dead pixel.', 'Baru', 'tersedia');

-- --------------------------------------------------------

--
-- Table structure for table `riwayat_barter`
--

CREATE TABLE `riwayat_barter` (
  `id` char(36) NOT NULL,
  `id_barter` char(36) DEFAULT NULL,
  `pengirim` varchar(50) DEFAULT NULL COMMENT 'pembeli, penjual',
  `tambah_dana` int DEFAULT NULL,
  `waktu` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `riwayat_negosiasi`
--

CREATE TABLE `riwayat_negosiasi` (
  `id` char(36) NOT NULL,
  `id_negosiasi` char(36) DEFAULT NULL,
  `pengirim` varchar(50) DEFAULT NULL COMMENT 'pembeli, penjual',
  `harga` int DEFAULT NULL,
  `waktu` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ulasan`
--

CREATE TABLE `ulasan` (
  `id` char(36) NOT NULL,
  `id_pesanan` char(36) DEFAULT NULL,
  `id_produk` char(36) DEFAULT NULL,
  `rating` int DEFAULT NULL,
  `komentar` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `antrean`
--
ALTER TABLE `antrean`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_pembeli` (`id_pembeli`),
  ADD KEY `id_produk` (`id_produk`);

--
-- Indexes for table `barter`
--
ALTER TABLE `barter`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_pembeli` (`id_pembeli`),
  ADD KEY `id_produk_diincar` (`id_produk_diincar`),
  ADD KEY `id_produk_ditawarkan` (`id_produk_ditawarkan`);

--
-- Indexes for table `detail_pesanan`
--
ALTER TABLE `detail_pesanan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_pesanan` (`id_pesanan`),
  ADD KEY `id_produk` (`id_produk`);

--
-- Indexes for table `kategori`
--
ALTER TABLE `kategori`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `negosiasi`
--
ALTER TABLE `negosiasi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_pembeli` (`id_pembeli`),
  ADD KEY `id_produk` (`id_produk`);

--
-- Indexes for table `pengguna`
--
ALTER TABLE `pengguna`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pesanan`
--
ALTER TABLE `pesanan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_pembeli` (`id_pembeli`);

--
-- Indexes for table `produk`
--
ALTER TABLE `produk`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_penjual` (`id_penjual`),
  ADD KEY `id_kategori` (`id_kategori`);

--
-- Indexes for table `riwayat_barter`
--
ALTER TABLE `riwayat_barter`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_barter` (`id_barter`);

--
-- Indexes for table `riwayat_negosiasi`
--
ALTER TABLE `riwayat_negosiasi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_negosiasi` (`id_negosiasi`);

--
-- Indexes for table `ulasan`
--
ALTER TABLE `ulasan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_pesanan` (`id_pesanan`),
  ADD KEY `id_produk` (`id_produk`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `kategori`
--
ALTER TABLE `kategori`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `antrean`
--
ALTER TABLE `antrean`
  ADD CONSTRAINT `antrean_ibfk_1` FOREIGN KEY (`id_pembeli`) REFERENCES `pengguna` (`id`),
  ADD CONSTRAINT `antrean_ibfk_2` FOREIGN KEY (`id_produk`) REFERENCES `produk` (`id`);

--
-- Constraints for table `barter`
--
ALTER TABLE `barter`
  ADD CONSTRAINT `barter_ibfk_1` FOREIGN KEY (`id_pembeli`) REFERENCES `pengguna` (`id`),
  ADD CONSTRAINT `barter_ibfk_2` FOREIGN KEY (`id_produk_diincar`) REFERENCES `produk` (`id`),
  ADD CONSTRAINT `barter_ibfk_3` FOREIGN KEY (`id_produk_ditawarkan`) REFERENCES `produk` (`id`);

--
-- Constraints for table `detail_pesanan`
--
ALTER TABLE `detail_pesanan`
  ADD CONSTRAINT `detail_pesanan_ibfk_1` FOREIGN KEY (`id_pesanan`) REFERENCES `pesanan` (`id`),
  ADD CONSTRAINT `detail_pesanan_ibfk_2` FOREIGN KEY (`id_produk`) REFERENCES `produk` (`id`);

--
-- Constraints for table `negosiasi`
--
ALTER TABLE `negosiasi`
  ADD CONSTRAINT `negosiasi_ibfk_1` FOREIGN KEY (`id_pembeli`) REFERENCES `pengguna` (`id`),
  ADD CONSTRAINT `negosiasi_ibfk_2` FOREIGN KEY (`id_produk`) REFERENCES `produk` (`id`);

--
-- Constraints for table `pesanan`
--
ALTER TABLE `pesanan`
  ADD CONSTRAINT `pesanan_ibfk_1` FOREIGN KEY (`id_pembeli`) REFERENCES `pengguna` (`id`);

--
-- Constraints for table `produk`
--
ALTER TABLE `produk`
  ADD CONSTRAINT `produk_ibfk_1` FOREIGN KEY (`id_penjual`) REFERENCES `pengguna` (`id`),
  ADD CONSTRAINT `produk_ibfk_2` FOREIGN KEY (`id_kategori`) REFERENCES `kategori` (`id`);

--
-- Constraints for table `riwayat_barter`
--
ALTER TABLE `riwayat_barter`
  ADD CONSTRAINT `riwayat_barter_ibfk_1` FOREIGN KEY (`id_barter`) REFERENCES `barter` (`id`);

--
-- Constraints for table `riwayat_negosiasi`
--
ALTER TABLE `riwayat_negosiasi`
  ADD CONSTRAINT `riwayat_negosiasi_ibfk_1` FOREIGN KEY (`id_negosiasi`) REFERENCES `negosiasi` (`id`);

--
-- Constraints for table `ulasan`
--
ALTER TABLE `ulasan`
  ADD CONSTRAINT `ulasan_ibfk_1` FOREIGN KEY (`id_pesanan`) REFERENCES `pesanan` (`id`),
  ADD CONSTRAINT `ulasan_ibfk_2` FOREIGN KEY (`id_produk`) REFERENCES `produk` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
