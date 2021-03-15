
-- --------------------------------------------------------

--
-- Структура таблицы `remote_syslog`
--

CREATE TABLE `remote_syslog` (
  `id` int(11) NOT NULL,
  `date` timestamp NOT NULL DEFAULT current_timestamp(),
  `device_id` int(11) NOT NULL,
  `ip` varchar(15) NOT NULL,
  `message` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
