-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 11, 2026 at 06:31 PM
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
-- Database: `event_booking_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `booking_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `booking_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `booking_status` enum('Pending','Confirmed','Cancelled') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`booking_id`, `user_id`, `event_id`, `quantity`, `total_price`, `booking_date`, `booking_status`) VALUES
(1, 4, 1, 1, 60.00, '2026-10-10 18:24:46', 'Cancelled'),
(2, 4, 5, 2, 80.00, '2026-10-11 11:15:32', 'Confirmed'),
(3, 5, 6, 1, 20.00, '2026-10-11 11:15:32', 'Pending'),
(4, 4, 7, 2, 70.00, '2026-10-11 11:15:32', 'Confirmed'),
(5, 5, 8, 3, 45.00, '2026-10-11 11:15:32', 'Pending'),
(6, 3, 1, 1, 60.00, '2026-10-11 11:58:28', 'Confirmed'),
(7, 3, 5, 1, 40.00, '2026-10-11 12:19:53', 'Cancelled'),
(8, 4, 5, 1, 40.00, '2026-10-11 16:05:46', 'Cancelled');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `event_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `venue_id` int(11) NOT NULL,
  `organiser_id` int(11) NOT NULL,
  `event_date` datetime NOT NULL,
  `ticket_price` decimal(10,2) NOT NULL,
  `total_tickets` int(11) NOT NULL,
  `available_tickets` int(11) NOT NULL,
  `status` enum('draft','published','cancelled','completed') DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`event_id`, `title`, `description`, `venue_id`, `organiser_id`, `event_date`, `ticket_price`, `total_tickets`, `available_tickets`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Cybersecurity Workshop 2026', 'A workshop about cybersecurity awareness and online safety.', 1, 1, '2026-10-20 01:53:00', 60.00, 120, 119, 'published', '2026-10-10 17:54:53', '2026-10-11 11:58:28'),
(5, 'Technology Innovation Seminar 2026', 'Seminar about technology and innovation.', 2, 3, '2026-11-15 09:00:00', 40.00, 100, 100, 'published', '2026-10-11 11:12:53', '2026-10-11 16:09:38'),
(6, 'Cybersecurity Awareness Talk', 'Awareness programme about online safety.', 3, 3, '2026-11-20 10:00:00', 20.00, 150, 150, 'published', '2026-10-11 11:12:53', '2026-10-11 11:12:53'),
(7, 'Web Development Workshop', 'Practical workshop on web development.', 4, 3, '2026-12-05 09:00:00', 35.00, 50, 50, 'draft', '2026-10-11 11:12:53', '2026-10-11 11:12:53'),
(8, 'Digital Future Exhibition', 'Exhibition showcasing digital technology.', 5, 3, '2026-12-12 10:00:00', 15.00, 200, 200, 'published', '2026-10-11 11:12:53', '2026-10-11 11:12:53');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Administrator','Organiser','Staff','Customer') DEFAULT 'Customer'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `email`, `password`, `role`) VALUES
(1, 'Admin User', 'admin@gmail.com', '$2y$10$8QFiprscFW/CXyt99c4fae5GWc9Rqrq4Iekec7fBzDUO3L6midLxm', 'Administrator'),
(2, 'Aina Rahman Updated', 'aina@gmail.com', '$2y$10$JaEjIb1N6AGeC1HGRiuw/emcZva1aX9bQZL0K8ZF04D7Q3cjoUgJ.', 'Staff'),
(3, 'Aisyah Maisarah', 'aisyahmayysarah@gmail.com', '$2y$10$BVJ7kYiHN6Q24lHX4vYUYu9VMulcZMt6HB9J73Wz5sGuJtc8JTe1W', 'Customer'),
(4, 'Seif Al-Fayyad', 'seif@gmail.com', '$2y$10$t/wv/5JCIq3qJCrBzX99deSapFLLVHoCOBnnbX263ocsf5U2rnM0S', 'Customer'),
(5, 'Amirul Fikri', 'amirul@gmail.com', '$2y$10$MgPu.2tZp4wTBP5XbUDACufy8YXVJHehLI7UqnANUI/QlYz1vdsAu', 'Customer'),
(9, 'Test Organiser', 'organiser.test@example.com', '$2y$10$yOChSDNlbBR7ERbGs.OML.r49yeBmDOrwe7wwvaVilafvvD.kSjFy', 'Organiser');

-- --------------------------------------------------------

--
-- Table structure for table `venues`
--

CREATE TABLE `venues` (
  `venue_id` int(11) NOT NULL,
  `venue_name` varchar(100) NOT NULL,
  `location` varchar(255) NOT NULL,
  `capacity` int(11) NOT NULL,
  `status` enum('Available','In Use','Unavailable') DEFAULT 'Available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `venues`
--

INSERT INTO `venues` (`venue_id`, `venue_name`, `location`, `capacity`, `status`, `created_at`, `updated_at`) VALUES
(1, 'UPTM Main Hall', 'Kuala Lumpur', 500, 'In Use', '2026-10-10 17:51:38', '2026-10-11 15:34:21'),
(2, 'Conference Room A', 'Kuala Lumpur', 100, 'Available', '2026-10-10 17:51:38', '2026-10-10 17:51:38'),
(3, 'Auditorium Hall', 'Selangor', 300, 'Available', '2026-10-10 17:51:38', '2026-10-10 17:51:38'),
(4, 'Seminar Room B', 'Kuala Lumpur', 80, 'Available', '2026-10-10 17:51:38', '2026-10-10 17:51:38'),
(5, 'Exhibition Centre', 'Putrajaya', 1000, 'Available', '2026-10-10 17:51:38', '2026-10-10 17:51:38');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`booking_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`event_id`),
  ADD KEY `venue_id` (`venue_id`),
  ADD KEY `organiser_id` (`organiser_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `venues`
--
ALTER TABLE `venues`
  ADD PRIMARY KEY (`venue_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `booking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `venues`
--
ALTER TABLE `venues`
  MODIFY `venue_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON UPDATE CASCADE;

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`venue_id`),
  ADD CONSTRAINT `events_ibfk_2` FOREIGN KEY (`organiser_id`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
