-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Sep 04, 2025 at 11:28 AM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `wholesale_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `created_at`) VALUES
(1, 'Cigarettes', '2025-09-04 08:28:27'),
(2, 'Food', '2025-09-04 08:28:27');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `picture_path` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `created_by_user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `name`, `phone`, `picture_path`, `latitude`, `longitude`, `created_by_user_id`, `created_at`) VALUES
(1, 'Walk-in Customer', NULL, NULL, NULL, NULL, 2, '2025-09-04 08:28:27'),
(2, 'saikat', '01407142922', NULL, 22.35952229, 91.82616906, 2, '2025-09-04 09:17:13');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_batches`
--

CREATE TABLE `inventory_batches` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity_pcs` bigint(20) NOT NULL,
  `cost_per_pc` decimal(10,2) NOT NULL,
  `purchase_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_batches`
--

INSERT INTO `inventory_batches` (`id`, `product_id`, `quantity_pcs`, `cost_per_pc`, `purchase_date`, `created_at`) VALUES
(1, 1, 50000, 10.00, '2025-08-15', '2025-09-04 08:28:27'),
(2, 2, 40000, 9.00, '2025-08-01', '2025-09-04 08:28:27'),
(3, 3, 10000, 25.00, '2025-08-25', '2025-09-04 08:28:27'),
(4, 4, 20000, 20.00, '2025-08-25', '2025-09-04 08:28:27');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `payment_status` enum('Paid','Due') NOT NULL DEFAULT 'Paid',
  `due_date` date DEFAULT NULL,
  `total_pcs` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `cost_per_pc_at_sale` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('Pending','Completed','Cancelled') NOT NULL DEFAULT 'Completed',
  `order_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `price_per_pc` decimal(10,2) NOT NULL,
  `default_cost_per_pc` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `category_id`, `price_per_pc`, `default_cost_per_pc`, `created_at`) VALUES
(1, 'Benson & Hedges Gold', 1, 15.00, 10.00, '2025-09-04 08:28:27'),
(2, 'Marlboro Red', 1, 14.00, 9.00, '2025-09-04 08:28:27'),
(3, 'Lays Classic Chips (50g)', 2, 50.00, 25.00, '2025-09-04 08:28:27'),
(4, 'Coca-Cola Can (330ml)', 2, 40.00, 20.00, '2025-09-04 08:28:27');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('owner','manager','supervisor','sales_head','sales') NOT NULL,
  `reports_to` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `username`, `password_hash`, `role`, `reports_to`, `created_at`) VALUES
(2, 'Saikat Kumar Shil', 'saikat', '$2y$10$qIsR1qopT0KeeZ7gHl0oiuPQSnM.oQDezZ4tCfUDtxruiFzPns9bS', 'owner', NULL, '2025-09-03 11:27:52'),
(3, 'manager', 'manager', '$2y$10$8kZLK5kkabWJ4otOlZaCzuU/daB8Cp7eUcpIlO08YrgMNSQU9Y9XK', 'manager', 2, '2025-09-04 06:09:39'),
(4, 'supervisor', 'supervisor', '$2y$10$SXwY4MMePR.OJ2TqXqVkL.CChgcP05Q6tizeTaDxYCZyYAuz1VynG', 'supervisor', 3, '2025-09-04 06:18:38'),
(5, 'sales head', 'saleshead', '$2y$10$2VFTfBnhLUac.qa6c4C6le7jF24k.FrREaCRUr.1XTpI5Avhlfkrm', 'sales_head', 4, '2025-09-04 06:19:00'),
(6, 'sales', 'sales', '$2y$10$MwoiJcTuJpBcWb.GWlQK5.B9X9ReQWHx.xOlQg74Nz99/ohdpIVXa', 'sales', 5, '2025-09-04 06:19:18'),
(7, 'owner', 'owner', '$2y$10$pRZRjhHyCTEVRtW5tyJLC.3.ZEbzdczp31KpMBMXWYo34k9VAnYby', 'owner', 2, '2025-09-04 09:25:28');

-- --------------------------------------------------------

--
-- Table structure for table `user_category_access`
--

CREATE TABLE `user_category_access` (
  `user_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_category_access`
--

INSERT INTO `user_category_access` (`user_id`, `category_id`) VALUES
(2, 1),
(2, 2),
(3, 1),
(4, 1),
(6, 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by_user_id` (`created_by_user_id`);

--
-- Indexes for table `inventory_batches`
--
ALTER TABLE `inventory_batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_category` (`category_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `reports_to` (`reports_to`);

--
-- Indexes for table `user_category_access`
--
ALTER TABLE `user_category_access`
  ADD PRIMARY KEY (`user_id`,`category_id`),
  ADD KEY `category_id` (`category_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `inventory_batches`
--
ALTER TABLE `inventory_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory_batches`
--
ALTER TABLE `inventory_batches`
  ADD CONSTRAINT `inventory_batches_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`reports_to`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_category_access`
--
ALTER TABLE `user_category_access`
  ADD CONSTRAINT `user_category_access_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_category_access_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
