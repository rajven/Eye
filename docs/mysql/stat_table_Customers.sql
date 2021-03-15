
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
(1, 'roman', '7a4415568f11d805b6eef1adb2b0af59', 0);
