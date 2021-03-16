
-- --------------------------------------------------------

--
-- Структура таблицы `device_l3_interfaces`
--

CREATE TABLE `device_l3_interfaces` (
  `id` int(11) NOT NULL,
  `device_id` int(11) DEFAULT NULL,
  `interface_type` int(11) NOT NULL DEFAULT 0,
  `name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
