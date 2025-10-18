-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 19, 2025 at 01:38 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ojtroute_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `action` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `description`, `created_at`) VALUES
(1, 1, 'system_initialized', 'OJT Route system initialized with default admin account', '2025-10-07 14:30:06'),
(2, 1, 'section_create', 'Created section BSIT-4A', '2025-10-07 14:40:41'),
(3, 4, 'submit_document', 'Submitted document: MOA (Memorandum of Agreement)', '2025-10-07 14:54:09'),
(4, 4, 'submit_document', 'Submitted document: Endorsement Letter', '2025-10-07 14:54:17'),
(5, 4, 'submit_document', 'Submitted document: Misdemeanor Penalty', '2025-10-07 14:54:53'),
(6, 4, 'submit_document', 'Submitted document: OJT Plan', '2025-10-07 15:00:29'),
(7, 4, 'submit_document', 'Submitted document: Notarized Parental Consent', '2025-10-07 15:00:37'),
(8, 4, 'submit_document', 'Submitted document: Pledge of Good Conduct', '2025-10-07 15:00:44'),
(9, 4, 'submit_document', 'Submitted document: Parental Consent', '2025-10-07 15:03:41'),
(11, 5, 'submit_document', 'Submitted document: MOA (Memorandum of Agreement)', '2025-10-07 15:20:54'),
(12, 5, 'submit_document', 'Submitted document: Endorsement Letter', '2025-10-07 15:21:01'),
(13, 5, 'submit_document', 'Submitted document: Parental Consent', '2025-10-07 15:21:08'),
(14, 5, 'submit_document', 'Submitted document: Misdemeanor Penalty', '2025-10-07 15:21:25'),
(15, 5, 'submit_document', 'Submitted document: OJT Plan', '2025-10-07 15:21:33'),
(16, 5, 'submit_document', 'Submitted document: Notarized Parental Consent', '2025-10-07 15:21:39'),
(17, 5, 'submit_document', 'Submitted document: Pledge of Good Conduct', '2025-10-07 15:21:45'),
(19, 1, 'test_insert', 'Testing activity log insertion', '2025-10-07 15:43:48'),
(20, 1, 'test_comprehensive', 'Testing comprehensive fix', '2025-10-07 15:45:28'),
(21, 4, 'test_session', 'Testing session user_id', '2025-10-07 15:45:28'),
(22, 1, 'test_fallback', 'Testing fallback to admin', '2025-10-07 15:45:28'),
(28, 1, 'test_session_null', 'Testing with session null', '2025-10-07 15:48:05'),
(30, 1, 'test_normal', 'Testing normal activity logging', '2025-10-07 15:49:30'),
(31, 1, 'security_event', '{\"event\":\"test_security\",\"data\":{\"test\":\"data\"},\"timestamp\":\"2025-10-07 17:49:30\"}', '2025-10-07 15:49:30'),
(32, 1, 'error', 'Testing error logging', '2025-10-07 15:49:30'),
(33, 1, 'test_auto', 'Testing auto activity logging', '2025-10-07 15:49:30'),
(34, 1, 'test_invalid_user', 'Testing with invalid user_id', '2025-10-07 15:49:30'),
(35, 1, 'test_null_user', 'Testing with null user_id', '2025-10-07 15:49:30'),
(36, 4, 'forgot_timeout_request_submitted', 'Student submitted forgot timeout request for attendance record 1', '2025-10-08 05:24:39'),
(40, 4, 'submit_custom_document', 'Submitted custom document: Test other 1', '2025-10-08 12:08:47'),
(46, 1, 'admin_notification_sent', 'Sent notification to students: 2 successful, 0 failed', '2025-10-08 13:51:53'),
(47, 4, 'message_sent', 'Sent message to user ID: 6', '2025-10-08 14:44:21'),
(48, 4, 'message_sent', 'Sent message to user ID: 6', '2025-10-08 14:44:21'),
(49, 4, 'message_sent', 'Sent message to user ID: 6', '2025-10-08 14:44:57'),
(50, 4, 'message_sent', 'Sent message to user ID: 6', '2025-10-08 14:44:57'),
(51, 4, 'message_sent', 'Sent message to user ID: 6', '2025-10-08 14:49:07'),
(52, 4, 'message_sent', 'Sent message to user ID: 6', '2025-10-08 14:49:07'),
(53, 4, 'message_sent', 'Sent message to user ID: 6', '2025-10-08 14:52:19'),
(54, 4, 'message_sent', 'Sent message to user ID: 6', '2025-10-08 14:52:19'),
(55, 4, 'message_sent', 'Sent message to user ID: 6', '2025-10-08 14:52:21'),
(56, 4, 'message_sent', 'Sent message to user ID: 6', '2025-10-08 14:52:21'),
(59, 4, 'message_sent', 'Sent message to user ID: 6', '2025-10-08 14:58:49'),
(60, 4, 'message_sent', 'Sent message to user ID: 6', '2025-10-08 14:58:49'),
(69, 4, 'message_sent', 'Sent message to user ID: 6', '2025-10-08 15:07:10'),
(70, 4, 'message_sent', 'Sent message to user ID: 6', '2025-10-08 15:09:41'),
(73, 4, 'message_sent', 'Sent message to user ID: 6', '2025-10-08 15:14:03'),
(74, 4, 'message_sent', 'Sent message to user ID: 6', '2025-10-08 15:14:35'),
(75, 4, 'message_sent', 'Sent message to user ID: 6', '2025-10-08 15:15:37'),
(76, 4, 'message_sent', 'Sent message to user ID: 6', '2025-10-08 15:15:50'),
(78, 4, 'message_sent', 'Sent message to user ID: 6', '2025-10-08 15:17:31'),
(79, 4, 'message_sent', 'Sent message to user ID: 6', '2025-10-08 15:20:32'),
(81, 5, 'message_sent', 'Sent message to user ID: 6', '2025-10-08 15:21:59'),
(84, 5, 'message_sent', 'Sent message to user ID: 6', '2025-10-08 15:25:25'),
(85, 5, 'message_sent', 'Sent message to user ID: 6', '2025-10-08 15:25:31'),
(87, 5, 'message_sent', 'Sent message to user ID: 6', '2025-10-08 15:27:17'),
(91, 5, 'message_sent', 'Sent message to user ID: 6', '2025-10-08 15:33:37'),
(158, 1, 'section_create', 'Created section BSIT4D', '2025-10-08 17:25:57'),
(159, 1, 'instructor_assign', 'Assigned instructor Ten Giovanni to section BSIT4D', '2025-10-08 17:27:02'),
(184, 1, 'section_create', 'Created section BSIT4C', '2025-10-08 18:30:48'),
(185, 1, 'instructor_assign', 'Assigned instructor Ten Giovanni to section BSIT4D', '2025-10-08 18:34:47'),
(187, 21, 'attendance_blocked', 'Document compliance required for attendance access', '2025-10-09 01:47:06'),
(188, 21, 'attendance_access_denied', '{\"reason\":\"document_compliance\",\"message\":\"You must complete all required documents before accessing attendance features. 7 document(s) remaining.\"}', '2025-10-09 01:47:06'),
(189, 21, 'submit_document', 'Submitted document: MOA (Memorandum of Agreement)', '2025-10-09 01:47:17'),
(190, 21, 'submit_document', 'Submitted document: Endorsement Letter', '2025-10-09 01:47:28'),
(191, 21, 'submit_document', 'Submitted document: Parental Consent', '2025-10-09 01:47:40'),
(192, 21, 'submit_document', 'Submitted document: Misdemeanor Penalty', '2025-10-09 01:47:47'),
(193, 21, 'submit_document', 'Submitted document: OJT Plan', '2025-10-09 01:47:57'),
(194, 21, 'submit_document', 'Submitted document: Notarized Parental Consent', '2025-10-09 01:48:04'),
(195, 21, 'submit_document', 'Submitted document: Pledge of Good Conduct', '2025-10-09 01:48:13'),
(196, 20, 'bulk_approve', 'Bulk approved 7 documents', '2025-10-09 01:49:44'),
(197, 21, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:19:38'),
(198, 21, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:20:39'),
(199, 21, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:26:42'),
(200, 21, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:26:44'),
(201, 21, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:26:45'),
(202, 21, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:26:49'),
(203, 21, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:26:52'),
(204, 21, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:27:39'),
(205, 21, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:29:27'),
(206, 21, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:29:38'),
(207, 1, 'attendance_blocked', 'Document compliance required for attendance access', '2025-10-09 02:30:03'),
(208, 21, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:30:30'),
(209, 21, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:30:32'),
(210, 21, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:32:24'),
(211, 21, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:33:09'),
(212, 21, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:33:48'),
(213, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:41:14'),
(214, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:41:18'),
(215, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:41:22'),
(216, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:44:28'),
(217, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:44:28'),
(218, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:45:05'),
(219, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:45:22'),
(220, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:46:02'),
(221, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:47:39'),
(222, 1, 'attendance_blocked', 'Document compliance required for attendance access', '2025-10-09 02:48:45'),
(223, 1, 'attendance_blocked', 'Document compliance required for attendance access', '2025-10-09 02:49:09'),
(224, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:51:09'),
(225, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:51:11'),
(226, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:51:48'),
(227, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:51:49'),
(228, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:52:05'),
(229, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:52:07'),
(230, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:52:47'),
(231, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:53:16'),
(232, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:53:36'),
(233, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:54:47'),
(234, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:55:59'),
(235, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:56:15'),
(236, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:56:25'),
(237, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:57:11'),
(238, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:58:08'),
(239, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 02:58:09'),
(240, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 03:00:11'),
(241, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 03:01:09'),
(242, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 03:02:06'),
(243, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 03:03:12'),
(244, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 03:03:18'),
(245, 4, 'message_sent', 'Sent message to user ID: 20', '2025-10-09 03:04:05'),
(246, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 03:04:09'),
(247, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 03:50:27'),
(248, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 04:00:33'),
(249, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 04:00:35'),
(250, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-09 04:00:55'),
(251, 1, 'admin_notification_sent', 'Sent notification to students: 8 successful, 0 failed', '2025-10-09 21:32:25'),
(290, 20, 'bulk_approve', 'Bulk approved 7 documents', '2025-10-10 07:53:03'),
(291, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-10 12:44:33'),
(292, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-10 12:44:35'),
(293, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-10 12:44:37'),
(294, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-10 12:44:38'),
(295, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-10 12:44:42'),
(296, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-10 12:44:45'),
(297, 4, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-10 12:45:18'),
(300, 4, 'attendance_transaction_failed', 'Attendance transaction failed: Location verification failed: Location too far. Distance: 4177.84m (required: ≤40m from Trident Corp.)', '2025-10-11 02:15:24'),
(301, 4, 'attendance_time_in', '{\"timestamp\":\"2025-10-11 10:15:24\",\"ip_address\":\"::1\"}', '2025-10-11 02:15:24'),
(302, 4, 'attendance_transaction_failed', 'Attendance transaction failed: Location verification failed: Location too far. Distance: 4177.84m (required: ≤40m from Trident Corp.)', '2025-10-11 02:15:38'),
(303, 4, 'attendance_time_in', '{\"timestamp\":\"2025-10-11 10:15:38\",\"ip_address\":\"::1\"}', '2025-10-11 02:15:38'),
(308, 33, 'attendance_blocked', 'Document compliance required for attendance access', '2025-10-11 02:50:37'),
(309, 33, 'attendance_access_denied', '{\"reason\":\"document_compliance\",\"message\":\"You must complete all required documents before accessing attendance features. 7 document(s) remaining.\"}', '2025-10-11 02:50:37'),
(310, 33, 'submit_document', 'Submitted document: MOA (Memorandum of Agreement)', '2025-10-11 02:50:51'),
(311, 33, 'submit_document', 'Submitted document: Endorsement Letter', '2025-10-11 02:50:56'),
(312, 33, 'submit_document', 'Submitted document: Parental Consent', '2025-10-11 02:51:02'),
(313, 33, 'submit_document', 'Submitted document: Misdemeanor Penalty', '2025-10-11 02:51:08'),
(314, 33, 'submit_document', 'Submitted document: OJT Plan', '2025-10-11 02:51:14'),
(315, 33, 'submit_document', 'Submitted document: Notarized Parental Consent', '2025-10-11 02:51:19'),
(316, 33, 'submit_document', 'Submitted document: Pledge of Good Conduct', '2025-10-11 02:51:24'),
(317, 33, 'attendance_blocked', 'Document compliance required for attendance access', '2025-10-11 02:51:27'),
(318, 33, 'attendance_access_denied', '{\"reason\":\"document_compliance\",\"message\":\"You must complete all required documents before accessing attendance features. 7 document(s) remaining.\"}', '2025-10-11 02:51:27'),
(319, 33, 'attendance_blocked', 'Document compliance required for attendance access', '2025-10-11 02:51:33'),
(320, 33, 'attendance_access_denied', '{\"reason\":\"document_compliance\",\"message\":\"You must complete all required documents before accessing attendance features. 7 document(s) remaining.\"}', '2025-10-11 02:51:33'),
(321, 20, 'approve_document', 'Approved document submission ID: 59', '2025-10-11 02:52:34'),
(322, 20, 'approve_document', 'Approved document submission ID: 58', '2025-10-11 02:52:41'),
(323, 20, 'approve_document', 'Approved document submission ID: 57', '2025-10-11 02:52:47'),
(324, 20, 'approve_document', 'Approved document submission ID: 56', '2025-10-11 02:52:54'),
(325, 20, 'approve_document', 'Approved document submission ID: 55', '2025-10-11 02:53:00'),
(326, 20, 'approve_document', 'Approved document submission ID: 54', '2025-10-11 02:53:10'),
(327, 20, 'approve_document', 'Approved document submission ID: 53', '2025-10-11 02:53:15'),
(328, 33, 'attendance_transaction_success', 'Attendance transaction completed successfully', '2025-10-11 02:53:46'),
(329, 33, 'attendance_time_in', '{\"timestamp\":\"2025-10-11 10:53:46\",\"ip_address\":\"::1\"}', '2025-10-11 02:53:46'),
(332, 33, 'attendance_transaction_success', 'Attendance transaction completed successfully', '2025-10-11 04:25:22'),
(333, 33, 'attendance_time_in', '{\"timestamp\":\"2025-10-11 12:25:22\",\"ip_address\":\"::1\"}', '2025-10-11 04:25:22'),
(344, 33, 'forgot_timeout_request_submitted', 'Student submitted forgot timeout request for attendance record 54', '2025-10-11 05:19:59'),
(346, 1, 'attendance_blocked', 'Document compliance required for attendance access', '2025-10-11 05:26:29'),
(349, 33, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-11 05:29:08'),
(350, 33, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-11 05:29:10'),
(351, 33, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-11 05:29:11'),
(352, 33, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-11 05:29:14'),
(353, 33, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-11 05:29:32'),
(354, 33, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-11 05:29:35'),
(355, 33, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-11 05:29:36'),
(356, 33, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-11 05:34:11'),
(357, 33, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-11 05:35:09'),
(358, 33, 'attendance_access_denied', '{\"reason\":\"poor_standing\",\"message\":\"Student has attendance or disciplinary issues\"}', '2025-10-11 05:35:10'),
(359, 4, 'attendance_transaction_failed', 'Attendance transaction failed: Location verification failed: Location too far. Distance: 4191.38m (required: ≤40m from Trident Corp.)', '2025-10-11 05:38:37'),
(360, 4, 'attendance_time_in', '{\"timestamp\":\"2025-10-11 13:38:37\",\"ip_address\":\"::1\"}', '2025-10-11 05:38:37'),
(365, 33, 'attendance_transaction_failed', 'Attendance transaction failed: Location verification failed: Location too far. Distance: 4201.86m (required: ≤40m from Jiga corp.)', '2025-10-11 08:37:26'),
(366, 33, 'attendance_time_out', '{\"timestamp\":\"2025-10-11 16:37:26\",\"ip_address\":\"::1\"}', '2025-10-11 08:37:26'),
(367, 33, 'attendance_transaction_failed', 'Attendance transaction failed: Location verification failed: Location too far. Distance: 4201.86m (required: ≤40m from Jiga corp.)', '2025-10-11 08:37:31'),
(368, 33, 'attendance_time_out', '{\"timestamp\":\"2025-10-11 16:37:31\",\"ip_address\":\"::1\"}', '2025-10-11 08:37:31'),
(369, 33, 'attendance_transaction_failed', 'Attendance transaction failed: Location verification failed: Location too far. Distance: 4201.86m (required: ≤40m from Jiga corp.)', '2025-10-11 08:37:35'),
(370, 33, 'attendance_time_out', '{\"timestamp\":\"2025-10-11 16:37:35\",\"ip_address\":\"::1\"}', '2025-10-11 08:37:35'),
(371, 4, 'attendance_transaction_success', 'Attendance transaction completed successfully', '2025-10-11 08:38:34'),
(372, 4, 'attendance_time_in', '{\"timestamp\":\"2025-10-11 16:38:34\",\"ip_address\":\"::1\"}', '2025-10-11 08:38:34'),
(373, 4, 'attendance_transaction_success', 'Attendance transaction completed successfully', '2025-10-11 08:39:28'),
(374, 4, 'attendance_time_out', '{\"timestamp\":\"2025-10-11 16:39:28\",\"ip_address\":\"::1\"}', '2025-10-11 08:39:28'),
(375, 33, 'attendance_transaction_failed', 'Attendance transaction failed: Location verification failed: Location too far. Distance: 4197.25m (required: ≤40m from Jiga corp.)', '2025-10-11 10:15:30'),
(376, 33, 'attendance_time_in', '{\"timestamp\":\"2025-10-11 18:15:30\",\"ip_address\":\"::1\"}', '2025-10-11 10:15:30'),
(377, 4, 'attendance_transaction_success', 'Attendance transaction completed successfully', '2025-10-11 10:16:06'),
(378, 4, 'attendance_time_in', '{\"timestamp\":\"2025-10-11 18:16:06\",\"ip_address\":\"::1\"}', '2025-10-11 10:16:06'),
(379, 4, 'attendance_transaction_success', 'Attendance transaction completed successfully', '2025-10-11 10:19:10'),
(380, 4, 'attendance_time_in', '{\"timestamp\":\"2025-10-11 18:19:10\",\"ip_address\":\"::1\"}', '2025-10-11 10:19:10'),
(381, 21, 'attendance_transaction_failed', 'Attendance transaction failed: Location verification failed: Location too far. Distance: 2641.3m (required: ≤40m from CHMSU)', '2025-10-11 10:20:15'),
(382, 21, 'attendance_time_in', '{\"timestamp\":\"2025-10-11 18:20:15\",\"ip_address\":\"::1\"}', '2025-10-11 10:20:15'),
(383, 34, 'attendance_blocked', 'Document compliance required for attendance access', '2025-10-11 10:22:37'),
(384, 34, 'attendance_access_denied', '{\"reason\":\"document_compliance\",\"message\":\"You must complete all required documents before accessing attendance features. 7 document(s) remaining.\"}', '2025-10-11 10:22:37'),
(385, 34, 'attendance_blocked', 'Document compliance required for attendance access', '2025-10-11 10:23:05'),
(386, 34, 'attendance_access_denied', '{\"reason\":\"document_compliance\",\"message\":\"You must complete all required documents before accessing attendance features. 7 document(s) remaining.\"}', '2025-10-11 10:23:05'),
(387, 34, 'submit_document', 'Submitted document: MOA (Memorandum of Agreement)', '2025-10-11 10:23:17'),
(388, 34, 'submit_document', 'Submitted document: Endorsement Letter', '2025-10-11 10:23:23'),
(389, 34, 'submit_document', 'Submitted document: Parental Consent', '2025-10-11 10:23:29'),
(390, 34, 'submit_document', 'Submitted document: Misdemeanor Penalty', '2025-10-11 10:23:35'),
(391, 34, 'submit_document', 'Submitted document: OJT Plan', '2025-10-11 10:23:41'),
(392, 34, 'submit_document', 'Submitted document: Notarized Parental Consent', '2025-10-11 10:23:46'),
(393, 34, 'submit_document', 'Submitted document: Pledge of Good Conduct', '2025-10-11 10:23:53'),
(394, 20, 'bulk_approve', 'Bulk approved 7 documents', '2025-10-11 10:24:58'),
(395, 34, 'attendance_transaction_success', 'Attendance transaction completed successfully', '2025-10-11 10:25:41'),
(396, 34, 'attendance_time_in', '{\"timestamp\":\"2025-10-11 18:25:41\",\"ip_address\":\"::1\"}', '2025-10-11 10:25:41'),
(397, 33, 'attendance_transaction_failed', 'Attendance transaction failed: Location verification failed: Location too far. Distance: 2092.25m (required: ≤40m from Jiga corp.)', '2025-10-14 00:12:25'),
(398, 33, 'attendance_time_in', '{\"timestamp\":\"2025-10-14 08:12:25\",\"ip_address\":\"::1\"}', '2025-10-14 00:12:25'),
(399, 21, 'attendance_transaction_success', 'Attendance transaction completed successfully', '2025-10-14 00:15:07'),
(400, 21, 'attendance_time_in', '{\"timestamp\":\"2025-10-14 08:15:07\",\"ip_address\":\"::1\"}', '2025-10-14 00:15:07'),
(401, 1, 'instructor_assign', 'Assigned instructor Ten Giovanni to section BSIT-4A', '2025-10-14 01:11:26'),
(402, 1, 'instructor_assign', 'Assigned instructor Ten Giovanni to section BSIT-4A', '2025-10-14 01:11:45'),
(403, 1, 'admin_notification_sent', 'Sent notification to students: 10 successful, 0 failed', '2025-10-14 01:14:40'),
(439, 21, 'attendance_transaction_success', 'Attendance transaction completed successfully', '2025-10-15 00:33:50'),
(440, 21, 'attendance_time_in', '{\"timestamp\":\"2025-10-15 08:33:51\",\"ip_address\":\"::1\"}', '2025-10-15 00:33:51'),
(441, 4, 'attendance_transaction_failed', 'Attendance transaction failed: Location verification failed: Location too far. Distance: 2609.84m (required: ≤40m from Trident Corp.)', '2025-10-15 00:47:51'),
(442, 4, 'attendance_time_in', '{\"timestamp\":\"2025-10-15 08:47:51\",\"ip_address\":\"::1\"}', '2025-10-15 00:47:51'),
(443, 4, 'profile_update', 'User VAJ10130300 updated profile', '2025-10-15 01:39:14'),
(446, 1, 'admin_notification_sent', 'Sent notification to students: 11 successful, 0 failed', '2025-10-15 03:02:47'),
(447, 1, 'instructor_assign', 'Assigned instructor Ten Giovanni to section BSIT-4C', '2025-10-15 03:06:31'),
(448, 1, 'instructor_assign', 'Assigned instructor Ten Giovanni to section BSIT-4D', '2025-10-15 03:06:36'),
(449, 1, 'section_create', 'Created section BSIS-4A', '2025-10-15 03:07:19'),
(450, 1, 'instructor_assign', 'Assigned instructor Ten Giovanni to section BSIS-4A', '2025-10-15 03:09:35'),
(451, 1, 'instructor_assign', 'Assigned instructor Ten Giovanni to section BSIT-4D', '2025-10-15 03:09:43'),
(458, 34, 'attendance_transaction_failed', 'Attendance transaction failed: Location verification failed: Location too far. Distance: 2660.89m (required: ≤40m from Trident Corp.)', '2025-10-15 03:30:41'),
(459, 34, 'attendance_time_in', '{\"timestamp\":\"2025-10-15 11:30:41\",\"ip_address\":\"::1\"}', '2025-10-15 03:30:41'),
(467, 20, 'approve_document', 'Approved document submission ID: 80', '2025-10-15 03:37:40'),
(468, 20, 'bulk_approve', 'Bulk approved 6 documents', '2025-10-15 03:39:39'),
(470, 20, 'message_sent', 'Sent message to user ID: 41', '2025-10-15 03:44:07'),
(473, 21, 'attendance_transaction_success', 'Attendance transaction completed successfully', '2025-10-15 03:48:39'),
(474, 21, 'attendance_time_out', '{\"timestamp\":\"2025-10-15 11:48:39\",\"ip_address\":\"::1\"}', '2025-10-15 03:48:39'),
(475, 4, 'attendance_transaction_failed', 'Attendance transaction failed: Location verification failed: Location too far. Distance: 2604.63m (required: ≤40m from Trident Corp.)', '2025-10-16 07:45:54'),
(476, 4, 'attendance_time_in', '{\"timestamp\":\"2025-10-16 15:45:54\",\"ip_address\":\"::1\"}', '2025-10-16 07:45:54'),
(477, 21, 'attendance_transaction_success', 'Attendance transaction completed successfully', '2025-10-16 07:46:17'),
(478, 21, 'attendance_time_in', '{\"timestamp\":\"2025-10-16 15:46:17\",\"ip_address\":\"::1\"}', '2025-10-16 07:46:17'),
(479, 4, 'attendance_transaction_failed', 'Attendance transaction failed: Location verification failed: Location too far. Distance: 2881.09m (required: ≤40m from Trident Corp.)', '2025-10-16 09:24:06'),
(480, 4, 'attendance_time_in', '{\"timestamp\":\"2025-10-16 17:24:06\",\"ip_address\":\"::1\"}', '2025-10-16 09:24:06'),
(481, 1, 'section_delete', 'Deleted section BSIS-4A', '2025-10-16 09:43:52'),
(482, 48, 'attendance_blocked', 'Document compliance required for attendance access', '2025-10-16 09:48:08'),
(483, 48, 'attendance_access_denied', '{\"reason\":\"document_compliance\",\"message\":\"You must complete all required documents before accessing attendance features. 7 document(s) remaining.\"}', '2025-10-16 09:48:08'),
(484, 48, 'submit_document', 'Submitted document: MOA (Memorandum of Agreement)', '2025-10-16 09:48:30'),
(485, 48, 'submit_document', 'Submitted document: Endorsement Letter', '2025-10-16 09:48:37'),
(486, 48, 'submit_document', 'Submitted document: Parental Consent', '2025-10-16 09:48:44'),
(487, 48, 'submit_document', 'Submitted document: Misdemeanor Penalty', '2025-10-16 09:48:51'),
(488, 48, 'submit_document', 'Submitted document: OJT Plan', '2025-10-16 09:48:57'),
(489, 48, 'submit_document', 'Submitted document: Notarized Parental Consent', '2025-10-16 09:49:03'),
(490, 48, 'submit_document', 'Submitted document: Pledge of Good Conduct', '2025-10-16 09:49:10'),
(491, 20, 'bulk_approve', 'Bulk approved 7 documents', '2025-10-16 09:50:34'),
(492, 48, 'attendance_transaction_success', 'Attendance transaction completed successfully', '2025-10-16 09:51:06'),
(493, 48, 'attendance_time_in', '{\"timestamp\":\"2025-10-16 17:51:06\",\"ip_address\":\"::1\"}', '2025-10-16 09:51:06'),
(494, 4, 'attendance_transaction_failed', 'Attendance transaction failed: Location verification failed: Location too far. Distance: 2881.13m (required: ≤40m from Trident Corp.)', '2025-10-16 09:52:30'),
(495, 4, 'attendance_time_in', '{\"timestamp\":\"2025-10-16 17:52:30\",\"ip_address\":\"::1\"}', '2025-10-16 09:52:30'),
(496, 48, 'attendance_transaction_success', 'Attendance transaction completed successfully', '2025-10-16 09:57:06'),
(497, 48, 'attendance_time_out', '{\"timestamp\":\"2025-10-16 17:57:06\",\"ip_address\":\"::1\"}', '2025-10-16 09:57:06'),
(498, 48, 'download_template', 'Downloaded template: Pledge of Good Conduct Template', '2025-10-16 10:05:18'),
(499, 48, 'download_template', 'Downloaded template: Pledge of Good Conduct Template', '2025-10-16 10:05:24'),
(500, 48, 'message_sent', 'Sent message to user ID: 20', '2025-10-16 10:05:51'),
(501, 1, 'section_create', 'Created section BSIS-4A', '2025-10-16 10:11:18'),
(502, 20, 'email_sent', 'Email sent to user 4: template_upload - resume', '2025-10-16 10:20:13'),
(503, 20, 'email_sent', 'Email sent to user 5: template_upload - resume', '2025-10-16 10:20:19'),
(504, 20, 'email_sent', 'Email sent to user 21: template_upload - resume', '2025-10-16 10:20:26'),
(505, 20, 'email_sent', 'Email sent to user 33: template_upload - resume', '2025-10-16 10:20:32'),
(506, 20, 'email_sent', 'Email sent to user 34: template_upload - resume', '2025-10-16 10:20:38'),
(507, 20, 'email_sent', 'Email sent to user 48: template_upload - resume', '2025-10-16 10:20:43'),
(508, 20, 'upload_template', 'Uploaded template: resume', '2025-10-16 10:20:43'),
(509, 20, 'message_sent', 'Sent message to user ID: 48', '2025-10-16 10:21:10'),
(510, 1, 'instructor_assign', 'Assigned instructor Ten Giovanni to section BSIS-4A', '2025-10-16 10:32:08'),
(511, 1, 'section_create', 'Created section BSIS-4B', '2025-10-16 10:33:14'),
(512, 1, 'instructor_assign', 'Assigned instructor Jean Castor to section BSIS-4B', '2025-10-16 10:33:46'),
(513, 1, 'instructor_assign', 'Assigned instructor Jean Castor to section BSIT-4B', '2025-10-16 10:34:25'),
(514, 20, 'email_sent', 'Email sent to user 4: template_upload - MOA 2024', '2025-10-16 10:38:14'),
(515, 20, 'email_sent', 'Email sent to user 5: template_upload - MOA 2024', '2025-10-16 10:38:18'),
(516, 20, 'email_sent', 'Email sent to user 21: template_upload - MOA 2024', '2025-10-16 10:38:22'),
(517, 20, 'email_sent', 'Email sent to user 33: template_upload - MOA 2024', '2025-10-16 10:38:28'),
(518, 20, 'email_sent', 'Email sent to user 34: template_upload - MOA 2024', '2025-10-16 10:38:33'),
(519, 20, 'email_sent', 'Email sent to user 48: template_upload - MOA 2024', '2025-10-16 10:38:39'),
(520, 20, 'upload_template', 'Uploaded template: MOA 2024', '2025-10-16 10:38:39'),
(521, 4, 'submit_custom_document', 'Submitted custom document: resume', '2025-10-16 10:43:12'),
(522, 20, 'request_revision', 'Requested revision for submission ID: 88', '2025-10-16 10:43:56'),
(523, 4, 'message_sent', 'Sent message to user ID: 20', '2025-10-16 11:01:42'),
(524, 1, 'instructor_assign', 'Assigned instructor Ten Giovanni to section BSIS-4A', '2025-10-16 11:28:35'),
(525, 1, 'admin_notification_sent', 'Sent notification to students: 11 successful, 0 failed', '2025-10-16 11:30:40'),
(526, 21, 'forgot_timeout_request_submitted', 'Student submitted forgot timeout request for attendance record 64', '2025-10-16 11:39:44'),
(527, 21, 'download_template', 'Downloaded template: resume', '2025-10-16 11:53:31'),
(528, 21, 'submit_custom_document', 'Submitted custom document: resume', '2025-10-16 11:53:40'),
(529, 20, 'request_revision', 'Requested revision for submission ID: 89', '2025-10-16 11:54:54'),
(530, 21, 'resubmit_document', 'Resubmitted document (ID: 89)', '2025-10-16 11:55:14'),
(531, 20, 'approve_document', 'Approved document submission ID: 89', '2025-10-16 11:55:44'),
(532, 21, 'forgot_timeout_request_submitted', 'Student submitted forgot timeout request for attendance record 61', '2025-10-16 11:56:06'),
(533, 1, 'instructor_assign', 'Assigned instructor Ten Giovanni to section BSIS-4A', '2025-10-16 12:17:18'),
(534, 1, 'admin_notification_sent', 'Sent notification to students: 11 successful, 0 failed', '2025-10-16 12:20:30'),
(535, 4, 'message_sent', 'Sent message to user ID: 20', '2025-10-16 12:22:17'),
(536, 21, 'forgot_timeout_request_submitted', 'Student submitted forgot timeout request for attendance record 33', '2025-10-16 12:23:24'),
(537, 20, 'email_sent', 'Email sent to user 4: template_upload - pleage of good conduct 2025', '2025-10-16 12:27:16'),
(538, 20, 'email_sent', 'Email sent to user 5: template_upload - pleage of good conduct 2025', '2025-10-16 12:27:21'),
(539, 20, 'email_sent', 'Email sent to user 21: template_upload - pleage of good conduct 2025', '2025-10-16 12:27:28'),
(540, 20, 'email_sent', 'Email sent to user 33: template_upload - pleage of good conduct 2025', '2025-10-16 12:27:34'),
(541, 20, 'email_sent', 'Email sent to user 34: template_upload - pleage of good conduct 2025', '2025-10-16 12:27:39'),
(542, 20, 'email_sent', 'Email sent to user 48: template_upload - pleage of good conduct 2025', '2025-10-16 12:27:45'),
(543, 20, 'upload_template', 'Uploaded template: pleage of good conduct 2025', '2025-10-16 12:27:45'),
(544, 4, 'attendance_transaction_failed', 'Attendance transaction failed: Location verification failed: Location too far. Distance: 59.12m (required: ≤40m from Trident Corp.)', '2025-10-17 01:30:53'),
(545, 4, 'attendance_time_in', '{\"timestamp\":\"2025-10-17 09:30:53\",\"ip_address\":\"::1\"}', '2025-10-17 01:30:53'),
(546, 4, 'attendance_transaction_failed', 'Attendance transaction failed: Location verification failed: Location too far. Distance: 51.89m (required: ≤40m from Trident Corp.)', '2025-10-17 01:31:11'),
(547, 4, 'attendance_time_in', '{\"timestamp\":\"2025-10-17 09:31:11\",\"ip_address\":\"::1\"}', '2025-10-17 01:31:11'),
(548, 4, 'attendance_transaction_failed', 'Attendance transaction failed: Location verification failed: Location too far. Distance: 51.89m (required: ≤40m from Trident Corp.)', '2025-10-17 01:31:36'),
(549, 4, 'attendance_time_in', '{\"timestamp\":\"2025-10-17 09:31:36\",\"ip_address\":\"::1\"}', '2025-10-17 01:31:36'),
(550, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-17 09:36:30\",\"ip_address\":\"::1\"}', '2025-10-17 01:36:30'),
(551, 4, 'attendance_transaction_success', 'Attendance transaction completed successfully', '2025-10-17 01:36:40'),
(552, 4, 'attendance_time_in', '{\"timestamp\":\"2025-10-17 09:36:40\",\"ip_address\":\"::1\"}', '2025-10-17 01:36:40'),
(553, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-17 09:43:42\",\"ip_address\":\"::1\"}', '2025-10-17 01:43:42'),
(554, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-17 09:43:45\",\"ip_address\":\"::1\"}', '2025-10-17 01:43:45'),
(555, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-17 09:43:46\",\"ip_address\":\"::1\"}', '2025-10-17 01:43:46'),
(556, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-17 09:43:47\",\"ip_address\":\"::1\"}', '2025-10-17 01:43:47'),
(557, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-17 09:43:47\",\"ip_address\":\"::1\"}', '2025-10-17 01:43:47'),
(558, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-17 09:43:52\",\"ip_address\":\"::1\"}', '2025-10-17 01:43:52'),
(559, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-17 09:43:55\",\"ip_address\":\"::1\"}', '2025-10-17 01:43:55'),
(560, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-17 09:44:03\",\"ip_address\":\"::1\"}', '2025-10-17 01:44:03'),
(561, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-17 09:44:52\",\"ip_address\":\"::1\"}', '2025-10-17 01:44:52'),
(562, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-17 09:45:07\",\"ip_address\":\"::1\"}', '2025-10-17 01:45:07'),
(563, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-17 09:49:11\",\"ip_address\":\"::1\"}', '2025-10-17 01:49:11'),
(564, 20, 'email_sent', 'Email sent to user 4: template_upload - Parental Consent', '2025-10-17 02:33:21'),
(565, 20, 'email_sent', 'Email sent to user 5: template_upload - Parental Consent', '2025-10-17 02:33:25'),
(566, 20, 'email_sent', 'Email sent to user 21: template_upload - Parental Consent', '2025-10-17 02:33:28'),
(567, 20, 'email_sent', 'Email sent to user 33: template_upload - Parental Consent', '2025-10-17 02:33:32'),
(568, 20, 'email_sent', 'Email sent to user 34: template_upload - Parental Consent', '2025-10-17 02:33:36'),
(569, 20, 'email_sent', 'Email sent to user 48: template_upload - Parental Consent', '2025-10-17 02:33:41'),
(570, 20, 'upload_template', 'Uploaded template: Parental Consent', '2025-10-17 02:33:41'),
(571, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-18 00:07:53\",\"ip_address\":\"::1\"}', '2025-10-17 16:07:53'),
(572, 20, 'email_sent', 'Email sent to user 4: template_upload - ss', '2025-10-17 17:14:32'),
(573, 20, 'email_sent', 'Email sent to user 5: template_upload - ss', '2025-10-17 17:14:37'),
(574, 20, 'email_sent', 'Email sent to user 21: template_upload - ss', '2025-10-17 17:14:41'),
(575, 20, 'email_sent', 'Email sent to user 33: template_upload - ss', '2025-10-17 17:14:45'),
(576, 20, 'email_sent', 'Email sent to user 34: template_upload - ss', '2025-10-17 17:14:50'),
(577, 20, 'email_sent', 'Email sent to user 48: template_upload - ss', '2025-10-17 17:14:54'),
(578, 20, 'upload_template', 'Uploaded template: ss', '2025-10-17 17:14:54'),
(579, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-18 01:15:11\",\"ip_address\":\"::1\"}', '2025-10-17 17:15:11'),
(580, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-18 02:31:37\",\"ip_address\":\"::1\"}', '2025-10-17 18:31:37'),
(581, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-18 02:31:41\",\"ip_address\":\"::1\"}', '2025-10-17 18:31:41'),
(582, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-19 02:01:15\",\"ip_address\":\"::1\"}', '2025-10-18 18:01:15'),
(583, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-19 02:01:21\",\"ip_address\":\"::1\"}', '2025-10-18 18:01:21'),
(584, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-19 02:01:28\",\"ip_address\":\"::1\"}', '2025-10-18 18:01:28'),
(585, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-19 02:01:30\",\"ip_address\":\"::1\"}', '2025-10-18 18:01:30'),
(586, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-19 02:46:49\",\"ip_address\":\"::1\"}', '2025-10-18 18:46:49'),
(587, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-19 02:47:10\",\"ip_address\":\"::1\"}', '2025-10-18 18:47:10'),
(588, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-19 02:47:54\",\"ip_address\":\"::1\"}', '2025-10-18 18:47:54'),
(589, 4, 'forgot_timeout_request_submitted', 'Student submitted forgot timeout request for attendance record 66', '2025-10-18 19:01:25'),
(590, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-19 03:38:34\",\"ip_address\":\"::1\"}', '2025-10-18 19:38:34'),
(591, 20, 'profile_picture_upload', 'User JAC09121900 uploaded profile picture', '2025-10-18 21:12:03'),
(592, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-19 06:03:52\",\"ip_address\":\"::1\"}', '2025-10-18 22:03:52'),
(593, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-19 06:04:24\",\"ip_address\":\"::1\"}', '2025-10-18 22:04:24'),
(594, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-19 06:05:39\",\"ip_address\":\"::1\"}', '2025-10-18 22:05:39'),
(595, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-19 06:20:48\",\"ip_address\":\"::1\"}', '2025-10-18 22:20:48'),
(596, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-19 06:20:52\",\"ip_address\":\"::1\"}', '2025-10-18 22:20:52'),
(597, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-19 06:20:53\",\"ip_address\":\"::1\"}', '2025-10-18 22:20:53'),
(598, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-19 06:20:59\",\"ip_address\":\"::1\"}', '2025-10-18 22:20:59'),
(599, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-19 06:21:01\",\"ip_address\":\"::1\"}', '2025-10-18 22:21:01'),
(600, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-19 06:21:05\",\"ip_address\":\"::1\"}', '2025-10-18 22:21:05'),
(601, 4, 'attendance_transaction_success', 'Attendance transaction completed successfully', '2025-10-18 22:21:15'),
(602, 4, 'attendance_time_in', '{\"timestamp\":\"2025-10-19 06:21:15\",\"ip_address\":\"::1\"}', '2025-10-18 22:21:15'),
(603, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-19 06:21:18\",\"ip_address\":\"::1\"}', '2025-10-18 22:21:18'),
(604, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-19 06:25:04\",\"ip_address\":\"::1\"}', '2025-10-18 22:25:04'),
(605, 4, 'attendance_transaction_success', 'Attendance transaction completed successfully', '2025-10-18 22:25:10'),
(606, 4, 'attendance_time_in', '{\"timestamp\":\"2025-10-19 06:25:10\",\"ip_address\":\"::1\"}', '2025-10-18 22:25:10'),
(607, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-19 06:25:12\",\"ip_address\":\"::1\"}', '2025-10-18 22:25:12'),
(608, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-19 06:25:18\",\"ip_address\":\"::1\"}', '2025-10-18 22:25:18'),
(609, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-19 06:25:21\",\"ip_address\":\"::1\"}', '2025-10-18 22:25:21'),
(610, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-19 06:32:36\",\"ip_address\":\"::1\"}', '2025-10-18 22:32:36'),
(611, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-19 06:32:51\",\"ip_address\":\"::1\"}', '2025-10-18 22:32:51'),
(612, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-19 06:48:45\",\"ip_address\":\"::1\"}', '2025-10-18 22:48:45'),
(613, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-19 06:51:08\",\"ip_address\":\"::1\"}', '2025-10-18 22:51:08'),
(614, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-19 07:09:31\",\"ip_address\":\"::1\"}', '2025-10-18 23:09:31'),
(615, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-19 07:14:14\",\"ip_address\":\"::1\"}', '2025-10-18 23:14:14'),
(616, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-19 07:19:33\",\"ip_address\":\"::1\"}', '2025-10-18 23:19:33'),
(617, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-19 07:20:53\",\"ip_address\":\"::1\"}', '2025-10-18 23:20:53'),
(618, 4, 'attendance_check_location', '{\"timestamp\":\"2025-10-19 07:21:19\",\"ip_address\":\"::1\"}', '2025-10-18 23:21:19');

-- --------------------------------------------------------

--
-- Table structure for table `attendance_records`
--

CREATE TABLE `attendance_records` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `block_type` enum('morning','afternoon','overtime') NOT NULL,
  `time_in` timestamp NULL DEFAULT NULL,
  `time_out` timestamp NULL DEFAULT NULL,
  `location_lat_in` decimal(10,8) DEFAULT NULL,
  `location_long_in` decimal(11,8) DEFAULT NULL,
  `location_lat_out` decimal(10,8) DEFAULT NULL,
  `location_long_out` decimal(11,8) DEFAULT NULL,
  `photo_path` varchar(500) DEFAULT NULL,
  `hours_earned` decimal(4,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attendance_records`
--

INSERT INTO `attendance_records` (`id`, `student_id`, `date`, `block_type`, `time_in`, `time_out`, `location_lat_in`, `location_long_in`, `location_lat_out`, `location_long_out`, `photo_path`, `hours_earned`, `created_at`, `updated_at`) VALUES
(1, 4, '2025-10-07', 'overtime', '2025-10-07 11:16:43', NULL, 10.66630655, 122.93612210, NULL, NULL, 'uploads/attendance_photos/student_4_evening_1759850203.jpg', 0.72, '2025-10-07 15:16:43', '2025-10-18 19:02:28'),
(2, 4, '2025-10-08', 'afternoon', '2025-10-08 04:46:49', '2025-10-08 08:13:43', 10.66631248, 122.93592239, NULL, NULL, 'uploads/attendance_photos/student_4_afternoon_1759898809.jpg', 3.43, '2025-10-08 04:46:49', '2025-10-08 08:13:43'),
(29, 4, '2025-10-09', 'morning', '2025-10-09 02:39:56', '2025-10-09 06:39:56', 0.00000000, 0.00000000, NULL, NULL, 'uploads/attendance_photos/student_4_morning_1759977596.jpg', 0.00, '2025-10-09 02:39:56', '2025-10-09 03:57:44'),
(32, 4, '2025-10-09', 'afternoon', '2025-10-09 04:00:29', NULL, 0.00000000, 0.00000000, NULL, NULL, 'uploads/attendance_photos/student_4_afternoon_1759982429.jpg', 0.02, '2025-10-09 04:00:29', '2025-10-18 18:57:01'),
(33, 21, '2025-10-09', 'afternoon', '2025-10-09 04:02:47', NULL, 10.64270160, 122.93978878, NULL, NULL, 'uploads/attendance_photos/student_21_afternoon_1759982567.jpg', 0.00, '2025-10-09 04:02:47', '2025-10-09 04:02:47'),
(54, 33, '2025-10-11', 'morning', '2025-10-11 02:53:46', '2025-10-11 04:00:00', 10.63471350, 122.95719900, NULL, NULL, 'uploads/attendance_photos/student_33_morning_1760151226.jpg', 1.10, '2025-10-11 02:53:46', '2025-10-11 05:20:59'),
(55, 33, '2025-10-11', 'afternoon', '2025-10-11 04:25:22', NULL, 10.63481319, 122.95722906, NULL, NULL, 'uploads/attendance_photos/student_33_afternoon_1760156722.jpg', 0.00, '2025-10-11 04:25:22', '2025-10-11 04:25:22'),
(57, 4, '2025-10-11', 'afternoon', '2025-10-11 08:38:34', '2025-10-11 08:39:28', 10.66626897, 122.93614277, NULL, NULL, 'uploads/attendance_photos/student_4_afternoon_1760171914.jpg', 0.00, '2025-10-11 08:38:34', '2025-10-11 08:39:28'),
(60, 34, '2025-10-11', '', '2025-10-11 10:25:41', NULL, 10.66635826, 122.93621563, NULL, NULL, 'uploads/attendance_photos/student_34_evening_1760178341.jpg', 0.00, '2025-10-11 10:25:41', '2025-10-11 10:25:41'),
(61, 21, '2025-10-14', 'morning', '2025-10-14 00:15:07', '2025-10-14 04:00:00', 10.64277802, 122.93992252, NULL, NULL, 'uploads/attendance_photos/student_21_morning_1760400907.jpg', 3.73, '2025-10-14 00:15:07', '2025-10-16 11:56:30'),
(63, 21, '2025-10-15', 'morning', '2025-10-15 00:33:50', '2025-10-15 03:48:39', 10.64277802, 122.93992252, NULL, NULL, 'uploads/attendance_photos/student_21_morning_1760488430.jpg', 3.23, '2025-10-15 00:33:50', '2025-10-15 03:48:39'),
(64, 21, '2025-10-16', 'afternoon', '2025-10-16 07:46:17', '2025-10-16 10:00:00', 10.64296359, 122.93997720, NULL, NULL, 'uploads/attendance_photos/student_21_afternoon_1760600777.jpg', 2.22, '2025-10-16 07:46:17', '2025-10-16 11:40:30'),
(65, 48, '2025-10-16', 'afternoon', '2025-10-16 09:51:06', '2025-10-16 09:57:06', 10.68293375, 122.95599395, NULL, NULL, 'uploads/attendance_photos/student_48_afternoon_1760608266.jpg', 0.10, '2025-10-16 09:51:06', '2025-10-16 09:57:06'),
(66, 4, '2025-10-17', 'morning', '2025-10-17 01:36:40', '2025-10-17 04:00:00', 10.66641982, 122.93608291, NULL, NULL, 'uploads/attendance_photos/student_4_morning_1760665000.jpg', 2.38, '2025-10-17 01:36:40', '2025-10-18 23:32:07'),
(68, 4, '2025-10-19', 'morning', '2025-10-18 22:25:10', NULL, 10.66640070, 122.93609291, NULL, NULL, 'uploads/attendance_photos/student_4_morning_1760826310.jpg', 0.00, '2025-10-18 22:25:10', '2025-10-18 22:25:10');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(10) UNSIGNED NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `document_type` enum('moa','endorsement','parental_consent','misdemeanor_penalty','ojt_plan','notarized_consent','pledge','weekly_report','other') NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `description` varchar(255) DEFAULT '1',
  `is_required` tinyint(1) DEFAULT 1,
  `uploaded_by` int(10) UNSIGNED NOT NULL,
  `uploaded_for_section` int(10) UNSIGNED DEFAULT NULL,
  `deadline` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`id`, `document_name`, `document_type`, `file_path`, `description`, `is_required`, `uploaded_by`, `uploaded_for_section`, `deadline`, `created_at`) VALUES
(2, 'Endorsement Letter', 'endorsement', 'uploads/templates/endorsement_letter_template.pdf.pdf', '1', 1, 1, NULL, NULL, '2025-10-07 14:30:05'),
(4, 'Misdemeanor Penalty Form', 'misdemeanor_penalty', 'uploads/templates/misdemeanor_penalty_template.pdf.pdf', '1', 1, 1, NULL, NULL, '2025-10-07 14:30:05'),
(5, 'OJT Plan Template', 'ojt_plan', 'uploads/templates/ojt_plan_template.pdf.pdf', '1', 1, 1, NULL, NULL, '2025-10-07 14:30:05'),
(6, 'Notarized Parental Consent', 'notarized_consent', 'uploads/templates/notarized_parental_consent_template.pdf.pdf', '1', 1, 1, NULL, NULL, '2025-10-07 14:30:05'),
(8, 'MOA Template', 'moa', 'uploads/templates/moa_template.pdf.pdf', '1', 1, 1, NULL, NULL, '2025-10-07 14:31:19'),
(14, 'Pledge of Good Conduct Template', 'pledge', 'uploads/templates/pledge_of_good_conduct_template.pdf.pdf', '1', 1, 1, NULL, NULL, '2025-10-07 14:31:19'),
(16, 'Parental Consent Template', 'parental_consent', 'uploads/templates/parental_consent_template.pdf.pdf', '1', 1, 1, NULL, NULL, '2025-10-07 15:03:28'),
(25, 'resume', 'other', 'uploads/templates/instructor_20_other_1760610007.pdf', 'lorem ipsum d', 0, 20, 2, '2025-10-18', '2025-10-16 10:20:07'),
(26, 'MOA 2024', 'moa', 'uploads/templates/instructor_20_moa_1760611087.pdf', '1', 0, 20, 2, NULL, '2025-10-16 10:38:07'),
(27, 'pleage of good conduct 2025', 'pledge', 'uploads/templates/instructor_20_pledge_1760617631.pdf', 'aa', 0, 20, 2, '2025-10-18', '2025-10-16 12:27:11'),
(28, 'Parental Consent', 'parental_consent', 'uploads/templates/instructor_20_parental_consent_1760668397.pdf', '1', 0, 20, 2, NULL, '2025-10-17 02:33:17'),
(29, 'ss', 'other', 'uploads/templates/instructor_20_other_1760721266.pdf', 'aa', 0, 20, 2, '2025-10-20', '2025-10-17 17:14:26');

-- --------------------------------------------------------

--
-- Table structure for table `email_logs`
--

CREATE TABLE `email_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `action` varchar(100) NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `email_logs`
--

INSERT INTO `email_logs` (`id`, `recipient_email`, `action`, `data`, `created_at`) VALUES
(1, 'test@example.com', 'email_sent', '{\"subject\": \"Test Email\", \"template\": \"welcome\"}', '2025-10-08 13:19:02'),
(2, 'admin@chmsu.edu.ph', 'email_sent', '{\"subject\": \"System Notification\", \"template\": \"system_announcement\"}', '2025-10-08 13:19:02');

-- --------------------------------------------------------

--
-- Table structure for table `email_queue`
--

CREATE TABLE `email_queue` (
  `id` int(10) UNSIGNED NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `attempts` int(11) DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sent_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `forgot_timeout_requests`
--

CREATE TABLE `forgot_timeout_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `attendance_record_id` int(10) UNSIGNED NOT NULL,
  `request_date` date NOT NULL,
  `block_type` enum('morning','afternoon','overtime') NOT NULL,
  `letter_file_path` varchar(500) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `instructor_response` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `forgot_timeout_requests`
--

INSERT INTO `forgot_timeout_requests` (`id`, `student_id`, `attendance_record_id`, `request_date`, `block_type`, `letter_file_path`, `status`, `instructor_response`, `created_at`, `reviewed_at`) VALUES
(1, 4, 1, '2025-10-08', 'overtime', 'uploads/letters/student_4_forgot_timeout_1759901079.pdf', 'approved', '', '2025-10-08 05:24:39', '2025-10-08 06:48:31'),
(8, 33, 54, '2025-10-11', 'morning', 'uploads/letters/student_33_forgot_timeout_1760159999.pdf', 'approved', '', '2025-10-11 05:19:59', '2025-10-11 05:20:59'),
(9, 21, 64, '2025-10-16', 'afternoon', 'uploads/letters/student_21_forgot_timeout_1760614784.pdf', 'approved', '', '2025-10-16 11:39:44', '2025-10-16 11:40:30'),
(10, 21, 61, '2025-10-16', 'morning', 'uploads/letters/student_21_forgot_timeout_1760615766.pdf', 'approved', '', '2025-10-16 11:56:06', '2025-10-16 11:56:30'),
(11, 21, 33, '2025-10-16', 'afternoon', 'uploads/letters/student_21_forgot_timeout_1760617404.pdf', 'pending', NULL, '2025-10-16 12:23:24', NULL),
(12, 4, 66, '2025-10-18', 'morning', 'uploads/letters/student_4_forgot_timeout_1760814085.pdf', 'approved', '', '2025-10-18 19:01:25', '2025-10-18 23:32:07');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(10) UNSIGNED NOT NULL,
  `sender_id` int(10) UNSIGNED NOT NULL,
  `recipient_id` int(10) UNSIGNED DEFAULT NULL,
  `section_id` int(10) UNSIGNED DEFAULT NULL,
  `message_body` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `recipient_id`, `section_id`, `message_body`, `is_read`, `created_at`) VALUES
(48, 4, 20, NULL, 'wew', 0, '2025-10-09 03:04:05'),
(53, 48, 20, NULL, 'hello', 1, '2025-10-16 10:05:51'),
(54, 20, 48, NULL, 'hi', 1, '2025-10-16 10:21:10'),
(55, 4, 20, NULL, 'aa', 0, '2025-10-16 11:01:42'),
(56, 4, 20, NULL, 'hello', 0, '2025-10-16 12:22:17');

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int(10) UNSIGNED NOT NULL,
  `section_code` varchar(20) NOT NULL,
  `section_name` varchar(100) NOT NULL,
  `instructor_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `section_code`, `section_name`, `instructor_id`, `created_at`) VALUES
(1, 'BSIT-4A', 'INFORMATION TECHNOLOGY 4A', NULL, '2025-10-07 14:30:05'),
(2, 'BSIT-4B', 'INFORMATION TECHNOLOGY 4B', 20, '2025-10-07 14:40:41'),
(3, 'BSIT-4C', 'INFORMATION TECHNOLOGY 4C', NULL, '2025-10-08 17:25:57'),
(4, 'BSIT-4D', 'INFORMATION TECHNOLOGY 4D', NULL, '2025-10-08 18:30:48'),
(6, 'BSIS-4A', 'BACHELOR OF SCIENCE IN INFORMATION System4A', NULL, '2025-10-16 10:11:18'),
(7, 'BSIS-4B', 'BACHELOR OF SCIENCE IN INFORMATION SYSTEM 4B', NULL, '2025-10-16 10:33:14');

-- --------------------------------------------------------

--
-- Table structure for table `student_documents`
--

CREATE TABLE `student_documents` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `document_id` int(10) UNSIGNED NOT NULL,
  `submission_file_path` varchar(500) DEFAULT NULL,
  `status` enum('pending','approved','rejected','revision_required') DEFAULT 'pending',
  `instructor_feedback` text DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_documents`
--

INSERT INTO `student_documents` (`id`, `student_id`, `document_id`, `submission_file_path`, `status`, `instructor_feedback`, `submitted_at`, `reviewed_at`, `created_at`, `updated_at`) VALUES
(1, 4, 8, '../../uploads/student_documents/student_4_notarized_consent_1759849237.pdf', 'approved', NULL, '2025-10-07 14:54:09', '2025-10-07 15:05:30', '2025-10-07 14:54:09', '2025-10-07 15:25:32'),
(2, 4, 2, '../../uploads/student_documents/student_4_notarized_consent_1759849237.pdf', 'approved', NULL, '2025-10-07 14:54:17', '2025-10-07 15:05:30', '2025-10-07 14:54:17', '2025-10-07 15:25:32'),
(3, 4, 4, '../../uploads/student_documents/student_4_notarized_consent_1759849237.pdf', 'approved', NULL, '2025-10-07 14:54:53', '2025-10-07 15:05:30', '2025-10-07 14:54:53', '2025-10-07 15:25:32'),
(4, 4, 5, '../../uploads/student_documents/student_4_ojt_plan_1759849229.pdf', 'approved', NULL, '2025-10-07 15:00:29', '2025-10-07 15:05:30', '2025-10-07 15:00:29', '2025-10-07 15:05:30'),
(5, 4, 6, '../../uploads/student_documents/student_4_notarized_consent_1759849237.pdf', 'approved', NULL, '2025-10-07 15:00:37', '2025-10-07 15:05:30', '2025-10-07 15:00:37', '2025-10-07 15:05:30'),
(6, 4, 14, '../../uploads/student_documents/student_4_pledge_1759849244.pdf', 'approved', NULL, '2025-10-07 15:00:44', '2025-10-07 15:05:30', '2025-10-07 15:00:44', '2025-10-07 15:05:30'),
(7, 4, 16, '../../uploads/student_documents/student_4_parental_consent_1759849421.pdf', 'approved', NULL, '2025-10-07 15:03:41', '2025-10-07 15:05:30', '2025-10-07 15:03:41', '2025-10-07 15:05:30'),
(8, 5, 8, '../../uploads/student_documents/student_5_moa_1759850454.pdf', 'approved', NULL, '2025-10-07 15:20:54', '2025-10-07 15:46:16', '2025-10-07 15:20:54', '2025-10-07 15:46:16'),
(9, 5, 2, '../../uploads/student_documents/student_5_endorsement_1759850461.pdf', 'approved', NULL, '2025-10-07 15:21:01', '2025-10-07 15:46:16', '2025-10-07 15:21:01', '2025-10-07 15:46:16'),
(10, 5, 16, '../../uploads/student_documents/student_5_parental_consent_1759850468.pdf', 'approved', NULL, '2025-10-07 15:21:08', '2025-10-07 15:46:16', '2025-10-07 15:21:08', '2025-10-07 15:46:16'),
(11, 5, 4, '../../uploads/student_documents/student_5_misdemeanor_penalty_1759850485.pdf', 'approved', NULL, '2025-10-07 15:21:25', '2025-10-07 15:42:16', '2025-10-07 15:21:25', '2025-10-07 15:42:16'),
(12, 5, 5, '../../uploads/student_documents/student_5_ojt_plan_1759850493.pdf', 'approved', NULL, '2025-10-07 15:21:33', '2025-10-07 15:42:16', '2025-10-07 15:21:33', '2025-10-07 15:42:16'),
(13, 5, 6, '../../uploads/student_documents/student_5_notarized_consent_1759850499.pdf', 'approved', NULL, '2025-10-07 15:21:39', '2025-10-07 15:42:16', '2025-10-07 15:21:39', '2025-10-07 15:42:16'),
(14, 5, 14, '../../uploads/student_documents/student_5_pledge_1759850505.pdf', 'approved', NULL, '2025-10-07 15:21:45', '2025-10-07 15:42:16', '2025-10-07 15:21:45', '2025-10-07 15:42:16'),
(25, 21, 8, '../../uploads/student_documents/student_21_moa_1759974437.pdf', 'approved', NULL, '2025-10-09 01:47:17', '2025-10-09 01:48:47', '2025-10-09 01:47:17', '2025-10-09 01:48:47'),
(26, 21, 2, '../../uploads/student_documents/student_21_endorsement_1759974448.pdf', 'approved', NULL, '2025-10-09 01:47:28', '2025-10-09 01:48:47', '2025-10-09 01:47:28', '2025-10-09 01:48:47'),
(27, 21, 16, '../../uploads/student_documents/student_21_parental_consent_1759974460.pdf', 'approved', NULL, '2025-10-09 01:47:40', '2025-10-09 01:48:47', '2025-10-09 01:47:40', '2025-10-09 01:48:47'),
(28, 21, 4, '../../uploads/student_documents/student_21_misdemeanor_penalty_1759974467.pdf', 'approved', NULL, '2025-10-09 01:47:47', '2025-10-09 01:48:47', '2025-10-09 01:47:47', '2025-10-09 01:48:47'),
(29, 21, 5, '../../uploads/student_documents/student_21_ojt_plan_1759974477.pdf', 'approved', NULL, '2025-10-09 01:47:57', '2025-10-09 01:48:47', '2025-10-09 01:47:57', '2025-10-09 01:48:47'),
(30, 21, 6, '../../uploads/student_documents/student_21_notarized_consent_1759974484.pdf', 'approved', NULL, '2025-10-09 01:48:04', '2025-10-09 01:48:47', '2025-10-09 01:48:04', '2025-10-09 01:48:47'),
(31, 21, 14, '../../uploads/student_documents/student_21_pledge_1759974493.pdf', 'approved', NULL, '2025-10-09 01:48:13', '2025-10-09 01:48:47', '2025-10-09 01:48:13', '2025-10-09 01:48:47'),
(32, 1, 2, 'uploads/student_documents/test_2_1759978257.pdf', 'approved', 'Approved for testing purposes', '2025-10-09 02:50:57', '2025-10-09 02:51:52', '2025-10-09 02:50:57', '2025-10-09 02:51:52'),
(33, 1, 4, 'uploads/student_documents/test_4_1759978257.pdf', 'approved', 'Approved for testing purposes', '2025-10-09 02:50:57', '2025-10-09 02:51:52', '2025-10-09 02:50:57', '2025-10-09 02:51:52'),
(34, 1, 5, 'uploads/student_documents/test_5_1759978257.pdf', 'approved', 'Approved for testing purposes', '2025-10-09 02:50:57', '2025-10-09 02:51:52', '2025-10-09 02:50:57', '2025-10-09 02:51:52'),
(35, 1, 6, 'uploads/student_documents/test_6_1759978257.pdf', 'approved', 'Approved for testing purposes', '2025-10-09 02:50:57', '2025-10-09 02:51:52', '2025-10-09 02:50:57', '2025-10-09 02:51:52'),
(36, 1, 8, 'uploads/student_documents/test_8_1759978257.pdf', 'approved', 'Approved for testing purposes', '2025-10-09 02:50:57', '2025-10-09 02:51:52', '2025-10-09 02:50:57', '2025-10-09 02:51:52'),
(37, 1, 14, 'uploads/student_documents/test_14_1759978257.pdf', 'approved', 'Approved for testing purposes', '2025-10-09 02:50:57', '2025-10-09 02:51:52', '2025-10-09 02:50:57', '2025-10-09 02:51:52'),
(38, 1, 16, 'uploads/student_documents/test_16_1759978257.pdf', 'approved', 'Approved for testing purposes', '2025-10-09 02:50:57', '2025-10-09 02:51:52', '2025-10-09 02:50:57', '2025-10-09 02:51:52'),
(53, 33, 8, '../../uploads/student_documents/student_33_moa_1760151051.pdf', 'approved', '', '2025-10-11 02:50:51', '2025-10-11 02:53:12', '2025-10-11 02:50:51', '2025-10-11 02:53:12'),
(54, 33, 2, '../../uploads/student_documents/student_33_endorsement_1760151056.pdf', 'approved', '', '2025-10-11 02:50:56', '2025-10-11 02:53:06', '2025-10-11 02:50:56', '2025-10-11 02:53:06'),
(55, 33, 16, '../../uploads/student_documents/student_33_parental_consent_1760151062.pdf', 'approved', '', '2025-10-11 02:51:02', '2025-10-11 02:52:57', '2025-10-11 02:51:02', '2025-10-11 02:52:57'),
(56, 33, 4, '../../uploads/student_documents/student_33_misdemeanor_penalty_1760151068.pdf', 'approved', '', '2025-10-11 02:51:08', '2025-10-11 02:52:50', '2025-10-11 02:51:08', '2025-10-11 02:52:50'),
(57, 33, 5, '../../uploads/student_documents/student_33_ojt_plan_1760151074.pdf', 'approved', '', '2025-10-11 02:51:14', '2025-10-11 02:52:44', '2025-10-11 02:51:14', '2025-10-11 02:52:44'),
(58, 33, 6, '../../uploads/student_documents/student_33_notarized_consent_1760151079.pdf', 'approved', '', '2025-10-11 02:51:19', '2025-10-11 02:52:38', '2025-10-11 02:51:19', '2025-10-11 02:52:38'),
(59, 33, 14, '../../uploads/student_documents/student_33_pledge_1760151084.pdf', 'approved', '', '2025-10-11 02:51:24', '2025-10-11 02:52:30', '2025-10-11 02:51:24', '2025-10-11 02:52:30'),
(60, 34, 8, '../../uploads/student_documents/student_34_moa_1760178197.pdf', 'approved', NULL, '2025-10-11 10:23:17', '2025-10-11 10:24:26', '2025-10-11 10:23:17', '2025-10-11 10:24:26'),
(61, 34, 2, '../../uploads/student_documents/student_34_endorsement_1760178203.pdf', 'approved', NULL, '2025-10-11 10:23:23', '2025-10-11 10:24:26', '2025-10-11 10:23:23', '2025-10-11 10:24:26'),
(62, 34, 16, '../../uploads/student_documents/student_34_parental_consent_1760178209.pdf', 'approved', NULL, '2025-10-11 10:23:29', '2025-10-11 10:24:26', '2025-10-11 10:23:29', '2025-10-11 10:24:26'),
(63, 34, 4, '../../uploads/student_documents/student_34_misdemeanor_penalty_1760178215.pdf', 'approved', NULL, '2025-10-11 10:23:35', '2025-10-11 10:24:26', '2025-10-11 10:23:35', '2025-10-11 10:24:26'),
(64, 34, 5, '../../uploads/student_documents/student_34_ojt_plan_1760178221.pdf', 'approved', NULL, '2025-10-11 10:23:41', '2025-10-11 10:24:26', '2025-10-11 10:23:41', '2025-10-11 10:24:26'),
(65, 34, 6, '../../uploads/student_documents/student_34_notarized_consent_1760178226.pdf', 'approved', NULL, '2025-10-11 10:23:46', '2025-10-11 10:24:26', '2025-10-11 10:23:46', '2025-10-11 10:24:26'),
(66, 34, 14, '../../uploads/student_documents/student_34_pledge_1760178233.pdf', 'approved', NULL, '2025-10-11 10:23:53', '2025-10-11 10:24:26', '2025-10-11 10:23:53', '2025-10-11 10:24:26'),
(81, 48, 8, '../../uploads/student_documents/student_48_moa_1760608110.pdf', 'approved', NULL, '2025-10-16 09:48:30', '2025-10-16 09:49:42', '2025-10-16 09:48:30', '2025-10-16 09:49:42'),
(82, 48, 2, '../../uploads/student_documents/student_48_endorsement_1760608117.pdf', 'approved', NULL, '2025-10-16 09:48:37', '2025-10-16 09:49:42', '2025-10-16 09:48:37', '2025-10-16 09:49:42'),
(83, 48, 16, '../../uploads/student_documents/student_48_parental_consent_1760608124.pdf', 'approved', NULL, '2025-10-16 09:48:44', '2025-10-16 09:49:42', '2025-10-16 09:48:44', '2025-10-16 09:49:42'),
(84, 48, 4, '../../uploads/student_documents/student_48_misdemeanor_penalty_1760608131.pdf', 'approved', NULL, '2025-10-16 09:48:51', '2025-10-16 09:49:42', '2025-10-16 09:48:51', '2025-10-16 09:49:42'),
(85, 48, 5, '../../uploads/student_documents/student_48_ojt_plan_1760608137.pdf', 'approved', NULL, '2025-10-16 09:48:57', '2025-10-16 09:49:42', '2025-10-16 09:48:57', '2025-10-16 09:49:42'),
(86, 48, 6, '../../uploads/student_documents/student_48_notarized_consent_1760608143.pdf', 'approved', NULL, '2025-10-16 09:49:03', '2025-10-16 09:49:42', '2025-10-16 09:49:03', '2025-10-16 09:49:42'),
(87, 48, 14, '../../uploads/student_documents/student_48_pledge_1760608150.pdf', 'approved', NULL, '2025-10-16 09:49:10', '2025-10-16 09:49:42', '2025-10-16 09:49:10', '2025-10-16 09:49:42'),
(88, 4, 25, 'uploads/student_documents/student_4_custom_25_1760611392.pdf', 'revision_required', 'revise this ', '2025-10-16 10:43:12', '2025-10-16 10:43:50', '2025-10-16 10:43:12', '2025-10-16 10:43:50'),
(89, 21, 25, 'C:\\xampp\\htdocs\\bmadOJT\\src\\Services/../../uploads/student_documents/student_21_resubmit_89_1760615714.pdf', 'approved', 'need sign of supervisor', '2025-10-16 11:55:14', '2025-10-16 11:55:38', '2025-10-16 11:53:40', '2025-10-16 11:55:38'),
(90, 4, 29, '../../uploads/student_documents/submission_4_29_1760723865.pdf', 'pending', NULL, '2025-10-17 17:57:45', NULL, '2025-10-17 17:57:45', '2025-10-17 17:57:45');

-- --------------------------------------------------------

--
-- Table structure for table `student_profiles`
--

CREATE TABLE `student_profiles` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `workplace_name` varchar(255) NOT NULL,
  `supervisor_name` varchar(255) NOT NULL,
  `company_head` varchar(255) NOT NULL,
  `student_position` varchar(255) NOT NULL,
  `ojt_start_date` date NOT NULL,
  `workplace_latitude` decimal(10,8) DEFAULT NULL,
  `workplace_longitude` decimal(11,8) DEFAULT NULL,
  `workplace_location_locked` tinyint(1) DEFAULT 0,
  `total_hours_accumulated` decimal(6,2) DEFAULT 0.00,
  `status` enum('on_track','needs_attention','at_risk') DEFAULT 'on_track',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_profiles`
--

INSERT INTO `student_profiles` (`id`, `user_id`, `workplace_name`, `supervisor_name`, `company_head`, `student_position`, `ojt_start_date`, `workplace_latitude`, `workplace_longitude`, `workplace_location_locked`, `total_hours_accumulated`, `status`, `created_at`, `updated_at`) VALUES
(2, 4, 'Trident Corp.', 'Ms. Jessa Dela Cruz', 'MS. Santos', 'Encoder', '2025-10-07', 10.66605708, 122.93598711, 1, 5.81, 'on_track', '2025-10-07 14:52:52', '2025-10-18 23:32:07'),
(3, 5, 'CHMSU', 'Mr. Wayne Custer', 'I&S Inc.', 'Assistant', '2025-10-07', 10.64275512, 122.93998361, 1, 0.00, 'on_track', '2025-10-07 15:20:40', '2025-10-07 15:20:40'),
(6, 21, 'CHMSU', 'Ms. Jessa Dela Cruz', 'MS. Santos', 'Encoder', '2025-10-09', 10.64283758, 122.93988705, 1, 9.18, 'on_track', '2025-10-09 01:47:01', '2025-10-16 11:56:30'),
(7, 1, 'Test Company', 'John Supervisor', 'Jane Manager', 'OJT Student', '2025-10-02', 10.31570000, 123.88540000, 0, 0.00, 'on_track', '2025-10-09 02:56:53', '2025-10-09 02:56:53'),
(8, 1, 'Test Company', 'John Supervisor', 'Jane Manager', 'OJT Student', '2025-10-02', 10.31570000, 123.88540000, 0, 0.00, 'on_track', '2025-10-09 02:57:52', '2025-10-09 02:57:52'),
(11, 33, 'Jiga corp.', 'Mr. Roj', 'MS. Santos', 'Assistant', '2025-10-11', 10.63471350, 122.95719900, 1, 1.10, 'on_track', '2025-10-11 02:50:35', '2025-10-11 05:20:59'),
(12, 34, 'Trident Corp.', 'Ms. Jessa Dela Cruz', 'MS. Santos', 'Encoder', '2025-10-11', 10.66641768, 122.93605925, 1, 0.00, 'on_track', '2025-10-11 10:23:03', '2025-10-11 10:23:03'),
(17, 48, 'Queno\'s corp', 'Mr. Neil', 'MS. Santos', 'Encoder', '2025-10-16', 10.68286755, 122.95586365, 1, 0.10, 'on_track', '2025-10-16 09:48:04', '2025-10-16 09:57:06'),
(18, 69, 'Trident Corp.', 'Ms. Jessa Dela Cruz', 'MS. Santos', 'Encoder', '2025-10-16', 10.66648833, 122.93620083, 1, 0.00, 'on_track', '2025-10-17 02:31:59', '2025-10-17 02:31:59');

-- --------------------------------------------------------

--
-- Table structure for table `system_config`
--

CREATE TABLE `system_config` (
  `id` int(10) UNSIGNED NOT NULL,
  `config_key` varchar(100) NOT NULL,
  `value` text NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) NOT NULL DEFAULT 'general',
  `is_encrypted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_config`
--

INSERT INTO `system_config` (`id`, `config_key`, `value`, `description`, `category`, `is_encrypted`, `created_at`, `updated_at`) VALUES
(1, 'email_smtp_host', 'smtp.gmail.com', 'SMTP server hostname', 'email', 0, '2025-10-07 14:30:30', '2025-10-07 14:30:30'),
(2, 'email_smtp_port', '587', 'SMTP server port', 'email', 0, '2025-10-07 14:30:30', '2025-10-07 14:30:30'),
(3, 'email_smtp_username', '', 'SMTP username', 'email', 0, '2025-10-07 14:30:30', '2025-10-07 14:30:30'),
(4, 'email_smtp_password', '', 'SMTP password (encrypted)', 'email', 0, '2025-10-07 14:30:30', '2025-10-07 14:30:30'),
(5, 'email_from_address', 'noreply@chmsu.edu.ph', 'Default sender email address', 'email', 0, '2025-10-07 14:30:30', '2025-10-07 14:30:30'),
(6, 'email_from_name', 'OJT Route System', 'Default sender name', 'email', 0, '2025-10-07 14:30:30', '2025-10-07 14:30:30'),
(7, 'email_queue_enabled', '1', 'Enable email queue processing', 'email', 0, '2025-10-07 14:30:30', '2025-10-07 14:30:30'),
(8, 'email_queue_interval', '5', 'Email queue processing interval (minutes)', 'email', 0, '2025-10-07 14:30:30', '2025-10-07 14:30:30'),
(9, 'geolocation_enabled', '1', 'Enable geolocation features', 'geolocation', 0, '2025-10-07 14:30:30', '2025-10-07 14:30:30'),
(10, 'geofence_radius', '40', 'Geofence radius in meters', 'geolocation', 0, '2025-10-07 14:30:30', '2025-10-07 14:30:30'),
(11, 'gps_accuracy_threshold', '20', 'GPS accuracy threshold in meters', 'geolocation', 0, '2025-10-07 14:30:30', '2025-10-07 14:30:30'),
(12, 'location_timeout', '30', 'Location request timeout in seconds', 'geolocation', 0, '2025-10-07 14:30:30', '2025-10-07 14:30:30'),
(13, 'file_upload_max_size', '10485760', 'Maximum file upload size in bytes (10MB)', 'file_upload', 0, '2025-10-07 14:30:30', '2025-10-07 14:30:30'),
(14, 'file_upload_allowed_types', 'pdf,doc,docx,jpg,jpeg,png', 'Allowed file types', 'file_upload', 0, '2025-10-07 14:30:30', '2025-10-07 14:30:30'),
(15, 'image_compression_enabled', '1', 'Enable automatic image compression', 'file_upload', 0, '2025-10-07 14:30:30', '2025-10-07 14:30:30'),
(16, 'image_compression_quality', '80', 'Image compression quality (1-100)', 'file_upload', 0, '2025-10-07 14:30:30', '2025-10-07 14:30:30'),
(17, 'system_name', 'OJT Route', 'System name', 'system', 0, '2025-10-07 14:30:30', '2025-10-07 14:30:30'),
(18, 'system_version', '1.0.0', 'System version', 'system', 0, '2025-10-07 14:30:30', '2025-10-07 14:30:30'),
(19, 'maintenance_mode', '0', 'Maintenance mode enabled', 'system', 0, '2025-10-07 14:30:30', '2025-10-07 14:30:30'),
(20, 'session_timeout', '1800', 'Session timeout in seconds (30 minutes)', 'system', 0, '2025-10-07 14:30:30', '2025-10-07 14:30:30'),
(21, 'password_min_length', '8', 'Minimum password length', 'system', 0, '2025-10-07 14:30:30', '2025-10-07 14:30:30'),
(22, 'ojt_required_hours', '600', 'Required OJT hours for completion', 'system', 0, '2025-10-07 14:30:30', '2025-10-07 14:30:30'),
(23, 'attendance_blocks_enabled', '1', 'Enable attendance blocks (morning/afternoon)', 'system', 0, '2025-10-07 14:30:30', '2025-10-07 14:30:30'),
(24, 'overtime_enabled', '1', 'Enable overtime attendance block', 'system', 0, '2025-10-07 14:30:30', '2025-10-07 14:30:30'),
(25, 'login_attempts_limit', '5', 'Maximum login attempts before lockout', 'security', 0, '2025-10-07 14:30:30', '2025-10-07 14:30:30'),
(26, 'login_lockout_duration', '900', 'Account lockout duration in seconds (15 minutes)', 'security', 0, '2025-10-07 14:30:30', '2025-10-07 14:30:30'),
(27, 'password_reset_enabled', '1', 'Enable password reset functionality', 'security', 0, '2025-10-07 14:30:30', '2025-10-07 14:30:30'),
(28, 'two_factor_enabled', '0', 'Enable two-factor authentication', 'security', 0, '2025-10-07 14:30:30', '2025-10-07 14:30:30'),
(29, 'notification_email_enabled', '1', 'Enable email notifications', 'notification', 0, '2025-10-07 14:30:30', '2025-10-07 14:30:30'),
(30, 'notification_sms_enabled', '0', 'Enable SMS notifications', 'notification', 0, '2025-10-07 14:30:30', '2025-10-07 14:30:30'),
(31, 'notification_push_enabled', '0', 'Enable push notifications', 'notification', 0, '2025-10-07 14:30:30', '2025-10-07 14:30:30'),
(32, 'notification_reminder_days', '7', 'Days before deadline to send reminders', 'notification', 0, '2025-10-07 14:30:30', '2025-10-07 14:30:30'),
(33, 'backup_enabled', '1', 'Enable automatic backups', 'backup', 0, '2025-10-07 14:30:30', '2025-10-07 14:30:30'),
(34, 'backup_interval', '24', 'Backup interval in hours', 'backup', 0, '2025-10-07 14:30:30', '2025-10-07 14:30:30'),
(35, 'backup_retention_days', '30', 'Backup retention period in days', 'backup', 0, '2025-10-07 14:30:30', '2025-10-07 14:30:30'),
(36, 'backup_location', '../backups/', 'Backup storage location', 'backup', 0, '2025-10-07 14:30:30', '2025-10-07 14:30:30');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `school_id` varchar(20) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `role` enum('student','instructor','admin') NOT NULL,
  `section_id` int(10) UNSIGNED DEFAULT NULL,
  `profile_picture` varchar(500) DEFAULT NULL,
  `gender` enum('male','female','non-binary') DEFAULT NULL,
  `contact` varchar(20) DEFAULT NULL,
  `facebook_name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `school_id`, `password_hash`, `email`, `full_name`, `role`, `section_id`, `profile_picture`, `gender`, `contact`, `facebook_name`, `created_at`, `updated_at`) VALUES
(1, 'ADM10052500', '$2y$10$7pZ.t3P4AHle2i.gJ6is5.LvVZmNCt33cdv8Bi8xpDe3UM1Sf9AM6', 'admin@chmsu.edu.ph', 'System Administrator', 'admin', 1, NULL, NULL, NULL, NULL, '2025-10-07 14:30:05', '2025-10-09 02:54:25'),
(4, 'VAJ10130300', '$2y$10$nKLDyWDJNWxWnTk4fohM8erVAAcyFGei/YRwSzHJmsJwwvs7XxDh.', 'mzakcoloradz18@gmail.com', 'Vince Philippe Judilla', 'student', 2, 'profile_4_1760828704.png', 'male', '09402273447', 'Vince Philippee', '2025-10-07 14:39:25', '2025-10-18 23:05:04'),
(5, 'ROM02140200', '$2y$10$PFYiPiKRoW8c8EOmXuWrMemosgZdwgLHcao47xSXdAMc0cM.XjVFq', 'ronmedel@gmail.com', 'Ron Medel', 'student', 2, NULL, 'male', '09502233424', '', '2025-10-07 14:39:53', '2025-10-08 05:03:07'),
(20, 'JAC09121900', '$2y$10$O6ROA1ztM7CTKwR9jCMmleXB8Ypr7icBVCzvPKrrAMOr1Gewhkf1.', 'Rassel12@gmail.com', 'Jean Castor', 'instructor', 2, 'profile_20_1760821923.png', 'male', '', '', '2025-10-09 01:40:55', '2025-10-18 21:12:03'),
(21, 'CMA12040300', '$2y$10$Jy5VWaQNHwk1chkFtgmOSusuHc59.wJyszVG6LGbMaWdovh7FgMdi', 'coloradomanuel.002@gmail.com', 'Manuel Colorado', 'student', 2, 'profile_21_1760829771.png', 'male', '', '', '2025-10-09 01:45:53', '2025-10-18 23:22:51'),
(33, 'noeme', '$2y$10$Zg9BA8f7FZ6SpDpGrUJbJOSCtYwolKv7WHod0UuWVJjP2oGGKMTAK', 'noem@gmail.com', 'Noeme O Nasis', 'student', 2, NULL, 'female', '09273384958', 'noeme', '2025-10-11 02:49:12', '2025-10-11 02:49:12'),
(34, 'KIM10030200', '$2y$10$XUMvSnWLNKGSE6ozfOrfOOBCPAV7LlqnsDT45Qz.54LfzwcYbFfde', 'kim@gmail.com', 'Kim T Diaz', 'student', 2, NULL, 'non-binary', '09364536546', '', '2025-10-11 10:22:25', '2025-10-11 10:22:25'),
(48, 'JDL12050600', '$2y$10$CdNKf/J5p9mUK/s6HxUIx.5OG1GNpVNikortTK2rxkesX6X8QXT16', 'diomed@gmail.com', 'Diomed Latonero', 'student', 2, NULL, 'male', '', '', '2025-10-16 09:46:57', '2025-10-16 09:46:57'),
(66, 'LJD12040301', '$2y$10$Z9K5fLKlBRfFadHf45Qp1.cqV/3hROuBV7Bc6lNdWYduJb4IMRssa', 'john.doe@chmsu.edu.ph', 'John Doe Lopez', 'student', 4, NULL, '', '9123456788', 'John Doe', '2025-10-16 12:13:35', '2025-10-16 12:13:35'),
(67, 'JSM12040302', '$2y$10$afGfTXHRjIjaHqqU5esCNuJnGzTG7MQIf7.Ttt2TfXaWIbx.ckgCy', 'jane.smith@chmsu.edu.ph', 'Jane Smith Martinez', 'student', 4, NULL, 'female', '9123456789', 'Jane Smith', '2025-10-16 12:13:35', '2025-10-16 12:13:35'),
(68, 'MJG12040303', '$2y$10$VxVaHbiFzPdYL0keREvUUOm3B4HIBO4gO8y.W8LhBfrNSmlMyFWZK', 'mike.johnson@chmsu.edu.ph', 'Mike Johnson Garcia', 'student', 4, NULL, 'male', '9123456790', 'Mike Johnson', '2025-10-16 12:13:35', '2025-10-16 12:13:35'),
(69, 'SLR12040304', '$2y$10$9704MNATmQDyuoYD13XaseqqVcIfl4C93nSyx2sZahnLvgB9TrP8O', 'sarah.lee@chmsu.edu.ph', 'Sarah Lee Rodriguez', 'student', 4, NULL, 'female', '9123456791', 'Sarah Lee', '2025-10-16 12:13:36', '2025-10-16 12:13:36'),
(70, 'DLW12040305', '$2y$10$BGwTx7/99pWliMfa6Zp0U.1QPoOVB/L7gV.kIJZnPil9.vZzgo0s2', 'david.wilson@chmsu.edu.ph', 'David Wilson Lopez', 'student', 4, NULL, 'male', '9123456792', 'David Wilson', '2025-10-16 12:13:36', '2025-10-16 12:13:36'),
(71, 'TEN02930400', '$2y$10$UfswjdkUXVa6lXy2Wph3oemYQF/HjFJ.8HCRiNnpPz4lQxpecZEt.', 'ten@gmail.com', 'Ten Giovanni', 'instructor', 6, NULL, 'male', '09402273446', '', '2025-10-16 12:14:37', '2025-10-16 12:17:18');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_date` (`date`),
  ADD KEY `idx_block_type` (`block_type`),
  ADD KEY `idx_time_in` (`time_in`),
  ADD KEY `idx_time_out` (`time_out`),
  ADD KEY `idx_student_date_block` (`student_id`,`date`,`block_type`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_document_type` (`document_type`),
  ADD KEY `idx_uploaded_by` (`uploaded_by`),
  ADD KEY `idx_uploaded_for_section` (`uploaded_for_section`),
  ADD KEY `idx_deadline` (`deadline`);

--
-- Indexes for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_recipient` (`recipient_email`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `email_queue`
--
ALTER TABLE `email_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_recipient_email` (`recipient_email`);

--
-- Indexes for table `forgot_timeout_requests`
--
ALTER TABLE `forgot_timeout_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_attendance_record_id` (`attendance_record_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_request_date` (`request_date`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sender_id` (`sender_id`),
  ADD KEY `idx_recipient_id` (`recipient_id`),
  ADD KEY `idx_section_id` (`section_id`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `section_code` (`section_code`),
  ADD KEY `idx_section_code` (`section_code`),
  ADD KEY `idx_instructor_id` (`instructor_id`);

--
-- Indexes for table `student_documents`
--
ALTER TABLE `student_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_document_id` (`document_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_submitted_at` (`submitted_at`);

--
-- Indexes for table `student_profiles`
--
ALTER TABLE `student_profiles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_workplace_location` (`workplace_latitude`,`workplace_longitude`);

--
-- Indexes for table `system_config`
--
ALTER TABLE `system_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `config_key` (`config_key`),
  ADD KEY `idx_config_key` (`config_key`),
  ADD KEY `idx_category` (`category`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `school_id` (`school_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_school_id` (`school_id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_section_id` (`section_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=619;

--
-- AUTO_INCREMENT for table `attendance_records`
--
ALTER TABLE `attendance_records`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `email_queue`
--
ALTER TABLE `email_queue`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `forgot_timeout_requests`
--
ALTER TABLE `forgot_timeout_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `student_documents`
--
ALTER TABLE `student_documents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;

--
-- AUTO_INCREMENT for table `student_profiles`
--
ALTER TABLE `student_profiles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `system_config`
--
ALTER TABLE `system_config`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD CONSTRAINT `attendance_records_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `documents_ibfk_2` FOREIGN KEY (`uploaded_for_section`) REFERENCES `sections` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `forgot_timeout_requests`
--
ALTER TABLE `forgot_timeout_requests`
  ADD CONSTRAINT `forgot_timeout_requests_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `forgot_timeout_requests_ibfk_2` FOREIGN KEY (`attendance_record_id`) REFERENCES `attendance_records` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sections`
--
ALTER TABLE `sections`
  ADD CONSTRAINT `sections_ibfk_1` FOREIGN KEY (`instructor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `student_documents`
--
ALTER TABLE `student_documents`
  ADD CONSTRAINT `student_documents_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_documents_ibfk_2` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_profiles`
--
ALTER TABLE `student_profiles`
  ADD CONSTRAINT `student_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
