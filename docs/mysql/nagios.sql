-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- Хост: localhost
-- Время создания: Фев 03 2025 г., 17:41
-- Версия сервера: 10.6.18-MariaDB-0ubuntu0.22.04.1-log
-- Версия PHP: 8.1.2-1ubuntu2.19

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `nagios`
--

-- --------------------------------------------------------

--
-- Структура таблицы `netdevices`
--

CREATE TABLE `netdevices` (
  `id` bigint(20) NOT NULL,
  `device_id` int(11) NOT NULL DEFAULT 0,
  `changed` bigint(20) DEFAULT NULL,
  `data_id` int(11) NOT NULL DEFAULT 0,
  `data_type` int(11) NOT NULL DEFAULT 0,
  `data_value1` bigint(20) NOT NULL DEFAULT 0,
  `data_value2` bigint(20) NOT NULL DEFAULT 0,
  `data_value3` bigint(20) NOT NULL DEFAULT 0,
  `data_value4` bigint(20) NOT NULL DEFAULT 0,
  `ip` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `netdevices`
--
ALTER TABLE `netdevices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `device_id` (`device_id`,`data_id`,`data_type`),
  ADD KEY `data_id` (`data_id`,`data_type`,`ip`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `netdevices`
--
ALTER TABLE `netdevices`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
