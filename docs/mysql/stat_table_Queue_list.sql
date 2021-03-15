
-- --------------------------------------------------------

--
-- Структура таблицы `Queue_list`
--

CREATE TABLE `Queue_list` (
  `id` int(11) NOT NULL,
  `queue_name` varchar(20) NOT NULL,
  `Download` int(11) NOT NULL DEFAULT 0,
  `Upload` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Дамп данных таблицы `Queue_list`
--

INSERT INTO `Queue_list` (`id`, `queue_name`, `Download`, `Upload`) VALUES
(0, 'unlimited', 0, 0),
(1, '2M/2M', 2048, 2048),
(2, '10M/10M', 10240, 10240),
(3, '100M/100M', 102400, 102400),
(4, '50M/50M', 50000, 50000),
(5, '20M/20M', 20480, 20480),
(6, '200M/200M', 212400, 212400);
