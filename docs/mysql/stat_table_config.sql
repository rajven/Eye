
-- --------------------------------------------------------

--
-- Структура таблицы `config`
--

CREATE TABLE `config` (
  `id` int(11) NOT NULL,
  `option_id` int(11) DEFAULT NULL,
  `value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Дамп данных таблицы `config`
--

INSERT INTO `config` (`id`, `option_id`, `value`) VALUES
(1, 1, '1024'),
(2, 22, '1'),
(3, 17, '1');