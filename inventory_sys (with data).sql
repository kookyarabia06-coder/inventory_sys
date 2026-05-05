-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 22, 2026 at 04:59 AM
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
-- Database: `inventory_sys`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `date_created` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `user_id`, `action`, `item_id`, `details`, `date_created`) VALUES
(1, NULL, 'Failed Login', 0, 'Failed login attempt for username: superadmin', '2026-02-28 20:36:22'),
(2, 5, 'Login', 5, 'User logged in', '2026-02-28 20:36:49'),
(3, 5, 'Logout', 5, 'User logged out', '2026-02-28 20:36:59'),
(4, 5, 'Login', 5, 'User logged in', '2026-02-28 20:37:04'),
(5, 5, 'Login', 5, 'User logged in', '2026-03-01 11:51:49'),
(6, 5, 'Logout', 5, 'User logged out', '2026-03-01 12:11:00'),
(7, 6, 'Login', 6, 'User logged in', '2026-03-01 12:11:04'),
(8, 6, 'Login', 6, 'User logged in', '2026-03-01 13:58:39'),
(9, 6, 'Add Inventory', 1, 'Added item with barcode: INV2026030100586', '2026-03-01 14:00:46'),
(10, 6, 'Add Inventory', 3, 'Added item with barcode: INV2026030105314', '2026-03-01 14:03:48'),
(11, 6, 'Edit Inventory', 3, 'Edited item: testtest', '2026-03-01 14:04:21'),
(12, 6, 'Add Inventory', 5, 'Added item with barcode: INV2026030193912', '2026-03-01 14:34:49'),
(13, 6, 'Add Inventory', 7, 'Added item with barcode: INV2026030127779', '2026-03-01 14:34:51'),
(14, 6, 'Logout', 6, 'User logged out', '2026-03-01 14:36:55'),
(15, 8, 'Login', 8, 'User logged in', '2026-03-01 14:38:27'),
(16, 8, 'Logout', 8, 'User logged out', '2026-03-01 14:38:36'),
(17, 6, 'Login', 6, 'User logged in', '2026-03-01 14:38:44'),
(18, 6, 'Login', 6, 'User logged in', '2026-03-01 17:02:39'),
(19, 6, 'Login', 6, 'User logged in', '2026-03-01 17:09:03'),
(20, 6, 'Logout', 6, 'User logged out', '2026-03-01 17:11:34'),
(21, 6, 'Login', 6, 'User logged in', '2026-03-01 17:13:48'),
(22, 6, 'Login', 6, 'User logged in', '2026-03-02 14:15:53'),
(23, 6, 'Add Inventory', 9, 'Added new item(s): addnewexample with single barcode(s)', '2026-03-02 14:21:18'),
(24, 6, 'Add Inventory', 19, 'Added new item(s): mikamaalat with 10 items barcode(s)', '2026-03-02 14:37:49'),
(25, 6, 'Login', 6, 'User logged in', '2026-03-02 18:03:36'),
(26, 6, 'Add Inventory', 20, 'Added new item(s): finalbarcodesingle with 1 items barcode(s)', '2026-03-02 18:07:38'),
(27, 6, 'Login', 6, 'User logged in', '2026-03-02 18:12:55'),
(28, 6, 'Edit Inventory', 20, 'Edited item: finalbarcodesingle', '2026-03-02 18:39:47'),
(29, 6, 'Add Building', 0, 'Added building: building ko', '2026-03-02 18:45:30'),
(30, 6, 'Edit Inventory', 20, 'Edited item: edited', '2026-03-02 19:14:28'),
(31, 6, 'Login', 6, 'User logged in', '2026-03-04 17:42:20'),
(32, 6, 'Edit Inventory', 20, 'Edited item: edited', '2026-03-04 17:42:33'),
(33, 6, 'Logout', 6, 'User logged out', '2026-03-04 17:48:07'),
(34, 8, 'Login', 8, 'User logged in', '2026-03-04 17:48:10'),
(35, 8, 'Unauthorized Access', 8, 'Attempted to access page requiring roles: admin', '2026-03-04 17:48:14'),
(36, 8, 'Unauthorized Access', 8, 'Attempted to access page requiring roles: admin', '2026-03-04 17:48:15'),
(37, 8, 'Unauthorized Access', 8, 'Attempted to access page requiring roles: admin', '2026-03-04 17:48:33'),
(38, 8, 'Unauthorized Access', 8, 'Attempted to access page requiring roles: admin', '2026-03-04 17:48:34'),
(39, 8, 'Unauthorized Access', 8, 'Attempted to access page requiring roles: admin', '2026-03-04 17:48:34'),
(40, 8, 'Unauthorized Access', 8, 'Attempted to access page requiring roles: admin', '2026-03-04 17:48:35'),
(41, 8, 'Unauthorized Access', 8, 'Attempted to access page requiring roles: admin', '2026-03-04 17:48:35'),
(42, 8, 'Unauthorized Access', 8, 'Attempted to access page requiring roles: admin', '2026-03-04 17:48:35'),
(43, 8, 'Unauthorized Access', 8, 'Attempted to access page requiring roles: admin', '2026-03-04 17:48:35'),
(44, 8, 'Unauthorized Access', 8, 'Attempted to access page requiring roles: admin', '2026-03-04 17:48:35'),
(45, 8, 'Unauthorized Access', 8, 'Attempted to access page requiring roles: admin', '2026-03-04 17:48:35'),
(46, 8, 'Unauthorized Access', 8, 'Attempted to access page requiring roles: admin', '2026-03-04 17:48:39'),
(47, 8, 'Logout', 8, 'User logged out', '2026-03-04 17:49:20'),
(48, 6, 'Login', 6, 'User logged in', '2026-03-04 17:49:22'),
(49, 6, 'Login', 6, 'User logged in', '2026-03-05 08:42:34'),
(50, 6, 'Login', 6, 'User logged in', '2026-03-05 11:17:28'),
(51, 6, 'Login', 6, 'User logged in', '2026-03-05 12:59:15'),
(52, 6, 'Login', 6, 'User logged in', '2026-03-06 08:48:04'),
(53, 6, 'Login', 6, 'User logged in', '2026-03-06 09:41:03'),
(54, 6, 'Add Employee', 1, 'Added employee: adsadada, dasdasdasd', '2026-03-06 09:41:16'),
(55, 6, 'Login', 6, 'User logged in', '2026-03-06 13:19:19'),
(56, 5, 'Login', 5, 'User logged in', '2026-03-06 15:50:51'),
(57, 6, 'Login', 6, 'User logged in', '2026-03-06 15:56:11'),
(58, 6, 'Logout', 6, 'User logged out', '2026-03-06 15:57:00'),
(59, 6, 'Login', 6, 'User logged in', '2026-03-06 15:58:24'),
(60, 6, 'Logout', 6, 'User logged out', '2026-03-06 16:11:53'),
(61, 5, 'Login', 5, 'User logged in', '2026-03-06 16:11:56'),
(62, 5, 'Logout', 5, 'User logged out', '2026-03-06 16:12:07'),
(63, 6, 'Login', 6, 'User logged in', '2026-03-06 16:12:10'),
(64, 6, 'Logout', 6, 'User logged out', '2026-03-06 16:12:18'),
(65, 6, 'Login', 6, 'User logged in', '2026-03-06 16:12:30'),
(66, 6, 'Logout', 6, 'User logged out', '2026-03-06 16:14:54'),
(67, 6, 'Login', 6, 'User logged in', '2026-03-06 16:28:35'),
(68, 6, 'Login', 6, 'User logged in', '2026-03-10 07:33:53'),
(69, 6, 'Logout', 6, 'User logged out', '2026-03-10 08:14:23'),
(70, 6, 'Login', 6, 'User logged in', '2026-03-10 08:17:04'),
(71, 6, 'Add Semi-Expendable', 21, 'Added new semi-expendable item: dsadasdasd', '2026-03-10 10:15:05'),
(72, 6, 'Add Semi-Expendable', 22, 'Added new semi-expendable item: exampleSemi', '2026-03-10 10:16:55'),
(73, 6, 'Add Semi-Expendable', 23, 'Added new semi-expendable item: exampletwo', '2026-03-10 10:18:01'),
(74, 6, 'Add Semi-Expendable', 24, 'Added new semi-expendable item: exampleeenotatlo', '2026-03-10 10:32:19'),
(75, 6, 'Add Semi-Expendable', 25, 'Added new semi-expendable item: exampleidk', '2026-03-10 10:51:01'),
(76, 6, 'Add PPE', 26, 'Added new PPE item: mikaarticlenotwotwosixsix', '2026-03-10 11:14:39'),
(77, 6, 'Login', 6, 'User logged in', '2026-03-10 11:59:33'),
(78, 6, 'Login', 6, 'User logged in', '2026-03-10 13:13:20'),
(79, 6, 'Edit Semi-Expendable', 25, 'Edited semi-expendable item: exampleidk', '2026-03-10 13:22:01'),
(80, 6, 'Add PPE', 27, 'Added new PPE item: multitest', '2026-03-10 14:05:07'),
(81, 6, 'Login', 6, 'User logged in', '2026-03-10 15:29:53'),
(82, 6, 'Add PPE', 28, 'Added new PPE item: examplemultiple', '2026-03-10 15:30:38'),
(83, 6, 'Add PPE', 29, 'Added new PPE item: multianak', '2026-03-10 15:44:03'),
(84, 6, 'Add PPE', 30, 'Added new PPE item: singleexample', '2026-03-10 15:46:02'),
(85, 6, 'Add PPE', 31, 'Added new PPE item: asdasdasdasdasdasdasd', '2026-03-10 15:59:30'),
(86, 6, 'Login', 6, 'User logged in', '2026-03-11 08:47:16'),
(87, 6, 'Add PPE Multiple', 32, 'Added PPE item 1/12: marchofyou', '2026-03-11 08:51:49'),
(88, 6, 'Add PPE Multiple', 33, 'Added PPE item 2/12: marchofyou', '2026-03-11 08:51:49'),
(89, 6, 'Add PPE Multiple', 34, 'Added PPE item 3/12: marchofyou', '2026-03-11 08:51:49'),
(90, 6, 'Add PPE Multiple', 35, 'Added PPE item 4/12: marchofyou', '2026-03-11 08:51:49'),
(91, 6, 'Add PPE Multiple', 36, 'Added PPE item 5/12: marchofyou', '2026-03-11 08:51:49'),
(92, 6, 'Add PPE Multiple', 37, 'Added PPE item 6/12: marchofyou', '2026-03-11 08:51:49'),
(93, 6, 'Add PPE Multiple', 38, 'Added PPE item 7/12: marchofyou', '2026-03-11 08:51:49'),
(94, 6, 'Add PPE Multiple', 39, 'Added PPE item 8/12: marchofyou', '2026-03-11 08:51:49'),
(95, 6, 'Add PPE Multiple', 40, 'Added PPE item 9/12: marchofyou', '2026-03-11 08:51:49'),
(96, 6, 'Add PPE Multiple', 41, 'Added PPE item 10/12: marchofyou', '2026-03-11 08:51:49'),
(97, 6, 'Add PPE Multiple', 42, 'Added PPE item 11/12: marchofyou', '2026-03-11 08:51:49'),
(98, 6, 'Add PPE Multiple', 43, 'Added PPE item 12/12: marchofyou', '2026-03-11 08:51:49'),
(99, 6, 'Add Semi-Expendable', 44, 'Added new semi-expendable item: marchsemi', '2026-03-11 08:57:36'),
(100, 6, 'Edit Semi-Expendable', 44, 'Edited semi-expendable item: marchsemi', '2026-03-11 08:58:20'),
(101, 6, 'Login', 6, 'User logged in', '2026-03-11 09:18:18'),
(102, 6, 'Add Semi-Expendable', 45, 'Added new semi-expendable item: test', '2026-03-11 09:21:44'),
(103, 6, 'Login', 6, 'User logged in', '2026-03-11 10:05:43'),
(104, 6, 'Add Semi-Expendable Multiple', 46, 'Added semi-expendable item 1/12: ssssssssssssssssssssssss', '2026-03-11 10:46:32'),
(105, 6, 'Add Semi-Expendable Multiple', 47, 'Added semi-expendable item 2/12: ssssssssssssssssssssssss', '2026-03-11 10:46:32'),
(106, 6, 'Add Semi-Expendable Multiple', 48, 'Added semi-expendable item 3/12: ssssssssssssssssssssssss', '2026-03-11 10:46:32'),
(107, 6, 'Add Semi-Expendable Multiple', 49, 'Added semi-expendable item 4/12: ssssssssssssssssssssssss', '2026-03-11 10:46:32'),
(108, 6, 'Add Semi-Expendable Multiple', 50, 'Added semi-expendable item 5/12: ssssssssssssssssssssssss', '2026-03-11 10:46:32'),
(109, 6, 'Add Semi-Expendable Multiple', 51, 'Added semi-expendable item 6/12: ssssssssssssssssssssssss', '2026-03-11 10:46:32'),
(110, 6, 'Add Semi-Expendable Multiple', 52, 'Added semi-expendable item 7/12: ssssssssssssssssssssssss', '2026-03-11 10:46:32'),
(111, 6, 'Add Semi-Expendable Multiple', 53, 'Added semi-expendable item 8/12: ssssssssssssssssssssssss', '2026-03-11 10:46:32'),
(112, 6, 'Add Semi-Expendable Multiple', 54, 'Added semi-expendable item 9/12: ssssssssssssssssssssssss', '2026-03-11 10:46:32'),
(113, 6, 'Add Semi-Expendable Multiple', 55, 'Added semi-expendable item 10/12: ssssssssssssssssssssssss', '2026-03-11 10:46:32'),
(114, 6, 'Add Semi-Expendable Multiple', 56, 'Added semi-expendable item 11/12: ssssssssssssssssssssssss', '2026-03-11 10:46:32'),
(115, 6, 'Add Semi-Expendable Multiple', 57, 'Added semi-expendable item 12/12: ssssssssssssssssssssssss', '2026-03-11 10:46:32'),
(116, 6, 'Add Semi-Expendable Multiple', 58, 'Added semi-expendable item 1/2: ,mmmmmmmmmmmmmmmmmmm', '2026-03-11 10:48:51'),
(117, 6, 'Add Semi-Expendable Multiple', 59, 'Added semi-expendable item 2/2: ,mmmmmmmmmmmmmmmmmmm', '2026-03-11 10:48:51'),
(118, 6, 'Add Semi-Expendable', 60, 'Added new semi-expendable item: siinglesinglesiinglesingle', '2026-03-11 10:55:05'),
(119, 6, 'Add Semi-Expendable', 61, 'Added new semi-expendable item: awitnez', '2026-03-11 10:57:27'),
(120, 6, 'Delete Semi-Expendable', 61, 'Deleted semi-expendable item ID: 61', '2026-03-11 11:11:46'),
(121, 6, 'Delete Semi-Expendable', 60, 'Deleted semi-expendable item ID: 60', '2026-03-11 11:11:49'),
(122, 6, 'Delete Semi-Expendable', 58, 'Deleted semi-expendable item ID: 58', '2026-03-11 11:11:52'),
(123, 6, 'Delete Semi-Expendable', 59, 'Deleted semi-expendable item ID: 59', '2026-03-11 11:11:54'),
(124, 6, 'Delete Semi-Expendable', 46, 'Deleted semi-expendable item ID: 46', '2026-03-11 11:11:56'),
(125, 6, 'Add Semi-Expendable', 62, 'Added new semi-expendable item: oneitemto', '2026-03-11 11:12:22'),
(126, 6, 'Add Semi-Expendable Multiple', 63, 'Added semi-expendable item 1/7: sevenitemto', '2026-03-11 11:13:04'),
(127, 6, 'Add Semi-Expendable Multiple', 64, 'Added semi-expendable item 2/7: sevenitemto', '2026-03-11 11:13:04'),
(128, 6, 'Add Semi-Expendable Multiple', 65, 'Added semi-expendable item 3/7: sevenitemto', '2026-03-11 11:13:04'),
(129, 6, 'Add Semi-Expendable Multiple', 66, 'Added semi-expendable item 4/7: sevenitemto', '2026-03-11 11:13:04'),
(130, 6, 'Add Semi-Expendable Multiple', 67, 'Added semi-expendable item 5/7: sevenitemto', '2026-03-11 11:13:04'),
(131, 6, 'Add Semi-Expendable Multiple', 68, 'Added semi-expendable item 6/7: sevenitemto', '2026-03-11 11:13:04'),
(132, 6, 'Add Semi-Expendable Multiple', 69, 'Added semi-expendable item 7/7: sevenitemto', '2026-03-11 11:13:04'),
(133, 6, 'Add PPE', 70, 'Added new PPE item: isaPPE', '2026-03-11 11:19:56'),
(134, 6, 'Add PPE Multiple', 71, 'Added PPE item 1/7: sevenaPPE', '2026-03-11 11:20:36'),
(135, 6, 'Add PPE Multiple', 72, 'Added PPE item 2/7: sevenaPPE', '2026-03-11 11:20:36'),
(136, 6, 'Add PPE Multiple', 73, 'Added PPE item 3/7: sevenaPPE', '2026-03-11 11:20:36'),
(137, 6, 'Add PPE Multiple', 74, 'Added PPE item 4/7: sevenaPPE', '2026-03-11 11:20:36'),
(138, 6, 'Add PPE Multiple', 75, 'Added PPE item 5/7: sevenaPPE', '2026-03-11 11:20:36'),
(139, 6, 'Add PPE Multiple', 76, 'Added PPE item 6/7: sevenaPPE', '2026-03-11 11:20:36'),
(140, 6, 'Add PPE Multiple', 77, 'Added PPE item 7/7: sevenaPPE', '2026-03-11 11:20:36'),
(141, 6, 'Delete PPE', 32, 'Deleted PPE item ID: 32', '2026-03-11 11:34:04'),
(142, 6, 'Add PPE Multiple', 78, 'Added PPE item 1/3: tatlo', '2026-03-11 11:34:57'),
(143, 6, 'Add PPE Multiple', 79, 'Added PPE item 2/3: tatlo', '2026-03-11 11:34:57'),
(144, 6, 'Add PPE Multiple', 80, 'Added PPE item 3/3: tatlo', '2026-03-11 11:34:57'),
(145, 6, 'Add Semi-Expendable Multiple', 81, 'Added semi-expendable item 1/9: nineitemto', '2026-03-11 11:39:17'),
(146, 6, 'Add Semi-Expendable Multiple', 82, 'Added semi-expendable item 2/9: nineitemto', '2026-03-11 11:39:17'),
(147, 6, 'Add Semi-Expendable Multiple', 83, 'Added semi-expendable item 3/9: nineitemto', '2026-03-11 11:39:17'),
(148, 6, 'Add Semi-Expendable Multiple', 84, 'Added semi-expendable item 4/9: nineitemto', '2026-03-11 11:39:17'),
(149, 6, 'Add Semi-Expendable Multiple', 85, 'Added semi-expendable item 5/9: nineitemto', '2026-03-11 11:39:17'),
(150, 6, 'Add Semi-Expendable Multiple', 86, 'Added semi-expendable item 6/9: nineitemto', '2026-03-11 11:39:17'),
(151, 6, 'Add Semi-Expendable Multiple', 87, 'Added semi-expendable item 7/9: nineitemto', '2026-03-11 11:39:17'),
(152, 6, 'Add Semi-Expendable Multiple', 88, 'Added semi-expendable item 8/9: nineitemto', '2026-03-11 11:39:17'),
(153, 6, 'Add Semi-Expendable Multiple', 89, 'Added semi-expendable item 9/9: nineitemto', '2026-03-11 11:39:17'),
(154, 6, 'Add Semi-Expendable', 90, 'Added new semi-expendable item: oneone', '2026-03-11 11:39:58'),
(155, 6, 'Add PPE', 91, 'Added new PPE item: dsadsadsadsadsadsadsadassad', '2026-03-11 11:41:58'),
(156, 6, 'Login', 6, 'User logged in', '2026-03-11 12:53:04'),
(157, 6, 'Add Inventory', 92, 'Added new item(s): twenlve na barcode with 1 items barcode(s)', '2026-03-11 12:58:58'),
(158, 6, 'Add Inventory', 93, 'Added new item(s): onehundertre with 1 items barcode(s)', '2026-03-11 13:08:08'),
(159, 6, 'Add Inventory', 105, 'Added new item(s): sanagumanakanaboss with 12 items created.', '2026-03-11 13:10:44'),
(160, 6, 'Add Inventory', 106, 'Added new item: isalangtoboss', '2026-03-11 13:19:56'),
(161, 6, 'Login', 6, 'User logged in', '2026-03-11 13:40:11'),
(162, 6, 'Logout', 6, 'User logged out', '2026-03-11 14:41:58'),
(163, 6, 'Login', 6, 'User logged in', '2026-03-11 14:42:24'),
(164, 6, 'Logout', 6, 'User logged out', '2026-03-11 15:07:29'),
(165, 5, 'Login', 5, 'User logged in', '2026-03-11 15:07:31'),
(166, 5, 'Unauthorized Access', 5, 'Attempted to access page requiring roles: admin', '2026-03-11 15:07:59'),
(167, 5, 'Unauthorized Access', 5, 'Attempted to access page requiring roles: admin', '2026-03-11 15:08:01'),
(168, 5, 'Unauthorized Access', 5, 'Attempted to access page requiring roles: admin', '2026-03-11 15:08:02'),
(169, 5, 'Unauthorized Access', 5, 'Attempted to access page requiring roles: admin', '2026-03-11 15:08:03'),
(170, 5, 'Unauthorized Access', 5, 'Attempted to access page requiring roles: admin', '2026-03-11 15:08:03'),
(171, 5, 'Unauthorized Access', 5, 'Attempted to access page requiring roles: admin', '2026-03-11 15:08:03'),
(172, 5, 'Unauthorized Access', 5, 'Attempted to access page requiring roles: admin', '2026-03-11 15:09:59'),
(173, 5, 'Logout', 5, 'User logged out', '2026-03-11 15:13:02'),
(174, 8, 'Login', 8, 'User logged in', '2026-03-11 15:13:06'),
(175, 8, 'Unauthorized Access', 8, 'Attempted to access page requiring roles: admin', '2026-03-11 15:13:53'),
(176, 8, 'Unauthorized Access', 8, 'Attempted to access page requiring roles: admin', '2026-03-11 15:13:54'),
(177, 8, 'Logout', 8, 'User logged out', '2026-03-11 15:14:20'),
(178, 6, 'Login', 6, 'User logged in', '2026-03-11 15:14:23'),
(179, 6, 'Login', 6, 'User logged in', '2026-03-11 15:18:34'),
(180, 6, 'Logout', 6, 'User logged out', '2026-03-11 15:19:01'),
(181, 6, 'Login', 6, 'User logged in', '2026-03-11 15:19:18'),
(182, 6, 'Add Semi-Expendable Multiple', 107, 'Added semi-expendable item 1/12: test', '2026-03-11 15:27:33'),
(183, 6, 'Add Semi-Expendable Multiple', 108, 'Added semi-expendable item 2/12: test', '2026-03-11 15:27:33'),
(184, 6, 'Add Semi-Expendable Multiple', 109, 'Added semi-expendable item 3/12: test', '2026-03-11 15:27:33'),
(185, 6, 'Add Semi-Expendable Multiple', 110, 'Added semi-expendable item 4/12: test', '2026-03-11 15:27:33'),
(186, 6, 'Add Semi-Expendable Multiple', 111, 'Added semi-expendable item 5/12: test', '2026-03-11 15:27:33'),
(187, 6, 'Add Semi-Expendable Multiple', 112, 'Added semi-expendable item 6/12: test', '2026-03-11 15:27:33'),
(188, 6, 'Add Semi-Expendable Multiple', 113, 'Added semi-expendable item 7/12: test', '2026-03-11 15:27:33'),
(189, 6, 'Add Semi-Expendable Multiple', 114, 'Added semi-expendable item 8/12: test', '2026-03-11 15:27:33'),
(190, 6, 'Add Semi-Expendable Multiple', 115, 'Added semi-expendable item 9/12: test', '2026-03-11 15:27:33'),
(191, 6, 'Add Semi-Expendable Multiple', 116, 'Added semi-expendable item 10/12: test', '2026-03-11 15:27:33'),
(192, 6, 'Add Semi-Expendable Multiple', 117, 'Added semi-expendable item 11/12: test', '2026-03-11 15:27:33'),
(193, 6, 'Add Semi-Expendable Multiple', 118, 'Added semi-expendable item 12/12: test', '2026-03-11 15:27:33'),
(194, 6, 'Add Semi-Expendable Multiple', 119, 'Added semi-expendable item 1/12: examplequt', '2026-03-11 16:02:32'),
(195, 6, 'Add Semi-Expendable Multiple', 120, 'Added semi-expendable item 2/12: examplequt', '2026-03-11 16:02:32'),
(196, 6, 'Add Semi-Expendable Multiple', 121, 'Added semi-expendable item 3/12: examplequt', '2026-03-11 16:02:32'),
(197, 6, 'Add Semi-Expendable Multiple', 122, 'Added semi-expendable item 4/12: examplequt', '2026-03-11 16:02:32'),
(198, 6, 'Add Semi-Expendable Multiple', 123, 'Added semi-expendable item 5/12: examplequt', '2026-03-11 16:02:32'),
(199, 6, 'Add Semi-Expendable Multiple', 124, 'Added semi-expendable item 6/12: examplequt', '2026-03-11 16:02:32'),
(200, 6, 'Add Semi-Expendable Multiple', 125, 'Added semi-expendable item 7/12: examplequt', '2026-03-11 16:02:32'),
(201, 6, 'Add Semi-Expendable Multiple', 126, 'Added semi-expendable item 8/12: examplequt', '2026-03-11 16:02:32'),
(202, 6, 'Add Semi-Expendable Multiple', 127, 'Added semi-expendable item 9/12: examplequt', '2026-03-11 16:02:32'),
(203, 6, 'Add Semi-Expendable Multiple', 128, 'Added semi-expendable item 10/12: examplequt', '2026-03-11 16:02:32'),
(204, 6, 'Add Semi-Expendable Multiple', 129, 'Added semi-expendable item 11/12: examplequt', '2026-03-11 16:02:32'),
(205, 6, 'Add Semi-Expendable Multiple', 130, 'Added semi-expendable item 12/12: examplequt', '2026-03-11 16:02:32'),
(206, 6, 'Login', 6, 'User logged in', '2026-03-11 16:11:32'),
(207, 6, 'Add PPE Multiple', 131, 'Added PPE item 1/123: ppetests', '2026-03-11 16:13:31'),
(208, 6, 'Add PPE Multiple', 132, 'Added PPE item 2/123: ppetests', '2026-03-11 16:13:31'),
(209, 6, 'Add PPE Multiple', 133, 'Added PPE item 3/123: ppetests', '2026-03-11 16:13:31'),
(210, 6, 'Add PPE Multiple', 134, 'Added PPE item 4/123: ppetests', '2026-03-11 16:13:31'),
(211, 6, 'Add PPE Multiple', 135, 'Added PPE item 5/123: ppetests', '2026-03-11 16:13:31'),
(212, 6, 'Add PPE Multiple', 136, 'Added PPE item 6/123: ppetests', '2026-03-11 16:13:31'),
(213, 6, 'Add PPE Multiple', 137, 'Added PPE item 7/123: ppetests', '2026-03-11 16:13:31'),
(214, 6, 'Add PPE Multiple', 138, 'Added PPE item 8/123: ppetests', '2026-03-11 16:13:31'),
(215, 6, 'Add PPE Multiple', 139, 'Added PPE item 9/123: ppetests', '2026-03-11 16:13:31'),
(216, 6, 'Add PPE Multiple', 140, 'Added PPE item 10/123: ppetests', '2026-03-11 16:13:31'),
(217, 6, 'Add PPE Multiple', 141, 'Added PPE item 11/123: ppetests', '2026-03-11 16:13:31'),
(218, 6, 'Add PPE Multiple', 142, 'Added PPE item 12/123: ppetests', '2026-03-11 16:13:31'),
(219, 6, 'Add PPE Multiple', 143, 'Added PPE item 13/123: ppetests', '2026-03-11 16:13:31'),
(220, 6, 'Add PPE Multiple', 144, 'Added PPE item 14/123: ppetests', '2026-03-11 16:13:31'),
(221, 6, 'Add PPE Multiple', 145, 'Added PPE item 15/123: ppetests', '2026-03-11 16:13:31'),
(222, 6, 'Add PPE Multiple', 146, 'Added PPE item 16/123: ppetests', '2026-03-11 16:13:31'),
(223, 6, 'Add PPE Multiple', 147, 'Added PPE item 17/123: ppetests', '2026-03-11 16:13:31'),
(224, 6, 'Add PPE Multiple', 148, 'Added PPE item 18/123: ppetests', '2026-03-11 16:13:31'),
(225, 6, 'Add PPE Multiple', 149, 'Added PPE item 19/123: ppetests', '2026-03-11 16:13:31'),
(226, 6, 'Add PPE Multiple', 150, 'Added PPE item 20/123: ppetests', '2026-03-11 16:13:31'),
(227, 6, 'Add PPE Multiple', 151, 'Added PPE item 21/123: ppetests', '2026-03-11 16:13:31'),
(228, 6, 'Add PPE Multiple', 152, 'Added PPE item 22/123: ppetests', '2026-03-11 16:13:31'),
(229, 6, 'Add PPE Multiple', 153, 'Added PPE item 23/123: ppetests', '2026-03-11 16:13:31'),
(230, 6, 'Add PPE Multiple', 154, 'Added PPE item 24/123: ppetests', '2026-03-11 16:13:31'),
(231, 6, 'Add PPE Multiple', 155, 'Added PPE item 25/123: ppetests', '2026-03-11 16:13:31'),
(232, 6, 'Add PPE Multiple', 156, 'Added PPE item 26/123: ppetests', '2026-03-11 16:13:31'),
(233, 6, 'Add PPE Multiple', 157, 'Added PPE item 27/123: ppetests', '2026-03-11 16:13:31'),
(234, 6, 'Add PPE Multiple', 158, 'Added PPE item 28/123: ppetests', '2026-03-11 16:13:31'),
(235, 6, 'Add PPE Multiple', 159, 'Added PPE item 29/123: ppetests', '2026-03-11 16:13:31'),
(236, 6, 'Add PPE Multiple', 160, 'Added PPE item 30/123: ppetests', '2026-03-11 16:13:31'),
(237, 6, 'Add PPE Multiple', 161, 'Added PPE item 31/123: ppetests', '2026-03-11 16:13:31'),
(238, 6, 'Add PPE Multiple', 162, 'Added PPE item 32/123: ppetests', '2026-03-11 16:13:31'),
(239, 6, 'Add PPE Multiple', 163, 'Added PPE item 33/123: ppetests', '2026-03-11 16:13:31'),
(240, 6, 'Add PPE Multiple', 164, 'Added PPE item 34/123: ppetests', '2026-03-11 16:13:31'),
(241, 6, 'Add PPE Multiple', 165, 'Added PPE item 35/123: ppetests', '2026-03-11 16:13:31'),
(242, 6, 'Add PPE Multiple', 166, 'Added PPE item 36/123: ppetests', '2026-03-11 16:13:31'),
(243, 6, 'Add PPE Multiple', 167, 'Added PPE item 37/123: ppetests', '2026-03-11 16:13:31'),
(244, 6, 'Add PPE Multiple', 168, 'Added PPE item 38/123: ppetests', '2026-03-11 16:13:31'),
(245, 6, 'Add PPE Multiple', 169, 'Added PPE item 39/123: ppetests', '2026-03-11 16:13:31'),
(246, 6, 'Add PPE Multiple', 170, 'Added PPE item 40/123: ppetests', '2026-03-11 16:13:31'),
(247, 6, 'Add PPE Multiple', 171, 'Added PPE item 41/123: ppetests', '2026-03-11 16:13:31'),
(248, 6, 'Add PPE Multiple', 172, 'Added PPE item 42/123: ppetests', '2026-03-11 16:13:31'),
(249, 6, 'Add PPE Multiple', 173, 'Added PPE item 43/123: ppetests', '2026-03-11 16:13:31'),
(250, 6, 'Add PPE Multiple', 174, 'Added PPE item 44/123: ppetests', '2026-03-11 16:13:31'),
(251, 6, 'Add PPE Multiple', 175, 'Added PPE item 45/123: ppetests', '2026-03-11 16:13:31'),
(252, 6, 'Add PPE Multiple', 176, 'Added PPE item 46/123: ppetests', '2026-03-11 16:13:31'),
(253, 6, 'Add PPE Multiple', 177, 'Added PPE item 47/123: ppetests', '2026-03-11 16:13:31'),
(254, 6, 'Add PPE Multiple', 178, 'Added PPE item 48/123: ppetests', '2026-03-11 16:13:31'),
(255, 6, 'Add PPE Multiple', 179, 'Added PPE item 49/123: ppetests', '2026-03-11 16:13:31'),
(256, 6, 'Add PPE Multiple', 180, 'Added PPE item 50/123: ppetests', '2026-03-11 16:13:31'),
(257, 6, 'Add PPE Multiple', 181, 'Added PPE item 51/123: ppetests', '2026-03-11 16:13:31'),
(258, 6, 'Add PPE Multiple', 182, 'Added PPE item 52/123: ppetests', '2026-03-11 16:13:31'),
(259, 6, 'Add PPE Multiple', 183, 'Added PPE item 53/123: ppetests', '2026-03-11 16:13:31'),
(260, 6, 'Add PPE Multiple', 184, 'Added PPE item 54/123: ppetests', '2026-03-11 16:13:31'),
(261, 6, 'Add PPE Multiple', 185, 'Added PPE item 55/123: ppetests', '2026-03-11 16:13:31'),
(262, 6, 'Add PPE Multiple', 186, 'Added PPE item 56/123: ppetests', '2026-03-11 16:13:31'),
(263, 6, 'Add PPE Multiple', 187, 'Added PPE item 57/123: ppetests', '2026-03-11 16:13:31'),
(264, 6, 'Add PPE Multiple', 188, 'Added PPE item 58/123: ppetests', '2026-03-11 16:13:31'),
(265, 6, 'Add PPE Multiple', 189, 'Added PPE item 59/123: ppetests', '2026-03-11 16:13:31'),
(266, 6, 'Add PPE Multiple', 190, 'Added PPE item 60/123: ppetests', '2026-03-11 16:13:31'),
(267, 6, 'Add PPE Multiple', 191, 'Added PPE item 61/123: ppetests', '2026-03-11 16:13:31'),
(268, 6, 'Add PPE Multiple', 192, 'Added PPE item 62/123: ppetests', '2026-03-11 16:13:31'),
(269, 6, 'Add PPE Multiple', 193, 'Added PPE item 63/123: ppetests', '2026-03-11 16:13:31'),
(270, 6, 'Add PPE Multiple', 194, 'Added PPE item 64/123: ppetests', '2026-03-11 16:13:31'),
(271, 6, 'Add PPE Multiple', 195, 'Added PPE item 65/123: ppetests', '2026-03-11 16:13:31'),
(272, 6, 'Add PPE Multiple', 196, 'Added PPE item 66/123: ppetests', '2026-03-11 16:13:31'),
(273, 6, 'Add PPE Multiple', 197, 'Added PPE item 67/123: ppetests', '2026-03-11 16:13:31'),
(274, 6, 'Add PPE Multiple', 198, 'Added PPE item 68/123: ppetests', '2026-03-11 16:13:31'),
(275, 6, 'Add PPE Multiple', 199, 'Added PPE item 69/123: ppetests', '2026-03-11 16:13:31'),
(276, 6, 'Add PPE Multiple', 200, 'Added PPE item 70/123: ppetests', '2026-03-11 16:13:31'),
(277, 6, 'Add PPE Multiple', 201, 'Added PPE item 71/123: ppetests', '2026-03-11 16:13:31'),
(278, 6, 'Add PPE Multiple', 202, 'Added PPE item 72/123: ppetests', '2026-03-11 16:13:31'),
(279, 6, 'Add PPE Multiple', 203, 'Added PPE item 73/123: ppetests', '2026-03-11 16:13:31'),
(280, 6, 'Add PPE Multiple', 204, 'Added PPE item 74/123: ppetests', '2026-03-11 16:13:31'),
(281, 6, 'Add PPE Multiple', 205, 'Added PPE item 75/123: ppetests', '2026-03-11 16:13:31'),
(282, 6, 'Add PPE Multiple', 206, 'Added PPE item 76/123: ppetests', '2026-03-11 16:13:31'),
(283, 6, 'Add PPE Multiple', 207, 'Added PPE item 77/123: ppetests', '2026-03-11 16:13:31'),
(284, 6, 'Add PPE Multiple', 208, 'Added PPE item 78/123: ppetests', '2026-03-11 16:13:31'),
(285, 6, 'Add PPE Multiple', 209, 'Added PPE item 79/123: ppetests', '2026-03-11 16:13:31'),
(286, 6, 'Add PPE Multiple', 210, 'Added PPE item 80/123: ppetests', '2026-03-11 16:13:31'),
(287, 6, 'Add PPE Multiple', 211, 'Added PPE item 81/123: ppetests', '2026-03-11 16:13:31'),
(288, 6, 'Add PPE Multiple', 212, 'Added PPE item 82/123: ppetests', '2026-03-11 16:13:31'),
(289, 6, 'Add PPE Multiple', 213, 'Added PPE item 83/123: ppetests', '2026-03-11 16:13:31'),
(290, 6, 'Add PPE Multiple', 214, 'Added PPE item 84/123: ppetests', '2026-03-11 16:13:31'),
(291, 6, 'Add PPE Multiple', 215, 'Added PPE item 85/123: ppetests', '2026-03-11 16:13:31'),
(292, 6, 'Add PPE Multiple', 216, 'Added PPE item 86/123: ppetests', '2026-03-11 16:13:31'),
(293, 6, 'Add PPE Multiple', 217, 'Added PPE item 87/123: ppetests', '2026-03-11 16:13:31'),
(294, 6, 'Add PPE Multiple', 218, 'Added PPE item 88/123: ppetests', '2026-03-11 16:13:31'),
(295, 6, 'Add PPE Multiple', 219, 'Added PPE item 89/123: ppetests', '2026-03-11 16:13:31'),
(296, 6, 'Add PPE Multiple', 220, 'Added PPE item 90/123: ppetests', '2026-03-11 16:13:31'),
(297, 6, 'Add PPE Multiple', 221, 'Added PPE item 91/123: ppetests', '2026-03-11 16:13:31'),
(298, 6, 'Add PPE Multiple', 222, 'Added PPE item 92/123: ppetests', '2026-03-11 16:13:31'),
(299, 6, 'Add PPE Multiple', 223, 'Added PPE item 93/123: ppetests', '2026-03-11 16:13:31'),
(300, 6, 'Add PPE Multiple', 224, 'Added PPE item 94/123: ppetests', '2026-03-11 16:13:31'),
(301, 6, 'Add PPE Multiple', 225, 'Added PPE item 95/123: ppetests', '2026-03-11 16:13:31'),
(302, 6, 'Add PPE Multiple', 226, 'Added PPE item 96/123: ppetests', '2026-03-11 16:13:31'),
(303, 6, 'Add PPE Multiple', 227, 'Added PPE item 97/123: ppetests', '2026-03-11 16:13:31'),
(304, 6, 'Add PPE Multiple', 228, 'Added PPE item 98/123: ppetests', '2026-03-11 16:13:31'),
(305, 6, 'Add PPE Multiple', 229, 'Added PPE item 99/123: ppetests', '2026-03-11 16:13:31'),
(306, 6, 'Add PPE Multiple', 230, 'Added PPE item 100/123: ppetests', '2026-03-11 16:13:31'),
(307, 6, 'Add PPE Multiple', 231, 'Added PPE item 101/123: ppetests', '2026-03-11 16:13:31'),
(308, 6, 'Add PPE Multiple', 232, 'Added PPE item 102/123: ppetests', '2026-03-11 16:13:31'),
(309, 6, 'Add PPE Multiple', 233, 'Added PPE item 103/123: ppetests', '2026-03-11 16:13:31'),
(310, 6, 'Add PPE Multiple', 234, 'Added PPE item 104/123: ppetests', '2026-03-11 16:13:31'),
(311, 6, 'Add PPE Multiple', 235, 'Added PPE item 105/123: ppetests', '2026-03-11 16:13:31'),
(312, 6, 'Add PPE Multiple', 236, 'Added PPE item 106/123: ppetests', '2026-03-11 16:13:31'),
(313, 6, 'Add PPE Multiple', 237, 'Added PPE item 107/123: ppetests', '2026-03-11 16:13:31'),
(314, 6, 'Add PPE Multiple', 238, 'Added PPE item 108/123: ppetests', '2026-03-11 16:13:31'),
(315, 6, 'Add PPE Multiple', 239, 'Added PPE item 109/123: ppetests', '2026-03-11 16:13:31'),
(316, 6, 'Add PPE Multiple', 240, 'Added PPE item 110/123: ppetests', '2026-03-11 16:13:31'),
(317, 6, 'Add PPE Multiple', 241, 'Added PPE item 111/123: ppetests', '2026-03-11 16:13:31'),
(318, 6, 'Add PPE Multiple', 242, 'Added PPE item 112/123: ppetests', '2026-03-11 16:13:31'),
(319, 6, 'Add PPE Multiple', 243, 'Added PPE item 113/123: ppetests', '2026-03-11 16:13:31'),
(320, 6, 'Add PPE Multiple', 244, 'Added PPE item 114/123: ppetests', '2026-03-11 16:13:31'),
(321, 6, 'Add PPE Multiple', 245, 'Added PPE item 115/123: ppetests', '2026-03-11 16:13:31'),
(322, 6, 'Add PPE Multiple', 246, 'Added PPE item 116/123: ppetests', '2026-03-11 16:13:31'),
(323, 6, 'Add PPE Multiple', 247, 'Added PPE item 117/123: ppetests', '2026-03-11 16:13:31'),
(324, 6, 'Add PPE Multiple', 248, 'Added PPE item 118/123: ppetests', '2026-03-11 16:13:31'),
(325, 6, 'Add PPE Multiple', 249, 'Added PPE item 119/123: ppetests', '2026-03-11 16:13:31'),
(326, 6, 'Add PPE Multiple', 250, 'Added PPE item 120/123: ppetests', '2026-03-11 16:13:31'),
(327, 6, 'Add PPE Multiple', 251, 'Added PPE item 121/123: ppetests', '2026-03-11 16:13:31'),
(328, 6, 'Add PPE Multiple', 252, 'Added PPE item 122/123: ppetests', '2026-03-11 16:13:31'),
(329, 6, 'Add PPE Multiple', 253, 'Added PPE item 123/123: ppetests', '2026-03-11 16:13:31'),
(330, 6, 'Login', 6, 'User logged in', '2026-03-13 07:45:49'),
(331, 6, 'Login', 6, 'User logged in', '2026-03-13 08:06:44'),
(332, 6, 'Delete Semi-Expendable', 85, 'Deleted semi-expendable item ID: 85', '2026-03-13 09:09:14'),
(333, 6, 'Add Semi-Expendable', 254, 'Added new semi-expendable item: singlesingle', '2026-03-13 09:09:40'),
(334, 6, 'Add Semi-Expendable', 255, 'Added new semi-expendable item: stocksingle', '2026-03-13 09:24:35'),
(335, 6, 'Add Semi-Expendable', 256, 'Added new semi-expendable item: singlesingledocs', '2026-03-13 09:42:21'),
(336, 6, 'Login', 6, 'User logged in', '2026-03-13 14:37:21'),
(337, 6, 'Login', 6, 'User logged in', '2026-03-13 16:54:28'),
(338, 5, 'Login', 5, 'User logged in', '2026-04-07 08:01:57'),
(339, 5, 'Logout', 5, 'User logged out', '2026-04-07 08:04:00'),
(340, 6, 'Login', 6, 'User logged in', '2026-04-07 08:04:02'),
(341, 6, 'Add PPE Multiple', 257, 'Added PPE item 1/25: april', '2026-04-07 08:08:51'),
(342, 6, 'Add PPE Multiple', 258, 'Added PPE item 2/25: april', '2026-04-07 08:08:51'),
(343, 6, 'Add PPE Multiple', 259, 'Added PPE item 3/25: april', '2026-04-07 08:08:51'),
(344, 6, 'Add PPE Multiple', 260, 'Added PPE item 4/25: april', '2026-04-07 08:08:51'),
(345, 6, 'Add PPE Multiple', 261, 'Added PPE item 5/25: april', '2026-04-07 08:08:51'),
(346, 6, 'Add PPE Multiple', 262, 'Added PPE item 6/25: april', '2026-04-07 08:08:51'),
(347, 6, 'Add PPE Multiple', 263, 'Added PPE item 7/25: april', '2026-04-07 08:08:51'),
(348, 6, 'Add PPE Multiple', 264, 'Added PPE item 8/25: april', '2026-04-07 08:08:51'),
(349, 6, 'Add PPE Multiple', 265, 'Added PPE item 9/25: april', '2026-04-07 08:08:51'),
(350, 6, 'Add PPE Multiple', 266, 'Added PPE item 10/25: april', '2026-04-07 08:08:51'),
(351, 6, 'Add PPE Multiple', 267, 'Added PPE item 11/25: april', '2026-04-07 08:08:51'),
(352, 6, 'Add PPE Multiple', 268, 'Added PPE item 12/25: april', '2026-04-07 08:08:51'),
(353, 6, 'Add PPE Multiple', 269, 'Added PPE item 13/25: april', '2026-04-07 08:08:51'),
(354, 6, 'Add PPE Multiple', 270, 'Added PPE item 14/25: april', '2026-04-07 08:08:51'),
(355, 6, 'Add PPE Multiple', 271, 'Added PPE item 15/25: april', '2026-04-07 08:08:51'),
(356, 6, 'Add PPE Multiple', 272, 'Added PPE item 16/25: april', '2026-04-07 08:08:51'),
(357, 6, 'Add PPE Multiple', 273, 'Added PPE item 17/25: april', '2026-04-07 08:08:51'),
(358, 6, 'Add PPE Multiple', 274, 'Added PPE item 18/25: april', '2026-04-07 08:08:51'),
(359, 6, 'Add PPE Multiple', 275, 'Added PPE item 19/25: april', '2026-04-07 08:08:51'),
(360, 6, 'Add PPE Multiple', 276, 'Added PPE item 20/25: april', '2026-04-07 08:08:51'),
(361, 6, 'Add PPE Multiple', 277, 'Added PPE item 21/25: april', '2026-04-07 08:08:51'),
(362, 6, 'Add PPE Multiple', 278, 'Added PPE item 22/25: april', '2026-04-07 08:08:51'),
(363, 6, 'Add PPE Multiple', 279, 'Added PPE item 23/25: april', '2026-04-07 08:08:51'),
(364, 6, 'Add PPE Multiple', 280, 'Added PPE item 24/25: april', '2026-04-07 08:08:51'),
(365, 6, 'Add PPE Multiple', 281, 'Added PPE item 25/25: april', '2026-04-07 08:08:51'),
(366, 6, 'Login', 6, 'User logged in', '2026-04-07 08:44:56'),
(367, 6, 'Add Semi-Expendable Multiple', 282, 'Added semi-expendable item 1/111: test2', '2026-04-07 09:24:57'),
(368, 6, 'Add Semi-Expendable Multiple', 283, 'Added semi-expendable item 2/111: test2', '2026-04-07 09:24:57'),
(369, 6, 'Add Semi-Expendable Multiple', 284, 'Added semi-expendable item 3/111: test2', '2026-04-07 09:24:57'),
(370, 6, 'Add Semi-Expendable Multiple', 285, 'Added semi-expendable item 4/111: test2', '2026-04-07 09:24:57'),
(371, 6, 'Add Semi-Expendable Multiple', 286, 'Added semi-expendable item 5/111: test2', '2026-04-07 09:24:57'),
(372, 6, 'Add Semi-Expendable Multiple', 287, 'Added semi-expendable item 6/111: test2', '2026-04-07 09:24:57'),
(373, 6, 'Add Semi-Expendable Multiple', 288, 'Added semi-expendable item 7/111: test2', '2026-04-07 09:24:57'),
(374, 6, 'Add Semi-Expendable Multiple', 289, 'Added semi-expendable item 8/111: test2', '2026-04-07 09:24:57'),
(375, 6, 'Add Semi-Expendable Multiple', 290, 'Added semi-expendable item 9/111: test2', '2026-04-07 09:24:57'),
(376, 6, 'Add Semi-Expendable Multiple', 291, 'Added semi-expendable item 10/111: test2', '2026-04-07 09:24:57'),
(377, 6, 'Add Semi-Expendable Multiple', 292, 'Added semi-expendable item 11/111: test2', '2026-04-07 09:24:57'),
(378, 6, 'Add Semi-Expendable Multiple', 293, 'Added semi-expendable item 12/111: test2', '2026-04-07 09:24:57'),
(379, 6, 'Add Semi-Expendable Multiple', 294, 'Added semi-expendable item 13/111: test2', '2026-04-07 09:24:57'),
(380, 6, 'Add Semi-Expendable Multiple', 295, 'Added semi-expendable item 14/111: test2', '2026-04-07 09:24:57'),
(381, 6, 'Add Semi-Expendable Multiple', 296, 'Added semi-expendable item 15/111: test2', '2026-04-07 09:24:57'),
(382, 6, 'Add Semi-Expendable Multiple', 297, 'Added semi-expendable item 16/111: test2', '2026-04-07 09:24:57'),
(383, 6, 'Add Semi-Expendable Multiple', 298, 'Added semi-expendable item 17/111: test2', '2026-04-07 09:24:57'),
(384, 6, 'Add Semi-Expendable Multiple', 299, 'Added semi-expendable item 18/111: test2', '2026-04-07 09:24:57'),
(385, 6, 'Add Semi-Expendable Multiple', 300, 'Added semi-expendable item 19/111: test2', '2026-04-07 09:24:57'),
(386, 6, 'Add Semi-Expendable Multiple', 301, 'Added semi-expendable item 20/111: test2', '2026-04-07 09:24:57'),
(387, 6, 'Add Semi-Expendable Multiple', 302, 'Added semi-expendable item 21/111: test2', '2026-04-07 09:24:57'),
(388, 6, 'Add Semi-Expendable Multiple', 303, 'Added semi-expendable item 22/111: test2', '2026-04-07 09:24:57'),
(389, 6, 'Add Semi-Expendable Multiple', 304, 'Added semi-expendable item 23/111: test2', '2026-04-07 09:24:57'),
(390, 6, 'Add Semi-Expendable Multiple', 305, 'Added semi-expendable item 24/111: test2', '2026-04-07 09:24:57'),
(391, 6, 'Add Semi-Expendable Multiple', 306, 'Added semi-expendable item 25/111: test2', '2026-04-07 09:24:57'),
(392, 6, 'Add Semi-Expendable Multiple', 307, 'Added semi-expendable item 26/111: test2', '2026-04-07 09:24:57'),
(393, 6, 'Add Semi-Expendable Multiple', 308, 'Added semi-expendable item 27/111: test2', '2026-04-07 09:24:57'),
(394, 6, 'Add Semi-Expendable Multiple', 309, 'Added semi-expendable item 28/111: test2', '2026-04-07 09:24:57'),
(395, 6, 'Add Semi-Expendable Multiple', 310, 'Added semi-expendable item 29/111: test2', '2026-04-07 09:24:57'),
(396, 6, 'Add Semi-Expendable Multiple', 311, 'Added semi-expendable item 30/111: test2', '2026-04-07 09:24:57'),
(397, 6, 'Add Semi-Expendable Multiple', 312, 'Added semi-expendable item 31/111: test2', '2026-04-07 09:24:57'),
(398, 6, 'Add Semi-Expendable Multiple', 313, 'Added semi-expendable item 32/111: test2', '2026-04-07 09:24:57'),
(399, 6, 'Add Semi-Expendable Multiple', 314, 'Added semi-expendable item 33/111: test2', '2026-04-07 09:24:57'),
(400, 6, 'Add Semi-Expendable Multiple', 315, 'Added semi-expendable item 34/111: test2', '2026-04-07 09:24:57'),
(401, 6, 'Add Semi-Expendable Multiple', 316, 'Added semi-expendable item 35/111: test2', '2026-04-07 09:24:57'),
(402, 6, 'Add Semi-Expendable Multiple', 317, 'Added semi-expendable item 36/111: test2', '2026-04-07 09:24:57'),
(403, 6, 'Add Semi-Expendable Multiple', 318, 'Added semi-expendable item 37/111: test2', '2026-04-07 09:24:57'),
(404, 6, 'Add Semi-Expendable Multiple', 319, 'Added semi-expendable item 38/111: test2', '2026-04-07 09:24:57'),
(405, 6, 'Add Semi-Expendable Multiple', 320, 'Added semi-expendable item 39/111: test2', '2026-04-07 09:24:57'),
(406, 6, 'Add Semi-Expendable Multiple', 321, 'Added semi-expendable item 40/111: test2', '2026-04-07 09:24:57'),
(407, 6, 'Add Semi-Expendable Multiple', 322, 'Added semi-expendable item 41/111: test2', '2026-04-07 09:24:57'),
(408, 6, 'Add Semi-Expendable Multiple', 323, 'Added semi-expendable item 42/111: test2', '2026-04-07 09:24:57'),
(409, 6, 'Add Semi-Expendable Multiple', 324, 'Added semi-expendable item 43/111: test2', '2026-04-07 09:24:57'),
(410, 6, 'Add Semi-Expendable Multiple', 325, 'Added semi-expendable item 44/111: test2', '2026-04-07 09:24:57'),
(411, 6, 'Add Semi-Expendable Multiple', 326, 'Added semi-expendable item 45/111: test2', '2026-04-07 09:24:57'),
(412, 6, 'Add Semi-Expendable Multiple', 327, 'Added semi-expendable item 46/111: test2', '2026-04-07 09:24:57'),
(413, 6, 'Add Semi-Expendable Multiple', 328, 'Added semi-expendable item 47/111: test2', '2026-04-07 09:24:57'),
(414, 6, 'Add Semi-Expendable Multiple', 329, 'Added semi-expendable item 48/111: test2', '2026-04-07 09:24:57'),
(415, 6, 'Add Semi-Expendable Multiple', 330, 'Added semi-expendable item 49/111: test2', '2026-04-07 09:24:57'),
(416, 6, 'Add Semi-Expendable Multiple', 331, 'Added semi-expendable item 50/111: test2', '2026-04-07 09:24:57'),
(417, 6, 'Add Semi-Expendable Multiple', 332, 'Added semi-expendable item 51/111: test2', '2026-04-07 09:24:57'),
(418, 6, 'Add Semi-Expendable Multiple', 333, 'Added semi-expendable item 52/111: test2', '2026-04-07 09:24:57'),
(419, 6, 'Add Semi-Expendable Multiple', 334, 'Added semi-expendable item 53/111: test2', '2026-04-07 09:24:57'),
(420, 6, 'Add Semi-Expendable Multiple', 335, 'Added semi-expendable item 54/111: test2', '2026-04-07 09:24:57'),
(421, 6, 'Add Semi-Expendable Multiple', 336, 'Added semi-expendable item 55/111: test2', '2026-04-07 09:24:57'),
(422, 6, 'Add Semi-Expendable Multiple', 337, 'Added semi-expendable item 56/111: test2', '2026-04-07 09:24:57'),
(423, 6, 'Add Semi-Expendable Multiple', 338, 'Added semi-expendable item 57/111: test2', '2026-04-07 09:24:57'),
(424, 6, 'Add Semi-Expendable Multiple', 339, 'Added semi-expendable item 58/111: test2', '2026-04-07 09:24:57'),
(425, 6, 'Add Semi-Expendable Multiple', 340, 'Added semi-expendable item 59/111: test2', '2026-04-07 09:24:57'),
(426, 6, 'Add Semi-Expendable Multiple', 341, 'Added semi-expendable item 60/111: test2', '2026-04-07 09:24:57'),
(427, 6, 'Add Semi-Expendable Multiple', 342, 'Added semi-expendable item 61/111: test2', '2026-04-07 09:24:57'),
(428, 6, 'Add Semi-Expendable Multiple', 343, 'Added semi-expendable item 62/111: test2', '2026-04-07 09:24:57'),
(429, 6, 'Add Semi-Expendable Multiple', 344, 'Added semi-expendable item 63/111: test2', '2026-04-07 09:24:57'),
(430, 6, 'Add Semi-Expendable Multiple', 345, 'Added semi-expendable item 64/111: test2', '2026-04-07 09:24:57'),
(431, 6, 'Add Semi-Expendable Multiple', 346, 'Added semi-expendable item 65/111: test2', '2026-04-07 09:24:57'),
(432, 6, 'Add Semi-Expendable Multiple', 347, 'Added semi-expendable item 66/111: test2', '2026-04-07 09:24:57'),
(433, 6, 'Add Semi-Expendable Multiple', 348, 'Added semi-expendable item 67/111: test2', '2026-04-07 09:24:57'),
(434, 6, 'Add Semi-Expendable Multiple', 349, 'Added semi-expendable item 68/111: test2', '2026-04-07 09:24:57'),
(435, 6, 'Add Semi-Expendable Multiple', 350, 'Added semi-expendable item 69/111: test2', '2026-04-07 09:24:57'),
(436, 6, 'Add Semi-Expendable Multiple', 351, 'Added semi-expendable item 70/111: test2', '2026-04-07 09:24:57'),
(437, 6, 'Add Semi-Expendable Multiple', 352, 'Added semi-expendable item 71/111: test2', '2026-04-07 09:24:57'),
(438, 6, 'Add Semi-Expendable Multiple', 353, 'Added semi-expendable item 72/111: test2', '2026-04-07 09:24:57'),
(439, 6, 'Add Semi-Expendable Multiple', 354, 'Added semi-expendable item 73/111: test2', '2026-04-07 09:24:57'),
(440, 6, 'Add Semi-Expendable Multiple', 355, 'Added semi-expendable item 74/111: test2', '2026-04-07 09:24:57'),
(441, 6, 'Add Semi-Expendable Multiple', 356, 'Added semi-expendable item 75/111: test2', '2026-04-07 09:24:57'),
(442, 6, 'Add Semi-Expendable Multiple', 357, 'Added semi-expendable item 76/111: test2', '2026-04-07 09:24:57'),
(443, 6, 'Add Semi-Expendable Multiple', 358, 'Added semi-expendable item 77/111: test2', '2026-04-07 09:24:57'),
(444, 6, 'Add Semi-Expendable Multiple', 359, 'Added semi-expendable item 78/111: test2', '2026-04-07 09:24:57'),
(445, 6, 'Add Semi-Expendable Multiple', 360, 'Added semi-expendable item 79/111: test2', '2026-04-07 09:24:57'),
(446, 6, 'Add Semi-Expendable Multiple', 361, 'Added semi-expendable item 80/111: test2', '2026-04-07 09:24:57'),
(447, 6, 'Add Semi-Expendable Multiple', 362, 'Added semi-expendable item 81/111: test2', '2026-04-07 09:24:57'),
(448, 6, 'Add Semi-Expendable Multiple', 363, 'Added semi-expendable item 82/111: test2', '2026-04-07 09:24:57'),
(449, 6, 'Add Semi-Expendable Multiple', 364, 'Added semi-expendable item 83/111: test2', '2026-04-07 09:24:57'),
(450, 6, 'Add Semi-Expendable Multiple', 365, 'Added semi-expendable item 84/111: test2', '2026-04-07 09:24:57'),
(451, 6, 'Add Semi-Expendable Multiple', 366, 'Added semi-expendable item 85/111: test2', '2026-04-07 09:24:57'),
(452, 6, 'Add Semi-Expendable Multiple', 367, 'Added semi-expendable item 86/111: test2', '2026-04-07 09:24:57'),
(453, 6, 'Add Semi-Expendable Multiple', 368, 'Added semi-expendable item 87/111: test2', '2026-04-07 09:24:57'),
(454, 6, 'Add Semi-Expendable Multiple', 369, 'Added semi-expendable item 88/111: test2', '2026-04-07 09:24:57'),
(455, 6, 'Add Semi-Expendable Multiple', 370, 'Added semi-expendable item 89/111: test2', '2026-04-07 09:24:57'),
(456, 6, 'Add Semi-Expendable Multiple', 371, 'Added semi-expendable item 90/111: test2', '2026-04-07 09:24:57'),
(457, 6, 'Add Semi-Expendable Multiple', 372, 'Added semi-expendable item 91/111: test2', '2026-04-07 09:24:57'),
(458, 6, 'Add Semi-Expendable Multiple', 373, 'Added semi-expendable item 92/111: test2', '2026-04-07 09:24:57'),
(459, 6, 'Add Semi-Expendable Multiple', 374, 'Added semi-expendable item 93/111: test2', '2026-04-07 09:24:57'),
(460, 6, 'Add Semi-Expendable Multiple', 375, 'Added semi-expendable item 94/111: test2', '2026-04-07 09:24:57'),
(461, 6, 'Add Semi-Expendable Multiple', 376, 'Added semi-expendable item 95/111: test2', '2026-04-07 09:24:57'),
(462, 6, 'Add Semi-Expendable Multiple', 377, 'Added semi-expendable item 96/111: test2', '2026-04-07 09:24:57'),
(463, 6, 'Add Semi-Expendable Multiple', 378, 'Added semi-expendable item 97/111: test2', '2026-04-07 09:24:57'),
(464, 6, 'Add Semi-Expendable Multiple', 379, 'Added semi-expendable item 98/111: test2', '2026-04-07 09:24:57'),
(465, 6, 'Add Semi-Expendable Multiple', 380, 'Added semi-expendable item 99/111: test2', '2026-04-07 09:24:57'),
(466, 6, 'Add Semi-Expendable Multiple', 381, 'Added semi-expendable item 100/111: test2', '2026-04-07 09:24:57'),
(467, 6, 'Add Semi-Expendable Multiple', 382, 'Added semi-expendable item 101/111: test2', '2026-04-07 09:24:57'),
(468, 6, 'Add Semi-Expendable Multiple', 383, 'Added semi-expendable item 102/111: test2', '2026-04-07 09:24:57'),
(469, 6, 'Add Semi-Expendable Multiple', 384, 'Added semi-expendable item 103/111: test2', '2026-04-07 09:24:57'),
(470, 6, 'Add Semi-Expendable Multiple', 385, 'Added semi-expendable item 104/111: test2', '2026-04-07 09:24:57'),
(471, 6, 'Add Semi-Expendable Multiple', 386, 'Added semi-expendable item 105/111: test2', '2026-04-07 09:24:57'),
(472, 6, 'Add Semi-Expendable Multiple', 387, 'Added semi-expendable item 106/111: test2', '2026-04-07 09:24:57'),
(473, 6, 'Add Semi-Expendable Multiple', 388, 'Added semi-expendable item 107/111: test2', '2026-04-07 09:24:57'),
(474, 6, 'Add Semi-Expendable Multiple', 389, 'Added semi-expendable item 108/111: test2', '2026-04-07 09:24:57'),
(475, 6, 'Add Semi-Expendable Multiple', 390, 'Added semi-expendable item 109/111: test2', '2026-04-07 09:24:57'),
(476, 6, 'Add Semi-Expendable Multiple', 391, 'Added semi-expendable item 110/111: test2', '2026-04-07 09:24:57'),
(477, 6, 'Add Semi-Expendable Multiple', 392, 'Added semi-expendable item 111/111: test2', '2026-04-07 09:24:57'),
(478, 6, 'Logout', 6, 'User logged out', '2026-04-07 09:32:00'),
(479, 6, 'Login', 6, 'User logged in', '2026-04-08 11:46:13'),
(480, 6, 'Login', 6, 'User logged in', '2026-04-08 14:46:36'),
(481, 6, 'Logout', 6, 'User logged out', '2026-04-08 14:47:52'),
(482, 5, 'Login', 5, 'User logged in', '2026-04-13 08:01:51'),
(483, 5, 'Logout', 5, 'User logged out', '2026-04-13 08:02:02'),
(484, 5, 'Login', 5, 'User logged in', '2026-04-13 08:02:05'),
(485, 5, 'Logout', 5, 'User logged out', '2026-04-13 08:02:44'),
(486, 6, 'Login', 6, 'User logged in', '2026-04-13 08:02:47'),
(487, 5, 'Login', 5, 'User logged in', '2026-04-13 11:32:41'),
(488, 5, 'Logout', 5, 'User logged out', '2026-04-13 11:34:48'),
(489, 6, 'Login', 6, 'User logged in', '2026-04-13 11:34:52'),
(490, 6, 'Logout', 6, 'User logged out', '2026-04-13 11:35:56'),
(491, 5, 'Login', 5, 'User logged in', '2026-04-13 11:36:01'),
(492, 5, 'Logout', 5, 'User logged out', '2026-04-13 11:37:45'),
(493, 6, 'Login', 6, 'User logged in', '2026-04-13 11:37:48'),
(494, 5, 'Login', 5, 'User logged in', '2026-04-13 12:52:50'),
(495, 5, 'Logout', 5, 'User logged out', '2026-04-13 12:53:05'),
(496, 6, 'Login', 6, 'User logged in', '2026-04-13 12:53:07'),
(497, 6, 'Login', 6, 'User logged in', '2026-04-13 16:19:47'),
(498, 6, 'Add Inventory', 393, 'Added new item(s): dasdasdasdasdasdas with single item created.', '2026-04-13 16:22:27'),
(499, 6, 'Login', 6, 'User logged in', '2026-04-14 07:59:20'),
(500, 6, 'Profile Update', 6, 'Updated profile information', '2026-04-14 07:59:32'),
(501, 6, 'Profile Update', 6, 'Updated profile information', '2026-04-14 08:41:04'),
(502, 6, 'Add Inventory', 405, 'Added new item(s): nobar with 12 items created.', '2026-04-14 08:43:10'),
(503, 6, 'Add PPE Multiple', 407, 'Added PPE item 1/12: testfordpt', '2026-04-14 08:52:45'),
(504, 6, 'Add PPE Multiple', 408, 'Added PPE item 2/12: testfordpt', '2026-04-14 08:52:45'),
(505, 6, 'Add PPE Multiple', 409, 'Added PPE item 3/12: testfordpt', '2026-04-14 08:52:45'),
(506, 6, 'Add PPE Multiple', 410, 'Added PPE item 4/12: testfordpt', '2026-04-14 08:52:45'),
(507, 6, 'Add PPE Multiple', 411, 'Added PPE item 5/12: testfordpt', '2026-04-14 08:52:45'),
(508, 6, 'Add PPE Multiple', 412, 'Added PPE item 6/12: testfordpt', '2026-04-14 08:52:45'),
(509, 6, 'Add PPE Multiple', 413, 'Added PPE item 7/12: testfordpt', '2026-04-14 08:52:45'),
(510, 6, 'Add PPE Multiple', 414, 'Added PPE item 8/12: testfordpt', '2026-04-14 08:52:45'),
(511, 6, 'Add PPE Multiple', 415, 'Added PPE item 9/12: testfordpt', '2026-04-14 08:52:45'),
(512, 6, 'Add PPE Multiple', 416, 'Added PPE item 10/12: testfordpt', '2026-04-14 08:52:45'),
(513, 6, 'Add PPE Multiple', 417, 'Added PPE item 11/12: testfordpt', '2026-04-14 08:52:45'),
(514, 6, 'Add PPE Multiple', 418, 'Added PPE item 12/12: testfordpt', '2026-04-14 08:52:45'),
(515, 6, 'Logout', 6, 'User logged out', '2026-04-14 10:10:31'),
(516, 5, 'Unauthorized Access', 5, 'Attempted to access page requiring roles: admin', '2026-04-14 10:13:07'),
(517, 5, 'Logout', 5, 'User logged out', '2026-04-14 10:13:24'),
(518, 7, 'Logout', 7, 'User logged out', '2026-04-14 10:30:00'),
(519, 7, 'Logout', 7, 'User logged out', '2026-04-14 10:42:43'),
(520, 5, 'Logout', 5, 'User logged out', '2026-04-14 13:30:21'),
(521, 8, 'Logout', 8, 'User logged out', '2026-04-14 14:55:41'),
(522, 6, 'Logout', 6, 'User logged out', '2026-04-14 15:20:24'),
(523, 5, 'Logout', 5, 'User logged out', '2026-04-14 15:24:37'),
(524, 6, 'Logout', 6, 'User logged out', '2026-04-14 15:25:21'),
(525, 7, 'Logout', 7, 'User logged out', '2026-04-14 15:25:28'),
(526, 5, 'Logout', 5, 'User logged out', '2026-04-14 15:25:43'),
(527, 6, 'Logout', 6, 'User logged out', '2026-04-14 15:31:59'),
(528, 5, 'Logout', 5, 'User logged out', '2026-04-14 15:32:48'),
(529, 6, 'Logout', 6, 'User logged out', '2026-04-14 15:59:50'),
(530, 5, 'Logout', 5, 'User logged out', '2026-04-14 16:00:55'),
(531, 6, 'Logout', 6, 'User logged out', '2026-04-14 16:05:17'),
(532, 5, '5', 0, 'Updated profile information', '2026-04-14 16:18:23'),
(533, 5, '5', 0, 'Updated profile information', '2026-04-14 16:18:49'),
(534, 5, 'Logout', 5, 'User logged out', '2026-04-14 16:27:00'),
(535, NULL, 'Failed Login', 0, 'Failed login attempt - username not found: asdasd', '2026-04-14 16:27:04'),
(536, 5, '5', 0, 'Updated profile information', '2026-04-14 16:28:30'),
(537, 5, 'Toggle User Status', 5, 'User status changed to inactive', '2026-04-14 16:29:19');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `item_id`, `details`, `date_created`) VALUES
(538, NULL, 'Unauthorized Access', 0, 'Attempted to access page requiring roles: super_admin', '2026-04-14 16:30:41'),
(539, NULL, 'Failed Login', 5, 'Login attempt on inactive account: superadmin', '2026-04-14 16:30:44'),
(540, NULL, 'Failed Login', 5, 'Login attempt on inactive account: superadmin', '2026-04-14 16:30:48'),
(541, 6, 'Logout', 6, 'User logged out', '2026-04-14 16:31:13'),
(542, NULL, 'Failed Login', 5, 'Login attempt on inactive account: superadmin', '2026-04-14 16:31:16'),
(543, 6, 'Add PPE', 419, 'Added new PPE item: for report', '2026-04-15 08:14:08'),
(544, 6, 'Issue Item', 9, 'Issued 1 of item to user ID: 9', '2026-04-15 08:35:05'),
(545, NULL, 'Failed Login', 6, 'Failed login attempt for username: admin', '2026-04-19 19:24:55'),
(546, 6, 'Logout', 6, 'User logged out', '2026-04-19 20:18:36'),
(547, 6, 'Issue Multiple Items', 0, 'Issued 1 item(s) to user ID: 7', '2026-04-20 08:20:39'),
(548, 6, 'Issue Multiple Items', 0, 'Issued 1 item(s) to user ID: 6', '2026-04-20 10:25:25'),
(549, 6, 'Logout', 6, 'User logged out', '2026-04-20 10:58:04'),
(550, 5, 'Logout', 5, 'User logged out', '2026-04-20 11:07:53'),
(551, 6, 'Logout', 6, 'User logged out', '2026-04-20 11:42:41'),
(552, 6, 'Issue Multiple Items', 0, 'Issued 2 item(s) to user ID: 8', '2026-04-20 11:44:21'),
(553, 6, 'Logout', 6, 'User logged out', '2026-04-20 11:54:31'),
(554, 6, 'Logout', 6, 'User logged out', '2026-04-20 13:27:34'),
(555, 5, 'Logout', 5, 'User logged out', '2026-04-20 13:32:56'),
(556, 6, 'Logout', 6, 'User logged out', '2026-04-20 13:36:28'),
(557, 6, 'Logout', 6, 'User logged out', '2026-04-20 14:00:14'),
(558, 6, '6', 0, 'Updated profile information', '2026-04-20 16:12:56'),
(559, 6, '6', 0, 'Updated profile information', '2026-04-20 16:14:07'),
(560, 6, '6', 0, 'Updated profile information', '2026-04-20 16:14:09'),
(561, 6, 'Profile Update', 6, 'Updated profile information', '2026-04-20 16:17:53'),
(562, 6, '6', 0, 'Updated profile information', '2026-04-20 16:53:57'),
(563, 6, 'Logout', 6, 'User logged out', '2026-04-22 10:43:26'),
(564, 6, 'Delete Semi-Expendable', 256, 'Deleted semi-expendable item ID: 256', '2026-04-22 10:57:38'),
(565, 6, 'Delete Semi-Expendable', 255, 'Deleted semi-expendable item ID: 255', '2026-04-22 10:57:41'),
(566, 6, 'Delete Semi-Expendable', 254, 'Deleted semi-expendable item ID: 254', '2026-04-22 10:57:43'),
(567, 6, 'Delete Semi-Expendable', 119, 'Deleted semi-expendable item ID: 119', '2026-04-22 10:57:46'),
(568, 6, 'Delete Semi-Expendable', 120, 'Deleted semi-expendable item ID: 120', '2026-04-22 10:57:52'),
(569, 6, 'Delete Semi-Expendable', 107, 'Deleted semi-expendable item ID: 107', '2026-04-22 10:58:03'),
(570, 6, 'Delete Semi-Expendable', 108, 'Deleted semi-expendable item ID: 108', '2026-04-22 10:58:09'),
(571, 6, 'Delete Semi-Expendable', 109, 'Deleted semi-expendable item ID: 109', '2026-04-22 10:58:13'),
(572, 6, 'Delete Semi-Expendable', 110, 'Deleted semi-expendable item ID: 110', '2026-04-22 10:58:17');

-- --------------------------------------------------------

--
-- Table structure for table `audit_trail`
--

CREATE TABLE `audit_trail` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `table_name` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_trail`
--

INSERT INTO `audit_trail` (`id`, `user_id`, `action`, `table_name`, `record_id`, `old_value`, `new_value`, `ip_address`, `created_at`) VALUES
(1, 5, 'LOGIN', 'users', 5, NULL, 'Login successful', '::1', '2026-04-14 02:10:33'),
(2, 7, 'LOGIN', 'users', 7, NULL, 'Login successful', '::1', '2026-04-14 02:13:27'),
(3, 7, 'LOGIN', 'users', 7, NULL, 'Login successful', '::1', '2026-04-14 02:35:18'),
(4, 6, 'LOGIN', 'users', 6, NULL, 'Login successful', '::1', '2026-04-14 02:42:46'),
(5, 5, 'LOGIN', 'users', 5, NULL, 'Login successful', '::1', '2026-04-14 05:30:17'),
(6, 6, 'LOGIN', 'users', 6, NULL, 'Login successful', '::1', '2026-04-14 05:30:23'),
(7, 8, 'LOGIN', 'users', 8, NULL, 'Login successful', '::1', '2026-04-14 06:47:12'),
(8, 6, 'LOGIN', 'users', 6, NULL, 'Login successful', '::1', '2026-04-14 06:55:44'),
(9, 5, 'LOGIN', 'users', 5, NULL, 'Login successful', '::1', '2026-04-14 07:20:27'),
(10, 6, 'LOGIN', 'users', 6, NULL, 'Login successful', '::1', '2026-04-14 07:24:39'),
(11, 7, 'LOGIN', 'users', 7, NULL, 'Login successful', '::1', '2026-04-14 07:25:24'),
(12, 5, 'LOGIN', 'users', 5, NULL, 'Login successful', '::1', '2026-04-14 07:25:31'),
(13, 6, 'LOGIN', 'users', 6, NULL, 'Login successful', '::1', '2026-04-14 07:25:47'),
(14, 5, 'LOGIN', 'users', 5, NULL, 'Login successful', '::1', '2026-04-14 07:32:01'),
(15, 6, 'LOGIN', 'users', 6, NULL, 'Login successful', '::1', '2026-04-14 07:32:51'),
(16, 5, 'LOGIN', 'users', 5, NULL, 'Login successful', '::1', '2026-04-14 07:59:53'),
(17, 6, 'LOGIN', 'users', 6, NULL, 'Login successful', '::1', '2026-04-14 08:00:57'),
(18, 5, 'LOGIN', 'users', 5, NULL, 'Login successful', '::1', '2026-04-14 08:05:20'),
(19, 5, 'LOGIN', 'users', 5, NULL, 'Login successful', '::1', '2026-04-14 08:27:06'),
(20, 5, 'UPDATE', 'users', 5, '{\"status\":\"active\"}', '{\"status\":\"inactive\"}', '::1', '2026-04-14 08:29:19'),
(21, 6, 'LOGIN', 'users', 6, NULL, 'Login successful', '::1', '2026-04-14 08:30:52'),
(22, 5, 'LOGIN', 'users', 5, NULL, 'Login successful', '::1', '2026-04-14 08:31:32'),
(23, 6, 'LOGIN', 'users', 6, NULL, 'Login successful', '::1', '2026-04-14 23:51:14'),
(24, 6, 'LOGIN', 'users', 6, NULL, 'Login successful', '::1', '2026-04-15 01:43:09'),
(25, 6, 'LOGIN', 'users', 6, NULL, 'Login successful', '::1', '2026-04-19 11:24:59'),
(26, 6, 'LOGIN', 'users', 6, NULL, 'Login successful', '::1', '2026-04-19 11:41:54'),
(27, 6, 'LOGIN', 'users', 6, NULL, 'Login successful', '::1', '2026-04-19 12:18:39'),
(28, 6, 'LOGIN', 'users', 6, NULL, 'Login successful', '::1', '2026-04-20 00:07:46'),
(29, 6, 'LOGIN', 'users', 6, NULL, 'Login successful', '::1', '2026-04-20 02:15:21'),
(30, 5, 'LOGIN', 'users', 5, NULL, 'Login successful', '::1', '2026-04-20 02:58:06'),
(31, 6, 'LOGIN', 'users', 6, NULL, 'Login successful', '::1', '2026-04-20 03:07:55'),
(32, 6, 'LOGIN', 'users', 6, NULL, 'Login successful', '::1', '2026-04-20 03:42:49'),
(33, 5, 'LOGIN', 'users', 5, NULL, 'Login successful', '::1', '2026-04-20 03:54:54'),
(34, 6, 'LOGIN', 'users', 6, NULL, 'Login successful', '::1', '2026-04-20 05:27:28'),
(35, 5, 'LOGIN', 'users', 5, NULL, 'Login successful', '::1', '2026-04-20 05:27:37'),
(36, 6, 'LOGIN', 'users', 6, NULL, 'Login successful', '::1', '2026-04-20 05:32:58'),
(37, 6, 'LOGIN', 'users', 6, NULL, 'Login successful', '::1', '2026-04-20 05:36:31'),
(38, 6, 'LOGIN', 'users', 6, NULL, 'Login successful', '::1', '2026-04-20 06:00:16'),
(39, 6, 'LOGIN', 'users', 6, NULL, 'Login successful', '::1', '2026-04-20 06:01:47'),
(40, 6, 'LOGIN', 'users', 6, NULL, 'Login successful', '::1', '2026-04-20 08:03:46'),
(41, 6, 'LOGIN', 'users', 6, NULL, 'Login successful', '::1', '2026-04-20 08:07:54'),
(42, 6, 'LOGIN', 'users', 6, NULL, 'Login successful', '::1', '2026-04-22 02:39:00'),
(43, 6, 'LOGIN', 'users', 6, NULL, 'Login successful', '::1', '2026-04-22 02:45:45');

-- --------------------------------------------------------

--
-- Table structure for table `buildings`
--

CREATE TABLE `buildings` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `floor` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `buildings`
--

INSERT INTO `buildings` (`id`, `name`, `floor`) VALUES
(1, 'Main Building', 3),
(2, 'Ward Building', 2),
(3, 'Annex Building', 1),
(4, 'Warehouse', 1),
(5, 'building ko', 1);

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `building_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `building_id`, `name`) VALUES
(1, 1, 'Emergency Department'),
(2, 1, 'Pharmacy'),
(3, 2, 'ICU'),
(4, 2, 'Surgery'),
(5, 3, 'Administration'),
(6, 4, 'Storage');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `firstname` varchar(100) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `middlename` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `contact` varchar(50) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `section_id` int(11) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `date_hired` date DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `date_created` timestamp NULL DEFAULT current_timestamp(),
  `date_updated` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `firstname`, `lastname`, `middlename`, `email`, `contact`, `department_id`, `section_id`, `position`, `date_hired`, `status`, `date_created`, `date_updated`) VALUES
(1, 'dasdasdasd', 'adsadada', 'dasdasdas', 'dasdasdasd@gmail.com', '', NULL, NULL, '', NULL, 'Active', '2026-03-06 01:41:16', '2026-03-06 01:41:16');

-- --------------------------------------------------------

--
-- Table structure for table `equipment`
--

CREATE TABLE `equipment` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `equipment`
--

INSERT INTO `equipment` (`id`, `name`, `category`, `description`) VALUES
(1, 'Dummy Equipment', 'GENERAL', 'Placeholder'),
(2, 'Laptop', 'ICT', 'Office Laptop'),
(3, 'Desktop PC', 'ICT', 'Computer'),
(4, 'Medical Bed', 'MEDICAL', 'Hospital Bed'),
(5, 'PPE Set', 'SAFETY', 'Personal Protective Equipment'),
(6, 'Semi-Expendable Item', 'SUPPLIES', 'Semi-expendable supplies');

-- --------------------------------------------------------

--
-- Table structure for table `equipment_issuance`
--

CREATE TABLE `equipment_issuance` (
  `id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `issued_to` int(11) NOT NULL,
  `issued_by` int(11) NOT NULL,
  `quantity_issued` decimal(12,2) NOT NULL,
  `purpose` varchar(255) DEFAULT NULL,
  `location_used` varchar(255) DEFAULT NULL,
  `expected_return` date DEFAULT NULL,
  `actual_return` date DEFAULT NULL,
  `status` enum('issued','returned','partial') DEFAULT 'issued',
  `condition_on_issue` varchar(100) DEFAULT NULL,
  `condition_on_return` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `issued_date` timestamp NULL DEFAULT current_timestamp(),
  `returned_date` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `equipment_issuance`
--

INSERT INTO `equipment_issuance` (`id`, `inventory_id`, `issued_to`, `issued_by`, `quantity_issued`, `purpose`, `location_used`, `expected_return`, `actual_return`, `status`, `condition_on_issue`, `condition_on_return`, `remarks`, `issued_date`, `returned_date`) VALUES
(2, 9, 9, 6, 1.00, 'issue yarn', NULL, NULL, NULL, 'issued', 'Good', NULL, '', '2026-04-15 00:35:05', NULL),
(4, 282, 7, 6, 12.00, 'multiple example distyplay', NULL, NULL, NULL, 'issued', 'Good', NULL, 'example', '2026-04-20 00:20:39', NULL),
(8, 407, 6, 6, 1.00, 'sadasd', NULL, NULL, NULL, 'issued', 'Good', NULL, 'asdasd', '2026-04-20 02:25:25', NULL),
(9, 407, 8, 6, 1.00, 'multiple example', NULL, NULL, NULL, 'issued', 'Good', NULL, 'example', '2026-04-20 03:44:21', NULL),
(10, 257, 8, 6, 1.00, 'multiple example', NULL, NULL, NULL, 'issued', 'Good', NULL, 'example', '2026-04-20 03:44:21', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `article_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `property_no` varchar(120) DEFAULT NULL,
  `uom` varchar(50) DEFAULT NULL,
  `qty_property_card` decimal(12,2) DEFAULT 0.00,
  `qty_physical_count` decimal(12,2) DEFAULT 0.00,
  `location_id` int(11) DEFAULT NULL,
  `condition_text` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `certified_correct` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `section_id` int(11) DEFAULT NULL,
  `date_added` timestamp NULL DEFAULT current_timestamp(),
  `date_updated` timestamp NULL DEFAULT NULL,
  `fund_cluster` varchar(50) DEFAULT NULL,
  `unit_value` decimal(12,2) DEFAULT 0.00,
  `equipment_id` int(11) DEFAULT 1,
  `type_equipment` varchar(50) DEFAULT '',
  `category` varchar(50) DEFAULT '',
  `allocate_to` int(11) DEFAULT NULL,
  `barcode_data` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `current_holder` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `article_name`, `description`, `property_no`, `uom`, `qty_property_card`, `qty_physical_count`, `location_id`, `condition_text`, `remarks`, `certified_correct`, `approved_by`, `verified_by`, `section_id`, `date_added`, `date_updated`, `fund_cluster`, `unit_value`, `equipment_id`, `type_equipment`, `category`, `allocate_to`, `barcode_data`, `created_by`, `current_holder`) VALUES
(1, 'test1', 'test1', '2026-000001', '0', 1.00, 1.00, NULL, 'Good', 'test1', NULL, NULL, NULL, 2, '2026-03-01 06:00:46', NULL, 'test1cluster', 213213123.00, 3, '', 'Office Supplies', NULL, 'INV2026030100586', 6, NULL),
(3, 'testtest', 'testtest', '2026-000002', 'unit', 1.00, 1.00, NULL, 'Good', 'testtest', NULL, NULL, NULL, 2, '2026-03-01 06:03:48', '2026-03-01 06:04:21', 'test1cluster', 213213123.00, 3, '', 'Office Supplies', NULL, 'INV2026030105314', 6, NULL),
(5, 'ffghfghffghfhgfhgfghfghfghfhg', 'TESTonehundredsevnty', '2026-000003', '0', 1.00, 1.00, NULL, 'New', 'TESTonehundredsevnty', NULL, NULL, NULL, NULL, '2026-03-01 06:34:49', NULL, 'TESTonehundredsevnty', 999999999.00, 1, '', 'Furniture', NULL, 'INV2026030193912', 6, NULL),
(7, 'ffghfghffghfhgfhgfghfghfghfhg', 'TESTonehundredsevnty', '2026-000004', '0', 1.00, 1.00, NULL, 'New', 'TESTonehundredsevnty', NULL, NULL, NULL, NULL, '2026-03-01 06:34:51', NULL, 'TESTonehundredsevnty', 999999999.00, 1, '', 'Furniture', NULL, 'INV2026030127779', 6, NULL),
(9, 'addnewexample', 'addnewexample', '2026-000005', '0', 222.00, 221.00, NULL, 'Good', '0', 'sir alvin', 5, 7, 2, '2026-03-02 06:21:18', '2026-03-02 06:21:18', 'idk', 2121313.00, 3, 'heavy', 'ICT Equipment', 5, 'INV202603021817', 6, NULL),
(10, 'mikamaalat', 'ljsakldjsadkjsajdl', '2026-000006-1', '0', 1.00, 1.00, NULL, 'Good', '0', 'aEIADSADKAJDKAJ', 7, 7, 1, '2026-03-02 06:37:49', '2026-03-02 06:37:49', 'ALVIN', 20.00, 3, 'sadsadsadsad', 'ICT Equipment', 7, 'INV202603024825001', 6, NULL),
(11, 'mikamaalat', 'ljsakldjsadkjsajdl', '2026-000006-2', '0', 1.00, 1.00, NULL, 'Good', '0', 'aEIADSADKAJDKAJ', 7, 7, 1, '2026-03-02 06:37:49', '2026-03-02 06:37:49', 'ALVIN', 20.00, 3, 'sadsadsadsad', 'ICT Equipment', 7, 'INV202603024825002', 6, NULL),
(12, 'mikamaalat', 'ljsakldjsadkjsajdl', '2026-000006-3', '0', 1.00, 1.00, NULL, 'Good', '0', 'aEIADSADKAJDKAJ', 7, 7, 1, '2026-03-02 06:37:49', '2026-03-02 06:37:49', 'ALVIN', 20.00, 3, 'sadsadsadsad', 'ICT Equipment', 7, 'INV202603024825003', 6, NULL),
(13, 'mikamaalat', 'ljsakldjsadkjsajdl', '2026-000006-4', '0', 1.00, 1.00, NULL, 'Good', '0', 'aEIADSADKAJDKAJ', 7, 7, 1, '2026-03-02 06:37:49', '2026-03-02 06:37:49', 'ALVIN', 20.00, 3, 'sadsadsadsad', 'ICT Equipment', 7, 'INV202603024825004', 6, NULL),
(14, 'mikamaalat', 'ljsakldjsadkjsajdl', '2026-000006-5', '0', 1.00, 1.00, NULL, 'Good', '0', 'aEIADSADKAJDKAJ', 7, 7, 1, '2026-03-02 06:37:49', '2026-03-02 06:37:49', 'ALVIN', 20.00, 3, 'sadsadsadsad', 'ICT Equipment', 7, 'INV202603024825005', 6, NULL),
(15, 'mikamaalat', 'ljsakldjsadkjsajdl', '2026-000006-6', '0', 1.00, 1.00, NULL, 'Good', '0', 'aEIADSADKAJDKAJ', 7, 7, 1, '2026-03-02 06:37:49', '2026-03-02 06:37:49', 'ALVIN', 20.00, 3, 'sadsadsadsad', 'ICT Equipment', 7, 'INV202603024825006', 6, NULL),
(16, 'mikamaalat', 'ljsakldjsadkjsajdl', '2026-000006-7', '0', 1.00, 1.00, NULL, 'Good', '0', 'aEIADSADKAJDKAJ', 7, 7, 1, '2026-03-02 06:37:49', '2026-03-02 06:37:49', 'ALVIN', 20.00, 3, 'sadsadsadsad', 'ICT Equipment', 7, 'INV202603024825007', 6, NULL),
(17, 'mikamaalat', 'ljsakldjsadkjsajdl', '2026-000006-8', '0', 1.00, 1.00, NULL, 'Good', '0', 'aEIADSADKAJDKAJ', 7, 7, 1, '2026-03-02 06:37:49', '2026-03-02 06:37:49', 'ALVIN', 20.00, 3, 'sadsadsadsad', 'ICT Equipment', 7, 'INV202603024825008', 6, NULL),
(18, 'mikamaalat', 'ljsakldjsadkjsajdl', '2026-000006-9', '0', 1.00, 1.00, NULL, 'Good', '0', 'aEIADSADKAJDKAJ', 7, 7, 1, '2026-03-02 06:37:49', '2026-03-02 06:37:49', 'ALVIN', 20.00, 3, 'sadsadsadsad', 'ICT Equipment', 7, 'INV202603024825009', 6, NULL),
(19, 'mikamaalat', 'ljsakldjsadkjsajdl', '2026-000006-10', '0', 1.00, 1.00, NULL, 'Good', '0', 'aEIADSADKAJDKAJ', 7, 7, 1, '2026-03-02 06:37:49', '2026-03-02 06:37:49', 'ALVIN', 20.00, 3, 'sadsadsadsad', 'ICT Equipment', 7, 'INV202603024825010', 6, NULL),
(20, 'edited', 'finalbarcodesingle', '2026-000016-1', 'box', 1.00, 1.00, NULL, 'Good', '0', 'finalbarcodesingle', 7, NULL, 4, '2026-03-02 10:07:38', '2026-03-04 09:42:33', 'finalbarcodesingle', 2.00, 3, 'heavy', 'ICT Equipment', 7, 'INsssV202603047930', 6, NULL),
(21, 'dsadasdasd', 'asdasdasdasd', '2026-000017', '0', 1.00, 1.00, NULL, 'Good', '0', 'sadadasd', 5, NULL, 5, '2026-03-10 02:15:05', '2026-03-10 02:15:05', 'mmmm', 326.00, 1, '', 'Semi-Expendable', 5, '0', 6, NULL),
(22, 'exampleSemi', 'exampleSemi', '2026-000018', '0', 1.00, 1.00, NULL, 'Under Repair', '0', 'exampleSemi', 7, 5, 2, '2026-03-10 02:16:55', '2026-03-10 02:16:55', 'exampleSemi', 22.00, 3, '', 'Semi-Expendable', 7, '0', 6, NULL),
(23, 'exampletwo', 'exampletwo', '2026-000019', '0', 12.00, 12.00, NULL, 'Non-Serviceable', 'exampletwoexampletwoexampletwoexampletwoexampletwo', 'exampletwo', 7, 7, 2, '2026-03-10 02:18:01', '2026-03-10 02:18:01', 'exampletwo', 22.00, 3, '', 'Semi-Expendable', 7, '0', 6, NULL),
(24, 'exampleeenotatlo', 'asdasdasdasd', '2026-000020', '0', 122.00, 122.00, NULL, 'For Condemn', 'sdasdasdasdsadasd', 'dasdasdas', 7, 5, 6, '2026-03-10 02:32:19', '2026-03-10 02:32:19', 'sssssssssssssss', 3213213.00, 3, '', 'Semi-Expendable', 5, '0', 6, NULL),
(25, 'exampleidk', 'exampleidk', '2026-000021', 'unit', 122.00, 122.00, NULL, 'Non-Serviceable', 'exampleidk', '', 5, 7, 1, '2026-03-10 02:51:01', '2026-03-10 05:22:01', 'exampleidk', 212.00, 1, '', 'Semi-Expendable', 7, 'SEMI-20260310-5429', 6, NULL),
(26, 'mikaarticlenotwotwosixsix', 'antimaasimact', '2026-000022', 'box', 12.00, 12.00, NULL, 'Good', 'may alat pa sa katawan', 'aling lucing', 8, 5, 1, '2026-03-10 03:14:39', '2026-03-10 03:14:39', 'mang bitoy', 213123.00, 4, '', 'PPE', 5, 'PPE-20260310-7710', 6, NULL),
(27, 'multitest', 'multitest', '2026-000023', 'box', 10.00, 10.00, NULL, 'New', 'multitestmultitestmultitest', 'multitest', 7, 7, 2, '2026-03-10 06:05:07', '2026-03-10 06:05:07', 'multitestmultitest', 23.00, 3, '', 'PPE', 7, 'PPE-20260310-7152', 6, NULL),
(28, 'examplemultiple', 'examplemultiple', '2026-000024', 'pcs', 123.00, 123.00, NULL, 'Fair', 'examplemultiple', 'examplemultipleexamplemultiple', NULL, 7, 2, '2026-03-10 07:30:38', '2026-03-10 07:30:38', 'examplemultipleexamplemultiple', 233.00, 3, '', 'PPE', 5, '', 6, NULL),
(29, 'multianak', 'multianakmultianak', '2026-000025', 'pcs', 12.00, 12.00, NULL, 'Fair', 'multianakmultianakmultianak', 'multianakmultianak', 7, 7, 1, '2026-03-10 07:44:03', '2026-03-10 07:44:03', 'multianakmultianakmultianak', 23123.00, 2, '', 'PPE', 7, '', 6, NULL),
(30, 'singleexample', 'singleexample', '2026-000026', 'unit', 1.00, 1.00, NULL, 'Fair', 'singleexamplesingleexamplesingleexamplesingleexample', 'singleexample', 8, 7, 1, '2026-03-10 07:46:02', '2026-03-10 07:46:02', 'singleexamplesingleexamplesingleexamplesingleexamp', 3213213.00, 3, 'example', 'PPE', 5, 'PPE-20260310-5244', 6, NULL),
(31, 'asdasdasdasdasdasdasd', 'asdasdasdasdasdasdasdasdasdasdasd', '2026-000027', 'box', 12.00, 12.00, NULL, 'Good', 'asdasdasd', 'asdasdasd', 5, 5, 1, '2026-03-10 07:59:30', '2026-03-10 07:59:30', 'sadasdasd', 2132.00, 6, '', 'PPE', 7, '', 6, NULL),
(33, 'marchofyou', 'marchofyou', '2026-000028-002', 'box', 1.00, 1.00, NULL, 'Fair', 'marchofyoumarchofyoumarchofyou', 'marchofyoumarchofyou', 5, 7, 2, '2026-03-11 00:51:49', '2026-03-11 00:51:49', 'marchofyoumarchofyou', 2313.00, 2, '', 'PPE', 5, 'PPE-20260311-002', 6, NULL),
(34, 'marchofyou', 'marchofyou', '2026-000028-003', 'box', 1.00, 1.00, NULL, 'Fair', 'marchofyoumarchofyoumarchofyou', 'marchofyoumarchofyou', 5, 7, 2, '2026-03-11 00:51:49', '2026-03-11 00:51:49', 'marchofyoumarchofyou', 2313.00, 2, '', 'PPE', 5, 'PPE-20260311-003', 6, NULL),
(35, 'marchofyou', 'marchofyou', '2026-000028-004', 'box', 1.00, 1.00, NULL, 'Fair', 'marchofyoumarchofyoumarchofyou', 'marchofyoumarchofyou', 5, 7, 2, '2026-03-11 00:51:49', '2026-03-11 00:51:49', 'marchofyoumarchofyou', 2313.00, 2, '', 'PPE', 5, 'PPE-20260311-004', 6, NULL),
(36, 'marchofyou', 'marchofyou', '2026-000028-005', 'box', 1.00, 1.00, NULL, 'Fair', 'marchofyoumarchofyoumarchofyou', 'marchofyoumarchofyou', 5, 7, 2, '2026-03-11 00:51:49', '2026-03-11 00:51:49', 'marchofyoumarchofyou', 2313.00, 2, '', 'PPE', 5, 'PPE-20260311-005', 6, NULL),
(37, 'marchofyou', 'marchofyou', '2026-000028-006', 'box', 1.00, 1.00, NULL, 'Fair', 'marchofyoumarchofyoumarchofyou', 'marchofyoumarchofyou', 5, 7, 2, '2026-03-11 00:51:49', '2026-03-11 00:51:49', 'marchofyoumarchofyou', 2313.00, 2, '', 'PPE', 5, 'PPE-20260311-006', 6, NULL),
(38, 'marchofyou', 'marchofyou', '2026-000028-007', 'box', 1.00, 1.00, NULL, 'Fair', 'marchofyoumarchofyoumarchofyou', 'marchofyoumarchofyou', 5, 7, 2, '2026-03-11 00:51:49', '2026-03-11 00:51:49', 'marchofyoumarchofyou', 2313.00, 2, '', 'PPE', 5, 'PPE-20260311-007', 6, NULL),
(39, 'marchofyou', 'marchofyou', '2026-000028-008', 'box', 1.00, 1.00, NULL, 'Fair', 'marchofyoumarchofyoumarchofyou', 'marchofyoumarchofyou', 5, 7, 2, '2026-03-11 00:51:49', '2026-03-11 00:51:49', 'marchofyoumarchofyou', 2313.00, 2, '', 'PPE', 5, 'PPE-20260311-008', 6, NULL),
(40, 'marchofyou', 'marchofyou', '2026-000028-009', 'box', 1.00, 1.00, NULL, 'Fair', 'marchofyoumarchofyoumarchofyou', 'marchofyoumarchofyou', 5, 7, 2, '2026-03-11 00:51:49', '2026-03-11 00:51:49', 'marchofyoumarchofyou', 2313.00, 2, '', 'PPE', 5, 'PPE-20260311-009', 6, NULL),
(41, 'marchofyou', 'marchofyou', '2026-000028-010', 'box', 1.00, 1.00, NULL, 'Fair', 'marchofyoumarchofyoumarchofyou', 'marchofyoumarchofyou', 5, 7, 2, '2026-03-11 00:51:49', '2026-03-11 00:51:49', 'marchofyoumarchofyou', 2313.00, 2, '', 'PPE', 5, 'PPE-20260311-010', 6, NULL),
(42, 'marchofyou', 'marchofyou', '2026-000028-011', 'box', 1.00, 1.00, NULL, 'Fair', 'marchofyoumarchofyoumarchofyou', 'marchofyoumarchofyou', 5, 7, 2, '2026-03-11 00:51:49', '2026-03-11 00:51:49', 'marchofyoumarchofyou', 2313.00, 2, '', 'PPE', 5, 'PPE-20260311-011', 6, NULL),
(43, 'marchofyou', 'marchofyou', '2026-000028-012', 'box', 1.00, 1.00, NULL, 'Fair', 'marchofyoumarchofyoumarchofyou', 'marchofyoumarchofyou', 5, 7, 2, '2026-03-11 00:51:49', '2026-03-11 00:51:49', 'marchofyoumarchofyou', 2313.00, 2, '', 'PPE', 5, 'PPE-20260311-012', 6, NULL),
(44, 'marchsemi', 'marchsemi', '2026-000040', 'box', 1.00, 1.00, NULL, 'For Condemn', 'marchsemimarchsemi', 'marchsemimarchsemi', 8, 7, 4, '2026-03-11 00:57:36', '2026-03-11 00:58:20', 'marchsemimarchsemi', 2.00, 1, '', 'Semi-Expendable', 7, 'SEMI-20260311-1606', 6, NULL),
(45, 'test', 'marchsemi', '2026-000041', 'box', 1.00, 1.00, NULL, 'For Condemn', 'marchsemimarchsemi', 'marchsemimarchsemi', 8, 7, 4, '2026-03-11 01:21:44', '2026-03-11 01:21:44', 'marchsemimarchsemi', 2.00, 2, '', 'Semi-Expendable', 7, 'SEMI-20260311-5343', 6, NULL),
(47, 'ssssssssssssssssssssssss', '', '2026-000042-002', 'unit', 1.00, 1.00, NULL, 'For Condemn', '', 'asdasdasd', NULL, 6, NULL, '2026-03-11 02:46:32', '2026-03-11 02:46:32', 'assadasdasdasdad', 21312321.00, 1, '', 'Semi-Expendable', 5, 'SEMI-20260311-002', 6, NULL),
(48, 'ssssssssssssssssssssssss', '', '2026-000042-003', 'unit', 1.00, 1.00, NULL, 'For Condemn', '', 'asdasdasd', NULL, 6, NULL, '2026-03-11 02:46:32', '2026-03-11 02:46:32', 'assadasdasdasdad', 21312321.00, 1, '', 'Semi-Expendable', 5, 'SEMI-20260311-003', 6, NULL),
(49, 'ssssssssssssssssssssssss', '', '2026-000042-004', 'unit', 1.00, 1.00, NULL, 'For Condemn', '', 'asdasdasd', NULL, 6, NULL, '2026-03-11 02:46:32', '2026-03-11 02:46:32', 'assadasdasdasdad', 21312321.00, 1, '', 'Semi-Expendable', 5, 'SEMI-20260311-004', 6, NULL),
(50, 'ssssssssssssssssssssssss', '', '2026-000042-005', 'unit', 1.00, 1.00, NULL, 'For Condemn', '', 'asdasdasd', NULL, 6, NULL, '2026-03-11 02:46:32', '2026-03-11 02:46:32', 'assadasdasdasdad', 21312321.00, 1, '', 'Semi-Expendable', 5, 'SEMI-20260311-005', 6, NULL),
(51, 'ssssssssssssssssssssssss', '', '2026-000042-006', 'unit', 1.00, 1.00, NULL, 'For Condemn', '', 'asdasdasd', NULL, 6, NULL, '2026-03-11 02:46:32', '2026-03-11 02:46:32', 'assadasdasdasdad', 21312321.00, 1, '', 'Semi-Expendable', 5, 'SEMI-20260311-006', 6, NULL),
(52, 'ssssssssssssssssssssssss', '', '2026-000042-007', 'unit', 1.00, 1.00, NULL, 'For Condemn', '', 'asdasdasd', NULL, 6, NULL, '2026-03-11 02:46:32', '2026-03-11 02:46:32', 'assadasdasdasdad', 21312321.00, 1, '', 'Semi-Expendable', 5, 'SEMI-20260311-007', 6, NULL),
(53, 'ssssssssssssssssssssssss', '', '2026-000042-008', 'unit', 1.00, 1.00, NULL, 'For Condemn', '', 'asdasdasd', NULL, 6, NULL, '2026-03-11 02:46:32', '2026-03-11 02:46:32', 'assadasdasdasdad', 21312321.00, 1, '', 'Semi-Expendable', 5, 'SEMI-20260311-008', 6, NULL),
(54, 'ssssssssssssssssssssssss', '', '2026-000042-009', 'unit', 1.00, 1.00, NULL, 'For Condemn', '', 'asdasdasd', NULL, 6, NULL, '2026-03-11 02:46:32', '2026-03-11 02:46:32', 'assadasdasdasdad', 21312321.00, 1, '', 'Semi-Expendable', 5, 'SEMI-20260311-009', 6, NULL),
(55, 'ssssssssssssssssssssssss', '', '2026-000042-010', 'unit', 1.00, 1.00, NULL, 'For Condemn', '', 'asdasdasd', NULL, 6, NULL, '2026-03-11 02:46:32', '2026-03-11 02:46:32', 'assadasdasdasdad', 21312321.00, 1, '', 'Semi-Expendable', 5, 'SEMI-20260311-010', 6, NULL),
(56, 'ssssssssssssssssssssssss', '', '2026-000042-011', 'unit', 1.00, 1.00, NULL, 'For Condemn', '', 'asdasdasd', NULL, 6, NULL, '2026-03-11 02:46:32', '2026-03-11 02:46:32', 'assadasdasdasdad', 21312321.00, 1, '', 'Semi-Expendable', 5, 'SEMI-20260311-011', 6, NULL),
(57, 'ssssssssssssssssssssssss', '', '2026-000042-012', 'unit', 1.00, 1.00, NULL, 'For Condemn', '', 'asdasdasd', NULL, 6, NULL, '2026-03-11 02:46:32', '2026-03-11 02:46:32', 'assadasdasdasdad', 21312321.00, 1, '', 'Semi-Expendable', 5, 'SEMI-20260311-012', 6, NULL),
(62, 'oneitemto', 'oneitemto', '2026-000053', 'box', 1.00, 1.00, NULL, 'For Condemn', 'oneitemto', 'oneitemto', 5, 7, 4, '2026-03-11 03:12:22', '2026-03-11 03:12:22', '', 123213.00, 1, '', 'Semi-Expendable', 7, 'SEMI-20260311-5640', 6, NULL),
(63, 'sevenitemto', 'sevenitemto', '2026-000054-001', 'pcs', 1.00, 1.00, NULL, 'For Condemn', '', 'sevenitemto', 5, 7, 4, '2026-03-11 03:13:04', '2026-03-11 03:13:04', '', 213213213.00, 3, '', 'Semi-Expendable', 7, 'SEMI-20260311-001', 6, NULL),
(64, 'sevenitemto', 'sevenitemto', '2026-000054-002', 'pcs', 1.00, 1.00, NULL, 'For Condemn', '', 'sevenitemto', 5, 7, 4, '2026-03-11 03:13:04', '2026-03-11 03:13:04', '', 213213213.00, 3, '', 'Semi-Expendable', 7, 'SEMI-20260311-002', 6, NULL),
(65, 'sevenitemto', 'sevenitemto', '2026-000054-003', 'pcs', 1.00, 1.00, NULL, 'For Condemn', '', 'sevenitemto', 5, 7, 4, '2026-03-11 03:13:04', '2026-03-11 03:13:04', '', 213213213.00, 3, '', 'Semi-Expendable', 7, 'SEMI-20260311-003', 6, NULL),
(66, 'sevenitemto', 'sevenitemto', '2026-000054-004', 'pcs', 1.00, 1.00, NULL, 'For Condemn', '', 'sevenitemto', 5, 7, 4, '2026-03-11 03:13:04', '2026-03-11 03:13:04', '', 213213213.00, 3, '', 'Semi-Expendable', 7, 'SEMI-20260311-004', 6, NULL),
(67, 'sevenitemto', 'sevenitemto', '2026-000054-005', 'pcs', 1.00, 1.00, NULL, 'For Condemn', '', 'sevenitemto', 5, 7, 4, '2026-03-11 03:13:04', '2026-03-11 03:13:04', '', 213213213.00, 3, '', 'Semi-Expendable', 7, 'SEMI-20260311-005', 6, NULL),
(68, 'sevenitemto', 'sevenitemto', '2026-000054-006', 'pcs', 1.00, 1.00, NULL, 'For Condemn', '', 'sevenitemto', 5, 7, 4, '2026-03-11 03:13:04', '2026-03-11 03:13:04', '', 213213213.00, 3, '', 'Semi-Expendable', 7, 'SEMI-20260311-006', 6, NULL),
(69, 'sevenitemto', 'sevenitemto', '2026-000054-007', 'pcs', 1.00, 1.00, NULL, 'For Condemn', '', 'sevenitemto', 5, 7, 4, '2026-03-11 03:13:04', '2026-03-11 03:13:04', '', 213213213.00, 3, '', 'Semi-Expendable', 7, 'SEMI-20260311-007', 6, NULL),
(70, 'isaPPE', 'isaPPE', '2026-000061', 'unit', 1.00, 1.00, NULL, 'Good', 'isaPPEisaPPEisaPPE', 'asdasdasd', 6, 5, 4, '2026-03-11 03:19:56', '2026-03-11 03:19:56', 'isaPPEisaPPE', 23213213.00, 1, '', 'PPE', 5, 'PPE-20260311-4823', 6, NULL),
(71, 'sevenaPPE', 'sevenaPPE', '2026-000062-001', 'unit', 1.00, 1.00, NULL, 'Fair', 'sevenaPPE', 'sevenaPPE', 5, 7, 6, '2026-03-11 03:20:36', '2026-03-11 03:20:36', 'sevenaPPE', 4214324.00, 2, '', 'PPE', 8, 'PPE-20260311-001', 6, NULL),
(72, 'sevenaPPE', 'sevenaPPE', '2026-000062-002', 'unit', 1.00, 1.00, NULL, 'Fair', 'sevenaPPE', 'sevenaPPE', 5, 7, 6, '2026-03-11 03:20:36', '2026-03-11 03:20:36', 'sevenaPPE', 4214324.00, 2, '', 'PPE', 8, 'PPE-20260311-002', 6, NULL),
(73, 'sevenaPPE', 'sevenaPPE', '2026-000062-003', 'unit', 1.00, 1.00, NULL, 'Fair', 'sevenaPPE', 'sevenaPPE', 5, 7, 6, '2026-03-11 03:20:36', '2026-03-11 03:20:36', 'sevenaPPE', 4214324.00, 2, '', 'PPE', 8, 'PPE-20260311-003', 6, NULL),
(74, 'sevenaPPE', 'sevenaPPE', '2026-000062-004', 'unit', 1.00, 1.00, NULL, 'Fair', 'sevenaPPE', 'sevenaPPE', 5, 7, 6, '2026-03-11 03:20:36', '2026-03-11 03:20:36', 'sevenaPPE', 4214324.00, 2, '', 'PPE', 8, 'PPE-20260311-004', 6, NULL),
(75, 'sevenaPPE', 'sevenaPPE', '2026-000062-005', 'unit', 1.00, 1.00, NULL, 'Fair', 'sevenaPPE', 'sevenaPPE', 5, 7, 6, '2026-03-11 03:20:36', '2026-03-11 03:20:36', 'sevenaPPE', 4214324.00, 2, '', 'PPE', 8, 'PPE-20260311-005', 6, NULL),
(76, 'sevenaPPE', 'sevenaPPE', '2026-000062-006', 'unit', 1.00, 1.00, NULL, 'Fair', 'sevenaPPE', 'sevenaPPE', 5, 7, 6, '2026-03-11 03:20:36', '2026-03-11 03:20:36', 'sevenaPPE', 4214324.00, 2, '', 'PPE', 8, 'PPE-20260311-006', 6, NULL),
(77, 'sevenaPPE', 'sevenaPPE', '2026-000062-007', 'unit', 1.00, 1.00, NULL, 'Fair', 'sevenaPPE', 'sevenaPPE', 5, 7, 6, '2026-03-11 03:20:36', '2026-03-11 03:20:36', 'sevenaPPE', 4214324.00, 2, '', 'PPE', 8, 'PPE-20260311-007', 6, NULL),
(78, 'tatlo', 'tatlo', '2026-000068-001', 'box', 1.00, 1.00, NULL, 'Fair', '', 'tatlo', 8, 5, 5, '2026-03-11 03:34:57', '2026-03-11 03:34:57', 'tatlo', 2131231.00, 3, 'asdasdasdasda', 'PPE', 7, 'PPE-20260311-001', 6, NULL),
(79, 'tatlo', 'tatlo', '2026-000068-002', 'box', 1.00, 1.00, NULL, 'Fair', '', 'tatlo', 8, 5, 5, '2026-03-11 03:34:57', '2026-03-11 03:34:57', 'tatlo', 2131231.00, 3, 'asdasdasdasda', 'PPE', 7, 'PPE-20260311-002', 6, NULL),
(80, 'tatlo', 'tatlo', '2026-000068-003', 'box', 1.00, 1.00, NULL, 'Fair', '', 'tatlo', 8, 5, 5, '2026-03-11 03:34:57', '2026-03-11 03:34:57', 'tatlo', 2131231.00, 3, 'asdasdasdasda', 'PPE', 7, 'PPE-20260311-003', 6, NULL),
(81, 'nineitemto', 'nineitemto', '2026-000071-001', 'pcs', 1.00, 1.00, NULL, 'Under Repair', 'nineitemtonineitemtonineitemtonineitemto', 'nineitemto', 6, 5, 3, '2026-03-11 03:39:17', '2026-03-11 03:39:17', 'nineitemto', 234123213.00, 1, '', 'Semi-Expendable', 7, 'SEMI-20260311-001', 6, NULL),
(82, 'nineitemto', 'nineitemto', '2026-000071-002', 'pcs', 1.00, 1.00, NULL, 'Under Repair', 'nineitemtonineitemtonineitemtonineitemto', 'nineitemto', 6, 5, 3, '2026-03-11 03:39:17', '2026-03-11 03:39:17', 'nineitemto', 234123213.00, 1, '', 'Semi-Expendable', 7, 'SEMI-20260311-002', 6, NULL),
(83, 'nineitemto', 'nineitemto', '2026-000071-003', 'pcs', 1.00, 1.00, NULL, 'Under Repair', 'nineitemtonineitemtonineitemtonineitemto', 'nineitemto', 6, 5, 3, '2026-03-11 03:39:17', '2026-03-11 03:39:17', 'nineitemto', 234123213.00, 1, '', 'Semi-Expendable', 7, 'SEMI-20260311-003', 6, NULL),
(84, 'nineitemto', 'nineitemto', '2026-000071-004', 'pcs', 1.00, 1.00, NULL, 'Under Repair', 'nineitemtonineitemtonineitemtonineitemto', 'nineitemto', 6, 5, 3, '2026-03-11 03:39:17', '2026-03-11 03:39:17', 'nineitemto', 234123213.00, 1, '', 'Semi-Expendable', 7, 'SEMI-20260311-004', 6, NULL),
(86, 'nineitemto', 'nineitemto', '2026-000071-006', 'pcs', 1.00, 1.00, NULL, 'Under Repair', 'nineitemtonineitemtonineitemtonineitemto', 'nineitemto', 6, 5, 3, '2026-03-11 03:39:17', '2026-03-11 03:39:17', 'nineitemto', 234123213.00, 1, '', 'Semi-Expendable', 7, 'SEMI-20260311-006', 6, NULL),
(87, 'nineitemto', 'nineitemto', '2026-000071-007', 'pcs', 1.00, 1.00, NULL, 'Under Repair', 'nineitemtonineitemtonineitemtonineitemto', 'nineitemto', 6, 5, 3, '2026-03-11 03:39:17', '2026-03-11 03:39:17', 'nineitemto', 234123213.00, 1, '', 'Semi-Expendable', 7, 'SEMI-20260311-007', 6, NULL),
(88, 'nineitemto', 'nineitemto', '2026-000071-008', 'pcs', 1.00, 1.00, NULL, 'Under Repair', 'nineitemtonineitemtonineitemtonineitemto', 'nineitemto', 6, 5, 3, '2026-03-11 03:39:17', '2026-03-11 03:39:17', 'nineitemto', 234123213.00, 1, '', 'Semi-Expendable', 7, 'SEMI-20260311-008', 6, NULL),
(89, 'nineitemto', 'nineitemto', '2026-000071-009', 'pcs', 1.00, 1.00, NULL, 'Under Repair', 'nineitemtonineitemtonineitemtonineitemto', 'nineitemto', 6, 5, 3, '2026-03-11 03:39:17', '2026-03-11 03:39:17', 'nineitemto', 234123213.00, 1, '', 'Semi-Expendable', 7, 'SEMI-20260311-009', 6, NULL),
(90, 'oneone', 'oneone', '2026-000080', 'unit', 1.00, 1.00, NULL, 'For Condemn', 'oneoneoneone', 'oneone', 5, 5, NULL, '2026-03-11 03:39:58', '2026-03-11 03:39:58', 'oneone', 2132132.00, 1, '', 'Semi-Expendable', 5, 'SEMI-20260311-9678', 6, NULL),
(91, 'dsadsadsadsadsadsadsadassad', 'dsadsadsadsadsadsadsadassad', '2026-000081', 'box', 1.00, 1.00, NULL, 'Fair', 'dsadsadsadsadsadsadsadassad', 'dsadsadsadsadsadsadsadassad', 5, 8, 5, '2026-03-11 03:41:58', '2026-03-11 03:41:58', 'dsadsadsadsadsadsadsadassad', 2321321321.00, 1, '', 'PPE', 5, 'PPE-20260311-7300', 6, NULL),
(92, 'twenlve na barcode', 'twenlve na barcode', '2026-000082-1', '0', 1.00, 1.00, NULL, 'Poor', '0', 'twenlve na barcodetwenlve na barcode', 8, 5, 4, '2026-03-11 04:58:58', '2026-03-11 04:58:58', 'twenlve na barcodetwenlve na barcode', 213123.00, 2, '', 'Medical Equipment', 8, 'INV202603119252001', 6, NULL),
(93, 'onehundertre', 'onehundertre', '2026-000083-1', '0', 1.00, 1.00, NULL, 'Good', '0', 'onehundertreonehundertre', 5, 8, 5, '2026-03-11 05:08:08', '2026-03-11 05:08:08', 'onehundertreonehundertre', 123123.00, 4, '', 'PPE', 5, 'INV202603114955001', 6, NULL),
(94, 'sanagumanakanaboss', 'sanagumanakanaboss', '2026-000084-1', '0', 1.00, 1.00, NULL, 'Poor', '0', 'sanagumanakanaboss', 5, 8, 4, '2026-03-11 05:10:44', '2026-03-11 05:10:44', 'sanagumanakanaboss', 23.00, 2, 'sanagumanakanaboss', 'Office Supplies', 8, 'INV202603119740001', 6, NULL),
(95, 'sanagumanakanaboss', 'sanagumanakanaboss', '2026-000084-2', '0', 1.00, 1.00, NULL, 'Poor', '0', 'sanagumanakanaboss', 5, 8, 4, '2026-03-11 05:10:44', '2026-03-11 05:10:44', 'sanagumanakanaboss', 23.00, 2, 'sanagumanakanaboss', 'Office Supplies', 8, 'INV202603112757002', 6, NULL),
(96, 'sanagumanakanaboss', 'sanagumanakanaboss', '2026-000084-3', '0', 1.00, 1.00, NULL, 'Poor', '0', 'sanagumanakanaboss', 5, 8, 4, '2026-03-11 05:10:44', '2026-03-11 05:10:44', 'sanagumanakanaboss', 23.00, 2, 'sanagumanakanaboss', 'Office Supplies', 8, 'INV202603116211003', 6, NULL),
(97, 'sanagumanakanaboss', 'sanagumanakanaboss', '2026-000084-4', '0', 1.00, 1.00, NULL, 'Poor', '0', 'sanagumanakanaboss', 5, 8, 4, '2026-03-11 05:10:44', '2026-03-11 05:10:44', 'sanagumanakanaboss', 23.00, 2, 'sanagumanakanaboss', 'Office Supplies', 8, 'INV202603112431004', 6, NULL),
(98, 'sanagumanakanaboss', 'sanagumanakanaboss', '2026-000084-5', '0', 1.00, 1.00, NULL, 'Poor', '0', 'sanagumanakanaboss', 5, 8, 4, '2026-03-11 05:10:44', '2026-03-11 05:10:44', 'sanagumanakanaboss', 23.00, 2, 'sanagumanakanaboss', 'Office Supplies', 8, 'INV202603113401005', 6, NULL),
(99, 'sanagumanakanaboss', 'sanagumanakanaboss', '2026-000084-6', '0', 1.00, 1.00, NULL, 'Poor', '0', 'sanagumanakanaboss', 5, 8, 4, '2026-03-11 05:10:44', '2026-03-11 05:10:44', 'sanagumanakanaboss', 23.00, 2, 'sanagumanakanaboss', 'Office Supplies', 8, 'INV202603111837006', 6, NULL),
(100, 'sanagumanakanaboss', 'sanagumanakanaboss', '2026-000084-7', '0', 1.00, 1.00, NULL, 'Poor', '0', 'sanagumanakanaboss', 5, 8, 4, '2026-03-11 05:10:44', '2026-03-11 05:10:44', 'sanagumanakanaboss', 23.00, 2, 'sanagumanakanaboss', 'Office Supplies', 8, 'INV202603119507007', 6, NULL),
(101, 'sanagumanakanaboss', 'sanagumanakanaboss', '2026-000084-8', '0', 1.00, 1.00, NULL, 'Poor', '0', 'sanagumanakanaboss', 5, 8, 4, '2026-03-11 05:10:44', '2026-03-11 05:10:44', 'sanagumanakanaboss', 23.00, 2, 'sanagumanakanaboss', 'Office Supplies', 8, 'INV202603118090008', 6, NULL),
(102, 'sanagumanakanaboss', 'sanagumanakanaboss', '2026-000084-9', '0', 1.00, 1.00, NULL, 'Poor', '0', 'sanagumanakanaboss', 5, 8, 4, '2026-03-11 05:10:44', '2026-03-11 05:10:44', 'sanagumanakanaboss', 23.00, 2, 'sanagumanakanaboss', 'Office Supplies', 8, 'INV202603117223009', 6, NULL),
(103, 'sanagumanakanaboss', 'sanagumanakanaboss', '2026-000084-10', '0', 1.00, 1.00, NULL, 'Poor', '0', 'sanagumanakanaboss', 5, 8, 4, '2026-03-11 05:10:44', '2026-03-11 05:10:44', 'sanagumanakanaboss', 23.00, 2, 'sanagumanakanaboss', 'Office Supplies', 8, 'INV202603113553010', 6, NULL),
(104, 'sanagumanakanaboss', 'sanagumanakanaboss', '2026-000084-11', '0', 1.00, 1.00, NULL, 'Poor', '0', 'sanagumanakanaboss', 5, 8, 4, '2026-03-11 05:10:44', '2026-03-11 05:10:44', 'sanagumanakanaboss', 23.00, 2, 'sanagumanakanaboss', 'Office Supplies', 8, 'INV202603115283011', 6, NULL),
(105, 'sanagumanakanaboss', 'sanagumanakanaboss', '2026-000084-12', '0', 1.00, 1.00, NULL, 'Poor', '0', 'sanagumanakanaboss', 5, 8, 4, '2026-03-11 05:10:44', '2026-03-11 05:10:44', 'sanagumanakanaboss', 23.00, 2, 'sanagumanakanaboss', 'Office Supplies', 8, 'INV202603112297012', 6, NULL),
(106, 'isalangtoboss', 'isalangtoboss', '2026-000096', '0', 1.00, 1.00, NULL, 'Poor', 'isalangtoboss', NULL, NULL, NULL, 1, '2026-03-11 05:19:56', NULL, 'isalangtoboss', 2312.00, 2, '', 'Semi-Expendable', NULL, 'INV202603114404', 6, NULL),
(111, 'test', 'testtesttesttesttesttesttest', '2026-000097-005', 'box', 1.00, 1.00, NULL, 'Serviceable', 'dfhexdtjrsz', 'marchsemimarchsemi', 7, 7, 1, '2026-03-11 07:27:33', '2026-03-11 07:27:33', '', 12.00, 2, 'medical', 'Semi-Expendable', 7, 'SEMI-20260311-005', 6, NULL),
(112, 'test', 'testtesttesttesttesttesttest', '2026-000097-006', 'box', 1.00, 1.00, NULL, 'Serviceable', 'dfhexdtjrsz', 'marchsemimarchsemi', 7, 7, 1, '2026-03-11 07:27:33', '2026-03-11 07:27:33', '', 12.00, 2, 'medical', 'Semi-Expendable', 7, 'SEMI-20260311-006', 6, NULL),
(113, 'test', 'testtesttesttesttesttesttest', '2026-000097-007', 'box', 1.00, 1.00, NULL, 'Serviceable', 'dfhexdtjrsz', 'marchsemimarchsemi', 7, 7, 1, '2026-03-11 07:27:33', '2026-03-11 07:27:33', '', 12.00, 2, 'medical', 'Semi-Expendable', 7, 'SEMI-20260311-007', 6, NULL),
(114, 'test', 'testtesttesttesttesttesttest', '2026-000097-008', 'box', 1.00, 1.00, NULL, 'Serviceable', 'dfhexdtjrsz', 'marchsemimarchsemi', 7, 7, 1, '2026-03-11 07:27:33', '2026-03-11 07:27:33', '', 12.00, 2, 'medical', 'Semi-Expendable', 7, 'SEMI-20260311-008', 6, NULL),
(115, 'test', 'testtesttesttesttesttesttest', '2026-000097-009', 'box', 1.00, 1.00, NULL, 'Serviceable', 'dfhexdtjrsz', 'marchsemimarchsemi', 7, 7, 1, '2026-03-11 07:27:33', '2026-03-11 07:27:33', '', 12.00, 2, 'medical', 'Semi-Expendable', 7, 'SEMI-20260311-009', 6, NULL),
(116, 'test', 'testtesttesttesttesttesttest', '2026-000097-010', 'box', 1.00, 1.00, NULL, 'Serviceable', 'dfhexdtjrsz', 'marchsemimarchsemi', 7, 7, 1, '2026-03-11 07:27:33', '2026-03-11 07:27:33', '', 12.00, 2, 'medical', 'Semi-Expendable', 7, 'SEMI-20260311-010', 6, NULL),
(117, 'test', 'testtesttesttesttesttesttest', '2026-000097-011', 'box', 1.00, 1.00, NULL, 'Serviceable', 'dfhexdtjrsz', 'marchsemimarchsemi', 7, 7, 1, '2026-03-11 07:27:33', '2026-03-11 07:27:33', '', 12.00, 2, 'medical', 'Semi-Expendable', 7, 'SEMI-20260311-011', 6, NULL),
(118, 'test', 'testtesttesttesttesttesttest', '2026-000097-012', 'box', 1.00, 1.00, NULL, 'Serviceable', 'dfhexdtjrsz', 'marchsemimarchsemi', 7, 7, 1, '2026-03-11 07:27:33', '2026-03-11 07:27:33', '', 12.00, 2, 'medical', 'Semi-Expendable', 7, 'SEMI-20260311-012', 6, NULL),
(121, 'examplequt', 'examplequt', '2026-000109-003', 'unit', 12.00, 12.00, NULL, 'Non-Serviceable', 'examplequtexamplequt', 'examplequt', 5, 5, 1, '2026-03-11 08:02:32', '2026-03-11 08:02:32', 'examplequtexamplequt', 23.00, 1, '', 'Semi-Expendable', 8, 'SEMI-20260311-003', 6, NULL),
(122, 'examplequt', 'examplequt', '2026-000109-004', 'unit', 12.00, 12.00, NULL, 'Non-Serviceable', 'examplequtexamplequt', 'examplequt', 5, 5, 1, '2026-03-11 08:02:32', '2026-03-11 08:02:32', 'examplequtexamplequt', 23.00, 1, '', 'Semi-Expendable', 8, 'SEMI-20260311-004', 6, NULL),
(123, 'examplequt', 'examplequt', '2026-000109-005', 'unit', 12.00, 12.00, NULL, 'Non-Serviceable', 'examplequtexamplequt', 'examplequt', 5, 5, 1, '2026-03-11 08:02:32', '2026-03-11 08:02:32', 'examplequtexamplequt', 23.00, 1, '', 'Semi-Expendable', 8, 'SEMI-20260311-005', 6, NULL),
(124, 'examplequt', 'examplequt', '2026-000109-006', 'unit', 12.00, 12.00, NULL, 'Non-Serviceable', 'examplequtexamplequt', 'examplequt', 5, 5, 1, '2026-03-11 08:02:32', '2026-03-11 08:02:32', 'examplequtexamplequt', 23.00, 1, '', 'Semi-Expendable', 8, 'SEMI-20260311-006', 6, NULL),
(125, 'examplequt', 'examplequt', '2026-000109-007', 'unit', 12.00, 12.00, NULL, 'Non-Serviceable', 'examplequtexamplequt', 'examplequt', 5, 5, 1, '2026-03-11 08:02:32', '2026-03-11 08:02:32', 'examplequtexamplequt', 23.00, 1, '', 'Semi-Expendable', 8, 'SEMI-20260311-007', 6, NULL),
(126, 'examplequt', 'examplequt', '2026-000109-008', 'unit', 12.00, 12.00, NULL, 'Non-Serviceable', 'examplequtexamplequt', 'examplequt', 5, 5, 1, '2026-03-11 08:02:32', '2026-03-11 08:02:32', 'examplequtexamplequt', 23.00, 1, '', 'Semi-Expendable', 8, 'SEMI-20260311-008', 6, NULL),
(127, 'examplequt', 'examplequt', '2026-000109-009', 'unit', 12.00, 12.00, NULL, 'Non-Serviceable', 'examplequtexamplequt', 'examplequt', 5, 5, 1, '2026-03-11 08:02:32', '2026-03-11 08:02:32', 'examplequtexamplequt', 23.00, 1, '', 'Semi-Expendable', 8, 'SEMI-20260311-009', 6, NULL),
(128, 'examplequt', 'examplequt', '2026-000109-010', 'unit', 12.00, 12.00, NULL, 'Non-Serviceable', 'examplequtexamplequt', 'examplequt', 5, 5, 1, '2026-03-11 08:02:32', '2026-03-11 08:02:32', 'examplequtexamplequt', 23.00, 1, '', 'Semi-Expendable', 8, 'SEMI-20260311-010', 6, NULL),
(129, 'examplequt', 'examplequt', '2026-000109-011', 'unit', 12.00, 12.00, NULL, 'Non-Serviceable', 'examplequtexamplequt', 'examplequt', 5, 5, 1, '2026-03-11 08:02:32', '2026-03-11 08:02:32', 'examplequtexamplequt', 23.00, 1, '', 'Semi-Expendable', 8, 'SEMI-20260311-011', 6, NULL),
(130, 'examplequt', 'examplequt', '2026-000109-012', 'unit', 12.00, 12.00, NULL, 'Non-Serviceable', 'examplequtexamplequt', 'examplequt', 5, 5, 1, '2026-03-11 08:02:32', '2026-03-11 08:02:32', 'examplequtexamplequt', 23.00, 1, '', 'Semi-Expendable', 8, 'SEMI-20260311-012', 6, NULL),
(131, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-001', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-001', 6, NULL),
(132, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-002', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-002', 6, NULL),
(133, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-003', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-003', 6, NULL),
(134, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-004', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-004', 6, NULL),
(135, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-005', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-005', 6, NULL),
(136, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-006', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-006', 6, NULL),
(137, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-007', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-007', 6, NULL),
(138, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-008', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-008', 6, NULL),
(139, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-009', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-009', 6, NULL),
(140, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-010', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-010', 6, NULL),
(141, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-011', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-011', 6, NULL),
(142, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-012', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-012', 6, NULL),
(143, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-013', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-013', 6, NULL),
(144, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-014', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-014', 6, NULL),
(145, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-015', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-015', 6, NULL),
(146, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-016', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-016', 6, NULL),
(147, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-017', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-017', 6, NULL),
(148, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-018', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-018', 6, NULL),
(149, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-019', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-019', 6, NULL),
(150, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-020', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-020', 6, NULL),
(151, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-021', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-021', 6, NULL),
(152, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-022', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-022', 6, NULL),
(153, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-023', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-023', 6, NULL),
(154, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-024', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-024', 6, NULL),
(155, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-025', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-025', 6, NULL),
(156, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-026', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-026', 6, NULL),
(157, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-027', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-027', 6, NULL),
(158, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-028', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-028', 6, NULL),
(159, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-029', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-029', 6, NULL),
(160, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-030', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-030', 6, NULL),
(161, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-031', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-031', 6, NULL),
(162, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-032', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-032', 6, NULL),
(163, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-033', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-033', 6, NULL),
(164, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-034', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-034', 6, NULL),
(165, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-035', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-035', 6, NULL),
(166, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-036', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-036', 6, NULL),
(167, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-037', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-037', 6, NULL),
(168, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-038', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-038', 6, NULL),
(169, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-039', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-039', 6, NULL),
(170, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-040', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-040', 6, NULL),
(171, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-041', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-041', 6, NULL),
(172, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-042', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-042', 6, NULL),
(173, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-043', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-043', 6, NULL),
(174, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-044', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-044', 6, NULL),
(175, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-045', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-045', 6, NULL),
(176, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-046', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-046', 6, NULL),
(177, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-047', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-047', 6, NULL),
(178, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-048', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-048', 6, NULL),
(179, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-049', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-049', 6, NULL),
(180, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-050', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-050', 6, NULL),
(181, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-051', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-051', 6, NULL),
(182, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-052', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-052', 6, NULL),
(183, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-053', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-053', 6, NULL),
(184, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-054', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-054', 6, NULL),
(185, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-055', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-055', 6, NULL),
(186, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-056', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-056', 6, NULL),
(187, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-057', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-057', 6, NULL),
(188, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-058', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-058', 6, NULL),
(189, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-059', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-059', 6, NULL),
(190, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-060', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-060', 6, NULL),
(191, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-061', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-061', 6, NULL),
(192, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-062', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-062', 6, NULL),
(193, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-063', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-063', 6, NULL),
(194, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-064', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-064', 6, NULL),
(195, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-065', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-065', 6, NULL),
(196, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-066', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-066', 6, NULL),
(197, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-067', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-067', 6, NULL),
(198, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-068', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-068', 6, NULL),
(199, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-069', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-069', 6, NULL),
(200, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-070', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-070', 6, NULL);
INSERT INTO `inventory` (`id`, `article_name`, `description`, `property_no`, `uom`, `qty_property_card`, `qty_physical_count`, `location_id`, `condition_text`, `remarks`, `certified_correct`, `approved_by`, `verified_by`, `section_id`, `date_added`, `date_updated`, `fund_cluster`, `unit_value`, `equipment_id`, `type_equipment`, `category`, `allocate_to`, `barcode_data`, `created_by`, `current_holder`) VALUES
(201, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-071', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-071', 6, NULL),
(202, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-072', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-072', 6, NULL),
(203, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-073', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-073', 6, NULL),
(204, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-074', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-074', 6, NULL),
(205, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-075', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-075', 6, NULL),
(206, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-076', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-076', 6, NULL),
(207, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-077', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-077', 6, NULL),
(208, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-078', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-078', 6, NULL),
(209, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-079', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-079', 6, NULL),
(210, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-080', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-080', 6, NULL),
(211, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-081', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-081', 6, NULL),
(212, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-082', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-082', 6, NULL),
(213, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-083', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-083', 6, NULL),
(214, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-084', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-084', 6, NULL),
(215, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-085', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-085', 6, NULL),
(216, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-086', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-086', 6, NULL),
(217, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-087', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-087', 6, NULL),
(218, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-088', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-088', 6, NULL),
(219, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-089', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-089', 6, NULL),
(220, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-090', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-090', 6, NULL),
(221, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-091', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-091', 6, NULL),
(222, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-092', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-092', 6, NULL),
(223, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-093', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-093', 6, NULL),
(224, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-094', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-094', 6, NULL),
(225, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-095', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-095', 6, NULL),
(226, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-096', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-096', 6, NULL),
(227, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-097', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-097', 6, NULL),
(228, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-098', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-098', 6, NULL),
(229, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-099', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-099', 6, NULL),
(230, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-100', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-100', 6, NULL),
(231, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-101', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-101', 6, NULL),
(232, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-102', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-102', 6, NULL),
(233, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-103', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-103', 6, NULL),
(234, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-104', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-104', 6, NULL),
(235, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-105', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-105', 6, NULL),
(236, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-106', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-106', 6, NULL),
(237, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-107', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-107', 6, NULL),
(238, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-108', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-108', 6, NULL),
(239, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-109', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-109', 6, NULL),
(240, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-110', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-110', 6, NULL),
(241, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-111', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-111', 6, NULL),
(242, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-112', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-112', 6, NULL),
(243, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-113', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-113', 6, NULL),
(244, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-114', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-114', 6, NULL),
(245, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-115', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-115', 6, NULL),
(246, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-116', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-116', 6, NULL),
(247, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-117', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-117', 6, NULL),
(248, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-118', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-118', 6, NULL),
(249, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-119', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-119', 6, NULL),
(250, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-120', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-120', 6, NULL),
(251, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-121', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-121', 6, NULL),
(252, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-122', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-122', 6, NULL),
(253, 'ppetests', 'ppetestsppetestsppetests', '2026-000121-123', 'unit', 123.00, 123.00, NULL, 'Poor', 'ppetestsppetestsppetests', 'ppetests', 5, 7, 1, '2026-03-11 08:13:31', '2026-03-11 08:13:31', 'ppetestsppetestsppetests', 23.00, 1, '', 'PPE', 7, 'PPE-20260311-123', 6, NULL),
(257, 'april', 'aprilapril', '2026-000246-001', 'pcs', 25.00, 24.00, NULL, 'Good', 'odes to be generated:\\r\\n\\r\\n#1:\\r\\nPPE-20260407-001\\r\\n\\r\\n#2:\\r\\nPPE-20260407-002\\r\\n\\r\\n#3:\\r\\nPPE-20260407-003\\r\\n\\r\\n#4:\\r\\nPPE-20260407-004\\r\\n\\r\\n#5:\\r\\nPPE-20260407-005\\r\\n\\r\\n#6:\\r\\nPPE-20260407-006\\r\\n\\r\\n#7:\\r\\nPPE-20260407-007\\r\\n\\r\\n#8:\\r\\nPPE-20260407-008\\r\\n\\r\\n#9:\\r\\nPPE-20260407-009\\r\\n\\r\\n#10:\\r\\nPPE-20260407-010\\r\\n\\r\\n... and 15 more items', 'asdasdasd', 6, 6, 4, '2026-04-07 00:08:51', '2026-04-07 00:08:51', 'example', 12.00, 3, 'example', 'PPE', 7, 'PPE-20260407-001', 6, NULL),
(258, 'april', 'aprilapril', '2026-000246-002', 'pcs', 25.00, 25.00, NULL, 'Good', 'odes to be generated:\\r\\n\\r\\n#1:\\r\\nPPE-20260407-001\\r\\n\\r\\n#2:\\r\\nPPE-20260407-002\\r\\n\\r\\n#3:\\r\\nPPE-20260407-003\\r\\n\\r\\n#4:\\r\\nPPE-20260407-004\\r\\n\\r\\n#5:\\r\\nPPE-20260407-005\\r\\n\\r\\n#6:\\r\\nPPE-20260407-006\\r\\n\\r\\n#7:\\r\\nPPE-20260407-007\\r\\n\\r\\n#8:\\r\\nPPE-20260407-008\\r\\n\\r\\n#9:\\r\\nPPE-20260407-009\\r\\n\\r\\n#10:\\r\\nPPE-20260407-010\\r\\n\\r\\n... and 15 more items', 'asdasdasd', 6, 6, 4, '2026-04-07 00:08:51', '2026-04-07 00:08:51', 'example', 12.00, 3, 'example', 'PPE', 7, 'PPE-20260407-002', 6, NULL),
(259, 'april', 'aprilapril', '2026-000246-003', 'pcs', 25.00, 25.00, NULL, 'Good', 'odes to be generated:\\r\\n\\r\\n#1:\\r\\nPPE-20260407-001\\r\\n\\r\\n#2:\\r\\nPPE-20260407-002\\r\\n\\r\\n#3:\\r\\nPPE-20260407-003\\r\\n\\r\\n#4:\\r\\nPPE-20260407-004\\r\\n\\r\\n#5:\\r\\nPPE-20260407-005\\r\\n\\r\\n#6:\\r\\nPPE-20260407-006\\r\\n\\r\\n#7:\\r\\nPPE-20260407-007\\r\\n\\r\\n#8:\\r\\nPPE-20260407-008\\r\\n\\r\\n#9:\\r\\nPPE-20260407-009\\r\\n\\r\\n#10:\\r\\nPPE-20260407-010\\r\\n\\r\\n... and 15 more items', 'asdasdasd', 6, 6, 4, '2026-04-07 00:08:51', '2026-04-07 00:08:51', 'example', 12.00, 3, 'example', 'PPE', 7, 'PPE-20260407-003', 6, NULL),
(260, 'april', 'aprilapril', '2026-000246-004', 'pcs', 25.00, 25.00, NULL, 'Good', 'odes to be generated:\\r\\n\\r\\n#1:\\r\\nPPE-20260407-001\\r\\n\\r\\n#2:\\r\\nPPE-20260407-002\\r\\n\\r\\n#3:\\r\\nPPE-20260407-003\\r\\n\\r\\n#4:\\r\\nPPE-20260407-004\\r\\n\\r\\n#5:\\r\\nPPE-20260407-005\\r\\n\\r\\n#6:\\r\\nPPE-20260407-006\\r\\n\\r\\n#7:\\r\\nPPE-20260407-007\\r\\n\\r\\n#8:\\r\\nPPE-20260407-008\\r\\n\\r\\n#9:\\r\\nPPE-20260407-009\\r\\n\\r\\n#10:\\r\\nPPE-20260407-010\\r\\n\\r\\n... and 15 more items', 'asdasdasd', 6, 6, 4, '2026-04-07 00:08:51', '2026-04-07 00:08:51', 'example', 12.00, 3, 'example', 'PPE', 7, 'PPE-20260407-004', 6, NULL),
(261, 'april', 'aprilapril', '2026-000246-005', 'pcs', 25.00, 25.00, NULL, 'Good', 'odes to be generated:\\r\\n\\r\\n#1:\\r\\nPPE-20260407-001\\r\\n\\r\\n#2:\\r\\nPPE-20260407-002\\r\\n\\r\\n#3:\\r\\nPPE-20260407-003\\r\\n\\r\\n#4:\\r\\nPPE-20260407-004\\r\\n\\r\\n#5:\\r\\nPPE-20260407-005\\r\\n\\r\\n#6:\\r\\nPPE-20260407-006\\r\\n\\r\\n#7:\\r\\nPPE-20260407-007\\r\\n\\r\\n#8:\\r\\nPPE-20260407-008\\r\\n\\r\\n#9:\\r\\nPPE-20260407-009\\r\\n\\r\\n#10:\\r\\nPPE-20260407-010\\r\\n\\r\\n... and 15 more items', 'asdasdasd', 6, 6, 4, '2026-04-07 00:08:51', '2026-04-07 00:08:51', 'example', 12.00, 3, 'example', 'PPE', 7, 'PPE-20260407-005', 6, NULL),
(262, 'april', 'aprilapril', '2026-000246-006', 'pcs', 25.00, 25.00, NULL, 'Good', 'odes to be generated:\\r\\n\\r\\n#1:\\r\\nPPE-20260407-001\\r\\n\\r\\n#2:\\r\\nPPE-20260407-002\\r\\n\\r\\n#3:\\r\\nPPE-20260407-003\\r\\n\\r\\n#4:\\r\\nPPE-20260407-004\\r\\n\\r\\n#5:\\r\\nPPE-20260407-005\\r\\n\\r\\n#6:\\r\\nPPE-20260407-006\\r\\n\\r\\n#7:\\r\\nPPE-20260407-007\\r\\n\\r\\n#8:\\r\\nPPE-20260407-008\\r\\n\\r\\n#9:\\r\\nPPE-20260407-009\\r\\n\\r\\n#10:\\r\\nPPE-20260407-010\\r\\n\\r\\n... and 15 more items', 'asdasdasd', 6, 6, 4, '2026-04-07 00:08:51', '2026-04-07 00:08:51', 'example', 12.00, 3, 'example', 'PPE', 7, 'PPE-20260407-006', 6, NULL),
(263, 'april', 'aprilapril', '2026-000246-007', 'pcs', 25.00, 25.00, NULL, 'Good', 'odes to be generated:\\r\\n\\r\\n#1:\\r\\nPPE-20260407-001\\r\\n\\r\\n#2:\\r\\nPPE-20260407-002\\r\\n\\r\\n#3:\\r\\nPPE-20260407-003\\r\\n\\r\\n#4:\\r\\nPPE-20260407-004\\r\\n\\r\\n#5:\\r\\nPPE-20260407-005\\r\\n\\r\\n#6:\\r\\nPPE-20260407-006\\r\\n\\r\\n#7:\\r\\nPPE-20260407-007\\r\\n\\r\\n#8:\\r\\nPPE-20260407-008\\r\\n\\r\\n#9:\\r\\nPPE-20260407-009\\r\\n\\r\\n#10:\\r\\nPPE-20260407-010\\r\\n\\r\\n... and 15 more items', 'asdasdasd', 6, 6, 4, '2026-04-07 00:08:51', '2026-04-07 00:08:51', 'example', 12.00, 3, 'example', 'PPE', 7, 'PPE-20260407-007', 6, NULL),
(264, 'april', 'aprilapril', '2026-000246-008', 'pcs', 25.00, 25.00, NULL, 'Good', 'odes to be generated:\\r\\n\\r\\n#1:\\r\\nPPE-20260407-001\\r\\n\\r\\n#2:\\r\\nPPE-20260407-002\\r\\n\\r\\n#3:\\r\\nPPE-20260407-003\\r\\n\\r\\n#4:\\r\\nPPE-20260407-004\\r\\n\\r\\n#5:\\r\\nPPE-20260407-005\\r\\n\\r\\n#6:\\r\\nPPE-20260407-006\\r\\n\\r\\n#7:\\r\\nPPE-20260407-007\\r\\n\\r\\n#8:\\r\\nPPE-20260407-008\\r\\n\\r\\n#9:\\r\\nPPE-20260407-009\\r\\n\\r\\n#10:\\r\\nPPE-20260407-010\\r\\n\\r\\n... and 15 more items', 'asdasdasd', 6, 6, 4, '2026-04-07 00:08:51', '2026-04-07 00:08:51', 'example', 12.00, 3, 'example', 'PPE', 7, 'PPE-20260407-008', 6, NULL),
(265, 'april', 'aprilapril', '2026-000246-009', 'pcs', 25.00, 25.00, NULL, 'Good', 'odes to be generated:\\r\\n\\r\\n#1:\\r\\nPPE-20260407-001\\r\\n\\r\\n#2:\\r\\nPPE-20260407-002\\r\\n\\r\\n#3:\\r\\nPPE-20260407-003\\r\\n\\r\\n#4:\\r\\nPPE-20260407-004\\r\\n\\r\\n#5:\\r\\nPPE-20260407-005\\r\\n\\r\\n#6:\\r\\nPPE-20260407-006\\r\\n\\r\\n#7:\\r\\nPPE-20260407-007\\r\\n\\r\\n#8:\\r\\nPPE-20260407-008\\r\\n\\r\\n#9:\\r\\nPPE-20260407-009\\r\\n\\r\\n#10:\\r\\nPPE-20260407-010\\r\\n\\r\\n... and 15 more items', 'asdasdasd', 6, 6, 4, '2026-04-07 00:08:51', '2026-04-07 00:08:51', 'example', 12.00, 3, 'example', 'PPE', 7, 'PPE-20260407-009', 6, NULL),
(266, 'april', 'aprilapril', '2026-000246-010', 'pcs', 25.00, 25.00, NULL, 'Good', 'odes to be generated:\\r\\n\\r\\n#1:\\r\\nPPE-20260407-001\\r\\n\\r\\n#2:\\r\\nPPE-20260407-002\\r\\n\\r\\n#3:\\r\\nPPE-20260407-003\\r\\n\\r\\n#4:\\r\\nPPE-20260407-004\\r\\n\\r\\n#5:\\r\\nPPE-20260407-005\\r\\n\\r\\n#6:\\r\\nPPE-20260407-006\\r\\n\\r\\n#7:\\r\\nPPE-20260407-007\\r\\n\\r\\n#8:\\r\\nPPE-20260407-008\\r\\n\\r\\n#9:\\r\\nPPE-20260407-009\\r\\n\\r\\n#10:\\r\\nPPE-20260407-010\\r\\n\\r\\n... and 15 more items', 'asdasdasd', 6, 6, 4, '2026-04-07 00:08:51', '2026-04-07 00:08:51', 'example', 12.00, 3, 'example', 'PPE', 7, 'PPE-20260407-010', 6, NULL),
(267, 'april', 'aprilapril', '2026-000246-011', 'pcs', 25.00, 25.00, NULL, 'Good', 'odes to be generated:\\r\\n\\r\\n#1:\\r\\nPPE-20260407-001\\r\\n\\r\\n#2:\\r\\nPPE-20260407-002\\r\\n\\r\\n#3:\\r\\nPPE-20260407-003\\r\\n\\r\\n#4:\\r\\nPPE-20260407-004\\r\\n\\r\\n#5:\\r\\nPPE-20260407-005\\r\\n\\r\\n#6:\\r\\nPPE-20260407-006\\r\\n\\r\\n#7:\\r\\nPPE-20260407-007\\r\\n\\r\\n#8:\\r\\nPPE-20260407-008\\r\\n\\r\\n#9:\\r\\nPPE-20260407-009\\r\\n\\r\\n#10:\\r\\nPPE-20260407-010\\r\\n\\r\\n... and 15 more items', 'asdasdasd', 6, 6, 4, '2026-04-07 00:08:51', '2026-04-07 00:08:51', 'example', 12.00, 3, 'example', 'PPE', 7, 'PPE-20260407-011', 6, NULL),
(268, 'april', 'aprilapril', '2026-000246-012', 'pcs', 25.00, 25.00, NULL, 'Good', 'odes to be generated:\\r\\n\\r\\n#1:\\r\\nPPE-20260407-001\\r\\n\\r\\n#2:\\r\\nPPE-20260407-002\\r\\n\\r\\n#3:\\r\\nPPE-20260407-003\\r\\n\\r\\n#4:\\r\\nPPE-20260407-004\\r\\n\\r\\n#5:\\r\\nPPE-20260407-005\\r\\n\\r\\n#6:\\r\\nPPE-20260407-006\\r\\n\\r\\n#7:\\r\\nPPE-20260407-007\\r\\n\\r\\n#8:\\r\\nPPE-20260407-008\\r\\n\\r\\n#9:\\r\\nPPE-20260407-009\\r\\n\\r\\n#10:\\r\\nPPE-20260407-010\\r\\n\\r\\n... and 15 more items', 'asdasdasd', 6, 6, 4, '2026-04-07 00:08:51', '2026-04-07 00:08:51', 'example', 12.00, 3, 'example', 'PPE', 7, 'PPE-20260407-012', 6, NULL),
(269, 'april', 'aprilapril', '2026-000246-013', 'pcs', 25.00, 25.00, NULL, 'Good', 'odes to be generated:\\r\\n\\r\\n#1:\\r\\nPPE-20260407-001\\r\\n\\r\\n#2:\\r\\nPPE-20260407-002\\r\\n\\r\\n#3:\\r\\nPPE-20260407-003\\r\\n\\r\\n#4:\\r\\nPPE-20260407-004\\r\\n\\r\\n#5:\\r\\nPPE-20260407-005\\r\\n\\r\\n#6:\\r\\nPPE-20260407-006\\r\\n\\r\\n#7:\\r\\nPPE-20260407-007\\r\\n\\r\\n#8:\\r\\nPPE-20260407-008\\r\\n\\r\\n#9:\\r\\nPPE-20260407-009\\r\\n\\r\\n#10:\\r\\nPPE-20260407-010\\r\\n\\r\\n... and 15 more items', 'asdasdasd', 6, 6, 4, '2026-04-07 00:08:51', '2026-04-07 00:08:51', 'example', 12.00, 3, 'example', 'PPE', 7, 'PPE-20260407-013', 6, NULL),
(270, 'april', 'aprilapril', '2026-000246-014', 'pcs', 25.00, 25.00, NULL, 'Good', 'odes to be generated:\\r\\n\\r\\n#1:\\r\\nPPE-20260407-001\\r\\n\\r\\n#2:\\r\\nPPE-20260407-002\\r\\n\\r\\n#3:\\r\\nPPE-20260407-003\\r\\n\\r\\n#4:\\r\\nPPE-20260407-004\\r\\n\\r\\n#5:\\r\\nPPE-20260407-005\\r\\n\\r\\n#6:\\r\\nPPE-20260407-006\\r\\n\\r\\n#7:\\r\\nPPE-20260407-007\\r\\n\\r\\n#8:\\r\\nPPE-20260407-008\\r\\n\\r\\n#9:\\r\\nPPE-20260407-009\\r\\n\\r\\n#10:\\r\\nPPE-20260407-010\\r\\n\\r\\n... and 15 more items', 'asdasdasd', 6, 6, 4, '2026-04-07 00:08:51', '2026-04-07 00:08:51', 'example', 12.00, 3, 'example', 'PPE', 7, 'PPE-20260407-014', 6, NULL),
(271, 'april', 'aprilapril', '2026-000246-015', 'pcs', 25.00, 25.00, NULL, 'Good', 'odes to be generated:\\r\\n\\r\\n#1:\\r\\nPPE-20260407-001\\r\\n\\r\\n#2:\\r\\nPPE-20260407-002\\r\\n\\r\\n#3:\\r\\nPPE-20260407-003\\r\\n\\r\\n#4:\\r\\nPPE-20260407-004\\r\\n\\r\\n#5:\\r\\nPPE-20260407-005\\r\\n\\r\\n#6:\\r\\nPPE-20260407-006\\r\\n\\r\\n#7:\\r\\nPPE-20260407-007\\r\\n\\r\\n#8:\\r\\nPPE-20260407-008\\r\\n\\r\\n#9:\\r\\nPPE-20260407-009\\r\\n\\r\\n#10:\\r\\nPPE-20260407-010\\r\\n\\r\\n... and 15 more items', 'asdasdasd', 6, 6, 4, '2026-04-07 00:08:51', '2026-04-07 00:08:51', 'example', 12.00, 3, 'example', 'PPE', 7, 'PPE-20260407-015', 6, NULL),
(272, 'april', 'aprilapril', '2026-000246-016', 'pcs', 25.00, 25.00, NULL, 'Good', 'odes to be generated:\\r\\n\\r\\n#1:\\r\\nPPE-20260407-001\\r\\n\\r\\n#2:\\r\\nPPE-20260407-002\\r\\n\\r\\n#3:\\r\\nPPE-20260407-003\\r\\n\\r\\n#4:\\r\\nPPE-20260407-004\\r\\n\\r\\n#5:\\r\\nPPE-20260407-005\\r\\n\\r\\n#6:\\r\\nPPE-20260407-006\\r\\n\\r\\n#7:\\r\\nPPE-20260407-007\\r\\n\\r\\n#8:\\r\\nPPE-20260407-008\\r\\n\\r\\n#9:\\r\\nPPE-20260407-009\\r\\n\\r\\n#10:\\r\\nPPE-20260407-010\\r\\n\\r\\n... and 15 more items', 'asdasdasd', 6, 6, 4, '2026-04-07 00:08:51', '2026-04-07 00:08:51', 'example', 12.00, 3, 'example', 'PPE', 7, 'PPE-20260407-016', 6, NULL),
(273, 'april', 'aprilapril', '2026-000246-017', 'pcs', 25.00, 25.00, NULL, 'Good', 'odes to be generated:\\r\\n\\r\\n#1:\\r\\nPPE-20260407-001\\r\\n\\r\\n#2:\\r\\nPPE-20260407-002\\r\\n\\r\\n#3:\\r\\nPPE-20260407-003\\r\\n\\r\\n#4:\\r\\nPPE-20260407-004\\r\\n\\r\\n#5:\\r\\nPPE-20260407-005\\r\\n\\r\\n#6:\\r\\nPPE-20260407-006\\r\\n\\r\\n#7:\\r\\nPPE-20260407-007\\r\\n\\r\\n#8:\\r\\nPPE-20260407-008\\r\\n\\r\\n#9:\\r\\nPPE-20260407-009\\r\\n\\r\\n#10:\\r\\nPPE-20260407-010\\r\\n\\r\\n... and 15 more items', 'asdasdasd', 6, 6, 4, '2026-04-07 00:08:51', '2026-04-07 00:08:51', 'example', 12.00, 3, 'example', 'PPE', 7, 'PPE-20260407-017', 6, NULL),
(274, 'april', 'aprilapril', '2026-000246-018', 'pcs', 25.00, 25.00, NULL, 'Good', 'odes to be generated:\\r\\n\\r\\n#1:\\r\\nPPE-20260407-001\\r\\n\\r\\n#2:\\r\\nPPE-20260407-002\\r\\n\\r\\n#3:\\r\\nPPE-20260407-003\\r\\n\\r\\n#4:\\r\\nPPE-20260407-004\\r\\n\\r\\n#5:\\r\\nPPE-20260407-005\\r\\n\\r\\n#6:\\r\\nPPE-20260407-006\\r\\n\\r\\n#7:\\r\\nPPE-20260407-007\\r\\n\\r\\n#8:\\r\\nPPE-20260407-008\\r\\n\\r\\n#9:\\r\\nPPE-20260407-009\\r\\n\\r\\n#10:\\r\\nPPE-20260407-010\\r\\n\\r\\n... and 15 more items', 'asdasdasd', 6, 6, 4, '2026-04-07 00:08:51', '2026-04-07 00:08:51', 'example', 12.00, 3, 'example', 'PPE', 7, 'PPE-20260407-018', 6, NULL),
(275, 'april', 'aprilapril', '2026-000246-019', 'pcs', 25.00, 25.00, NULL, 'Good', 'odes to be generated:\\r\\n\\r\\n#1:\\r\\nPPE-20260407-001\\r\\n\\r\\n#2:\\r\\nPPE-20260407-002\\r\\n\\r\\n#3:\\r\\nPPE-20260407-003\\r\\n\\r\\n#4:\\r\\nPPE-20260407-004\\r\\n\\r\\n#5:\\r\\nPPE-20260407-005\\r\\n\\r\\n#6:\\r\\nPPE-20260407-006\\r\\n\\r\\n#7:\\r\\nPPE-20260407-007\\r\\n\\r\\n#8:\\r\\nPPE-20260407-008\\r\\n\\r\\n#9:\\r\\nPPE-20260407-009\\r\\n\\r\\n#10:\\r\\nPPE-20260407-010\\r\\n\\r\\n... and 15 more items', 'asdasdasd', 6, 6, 4, '2026-04-07 00:08:51', '2026-04-07 00:08:51', 'example', 12.00, 3, 'example', 'PPE', 7, 'PPE-20260407-019', 6, NULL),
(276, 'april', 'aprilapril', '2026-000246-020', 'pcs', 25.00, 25.00, NULL, 'Good', 'odes to be generated:\\r\\n\\r\\n#1:\\r\\nPPE-20260407-001\\r\\n\\r\\n#2:\\r\\nPPE-20260407-002\\r\\n\\r\\n#3:\\r\\nPPE-20260407-003\\r\\n\\r\\n#4:\\r\\nPPE-20260407-004\\r\\n\\r\\n#5:\\r\\nPPE-20260407-005\\r\\n\\r\\n#6:\\r\\nPPE-20260407-006\\r\\n\\r\\n#7:\\r\\nPPE-20260407-007\\r\\n\\r\\n#8:\\r\\nPPE-20260407-008\\r\\n\\r\\n#9:\\r\\nPPE-20260407-009\\r\\n\\r\\n#10:\\r\\nPPE-20260407-010\\r\\n\\r\\n... and 15 more items', 'asdasdasd', 6, 6, 4, '2026-04-07 00:08:51', '2026-04-07 00:08:51', 'example', 12.00, 3, 'example', 'PPE', 7, 'PPE-20260407-020', 6, NULL),
(277, 'april', 'aprilapril', '2026-000246-021', 'pcs', 25.00, 25.00, NULL, 'Good', 'odes to be generated:\\r\\n\\r\\n#1:\\r\\nPPE-20260407-001\\r\\n\\r\\n#2:\\r\\nPPE-20260407-002\\r\\n\\r\\n#3:\\r\\nPPE-20260407-003\\r\\n\\r\\n#4:\\r\\nPPE-20260407-004\\r\\n\\r\\n#5:\\r\\nPPE-20260407-005\\r\\n\\r\\n#6:\\r\\nPPE-20260407-006\\r\\n\\r\\n#7:\\r\\nPPE-20260407-007\\r\\n\\r\\n#8:\\r\\nPPE-20260407-008\\r\\n\\r\\n#9:\\r\\nPPE-20260407-009\\r\\n\\r\\n#10:\\r\\nPPE-20260407-010\\r\\n\\r\\n... and 15 more items', 'asdasdasd', 6, 6, 4, '2026-04-07 00:08:51', '2026-04-07 00:08:51', 'example', 12.00, 3, 'example', 'PPE', 7, 'PPE-20260407-021', 6, NULL),
(278, 'april', 'aprilapril', '2026-000246-022', 'pcs', 25.00, 25.00, NULL, 'Good', 'odes to be generated:\\r\\n\\r\\n#1:\\r\\nPPE-20260407-001\\r\\n\\r\\n#2:\\r\\nPPE-20260407-002\\r\\n\\r\\n#3:\\r\\nPPE-20260407-003\\r\\n\\r\\n#4:\\r\\nPPE-20260407-004\\r\\n\\r\\n#5:\\r\\nPPE-20260407-005\\r\\n\\r\\n#6:\\r\\nPPE-20260407-006\\r\\n\\r\\n#7:\\r\\nPPE-20260407-007\\r\\n\\r\\n#8:\\r\\nPPE-20260407-008\\r\\n\\r\\n#9:\\r\\nPPE-20260407-009\\r\\n\\r\\n#10:\\r\\nPPE-20260407-010\\r\\n\\r\\n... and 15 more items', 'asdasdasd', 6, 6, 4, '2026-04-07 00:08:51', '2026-04-07 00:08:51', 'example', 12.00, 3, 'example', 'PPE', 7, 'PPE-20260407-022', 6, NULL),
(279, 'april', 'aprilapril', '2026-000246-023', 'pcs', 25.00, 25.00, NULL, 'Good', 'odes to be generated:\\r\\n\\r\\n#1:\\r\\nPPE-20260407-001\\r\\n\\r\\n#2:\\r\\nPPE-20260407-002\\r\\n\\r\\n#3:\\r\\nPPE-20260407-003\\r\\n\\r\\n#4:\\r\\nPPE-20260407-004\\r\\n\\r\\n#5:\\r\\nPPE-20260407-005\\r\\n\\r\\n#6:\\r\\nPPE-20260407-006\\r\\n\\r\\n#7:\\r\\nPPE-20260407-007\\r\\n\\r\\n#8:\\r\\nPPE-20260407-008\\r\\n\\r\\n#9:\\r\\nPPE-20260407-009\\r\\n\\r\\n#10:\\r\\nPPE-20260407-010\\r\\n\\r\\n... and 15 more items', 'asdasdasd', 6, 6, 4, '2026-04-07 00:08:51', '2026-04-07 00:08:51', 'example', 12.00, 3, 'example', 'PPE', 7, 'PPE-20260407-023', 6, NULL),
(280, 'april', 'aprilapril', '2026-000246-024', 'pcs', 25.00, 25.00, NULL, 'Good', 'odes to be generated:\\r\\n\\r\\n#1:\\r\\nPPE-20260407-001\\r\\n\\r\\n#2:\\r\\nPPE-20260407-002\\r\\n\\r\\n#3:\\r\\nPPE-20260407-003\\r\\n\\r\\n#4:\\r\\nPPE-20260407-004\\r\\n\\r\\n#5:\\r\\nPPE-20260407-005\\r\\n\\r\\n#6:\\r\\nPPE-20260407-006\\r\\n\\r\\n#7:\\r\\nPPE-20260407-007\\r\\n\\r\\n#8:\\r\\nPPE-20260407-008\\r\\n\\r\\n#9:\\r\\nPPE-20260407-009\\r\\n\\r\\n#10:\\r\\nPPE-20260407-010\\r\\n\\r\\n... and 15 more items', 'asdasdasd', 6, 6, 4, '2026-04-07 00:08:51', '2026-04-07 00:08:51', 'example', 12.00, 3, 'example', 'PPE', 7, 'PPE-20260407-024', 6, NULL),
(281, 'april', 'aprilapril', '2026-000246-025', 'pcs', 25.00, 25.00, NULL, 'Good', 'odes to be generated:\\r\\n\\r\\n#1:\\r\\nPPE-20260407-001\\r\\n\\r\\n#2:\\r\\nPPE-20260407-002\\r\\n\\r\\n#3:\\r\\nPPE-20260407-003\\r\\n\\r\\n#4:\\r\\nPPE-20260407-004\\r\\n\\r\\n#5:\\r\\nPPE-20260407-005\\r\\n\\r\\n#6:\\r\\nPPE-20260407-006\\r\\n\\r\\n#7:\\r\\nPPE-20260407-007\\r\\n\\r\\n#8:\\r\\nPPE-20260407-008\\r\\n\\r\\n#9:\\r\\nPPE-20260407-009\\r\\n\\r\\n#10:\\r\\nPPE-20260407-010\\r\\n\\r\\n... and 15 more items', 'asdasdasd', 6, 6, 4, '2026-04-07 00:08:51', '2026-04-07 00:08:51', 'example', 12.00, 3, 'example', 'PPE', 7, 'PPE-20260407-025', 6, NULL),
(282, 'test2', 'test2', '2026-000271-001', 'box', 111.00, 99.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-001', 6, NULL),
(283, 'test2', 'test2', '2026-000271-002', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-002', 6, NULL),
(284, 'test2', 'test2', '2026-000271-003', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-003', 6, NULL),
(285, 'test2', 'test2', '2026-000271-004', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-004', 6, NULL),
(286, 'test2', 'test2', '2026-000271-005', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-005', 6, NULL),
(287, 'test2', 'test2', '2026-000271-006', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-006', 6, NULL),
(288, 'test2', 'test2', '2026-000271-007', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-007', 6, NULL),
(289, 'test2', 'test2', '2026-000271-008', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-008', 6, NULL),
(290, 'test2', 'test2', '2026-000271-009', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-009', 6, NULL),
(291, 'test2', 'test2', '2026-000271-010', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-010', 6, NULL),
(292, 'test2', 'test2', '2026-000271-011', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-011', 6, NULL),
(293, 'test2', 'test2', '2026-000271-012', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-012', 6, NULL),
(294, 'test2', 'test2', '2026-000271-013', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-013', 6, NULL),
(295, 'test2', 'test2', '2026-000271-014', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-014', 6, NULL),
(296, 'test2', 'test2', '2026-000271-015', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-015', 6, NULL),
(297, 'test2', 'test2', '2026-000271-016', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-016', 6, NULL),
(298, 'test2', 'test2', '2026-000271-017', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-017', 6, NULL),
(299, 'test2', 'test2', '2026-000271-018', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-018', 6, NULL),
(300, 'test2', 'test2', '2026-000271-019', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-019', 6, NULL),
(301, 'test2', 'test2', '2026-000271-020', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-020', 6, NULL),
(302, 'test2', 'test2', '2026-000271-021', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-021', 6, NULL),
(303, 'test2', 'test2', '2026-000271-022', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-022', 6, NULL),
(304, 'test2', 'test2', '2026-000271-023', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-023', 6, NULL),
(305, 'test2', 'test2', '2026-000271-024', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-024', 6, NULL),
(306, 'test2', 'test2', '2026-000271-025', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-025', 6, NULL),
(307, 'test2', 'test2', '2026-000271-026', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-026', 6, NULL),
(308, 'test2', 'test2', '2026-000271-027', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-027', 6, NULL),
(309, 'test2', 'test2', '2026-000271-028', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-028', 6, NULL),
(310, 'test2', 'test2', '2026-000271-029', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-029', 6, NULL),
(311, 'test2', 'test2', '2026-000271-030', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-030', 6, NULL),
(312, 'test2', 'test2', '2026-000271-031', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-031', 6, NULL),
(313, 'test2', 'test2', '2026-000271-032', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-032', 6, NULL),
(314, 'test2', 'test2', '2026-000271-033', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-033', 6, NULL),
(315, 'test2', 'test2', '2026-000271-034', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-034', 6, NULL),
(316, 'test2', 'test2', '2026-000271-035', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-035', 6, NULL),
(317, 'test2', 'test2', '2026-000271-036', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-036', 6, NULL),
(318, 'test2', 'test2', '2026-000271-037', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-037', 6, NULL),
(319, 'test2', 'test2', '2026-000271-038', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-038', 6, NULL),
(320, 'test2', 'test2', '2026-000271-039', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-039', 6, NULL),
(321, 'test2', 'test2', '2026-000271-040', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-040', 6, NULL),
(322, 'test2', 'test2', '2026-000271-041', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-041', 6, NULL),
(323, 'test2', 'test2', '2026-000271-042', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-042', 6, NULL),
(324, 'test2', 'test2', '2026-000271-043', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-043', 6, NULL),
(325, 'test2', 'test2', '2026-000271-044', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-044', 6, NULL),
(326, 'test2', 'test2', '2026-000271-045', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-045', 6, NULL),
(327, 'test2', 'test2', '2026-000271-046', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-046', 6, NULL),
(328, 'test2', 'test2', '2026-000271-047', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-047', 6, NULL),
(329, 'test2', 'test2', '2026-000271-048', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-048', 6, NULL),
(330, 'test2', 'test2', '2026-000271-049', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-049', 6, NULL),
(331, 'test2', 'test2', '2026-000271-050', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-050', 6, NULL),
(332, 'test2', 'test2', '2026-000271-051', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-051', 6, NULL),
(333, 'test2', 'test2', '2026-000271-052', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-052', 6, NULL),
(334, 'test2', 'test2', '2026-000271-053', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-053', 6, NULL),
(335, 'test2', 'test2', '2026-000271-054', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-054', 6, NULL),
(336, 'test2', 'test2', '2026-000271-055', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-055', 6, NULL),
(337, 'test2', 'test2', '2026-000271-056', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-056', 6, NULL),
(338, 'test2', 'test2', '2026-000271-057', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-057', 6, NULL),
(339, 'test2', 'test2', '2026-000271-058', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-058', 6, NULL),
(340, 'test2', 'test2', '2026-000271-059', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-059', 6, NULL),
(341, 'test2', 'test2', '2026-000271-060', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-060', 6, NULL),
(342, 'test2', 'test2', '2026-000271-061', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-061', 6, NULL),
(343, 'test2', 'test2', '2026-000271-062', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-062', 6, NULL),
(344, 'test2', 'test2', '2026-000271-063', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-063', 6, NULL),
(345, 'test2', 'test2', '2026-000271-064', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-064', 6, NULL),
(346, 'test2', 'test2', '2026-000271-065', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-065', 6, NULL),
(347, 'test2', 'test2', '2026-000271-066', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-066', 6, NULL),
(348, 'test2', 'test2', '2026-000271-067', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-067', 6, NULL),
(349, 'test2', 'test2', '2026-000271-068', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-068', 6, NULL),
(350, 'test2', 'test2', '2026-000271-069', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-069', 6, NULL),
(351, 'test2', 'test2', '2026-000271-070', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-070', 6, NULL),
(352, 'test2', 'test2', '2026-000271-071', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-071', 6, NULL),
(353, 'test2', 'test2', '2026-000271-072', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-072', 6, NULL),
(354, 'test2', 'test2', '2026-000271-073', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-073', 6, NULL);
INSERT INTO `inventory` (`id`, `article_name`, `description`, `property_no`, `uom`, `qty_property_card`, `qty_physical_count`, `location_id`, `condition_text`, `remarks`, `certified_correct`, `approved_by`, `verified_by`, `section_id`, `date_added`, `date_updated`, `fund_cluster`, `unit_value`, `equipment_id`, `type_equipment`, `category`, `allocate_to`, `barcode_data`, `created_by`, `current_holder`) VALUES
(355, 'test2', 'test2', '2026-000271-074', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-074', 6, NULL),
(356, 'test2', 'test2', '2026-000271-075', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-075', 6, NULL),
(357, 'test2', 'test2', '2026-000271-076', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-076', 6, NULL),
(358, 'test2', 'test2', '2026-000271-077', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-077', 6, NULL),
(359, 'test2', 'test2', '2026-000271-078', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-078', 6, NULL),
(360, 'test2', 'test2', '2026-000271-079', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-079', 6, NULL),
(361, 'test2', 'test2', '2026-000271-080', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-080', 6, NULL),
(362, 'test2', 'test2', '2026-000271-081', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-081', 6, NULL),
(363, 'test2', 'test2', '2026-000271-082', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-082', 6, NULL),
(364, 'test2', 'test2', '2026-000271-083', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-083', 6, NULL),
(365, 'test2', 'test2', '2026-000271-084', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-084', 6, NULL),
(366, 'test2', 'test2', '2026-000271-085', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-085', 6, NULL),
(367, 'test2', 'test2', '2026-000271-086', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-086', 6, NULL),
(368, 'test2', 'test2', '2026-000271-087', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-087', 6, NULL),
(369, 'test2', 'test2', '2026-000271-088', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-088', 6, NULL),
(370, 'test2', 'test2', '2026-000271-089', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-089', 6, NULL),
(371, 'test2', 'test2', '2026-000271-090', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-090', 6, NULL),
(372, 'test2', 'test2', '2026-000271-091', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-091', 6, NULL),
(373, 'test2', 'test2', '2026-000271-092', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-092', 6, NULL),
(374, 'test2', 'test2', '2026-000271-093', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-093', 6, NULL),
(375, 'test2', 'test2', '2026-000271-094', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-094', 6, NULL),
(376, 'test2', 'test2', '2026-000271-095', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-095', 6, NULL),
(377, 'test2', 'test2', '2026-000271-096', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-096', 6, NULL),
(378, 'test2', 'test2', '2026-000271-097', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-097', 6, NULL),
(379, 'test2', 'test2', '2026-000271-098', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-098', 6, NULL),
(380, 'test2', 'test2', '2026-000271-099', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-099', 6, NULL),
(381, 'test2', 'test2', '2026-000271-100', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-100', 6, NULL),
(382, 'test2', 'test2', '2026-000271-101', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-101', 6, NULL),
(383, 'test2', 'test2', '2026-000271-102', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-102', 6, NULL),
(384, 'test2', 'test2', '2026-000271-103', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-103', 6, NULL),
(385, 'test2', 'test2', '2026-000271-104', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-104', 6, NULL),
(386, 'test2', 'test2', '2026-000271-105', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-105', 6, NULL),
(387, 'test2', 'test2', '2026-000271-106', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-106', 6, NULL),
(388, 'test2', 'test2', '2026-000271-107', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-107', 6, NULL),
(389, 'test2', 'test2', '2026-000271-108', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-108', 6, NULL),
(390, 'test2', 'test2', '2026-000271-109', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-109', 6, NULL),
(391, 'test2', 'test2', '2026-000271-110', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-110', 6, NULL),
(392, 'test2', 'test2', '2026-000271-111', 'box', 111.00, 111.00, NULL, 'Serviceable', 'test2test2', 'test2test2', 5, 5, 1, '2026-04-07 01:24:57', '2026-04-07 01:24:57', 'test2test2', 23.00, 3, 'test2', 'Semi-Expendable', 5, 'SEMI-20260407-111', 6, NULL),
(393, 'dasdasdasdasdasdas', 'dsadasdasdasdasdsad', '2026-000382', '0', 1.00, 1.00, NULL, 'Good', '0', '', NULL, NULL, 2, '2026-04-13 08:22:27', '2026-04-13 08:22:27', 'example', 123123213.00, 1, 'dasdasdasd', 'ICT Equipment', 7, 'INV202604134113', 6, NULL),
(394, 'nobar', 'nobar', '2026-000383-1', '0', 1.00, 1.00, NULL, 'Good', '0', '', 6, 7, 1, '2026-04-14 00:43:10', '2026-04-14 00:43:10', '', 23.00, 3, 'nobar', 'ICT Equipment', 6, 'INV202604149822001', 6, NULL),
(395, 'nobar', 'nobar', '2026-000383-2', '0', 1.00, 1.00, NULL, 'Good', '0', '', 6, 7, 1, '2026-04-14 00:43:10', '2026-04-14 00:43:10', '', 23.00, 3, 'nobar', 'ICT Equipment', 6, 'INV202604145526002', 6, NULL),
(396, 'nobar', 'nobar', '2026-000383-3', '0', 1.00, 1.00, NULL, 'Good', '0', '', 6, 7, 1, '2026-04-14 00:43:10', '2026-04-14 00:43:10', '', 23.00, 3, 'nobar', 'ICT Equipment', 6, 'INV202604145112003', 6, NULL),
(397, 'nobar', 'nobar', '2026-000383-4', '0', 1.00, 1.00, NULL, 'Good', '0', '', 6, 7, 1, '2026-04-14 00:43:10', '2026-04-14 00:43:10', '', 23.00, 3, 'nobar', 'ICT Equipment', 6, 'INV202604145073004', 6, NULL),
(398, 'nobar', 'nobar', '2026-000383-5', '0', 1.00, 1.00, NULL, 'Good', '0', '', 6, 7, 1, '2026-04-14 00:43:10', '2026-04-14 00:43:10', '', 23.00, 3, 'nobar', 'ICT Equipment', 6, 'INV202604143885005', 6, NULL),
(399, 'nobar', 'nobar', '2026-000383-6', '0', 1.00, 1.00, NULL, 'Good', '0', '', 6, 7, 1, '2026-04-14 00:43:10', '2026-04-14 00:43:10', '', 23.00, 3, 'nobar', 'ICT Equipment', 6, 'INV202604148235006', 6, NULL),
(400, 'nobar', 'nobar', '2026-000383-7', '0', 1.00, 1.00, NULL, 'Good', '0', '', 6, 7, 1, '2026-04-14 00:43:10', '2026-04-14 00:43:10', '', 23.00, 3, 'nobar', 'ICT Equipment', 6, 'INV202604141086007', 6, NULL),
(401, 'nobar', 'nobar', '2026-000383-8', '0', 1.00, 1.00, NULL, 'Good', '0', '', 6, 7, 1, '2026-04-14 00:43:10', '2026-04-14 00:43:10', '', 23.00, 3, 'nobar', 'ICT Equipment', 6, 'INV202604143458008', 6, NULL),
(402, 'nobar', 'nobar', '2026-000383-9', '0', 1.00, 1.00, NULL, 'Good', '0', '', 6, 7, 1, '2026-04-14 00:43:10', '2026-04-14 00:43:10', '', 23.00, 3, 'nobar', 'ICT Equipment', 6, 'INV202604143211009', 6, NULL),
(403, 'nobar', 'nobar', '2026-000383-10', '0', 1.00, 1.00, NULL, 'Good', '0', '', 6, 7, 1, '2026-04-14 00:43:10', '2026-04-14 00:43:10', '', 23.00, 3, 'nobar', 'ICT Equipment', 6, 'INV202604146221010', 6, NULL),
(404, 'nobar', 'nobar', '2026-000383-11', '0', 1.00, 1.00, NULL, 'Good', '0', '', 6, 7, 1, '2026-04-14 00:43:10', '2026-04-14 00:43:10', '', 23.00, 3, 'nobar', 'ICT Equipment', 6, 'INV202604147939011', 6, NULL),
(405, 'nobar', 'nobar', '2026-000383-12', '0', 1.00, 1.00, NULL, 'Good', '0', '', 6, 7, 1, '2026-04-14 00:43:10', '2026-04-14 00:43:10', '', 23.00, 3, 'nobar', 'ICT Equipment', 6, 'INV202604146414012', 6, NULL),
(407, 'testfordpt', 'testfordpt', '2026-000395-001', 'pcs', 12.00, 10.00, NULL, 'Good', 'testfordpt', '[7,5,8,6]', 0, 0, 1, '2026-04-14 00:52:45', '2026-04-14 00:52:45', 'testfordpt', 9999999999.99, 3, 'testfordpt', 'PPE', 7, 'PPE-20260414-001', 6, NULL),
(408, 'testfordpt', 'testfordpt', '2026-000395-002', 'pcs', 12.00, 12.00, NULL, 'Good', 'testfordpt', '[7,5,8,6]', 0, 0, 1, '2026-04-14 00:52:45', '2026-04-14 00:52:45', 'testfordpt', 9999999999.99, 3, 'testfordpt', 'PPE', 7, 'PPE-20260414-002', 6, NULL),
(409, 'testfordpt', 'testfordpt', '2026-000395-003', 'pcs', 12.00, 12.00, NULL, 'Good', 'testfordpt', '[7,5,8,6]', 0, 0, 1, '2026-04-14 00:52:45', '2026-04-14 00:52:45', 'testfordpt', 9999999999.99, 3, 'testfordpt', 'PPE', 7, 'PPE-20260414-003', 6, NULL),
(410, 'testfordpt', 'testfordpt', '2026-000395-004', 'pcs', 12.00, 12.00, NULL, 'Good', 'testfordpt', '[7,5,8,6]', 0, 0, 1, '2026-04-14 00:52:45', '2026-04-14 00:52:45', 'testfordpt', 9999999999.99, 3, 'testfordpt', 'PPE', 7, 'PPE-20260414-004', 6, NULL),
(411, 'testfordpt', 'testfordpt', '2026-000395-005', 'pcs', 12.00, 12.00, NULL, 'Good', 'testfordpt', '[7,5,8,6]', 0, 0, 1, '2026-04-14 00:52:45', '2026-04-14 00:52:45', 'testfordpt', 9999999999.99, 3, 'testfordpt', 'PPE', 7, 'PPE-20260414-005', 6, NULL),
(412, 'testfordpt', 'testfordpt', '2026-000395-006', 'pcs', 12.00, 12.00, NULL, 'Good', 'testfordpt', '[7,5,8,6]', 0, 0, 1, '2026-04-14 00:52:45', '2026-04-14 00:52:45', 'testfordpt', 9999999999.99, 3, 'testfordpt', 'PPE', 7, 'PPE-20260414-006', 6, NULL),
(413, 'testfordpt', 'testfordpt', '2026-000395-007', 'pcs', 12.00, 12.00, NULL, 'Good', 'testfordpt', '[7,5,8,6]', 0, 0, 1, '2026-04-14 00:52:45', '2026-04-14 00:52:45', 'testfordpt', 9999999999.99, 3, 'testfordpt', 'PPE', 7, 'PPE-20260414-007', 6, NULL),
(414, 'testfordpt', 'testfordpt', '2026-000395-008', 'pcs', 12.00, 12.00, NULL, 'Good', 'testfordpt', '[7,5,8,6]', 0, 0, 1, '2026-04-14 00:52:45', '2026-04-14 00:52:45', 'testfordpt', 9999999999.99, 3, 'testfordpt', 'PPE', 7, 'PPE-20260414-008', 6, NULL),
(415, 'testfordpt', 'testfordpt', '2026-000395-009', 'pcs', 12.00, 12.00, NULL, 'Good', 'testfordpt', '[7,5,8,6]', 0, 0, 1, '2026-04-14 00:52:45', '2026-04-14 00:52:45', 'testfordpt', 9999999999.99, 3, 'testfordpt', 'PPE', 7, 'PPE-20260414-009', 6, NULL),
(416, 'testfordpt', 'testfordpt', '2026-000395-010', 'pcs', 12.00, 12.00, NULL, 'Good', 'testfordpt', '[7,5,8,6]', 0, 0, 1, '2026-04-14 00:52:45', '2026-04-14 00:52:45', 'testfordpt', 9999999999.99, 3, 'testfordpt', 'PPE', 7, 'PPE-20260414-010', 6, NULL),
(417, 'testfordpt', 'testfordpt', '2026-000395-011', 'pcs', 12.00, 12.00, NULL, 'Good', 'testfordpt', '[7,5,8,6]', 0, 0, 1, '2026-04-14 00:52:45', '2026-04-14 00:52:45', 'testfordpt', 9999999999.99, 3, 'testfordpt', 'PPE', 7, 'PPE-20260414-011', 6, NULL),
(418, 'testfordpt', 'testfordpt', '2026-000395-012', 'pcs', 12.00, 12.00, NULL, 'Good', 'testfordpt', '[7,5,8,6]', 0, 0, 1, '2026-04-14 00:52:45', '2026-04-14 00:52:45', 'testfordpt', 9999999999.99, 3, 'testfordpt', 'PPE', 7, 'PPE-20260414-012', 6, NULL),
(419, 'for report', 'example here', '2026-000407', 'box', 1.00, 1.00, NULL, 'New', '', '[9,7]', 0, 0, 4, '2026-04-15 00:14:08', '2026-04-15 00:14:08', 'example name', 232.00, 3, 'example', 'PPE', 9, 'PPE-20260415-7763', 6, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int(11) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `department_id`, `name`) VALUES
(1, 1, 'Triage'),
(2, 1, 'Treatment Room'),
(3, 2, 'Dispensing'),
(4, 3, 'ICU Ward A'),
(5, 3, 'ICU Ward B'),
(6, 4, 'Operating Room 1'),
(7, 4, 'Operating Room 2');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) DEFAULT NULL,
  `setting_value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_by`, `updated_at`) VALUES
(1, 'system_name', 'Inventory Management System', 'System name', NULL, '2026-02-28 12:35:11'),
(2, 'company_name', 'Your Company Name', 'Company name', NULL, '2026-02-28 12:35:11'),
(3, 'system_email', 'admin@example.com', 'System email', NULL, '2026-02-28 12:35:11'),
(4, 'items_per_page', '10', 'Items per page in listings', NULL, '2026-02-28 12:35:11'),
(5, 'enable_audit_trail', '1', 'Enable audit trail logging', NULL, '2026-02-28 12:35:11'),
(6, 'session_timeout', '3600', 'Session timeout in seconds', NULL, '2026-02-28 12:35:11');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `firstname` varchar(100) DEFAULT NULL,
  `lastname` varchar(100) DEFAULT NULL,
  `username` varchar(80) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('super_admin','admin','supply','user') DEFAULT 'user',
  `status` enum('active','inactive') DEFAULT 'active',
  `avatar` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `firstname`, `lastname`, `username`, `password`, `email`, `role`, `status`, `avatar`, `created_at`) VALUES
(5, 'Super', 'Admin', 'superadmin', '$2y$10$SvhCUW1hUVB0yIPhkvjlNOjs/ISFgF6nXC3D5eQA1GDQZtLd1ny/i', 'superadmin@test.com', 'super_admin', 'active', 'avatar_5_1776154729.png', '2026-02-28 12:36:49'),
(6, 'System', 'Admin', 'admin', '$2y$10$5acAj2efifVzkGD9fUa/2O35Ce1ouYmbYOlaxrRoyQIZ6e2XUgFAS', 'admin@test.com', 'admin', 'active', 'avatar_6_1776675237.png', '2026-02-28 12:36:49'),
(7, 'Regular', 'User', 'user', '$2y$10$txXDgxWFsmXSyaoLFiF4OO1mbEWc7KV175Y4JxRXPTnInWwzkvbtK', 'user@test.com', 'user', 'active', NULL, '2026-02-28 12:36:49'),
(8, 'Supply', 'Officer', 'supply', '$2y$10$DmxAimGAkyjd52b62rOvae6.Gkyl4A2pMCgIZNXeu4LUAbqK2lVrm', 'supply@test.com', 'supply', 'active', NULL, '2026-02-28 12:36:49'),
(9, 'dummy', 'dummy', 'dummy', '$2y$10$1NzIJm9XEKuzjEnY.dFTp.DT2yce.h1A5EIUO/vA2antIM3bc9VJS', 'dummy@gmail.com', 'supply', 'active', NULL, '2026-03-06 00:56:42');

-- --------------------------------------------------------

--
-- Table structure for table `user_inventory`
--

CREATE TABLE `user_inventory` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `issuance_id` int(11) DEFAULT NULL,
  `quantity_assigned` decimal(12,2) NOT NULL,
  `assigned_date` timestamp NULL DEFAULT current_timestamp(),
  `status` enum('active','returned','lost','damaged') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_inventory`
--

INSERT INTO `user_inventory` (`id`, `user_id`, `inventory_id`, `issuance_id`, `quantity_assigned`, `assigned_date`, `status`) VALUES
(1, 5, 9, NULL, 222.00, '2026-03-02 06:21:18', 'active'),
(2, 7, 10, NULL, 1.00, '2026-03-02 06:37:49', 'active'),
(3, 7, 11, NULL, 1.00, '2026-03-02 06:37:49', 'active'),
(4, 7, 12, NULL, 1.00, '2026-03-02 06:37:49', 'active'),
(5, 7, 13, NULL, 1.00, '2026-03-02 06:37:49', 'active'),
(6, 7, 14, NULL, 1.00, '2026-03-02 06:37:49', 'active'),
(7, 7, 15, NULL, 1.00, '2026-03-02 06:37:49', 'active'),
(8, 7, 16, NULL, 1.00, '2026-03-02 06:37:49', 'active'),
(9, 7, 17, NULL, 1.00, '2026-03-02 06:37:49', 'active'),
(10, 7, 18, NULL, 1.00, '2026-03-02 06:37:49', 'active'),
(11, 7, 19, NULL, 1.00, '2026-03-02 06:37:49', 'active'),
(13, 7, 20, NULL, 1.00, '2026-03-02 10:07:38', 'active'),
(14, 8, 92, NULL, 1.00, '2026-03-11 04:58:58', 'active'),
(15, 5, 93, NULL, 1.00, '2026-03-11 05:08:08', 'active'),
(16, 8, 94, NULL, 1.00, '2026-03-11 05:10:44', 'active'),
(17, 8, 95, NULL, 1.00, '2026-03-11 05:10:44', 'active'),
(18, 8, 96, NULL, 1.00, '2026-03-11 05:10:44', 'active'),
(19, 8, 97, NULL, 1.00, '2026-03-11 05:10:44', 'active'),
(20, 8, 98, NULL, 1.00, '2026-03-11 05:10:44', 'active'),
(21, 8, 99, NULL, 1.00, '2026-03-11 05:10:44', 'active'),
(22, 8, 100, NULL, 1.00, '2026-03-11 05:10:44', 'active'),
(23, 8, 101, NULL, 1.00, '2026-03-11 05:10:44', 'active'),
(24, 8, 102, NULL, 1.00, '2026-03-11 05:10:44', 'active'),
(25, 8, 103, NULL, 1.00, '2026-03-11 05:10:44', 'active'),
(26, 8, 104, NULL, 1.00, '2026-03-11 05:10:44', 'active'),
(27, 8, 105, NULL, 1.00, '2026-03-11 05:10:44', 'active'),
(28, 7, 393, NULL, 1.00, '2026-04-13 08:22:27', 'active'),
(29, 6, 394, NULL, 1.00, '2026-04-14 00:43:10', 'active'),
(30, 6, 395, NULL, 1.00, '2026-04-14 00:43:10', 'active'),
(31, 6, 396, NULL, 1.00, '2026-04-14 00:43:10', 'active'),
(32, 6, 397, NULL, 1.00, '2026-04-14 00:43:10', 'active'),
(33, 6, 398, NULL, 1.00, '2026-04-14 00:43:10', 'active'),
(34, 6, 399, NULL, 1.00, '2026-04-14 00:43:10', 'active'),
(35, 6, 400, NULL, 1.00, '2026-04-14 00:43:10', 'active'),
(36, 6, 401, NULL, 1.00, '2026-04-14 00:43:10', 'active'),
(37, 6, 402, NULL, 1.00, '2026-04-14 00:43:10', 'active'),
(38, 6, 403, NULL, 1.00, '2026-04-14 00:43:10', 'active'),
(39, 6, 404, NULL, 1.00, '2026-04-14 00:43:10', 'active'),
(40, 6, 405, NULL, 1.00, '2026-04-14 00:43:10', 'active'),
(41, 9, 9, 2, 1.00, '2026-04-15 00:35:05', 'active'),
(43, 7, 282, 4, 12.00, '2026-04-20 00:20:39', 'active'),
(47, 6, 407, 8, 1.00, '2026-04-20 02:25:25', 'active'),
(48, 8, 407, 9, 1.00, '2026-04-20 03:44:21', 'active'),
(49, 8, 257, 10, 1.00, '2026-04-20 03:44:21', 'active');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `audit_trail`
--
ALTER TABLE `audit_trail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `buildings`
--
ALTER TABLE `buildings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `building_id` (`building_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `section_id` (`section_id`);

--
-- Indexes for table `equipment`
--
ALTER TABLE `equipment`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `equipment_issuance`
--
ALTER TABLE `equipment_issuance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inventory_id` (`inventory_id`),
  ADD KEY `issued_to` (`issued_to`),
  ADD KEY `issued_by` (`issued_by`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `property_no` (`property_no`),
  ADD KEY `section_id` (`section_id`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `verified_by` (`verified_by`),
  ADD KEY `equipment_id` (`equipment_id`),
  ADD KEY `location_id` (`location_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `current_holder` (`current_holder`),
  ADD KEY `idx_barcode` (`barcode_data`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `user_inventory`
--
ALTER TABLE `user_inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_item` (`user_id`,`inventory_id`,`status`),
  ADD KEY `inventory_id` (`inventory_id`),
  ADD KEY `issuance_id` (`issuance_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=573;

--
-- AUTO_INCREMENT for table `audit_trail`
--
ALTER TABLE `audit_trail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `buildings`
--
ALTER TABLE `buildings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `equipment`
--
ALTER TABLE `equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `equipment_issuance`
--
ALTER TABLE `equipment_issuance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=420;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `user_inventory`
--
ALTER TABLE `user_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `audit_trail`
--
ALTER TABLE `audit_trail`
  ADD CONSTRAINT `audit_trail_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `employees_ibfk_2` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `equipment_issuance`
--
ALTER TABLE `equipment_issuance`
  ADD CONSTRAINT `equipment_issuance_ibfk_1` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `equipment_issuance_ibfk_2` FOREIGN KEY (`issued_to`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `equipment_issuance_ibfk_3` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_ibfk_4` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_ibfk_5` FOREIGN KEY (`location_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_ibfk_6` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_ibfk_7` FOREIGN KEY (`current_holder`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sections`
--
ALTER TABLE `sections`
  ADD CONSTRAINT `sections_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD CONSTRAINT `system_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_inventory`
--
ALTER TABLE `user_inventory`
  ADD CONSTRAINT `user_inventory_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_inventory_ibfk_2` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_inventory_ibfk_3` FOREIGN KEY (`issuance_id`) REFERENCES `equipment_issuance` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
