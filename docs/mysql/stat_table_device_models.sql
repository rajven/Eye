
--
-- Структура таблицы `device_models`
--

CREATE TABLE `device_models` (
  `id` int(11) NOT NULL,
  `model_name` varchar(200) DEFAULT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `nagios_template` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `device_models`
--
ALTER TABLE `device_models`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `device_models`
--
ALTER TABLE `device_models`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
