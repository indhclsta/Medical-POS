-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 13, 2025 at 03:22 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `medipos`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `activity` varchar(255) NOT NULL,
  `timestamp` datetime NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `admin_id`, `username`, `activity`, `timestamp`, `ip_address`) VALUES
(1, 29, 'user', 'Melakukan transaksi #232 dengan total Rp 1.234', '2025-08-11 18:36:55', NULL),
(2, 29, 'user', 'Melakukan transaksi #233 dengan total Rp 15.300', '2025-08-11 18:38:23', NULL),
(3, 29, 'user', 'Melakukan transaksi #234 dengan total Rp 15.000', '2025-08-11 19:02:05', NULL),
(4, 29, 'user', 'Melakukan transaksi #237 dengan total Rp 5.000', '2025-08-11 19:11:52', NULL),
(5, 29, 'user', 'Melakukan transaksi #238 dengan total Rp 5.000', '2025-08-11 19:17:57', NULL),
(6, 38, 'exxx', 'Melakukan transaksi #239 dengan total Rp 27.000', '2025-08-12 08:27:03', NULL),
(7, 38, 'exxx', 'Melakukan transaksi #240 dengan total Rp 30.000', '2025-08-12 09:11:08', NULL),
(8, 38, 'exxx', 'Melakukan transaksi #241 dengan total Rp 24.300', '2025-08-12 09:20:42', NULL),
(9, 29, 'user', 'Melakukan transaksi #242 dengan total Rp 10.000', '2025-08-12 11:00:07', NULL),
(10, 29, 'user', 'Melakukan transaksi #243 dengan total Rp 1.234', '2025-08-12 11:05:32', NULL),
(11, 29, 'user', 'Melakukan transaksi #244 dengan total Rp 15.000', '2025-08-12 11:15:06', NULL),
(12, 29, 'user', 'Melakukan transaksi #245 dengan total Rp 5.000', '2025-08-12 18:54:50', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `image` varchar(255) NOT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expiry` datetime DEFAULT NULL,
  `role` enum('super_admin','cashier') NOT NULL DEFAULT 'cashier',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `verified_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `email`, `username`, `password`, `image`, `reset_token`, `reset_expiry`, `role`, `created_at`, `status`, `verified_by`) VALUES
(1, 'indahcalistaexcella@gmail.com', 'Indah ', '$2y$10$qoyJwW6Pjue/c6VoMuTlCO9MnsHIymCfGNpWKGcPrd6Tv8s2dnGfi', 'Laporan (1).png', NULL, NULL, 'super_admin', '2025-08-02 14:21:01', 'active', NULL),
(9, 'ayushafira2107@gmail.com', 'Ayu', '$2y$10$Ef3o/7g3mFn7N9y.zQenl.uRfnI58/y1xVbPpltWprQD.7DL2Us2W', 'photostrip (2).jpg', NULL, NULL, 'cashier', '2025-08-02 14:21:01', 'inactive', NULL),
(29, 'user@gmail.com', 'user', '$2y$10$r2vfAkOPI.DV9KD8/F1TT.no975CoE9XfgMM6mhJx41axsWHjypv.', '689ae6350bbfc_transaksi.jpg', 'e7e25ac27febc690b887e1dbc6a9a59ab343708f69fbb48c46738bf11d078e5e8533e203f9ac40c0d227b94f38cf10644622', '2025-08-08 10:05:45', 'cashier', '2025-08-05 02:39:46', 'active', NULL),
(38, 'indah.callista26@smk.belajar.id', 'exxx', '$2y$10$SD3hugxsJB71EkzM/zRcP.7JiEwJN0dze2PjZxr3q0.8UmW0.z7b2', '689a980f0f043_3D Box Cipro 500.png', NULL, NULL, 'cashier', '2025-08-12 00:04:00', 'active', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `category`
--

CREATE TABLE `category` (
  `id` int(11) NOT NULL,
  `category` varchar(255) NOT NULL,
  `image` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `category`
--

INSERT INTO `category` (`id`, `category`, `image`) VALUES
(22, 'Obat Bebas', 'categories/6895a77b5a730_logo-obat-bebas-doktersehat.png'),
(23, 'Obat Bebas Terbatas', 'categories/6895a7993ea03_obat bebas terbatas.png'),
(24, 'Obat Keras', 'categories/6895a7c0c386c_logo-obat-keras-doktersehat-800x799.png'),
(25, 'Obat Jamu', 'categories/6895a877e7106_logo jamu depkes.png');

-- --------------------------------------------------------

--
-- Table structure for table `discounts`
--

CREATE TABLE `discounts` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `jenis_diskon` enum('persen','nominal') NOT NULL,
  `nilai_diskon` decimal(10,2) NOT NULL,
  `tgl_mulai` date NOT NULL,
  `tgl_berakhir` date NOT NULL,
  `status` enum('aktif','non-aktif') DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `member`
--

CREATE TABLE `member` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `point` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','non-active') NOT NULL,
  `last_active` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `inactive_duration` int(11) DEFAULT 30,
  `duration_unit` enum('MINUTE','HOUR','DAY','MONTH') DEFAULT 'DAY'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `member`
--

INSERT INTO `member` (`id`, `name`, `phone`, `point`, `status`, `last_active`, `inactive_duration`, `duration_unit`) VALUES
(1, 'Indah', '081284421151', 665, 'non-active', '2025-08-12 02:43:15', 30, 'DAY'),
(3, 'Excella', '1234654300', 4, 'non-active', '2025-08-05 04:05:35', 30, 'DAY'),
(6, 'oke', '0812345', 23, 'non-active', '2025-08-05 04:05:35', 30, 'DAY'),
(7, 'job', '1234554', 5, 'non-active', '2025-05-14 00:48:32', 30, 'DAY'),
(11, 'Laisa', '08997498189', 613, 'non-active', '2025-05-20 04:27:38', 30, 'DAY'),
(17, 'Aisya', '0812714423', 10, 'non-active', '2025-07-28 03:17:39', 30, 'DAY'),
(18, 'ara', '08129378864', 0, 'non-active', '2025-05-26 07:16:39', 30, 'DAY'),
(20, 'Call', '081383528994', 0, 'non-active', '2025-08-12 02:07:48', 30, 'DAY');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `exp` date DEFAULT NULL,
  `qty` int(11) NOT NULL,
  `starting_price` decimal(10,0) NOT NULL,
  `selling_price` decimal(10,0) NOT NULL,
  `margin` decimal(10,2) NOT NULL,
  `fid_category` int(11) NOT NULL,
  `image` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL,
  `barcode` varchar(255) DEFAULT NULL,
  `barcode_image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `product_name`, `exp`, `qty`, `starting_price`, `selling_price`, `margin`, `fid_category`, `image`, `description`, `barcode`, `barcode_image`) VALUES
(37, 'Tolak Linu', NULL, 0, 123, 1234, 1111.00, 25, '1754638801_produk-tolak-linu.png', 'Sidomuncul', '6895a9d1699ca', 'barcode_1754638801.png'),
(38, 'Paracetamol', '2025-08-16', 3, 10000, 15000, 5000.00, 22, '1754829096_paracetamol-box-1.png', 'Paracetamol box', '68989128467aa', 'barcode_1754829096.png'),
(39, 'Oralit', '2025-08-19', 5, 7000, 10000, 3000.00, 22, '1754829143_apotek_online_k24klik_2017040603332513_1413-Oralit-200.png', 'Obat diare', '68989157e90a6', 'barcode_1754829143.png'),
(40, 'CTM (Chlorpheniramine Maleate', '2025-08-16', 11, 15000, 17000, 2000.00, 23, '1754829220_1660032059_61b3688eb5a5e2062d979766.jpg', 'CTM (Chlorpheniramine Maleate) – antihistamin', '689891a4e8642', 'barcode_1754829220.png'),
(41, 'Dextromethorphan', '2025-08-16', 13, 10000, 15000, 5000.00, 23, '1754829275_thuoc-dextromethorphan-30mg-2100.jpg', 'Dextromethorphan HBr – antitusif', '689891db98c5a', 'barcode_1754829275.png'),
(42, 'Amoxicillin', '2025-08-16', 12, 17000, 20000, 3000.00, 24, '1754829337_AMOXICILLIN-SODIUM-INJECTION.png', 'Amoxicillin (antibiotik)', '68989219a6a3a', 'barcode_1754829337.png'),
(43, 'Ciprofloxacin ', '2025-08-23', 16, 17000, 20000, 3000.00, 24, '1754829413_3D Box Cipro 500.png', 'Ciprofloxacin (antibiotik)', '689892656dfad', 'barcode_1754829413.png'),
(44, 'Entrostop', '2025-08-16', 12, 3000, 5000, 2000.00, 25, '1754829461_87a2d73f3569cc8ba6c640310056bf81.jpg', 'Entrostop anak', '68989295ae667', 'barcode_1754829461.png');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `date` timestamp NOT NULL DEFAULT current_timestamp(),
  `fid_admin` int(11) NOT NULL,
  `fid_member` int(11) DEFAULT NULL,
  `fid_product` int(11) DEFAULT NULL,
  `detail` varchar(255) DEFAULT NULL,
  `total_price` decimal(12,2) DEFAULT NULL,
  `margin_total` decimal(10,2) NOT NULL,
  `paid_amount` decimal(10,0) DEFAULT 0,
  `kembalian` decimal(10,0) DEFAULT 0,
  `payment_method` enum('tunai','qris') NOT NULL,
  `final_total` int(11) NOT NULL,
  `points` int(11) DEFAULT 0,
  `discount` decimal(3,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `date`, `fid_admin`, `fid_member`, `fid_product`, `detail`, `total_price`, `margin_total`, `paid_amount`, `kembalian`, `payment_method`, `final_total`, `points`, `discount`) VALUES
(185, '2025-05-19 04:33:25', 1, 1, NULL, '', 6300.00, 2000.00, 6300, 0, 'tunai', 0, 0, 0.10),
(186, '2025-05-19 04:34:42', 1, 11, NULL, '', 22500.00, 6000.00, 22500, 0, 'tunai', 0, 2, 0.10),
(187, '2025-05-19 06:56:55', 1, 1, NULL, '', 19800.00, 5000.00, 19800, 0, 'tunai', 0, 2, 0.10),
(188, '2025-05-19 12:42:12', 1, 1, NULL, '', 9000.00, 3000.00, 9000, 0, 'tunai', 0, 1, 0.10),
(189, '2025-05-20 01:52:59', 1, 1, NULL, '', 121500.00, 30000.00, 145000, 23500, 'tunai', 0, 13, 0.10),
(190, '2025-05-20 01:54:47', 1, 17, NULL, '', 30000.00, 6000.00, 30000, 0, 'tunai', 0, 3, 0.00),
(191, '2025-05-20 04:54:49', 1, 1, NULL, '', 13500.00, 3000.00, 13500, 0, 'tunai', 0, 1, 0.10),
(192, '2025-07-22 00:55:43', 1, NULL, NULL, '', 10000.00, 3000.00, 10000, 0, 'tunai', 0, 1, 0.00),
(193, '2025-07-22 01:28:21', 1, NULL, NULL, '', 10000.00, 3000.00, 50000, 40000, 'tunai', 0, 1, 0.00),
(194, '2025-07-22 01:57:33', 1, 17, NULL, '', 70000.00, 21000.00, 70000, 0, 'tunai', 0, 7, 0.00),
(195, '2025-07-22 01:58:28', 1, 1, NULL, '', 13500.00, 6000.00, 14000, 500, 'tunai', 0, 1, 0.10),
(196, '2025-07-24 04:19:27', 1, NULL, NULL, '', 56789.00, -177778.00, 57000, 211, 'tunai', 0, 5, 0.00),
(197, '2025-08-10 03:32:37', 29, NULL, NULL, '', 1234.00, 1234.00, 0, 0, 'tunai', 0, 0, NULL),
(198, '2025-08-10 03:36:47', 29, NULL, NULL, '', 1234.00, 1234.00, 0, 0, 'tunai', 0, 0, NULL),
(199, '2025-08-10 03:37:38', 29, NULL, NULL, '', 1234.00, 1234.00, 0, 0, 'tunai', 0, 0, NULL),
(200, '2025-08-10 03:37:50', 29, NULL, NULL, '', 1234.00, 1234.00, 0, 0, 'tunai', 0, 0, NULL),
(201, '2025-08-10 03:38:32', 29, NULL, NULL, '', 1234.00, 1234.00, 0, 0, 'tunai', 0, 0, NULL),
(202, '2025-08-10 03:39:00', 29, NULL, NULL, '', 1234.00, 1234.00, 0, 0, 'tunai', 0, 0, NULL),
(203, '2025-08-10 03:40:09', 29, NULL, NULL, '', 1234.00, 1234.00, 0, 0, 'tunai', 0, 0, NULL),
(204, '2025-08-10 05:01:32', 29, NULL, NULL, '', 1234.00, 0.00, 2000, 766, 'tunai', 1234, 0, 0.00),
(205, '2025-08-10 12:07:51', 29, NULL, NULL, '', 1234.00, 0.00, 2000, 766, 'tunai', 1234, 0, 0.00),
(206, '2025-08-10 12:13:33', 29, NULL, NULL, '', 1234.00, 0.00, 2000, 766, 'tunai', 1234, 0, 0.00),
(207, '2025-08-10 12:59:39', 29, NULL, NULL, '', 25000.00, 0.00, 27000, 2000, 'tunai', 25000, 2, 0.00),
(208, '2025-08-10 13:03:19', 29, NULL, NULL, '', 15000.00, 0.00, 15000, 0, 'tunai', 15000, 1, 0.00),
(209, '2025-08-10 13:04:38', 29, NULL, NULL, '', 15000.00, 0.00, 15000, 0, 'tunai', 15000, 1, 0.00),
(210, '2025-08-10 13:09:22', 29, 1, NULL, '', 13500.00, 0.00, 15000, 1500, 'tunai', 13500, 1, 0.00),
(211, '2025-08-10 13:20:47', 29, NULL, NULL, '', 10000.00, 0.00, 10000, 0, 'tunai', 10000, 1, 0.00),
(212, '2025-08-10 23:22:03', 29, NULL, NULL, '', 1234.00, 0.00, 2000, 766, 'tunai', 1234, 0, 0.00),
(213, '2025-08-10 23:31:57', 29, 1, 39, 'Pembelian Oralit', 10000.00, 3000.00, 10000, 0, 'tunai', 10000, 1, 0.00),
(214, '2025-08-10 23:33:58', 29, NULL, NULL, NULL, 15000.00, 0.00, 15000, 0, 'tunai', 15000, 1, 0.00),
(215, '2025-08-11 00:02:04', 29, NULL, NULL, NULL, 1234.00, 0.00, 2000, 766, 'tunai', 1234, 0, 0.00),
(216, '2025-08-11 01:11:40', 29, NULL, NULL, NULL, 1234.00, 0.00, 2000, 766, 'tunai', 1234, 0, 0.00),
(217, '2025-08-11 01:19:13', 29, NULL, NULL, NULL, 1234.00, 0.00, 2000, 766, 'tunai', 1234, 0, 0.00),
(218, '2025-08-11 01:20:13', 29, NULL, NULL, NULL, 15000.00, 0.00, 15000, 0, 'tunai', 15000, 1, 0.00),
(219, '2025-08-11 01:22:32', 29, NULL, NULL, NULL, 1234.00, 0.00, 2000, 766, 'tunai', 1234, 0, 0.00),
(220, '2025-08-11 01:23:27', 29, NULL, NULL, NULL, 1234.00, 0.00, 2000, 766, 'tunai', 1234, 0, 0.00),
(221, '2025-08-11 01:25:39', 29, NULL, NULL, NULL, 1234.00, 0.00, 2000, 766, 'tunai', 1234, 0, 0.00),
(222, '2025-08-11 01:28:58', 29, NULL, NULL, NULL, 1234.00, 0.00, 2000, 766, 'tunai', 1234, 0, 0.00),
(223, '2025-08-11 01:30:42', 29, 1, NULL, NULL, 13500.00, 0.00, 15000, 1500, 'tunai', 13500, 1, 0.00),
(224, '2025-08-11 01:33:22', 29, NULL, NULL, NULL, 10000.00, 0.00, 10000, 0, 'tunai', 10000, 1, 0.00),
(225, '2025-08-11 01:35:34', 29, NULL, NULL, NULL, 27000.00, 0.00, 30000, 3000, 'tunai', 27000, 2, 0.00),
(226, '2025-08-11 01:39:00', 29, NULL, NULL, NULL, 10000.00, 0.00, 10000, 0, 'tunai', 10000, 1, 0.00),
(227, '2025-08-11 07:42:45', 29, NULL, NULL, NULL, 10000.00, 0.00, 12000, 2000, 'tunai', 10000, 1, 0.00),
(228, '2025-08-11 07:51:29', 29, NULL, NULL, NULL, 10000.00, 0.00, 10000, 0, 'tunai', 10000, 1, 0.00),
(229, '2025-08-11 07:57:27', 29, NULL, NULL, NULL, 10000.00, 0.00, 10900, 900, 'tunai', 10000, 1, 0.00),
(230, '2025-08-11 11:32:44', 29, NULL, NULL, NULL, 10000.00, 0.00, 20000, 10000, 'tunai', 10000, 1, 0.00),
(231, '2025-08-11 11:33:05', 29, NULL, NULL, NULL, 10000.00, 0.00, 10000, 0, 'tunai', 10000, 1, 0.00),
(232, '2025-08-11 11:36:55', 29, NULL, NULL, NULL, 1234.00, 0.00, 2000, 766, '', 1234, 0, 0.00),
(233, '2025-08-11 11:38:23', 29, 1, NULL, NULL, 15300.00, 0.00, 17000, 1700, '', 15300, 1, 0.00),
(234, '2025-08-11 12:02:05', 29, NULL, NULL, NULL, 15000.00, 0.00, 15000, 0, '', 15000, 1, 0.00),
(237, '2025-08-11 12:11:52', 29, NULL, NULL, NULL, 5000.00, 0.00, 5000, 0, '', 5000, 0, 0.00),
(238, '2025-08-11 12:17:57', 29, NULL, NULL, NULL, 5000.00, 0.00, 5000, 0, '', 5000, 0, 0.00),
(239, '2025-08-12 01:27:03', 38, NULL, NULL, NULL, 27000.00, 0.00, 30000, 3000, '', 27000, 2, 0.00),
(240, '2025-08-12 02:11:08', 38, NULL, NULL, NULL, 30000.00, 0.00, 35000, 5000, '', 30000, 3, 0.00),
(241, '2025-08-12 02:20:42', 38, 1, NULL, NULL, 24300.00, 0.00, 25000, 700, '', 24300, 2, 0.00),
(242, '2025-08-12 04:00:06', 29, NULL, NULL, NULL, 10000.00, 0.00, 19000, 9000, '', 10000, 1, 0.00),
(243, '2025-08-12 04:05:32', 29, NULL, NULL, NULL, 1234.00, 0.00, 2000, 766, '', 1234, 0, 0.00),
(244, '2025-08-12 04:15:06', 29, NULL, NULL, NULL, 15000.00, 0.00, 16000, 1000, 'tunai', 15000, 1, 0.00),
(245, '2025-08-12 11:54:50', 29, NULL, NULL, NULL, 5000.00, 0.00, 5000, 0, 'tunai', 5000, 0, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `transaction_details`
--

CREATE TABLE `transaction_details` (
  `id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `subtotal` decimal(10,0) NOT NULL,
  `harga` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transaction_details`
--

INSERT INTO `transaction_details` (`id`, `transaction_id`, `product_id`, `quantity`, `subtotal`, `harga`) VALUES
(64, 185, 25, 1, 7000, 7000),
(65, 186, 26, 1, 15000, 15000),
(66, 186, 27, 1, 10000, 10000),
(67, 187, 25, 1, 7000, 7000),
(68, 187, 26, 1, 15000, 15000),
(69, 188, 27, 1, 10000, 10000),
(70, 189, 26, 7, 105000, 15000),
(71, 189, 27, 3, 30000, 10000),
(72, 190, 26, 2, 30000, 15000),
(73, 191, 26, 1, 15000, 15000),
(74, 192, 27, 1, 10000, 10000),
(75, 193, 27, 1, 10000, 10000),
(76, 194, 27, 7, 70000, 10000),
(77, 195, 28, 3, 15000, 5000),
(78, 196, 35, 1, 56789, 56789),
(79, 197, 37, 1, 1234, 1234),
(80, 198, 37, 1, 1234, 1234),
(81, 199, 37, 1, 1234, 1234),
(82, 200, 37, 1, 1234, 1234),
(83, 201, 37, 1, 1234, 1234),
(84, 202, 37, 1, 1234, 1234),
(85, 203, 37, 1, 1234, 1234),
(86, 204, 37, 1, 1234, 1234),
(87, 205, 37, 1, 1234, 1234),
(88, 206, 37, 1, 1234, 1234),
(89, 207, 38, 1, 15000, 15000),
(90, 207, 39, 1, 10000, 10000),
(91, 208, 38, 1, 15000, 15000),
(92, 209, 38, 1, 15000, 15000),
(93, 210, 38, 1, 15000, 15000),
(94, 211, 39, 1, 10000, 10000),
(95, 212, 37, 1, 1234, 1234),
(96, 214, 38, 1, 15000, 15000),
(97, 215, 37, 1, 1234, 1234),
(98, 216, 37, 1, 1234, 1234),
(99, 217, 37, 1, 1234, 1234),
(100, 218, 38, 1, 15000, 15000),
(101, 219, 37, 1, 1234, 1234),
(102, 220, 37, 1, 1234, 1234),
(103, 221, 37, 1, 1234, 1234),
(104, 222, 37, 1, 1234, 1234),
(105, 223, 38, 1, 15000, 15000),
(106, 224, 39, 1, 10000, 10000),
(107, 225, 39, 1, 10000, 10000),
(108, 225, 40, 1, 17000, 17000),
(109, 226, 39, 1, 10000, 10000),
(110, 227, 39, 1, 10000, 10000),
(111, 228, 39, 1, 10000, 10000),
(112, 229, 39, 1, 10000, 10000),
(113, 230, 39, 1, 10000, 10000),
(114, 231, 39, 1, 10000, 10000),
(115, 232, 37, 1, 1234, 1234),
(116, 233, 40, 1, 17000, 17000),
(117, 234, 38, 1, 15000, 15000),
(118, 237, 44, 1, 5000, 5000),
(119, 238, 44, 1, 5000, 5000),
(120, 239, 39, 1, 10000, 10000),
(121, 239, 40, 1, 17000, 17000),
(122, 240, 44, 2, 10000, 5000),
(123, 240, 43, 1, 20000, 20000),
(124, 241, 39, 1, 10000, 10000),
(125, 241, 40, 1, 17000, 17000),
(126, 242, 39, 1, 10000, 10000),
(127, 243, 37, 1, 1234, 1234),
(128, 244, 38, 1, 15000, 15000),
(129, 245, 44, 1, 5000, 5000);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_email` (`email`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `category`
--
ALTER TABLE `category`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `discounts`
--
ALTER TABLE `discounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `member`
--
ALTER TABLE `member`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_products_category` (`fid_category`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fid_admin` (`fid_admin`),
  ADD KEY `fid_member` (`fid_member`),
  ADD KEY `fid_product` (`fid_product`);

--
-- Indexes for table `transaction_details`
--
ALTER TABLE `transaction_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaction_id` (`transaction_id`),
  ADD KEY `product_id` (`product_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `category`
--
ALTER TABLE `category`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `discounts`
--
ALTER TABLE `discounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `member`
--
ALTER TABLE `member`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=246;

--
-- AUTO_INCREMENT for table `transaction_details`
--
ALTER TABLE `transaction_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=130;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `discounts`
--
ALTER TABLE `discounts`
  ADD CONSTRAINT `discounts_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`fid_category`) REFERENCES `category` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `FK_product` FOREIGN KEY (`fid_product`) REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`fid_admin`) REFERENCES `admin` (`id`),
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`fid_member`) REFERENCES `member` (`id`),
  ADD CONSTRAINT `transactions_ibfk_3` FOREIGN KEY (`fid_product`) REFERENCES `products` (`id`);

--
-- Constraints for table `transaction_details`
--
ALTER TABLE `transaction_details`
  ADD CONSTRAINT `transaction_details_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
