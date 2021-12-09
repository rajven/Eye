-- --------------------------------------------------------

--
-- Структура таблицы `auth_rules`
--

CREATE TABLE `auth_rules` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ou_id` int(11) DEFAULT NULL,
  `type` int(11) NOT NULL,
  `rule` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `building`
--

CREATE TABLE `building` (
  `id` int(11) NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `building`
--

INSERT INTO `building` (`id`, `name`, `comment`) VALUES(1, 'Earth', 'Somewhere');

-- --------------------------------------------------------

--
-- Структура таблицы `config`
--

CREATE TABLE `config` (
  `id` int(11) NOT NULL,
  `option_id` int(11) DEFAULT NULL,
  `value` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `config`
--

INSERT INTO `config` (`id`, `option_id`, `value`) VALUES(1, 1, '1024');
INSERT INTO `config` (`id`, `option_id`, `value`) VALUES(77, 41, '/usr/local/scripts/fetch_new_arp.pl');
INSERT INTO `config` (`id`, `option_id`, `value`) VALUES(78, 37, '/usr/local/scripts/sync_mikrotik.pl');

-- --------------------------------------------------------

--
-- Структура таблицы `config_options`
--

CREATE TABLE `config_options` (
  `id` int(11) NOT NULL,
  `option_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uniq` tinyint(1) NOT NULL DEFAULT 1,
  `type` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `default_value` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `min_value` int(11) NOT NULL DEFAULT 0,
  `max_value` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `config_options`
--

INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(1, 'KB', 'Число байт в килобайте', 1, 'int', '1024', 1000, 1024);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(3, 'dns server', 'ip-адрес DNS-сервера', 1, 'text', '127.0.0.1', 0, 0);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(5, 'dhcp server', 'ip-адрес DHCP-сервера', 1, 'text', '127.0.0.1', 0, 0);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(9, 'default snmp version', 'Версия snmp по умолчанию. В настоящий момент поддерживаются 1 и 2. Поддержка версии 3 в разработке.', 1, 'int', '2', 1, 3);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(11, 'default snmp community', 'Read snmp community по умолчанию', 1, 'text', 'public', 0, 0);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(17, 'mac discavery', 'Выполнять опрос mac-таблицы коммутаторов при сканировании сети. Если опция отключена будет нельзя сопоставить ip-адрес порту коммутатора', 1, 'bool', '1', 0, 0);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(19, 'arp discavery', 'Выполнять сканирование arp-таблицы роутеров при сканировании сети. Если опция отключена сопоставление mac-адреса ip-адресу будет возможно только из логов dhcp-сервера.', 1, 'bool', '1', 0, 0);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(21, 'admin email', 'E-mail администратора', 1, 'text', 'root', 0, 0);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(22, 'add user from netflow', 'Создавать ли новые записи для неизвестных адресов из анализа трафика netflow. Обычно выключается, поскольку может создавать много фэйковых адресов - например при пинге подсети, когда пакеты идут на все, даже не существующие адреса. Имеет смысл включать только на маршрутизаторах во внешние сети.', 1, 'bool', '0', 0, 0);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(23, 'save traffic detail', 'Сохранять ли детализацию трафика из netflow по ip-адресам пользователей', 1, 'bool', '1', 0, 0);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(26, 'history detail traffic', 'Глубина хранения детализации в сутках. Установка значения больше 3-7 дней приведёт к разрастанию базы данных и увеличит время отображения детализации в интерфейсе администратора', 1, 'int', '3', 1, 7);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(27, 'history dhcp lease', 'Глубина хранения аренды dhcp-сервера', 1, 'int', '1', 0, 0);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(28, 'router_login', 'Логин для входа на маршрутизаторы Mikrotik для управления dhcp-сервером и контролем доступа', 1, 'text', 'admin', 0, 0);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(29, 'router_password', 'Пароль для входа на маршрутизаторы Mikrotik для управления dhcp-сервером и контролем доступа', 1, 'text', 'admin', 0, 0);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(30, 'router_port', 'Порт ssh маршрутизатора', 1, 'int', '22', 22, 0);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(32, 'org name', 'Название организации', 1, 'text', 'ORG', 0, 0);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(33, 'office domain', 'Домен организации', 1, 'text', 'local', 0, 0);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(34, 'debug', 'Включить отладку', 1, 'bool', '0', 0, 0);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(35, 'connections history, days', 'Время хранения истории мест подключения ip-адресов', 1, 'int', '90', 1, 365);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(37, 'refresh access lists', 'Расположение скрипта управления контролем доступа для роутеров Mikrotik', 1, 'text', '/usr/local/scripts/sync_user_list.pl', 0, 0);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(38, 'regenerate dhcp cconfig', 'Расположение скрипта управления конфигурацией dhcp-серверами', 1, 'text', '/usr/local/scripts/update-dnsmasq', 0, 0);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(39, 'regenerate dns cconfig', 'Расположение скрипта управления dns-сервером', 1, 'text', '/usr/local/scripts/update-dns', 0, 0);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(40, 'regenerate nagios cconfig', 'Расположение скрипта конфигурирования Nagios', 1, 'text', '/etc/nagios/restart_nagios', 0, 0);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(41, 'discavery network', 'Расположение скрипта сканирования сети', 1, 'text', '/usr/local/scripts/fetch_new_arp.pl', 0, 0);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(44, 'Ignore hotspot dhcp log', 'Не писать лог событий dhcp-сервера хотспота. Имеет смысл вклчючать, поскольку время аренды в хот-споте как правило маленькое и в записях хот-спота становятся незаметны логи обычных пользователей', 1, 'bool', '1', 0, 0);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(45, 'ignore update dhcp event', 'Не писать события обновления ip-адреса dhcp-сервера. ', 1, 'bool', '0', 0, 0);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(46, 'update hostname from dhcp', 'Обновлять имя хоста в DNS при получении адреса по DHCP', 1, 'bool', '0', 0, 0);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(47, 'history worklog', 'Глубина хранения логов работы в интерфейсе администратора', 1, 'int', '90', 30, 1095);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(48, 'history syslog', 'Глубина хранения логов работы syslog-сервера', 1, 'int', '90', 30, 1095);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(49, 'history traffic stats', 'Глубина хранения статистики трафика юзеров', 1, 'int', '365', 30, 0);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(50, 'urgent sync access', 'Немедленное изменение списков доступа на роутере после правки записи пользователя', 1, 'bool', '0', 0, 0);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(51, 'Email_alert', 'Отправлять e-mail сообщения для уровней сообщений WARNING & ERROR', 1, 'bool', '1', 0, 0);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(52, 'Sender email', 'E-mail адрес, с которого рассылается почта', 1, 'text', 'root', 0, 0);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(53, 'log level', 'Каждый уровень включает в себя предыдущий:\r\n0 - ERROR - писать только ошибки\r\n1 - WARNING - писать предупреждения\r\n2 - INFO - писать информационные сообщения\r\n3 - VERBOSE - писать подробную информацию о выполняемых операциях', 1, 'int', '2', 0, 3);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(54, 'enable_quotes', 'Включить обработку квот по трафику', 1, 'bool', '0', 0, 0);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(55, 'netflow_step', 'Интервал сброса данных из коллектора netflow, минуты', 1, 'int', '10', 1, 60);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(56, 'traffic_ipstat_history', 'Время хранения полной статистики по трафику для каждого ip-адреса в сутках. Таблица в 6 раз больше обычной часовой статистики. Врядли кому-то потребуется глубина хранения более месяца.', 1, 'int', '30', 7, 365);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(57, 'nagios_url', 'Адрес сайта nagios', 1, 'text', 'http://127.0.0.1/nagios', 0, 0);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(58, 'cacti_url', 'Адрес сайта cacti', 1, 'text', 'http://127.0.0.1/cacti', 0, 0);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(59, 'torrus_url', 'Адрес сайта Torrus', 1, 'text', 'http://127.0.0.1/torrus/CollectorName/', 0, 0);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(60, 'wiki_url', 'Адрес wiki', 1, 'text', 'http://127.0.0.1/wiki', 0, 0);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(61, 'wiki_path', 'Путь к каталогу данных вики', 1, 'text', '/var/www/foswiki/data/', 0, 0);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(62, 'stat_url', 'Адрес этого сайта', 1, 'text', 'http://127.0.0.1/stat', 0, 0);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(63, 'wiki_web', 'Web for Wiki. Default - Main. http://example.local/Main/WebHome', 1, 'text', 'Main', 0, 0);
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES(64, 'auto_mac_rule', 'Создавать автоматическую привязку мак-адреса к юзеру. Т.е. все ip-адреса для найденного мака будут привязываться к одном и тому же юзеру.', 1, 'bool', '0', 0, 1);

-- --------------------------------------------------------

--
-- Структура таблицы `connections`
--

CREATE TABLE `connections` (
  `id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `port_id` int(11) NOT NULL,
  `auth_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `Customers`
--

CREATE TABLE `Customers` (
  `id` int(11) NOT NULL,
  `Login` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'NULL',
  `Pwd` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT 'NULL',
  `readonly` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `Customers`
--

INSERT INTO `Customers` (`id`, `Login`, `Pwd`, `readonly`) VALUES(1, 'admin', '21232f297a57a5a743894a0e4a801fc3', 0);

-- --------------------------------------------------------

--
-- Структура таблицы `devices`
--

CREATE TABLE `devices` (
  `id` int(11) NOT NULL,
  `device_type` int(11) NOT NULL DEFAULT 1,
  `device_model_id` int(11) DEFAULT 89,
  `firmware` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vendor_id` int(11) NOT NULL DEFAULT 1,
  `device_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `building_id` int(11) NOT NULL DEFAULT 1,
  `ip` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `port_count` int(11) NOT NULL DEFAULT 0,
  `SN` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `snmp_version` tinyint(4) NOT NULL DEFAULT 0,
  `snmp3_user_rw` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `snmp3_user_rw_password` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `snmp3_user_ro` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `snmp3_user_ro_password` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `community` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public',
  `rw_community` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'private',
  `fdb_snmp_index` tinyint(1) NOT NULL DEFAULT 0,
  `discovery` tinyint(1) NOT NULL DEFAULT 1,
  `user_acl` tinyint(1) NOT NULL DEFAULT 0,
  `dhcp` tinyint(1) NOT NULL DEFAULT 0,
  `nagios` tinyint(1) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `nagios_status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UP',
  `queue_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `connected_user_only` tinyint(1) NOT NULL DEFAULT 1,
  `user_id` int(11) DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `device_l3_interfaces`
--

CREATE TABLE `device_l3_interfaces` (
  `id` int(11) NOT NULL,
  `device_id` int(11) DEFAULT NULL,
  `interface_type` int(11) NOT NULL DEFAULT 0,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `device_models`
--

CREATE TABLE `device_models` (
  `id` int(11) NOT NULL,
  `model_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vendor_id` int(11) DEFAULT 1,
  `nagios_template` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `device_models`
--

INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(1, '2011LS', 9, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(2, '2011UAS-2HnD', 9, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(3, 'AT-8000S', 8, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(4, 'AT-8100S/48POE', 8, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(5, 'AT-9000/28', 8, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(6, 'AT-GS950/24', 8, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(7, 'CCR1009-7G-1C-1S+', 9, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(8, 'CCR1036-8G-2S+', 9, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(10, 'CRS317-1G-16S+', 9, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(11, 'CRS326-24S+2Q+', 9, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(12, 'CRS328-24P-4S+', 9, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(14, 'CRS328-4C-20S-4S+', 9, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(15, 'DGS-3120-48TC', 7, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(16, 'ES-2024', 4, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(17, 'ES-2024A', 4, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(18, 'ES-2108', 4, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(19, 'ES-2108-G', 4, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(20, 'ES-3124-4F', 4, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(21, 'GS110TP', 10, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(22, 'GS-4024', 4, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(23, 'HP 1910', 15, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(24, 'ISCOM2110A-MA', 5, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(25, 'ISCOM2110EA-MA', 5, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(26, 'ISCOM2126EA-MA', 5, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(27, 'ISCOM2128EA-MA', 5, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(28, 'Linux server', 1, '');
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(29, 'MES2124F', 2, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(30, 'MES2124MB', 2, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(31, 'MES5248', 2, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(32, 'RB2011UAS', 9, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(33, 'RB3011UiAS', 9, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(34, 'RB960PGS', 9, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(35, 'RBD52G-5HacD2HnD', 9, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(36, 'S2940-8G-v2', 6, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(37, 'S2980G-24T', 6, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(38, 'S3750G-24S-E', 6, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(39, 'S5300-52P-LI-AC', 3, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(40, 'S5320-52X-PWR-SI-AC', 3, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(41, 'S5321-28X-SI-AC', 3, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(42, 'S5321-52X-SI-AC', 3, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(43, 'S6320-54C-EI-48S-AC', 3, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(44, 'SNR-S2980G-24T', 6, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(45, 'V1910-16G', 15, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(46, 'V1910-24G-PoE', 15, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(47, 'Windows server', 1, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(48, 'WS-C2960G-24TC-L', 16, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(49, 'WS-C3560G-24TS-S', 16, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(50, 'x210-16GT', 8, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(51, 'x210-24GT', 8, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(52, 'x610-24Ts/X', 8, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(53, 'x610-48Ts', 8, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(54, 'XGS-4728', 4, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(55, 'ZyWall 310', 4, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(56, 'APC Smart-UPS_3000', 20, 'ups.cfg');
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(57, 'APC Smart-UPS_5000', 20, 'ups.cfg');
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(58, 'Schneider Smart-UPS_3000', 27, 'ups.cfg');
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(59, 'SMG-1016M', 2, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(60, 'EATON 9PX 1500i RT 2U', 24, 'ups.cfg');
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(61, 'EATON 9PX3000i_RT_2U', 24, 'ups.cfg');
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(62, 'EATON 9PX_6000i', 24, 'ups.cfg');
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(63, 'EATON PW9130_3000', 24, 'ups.cfg');
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(64, 'EATON PW9130_3000VA-R', 24, 'ups.cfg');
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(65, 'Epson WF-5620 Series', 26, 'epson.cfg');
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(66, 'Epson WF-8590 Series', 26, 'epson.cfg');
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(67, 'HP Officejet-7000', 15, 'hp.cfg');
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(68, 'OKI C610', 22, 'oki.cfg');
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(69, 'OKI MB472', 22, 'oki.cfg');
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(70, 'OKI MB491', 22, 'oki.cfg');
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(71, 'OKI MC562', 22, 'oki.cfg');
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(72, 'OKI MC573', 22, 'oki.cfg');
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(73, 'OKI MC861', 22, 'oki.cfg');
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(74, 'Panasonic KX-MB2000RU', 21, 'panasonic.cfg');
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(75, 'PT-MZ10KE', 21, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(76, 'PT-VX41', 21, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(77, 'Rave 522AA', 33, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(78, 'DZ570E', 21, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(79, 'DZ6700', 21, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(80, 'Rcq80', 21, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(81, 'RZ12K', 21, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(82, 'RZ660', 21, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(83, 'RZ770', 21, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(84, 'RZ970', 21, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(85, 'XVR-5216', 32, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(86, 'HWg-STE', 30, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(87, 'Computer', 1, '');
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(88, 'Mobile Phone', 1, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(89, 'Switch', 1, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(90, 'Projectiondesign F22', 34, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(91, 'DS-I252', 36, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(92, 'LTV-CNE-720-48', 37, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(93, 'U-100', 38, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(94, 'TAU-8', 2, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(95, 'SIP-T21P E2', 39, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(96, 'A510 IP', 40, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(97, 'W60B', 39, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(98, 'TAU-2M', 2, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(99, 'PAP2T', 41, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(100, 'VP-12', 2, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(101, 'SIP-T23P', 39, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(102, 'SPA-2102', 16, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(103, 'RB760iGS', 9, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(104, 'MES2324B', 2, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(105, 'MES2324FB', 2, NULL);
INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES(106, 'MES2124P', 2, NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `device_ports`
--

CREATE TABLE `device_ports` (
  `id` int(11) NOT NULL,
  `device_id` int(11) DEFAULT NULL,
  `snmp_index` int(11) DEFAULT NULL,
  `port` int(11) DEFAULT NULL,
  `ifName` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `port_name` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target_port_id` int(11) NOT NULL DEFAULT 0,
  `auth_id` int(11) DEFAULT NULL,
  `last_mac_count` int(11) DEFAULT 0,
  `uplink` tinyint(1) NOT NULL DEFAULT 0,
  `nagios` tinyint(1) NOT NULL DEFAULT 0,
  `skip` tinyint(1) NOT NULL DEFAULT 0,
  `vlan` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `device_types`
--

CREATE TABLE `device_types` (
  `id` int(11) NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `device_types`
--

INSERT INTO `device_types` (`id`, `name`) VALUES(1, 'Свич');
INSERT INTO `device_types` (`id`, `name`) VALUES(2, 'Роутер');
INSERT INTO `device_types` (`id`, `name`) VALUES(3, 'Сервер');
INSERT INTO `device_types` (`id`, `name`) VALUES(4, 'Точка доступа');
INSERT INTO `device_types` (`id`, `name`) VALUES(5, 'Сетевое устройство');

-- --------------------------------------------------------

--
-- Структура таблицы `dhcp_log`
--

CREATE TABLE `dhcp_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `mac` varchar(17) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_int` int(10) UNSIGNED NOT NULL,
  `ip` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `auth_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `dns_cache`
--

CREATE TABLE `dns_cache` (
  `id` int(11) NOT NULL,
  `dns` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` int(11) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `Filter_list`
--

CREATE TABLE `Filter_list` (
  `id` int(11) NOT NULL,
  `name` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `proto` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dst` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dstport` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` int(11) NOT NULL DEFAULT 0,
  `type` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `Filter_list` (`id`, `name`, `proto`, `dst`, `dstport`, `action`, `type`) VALUES(1, 'pop3', 'tcp', '0/0', '110', 1, 0);
INSERT INTO `Filter_list` (`id`, `name`, `proto`, `dst`, `dstport`, `action`, `type`) VALUES(3, 'http', 'tcp', '0/0', '80', 1, 0);
INSERT INTO `Filter_list` (`id`, `name`, `proto`, `dst`, `dstport`, `action`, `type`) VALUES(4, 'https', 'tcp', '0/0', '443', 1, 0);
INSERT INTO `Filter_list` (`id`, `name`, `proto`, `dst`, `dstport`, `action`, `type`) VALUES(5, 'icq', 'tcp', '0/0', '5190', 1, 0);
INSERT INTO `Filter_list` (`id`, `name`, `proto`, `dst`, `dstport`, `action`, `type`) VALUES(6, 'jabber', 'tcp', '0/0', '5222', 1, 0);
INSERT INTO `Filter_list` (`id`, `name`, `proto`, `dst`, `dstport`, `action`, `type`) VALUES(9, 'allow_all', 'all', '0/0', '0', 1, 0);
INSERT INTO `Filter_list` (`id`, `name`, `proto`, `dst`, `dstport`, `action`, `type`) VALUES(10, 'icmp', 'icmp', '0/0', '0', 1, 0);
INSERT INTO `Filter_list` (`id`, `name`, `proto`, `dst`, `dstport`, `action`, `type`) VALUES(11, 'ftp', 'tcp', '0/0', '20-21', 1, 0);
INSERT INTO `Filter_list` (`id`, `name`, `proto`, `dst`, `dstport`, `action`, `type`) VALUES(14, 'radmin', 'tcp', '0/0', '4899', 1, 0);
INSERT INTO `Filter_list` (`id`, `name`, `proto`, `dst`, `dstport`, `action`, `type`) VALUES(15, 'telnet', 'tcp', '0/0', '23', 1, 0);
INSERT INTO `Filter_list` (`id`, `name`, `proto`, `dst`, `dstport`, `action`, `type`) VALUES(16, 'ssh', 'tcp', '0/0', '22', 1, 0);
INSERT INTO `Filter_list` (`id`, `name`, `proto`, `dst`, `dstport`, `action`, `type`) VALUES(28, 'smtp', 'tcp', '0/0', '25', 1, 0);
INSERT INTO `Filter_list` (`id`, `name`, `proto`, `dst`, `dstport`, `action`, `type`) VALUES(32, 'tsclient', 'tcp', '0/0', '3389', 1, 0);
INSERT INTO `Filter_list` (`id`, `name`, `proto`, `dst`, `dstport`, `action`, `type`) VALUES(34, 'sberbank', 'udp', '0/0', '87', 1, 0);
INSERT INTO `Filter_list` (`id`, `name`, `proto`, `dst`, `dstport`, `action`, `type`) VALUES(40, 'ntp', 'udp', '0/0', '123', 1, 0);
INSERT INTO `Filter_list` (`id`, `name`, `proto`, `dst`, `dstport`, `action`, `type`) VALUES(44, 'vnc', 'tcp', '0/0', '5800-5900', 1, 0);
INSERT INTO `Filter_list` (`id`, `name`, `proto`, `dst`, `dstport`, `action`, `type`) VALUES(55, 'unprivileged tcp', 'tcp', '0/0', '1024-65500', 1, 0);
INSERT INTO `Filter_list` (`id`, `name`, `proto`, `dst`, `dstport`, `action`, `type`) VALUES(76, 'ipsec', 'udp', '0/0', '500', 1, 0);
INSERT INTO `Filter_list` (`id`, `name`, `proto`, `dst`, `dstport`, `action`, `type`) VALUES(77, 'isakmp', 'udp', '0/0', '4500', 1, 0);
INSERT INTO `Filter_list` (`id`, `name`, `proto`, `dst`, `dstport`, `action`, `type`) VALUES(79, 'pop3s', 'tcp', '0/0', '995', 1, 0);
INSERT INTO `Filter_list` (`id`, `name`, `proto`, `dst`, `dstport`, `action`, `type`) VALUES(80, 'smtps', 'tcp', '0/0', '465,587', 1, 0);
INSERT INTO `Filter_list` (`id`, `name`, `proto`, `dst`, `dstport`, `action`, `type`) VALUES(81, 'imap', 'tcp', '0/0', '143', 1, 0);
INSERT INTO `Filter_list` (`id`, `name`, `proto`, `dst`, `dstport`, `action`, `type`) VALUES(82, 'imaps', 'tcp', '0/0', '993', 1, 0);
INSERT INTO `Filter_list` (`id`, `name`, `proto`, `dst`, `dstport`, `action`, `type`) VALUES(83, 'unprivileged udp', 'udp', '0/0', '1024-65000', 1, 0);
INSERT INTO `Filter_list` (`id`, `name`, `proto`, `dst`, `dstport`, `action`, `type`) VALUES(84, 'pptp', 'tcp', '0/0', '1723', 1, 0);
INSERT INTO `Filter_list` (`id`, `name`, `proto`, `dst`, `dstport`, `action`, `type`) VALUES(85, 'openvpn-udp', 'udp', '0/0', '1194', 1, 0);
INSERT INTO `Filter_list` (`id`, `name`, `proto`, `dst`, `dstport`, `action`, `type`) VALUES(88, 'pos-server', 'tcp', '0/0', '21101', 1, 0);
INSERT INTO `Filter_list` (`id`, `name`, `proto`, `dst`, `dstport`, `action`, `type`) VALUES(89, 'ofdp.platformaofd.ru', 'all', '185.170.204.91', '0', 1, 0);
INSERT INTO `Filter_list` (`id`, `name`, `proto`, `dst`, `dstport`, `action`, `type`) VALUES(90, 'dns_udp', 'udp', '0/0', '53', 1, 0);
INSERT INTO `Filter_list` (`id`, `name`, `proto`, `dst`, `dstport`, `action`, `type`) VALUES(91, 'dns_tcp', 'tcp', '0/0', '53', 1, 0);
INSERT INTO `Filter_list` (`id`, `name`, `proto`, `dst`, `dstport`, `action`, `type`) VALUES(92, 'sber-online', 'tcp', '0/0', '4477', 1, 0);
INSERT INTO `Filter_list` (`id`, `name`, `proto`, `dst`, `dstport`, `action`, `type`) VALUES(93, 'devline.tv', 'tcp', '0/0', '843', 1, 0);
INSERT INTO `Filter_list` (`id`, `name`, `proto`, `dst`, `dstport`, `action`, `type`) VALUES(96, 'cvs', 'tcp', '0/0', '5000-5012', 1, 0);
INSERT INTO `Filter_list` (`id`, `name`, `proto`, `dst`, `dstport`, `action`, `type`) VALUES(97, 'devline', 'tcp', '0/0', '9780-9786,9877', 1, 0);
INSERT INTO `Filter_list` (`id`, `name`, `proto`, `dst`, `dstport`, `action`, `type`) VALUES(101, 'snmp', 'udp', '0/0', '161', 1, 0);
INSERT INTO `Filter_list` (`id`, `name`, `proto`, `dst`, `dstport`, `action`, `type`) VALUES(104, 'sberbank2', 'tcp', '0/0', '9443', 1, 0);

-- --------------------------------------------------------

--
-- Структура таблицы `Group_filters`
--

CREATE TABLE `Group_filters` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL DEFAULT 0,
  `filter_id` int(11) NOT NULL DEFAULT 0,
  `order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `Group_filters`
--

INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(1, 1, 9, 0);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(2, 2, 1, 1);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(5, 2, 4, 9);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(6, 2, 5, 8);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(7, 2, 6, 7);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(10, 2, 10, 6);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(13, 2, 11, 5);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(147, 2, 67, 0);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(148, 2, 68, 0);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(149, 2, 69, 0);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(152, 1, 67, 0);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(153, 1, 68, 0);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(154, 1, 69, 0);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(166, 2, 3, 10);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(172, 0, 78, 1);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(173, 2, 81, 11);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(174, 2, 82, 12);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(175, 2, 80, 13);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(176, 2, 79, 14);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(179, 3, 11, 1);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(180, 3, 4, 2);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(181, 3, 10, 3);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(182, 3, 5, 4);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(183, 3, 81, 5);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(184, 3, 82, 6);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(185, 3, 6, 7);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(186, 3, 40, 8);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(187, 3, 1, 9);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(188, 3, 79, 10);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(190, 3, 80, 12);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(191, 3, 15, 13);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(192, 3, 55, 14);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(193, 3, 83, 15);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(194, 3, 3, 16);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(195, 3, 16, 17);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(198, 5, 90, 1);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(199, 5, 88, 2);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(200, 5, 89, 3);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(201, 5, 3, 4);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(202, 2, 90, 15);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(205, 3, 90, 19);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(206, 2, 92, 16);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(208, 2, 40, 18);
INSERT INTO `Group_filters` (`id`, `group_id`, `filter_id`, `order`) VALUES(213, 5, 4, 5);

-- --------------------------------------------------------

--
-- Структура таблицы `Group_list`
--

CREATE TABLE `Group_list` (
  `id` int(11) NOT NULL,
  `group_name` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `Group_list`
--

INSERT INTO `Group_list` (`id`, `group_name`) VALUES(0, 'default');
INSERT INTO `Group_list` (`id`, `group_name`) VALUES(1, 'Allow all');
INSERT INTO `Group_list` (`id`, `group_name`) VALUES(2, 'Users');

-- --------------------------------------------------------

--
-- Структура таблицы `mac_history`
--

CREATE TABLE `mac_history` (
  `id` int(10) UNSIGNED NOT NULL,
  `mac` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `device_id` int(11) DEFAULT NULL,
  `port_id` int(11) DEFAULT NULL,
  `ip` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `auth_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `mac_vendors`
--

CREATE TABLE `mac_vendors` (
  `id` int(11) NOT NULL,
  `oui` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `companyName` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `companyAddress` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Структура таблицы `OU`
--

CREATE TABLE `OU` (
  `id` int(11) NOT NULL,
  `ou_name` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_users` tinyint(1) NOT NULL DEFAULT 0,
  `default_hotspot` tinyint(1) NOT NULL DEFAULT 0,
  `nagios_dir` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nagios_host_use` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nagios_ping` tinyint(1) NOT NULL DEFAULT 1,
  `nagios_default_service` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `enabled` int(11) NOT NULL DEFAULT 0,
  `filter_group_id` int(11) NOT NULL DEFAULT 0,
  `queue_id` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `OU`
--

INSERT INTO `OU` (`id`, `ou_name`, `default_users`, `default_hotspot`, `nagios_dir`, `nagios_host_use`, `nagios_ping`, `nagios_default_service`, `enabled`, `filter_group_id`, `queue_id`) VALUES(0, '!Всё', 0, 0, '/etc/nagios/any', 'generic-host', 1, NULL, 0, 0, 0);
INSERT INTO `OU` (`id`, `ou_name`, `default_users`, `default_hotspot`, `nagios_dir`, `nagios_host_use`, `nagios_ping`, `nagios_default_service`, `enabled`, `filter_group_id`, `queue_id`) VALUES(1, 'Сервера', 0, 0, NULL, NULL, 1, NULL, 1, 1, 0);
INSERT INTO `OU` (`id`, `ou_name`, `default_users`, `default_hotspot`, `nagios_dir`, `nagios_host_use`, `nagios_ping`, `nagios_default_service`, `enabled`, `filter_group_id`, `queue_id`) VALUES(2, 'Администраторы', 0, 0, NULL, NULL, 1, NULL, 0, 0, 0);
INSERT INTO `OU` (`id`, `ou_name`, `default_users`, `default_hotspot`, `nagios_dir`, `nagios_host_use`, `nagios_ping`, `nagios_default_service`, `enabled`, `filter_group_id`, `queue_id`) VALUES(3, 'Пользователи', 0, 0, NULL, NULL, 1, NULL, 0, 0, 0);
INSERT INTO `OU` (`id`, `ou_name`, `default_users`, `default_hotspot`, `nagios_dir`, `nagios_host_use`, `nagios_ping`, `nagios_default_service`, `enabled`, `filter_group_id`, `queue_id`) VALUES(4, 'VOIP', 0, 0, 'voip', 'voip', 1, NULL, 0, 0, 0);
INSERT INTO `OU` (`id`, `ou_name`, `default_users`, `default_hotspot`, `nagios_dir`, `nagios_host_use`, `nagios_ping`, `nagios_default_service`, `enabled`, `filter_group_id`, `queue_id`) VALUES(5, 'IPCAM', 0, 0, 'videocam', 'ip-cam', 0, NULL, 0, 0, 0);
INSERT INTO `OU` (`id`, `ou_name`, `default_users`, `default_hotspot`, `nagios_dir`, `nagios_host_use`, `nagios_ping`, `nagios_default_service`, `enabled`, `filter_group_id`, `queue_id`) VALUES(6, 'Принтеры', 0, 0, 'printers', 'printers', 1, 'printer-service', 0, 0, 0);
INSERT INTO `OU` (`id`, `ou_name`, `default_users`, `default_hotspot`, `nagios_dir`, `nagios_host_use`, `nagios_ping`, `nagios_default_service`, `enabled`, `filter_group_id`, `queue_id`) VALUES(7, 'Свичи', 0, 0, 'switches', 'switches', 1, NULL, 0, 0, 0);
INSERT INTO `OU` (`id`, `ou_name`, `default_users`, `default_hotspot`, `nagios_dir`, `nagios_host_use`, `nagios_ping`, `nagios_default_service`, `enabled`, `filter_group_id`, `queue_id`) VALUES(8, 'UPS', 0, 0, 'ups', 'ups', 1, NULL, 0, 0, 0);
INSERT INTO `OU` (`id`, `ou_name`, `default_users`, `default_hotspot`, `nagios_dir`, `nagios_host_use`, `nagios_ping`, `nagios_default_service`, `enabled`, `filter_group_id`, `queue_id`) VALUES(9, 'Охрана', 0, 0, 'security', 'security', 1, NULL, 0, 0, 0);
INSERT INTO `OU` (`id`, `ou_name`, `default_users`, `default_hotspot`, `nagios_dir`, `nagios_host_use`, `nagios_ping`, `nagios_default_service`, `enabled`, `filter_group_id`, `queue_id`) VALUES(10, 'Роутеры', 0, 0, 'routers', 'routers', 1, NULL, 0, 0, 0);
INSERT INTO `OU` (`id`, `ou_name`, `default_users`, `default_hotspot`, `nagios_dir`, `nagios_host_use`, `nagios_ping`, `nagios_default_service`, `enabled`, `filter_group_id`, `queue_id`) VALUES(12, 'WiFi AP', 0, 0, 'ap', 'ap', 1, NULL, 0, 0, 0);
INSERT INTO `OU` (`id`, `ou_name`, `default_users`, `default_hotspot`, `nagios_dir`, `nagios_host_use`, `nagios_ping`, `nagios_default_service`, `enabled`, `filter_group_id`, `queue_id`) VALUES(23, 'WiFi', 0, 0, NULL, NULL, 1, NULL, 1, 1, 4);
INSERT INTO `OU` (`id`, `ou_name`, `default_users`, `default_hotspot`, `nagios_dir`, `nagios_host_use`, `nagios_ping`, `nagios_default_service`, `enabled`, `filter_group_id`, `queue_id`) VALUES(24, 'VPN', 0, 0, NULL, NULL, 1, NULL, 0, 0, 0);
INSERT INTO `OU` (`id`, `ou_name`, `default_users`, `default_hotspot`, `nagios_dir`, `nagios_host_use`, `nagios_ping`, `nagios_default_service`, `enabled`, `filter_group_id`, `queue_id`) VALUES(27, 'DHCP', 1, 0, NULL, NULL, 1, NULL, 0, 0, 0);
INSERT INTO `OU` (`id`, `ou_name`, `default_users`, `default_hotspot`, `nagios_dir`, `nagios_host_use`, `nagios_ping`, `nagios_default_service`, `enabled`, `filter_group_id`, `queue_id`) VALUES(28, 'Гости', 0, 0, NULL, NULL, 1, NULL, 0, 0, 0);

-- --------------------------------------------------------

--
-- Структура таблицы `Queue_list`
--

CREATE TABLE `Queue_list` (
  `id` int(11) NOT NULL,
  `queue_name` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Download` int(11) NOT NULL DEFAULT 0,
  `Upload` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `Queue_list`
--

INSERT INTO `Queue_list` (`id`, `queue_name`, `Download`, `Upload`) VALUES(0, 'unlimited', 0, 0);
INSERT INTO `Queue_list` (`id`, `queue_name`, `Download`, `Upload`) VALUES(1, '2M/2M', 2048, 2048);
INSERT INTO `Queue_list` (`id`, `queue_name`, `Download`, `Upload`) VALUES(2, '10M/10M', 10240, 10240);
INSERT INTO `Queue_list` (`id`, `queue_name`, `Download`, `Upload`) VALUES(3, '100M/100M', 102400, 102400);
INSERT INTO `Queue_list` (`id`, `queue_name`, `Download`, `Upload`) VALUES(4, '50M/50M', 50000, 50000);
INSERT INTO `Queue_list` (`id`, `queue_name`, `Download`, `Upload`) VALUES(5, '20M/20M', 20480, 20480);
INSERT INTO `Queue_list` (`id`, `queue_name`, `Download`, `Upload`) VALUES(6, '200M/200M', 212400, 212400);
INSERT INTO `Queue_list` (`id`, `queue_name`, `Download`, `Upload`) VALUES(7, '1G/1G', 1024000, 1024000);

-- --------------------------------------------------------

--
-- Структура таблицы `remote_syslog`
--

CREATE TABLE `remote_syslog` (
  `id` int(11) NOT NULL,
  `date` timestamp NOT NULL DEFAULT current_timestamp(),
  `device_id` int(11) NOT NULL,
  `ip` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `subnets`
--

CREATE TABLE `subnets` (
  `id` int(11) NOT NULL,
  `subnet` varchar(18) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_int_start` bigint(20) NOT NULL,
  `ip_int_stop` bigint(20) NOT NULL,
  `dhcp_start` bigint(20) NOT NULL DEFAULT 0,
  `dhcp_stop` bigint(20) NOT NULL DEFAULT 0,
  `dhcp_lease_time` int(11) NOT NULL DEFAULT 480,
  `gateway` bigint(20) NOT NULL DEFAULT 0,
  `office` tinyint(1) NOT NULL DEFAULT 1,
  `hotspot` tinyint(1) NOT NULL DEFAULT 0,
  `vpn` tinyint(1) NOT NULL DEFAULT 0,
  `free` tinyint(1) NOT NULL DEFAULT 0,
  `dhcp` tinyint(1) NOT NULL DEFAULT 1,
  `static` tinyint(1) NOT NULL DEFAULT 0,
  `dhcp_update_hostname` tinyint(1) NOT NULL DEFAULT 0,
  `discovery` tinyint(1) NOT NULL DEFAULT 1,
  `comment` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `subnets`
--

INSERT INTO `subnets` (`id`, `subnet`, `ip_int_start`, `ip_int_stop`, `dhcp_start`, `dhcp_stop`, `dhcp_lease_time`, `gateway`, `office`, `hotspot`, `vpn`, `free`, `dhcp`, `static`, `dhcp_update_hostname`, `discovery`, `comment`) VALUES(1, '192.168.2.0/24', 3232236032, 3232236287, 3232236132, 3232236182, 480, 3232236033, 1, 0, 0, 0, 1, 0, 0, 1, 'Сервера');
INSERT INTO `subnets` (`id`, `subnet`, `ip_int_start`, `ip_int_stop`, `dhcp_start`, `dhcp_stop`, `dhcp_lease_time`, `gateway`, `office`, `hotspot`, `vpn`, `free`, `dhcp`, `static`, `dhcp_update_hostname`, `discovery`, `comment`) VALUES(2, '192.168.3.0/24', 3232236288, 3232236543, 3232236388, 3232236438, 480, 3232236289, 1, 0, 0, 0, 1, 0, 0, 1, 'Сотрудники, Можаевская');
INSERT INTO `subnets` (`id`, `subnet`, `ip_int_start`, `ip_int_stop`, `dhcp_start`, `dhcp_stop`, `dhcp_lease_time`, `gateway`, `office`, `hotspot`, `vpn`, `free`, `dhcp`, `static`, `dhcp_update_hostname`, `discovery`, `comment`) VALUES(3, '192.168.5.0/24', 3232236800, 3232237055, 3232236900, 3232237000, 480, 3232236801, 1, 0, 0, 0, 1, 0, 0, 1, 'Сотрудники, Дзержинского');
INSERT INTO `subnets` (`id`, `subnet`, `ip_int_start`, `ip_int_stop`, `dhcp_start`, `dhcp_stop`, `dhcp_lease_time`, `gateway`, `office`, `hotspot`, `vpn`, `free`, `dhcp`, `static`, `dhcp_update_hostname`, `discovery`, `comment`) VALUES(4, '192.168.8.0/24', 3232237568, 3232237823, 3232237668, 3232237718, 480, 3232237569, 1, 0, 0, 0, 1, 0, 0, 1, 'Удалёнщики - прямой линк');
INSERT INTO `subnets` (`id`, `subnet`, `ip_int_start`, `ip_int_stop`, `dhcp_start`, `dhcp_stop`, `dhcp_lease_time`, `gateway`, `office`, `hotspot`, `vpn`, `free`, `dhcp`, `static`, `dhcp_update_hostname`, `discovery`, `comment`) VALUES(5, '192.168.9.0/24', 3232237824, 3232238079, 3232237924, 3232238024, 480, 3232237825, 1, 0, 0, 0, 1, 0, 0, 1, 'Литовская, сотрудники');
INSERT INTO `subnets` (`id`, `subnet`, `ip_int_start`, `ip_int_stop`, `dhcp_start`, `dhcp_stop`, `dhcp_lease_time`, `gateway`, `office`, `hotspot`, `vpn`, `free`, `dhcp`, `static`, `dhcp_update_hostname`, `discovery`, `comment`) VALUES(6, '192.168.13.0/24', 3232238848, 3232239103, 3232238911, 3232239039, 480, 3232238849, 1, 0, 0, 0, 1, 0, 0, 1, 'Облако');
INSERT INTO `subnets` (`id`, `subnet`, `ip_int_start`, `ip_int_stop`, `dhcp_start`, `dhcp_stop`, `dhcp_lease_time`, `gateway`, `office`, `hotspot`, `vpn`, `free`, `dhcp`, `static`, `dhcp_update_hostname`, `discovery`, `comment`) VALUES(7, '192.168.12.0/24', 3232238592, 3232238847, 3232238602, 3232238846, 480, 3232238593, 1, 0, 0, 0, 1, 0, 0, 1, 'Управление, Дзержинского');
INSERT INTO `subnets` (`id`, `subnet`, `ip_int_start`, `ip_int_stop`, `dhcp_start`, `dhcp_stop`, `dhcp_lease_time`, `gateway`, `office`, `hotspot`, `vpn`, `free`, `dhcp`, `static`, `dhcp_update_hostname`, `discovery`, `comment`) VALUES(8, '192.168.22.0/24', 3232241152, 3232241407, 3232241162, 3232241406, 480, 3232241153, 1, 0, 0, 0, 1, 0, 0, 1, 'Управление, Литовская');
INSERT INTO `subnets` (`id`, `subnet`, `ip_int_start`, `ip_int_stop`, `dhcp_start`, `dhcp_stop`, `dhcp_lease_time`, `gateway`, `office`, `hotspot`, `vpn`, `free`, `dhcp`, `static`, `dhcp_update_hostname`, `discovery`, `comment`) VALUES(9, '192.168.32.0/24', 3232243712, 3232243967, 3232243722, 3232243966, 480, 3232243713, 1, 0, 0, 0, 1, 0, 0, 1, 'Управление, Можаевская');
INSERT INTO `subnets` (`id`, `subnet`, `ip_int_start`, `ip_int_stop`, `dhcp_start`, `dhcp_stop`, `dhcp_lease_time`, `gateway`, `office`, `hotspot`, `vpn`, `free`, `dhcp`, `static`, `dhcp_update_hostname`, `discovery`, `comment`) VALUES(10, '10.160.0.0/24', 178257920, 178258175, 178258020, 178258070, 1440, 178257921, 1, 0, 0, 0, 1, 0, 0, 1, 'Жуково');
INSERT INTO `subnets` (`id`, `subnet`, `ip_int_start`, `ip_int_stop`, `dhcp_start`, `dhcp_stop`, `dhcp_lease_time`, `gateway`, `office`, `hotspot`, `vpn`, `free`, `dhcp`, `static`, `dhcp_update_hostname`, `discovery`, `comment`) VALUES(11, '192.168.0.0/16', 3232235520, 3232301055, 0, 0, 480, 0, 0, 0, 0, 1, 0, 0, 0, 0, 'Не считать трафик');
INSERT INTO `subnets` (`id`, `subnet`, `ip_int_start`, `ip_int_stop`, `dhcp_start`, `dhcp_stop`, `dhcp_lease_time`, `gateway`, `office`, `hotspot`, `vpn`, `free`, `dhcp`, `static`, `dhcp_update_hostname`, `discovery`, `comment`) VALUES(12, '10.0.0.0/8', 167772160, 184549375, 0, 0, 480, 0, 0, 0, 0, 0, 0, 0, 0, 0, 'Не считать трафик');
INSERT INTO `subnets` (`id`, `subnet`, `ip_int_start`, `ip_int_stop`, `dhcp_start`, `dhcp_stop`, `dhcp_lease_time`, `gateway`, `office`, `hotspot`, `vpn`, `free`, `dhcp`, `static`, `dhcp_update_hostname`, `discovery`, `comment`) VALUES(13, '172.16.0.0/12', 2886729728, 2887778303, 0, 0, 480, 0, 0, 0, 0, 0, 0, 0, 0, 0, 'Не считать трафик');
INSERT INTO `subnets` (`id`, `subnet`, `ip_int_start`, `ip_int_stop`, `dhcp_start`, `dhcp_stop`, `dhcp_lease_time`, `gateway`, `office`, `hotspot`, `vpn`, `free`, `dhcp`, `static`, `dhcp_update_hostname`, `discovery`, `comment`) VALUES(14, '192.168.40.0/24', 3232245760, 3232246015, 3232245762, 3232245960, 480, 3232245761, 1, 0, 0, 0, 1, 0, 0, 1, 'WiFI, Можаевская');
INSERT INTO `subnets` (`id`, `subnet`, `ip_int_start`, `ip_int_stop`, `dhcp_start`, `dhcp_stop`, `dhcp_lease_time`, `gateway`, `office`, `hotspot`, `vpn`, `free`, `dhcp`, `static`, `dhcp_update_hostname`, `discovery`, `comment`) VALUES(15, '192.168.200.0/24', 3232286720, 3232286975, 3232286783, 3232286911, 480, 3232286721, 1, 0, 0, 0, 0, 0, 0, 1, 'Транспорт - оптика');
INSERT INTO `subnets` (`id`, `subnet`, `ip_int_start`, `ip_int_stop`, `dhcp_start`, `dhcp_stop`, `dhcp_lease_time`, `gateway`, `office`, `hotspot`, `vpn`, `free`, `dhcp`, `static`, `dhcp_update_hostname`, `discovery`, `comment`) VALUES(16, '192.168.201.0/24', 3232286976, 3232287231, 3232287039, 3232287167, 480, 3232286977, 1, 0, 1, 0, 0, 0, 0, 0, 'Транспорт - туннели');
INSERT INTO `subnets` (`id`, `subnet`, `ip_int_start`, `ip_int_stop`, `dhcp_start`, `dhcp_stop`, `dhcp_lease_time`, `gateway`, `office`, `hotspot`, `vpn`, `free`, `dhcp`, `static`, `dhcp_update_hostname`, `discovery`, `comment`) VALUES(17, '10.111.4.0/24', 175047680, 175047935, 175047743, 175047871, 480, 175047681, 1, 0, 0, 0, 0, 0, 0, 1, 'Мультиплексоры');
INSERT INTO `subnets` (`id`, `subnet`, `ip_int_start`, `ip_int_stop`, `dhcp_start`, `dhcp_stop`, `dhcp_lease_time`, `gateway`, `office`, `hotspot`, `vpn`, `free`, `dhcp`, `static`, `dhcp_update_hostname`, `discovery`, `comment`) VALUES(18, '10.1.7.0/24', 167839488, 167839743, 167839551, 167839679, 480, 167839489, 1, 0, 1, 0, 0, 0, 0, 0, 'l2tp vpn');
INSERT INTO `subnets` (`id`, `subnet`, `ip_int_start`, `ip_int_stop`, `dhcp_start`, `dhcp_stop`, `dhcp_lease_time`, `gateway`, `office`, `hotspot`, `vpn`, `free`, `dhcp`, `static`, `dhcp_update_hostname`, `discovery`, `comment`) VALUES(19, '10.1.3.0/24', 167838464, 167838719, 167838527, 167838655, 480, 167838465, 1, 0, 1, 0, 0, 0, 0, 0, 'gre vpn');
INSERT INTO `subnets` (`id`, `subnet`, `ip_int_start`, `ip_int_stop`, `dhcp_start`, `dhcp_stop`, `dhcp_lease_time`, `gateway`, `office`, `hotspot`, `vpn`, `free`, `dhcp`, `static`, `dhcp_update_hostname`, `discovery`, `comment`) VALUES(20, '10.5.1.0/24', 168100096, 168100351, 168100159, 168100287, 480, 168100097, 1, 0, 1, 0, 0, 0, 0, 0, 'openvpn');
INSERT INTO `subnets` (`id`, `subnet`, `ip_int_start`, `ip_int_stop`, `dhcp_start`, `dhcp_stop`, `dhcp_lease_time`, `gateway`, `office`, `hotspot`, `vpn`, `free`, `dhcp`, `static`, `dhcp_update_hostname`, `discovery`, `comment`) VALUES(21, '10.5.2.0/24', 168100352, 168100607, 168100415, 168100543, 480, 168100353, 1, 0, 1, 0, 0, 0, 0, 0, 'new openvpn');
INSERT INTO `subnets` (`id`, `subnet`, `ip_int_start`, `ip_int_stop`, `dhcp_start`, `dhcp_stop`, `dhcp_lease_time`, `gateway`, `office`, `hotspot`, `vpn`, `free`, `dhcp`, `static`, `dhcp_update_hostname`, `discovery`, `comment`) VALUES(22, '192.168.42.0/24', 3232246272, 3232246527, 3232246472, 3232246526, 1440, 3232246273, 1, 0, 0, 0, 1, 0, 0, 1, 'Управление, Жуково');
INSERT INTO `subnets` (`id`, `subnet`, `ip_int_start`, `ip_int_stop`, `dhcp_start`, `dhcp_stop`, `dhcp_lease_time`, `gateway`, `office`, `hotspot`, `vpn`, `free`, `dhcp`, `static`, `dhcp_update_hostname`, `discovery`, `comment`) VALUES(23, '192.168.50.0/24', 3232248320, 3232248575, 3232248330, 3232248574, 480, 3232248321, 1, 0, 0, 0, 1, 0, 0, 1, 'Гудков, Дом');
INSERT INTO `subnets` (`id`, `subnet`, `ip_int_start`, `ip_int_stop`, `dhcp_start`, `dhcp_stop`, `dhcp_lease_time`, `gateway`, `office`, `hotspot`, `vpn`, `free`, `dhcp`, `static`, `dhcp_update_hostname`, `discovery`, `comment`) VALUES(24, '192.168.41.0/24', 3232246016, 3232246271, 3232246079, 3232246207, 480, 3232246017, 1, 0, 0, 0, 1, 0, 0, 1, 'WiFi, Дзержинского84');
INSERT INTO `subnets` (`id`, `subnet`, `ip_int_start`, `ip_int_stop`, `dhcp_start`, `dhcp_stop`, `dhcp_lease_time`, `gateway`, `office`, `hotspot`, `vpn`, `free`, `dhcp`, `static`, `dhcp_update_hostname`, `discovery`, `comment`) VALUES(25, '192.168.15.0/24', 3232239360, 3232239615, 3232239423, 3232239551, 480, 3232239361, 1, 0, 0, 0, 1, 0, 0, 1, 'Жуково - оборудование');
INSERT INTO `subnets` (`id`, `subnet`, `ip_int_start`, `ip_int_stop`, `dhcp_start`, `dhcp_stop`, `dhcp_lease_time`, `gateway`, `office`, `hotspot`, `vpn`, `free`, `dhcp`, `static`, `dhcp_update_hostname`, `discovery`, `comment`) VALUES(26, '192.168.45.0/24', 3232247040, 3232247295, 3232247103, 3232247231, 480, 3232247041, 1, 0, 0, 0, 1, 0, 0, 1, 'Ceph test cluster');
INSERT INTO `subnets` (`id`, `subnet`, `ip_int_start`, `ip_int_stop`, `dhcp_start`, `dhcp_stop`, `dhcp_lease_time`, `gateway`, `office`, `hotspot`, `vpn`, `free`, `dhcp`, `static`, `dhcp_update_hostname`, `discovery`, `comment`) VALUES(27, '192.168.205.0/24', 3232288000, 3232288255, 3232288002, 3232288254, 480, 3232288001, 1, 0, 0, 0, 1, 0, 0, 1, 'Гости');

-- --------------------------------------------------------

--
-- Структура таблицы `syslog`
--

CREATE TABLE `syslog` (
  `id` int(11) UNSIGNED NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `auth_id` int(11) NOT NULL DEFAULT 0,
  `customer` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'system',
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `level` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `Traffic_detail`
--

CREATE TABLE `Traffic_detail` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `auth_id` int(11) DEFAULT NULL,
  `router_id` int(11) NOT NULL DEFAULT 0,
  `timestamp` timestamp NULL DEFAULT NULL,
  `proto` int(11) DEFAULT NULL,
  `src_ip` int(10) UNSIGNED NOT NULL,
  `dst_ip` int(10) UNSIGNED NOT NULL,
  `src_port` smallint(5) UNSIGNED NOT NULL,
  `dst_port` smallint(5) UNSIGNED NOT NULL,
  `bytes` bigint(20) NOT NULL,
  `pkt` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `Unknown_mac`
--

CREATE TABLE `Unknown_mac` (
  `id` int(11) NOT NULL,
  `mac` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `port_id` int(11) DEFAULT NULL,
  `device_id` int(11) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `User_auth`
--

CREATE TABLE `User_auth` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `ou_id` int(11) DEFAULT NULL,
  `ip` varchar(18) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ip_int` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `save_traf` tinyint(1) NOT NULL DEFAULT 0,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `dhcp` tinyint(1) NOT NULL DEFAULT 1,
  `filter_group_id` tinyint(1) NOT NULL DEFAULT 0,
  `deleted` tinyint(4) NOT NULL DEFAULT 0,
  `comments` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dns_name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `WikiName` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dhcp_acl` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `queue_id` int(11) NOT NULL DEFAULT 0,
  `mac` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `dhcp_action` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `dhcp_time` datetime NOT NULL DEFAULT current_timestamp(),
  `dhcp_hostname` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_found` datetime NOT NULL DEFAULT current_timestamp(),
  `blocked` tinyint(1) NOT NULL DEFAULT 0,
  `day_quota` int(11) NOT NULL DEFAULT 0,
  `month_quota` int(11) NOT NULL DEFAULT 0,
  `device_model_id` int(11) DEFAULT 87,
  `firmware` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `clientid` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `nagios` tinyint(1) NOT NULL DEFAULT 0,
  `nagios_status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `nagios_handler` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `link_check` tinyint(1) NOT NULL DEFAULT 0,
  `changed` tinyint(1) NOT NULL DEFAULT 0,
  `changed_time` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `User_auth_alias`
--

CREATE TABLE `User_auth_alias` (
  `id` int(11) NOT NULL,
  `auth_id` int(11) NOT NULL,
  `alias` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `User_list`
--

CREATE TABLE `User_list` (
  `id` int(10) UNSIGNED NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `login` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `fio` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `blocked` tinyint(1) NOT NULL DEFAULT 0,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  `ou_id` int(11) NOT NULL DEFAULT 0,
  `device_id` int(11) DEFAULT NULL,
  `filter_group_id` int(11) NOT NULL DEFAULT 0,
  `queue_id` int(11) NOT NULL DEFAULT 0,
  `day_quota` int(11) NOT NULL DEFAULT 0,
  `month_quota` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `User_stats`
--

CREATE TABLE `User_stats` (
  `id` int(11) NOT NULL,
  `router_id` int(11) DEFAULT 0,
  `auth_id` int(11) NOT NULL DEFAULT 0,
  `timestamp` datetime NOT NULL DEFAULT current_timestamp(),
  `byte_in` bigint(20) NOT NULL DEFAULT 0,
  `byte_out` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `User_stats_full`
--

CREATE TABLE `User_stats_full` (
  `id` int(11) NOT NULL,
  `router_id` int(11) DEFAULT 0,
  `auth_id` int(11) NOT NULL DEFAULT 0,
  `timestamp` datetime NOT NULL DEFAULT current_timestamp(),
  `byte_in` bigint(20) NOT NULL DEFAULT 0,
  `byte_out` bigint(20) NOT NULL DEFAULT 0,
  `pkt_in` int(11) DEFAULT NULL,
  `pkt_out` int(11) DEFAULT NULL,
  `step` int(11) NOT NULL DEFAULT 600
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `variables`
--

CREATE TABLE `variables` (
  `id` int(11) NOT NULL,
  `name` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `clear_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `created` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `vendors`
--

CREATE TABLE `vendors` (
  `id` int(11) NOT NULL,
  `name` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `vendors`
--

INSERT INTO `vendors` (`id`, `name`) VALUES(1, 'Unknown');
INSERT INTO `vendors` (`id`, `name`) VALUES(2, 'Eltex');
INSERT INTO `vendors` (`id`, `name`) VALUES(3, 'Huawei');
INSERT INTO `vendors` (`id`, `name`) VALUES(4, 'Zyxel');
INSERT INTO `vendors` (`id`, `name`) VALUES(5, 'Raisecom');
INSERT INTO `vendors` (`id`, `name`) VALUES(6, 'SNR');
INSERT INTO `vendors` (`id`, `name`) VALUES(7, 'Dlink');
INSERT INTO `vendors` (`id`, `name`) VALUES(8, 'Allied Telesis');
INSERT INTO `vendors` (`id`, `name`) VALUES(9, 'Mikrotik');
INSERT INTO `vendors` (`id`, `name`) VALUES(10, 'NetGear');
INSERT INTO `vendors` (`id`, `name`) VALUES(11, 'Ubiquiti');
INSERT INTO `vendors` (`id`, `name`) VALUES(15, 'HP');
INSERT INTO `vendors` (`id`, `name`) VALUES(16, 'Cisco');
INSERT INTO `vendors` (`id`, `name`) VALUES(17, 'Maipu');
INSERT INTO `vendors` (`id`, `name`) VALUES(18, 'Asus');
INSERT INTO `vendors` (`id`, `name`) VALUES(19, 'Linux');
INSERT INTO `vendors` (`id`, `name`) VALUES(20, 'APC');
INSERT INTO `vendors` (`id`, `name`) VALUES(21, 'Schneider');
INSERT INTO `vendors` (`id`, `name`) VALUES(33, 'QSC');
INSERT INTO `vendors` (`id`, `name`) VALUES(34, 'Projectiondesign');
INSERT INTO `vendors` (`id`, `name`) VALUES(35, 'Lenovo');
INSERT INTO `vendors` (`id`, `name`) VALUES(36, 'HiWatch');
INSERT INTO `vendors` (`id`, `name`) VALUES(37, 'LTV');
INSERT INTO `vendors` (`id`, `name`) VALUES(38, 'Yeastar');
INSERT INTO `vendors` (`id`, `name`) VALUES(39, 'Yealink');
INSERT INTO `vendors` (`id`, `name`) VALUES(40, 'Gigaset');
INSERT INTO `vendors` (`id`, `name`) VALUES(41, 'Linksys');
INSERT INTO `vendors` (`id`, `name`) VALUES(42, 'Samsung');
INSERT INTO `vendors` (`id`, `name`) VALUES(43, 'Supermicro');
INSERT INTO `vendors` (`id`, `name`) VALUES(44, 'RDP');
INSERT INTO `vendors` (`id`, `name`) VALUES(45, 'SANYO');
INSERT INTO `vendors` (`id`, `name`) VALUES(46, 'Extreme');
INSERT INTO `vendors` (`id`, `name`) VALUES(47, 'Intel');
INSERT INTO `vendors` (`id`, `name`) VALUES(48, 'Micron');
INSERT INTO `vendors` (`id`, `name`) VALUES(49, 'Gigabyte');
INSERT INTO `vendors` (`id`, `name`) VALUES(50, 'Acer');
INSERT INTO `vendors` (`id`, `name`) VALUES(51, 'Seagate');
INSERT INTO `vendors` (`id`, `name`) VALUES(52, 'SanDisk');
INSERT INTO `vendors` (`id`, `name`) VALUES(53, 'Toshiba');
INSERT INTO `vendors` (`id`, `name`) VALUES(54, 'Kingston');
INSERT INTO `vendors` (`id`, `name`) VALUES(55, 'AddPac');
INSERT INTO `vendors` (`id`, `name`) VALUES(56, 'Devline');
INSERT INTO `vendors` (`id`, `name`) VALUES(57, 'Canon');
INSERT INTO `vendors` (`id`, `name`) VALUES(58, 'Brother');

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `auth_rules`
--
ALTER TABLE `auth_rules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rule` (`rule`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `building`
--
ALTER TABLE `building`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `config`
--
ALTER TABLE `config`
  ADD PRIMARY KEY (`id`),
  ADD KEY `option` (`option_id`);

--
-- Индексы таблицы `config_options`
--
ALTER TABLE `config_options`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `connections`
--
ALTER TABLE `connections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `auth_id` (`auth_id`),
  ADD KEY `device_id` (`device_id`,`port_id`);

--
-- Индексы таблицы `Customers`
--
ALTER TABLE `Customers`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `devices`
--
ALTER TABLE `devices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ip` (`ip`),
  ADD KEY `device_type` (`device_type`);

--
-- Индексы таблицы `device_l3_interfaces`
--
ALTER TABLE `device_l3_interfaces`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `device_models`
--
ALTER TABLE `device_models`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `device_ports`
--
ALTER TABLE `device_ports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `device_id` (`device_id`),
  ADD KEY `port` (`port`),
  ADD KEY `target_port_id` (`target_port_id`);

--
-- Индексы таблицы `device_types`
--
ALTER TABLE `device_types`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `dhcp_log`
--
ALTER TABLE `dhcp_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `timestamp` (`timestamp`,`action`);

--
-- Индексы таблицы `dns_cache`
--
ALTER TABLE `dns_cache`
  ADD PRIMARY KEY (`id`),
  ADD KEY `dns` (`dns`,`ip`),
  ADD KEY `timestamp` (`timestamp`);

--
-- Индексы таблицы `Filter_list`
--
ALTER TABLE `Filter_list`
  ADD PRIMARY KEY (`id`),
  ADD KEY `Name` (`name`);

--
-- Индексы таблицы `Group_filters`
--
ALTER TABLE `Group_filters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `GroupId` (`group_id`,`filter_id`);

--
-- Индексы таблицы `Group_list`
--
ALTER TABLE `Group_list`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `mac_history`
--
ALTER TABLE `mac_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mac` (`mac`,`timestamp`),
  ADD KEY `ip` (`ip`,`timestamp`),
  ADD KEY `timestamp` (`timestamp`,`mac`),
  ADD KEY `timestamp_2` (`timestamp`,`ip`);


ALTER TABLE `mac_vendors` ADD PRIMARY KEY (`id`),  ADD KEY `oui` (`oui`);
ALTER TABLE `mac_vendors` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Индексы таблицы `OU`
--
ALTER TABLE `OU`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `Queue_list`
--
ALTER TABLE `Queue_list`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`);

--
-- Индексы таблицы `remote_syslog`
--
ALTER TABLE `remote_syslog`
  ADD PRIMARY KEY (`id`),
  ADD KEY `date` (`date`,`device_id`,`ip`);
ALTER TABLE `remote_syslog` ADD FULLTEXT KEY `message` (`message`);

--
-- Индексы таблицы `subnets`
--
ALTER TABLE `subnets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ip_int_start` (`ip_int_start`,`ip_int_stop`),
  ADD KEY `dhcp` (`dhcp`,`office`,`hotspot`,`static`);

--
-- Индексы таблицы `syslog`
--
ALTER TABLE `syslog`
  ADD PRIMARY KEY (`id`),
  ADD KEY `timestamp_2` (`timestamp`,`level`,`customer`) USING BTREE;

--
-- Индексы таблицы `Traffic_detail`
--
ALTER TABLE `Traffic_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `src` (`auth_id`,`timestamp`,`router_id`,`src_ip`),
  ADD KEY `dst` (`auth_id`,`timestamp`,`router_id`,`dst_ip`);

--
-- Индексы таблицы `Unknown_mac`
--
ALTER TABLE `Unknown_mac`
  ADD PRIMARY KEY (`id`),
  ADD KEY `timestamp` (`timestamp`,`device_id`,`port_id`,`mac`);

--
-- Индексы таблицы `User_auth`
--
ALTER TABLE `User_auth`
  ADD PRIMARY KEY (`id`),
  ADD KEY `auth_index` (`id`,`user_id`,`ip_int`,`mac`,`ip`,`deleted`) USING BTREE,
  ADD KEY `deleted` (`deleted`),
  ADD KEY `ou_id` (`ou_id`);

--
-- Индексы таблицы `User_auth_alias`
--
ALTER TABLE `User_auth_alias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `auth_id` (`auth_id`);

--
-- Индексы таблицы `User_list`
--
ALTER TABLE `User_list`
  ADD PRIMARY KEY (`id`),
  ADD KEY `users` (`id`,`ou_id`,`enabled`,`blocked`,`deleted`);

--
-- Индексы таблицы `User_stats`
--
ALTER TABLE `User_stats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `timestamp` (`timestamp`,`auth_id`,`router_id`);

--
-- Индексы таблицы `User_stats_full`
--
ALTER TABLE `User_stats_full`
  ADD PRIMARY KEY (`id`),
  ADD KEY `timestamp` (`timestamp`,`auth_id`,`router_id`);

--
-- Индексы таблицы `variables`
--
ALTER TABLE `variables`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `clear_time` (`clear_time`,`created`);

--
-- Индексы таблицы `vendors`
--
ALTER TABLE `vendors`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `auth_rules`
--
ALTER TABLE `auth_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `building`
--
ALTER TABLE `building`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT для таблицы `config`
--
ALTER TABLE `config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=119;

--
-- AUTO_INCREMENT для таблицы `config_options`
--
ALTER TABLE `config_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT для таблицы `connections`
--
ALTER TABLE `connections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `Customers`
--
ALTER TABLE `Customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT для таблицы `devices`
--
ALTER TABLE `devices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `device_l3_interfaces`
--
ALTER TABLE `device_l3_interfaces`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `device_models`
--
ALTER TABLE `device_models`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10013;

--
-- AUTO_INCREMENT для таблицы `device_ports`
--
ALTER TABLE `device_ports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `device_types`
--
ALTER TABLE `device_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `dhcp_log`
--
ALTER TABLE `dhcp_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `dns_cache`
--
ALTER TABLE `dns_cache`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `Filter_list`
--
ALTER TABLE `Filter_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=105;

--
-- AUTO_INCREMENT для таблицы `Group_filters`
--
ALTER TABLE `Group_filters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=220;

--
-- AUTO_INCREMENT для таблицы `Group_list`
--
ALTER TABLE `Group_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT для таблицы `mac_history`
--
ALTER TABLE `mac_history`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
--
-- AUTO_INCREMENT для таблицы `OU`
--
ALTER TABLE `OU`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT для таблицы `Queue_list`
--
ALTER TABLE `Queue_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT для таблицы `remote_syslog`
--
ALTER TABLE `remote_syslog`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `subnets`
--
ALTER TABLE `subnets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT для таблицы `syslog`
--
ALTER TABLE `syslog`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `Traffic_detail`
--
ALTER TABLE `Traffic_detail`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `Unknown_mac`
--
ALTER TABLE `Unknown_mac`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `User_auth`
--
ALTER TABLE `User_auth`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `User_auth_alias`
--
ALTER TABLE `User_auth_alias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `User_list`
--
ALTER TABLE `User_list`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `User_stats`
--
ALTER TABLE `User_stats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `User_stats_full`
--
ALTER TABLE `User_stats_full`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `variables`
--
ALTER TABLE `variables`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `vendors`
--
ALTER TABLE `vendors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10023;
