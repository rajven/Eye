
-- --------------------------------------------------------

--
-- Table structure for table `config_options`
--

CREATE TABLE `config_options` (
  `id` int(11) NOT NULL,
  `option_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `uniq` tinyint(1) NOT NULL DEFAULT 1,
  `type` varchar(10) NOT NULL,
  `default_value` text DEFAULT NULL,
  `min_value` int(11) NOT NULL DEFAULT 0,
  `max_value` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Truncate table before insert `config_options`
--

TRUNCATE TABLE `config_options`;
--
-- Dumping data for table `config_options`
--

INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES
(1, 'KB', 'Число байт в килобайте', 1, 'int', '1024', 1000, 1024),
(3, 'dns server', 'ip-адрес DNS-сервера', 1, 'text', '127.0.0.1', 0, 0),
(5, 'dhcp server', 'ip-адрес DHCP-сервера', 1, 'text', '127.0.0.1', 0, 0),
(9, 'default snmp version', 'Версия snmp по умолчанию. В настоящий момент поддерживаются 1 и 2. Поддержка версии 3 в разработке.', 1, 'int', '2', 1, 3),
(11, 'default snmp community', 'Read snmp community по умолчанию', 1, 'text', 'public', 0, 0),
(17, 'mac discavery', 'Выполнять опрос mac-таблицы коммутаторов при сканировании сети. Если опция отключена будет нельзя сопоставить ip-адрес порту коммутатора', 1, 'bool', '1', 0, 0),
(19, 'arp discavery', 'Выполнять сканирование arp-таблицы роутеров при сканировании сети. Если опция отключена сопоставление mac-адреса ip-адресу будет возможно только из логов dhcp-сервера.', 1, 'bool', '1', 0, 0),
(20, 'default user id', 'Id записи пользователя, в которую помещаются все вновь создаваемые при сканировании ip-адреса. Здесь же создаются записи для адресов, которые выдаёт dhcp-сервер', 1, 'int', '1', 0, 0),
(21, 'admin email', 'E-mail администратора', 1, 'text', 'root', 0, 0),
(22, 'add user from netflow', 'Создавать ли новые записи для неизвестных адресов из анализа трафика netflow. Обычно выключается, поскольку может создавать много фэйковых адресов - например при пинге подсети, когда пакеты идут на все, даже не существующие адреса. Имеет смысл включать только на маршрутизаторах во внешние сети.', 1, 'bool', '0', 0, 0),
(23, 'save traffic detail', 'Сохранять ли детализацию трафика из netflow по ip-адресам пользователей', 1, 'bool', '1', 0, 0),
(26, 'history detail traffic', 'Глубина хранения детализации в сутках. Установка значения больше 3-7 дней приведёт к разрастанию базы данных и увеличит время отображения детализации в интерфейсе администратора', 1, 'int', '3', 1, 7),
(27, 'history dhcp lease', 'Глубина хранения аренды dhcp-сервера', 1, 'int', '1', 0, 0),
(28, 'router_login', 'Логин для входа на маршрутизаторы Mikrotik для управления dhcp-сервером и контролем доступа', 1, 'text', 'admin', 0, 0),
(29, 'router_password', 'Пароль для входа на маршрутизаторы Mikrotik для управления dhcp-сервером и контролем доступа', 1, 'text', 'admin', 0, 0),
(30, 'router_port', 'Порт telnet маршрутизатора', 1, 'int', '23', 23, 0),
(32, 'org name', 'Название организации', 1, 'text', 'ORG', 0, 0),
(33, 'office domain', 'Домен организации', 1, 'text', 'local', 0, 0),
(34, 'debug', 'Включить отладку', 1, 'bool', '0', 0, 0),
(35, 'connections history, days', 'Время хранения истории мест подключения ip-адресов', 1, 'int', '90', 1, 365),
(37, 'refresh access lists', 'Расположение скрипта управления контролем доступа для роутеров Mikrotik', 1, 'text', '/usr/local/scripts/sync_user_list.pl', 0, 0),
(38, 'regenerate dhcp cconfig', 'Расположение скрипта управления конфигурацией dhcp-серверами', 1, 'text', '/usr/local/scripts/update-dnsmasq', 0, 0),
(39, 'regenerate dns cconfig', 'Расположение скрипта управления dns-сервером', 1, 'text', '/usr/local/scripts/update-dns', 0, 0),
(40, 'regenerate nagios cconfig', 'Расположение скрипта конфигурирования Nagios', 1, 'text', '/etc/nagios/restart_nagios', 0, 0),
(41, 'discavery network', 'Расположение скрипта сканирования сети', 1, 'text', '/usr/local/scripts/fetch_new_arp.pl', 0, 0),
(43, 'hotspot_user_id', 'Id запись юзера, в которой создаются ip-адреса, выдаваемые хот-спотом. По умолчанию, используется та же запись, что и для обычных пользователей, но лучше завести отдельную учётку, чтобы не мешать временные записи и постоянные записи компьютеров организации', 1, 'int', '1', 1, 0),
(44, 'Ignore hotspot dhcp log', 'Не писать лог событий dhcp-сервера хотспота. Имеет смысл вклчючать, поскольку время аренды в хот-споте как правило маленькое и в записях хот-спота становятся незаметны логи обычных пользователей', 1, 'bool', '1', 0, 0),
(45, 'ignore update dhcp event', 'Не писать события обновления ip-адреса dhcp-сервера. ', 1, 'bool', '0', 0, 0),
(46, 'update hostname from dhcp', 'Обновлять имя хоста в DNS при получении адреса по DHCP', 1, 'bool', '0', 0, 0),
(47, 'history worklog', 'Глубина хранения логов работы в интерфейсе администратора', 1, 'int', '90', 30, 1095),
(48, 'history syslog', 'Глубина хранения логов работы syslog-сервера', 1, 'int', '90', 30, 1095),
(49, 'history traffic stats', 'Глубина хранения статистики трафика юзеров', 1, 'int', '365', 30, 0),
(50, 'urgent sync access', 'Немедленное изменение списков доступа на роутере после правки записи пользователя', 1, 'bool', '0', 0, 0),
(51, 'Email_alert', 'Отправлять e-mail сообщения для уровней сообщений WARNING & ERROR', 1, 'bool', '1', 0, 0),
(52, 'Sender email', 'E-mail адрес, с которого рассылается почта', 1, 'text', 'root', 0, 0),
(53, 'log level', 'Каждый уровень включает в себя предыдущий:\r\n0 - ERROR - писать только ошибки\r\n1 - WARNING - писать предупреждения\r\n2 - INFO - писать информационные сообщения\r\n3 - VERBOSE - писать подробную информацию о выполняемых операциях', 1, 'int', '2', 0, 3),
(54, 'enable_quotes', 'Включить обработку квот по трафику', 1, 'bool', '0', 0, 0),
(55, 'netflow_step', 'Интервал сброса данных из коллектора netflow, минуты', 1, 'int', '10', 1, 60),
(56, 'traffic_ipstat_history', 'Время хранения полной статистики по трафику для каждого ip-адреса в сутках. Таблица в 6 раз больше обычной часовой статистики. Врядли кому-то потребуется глубина хранения более месяца.', 1, 'int', '30', 7, 365),
(57, 'nagios_url', 'Адрес сайта nagios', 1, 'text', 'http://127.0.0.1/nagios', 0, 0),
(58, 'cacti_url', 'Адрес сайта cacti', 1, 'text', 'http://127.0.0.1/cacti', 0, 0),
(59, 'torrus_url', 'Адрес сайта Torrus', 1, 'text', 'http://127.0.0.1/torrus', 0, 0),
(60, 'wiki_url', 'Адрес wiki', 1, 'text', 'http://127.0.0.1/wiki', 0, 0),
(61, 'wiki_path', 'Путь к каталогу данных вики', 1, 'text', '/var/www/foswiki/data/', 0, 0),
(62, 'stat_url', 'Адрес этого сайта', 1, 'text', 'http://127.0.0.1/stat', 0, 0);
