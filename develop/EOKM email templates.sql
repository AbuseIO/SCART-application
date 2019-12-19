-- phpMyAdmin SQL Dump
-- version 4.8.4
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Gegenereerd op: 01 sep 2019 om 14:22
-- Serverversie: 5.5.62-MariaDB
-- PHP-versie: 7.0.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `reportertool_eokm_ntd_template`
--

DROP TABLE IF EXISTS `reportertool_eokm_ntd_template`;
CREATE TABLE `reportertool_eokm_ntd_template` (
  `id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `reportertool_eokm_ntd_template`
--

INSERT INTO `reportertool_eokm_ntd_template` (`id`, `created_at`, `updated_at`, `deleted_at`, `title`, `subject`, `body`) VALUES
(1, '2019-08-30 15:32:52', '2019-09-01 17:18:54', NULL, 'Standaard NTD template', 'Notice & Take Down message from Meldpunt Kinderporno', '<p>Dear Sir/Madam,</p>\r\n\r\n<p><strong>Who we are</strong>\r\n	<br>The Dutch Hotline combating Child Pornography on the Internet (Meldpunt Kinderporno) is an independent private foundation. Meldpunt receives subsidies to support its activities from the Dutch Ministry of Security and Justice and the European Commission. Since the start of the Dutch Hotline there has been a close cooperation between the Hotline and the Dutch Police. We are also one on the founders of <a href=\"http://www.inhope.org/\">http://www.inhope.org/&nbsp;</a>INHOPE, the international network of Internet hotline against child sexual abuse on the Internet. The report procedure, together with supporting information, is published on our website.\r\n	<br>\r\n	<br><strong>Reporting child sexual abuse content</strong>\r\n	<br>Meldpunt Kinderporno would like to make you aware by reporting the URL below as containing child sexual abuse content as assessed under Dutch Law.</p>\r\n\r\n<p>{{abuselinks}}</p>\r\n\r\n<p>\r\n	<br>\r\n</p>\r\n\r\n<p>Should you require any further information regarding this matter, please contact us by email <a href=\"mailto:info@meldpunt-kinderporno.nl\">info@meldpunt-kinderporno.nl</a></p>\r\n\r\n<p>\r\n	<br>\r\n</p>\r\n\r\n<p>Kind regards,</p>\r\n\r\n<p>Meldpunt Kinderporno op Internet | Dutch hotline against child sexual abuse material on the Internet <a href=\"http://www.meldpunt-kinderporno.nl\">www.meldpunt-kinderporno.nl</a></p>\r\n\r\n<p>\r\n	<br>\r\n</p>'),
(2, '2019-09-01 12:12:40', '2019-09-01 12:20:03', NULL, 'Standaard POLICE temlate', 'Illegal content found', '<p>Dear Sir/Madam,</p>\r\n\r\n<p><strong>Reporting child sexual abuse content</strong>\r\n	<br>Meldpunt Kinderporno would like to make you aware by reporting the URL below as containing child sexual abuse content as assessed under Dutch Law.\r\n	<br>\r\n	<br>BEGIN</p>\r\n\r\n<p>{{abuselinks}}</p>\r\n\r\n<p>END</p>\r\n\r\n<p>Should you require any further information regarding this matter, please contact us by email <a href=\"mailto:info@meldpunt-kinderporno.nl\">info@meldpunt-kinderporno.nl</a></p>\r\n\r\n<p>\r\n	<br>\r\n</p>\r\n\r\n<p>Kind regards,</p>\r\n\r\n<p>Meldpunt Kinderporno op Internet | Dutch hotline against child sexual abuse material on the Internet <a href=\"http://www.meldpunt-kinderporno.nl\">www.meldpunt-kinderporno.nl</a></p>\r\n\r\n<p>\r\n	<br>\r\n</p>\r\n\r\n<p><strong>Who we are</strong> The Dutch Hotline combating Child Pornography on the Internet (Meldpunt Kinderporno) is an independent private foundation. Meldpunt receives subsidies to support its activities from the Dutch Ministry of Security and Justice and the European Commission. Since the start of the Dutch Hotline there has been a close cooperation between the Hotline and the Dutch Police. We are also one on the founders of <a href=\"http://www.inhope.org/\">http://www.inhope.org/</a>INHOPE, the international network of Internet hotline against child sexual abuse on the Internet. The report procedure, together with supporting information, is published on our website.</p>');

--
-- Indexen voor geëxporteerde tabellen
--

--
-- Indexen voor tabel `reportertool_eokm_ntd_template`
--
ALTER TABLE `reportertool_eokm_ntd_template`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT voor geëxporteerde tabellen
--

--
-- AUTO_INCREMENT voor een tabel `reportertool_eokm_ntd_template`
--
ALTER TABLE `reportertool_eokm_ntd_template`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;
