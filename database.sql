-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 28, 2026 at 09:40 AM
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
-- Database: `buka_puasa_booking`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_notifications`
--

CREATE TABLE `admin_notifications` (
  `id` int(11) NOT NULL,
  `type` varchar(64) NOT NULL,
  `message` text NOT NULL,
  `booking_reference` varchar(64) DEFAULT NULL,
  `meta_json` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_notifications`
--

INSERT INTO `admin_notifications` (`id`, `type`, `message`, `booking_reference`, `meta_json`, `created_at`) VALUES
(1, 'proof_uploaded', 'New payment proof uploaded for BR26-65672.', 'BR26-65672', '{\"slot_date\":\"2026-03-08\"}', '2026-01-22 14:46:48'),
(2, 'proof_uploaded', 'New payment proof uploaded for BR26-91227.', 'BR26-91227', '{\"slot_date\":\"2026-02-22\"}', '2026-01-22 15:06:51'),
(3, 'proof_duplicate', 'Duplicate payment proof detected for BR26-91227 (matches BR26-65672).', 'BR26-91227', '{\"duplicate_booking_reference\":\"BR26-65672\"}', '2026-01-22 15:06:51'),
(4, 'proof_uploaded', 'New payment proof uploaded for BR26-73834.', 'BR26-73834', '{\"slot_date\":\"2026-03-16\"}', '2026-01-22 16:36:36'),
(5, 'proof_uploaded', 'New payment proof uploaded for BR26-71645.', 'BR26-71645', '{\"slot_date\":\"2026-03-02\"}', '2026-01-22 17:00:09'),
(6, 'proof_uploaded', 'New payment proof uploaded for BR26-22700.', 'BR26-22700', '{\"slot_date\":\"2026-02-21\"}', '2026-01-23 10:06:57'),
(7, 'proof_duplicate', 'Duplicate payment proof detected for BR26-22700 (matches BR26-71645).', 'BR26-22700', '{\"duplicate_booking_reference\":\"BR26-71645\"}', '2026-01-23 10:06:57'),
(8, 'proof_uploaded', 'New payment proof uploaded for BR26-80629.', 'BR26-80629', '{\"slot_date\":\"2026-03-03\"}', '2026-01-23 12:33:10'),
(9, 'proof_duplicate', 'Duplicate payment proof detected for BR26-80629 (matches BR26-71645).', 'BR26-80629', '{\"duplicate_booking_reference\":\"BR26-71645\"}', '2026-01-23 12:33:10'),
(10, 'proof_uploaded', 'New payment proof uploaded for BR26-17380.', 'BR26-17380', '{\"slot_date\":\"2026-03-03\"}', '2026-01-23 14:56:39'),
(11, 'proof_duplicate', 'Duplicate payment proof detected for BR26-17380 (matches BR26-71645).', 'BR26-17380', '{\"duplicate_booking_reference\":\"BR26-71645\"}', '2026-01-23 14:56:39'),
(12, 'payment_rejected', 'Booking rejected for BR26-17380.', 'BR26-17380', '{\"reason\":\"Tipu\"}', '2026-01-23 15:05:37'),
(13, 'payment_approved', 'Payment approved for BR26-54717.', 'BR26-54717', NULL, '2026-01-23 15:12:31'),
(14, 'payment_approved', 'Payment approved for BR26-80629.', 'BR26-80629', NULL, '2026-01-23 15:13:10'),
(15, 'payment_approved', 'Payment approved for BR26-22700.', 'BR26-22700', NULL, '2026-01-23 15:13:19'),
(16, 'payment_approved', 'Payment approved for BR26-71645.', 'BR26-71645', NULL, '2026-01-23 15:13:30'),
(17, 'payment_approved', 'Payment approved for BR26-70111.', 'BR26-70111', NULL, '2026-01-23 15:24:09'),
(18, 'proof_uploaded', 'New payment proof uploaded for BR26-49030.', 'BR26-49030', '{\"slot_date\":\"2026-02-22\"}', '2026-01-23 15:37:55'),
(19, 'proof_duplicate', 'Duplicate payment proof detected for BR26-49030 (matches BR26-71645).', 'BR26-49030', '{\"duplicate_booking_reference\":\"BR26-71645\"}', '2026-01-23 15:37:55'),
(20, 'payment_approved', 'Payment approved for BR26-49030.', 'BR26-49030', NULL, '2026-01-23 15:40:38'),
(21, 'proof_uploaded', 'New payment proof uploaded for BR26-37581.', 'BR26-37581', '{\"slot_date\":\"2026-03-17\"}', '2026-01-23 15:41:37'),
(22, 'proof_duplicate', 'Duplicate payment proof detected for BR26-37581 (matches BR26-71645).', 'BR26-37581', '{\"duplicate_booking_reference\":\"BR26-71645\"}', '2026-01-23 15:41:37'),
(23, 'proof_uploaded', 'New payment proof uploaded for BR26-50918.', 'BR26-50918', '{\"slot_date\":\"2026-02-25\"}', '2026-01-23 15:49:08'),
(24, 'proof_duplicate', 'Duplicate payment proof detected for BR26-50918 (matches BR26-71645).', 'BR26-50918', '{\"duplicate_booking_reference\":\"BR26-71645\"}', '2026-01-23 15:49:08'),
(25, 'payment_approved', 'Payment approved for BR26-50918.', 'BR26-50918', NULL, '2026-01-23 16:03:58'),
(26, 'payment_approved', 'Payment approved for BR26-37581.', 'BR26-37581', NULL, '2026-01-23 16:25:58'),
(27, 'payment_approved', 'Payment approved for MAN2601268631.', 'MAN2601268631', NULL, '2026-01-26 15:33:51'),
(28, 'payment_approved', 'Payment approved for MAN2601261892.', 'MAN2601261892', NULL, '2026-01-26 15:33:57'),
(29, 'payment_approved', 'Payment approved for BR26-77535.', 'BR26-77535', NULL, '2026-01-27 11:28:18'),
(30, 'proof_uploaded', 'New payment proof uploaded for BR26-57607.', 'BR26-57607', '{\"slot_date\":\"2026-03-19\"}', '2026-01-27 15:22:14'),
(31, 'proof_uploaded', 'New payment proof uploaded for BR26-99352.', 'BR26-99352', '{\"slot_date\":\"2026-02-28\"}', '2026-01-28 09:36:11'),
(32, 'proof_uploaded', 'New payment proof uploaded for WP26-30097.', 'WP26-30097', '{\"slot_date\":\"2026-02-28\"}', '2026-01-28 09:38:33'),
(33, 'payment_approved', 'Payment approved for BR26-99352.', 'BR26-99352', NULL, '2026-01-28 09:38:53'),
(34, 'proof_uploaded', 'New payment proof uploaded for WP26-74391.', 'WP26-74391', '{\"slot_date\":\"2026-03-01\"}', '2026-01-28 09:58:14'),
(35, 'proof_duplicate', 'Duplicate payment proof detected for WP26-74391 (matches BR26-99352).', 'WP26-74391', '{\"duplicate_booking_reference\":\"BR26-99352\"}', '2026-01-28 09:58:14'),
(36, 'proof_uploaded', 'New payment proof uploaded for WP26-95602.', 'WP26-95602', '{\"slot_date\":\"2026-03-18\"}', '2026-01-28 10:13:05'),
(37, 'payment_approved', 'Payment approved for WP26-95602.', 'WP26-95602', NULL, '2026-01-28 10:15:39'),
(38, 'proof_uploaded', 'New payment proof uploaded for WP26-21760.', 'WP26-21760', '{\"slot_date\":\"2026-03-19\"}', '2026-01-28 11:35:27'),
(39, 'proof_duplicate', 'Duplicate payment proof detected for WP26-21760 (matches BR26-71645).', 'WP26-21760', '{\"duplicate_booking_reference\":\"BR26-71645\"}', '2026-01-28 11:35:27'),
(40, 'payment_approved', 'Payment approved for WP26-21760.', 'WP26-21760', NULL, '2026-01-28 11:36:38'),
(41, 'proof_uploaded', 'New payment proof uploaded for WP26-91749.', 'WP26-91749', '{\"slot_date\":\"2026-03-09\"}', '2026-01-28 16:22:15'),
(42, 'proof_duplicate', 'Duplicate payment proof detected for WP26-91749 (matches BR26-57607).', 'WP26-91749', '{\"duplicate_booking_reference\":\"BR26-57607\"}', '2026-01-28 16:22:15');

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `username` varchar(64) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `role` varchar(32) NOT NULL DEFAULT 'admin',
  `password_valid_from` time DEFAULT NULL,
  `password_valid_until` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `password_hash`, `is_active`, `created_at`, `role`, `password_valid_from`, `password_valid_until`) VALUES
(1, 'admin', '$2y$12$LE3FMpceKpiMAbnxR.r5uuv0ym8we0LbKxBCdxp8DQdNz.aBzWuDO', 1, '2026-01-21 10:35:51', 'admin', NULL, NULL),
(3, 'bq123', '$2y$12$DUW1bZ.5VAiJuk.cFzj24eHGYvja6WL5o9YiQS5/ZqyAlm366MGfO', 1, '2026-01-21 10:38:28', 'banquet', NULL, NULL),
(5, 'entry1', '$2y$12$V03pDqylW9b74AMx.eGI3ufH8VwsuSFpEw5Tvxrs7rw5XBbC43B7u', 1, '2026-01-21 10:39:17', 'entry_duty', '17:00:00', '23:59:59'),
(6, 'boss', '$2y$12$qkeMlp8bvIe1Uu4YgnP6pOJzoKhiTjFKSaRoO/Xiicc3ZO4hMxyUy', 1, '2026-01-21 10:51:41', 'ent_admin', NULL, NULL),
(8, 'Finance1', '$2y$12$dU4./MiZBTm2JQc2Eh/p6ePxeebPRbAcJUdYIgpY8CrfevYkINyWW', 1, '2026-01-27 12:50:36', 'finance', NULL, NULL),
(9, 'Sales1', '$2y$12$mCmIQMmoB3kt4lf7/EdalOhW9NFKF.95C6awOY5s5j1hiGX/0lQnO', 1, '2026-01-27 16:58:29', 'staff', NULL, NULL),
(10, 'wanfarhanfuad', '$2y$12$jQHkFaX8i.0u5P8WVefP7Obb2NApp9LcWJ2wXIyc1MWPAg.xH6g2q', 1, '2026-01-28 15:54:42', 'admin', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `booking_reference` varchar(64) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `phone` varchar(40) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `slot_date` date NOT NULL,
  `quantity_dewasa` int(11) NOT NULL DEFAULT 0,
  `quantity_kanak` int(11) NOT NULL DEFAULT 0,
  `quantity_warga_emas` int(11) NOT NULL DEFAULT 0,
  `total_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('PENDING','PAID','FAILED') NOT NULL DEFAULT 'PENDING',
  `checkin_status` varchar(32) NOT NULL DEFAULT 'Not Checked',
  `payment_proof` varchar(255) DEFAULT NULL,
  `billcode` varchar(64) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `paid_at` datetime DEFAULT NULL,
  `military_no` varchar(40) DEFAULT NULL,
  `remark` text DEFAULT NULL,
  `quantity_atm` int(11) NOT NULL DEFAULT 0,
  `payment_method` varchar(16) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `payment_proof_hash` varchar(64) DEFAULT NULL,
  `quantity_kanak_foc` int(11) NOT NULL DEFAULT 0,
  `staff_blanket_qty` int(11) NOT NULL DEFAULT 0,
  `miss_office_qty` int(11) NOT NULL DEFAULT 0,
  `living_in_qty` int(11) NOT NULL DEFAULT 0,
  `table_no` varchar(255) DEFAULT NULL,
  `bank_received_status` enum('PENDING','CONFIRMED','NOT_RECEIVED') NOT NULL DEFAULT 'PENDING',
  `bank_not_received_reason` text DEFAULT NULL,
  `bank_confirmed_at` datetime DEFAULT NULL,
  `free_quantity_dewasa` int(11) NOT NULL DEFAULT 0,
  `free_quantity_kanak` int(11) NOT NULL DEFAULT 0,
  `free_quantity_kanak_foc` int(11) NOT NULL DEFAULT 0,
  `free_quantity_warga_emas` int(11) NOT NULL DEFAULT 0,
  `free_quantity_atm` int(11) NOT NULL DEFAULT 0,
  `comp_qty` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `booking_reference`, `full_name`, `phone`, `email`, `slot_date`, `quantity_dewasa`, `quantity_kanak`, `quantity_warga_emas`, `total_price`, `payment_status`, `checkin_status`, `payment_proof`, `billcode`, `created_at`, `paid_at`, `military_no`, `remark`, `quantity_atm`, `payment_method`, `rejection_reason`, `payment_proof_hash`, `quantity_kanak_foc`, `staff_blanket_qty`, `miss_office_qty`, `living_in_qty`, `table_no`, `bank_received_status`, `bank_not_received_reason`, `bank_confirmed_at`, `free_quantity_dewasa`, `free_quantity_kanak`, `free_quantity_kanak_foc`, `free_quantity_warga_emas`, `free_quantity_atm`, `comp_qty`) VALUES
(1, 'ENT26-23492', 'lhjk', '01126587463', NULL, '2026-02-25', 1, 1, 1, 0.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 11:04:07', '2026-01-21 11:04:07', NULL, '', 0, 'COMP', NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(2, 'BR26-61116', 'Datin y', '01121011123', '', '2026-03-13', 2, 0, 0, 190.00, 'PENDING', 'Not Checked', NULL, NULL, '2026-01-21 11:15:23', NULL, '', '', 0, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(3, 'BR26-37635', 'Leftenan Madya Ahmad', '01126587463', '', '2026-02-23', 0, 0, 0, 85.00, 'PENDING', 'Not Checked', NULL, NULL, '2026-01-21 12:50:16', NULL, 'NV8702564', '', 1, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(143, 'BR26-10473', 'Puan Siti Aisyah Binti Ahmad', '0123456789', NULL, '2026-02-21', 2, 1, 0, 246.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 09:10:00', NULL, '', 'Permintaan kerusi bayi jika ada', 0, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'CONFIRMED', NULL, '2026-01-27 16:20:39', 0, 0, 0, 0, 0, 0),
(144, 'BR26-11892', 'Encik Muhammad Razif Bin Hassan', '0198765432', NULL, '2026-02-22', 3, 0, 1, 392.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 09:25:00', NULL, '', NULL, 0, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(145, 'BR26-12645', 'Puan Nur Amirah Binti Ismail', '0172345678', NULL, '2026-02-23', 1, 2, 0, 198.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 09:40:00', NULL, '', NULL, 0, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(146, 'BR26-13780', 'Encik Mohd Azlan Bin Omar', '0145678901', NULL, '2026-02-24', 4, 1, 0, 442.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 09:55:00', NULL, '', NULL, 0, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(147, 'BR26-14936', 'Puan Farah Nadhirah Binti Yusof', '0167890123', NULL, '2026-02-25', 2, 2, 0, 296.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 10:10:00', NULL, '', NULL, 0, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(148, 'BR26-15307', 'Encik Khairul Anwar Bin Rahman', '0134567890', NULL, '2026-02-26', 3, 1, 1, 440.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 10:25:00', NULL, '', 'Meja tepi jika boleh', 0, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(149, 'BR26-16754', 'Puan Balqis Binti Hamzah', '0189012345', NULL, '2026-02-27', 1, 1, 0, 148.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 10:40:00', NULL, '', NULL, 0, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(150, 'BR26-17209', 'Encik Faiz Bin Karim', '0112345678', NULL, '2026-02-28', 2, 0, 2, 392.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 10:55:00', NULL, '', NULL, 0, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(151, 'BR26-18561', 'Puan Siti Khadijah Binti Salleh', '0156789012', NULL, '2026-03-01', 2, 1, 1, 344.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 11:10:00', NULL, '', NULL, 0, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(152, 'BR26-19348', 'Encik Haziq Bin Zulkifli', '0193456789', NULL, '2026-03-02', 4, 0, 0, 392.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 11:25:00', NULL, '', NULL, 0, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(153, 'BR26-20417', 'Puan Nor Aina Binti Ahmad', '0128901234', NULL, '2026-03-03', 3, 2, 0, 394.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 11:40:00', NULL, '', NULL, 0, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(154, 'BR26-21590', 'Encik Shahrul Nizam Bin Hassan', '0175678901', NULL, '2026-03-04', 2, 1, 0, 246.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 11:55:00', NULL, '', NULL, 0, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(155, 'BR26-22371', 'Puan Hanis Binti Omar', '0147890123', NULL, '2026-03-05', 1, 3, 0, 248.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 12:10:00', NULL, '', NULL, 0, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(156, 'BR26-23684', 'Encik Syafiq Bin Ismail', '0169012345', NULL, '2026-03-06', 4, 1, 1, 588.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 12:25:00', NULL, '', 'Mohon tempat tidak terlalu bising', 0, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(157, 'BR26-24135', 'Puan Nur Syuhada Binti Rahman', '0132345678', NULL, '2026-03-07', 2, 2, 1, 442.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 12:40:00', NULL, '', NULL, 0, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(158, 'BR26-25809', 'Encik Zaid Bin Yusof', '0184567890', NULL, '2026-03-08', 3, 0, 2, 490.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 12:55:00', NULL, '', NULL, 0, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(159, 'BR26-26944', 'Puan Ainul Mardhiah Binti Ahmad', '0116789012', NULL, '2026-03-09', 2, 1, 0, 246.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 13:10:00', NULL, '', NULL, 0, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(160, 'BR26-27413', 'Encik Azmir Bin Salleh', '0158901234', NULL, '2026-03-10', 1, 2, 0, 198.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 13:25:00', NULL, '', NULL, 0, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(161, 'BR26-28675', 'Puan Nurul Iman Binti Hassan', '0192345678', NULL, '2026-03-11', 3, 1, 1, 440.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 13:40:00', NULL, '', NULL, 0, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(162, 'BR26-29706', 'Encik Firdaus Bin Omar', '0125678901', NULL, '2026-03-12', 2, 0, 1, 294.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 13:55:00', NULL, '', NULL, 0, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(163, 'BR26-30812', 'Mejar Ahmad Bin Ismail', '0139988776', NULL, '2026-02-21', 2, 1, 0, 344.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 14:10:00', NULL, '3012345', NULL, 1, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(164, 'BR26-31957', 'Leftenan Kamal Bin Hassan', '0171122334', NULL, '2026-02-22', 3, 0, 0, 490.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 14:25:00', NULL, '7523456', NULL, 2, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(165, 'BR26-32740', 'Kapten Razif Bin Ahmad', '0145566778', NULL, '2026-02-23', 1, 2, 0, 246.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 14:40:00', NULL, '3017777', 'Mohon duduk berdekatan keluarga', 1, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(166, 'BR26-33291', 'Leftenan Muda Azlan Bin Omar', '0162233445', NULL, '2026-02-24', 4, 1, 0, 540.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 14:55:00', NULL, '7598765', NULL, 1, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(167, 'BR26-34806', 'Mejar Zulkifli Bin Rahman', '0183344556', NULL, '2026-02-25', 2, 2, 0, 492.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 15:10:00', NULL, '3012222', NULL, 2, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(168, 'BR26-35179', 'Kapten Farhan Bin Khalid', '0114455667', NULL, '2026-02-26', 3, 1, 0, 442.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 15:25:00', NULL, '3013333', NULL, 1, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(169, 'BR26-36620', 'Leftenan Jamaluddin Bin Ismail', '0155566778', NULL, '2026-02-27', 1, 0, 0, 294.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 15:40:00', NULL, '3018888', NULL, 2, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(170, 'BR26-37458', 'Mejar Shamsul Bin Ahmad', '0196677889', NULL, '2026-02-28', 4, 2, 0, 638.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 15:55:00', NULL, '7529999', NULL, 1, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(171, 'BR26-38916', 'Kapten Rohani Bin Hassan', '0127788990', NULL, '2026-03-01', 2, 1, 0, 540.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 16:10:00', NULL, '3015555', NULL, 3, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(172, 'BR26-39247', 'Leftenan Muda Azmi Bin Rahman', '0178899001', NULL, '2026-03-02', 3, 0, 0, 392.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 16:25:00', NULL, '7512345', NULL, 1, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(173, 'BR26-40593', 'Leftenan Komander Siti Aishah Binti Ahmad', '0132211334', NULL, '2026-03-03', 2, 2, 0, 394.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 16:40:00', NULL, '812345', NULL, 1, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(174, 'BR26-41602', 'Kapten Muhammad Razif Bin Hassan', '0143322445', NULL, '2026-03-04', 3, 1, 0, 540.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 16:55:00', NULL, '823456', 'Jika boleh meja berhampiran surau', 2, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(175, 'BR26-42785', 'Leftenan Norhayati Binti Omar', '0164433556', NULL, '2026-03-05', 1, 1, 0, 246.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 17:10:00', NULL, '834567', NULL, 1, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(176, 'BR26-43814', 'Leftenan Muda Azlan Bin Sulaiman', '0185544667', NULL, '2026-03-06', 4, 0, 0, 588.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 17:25:00', NULL, '845678', NULL, 2, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(177, 'BR26-44927', 'Kapten Farhan Bin Khalid', '0116655778', NULL, '2026-03-07', 2, 2, 0, 492.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 17:40:00', NULL, '856789', NULL, 2, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(178, 'BR26-45163', 'Leftenan Komander Amalina Binti Zakaria', '0157766889', NULL, '2026-03-08', 3, 1, 0, 442.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 17:55:00', NULL, '867890', NULL, 1, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(179, 'BR26-46790', 'Leftenan Rahman Bin Hashim', '0198877990', NULL, '2026-03-09', 1, 0, 0, 294.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 18:10:00', NULL, '878901', NULL, 2, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(180, 'BR26-47831', 'Kapten Aida Binti Mahmud', '0129988112', NULL, '2026-03-10', 4, 1, 0, 540.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 18:25:00', NULL, '889012', NULL, 1, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(181, 'BR26-48955', 'Leftenan Muda Azmi Bin Yusoff', '0171100223', NULL, '2026-03-11', 2, 1, 0, 442.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 18:40:00', NULL, '890123', NULL, 2, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(182, 'BR26-49208', 'Kapten Salwa Binti Karim', '0142200334', NULL, '2026-03-12', 3, 0, 0, 392.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 18:55:00', NULL, '801234', NULL, 1, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(183, 'BR26-50319', 'Mejar Zulkifli Bin Rahman', '0163300445', NULL, '2026-03-13', 2, 1, 0, 344.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 19:10:00', NULL, '712345', NULL, 1, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(184, 'BR26-51462', 'Leftenan Mariam Binti Sulaiman', '0184400556', NULL, '2026-03-14', 3, 2, 0, 492.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 19:25:00', NULL, '723456', 'Naak dekat dengan stage', 1, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(185, 'BR26-52874', 'Kapten Jamaluddin Bin Hassan', '0115500667', NULL, '2026-03-15', 1, 1, 0, 344.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 19:40:00', NULL, '734567', NULL, 2, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(186, 'BR26-53701', 'Leftenan Muda Hidayah Binti Ahmad', '0156600778', NULL, '2026-03-16', 4, 1, 0, 540.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 19:55:00', NULL, '745678', NULL, 1, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(187, 'BR26-54896', 'Mejar Shamsul Bin Ismail', '0197700889', NULL, '2026-03-17', 2, 2, 0, 492.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 20:10:00', NULL, '756789', NULL, 2, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(189, 'BR26-56378', 'Leftenan Aishah Binti Rahman', '0179900112', NULL, '2026-03-19', 1, 0, 0, 392.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 20:40:00', NULL, '778901', NULL, 3, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(190, 'BR26-57410', 'Mejar Khairul Anwar Bin Hassan', '0141011223', NULL, '2026-03-05', 4, 2, 0, 638.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 20:55:00', NULL, '789012', NULL, 1, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(191, 'BR26-58642', 'Kapten Fatimah Binti Ali', '0182122334', NULL, '2026-03-07', 2, 1, 0, 442.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 21:10:00', NULL, '790123', NULL, 2, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(192, 'BR26-59731', 'Leftenan Muda Ahmad Bin Ismail', '0113233445', NULL, '2026-03-09', 3, 0, 0, 490.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-21 21:25:00', NULL, '701234', 'Tempat duduk di kawasan tepi', 2, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(193, 'BR26-70111', 'Puan Nur Aina Binti Ahmad', '0127001111', NULL, '2026-02-21', 2, 1, 0, 246.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-22 10:05:00', '2026-01-23 15:24:09', '', 'Permintaan kerusi bayi jika ada', 0, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(194, 'BR26-70127', 'Encik Mohd Hafiz Bin Hassan', '0197001127', NULL, '2026-02-22', 3, 0, 1, 392.00, 'PENDING', 'Not Checked', NULL, NULL, '2026-01-22 10:12:00', NULL, '', NULL, 0, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(195, 'BR26-70139', 'Puan Siti Hajar Binti Omar', '0177001139', NULL, '2026-02-24', 1, 2, 0, 198.00, 'PENDING', 'Not Checked', NULL, NULL, '2026-01-22 10:20:00', NULL, '', NULL, 0, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(196, 'BR26-70152', 'Encik Amirul Bin Rahman', '0147001152', NULL, '2026-02-25', 4, 1, 0, 442.00, 'PENDING', 'Not Checked', NULL, NULL, '2026-01-22 10:28:00', NULL, '', NULL, 0, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(197, 'BR26-70166', 'Puan Nurul Iman Binti Ismail', '0167001166', NULL, '2026-02-27', 2, 2, 0, 296.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-22 10:35:00', NULL, '', 'Meja tepi jika boleh', 0, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(213, 'BR26-51991', 'Cik Hanna', '0156987452', '', '2026-03-02', 2, 1, 0, 246.00, 'PENDING', 'Not Checked', 'uploads/payment_proof/PENDING_56c39c5b7067b0c8_1768983859.jpg', NULL, '2026-01-21 16:24:19', NULL, '', '', 0, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(214, 'BR26-11383', 'Encik Anuar', '01136246976', '', '2026-02-23', 1, 0, 0, 98.00, 'PENDING', 'Not Checked', 'uploads/payment_proof/PENDING_825e89c9ae1352f3_1768985860.jpg', NULL, '2026-01-21 16:57:40', NULL, '', '', 0, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(215, 'BR26-71770', 'Encik Zahid', '0115479658', '', '2026-02-22', 2, 0, 0, 196.00, 'PENDING', 'Not Checked', 'uploads/payment_proof/PENDING_6beeb3f7e64ad47c_1768987259.png', NULL, '2026-01-21 17:20:59', NULL, '', '', 0, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(216, 'BR26-12946', 'Cik Aminah', '0135468755', '', '2026-03-01', 1, 0, 1, 183.00, 'PENDING', 'Not Checked', 'uploads/payment_proof/PENDING_db0d87c598eb8c0e_1769046289.png', NULL, '2026-01-22 09:44:49', NULL, '', '', 0, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(217, 'BR26-41954', 'Leftenan Muda Aiman', '0115479658', '', '2026-02-22', 0, 0, 0, 85.00, 'PENDING', 'Not Checked', 'uploads/payment_proof/PENDING_9fbd6fa2b152954b_1769049380.jpg', NULL, '2026-01-22 10:36:20', NULL, '3065497', '', 1, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(218, 'BR26-54717', 'Dato Malik', '01121012083', '', '2026-02-23', 1, 0, 0, 98.00, 'PAID', 'Not Checked', 'uploads/payment_proof/PENDING_9b6750b8fe4c453e_1769049524.jpg', NULL, '2026-01-22 10:38:44', '2026-01-23 15:12:31', '', '', 0, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(219, 'ENT26-48819', 'ahmad', '01182754467', NULL, '2026-02-22', 1, 0, 0, 0.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-22 11:42:48', '2026-01-22 11:42:48', NULL, '', 0, 'COMP', NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(220, 'BR26-65672', 'Dato Othman', '0168273265', '', '2026-03-08', 1, 0, 0, 98.00, 'PENDING', 'Not Checked', 'uploads/payment_proof/PENDING_09f9db91aaff1828_1769064408.jpg', NULL, '2026-01-22 14:46:48', NULL, '', '', 0, NULL, NULL, 'f8d47850a105457edb65611e0079f01befd02b5da5987990910f93f1884228cf', 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(221, 'BR26-91227', 'Datin r', '0135468755', '', '2026-02-22', 1, 0, 0, 98.00, 'PENDING', 'Not Checked', 'uploads/payment_proof/PENDING_3c6d0baca9cd9d07_1769065611.jpg', NULL, '2026-01-22 15:06:51', NULL, '', '', 0, NULL, NULL, 'f8d47850a105457edb65611e0079f01befd02b5da5987990910f93f1884228cf', 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(222, 'BR26-73834', 'Tuan h', '0135468755', '', '2026-03-16', 1, 1, 1, 233.00, 'PENDING', 'Not Checked', 'uploads/payment_proof/PENDING_7ea789954d886e4c_1769070996.png', NULL, '2026-01-22 16:36:36', NULL, '', '', 0, NULL, NULL, '2e17efe5ab6e376a68f6fd55fb412067506a017fb99c0ab6c79d09b03a3a3184', 1, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(223, 'BR26-71645', 'Encik y', '0115479658', '', '2026-03-02', 1, 1, 1, 233.00, 'PAID', 'Not Checked', 'uploads/payment_proof/PENDING_0e2e96251de0fbad_1769072409.png', NULL, '2026-01-22 17:00:09', '2026-01-23 15:13:30', '', '', 0, NULL, NULL, '31116baafaa25db3ee7ab8a2035b20996faefc0c05552371b4b7f7fcbf291fc0', 1, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(224, 'BR26-22700', 'Leftenan Muda Haikal', '0115479658', '', '2026-02-21', 0, 1, 0, 135.00, 'PAID', 'Not Checked', 'uploads/payment_proof/PENDING_904f11e88e27012b_1769134017.png', NULL, '2026-01-23 10:06:57', '2026-01-23 15:13:19', '376547', '', 1, NULL, NULL, '31116baafaa25db3ee7ab8a2035b20996faefc0c05552371b4b7f7fcbf291fc0', 1, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(225, 'BR26-80629', 'Datuk Aiman Malik', '0123654987', '', '2026-03-03', 1, 0, 1, 183.00, 'PAID', 'Not Checked', 'uploads/payment_proof/PENDING_6f6456e351a92e99_1769142790.png', NULL, '2026-01-23 12:33:10', '2026-01-23 15:13:10', '', '', 0, NULL, NULL, '31116baafaa25db3ee7ab8a2035b20996faefc0c05552371b4b7f7fcbf291fc0', 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(226, 'BR26-17380', 'Datin Yas', '01154789963', '', '2026-03-03', 1, 0, 1, 183.00, 'FAILED', 'Not Checked', 'uploads/payment_proof/PENDING_565970114aacfecc_1769151399.png', NULL, '2026-01-23 14:56:39', NULL, '', '', 0, NULL, 'Tipu', '31116baafaa25db3ee7ab8a2035b20996faefc0c05552371b4b7f7fcbf291fc0', 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(227, 'ENT26-49788', 'Amir', '0146958773', NULL, '2026-02-25', 2, 0, 0, 0.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-23 15:14:49', '2026-01-23 15:14:49', NULL, '', 0, 'COMP', NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(228, 'ENT26-13128', 'Amir', '0146958773', NULL, '2026-02-25', 2, 0, 0, 0.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-23 15:18:08', '2026-01-23 15:18:08', NULL, '', 0, 'COMP', NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(229, 'ENT26-22171', 'Amirul', '01125347798', NULL, '2026-02-23', 2, 1, 0, 0.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-23 15:21:38', '2026-01-23 15:21:38', NULL, '', 0, 'COMP', NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(230, 'BR26-49030', 'Cik Aisyah', '01165847935', '', '2026-02-22', 2, 0, 0, 196.00, 'PAID', 'Not Checked', 'uploads/payment_proof/PENDING_ce32eb1d3ef2a4e4_1769153875.png', NULL, '2026-01-23 15:37:55', '2026-01-23 15:40:38', '', 'pasojqwndobdajsldbgydagsdpacbasjbddhgqougyetoqldvasdhaspdabsahbsdhgqhlewvbqjhvascuaygcypuygdpqudqpqe', 0, NULL, NULL, '31116baafaa25db3ee7ab8a2035b20996faefc0c05552371b4b7f7fcbf291fc0', 1, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(231, 'BR26-37581', 'Kapten Alif', '01123694158', '', '2026-03-17', 0, 0, 0, 170.00, 'PAID', 'Not Checked', 'uploads/payment_proof/PENDING_d39caed0cfc136b3_1769154097.png', NULL, '2026-01-23 15:41:37', '2026-01-23 16:25:58', '3098125', 'Nak kerusi baby', 2, NULL, NULL, '31116baafaa25db3ee7ab8a2035b20996faefc0c05552371b4b7f7fcbf291fc0', 1, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(232, 'ENT26-96143', 'Aminah', '0145698753', NULL, '2026-03-08', 1, 0, 0, 0.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-23 15:48:29', '2026-01-23 15:48:29', NULL, '123456789', 0, 'COMP', NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(233, 'BR26-50918', 'Puan Anur', '0123654987', '', '2026-02-25', 1, 0, 0, 98.00, 'PAID', 'Not Checked', 'uploads/payment_proof/PENDING_5c62cf357af810f5_1769154548.png', NULL, '2026-01-23 15:49:08', '2026-01-23 16:03:58', '', '', 0, NULL, NULL, '31116baafaa25db3ee7ab8a2035b20996faefc0c05552371b4b7f7fcbf291fc0', 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(236, 'BR26-77535', 'test manual', '0142135003', NULL, '2026-02-22', 1, 1, 0, 198.00, 'PAID', 'Not Checked', 'uploads/payment_proof/APPROVED_10f88e28211746b4_1769484498.png', NULL, '2026-01-27 11:25:47', '2026-01-27 11:28:18', NULL, '', 0, 'MANUAL', NULL, NULL, 1, 1, 0, 0, '', 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(237, 'BR26-26731', 'tesssss', '01121011123', NULL, '2026-02-21', 1, 0, 0, 115.00, 'PENDING', 'Not Checked', NULL, NULL, '2026-01-27 11:27:36', NULL, NULL, '', 0, 'MANUAL', NULL, NULL, 0, 0, 1, 0, '', 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(238, 'BR26-35128', 'fdsd', '47521', NULL, '2026-02-22', 0, 0, 0, 50.00, 'PENDING', 'Not Checked', NULL, NULL, '2026-01-27 14:49:43', NULL, NULL, '', 0, 'MANUAL', NULL, NULL, 0, 1, 0, 0, '', 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(239, 'BR26-57607', 'Tuan Amirual', '01126587463', '', '2026-03-19', 1, 1, 0, 148.00, 'PENDING', 'Not Checked', 'uploads/payment_proof/PENDING_0db0a198e9d32aad_1769498534.jpg', NULL, '2026-01-27 15:22:14', NULL, '', '', 0, NULL, NULL, 'f7642ebb8194814a9f9e921c5b303b10f82ff1f38c284fcf468ca226ae39b2c7', 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(240, 'ENT26-31140', 'Hairul', '01136579954', NULL, '2026-03-15', 0, 0, 0, 0.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-28 09:32:45', '2026-01-28 09:32:45', NULL, 'Nak duduk sebelah OM', 0, 'ENT', NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 4, 2, 1, 1, 0, 0),
(241, 'BR26-99352', 'Dato Farhan', '0167614234', '', '2026-02-28', 5, 1, 1, 625.00, 'PAID', 'Not Checked', 'uploads/payment_proof/PENDING_62c44ece7142643a_1769564171.jpg', NULL, '2026-01-28 09:36:11', '2026-01-28 09:38:53', '', 'saya nak meja depan sekali pls', 0, NULL, NULL, '12f458cf1ab1ab743ce29b7cbd4f558b4dc65cf20904a6cb35debef6c21cca22', 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(242, 'WP26-30097', 'Puan Faiqah', '01155060714', '', '2026-02-28', 1, 3, 1, 333.00, 'PENDING', 'Not Checked', 'uploads/payment_proof/PENDING_d0b02dc9bcda8f75_1769564313.png', NULL, '2026-01-28 09:38:33', NULL, '', 'Baby chair nak 2', 0, NULL, NULL, '3b5698557e3282f75f095c21e71c50e631a2ed07ddf6d0b10038940f94dca643', 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(243, 'WP26-74391', 'Tuan Farhan', '0167614234', '', '2026-03-01', 1, 0, 0, 98.00, 'PENDING', 'Not Checked', 'uploads/payment_proof/PENDING_a498bdfde8b38429_1769565494.jpg', NULL, '2026-01-28 09:58:14', NULL, '', 'saya nak meja depan sekali pls', 0, NULL, NULL, '12f458cf1ab1ab743ce29b7cbd4f558b4dc65cf20904a6cb35debef6c21cca22', 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(244, 'ENT26-53643', 'asdqkwe', '0153254785', NULL, '2026-03-18', 0, 0, 0, 0.00, 'PAID', 'Not Checked', NULL, NULL, '2026-01-28 10:05:58', '2026-01-28 10:05:58', NULL, '', 0, 'ENT', NULL, NULL, 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 1, 1, 0, 1, 0, 0),
(245, 'WP26-95602', 'Datuk asd1', '23', '', '2026-03-18', 1, 2, 1, 283.00, 'PAID', 'Not Checked', 'uploads/payment_proof/PENDING_834d2709e918f0f5_1769566385.png', NULL, '2026-01-28 10:13:05', '2026-01-28 10:15:39', '', '', 0, NULL, NULL, 'e6f041414524f40c42d59dd2da2fbc2dba47c4d3437a60d5ff690b7578bd6aed', 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0),
(246, 'WP26-21760', 'Encik Rais', '01124789965', '', '2026-03-19', 1, 0, 1, 183.00, 'PAID', 'Not Checked', 'uploads/payment_proof/PENDING_a543ea4ea2c9447e_1769571327.png', NULL, '2026-01-28 11:35:27', '2026-01-28 11:36:38', '', '', 0, NULL, NULL, '31116baafaa25db3ee7ab8a2035b20996faefc0c05552371b4b7f7fcbf291fc0', 0, 0, 0, 0, NULL, 'CONFIRMED', NULL, '2026-01-28 11:36:52', 0, 0, 0, 0, 0, 0),
(247, 'WP26-91749', 'Encik Ain', '0162547953', '', '2026-03-09', 1, 0, 0, 98.00, 'PENDING', 'Not Checked', 'uploads/payment_proof/PENDING_7c05c374ca95db24_1769588535.jpg', NULL, '2026-01-28 16:22:15', NULL, '', '', 0, NULL, NULL, 'f7642ebb8194814a9f9e921c5b303b10f82ff1f38c284fcf468ca226ae39b2c7', 0, 0, 0, 0, NULL, 'PENDING', NULL, NULL, 0, 0, 0, 0, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `booking_slots`
--

CREATE TABLE `booking_slots` (
  `id` int(11) NOT NULL,
  `slot_date` date NOT NULL,
  `max_capacity` int(11) NOT NULL DEFAULT 0,
  `booked_count` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking_slots`
--

INSERT INTO `booking_slots` (`id`, `slot_date`, `max_capacity`, `booked_count`, `created_at`) VALUES
(59, '2026-02-21', 700, 3, '2026-01-21 11:52:19'),
(60, '2026-02-22', 700, 8, '2026-01-21 11:52:19'),
(61, '2026-02-23', 700, 6, '2026-01-21 11:52:19'),
(62, '2026-02-24', 700, 0, '2026-01-21 11:52:19'),
(63, '2026-02-25', 700, 8, '2026-01-21 11:52:19'),
(64, '2026-02-26', 700, 0, '2026-01-21 11:52:19'),
(65, '2026-02-27', 700, 0, '2026-01-21 11:52:19'),
(66, '2026-02-28', 700, 12, '2026-01-21 11:52:19'),
(67, '2026-03-01', 700, 3, '2026-01-21 11:52:19'),
(68, '2026-03-02', 700, 7, '2026-01-21 11:52:19'),
(69, '2026-03-03', 700, 4, '2026-01-21 11:52:19'),
(70, '2026-03-04', 700, 0, '2026-01-21 11:52:19'),
(71, '2026-03-05', 700, 0, '2026-01-21 11:52:19'),
(72, '2026-03-06', 700, 0, '2026-01-21 11:52:19'),
(73, '2026-03-07', 700, 0, '2026-01-21 11:52:19'),
(74, '2026-03-08', 700, 2, '2026-01-21 11:52:19'),
(75, '2026-03-09', 700, 1, '2026-01-21 11:52:19'),
(76, '2026-03-10', 700, 0, '2026-01-21 11:52:19'),
(77, '2026-03-11', 700, 0, '2026-01-21 11:52:19'),
(78, '2026-03-12', 700, 0, '2026-01-21 11:52:19'),
(79, '2026-03-13', 700, 2, '2026-01-21 11:52:19'),
(80, '2026-03-14', 700, 0, '2026-01-21 11:52:19'),
(81, '2026-03-15', 700, 8, '2026-01-21 11:52:19'),
(82, '2026-03-16', 700, 4, '2026-01-21 11:52:19'),
(83, '2026-03-17', 700, 3, '2026-01-21 11:52:19'),
-- --------------------------------------------------------

--
-- Table structure for table `global_settings`
--

CREATE TABLE `global_settings` (
  `id` int(11) NOT NULL,
  `event_name` varchar(255) NOT NULL DEFAULT 'Ramadan Iftar Buffet',
  `event_venue` varchar(255) NOT NULL DEFAULT 'Dewan Wisma Perwira',
  `event_year` int(11) NOT NULL DEFAULT 2026,
  `event_start_date` date NOT NULL DEFAULT '2026-02-01',
  `event_end_date` date NOT NULL DEFAULT '2026-03-31',
  `price_dewasa` decimal(10,2) NOT NULL DEFAULT 95.00,
  `price_kanak` decimal(10,2) NOT NULL DEFAULT 70.00,
  `price_warga` decimal(10,2) NOT NULL DEFAULT 85.00,
  `payment_method_name` varchar(255) NOT NULL DEFAULT 'DuitNow QR',
  `payment_bank_name` varchar(255) NOT NULL DEFAULT 'Maybank',
  `payment_account_holder` varchar(255) NOT NULL DEFAULT 'Hotel Buka Puasa',
  `payment_qr_path` varchar(255) DEFAULT NULL,
  `payment_instructions` text NOT NULL,
  `max_tickets_per_booking` int(11) NOT NULL DEFAULT 20,
  `booking_status` enum('OPEN','CLOSED') NOT NULL DEFAULT 'OPEN',
  `allow_same_day_booking` tinyint(1) NOT NULL DEFAULT 1,
  `checkin_start_time` time NOT NULL DEFAULT '17:00:00',
  `allow_ticket_reprint` tinyint(1) NOT NULL DEFAULT 1,
  `ticket_reference_prefix` varchar(32) NOT NULL DEFAULT 'BP2026',
  `admin_name` varchar(255) NOT NULL DEFAULT 'Admin',
  `admin_email` varchar(255) NOT NULL DEFAULT 'admin@hotel.com',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `global_settings`
--

INSERT INTO `global_settings` (`id`, `event_name`, `event_venue`, `event_year`, `event_start_date`, `event_end_date`, `price_dewasa`, `price_kanak`, `price_warga`, `payment_method_name`, `payment_bank_name`, `payment_account_holder`, `payment_qr_path`, `payment_instructions`, `max_tickets_per_booking`, `booking_status`, `allow_same_day_booking`, `checkin_start_time`, `allow_ticket_reprint`, `ticket_reference_prefix`, `admin_name`, `admin_email`, `updated_at`) VALUES
(1, 'Ramadan Buffet', 'Dewan Wisma Perwira', 2026, '2026-02-21', '2026-03-19', 98.00, 50.00, 85.00, 'DuitNow QR', 'Maybank', 'the blanket hotel', 'uploads/payment_qr/payment_qr_20260128_080054.jpg', 'Sila buat pembayaran menggunakan DuitNow QR.\r\n\r\n1) Scan QR\r\n2) Masukkan jumlah tepat\r\n3) Upload resit / bukti pembayaran\r\n\r\nTempahan akan disahkan selepas semakan oleh pihak admin.', 20, 'OPEN', 1, '17:00:00', 1, 'BP2026', 'Admin', 'admin@hotel.com', '2026-01-28 08:01:42');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_booking_reference` (`booking_reference`);

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_admin_username` (`username`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_booking_reference` (`booking_reference`),
  ADD KEY `idx_billcode` (`billcode`),
  ADD KEY `idx_slot_date` (`slot_date`);

--
-- Indexes for table `booking_slots`
--
ALTER TABLE `booking_slots`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_slot_date` (`slot_date`),
  ADD KEY `idx_slot_date` (`slot_date`);

--
-- Indexes for table `global_settings`
--
ALTER TABLE `global_settings`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=248;

--
-- AUTO_INCREMENT for table `booking_slots`
--
ALTER TABLE `booking_slots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT for table `global_settings`
--
ALTER TABLE `global_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
