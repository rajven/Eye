
-- --------------------------------------------------------

--
-- Структура таблицы `dns_cache`
--

CREATE TABLE `dns_cache` (
  `id` int(11) NOT NULL,
  `dns` varchar(250) DEFAULT NULL,
  `ip` int(11) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
