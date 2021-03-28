
-- --------------------------------------------------------

--
-- Структура таблицы `OU`
--

CREATE TABLE `OU` (
  `id` int(11) NOT NULL,
  `ou_name` varchar(40) DEFAULT NULL,
  `nagios_dir` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Дамп данных таблицы `OU`
--

INSERT INTO `OU` (`id`, `ou_name`, `nagios_dir`) VALUES
(0, 'Все', '/etc/nagios/any'),
(1, 'Сервера', NULL),
(2, 'Администраторы', NULL),
(3, 'Пользователи', NULL),
(4, 'VOIP', '/etc/nagios/voip'),
(5, 'IPCAM', '/etc/nagios/videocam'),
(6, 'Принтеры', '/etc/nagios/printers'),
(7, 'Свичи', '/etc/nagios/switches'),
(8, 'UPS', '/etc/nagios/ups'),
(9, 'Охрана', '/etc/nagios/security'),
(10, 'Роутеры', '/etc/nagios/routers'),
(11, 'IPTV', NULL),
(12, 'WiFi AP', '/etc/nagios/ap'),
(13, 'Техподдержка', NULL),
(14, 'POS-терминалы', NULL),
(23, 'WiFi', NULL),
(24, 'VPN', NULL);
