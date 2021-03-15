
-- --------------------------------------------------------

--
-- Структура таблицы `mac_history`
--

CREATE TABLE `mac_history` (
  `id` int(10) UNSIGNED NOT NULL,
  `mac` varchar(12) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `device_id` int(11) DEFAULT NULL,
  `port_id` int(11) DEFAULT NULL,
  `ip` varchar(16) NOT NULL DEFAULT '',
  `auth_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
