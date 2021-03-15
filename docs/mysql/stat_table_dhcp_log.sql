
-- --------------------------------------------------------

--
-- Структура таблицы `dhcp_log`
--

CREATE TABLE `dhcp_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `mac` varchar(17) NOT NULL,
  `ip_int` int(10) UNSIGNED NOT NULL,
  `ip` varchar(15) NOT NULL,
  `action` varchar(10) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `auth_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
