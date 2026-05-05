-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 22, 2026 at 05:00 AM
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

-- --------------------------------------------------------

--
-- Table structure for table `buildings`
--

CREATE TABLE `buildings` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `floor` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `building_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int(11) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_trail`
--
ALTER TABLE `audit_trail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `buildings`
--
ALTER TABLE `buildings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `equipment`
--
ALTER TABLE `equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `equipment_issuance`
--
ALTER TABLE `equipment_issuance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_inventory`
--
ALTER TABLE `user_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
