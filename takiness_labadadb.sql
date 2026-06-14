-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 14, 2026 at 09:36 AM
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
-- Database: `takiness_labadadb`
--

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `expense_id` int(11) NOT NULL,
  `date_incurred` date NOT NULL,
  `expense_type` enum('Rental','Salaries','Electricity','Gas Tank','Supplies') NOT NULL,
  `description` varchar(500) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`expense_id`, `date_incurred`, `expense_type`, `description`, `amount`, `remarks`, `created_at`) VALUES
(1, '2026-05-01', 'Rental', 'Monthly shop rental - May 2026', 6000.00, 'Paid to landlord', '2026-06-14 06:42:50'),
(2, '2026-05-03', 'Salaries', 'Staff salary - Maria Santos', 4500.00, 'Bi-weekly pay', '2026-06-14 06:42:50'),
(3, '2026-05-03', 'Electricity', 'Meralco electric bill - April', 2180.00, NULL, '2026-06-14 06:42:50'),
(4, '2026-05-02', 'Gas Tank', 'LPG replacement - 1 tank', 950.00, '2nd gas tank', '2026-06-14 06:42:50'),
(5, '2026-05-01', 'Supplies', 'Liquid detergent restock 5L', 550.00, 'Puregold', '2026-06-14 06:42:50'),
(6, '2026-06-14', 'Electricity', 'Month on may', 1000.00, NULL, '2026-06-14 07:34:29');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `inventory_id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `unit` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `max_quantity` int(11) NOT NULL DEFAULT 0,
  `icon` varchar(10) NOT NULL DEFAULT '?',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`inventory_id`, `item_name`, `unit`, `quantity`, `max_quantity`, `icon`, `updated_at`) VALUES
(1, 'Liquid Detergent', 'per liter', 18, 30, '🧴', '2026-06-14 06:42:50'),
(2, 'Fabric Conditioner', 'per liter', 14, 20, '🌸', '2026-06-14 06:47:41'),
(3, 'Plastic Bags', 'per piece', 44, 100, '🛍️', '2026-06-14 06:42:50'),
(4, 'Scotch Tape', 'per roll', 3, 10, '📦', '2026-06-14 06:42:50'),
(5, 'Gas Tank', 'per tank', 7, 5, '🔴', '2026-06-14 07:28:53'),
(6, 'Fabric Spray', 'per bottle', 7, 15, '💧', '2026-06-14 06:42:50');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_logs`
--

CREATE TABLE `inventory_logs` (
  `log_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `add_quantity` int(11) NOT NULL,
  `date_restocked` date NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_logs`
--

INSERT INTO `inventory_logs` (`log_id`, `inventory_id`, `add_quantity`, `date_restocked`, `remarks`, `created_at`) VALUES
(1, 5, 1, '2026-04-28', 'Gas Tank (+1)', '2026-06-14 06:42:50'),
(2, 1, 5, '2026-05-01', 'Liquid Detergent (+5L)', '2026-06-14 06:42:50'),
(3, 6, 3, '2026-05-02', 'Fabric Spray (+3 btls)', '2026-06-14 06:42:50'),
(4, 2, 2, '2026-06-14', NULL, '2026-06-14 06:47:41'),
(5, 5, 5, '2026-06-14', NULL, '2026-06-14 07:28:53');

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `sale_id` int(11) NOT NULL,
  `transaction_date` date NOT NULL,
  `transaction_time` time NOT NULL,
  `service_type` enum('Wash + Dry + Fold','Wash + Dry','Wash Only') NOT NULL,
  `num_loads` int(11) NOT NULL DEFAULT 1,
  `fabric_spray` tinyint(1) NOT NULL DEFAULT 0,
  `amount_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `cash_on_hand` decimal(10,2) NOT NULL DEFAULT 0.00,
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`sale_id`, `transaction_date`, `transaction_time`, `service_type`, `num_loads`, `fabric_spray`, `amount_paid`, `cash_on_hand`, `recorded_by`, `created_at`) VALUES
(1, '2026-06-14', '09:15:00', 'Wash + Dry + Fold', 3, 1, 350.00, 350.00, 1, '2026-06-14 06:42:50'),
(2, '2026-06-14', '10:30:00', 'Wash + Dry', 2, 0, 200.00, 550.00, 2, '2026-06-14 06:42:50'),
(3, '2026-06-14', '13:45:00', 'Wash + Dry + Fold', 5, 1, 600.00, 1150.00, 2, '2026-06-14 06:42:50'),
(4, '2026-06-14', '15:00:00', 'Wash Only', 2, 0, 160.00, 1310.00, 1, '2026-06-14 06:42:50'),
(5, '2026-06-14', '08:49:00', 'Wash + Dry + Fold', 1, 1, 120.00, 120.00, 1, '2026-06-14 06:49:50'),
(6, '2026-06-14', '09:27:00', 'Wash Only', 1, 1, 120.00, 120.00, 2, '2026-06-14 07:27:48');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('shop_address', 'Brgy. Sample, Antique'),
('shop_contact', '0967 9045 995'),
('shop_name', 'Takines Labada');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('owner','staff') NOT NULL DEFAULT 'staff',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `password`, `role`, `is_active`, `created_at`) VALUES
(1, 'Judith D. Piano', 'owner', '$2b$10$Qhj8HHP.Ill7VIDFS5OYz.eQhPE86ypo39.ub5jD38wUTIeM6t6xu', 'owner', 1, '2026-06-14 06:42:50'),
(2, 'Maria Santos', 'staff', '$2b$10$Qhj8HHP.Ill7VIDFS5OYz.eQhPE86ypo39.ub5jD38wUTIeM6t6xu', 'staff', 1, '2026-06-14 06:42:50');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`expense_id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`inventory_id`);

--
-- Indexes for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `fk_log_inventory` (`inventory_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`sale_id`),
  ADD KEY `fk_sales_user` (`recorded_by`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `expense_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `inventory_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `sale_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  ADD CONSTRAINT `fk_log_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`inventory_id`) ON DELETE CASCADE;

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `fk_sales_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
