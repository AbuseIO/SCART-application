-- phpMyAdmin SQL Dump
-- version 4.8.4
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Gegenereerd op: 01 sep 2019 om 16:14
-- Serverversie: 5.5.62-MariaDB
-- PHP-versie: 7.0.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `eokm_release_2`
--

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `backend_user_roles`
--

DROP TABLE IF EXISTS `backend_user_roles`;
CREATE TABLE `backend_user_roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `permissions` text COLLATE utf8mb4_unicode_ci,
  `is_system` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `backend_user_roles`
--

INSERT INTO `backend_user_roles` (`id`, `name`, `code`, `description`, `permissions`, `is_system`, `created_at`, `updated_at`) VALUES
(1, 'Publisher', 'publisher', 'Site editor with access to publishing tools.', '', 1, '2019-01-05 10:47:23', '2019-01-05 10:47:23'),
(2, 'Developer', 'developer', 'Site administrator with access to developer tools.', '', 1, '2019-01-05 10:47:23', '2019-01-05 10:47:23'),
(3, 'ERTmanager', 'ERTmanager', 'ERT manager role', '{\"reportertool.eokm.startpage\":\"1\",\"reportertool.eokm.input_manage\":\"1\",\"reportertool.eokm.grade_notifications\":\"1\",\"reportertool.eokm.abusecontact_manage\":\"1\",\"reportertool.eokm.manual_read\":\"1\"}', 0, '2019-01-31 14:14:54', '2019-09-01 13:48:10'),
(4, 'ERTuser', 'ERTuser', 'ERT user role', '{\"reportertool.eokm.startpage\":\"1\",\"reportertool.eokm.input_manage\":\"1\",\"reportertool.eokm.grade_notifications\":\"1\",\"reportertool.eokm.manual_read\":\"1\"}', 0, '2019-02-05 13:14:51', '2019-09-01 13:52:53'),
(5, 'ERTscheduler', 'ERTscheduler', 'ERTscheduler role', '', 0, '2019-05-31 08:58:04', '2019-05-31 08:58:04'),
(6, 'ERTadmin', 'ERTadmin', 'Admin rights', '{\"reportertool.eokm.startpage\":\"1\",\"reportertool.eokm.input_manage\":\"1\",\"reportertool.eokm.grade_notifications\":\"1\",\"reportertool.eokm.reporting\":\"1\",\"reportertool.eokm.utility\":\"1\",\"reportertool.eokm.abusecontact_manage\":\"1\",\"reportertool.eokm.grade_questions\":\"1\",\"reportertool.eokm.ntdtemplate_manage\":\"1\",\"reportertool.eokm.whois\":\"1\",\"reportertool.eokm.manual_read\":\"1\",\"reportertool.eokm.manual_write\":\"1\"}', 0, '2019-09-01 13:47:20', '2019-09-01 13:47:20');

--
-- Indexen voor geëxporteerde tabellen
--

--
-- Indexen voor tabel `backend_user_roles`
--
ALTER TABLE `backend_user_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_unique` (`name`),
  ADD KEY `role_code_index` (`code`);

--
-- AUTO_INCREMENT voor geëxporteerde tabellen
--

--
-- AUTO_INCREMENT voor een tabel `backend_user_roles`
--
ALTER TABLE `backend_user_roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

TRUNCATE backend_user_groups;
INSERT INTO `backend_user_groups` (`id`, `name`, `created_at`, `updated_at`, `code`, `description`, `is_new_user_default`) VALUES
(1, 'Owners', '2019-01-05 10:47:23', '2019-01-05 10:47:23', 'owners', 'Default group for website owners.', 0),
(2, 'ERTworkuser', '2019-05-31 08:59:32', '2019-05-31 08:59:32', 'ERTworkuser', 'ERTworkuser can have work', 0);

