
-- --------------------------------------------------------

--
-- Структура таблицы `Group_list`
--

CREATE TABLE `Group_list` (
  `id` int(11) NOT NULL,
  `group_name` varchar(30) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Дамп данных таблицы `Group_list`
--

INSERT INTO `Group_list` (`id`, `group_name`) VALUES
(0, 'default'),
(1, 'Allow all'),
(2, 'Users'),
(3, 'Support'),
(4, 'VOIP'),
(5, 'POS');
