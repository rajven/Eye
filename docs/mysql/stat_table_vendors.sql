--
-- Структура таблицы `vendors`
--

CREATE TABLE `vendors` (
  `id` int(11) NOT NULL,
  `name` varchar(40) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Очистить таблицу перед добавлением данных `vendors`
--

TRUNCATE TABLE `vendors`;
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
(19, 'Linux'),
(20, 'APC'),
(21, 'Panasonic'),
(22, 'OKI'),
(23, 'Samsung'),
(24, 'EATON'),
(25, 'Apple'),
(26, 'Epson'),
(27, 'Schneider'),
(28, 'Avaya'),
(29, 'Hikvision'),
(30, 'HW-group'),
(31, 'Netping'),
(32, 'Trassir'),
(33, 'QSC');

--
ALTER TABLE `vendors`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `vendors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;
