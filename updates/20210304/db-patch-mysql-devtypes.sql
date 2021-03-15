
CREATE TABLE `device_types` (
  `id` int(11) NOT NULL,
  `name` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Дамп данных таблицы `device_types`
--

INSERT INTO `device_types` (`id`, `name`) VALUES
(1, 'Свич'),
(2, 'Роутер'),
(3, 'Сервер'),
(4, 'Точка доступа');

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `device_types`
--
ALTER TABLE `device_types`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `device_types`
--
ALTER TABLE `device_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
