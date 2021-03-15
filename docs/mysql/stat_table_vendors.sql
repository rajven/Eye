
-- --------------------------------------------------------

--
-- Структура таблицы `vendors`
--

CREATE TABLE `vendors` (
  `id` int(11) NOT NULL,
  `name` varchar(40) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Дамп данных таблицы `vendors`
--

INSERT INTO `vendors` (`id`, `name`) VALUES
(1, 'Unknown'),
(2, 'Eltex'),
(3, 'Huawei'),
(4, 'Zyxel'),
(5, 'Raisecom'),
(6, 'SNR'),
(7, 'Dlink'),
(8, 'Allied Telesis'),
(9, 'Mikrotik'),
(10, 'NetGear'),
(11, 'Ubiquiti'),
(15, 'HP'),
(16, 'Cisco'),
(17, 'Maipu'),
(18, 'Asus'),
(19, 'Linux');
