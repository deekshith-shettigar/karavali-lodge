SET FOREIGN_KEY_CHECKS=0;

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 06, 2026 at 04:51 PM
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
-- Database: `karavali_lodge`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `password_reset_otps`;
DROP TABLE IF EXISTS `hk_staff`;
DROP TABLE IF EXISTS `service_menu`;
DROP TABLE IF EXISTS `room_service`;
DROP TABLE IF EXISTS `rooms`;
DROP TABLE IF EXISTS `online_booking_requests`;
DROP TABLE IF EXISTS `night_audits`;
DROP TABLE IF EXISTS `login_attempts`;
DROP TABLE IF EXISTS `id_proofs`;
DROP TABLE IF EXISTS `housekeeping`;
DROP TABLE IF EXISTS `guest_services`;
DROP TABLE IF EXISTS `guests`;
DROP TABLE IF EXISTS `contact_messages`;
DROP TABLE IF EXISTS `checkins`;
DROP TABLE IF EXISTS `bookings`;
DROP TABLE IF EXISTS `bills`;
DROP TABLE IF EXISTS `admins`;

CREATE TABLE IF NOT EXISTS `admins` (
  `id` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `mobile` varchar(15) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'admin',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `name`, `mobile`, `email`, `password_hash`, `role`, `created_at`, `updated_at`) VALUES
('9d81023d-0973-4af9-b8be-5292d249d4f6', 'Deekshith Shettigar', '9019509469', 'deekshith3838@gmail.com', '$2y$10$hY.GtUrWreinoeuAtbp2RuLZBL.Q.ojMsR.Zas.oyFAsltZEGKq8C', 'admin', '2026-06-06 15:49:29', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `bills`
--

CREATE TABLE IF NOT EXISTS `bills` (
  `id` varchar(50) NOT NULL,
  `bill_no` varchar(30) DEFAULT NULL,
  `checkin_id` varchar(50) DEFAULT NULL,
  `guest_id` varchar(50) DEFAULT NULL,
  `guest_name` varchar(100) DEFAULT NULL,
  `mobile` varchar(15) DEFAULT NULL,
  `room_id` varchar(50) DEFAULT NULL,
  `check_in` date DEFAULT NULL,
  `check_out` date DEFAULT NULL,
  `nights` int(11) DEFAULT 1,
  `room_charges` decimal(10,2) DEFAULT 0.00,
  `service_charges` decimal(10,2) DEFAULT 0.00,
  `amenity_charges` decimal(10,2) DEFAULT 0.00,
  `tax` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `advance` decimal(10,2) DEFAULT 0.00,
  `balance` decimal(10,2) DEFAULT 0.00,
  `status` enum('Paid','Unpaid') DEFAULT 'Unpaid',
  `paid_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE IF NOT EXISTS `bookings` (
  `id` varchar(50) NOT NULL,
  `booking_no` varchar(20) DEFAULT NULL,
  `guest_name` varchar(100) NOT NULL,
  `mobile` varchar(15) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `room_id` varchar(50) DEFAULT NULL,
  `room_number` varchar(10) DEFAULT NULL,
  `room_type` varchar(50) DEFAULT NULL,
  `booking_type` enum('Walk-in','Online','Advance Reservation') DEFAULT 'Online',
  `check_in` date NOT NULL,
  `check_out` date NOT NULL,
  `checkin_time` varchar(50) DEFAULT NULL,
  `checkout_time` varchar(50) DEFAULT NULL,
  `num_guests` int(11) DEFAULT 1,
  `status` enum('Pending','Confirmed','Checked-In','Completed','Cancelled') DEFAULT 'Pending',
  `advance` decimal(10,2) DEFAULT 0.00,
  `advance_paid` decimal(10,2) DEFAULT 0.00,
  `payment_id` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `special_requests` text DEFAULT NULL,
  `payment_status` enum('Pending','Paid','Partial') DEFAULT 'Pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `checkins`
--

CREATE TABLE IF NOT EXISTS `checkins` (
  `id` varchar(50) NOT NULL,
  `guest_name` varchar(100) NOT NULL,
  `mobile` varchar(15) NOT NULL,
  `room_id` varchar(50) DEFAULT NULL,
  `booking_id` varchar(50) DEFAULT NULL,
  `check_in_date` date DEFAULT NULL,
  `check_in_time` datetime DEFAULT NULL,
  `expected_check_out` date DEFAULT NULL,
  `check_out_date` date DEFAULT NULL,
  `check_out_time` datetime DEFAULT NULL,
  `id_proof_type` varchar(50) DEFAULT NULL,
  `id_number` varchar(30) DEFAULT NULL,
  `advance` decimal(10,2) DEFAULT 0.00,
  `num_guests` int(11) DEFAULT 1,
  `status` enum('Checked-In','Checked-Out') DEFAULT 'Checked-In',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `mobile` varchar(15) DEFAULT NULL,
  `subject` varchar(200) DEFAULT NULL,
  `message` text NOT NULL,
  `status` enum('New','Read','Replied') DEFAULT 'New',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `guests`
--

CREATE TABLE IF NOT EXISTS `guests` (
  `id` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `mobile` varchar(15) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `nationality` varchar(50) DEFAULT 'Indian',
  `address` text DEFAULT NULL,
  `id_proof_type` varchar(50) DEFAULT NULL,
  `id_proof_number` varchar(30) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `guests`
--

INSERT INTO `guests` (`id`, `name`, `mobile`, `email`, `nationality`, `address`, `id_proof_type`, `id_proof_number`, `password_hash`, `created_at`, `updated_at`) VALUES
('05f019d8-08d7-419b-a938-2eeadce6ce91', 'Rahul', '9019509467', 'deekshith3838@gmail.com', 'Indian', 'Kundapura', NULL, NULL, '$2y$10$51cdNUdaBFgZwyUxgmX./u09E1Uuvqcp39Ua/03oeSzMhaFchWiVO', '2026-06-06 14:36:49', '2026-06-06 14:36:49');

-- --------------------------------------------------------

--
-- Table structure for table `guest_services`
--

CREATE TABLE IF NOT EXISTS `guest_services` (
  `id` varchar(50) NOT NULL,
  `checkin_id` varchar(50) DEFAULT NULL,
  `service_id` varchar(50) DEFAULT NULL,
  `service_name` varchar(100) DEFAULT NULL,
  `charge` decimal(10,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `housekeeping`
--

CREATE TABLE IF NOT EXISTS `housekeeping` (
  `id` varchar(50) NOT NULL,
  `room_id` varchar(50) DEFAULT NULL,
  `status` enum('Dirty','Cleaning','Clean','Maintenance') DEFAULT 'Dirty',
  `assigned_to` varchar(50) DEFAULT NULL,
  `priority` enum('Normal','High','Urgent') DEFAULT 'Normal',
  `assigned_date` date DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `id_proofs`
--

CREATE TABLE IF NOT EXISTS `id_proofs` (
  `id` varchar(50) NOT NULL,
  `guest_id` varchar(50) DEFAULT NULL,
  `id_type` varchar(50) DEFAULT NULL,
  `id_number` varchar(30) DEFAULT NULL,
  `photo` longtext DEFAULT NULL,
  `photo_back` longtext DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int(11) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `mobile` varchar(20) NOT NULL DEFAULT '',
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `night_audits`
--

CREATE TABLE IF NOT EXISTS `night_audits` (
  `id` varchar(50) NOT NULL,
  `audit_date` date DEFAULT NULL,
  `occupied_rooms` int(11) DEFAULT 0,
  `reserved_rooms` int(11) DEFAULT 0,
  `available_rooms` int(11) DEFAULT 0,
  `total_rooms` int(11) DEFAULT 0,
  `occupancy_rate` decimal(5,2) DEFAULT 0.00,
  `room_revenue` decimal(10,2) DEFAULT 0.00,
  `service_revenue` decimal(10,2) DEFAULT 0.00,
  `amenity_revenue` decimal(10,2) DEFAULT 0.00,
  `tax_collected` decimal(10,2) DEFAULT 0.00,
  `total_revenue` decimal(10,2) DEFAULT 0.00,
  `paid_amount` decimal(10,2) DEFAULT 0.00,
  `unpaid_amount` decimal(10,2) DEFAULT 0.00,
  `total_outstanding` decimal(10,2) DEFAULT 0.00,
  `bill_count` int(11) DEFAULT 0,
  `today_checkins` int(11) DEFAULT 0,
  `today_checkouts` int(11) DEFAULT 0,
  `new_bookings` int(11) DEFAULT 0,
  `discrepancies` int(11) DEFAULT 0,
  `discrepancy_details` text DEFAULT NULL,
  `audit_time` datetime DEFAULT NULL,
  `status` enum('Open','Closed') DEFAULT 'Open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `online_booking_requests`
--

CREATE TABLE IF NOT EXISTS `online_booking_requests` (
  `id` int(11) NOT NULL,
  `request_no` varchar(30) DEFAULT NULL,
  `guest_name` varchar(100) NOT NULL,
  `mobile` varchar(15) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `room_type` varchar(50) DEFAULT NULL,
  `room_id` varchar(50) DEFAULT NULL,
  `room_number` varchar(10) DEFAULT NULL,
  `check_in` date NOT NULL,
  `check_out` date NOT NULL,
  `checkin_time` varchar(50) DEFAULT NULL,
  `checkout_time` varchar(50) DEFAULT NULL,
  `num_adults` int(11) DEFAULT 1,
  `num_children` int(11) DEFAULT 0,
  `special_requests` text DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `payment_status` varchar(20) DEFAULT 'Pending',
  `payment_id` varchar(100) DEFAULT NULL,
  `payment_order_id` varchar(100) DEFAULT NULL,
  `advance_paid` decimal(10,2) DEFAULT 0.00,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `status` enum('Pending','Confirmed','Rejected','Cancelled') DEFAULT 'Pending',
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE IF NOT EXISTS `rooms` (
  `id` varchar(50) NOT NULL,
  `room_number` varchar(10) NOT NULL,
  `room_type` enum('Single','Double','Deluxe','Suite','Family','Dormitory') NOT NULL,
  `floor` varchar(10) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `capacity` int(11) DEFAULT 2,
  `status` enum('Available','Occupied','Reserved','Cleaning','Maintenance') DEFAULT 'Available',
  `amenities` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `room_number`, `room_type`, `floor`, `price`, `capacity`, `status`, `amenities`, `description`, `image_url`, `created_at`, `updated_at`) VALUES
('r001', '101', 'Single', '1', 1200.00, 1, 'Available', 'WiFi, TV, AC', 'Comfortable single room with all basic amenities. Perfect for solo travelers.', NULL, '2026-06-06 10:07:03', '2026-06-06 10:07:03'),
('r002', '102', 'Single', '1', 1200.00, 1, 'Available', 'WiFi, TV, AC', 'Cozy single room on the ground floor with easy access.', NULL, '2026-06-06 10:07:03', '2026-06-06 10:07:03'),
('r003', '103', 'Double', '1', 2000.00, 2, 'Available', 'WiFi, TV, AC, Mini-bar', 'Spacious double room with mini-bar and city view.', NULL, '2026-06-06 10:07:03', '2026-06-06 10:07:03'),
('r004', '104', 'Double', '1', 2000.00, 2, 'Available', 'WiFi, TV, AC, Mini-bar', 'Well-appointed double room with modern furnishings.', NULL, '2026-06-06 10:07:03', '2026-06-06 10:07:03'),
('r005', '201', 'Deluxe', '2', 3500.00, 2, 'Available', 'WiFi, TV, AC, Mini-bar, Balcony', 'Premium deluxe room with private balcony overlooking the garden.', NULL, '2026-06-06 10:07:03', '2026-06-06 10:07:03'),
('r006', '202', 'Deluxe', '2', 3500.00, 2, 'Available', 'WiFi, TV, AC, Mini-bar, Balcony', 'Elegant deluxe room with superior finishing and balcony.', NULL, '2026-06-06 10:07:03', '2026-06-06 10:07:03'),
('r007', '203', 'Suite', '2', 5500.00, 3, 'Available', 'WiFi, TV, AC, Mini-bar, Balcony, Living Room', 'Luxurious suite with separate living room and premium amenities.', NULL, '2026-06-06 10:07:03', '2026-06-06 10:07:03'),
('r008', '204', 'Family', '2', 4500.00, 4, 'Available', 'WiFi, TV, AC, Extra Beds', 'Ideal family room with extra beds and ample space for families.', NULL, '2026-06-06 10:07:03', '2026-06-06 10:07:03'),
('r009', '301', 'Suite', '3', 6000.00, 3, 'Available', 'WiFi, TV, AC, Mini-bar, Jacuzzi, Lake View', 'Signature suite with jacuzzi and breathtaking lake view.', NULL, '2026-06-06 10:07:03', '2026-06-06 10:07:03'),
('r010', '302', 'Deluxe', '3', 3500.00, 2, 'Available', 'WiFi, TV, AC, Mini-bar', 'Top-floor deluxe room with panoramic views.', NULL, '2026-06-06 10:07:03', '2026-06-06 10:07:03'),
('r011', '303', 'Double', '3', 2200.00, 2, 'Available', 'WiFi, TV, AC', 'Top-floor double room with fresh air and open views.', NULL, '2026-06-06 10:07:03', '2026-06-06 10:07:03'),
('r012', '304', 'Single', '3', 1500.00, 1, 'Available', 'WiFi, TV, AC', 'Peaceful single room on top floor.', NULL, '2026-06-06 10:07:03', '2026-06-06 10:07:03'),
('r013', 'D01', 'Dormitory', 'G', 600.00, 6, 'Available', 'WiFi, Fan', 'Budget-friendly dormitory with shared facilities.', NULL, '2026-06-06 10:07:03', '2026-06-06 10:07:03'),
('r014', 'D02', 'Dormitory', 'G', 600.00, 6, 'Available', 'WiFi, Fan', 'Clean and comfortable dormitory accommodation.', NULL, '2026-06-06 10:07:03', '2026-06-06 10:07:03');

-- --------------------------------------------------------

--
-- Table structure for table `room_service`
--

CREATE TABLE IF NOT EXISTS `room_service` (
  `id` varchar(50) NOT NULL,
  `order_no` varchar(20) DEFAULT NULL,
  `checkin_id` varchar(50) DEFAULT NULL,
  `room_id` varchar(50) DEFAULT NULL,
  `guest_name` varchar(100) DEFAULT NULL,
  `items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`items`)),
  `total` decimal(10,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `status` enum('Pending','Preparing','Delivered','Cancelled') DEFAULT 'Pending',
  `order_time` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_menu`
--

CREATE TABLE IF NOT EXISTS `service_menu` (
  `id` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_menu`
--

INSERT INTO `service_menu` (`id`, `name`, `category`, `price`, `created_at`) VALUES
('s001', 'Laundry', 'Housekeeping', 200.00, '2026-06-06 10:07:03'),
('s002', 'Extra Bed', 'Room', 500.00, '2026-06-06 10:07:03'),
('s003', 'Airport Pickup', 'Transport', 1500.00, '2026-06-06 10:07:03'),
('s004', 'Airport Drop', 'Transport', 1500.00, '2026-06-06 10:07:03'),
('s005', 'WiFi Premium', 'Technology', 100.00, '2026-06-06 10:07:03'),
('s006', 'Spa - Basic', 'Wellness', 800.00, '2026-06-06 10:07:03'),
('s007', 'Spa - Premium', 'Wellness', 1500.00, '2026-06-06 10:07:03'),
('s008', 'Mini Bar Refill', 'Food', 300.00, '2026-06-06 10:07:03'),
('s009', 'Early Check-In', 'Room', 500.00, '2026-06-06 10:07:03'),
('s010', 'Late Check-Out', 'Room', 500.00, '2026-06-06 10:07:03'),
('s011', 'Iron & Board', 'Housekeeping', 100.00, '2026-06-06 10:07:03'),
('s012', 'Baby Crib', 'Room', 300.00, '2026-06-06 10:07:03');

--

--
-- Table structure for table `hk_staff`
--

CREATE TABLE IF NOT EXISTS `hk_staff` (
  `id` varchar(64) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category` varchar(50) NOT NULL DEFAULT 'Cleaning',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `password_reset_otps`
--

CREATE TABLE IF NOT EXISTS `password_reset_otps` (
  `id` int(11) NOT NULL,
  `mobile` varchar(20) NOT NULL,
  `otp` varchar(255) NOT NULL,
  `attempts` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mobile` (`mobile`);

--
-- Indexes for table `bills`
--
ALTER TABLE `bills`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bill_no` (`bill_no`),
  ADD KEY `checkin_id` (`checkin_id`),
  ADD KEY `guest_id` (`guest_id`),
  ADD KEY `room_id` (`room_id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_no` (`booking_no`),
  ADD KEY `room_id` (`room_id`),
  ADD KEY `idx_mobile` (`mobile`),
  ADD KEY `idx_check_dates` (`check_in`,`check_out`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `checkins`
--
ALTER TABLE `checkins`
  ADD PRIMARY KEY (`id`),
  ADD KEY `room_id` (`room_id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `guests`
--
ALTER TABLE `guests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `guest_services`
--
ALTER TABLE `guest_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `checkin_id` (`checkin_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `housekeeping`
--
ALTER TABLE `housekeeping`
  ADD PRIMARY KEY (`id`),
  ADD KEY `room_id` (`room_id`);

--
-- Indexes for table `id_proofs`
--
ALTER TABLE `id_proofs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `guest_id` (`guest_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip_time` (`ip`,`attempted_at`);

--
-- Indexes for table `night_audits`
--
ALTER TABLE `night_audits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `audit_date` (`audit_date`);

--
-- Indexes for table `online_booking_requests`
--
ALTER TABLE `online_booking_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_no` (`request_no`),
  ADD KEY `idx_mobile` (`mobile`),
  ADD KEY `idx_room_dates` (`room_id`,`check_in`,`check_out`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `room_number` (`room_number`);

--
-- Indexes for table `room_service`
--
ALTER TABLE `room_service`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_no` (`order_no`),
  ADD KEY `checkin_id` (`checkin_id`),
  ADD KEY `room_id` (`room_id`);

--
-- Indexes for table `service_menu`
--
ALTER TABLE `service_menu`
  ADD PRIMARY KEY (`id`);

--
--
-- Indexes for table `hk_staff`
--
ALTER TABLE `hk_staff`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_reset_otps`
--
ALTER TABLE `password_reset_otps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mobile` (`mobile`),
  ADD KEY `idx_expires` (`expires_at`);

-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `online_booking_requests`
--
ALTER TABLE `online_booking_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bills`
--
ALTER TABLE `bills`
  ADD CONSTRAINT `bills_ibfk_1` FOREIGN KEY (`checkin_id`) REFERENCES `checkins` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `bills_ibfk_2` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `bills_ibfk_3` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `checkins`
--
ALTER TABLE `checkins`
  ADD CONSTRAINT `checkins_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `checkins_ibfk_2` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `guest_services`
--
ALTER TABLE `guest_services`
  ADD CONSTRAINT `guest_services_ibfk_1` FOREIGN KEY (`checkin_id`) REFERENCES `checkins` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `guest_services_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `service_menu` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `housekeeping`
--
ALTER TABLE `housekeeping`
  ADD CONSTRAINT `housekeeping_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `id_proofs`
--
ALTER TABLE `id_proofs`
  ADD CONSTRAINT `id_proofs_ibfk_1` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `room_service`
--
ALTER TABLE `room_service`
  ADD CONSTRAINT `room_service_ibfk_1` FOREIGN KEY (`checkin_id`) REFERENCES `checkins` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `room_service_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

--
-- AUTO_INCREMENT for table `password_reset_otps`
--
ALTER TABLE `password_reset_otps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

SET FOREIGN_KEY_CHECKS=1;
