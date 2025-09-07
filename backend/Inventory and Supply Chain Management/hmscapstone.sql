-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Sep 01, 2025 at 11:36 AM
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
-- Database: `hmscapstone`
--

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `item_type` varchar(100) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `sub_type` varchar(100) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `expiration_date` date DEFAULT NULL,
  `received_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `location` varchar(100) DEFAULT 'Main Storage',
  `min_stock` int(11) DEFAULT 0,
  `max_stock` int(11) DEFAULT 9999
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `item_id`, `item_name`, `item_type`, `category`, `sub_type`, `quantity`, `price`, `expiration_date`, `received_at`, `location`, `min_stock`, `max_stock`) VALUES
(6, 3, 'Biogesic', 'Medications and pharmacy supplies', NULL, 'Solid', 301, 2250.00, '2030-09-01', '2025-08-30 13:51:16', 'Main Storage', 0, 9999),
(7, 4, 'Neozep', 'Medications and pharmacy supplies', NULL, 'Solid', 201, 1000.00, '2030-01-01', '2025-08-30 13:51:16', 'Main Storage', 0, 9999),
(8, 2, 'Personal Computer', 'IT and supporting tech', NULL, '', 41, 60000.00, NULL, '2025-08-30 13:51:16', 'Main Storage', 0, 9999),
(9, 5, 'XRAY MACHINE', 'Diagnostic Equipment', NULL, '', 8, 65000.00, NULL, '2025-08-30 10:38:07', 'Main Storage', 0, 9999);

-- --------------------------------------------------------

--
-- Table structure for table `iscmlogin`
--

CREATE TABLE `iscmlogin` (
  `id` int(11) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `iscmlogin`
--

INSERT INTO `iscmlogin` (`id`, `fullname`, `username`, `email`, `password`, `created_at`) VALUES
(1, 'Christian Rodriguez', 'Admin', 'khiane01111@gmail.com', '$2y$10$IFSEXawcu9G56.FCUElUzu3NzyQ9kmDVE6Uel6eE.cnnDDjI7f8lW', '2025-08-08 14:55:15');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_requests`
--

CREATE TABLE `purchase_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`items`)),
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `receipts`
--

CREATE TABLE `receipts` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `vat` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `receipt_items`
--

CREATE TABLE `receipt_items` (
  `id` int(11) NOT NULL,
  `receipt_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `quantity_received` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `receipt_payments`
--

CREATE TABLE `receipt_payments` (
  `id` int(11) NOT NULL,
  `receipt_id` int(11) NOT NULL,
  `status` enum('Pending','Paid') DEFAULT 'Pending',
  `paid_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_adjustments`
--

CREATE TABLE `stock_adjustments` (
  `id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `old_quantity` int(11) NOT NULL,
  `new_quantity` int(11) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `adjusted_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vendors`
--

CREATE TABLE `vendors` (
  `id` int(11) NOT NULL,
  `registration_number` varchar(50) DEFAULT NULL,
  `company_name` varchar(255) NOT NULL,
  `company_address` text NOT NULL,
  `contact_name` varchar(255) NOT NULL,
  `contact_title` varchar(255) DEFAULT NULL,
  `phone` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `tin_vat` varchar(100) NOT NULL,
  `primary_product_categories` text NOT NULL,
  `country` varchar(100) NOT NULL,
  `website` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `approved_at` datetime DEFAULT NULL,
  `contract_end_date` datetime DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vendors`
--

INSERT INTO `vendors` (`id`, `registration_number`, `company_name`, `company_address`, `contact_name`, `contact_title`, `phone`, `email`, `tin_vat`, `primary_product_categories`, `country`, `website`, `status`, `approved_at`, `contract_end_date`, `username`, `password`, `created_at`) VALUES
(1, 'VEND-20250813-E4AB', 'Strongholds Incorporation', 'Bulacan', 'Admin', 'Manager', '564654654654', 'Stronghold@gmail.com', '654654654654657', 'Medicine', 'Philippines', 'Stronghold.com', 'Approved', '2025-08-24 13:51:17', '2026-02-24 14:11:10', 'admin', '$2y$10$neICy6sMVeq3rvzNSXRiv.uUYfZMo2VB2s0yWH6aT4BaHM3R5Tjou', '2025-08-13 09:40:51'),
(2, 'VEND-20250824-8611', 'bcp', 'rd1', 'Admin', 'head', '654654654654654', 'bcp@gmail.com', '4654654654654654', 'Medicine', 'Philippines', '', 'Approved', '2025-08-25 15:32:21', '2026-02-25 15:32:21', 'kian', '$2y$10$iZmQ2BZTGRPEix4gCO3JQe9aCMTTbfu6vyT2Qmj5.jFzqMJXK4VM6', '2025-08-24 12:19:57');

-- --------------------------------------------------------

--
-- Table structure for table `vendor_acknowledgments`
--

CREATE TABLE `vendor_acknowledgments` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `accept_use_policy` tinyint(1) DEFAULT 0,
  `comply_procurement_policy` tinyint(1) DEFAULT 0,
  `due_diligence_consent` tinyint(1) DEFAULT 0,
  `data_processing_consent` tinyint(1) DEFAULT 0,
  `contract_terms_accepted` tinyint(1) DEFAULT 0,
  `warranty_terms_accepted` tinyint(1) DEFAULT 0,
  `disposal_policy_accepted` tinyint(1) DEFAULT 0,
  `info_certified` tinyint(1) DEFAULT 0,
  `authorized_name` varchar(255) DEFAULT NULL,
  `authorized_title` varchar(255) DEFAULT NULL,
  `signed_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vendor_acknowledgments`
--

INSERT INTO `vendor_acknowledgments` (`id`, `vendor_id`, `accept_use_policy`, `comply_procurement_policy`, `due_diligence_consent`, `data_processing_consent`, `contract_terms_accepted`, `warranty_terms_accepted`, `disposal_policy_accepted`, `info_certified`, `authorized_name`, `authorized_title`, `signed_date`) VALUES
(1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 'awdawdawd', 'awdawdawdwad', '2025-08-13'),
(2, 2, 1, 1, 1, 1, 1, 1, 1, 1, 'admin', 'head', '2025-08-24');

-- --------------------------------------------------------

--
-- Table structure for table `vendor_documents`
--

CREATE TABLE `vendor_documents` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `doc_type` varchar(100) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vendor_documents`
--

INSERT INTO `vendor_documents` (`id`, `vendor_id`, `doc_type`, `file_path`, `uploaded_at`) VALUES
(1, 1, 'Compliance Document', 'uploads/689c5da39fc11_Kian,COR.pdf', '2025-08-13 09:40:51'),
(2, 2, 'Compliance Document', 'uploads/68ab036dbb69e_COLLEGE _SCHEDULE.xlsx', '2025-08-24 12:19:57');

-- --------------------------------------------------------

--
-- Table structure for table `vendor_orders`
--

CREATE TABLE `vendor_orders` (
  `id` int(11) NOT NULL,
  `purchase_request_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `status` enum('Pending','Processing','Shipped','Completed') DEFAULT 'Pending',
  `checklist` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`checklist`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_products`
--

CREATE TABLE `vendor_products` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `item_description` text NOT NULL,
  `item_type` varchar(100) NOT NULL,
  `sub_type` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `picture` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vendor_products`
--

INSERT INTO `vendor_products` (`id`, `vendor_id`, `item_name`, `item_description`, `item_type`, `sub_type`, `price`, `picture`, `created_at`) VALUES
(2, 1, 'Personal Computer', '16 GB RAM 2TB STORAGE', 'IT and supporting tech', '', 60000.00, 'uploads/1755532575_656061.jpg', '2025-08-18 15:56:15'),
(3, 1, 'Biogesic', '500 MG 500 TABS', 'Medications and pharmacy supplies', 'Solid', 2250.00, 'uploads/1755532622_656061.jpg', '2025-08-18 15:57:02'),
(4, 1, 'Neozep', 'Generic', 'Medications and pharmacy supplies', 'Solid', 1000.00, 'uploads/1755690668_aesthetic-computer-4k-2o1einarkegqjjj5.jpg', '2025-08-20 11:51:08'),
(5, 1, 'XRAY MACHINE', 'ORIGINAL', 'Diagnostic Equipment', '', 65000.00, 'uploads/1756016161_aesthetic-computer-4k-2o1einarkegqjjj5.jpg', '2025-08-24 06:16:01'),
(6, 2, 'IV Catheter / IV Cannula, SURGITECH', 'Packaging: 1 Box 100’s', 'Diagnostic Equipment', '', 981.00, 'uploads/1756108032_IV CATHERER.jpg', '2025-08-25 07:47:12');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `item_id` (`item_id`);

--
-- Indexes for table `iscmlogin`
--
ALTER TABLE `iscmlogin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `receipts`
--
ALTER TABLE `receipts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `receipt_items`
--
ALTER TABLE `receipt_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `receipt_payments`
--
ALTER TABLE `receipt_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `receipt_id` (`receipt_id`);

--
-- Indexes for table `stock_adjustments`
--
ALTER TABLE `stock_adjustments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inventory_id` (`inventory_id`);

--
-- Indexes for table `vendors`
--
ALTER TABLE `vendors`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `vendor_acknowledgments`
--
ALTER TABLE `vendor_acknowledgments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendor_id` (`vendor_id`);

--
-- Indexes for table `vendor_documents`
--
ALTER TABLE `vendor_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendor_id` (`vendor_id`);

--
-- Indexes for table `vendor_orders`
--
ALTER TABLE `vendor_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_request_id` (`purchase_request_id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `vendor_products`
--
ALTER TABLE `vendor_products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendor_id` (`vendor_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `iscmlogin`
--
ALTER TABLE `iscmlogin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `receipts`
--
ALTER TABLE `receipts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `receipt_items`
--
ALTER TABLE `receipt_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `receipt_payments`
--
ALTER TABLE `receipt_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `stock_adjustments`
--
ALTER TABLE `stock_adjustments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendors`
--
ALTER TABLE `vendors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `vendor_acknowledgments`
--
ALTER TABLE `vendor_acknowledgments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `vendor_documents`
--
ALTER TABLE `vendor_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `vendor_orders`
--
ALTER TABLE `vendor_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `vendor_products`
--
ALTER TABLE `vendor_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `receipt_payments`
--
ALTER TABLE `receipt_payments`
  ADD CONSTRAINT `receipt_payments_ibfk_1` FOREIGN KEY (`receipt_id`) REFERENCES `receipts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_adjustments`
--
ALTER TABLE `stock_adjustments`
  ADD CONSTRAINT `stock_adjustments_ibfk_1` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vendor_acknowledgments`
--
ALTER TABLE `vendor_acknowledgments`
  ADD CONSTRAINT `vendor_acknowledgments_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vendor_documents`
--
ALTER TABLE `vendor_documents`
  ADD CONSTRAINT `vendor_documents_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vendor_orders`
--
ALTER TABLE `vendor_orders`
  ADD CONSTRAINT `vendor_orders_ibfk_1` FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`),
  ADD CONSTRAINT `vendor_orders_ibfk_2` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`),
  ADD CONSTRAINT `vendor_orders_ibfk_3` FOREIGN KEY (`item_id`) REFERENCES `vendor_products` (`id`);

--
-- Constraints for table `vendor_products`
--
ALTER TABLE `vendor_products`
  ADD CONSTRAINT `vendor_products_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
