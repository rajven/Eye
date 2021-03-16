
-- --------------------------------------------------------

--
-- Структура таблицы `Customers`
--

CREATE TABLE `Customers` (
  `id` int(11) NOT NULL,
  `Login` varchar(20) DEFAULT 'NULL',
  `Pwd` varchar(32) DEFAULT 'NULL',
  `readonly` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Дамп данных таблицы `Customers`
--

INSERT INTO `Customers` (`id`, `Login`, `Pwd`, `readonly`) VALUES
(1, 'admin', '01a5aa14009873f841eab46082418496', 0);
