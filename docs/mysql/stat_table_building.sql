
-- --------------------------------------------------------

--
-- Структура таблицы `building`
--

CREATE TABLE `building` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `comment` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Дамп данных таблицы `building`
--

INSERT INTO `building` (`id`, `name`, `comment`) VALUES
(1, 'Earth', 'Somewhere');
