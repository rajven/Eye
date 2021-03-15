
-- --------------------------------------------------------

--
-- Структура таблицы `User_stats`
--

CREATE TABLE `User_stats` (
  `id` int(11) NOT NULL,
  `router_id` int(11) DEFAULT 0,
  `auth_id` int(11) NOT NULL DEFAULT 0,
  `timestamp` datetime NOT NULL DEFAULT current_timestamp(),
  `byte_in` int(11) NOT NULL DEFAULT 0,
  `byte_out` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
