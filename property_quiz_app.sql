-- phpMyAdmin SQL Dump
-- version 5.1.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 04, 2025 at 05:21 AM
-- Server version: 10.4.19-MariaDB
-- PHP Version: 7.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `property_quiz_app`
--

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `p_id` int(11) NOT NULL,
  `p_payment` float NOT NULL,
  `p_transaction_id` varchar(20) NOT NULL,
  `p_channel` enum('STRIPE','PAYPAL') NOT NULL,
  `p_payer_email` varchar(50) NOT NULL,
  `p_payer_name` varchar(30) NOT NULL,
  `p_user_id` int(11) NOT NULL,
  `p_paid_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`p_id`, `p_payment`, `p_transaction_id`, `p_channel`, `p_payer_email`, `p_payer_name`, `p_user_id`, `p_paid_at`) VALUES
(1, 30, '69E55781WC5095929', 'PAYPAL', 'sb-sbfc837124934@personal.example.com', 'John Doe', 19, '2025-10-04 08:14:27');

-- --------------------------------------------------------

--
-- Table structure for table `quizzes`
--

CREATE TABLE `quizzes` (
  `q_id` int(11) NOT NULL,
  `q_user_id` int(11) NOT NULL,
  `q_name` varchar(100) DEFAULT NULL,
  `q_email` varchar(50) DEFAULT NULL,
  `q_phone` varchar(50) DEFAULT NULL,
  `q_code` varchar(100) NOT NULL,
  `q_result` varchar(200) DEFAULT NULL,
  `q_image` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `quizzes`
--

INSERT INTO `quizzes` (`q_id`, `q_user_id`, `q_name`, `q_email`, `q_phone`, `q_code`, `q_result`, `q_image`) VALUES
(19, 19, NULL, NULL, NULL, '5f5d1431', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `u_id` int(11) NOT NULL,
  `u_name` varchar(70) NOT NULL,
  `u_email` varchar(50) NOT NULL,
  `u_password` varchar(50) NOT NULL,
  `u_profile_img` varchar(300) DEFAULT NULL,
  `u_package_type` enum('Basic','Silver','Gold','Platinum','Admin') NOT NULL,
  `u_registered_at` datetime DEFAULT NULL,
  `u_expired_at` datetime DEFAULT NULL,
  `u_is_expired` enum('0','1') NOT NULL,
  `u_role` enum('Admin','Landlord') NOT NULL,
  `u_status` enum('0','1') NOT NULL,
  `u_quiz_created` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`u_id`, `u_name`, `u_email`, `u_password`, `u_profile_img`, `u_package_type`, `u_registered_at`, `u_expired_at`, `u_is_expired`, `u_role`, `u_status`, `u_quiz_created`) VALUES
(17, 'Admin', 'admin@domain.com', '@dmin', '', 'Admin', '2025-10-03 14:05:53', '2025-10-03 14:05:53', '0', 'Admin', '1', 0),
(19, 'Arbaz Ali', 'arbu1499@gmail.com', '1234', NULL, 'Gold', '2025-10-04 08:14:27', '2025-10-05 08:14:27', '0', 'Landlord', '1', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`p_id`),
  ADD KEY `payment_ibfk_1` (`p_user_id`);

--
-- Indexes for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD PRIMARY KEY (`q_id`),
  ADD KEY `quiz_ibfk_1` (`q_user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`u_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `p_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `quizzes`
--
ALTER TABLE `quizzes`
  MODIFY `q_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `u_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payment_ibfk_1` FOREIGN KEY (`p_user_id`) REFERENCES `users` (`u_id`);

--
-- Constraints for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD CONSTRAINT `quiz_ibfk_1` FOREIGN KEY (`q_user_id`) REFERENCES `users` (`u_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
