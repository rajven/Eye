-- Импорт данных в PostgreSQL для русской локали

-- ACL (Access Control List)
INSERT INTO acl (id, name, "description.english", "description.russian") 
VALUES 
(1, 'Full access', 'Full access', 'Полный доступ'),
(2, 'Operator', 'Editing parameters that are not related to access rights', 'Редактирование параметров, не связанных с правами доступа'),
(3, 'View only', 'View only', 'Только просмотр')
ON CONFLICT (id) DO UPDATE SET
    name = EXCLUDED.name,
    "description.english" = EXCLUDED."description.english",
    "description.russian" = EXCLUDED."description.russian";

-- Buildings
INSERT INTO building (id, name, comment)
VALUES (1, 'Earth', 'Somewhere')
ON CONFLICT (id) DO UPDATE SET
    name = EXCLUDED.name,
    comment = EXCLUDED.comment;

-- Configuration options
INSERT INTO config_options (id, option_name, "description.russian", "description.english", draft, uniq, type, default_value, min_value, max_value)
VALUES
(1, 'KB', 'Еденица измерения трафика - Килобайт (0) или кибибайт (1,default)', 'Traffic measurement unit - Kilobyte (1000b) or kibibyte (1024b,default)', 0, 1, 'bool', '1024', 0, 1),
(3, 'dns server', 'ip-адрес DNS-сервера', 'DNS server ip address', 0, 1, 'text', '127.0.0.1', 0, 0),
(5, 'dhcp server', 'ip-адрес DHCP-сервера', 'ip address of the DHCP server', 0, 1, 'text', '127.0.0.1', 0, 0),
(9, 'default snmp version', 'Версия snmp по умолчанию. В настоящиймомент поддерживаются 1 и 2. Поддержка версии 3 в разработке.', 'The default version of snmp. Currently, 1 and 2 are supported. Support for version 3 is in development.', 0, 1, 'int', '2', 1, 3),
(11, 'default snmp community', 'Read snmp community по умолчанию','Read snmp community by default', 0, 1, 'text', 'public', 0, 0),
(21, 'admin email', 'E-mail администратора', 'Administrator e-mail', 0, 1, 'text', 'root', 0, 0),
(22, 'add user from netflow', 'Создавать ли новые записи для неизвестных адресов из анализа трафика netflow. Не включать, если netflow снимает данные с маршрутизатора локальной сети', 'Whether to create new records for unknown addresses from netflow traffic analysis. Do not enable if netflow get data from the local network router', 0, 1, 'bool', '0', 0, 1),
(23, 'save traffic detail', 'Сохранять ли детализацию трафика из netflow по ip-адресам пользователей', 'Whether to keep the details of traffic from netflow by ip addresses of users', 0, 1, 'bool', '1', 0, 1),
(26, 'history detail traffic', 'Глубина хранения детализации в сутках. Установка значения больше 3-7 дней приведёт к разрастанию базы данных и увеличит время отображения детализации в интерфейсе администратора', 'Depth of detail storage in days. Setting a value greater than 3-7 days will cause the database to grow and increase the time about to display details in the admin interface', 0, 1, 'int', '3', 1, 7),
(27, 'history dhcp lease', 'Глубина хранения аренды dhcp-сервера','Storage depth of the dhcp server lease', 0, 1, 'int', '1', 0, 0),
(28, 'router_login', 'Логин для входа на сетевые устройства по умолчанию', 'Default login for network devices', 0, 1, 'text', 'admin', 0, 0),
(29, 'router_password', 'Пароль по умолчанию на сетевые устройства', 'Default password for network devices', 0, 1, 'text', 'admin', 0, 0),
(30, 'router_ssh_port', 'Порт ssh по умолчанию', 'SSH default port', 0, 1, 'int', '22', 22, 0),
(32, 'org name', 'Название организации', 'Organization name', 0, 1, 'text', 'ORG', 0, 0),
(33, 'office domain', 'Домен организации', 'Organization domain', 0, 1, 'text', 'local', 0, 0),
(34, 'debug', 'Включить отладку', 'Enable debugging', 0, 1, 'bool', '0', 0, 1),
(35, 'connections history, days', 'Время хранения истории мест подключения ip-адресов', 'Storage time of the history of connection locations of ip addresses', 0, 1, 'int', '90', 1, 365),
(37, 'refresh access lists', 'Расположение скрипта управления контролем доступа для роутеров Mikrotik', 'Location of the access control script for Mikrotik routers', 0, 1, 'text', '/opt/Eye/scripts/sync_mikrotik.pl', 0, 0),
(38, 'regenerate dhcp cconfig', 'Расположение скрипта управления конфигурацией dhcp-серверами', 'Location of the dhcp server configuration management script', 0, 1, 'text', '/opt/Eye/scripts/update-dnsmasq', 0, 0),
(39, 'regenerate dns cconfig', 'Расположение скрипта управления dns-сервером', 'Location of the dns server management script', 0, 1, 'text', '/opt/Eye/scripts/update-dns', 0, 0),
(40, 'regenerate nagios cconfig', 'Расположение скрипта конфигурирования Nagios', 'Location of the Nagios configuration script', 0, 1, 'text', '/etc/nagios/restart_nagios', 0, 0),
(41, 'discovery network', 'Расположение скрипта сканирования сети', 'Location of the network scan script', 0, 1, 'text', '/opt/Eye/scripts/fetch_new_arp.pl', 0, 0),
(44, 'Ignore hotspot dhcp log', 'Не писать лог событий dhcp-сервера хотспота. Имеет смысл вклчючать, поскольку время аренды в хот-споте как правило маленькое и в записях хот-спота становятся незаметны логи обычных пользователей', 'Do not write the event log of the hotspot dhcp server. It makes sense toinclude it, since the rental time in the hotspot is usually small and the logs of ordinary users become invisible in the hotspot records', 0, 1, 'bool', '1', 0, 1),
(45, 'ignore update dhcp event', 'Не писать события обновления ip-адреса dhcp-сервера. ', 'Do not write events for updating the IP address of the dhcp server. ', 0, 1, 'bool', '0', 0, 1),
(46, 'update hostname from dhcp', 'Обновлять имя хоста в DNS при получении адреса по DHCP', 'Update the hostname in DNS when receiving the address via DHCP', 0, 1, 'bool', '0', 0, 1),
(47, 'history worklog', 'Глубина хранения VERBOSE логов работы в интерфейсе администратора', 'Depth of work VERBOSE logs storage in the admin interface', 0, 1, 'int', '90', 0, 1095),
(48, 'history syslog', 'Глубина хранения логов работы syslog-сервера', 'Syslog server logs storage depth', 0, 1, 'int', '90', 0, 1095),
(49, 'history traffic stats', 'Глубина хранения статистики трафикаюзеров', 'User traffic statistics storage depth', 0, 1, 'int', '365', 0, 0),
(50, 'urgent sync access', 'Немедленное изменение списков доступа на роутере после правки записи пользователя', 'Immediate change of access lists on the router after editing the user record ', 0, 1, 'bool', '0', 0, 1),
(51, 'Email_alert', 'Отправлять e-mail уведомления', 'Send e-mail notifications', 0, 1, 'bool', '1', 0, 1),
(52, 'Sender email', 'E-mail адрес, с которого рассылается почта','E-mail address from which mail is sent', 0, 1, 'text', 'root', 0, 0),
(53, 'log level', 'Каждый уровень включает в себя предыдущий:\r\n0- ERROR - писать только ошибки\r\n1 - WARNING - писать предупреждения\r\n2 - INFO - писать информационные сообщения\r\n3 - VERBOSE - писать подробную информацию о выполняемых операциях', 'Each level includes the previous one:\r\n0 - ERROR - write only errors\r\n1 - WARNING - write warnings\r\n2 - INFO - write informational\r\n3 - VERBOSE - write detailed information about the operations performed ', 0, 1, 'int', '2', 0, 3),
(54, 'enable_quotes', 'Включить обработку квот по трафику', 'Enable traffic quota processing', 0, 1, 'bool', '0', 0, 1),
(55, 'netflow_step', 'Интервал сброса данных из коллектора netflow, минуты', 'Data reset interval from netflow collector, minutes', 0, 1, 'int', '1', 1, 10),
(56, 'traffic_ipstat_history', 'Время хранения полной статистики по трафику для каждого ip-адреса в сутках. Таблица в 6 раз больше обычной часовой статистики. Врядли кому-то потребуется глубина хранения более месяца.', 'The storage time of complete traffic statistics for each ip address in days. The table is 6 times larger than the usual hourly statistic Hardly anyone will need a storage depth of more than a month.', 0, 1, 'int', '30', 0, 365),
(57, 'nagios_url', 'Адрес сайта nagios', 'nagios site address', 0, 1, 'text', 'http://127.0.0.1/nagios', 0, 0),
(58, 'cacti_url', 'Адрес сайта cacti', 'cacti site address', 0, 1, 'text', 'http://127.0.0.1/cacti', 0, 0),
(59, 'torrus_url', 'Адрес сайта Torrus', 'Torrus website address', 0, 1, 'text', 'http://127.0.0.1/torrus/CollectorName/', 0, 0),
(60, 'wiki_url', 'Адрес wiki', 'Wiki website address', 0, 1, 'text', 'http://127.0.0.1/wiki', 0, 0),
(61, 'wiki_path', 'Путь к каталогу данных вики', 'Path to wiki data directory', 0, 1, 'text', '/var/www/foswiki/data/', 0, 0),
(62, 'stat_url', 'Адрес этого сайта', 'Address of this site', 0, 1, 'text', 'http://127.0.0.1/stat', 0, 0),
(63, 'wiki_web', 'Web for Wiki. Default - Main. http://example.local/Main/WebHome', 'Web for Wiki. Default - Main. http://example.local/Main/WebHome', 0, 1, 'text', 'Main', 0, 0),
(64, 'auto_mac_rule', 'Создавать автоматическую привязку мак-адреса к юзеру. Т.е. все ip-адреса для найденного мака будут привязываться к одном и тому же юзеру.', 'Create an automatic binding of the mac address to the user. I.e. all ip addresses for the found mac will be bound to the same user.', 0, 1, 'bool', '0', 0, 1),
(65, 'mikrotik_command_interface', 'Используемый способ конфигурирования (0 - cli для ROS 6, 1 - rest api для ROS 7)', 'Configuration method used (0 - cli for ROS 6, 1 - rest api for ROS 7)', 1, 1, 'int', '0', 0, 1),
(66, 'mikrotik_rest_api_ssl', 'Использовать https для rest api', 'Use HTTPS for rest api', 1, 1, 'bool', '1', 0, 1),
(67, 'mikrotik_rest_api_port', 'Порт вэб-интерфейса для rest api','Web interface port for rest API', 1, 1, 'int', '443', 0, 0),
(68, 'config_mode', 'Режим конфигурирования. Скрипт опроса устройств не выполняется.', 'Configuration mode. The device polling script is not running.', 0, 1, 'bool', '0', 0, 1),
(69, 'clean_empty_user', 'Автоматически удалять записи пользователей, не содержащие ip-адресов или автоматических привязок', 'Automatically delete user records that do not contain IP addresses or automatic bindings', 0, 1, 'bool', '0', 0, 1),
(70, 'dns_server_type', 'Тип используемого dns-сервера: Windows, Bind. Если используется локальный dnsmasq - параметры dns-сервера указывать не надо.', 'The type of dns server used: Windows, Bind. If you are using a local dnsmasq, you do not need to specify the dns server parameters.', 0, 1, 'list;windows;bind', 'bind', 0, 0),
(71, 'enable_dns_updates', 'Включить обновления DNS имен при изменении dns-имени в ip-записи', 'Enable DNS name updates when dns name changes in an ip record', 0, 1, 'bool', '0', 0, 1),
(72, 'netflow_path', 'Каталог для хранения данных, полученных по netflow от маршрутизаторов', 'The directory for storing data received via netflow from routers', 0, 1, 'text', '/opt/Eye/netflow', 0, 0),
(73, 'check_computer_exists', 'Проверять существование компьютера в домене перед обновлением DNS по DHCP запросу', 'Verify the existence of a computer in the domain before updating DNS by DHCP request', 0, 1, 'bool', '1', 0, 0)
ON CONFLICT (id) DO UPDATE SET
    option_name = EXCLUDED.option_name,
    "description.russian" = EXCLUDED."description.russian",
    "description.english" = EXCLUDED."description.english",
    draft = EXCLUDED.draft,
    uniq = EXCLUDED.uniq,
    type = EXCLUDED.type,
    default_value = EXCLUDED.default_value,
    min_value = EXCLUDED.min_value,
    max_value = EXCLUDED.max_value;

-- System configuration values
INSERT INTO config (id, option_id, value)
VALUES
(1, 1, '0'),
(2, 11, 'public'),
(3, 32, 'ORG'),
(123, 19, '1'),
(124, 35, '120'),
(125, 9, '2'),
(126, 41, '/opt/Eye/scripts/fetch_new_arp.pl'),
(127, 26, '3'),
(128, 27, '10'),
(129, 48, '90'),
(130, 49, '365'),
(131, 47, '90'),
(132, 53, '1'),
(133, 55, '1'),
(134, 56, '30'),
(135, 34, '0'),
(137, 65, '0'),
(142, 54, ''),
(143, 17, '1'),
(144, 37, '/opt/Eye/scripts/sync_mikrotik.pl'),
(145, 23, '1'),
(148, 22, '1')
ON CONFLICT (id) DO UPDATE SET
    option_id = EXCLUDED.option_id,
    value = EXCLUDED.value;

-- System users/administrators
-- В PostgreSQL нет аналога ON DUPLICATE KEY UPDATE, используем ON CONFLICT
INSERT INTO Customers (id, Login, comment, password, api_key, rights)
VALUES (1, 'admin', 'Administrator', '$2y$11$wohV8Tuqu0Yai9Shacei5OKfMxG5bnLxB5ACcZcJJ3pYEbIH0qLGG', 'c3284d0f94606de1fd2af172aba15bf31', 1)
ON CONFLICT (id) DO UPDATE SET
    Login = EXCLUDED.Login,
    comment = EXCLUDED.comment,
    password = EXCLUDED.password,
    api_key = EXCLUDED.api_key,
    rights = EXCLUDED.rights;

-- Device models
INSERT INTO device_models (id, model_name, vendor_id, poe_in, poe_out, nagios_template)
VALUES
(1, '2011LS', 9, 1, 0, NULL),
(2, '2011UAS-2HnD', 9, 1, 0, NULL),
(3, 'AT-8000S', 8, 0, 0, NULL),
(4, 'AT-8100S/48POE', 8, 0, 0, NULL),
(5, 'AT-9000/28', 8, 0, 0, NULL),
(6, 'AT-GS950/24', 8, 0, 0, NULL),
(7, 'CCR1009-7G-1C-1S+', 9, 0, 0, NULL),
(8, 'CCR1036-8G-2S+', 9, 0, 0, NULL),
(10, 'CRS317-1G-16S+', 9, 0, 0, NULL),
(11, 'CRS326-24S+2Q+', 9, 0, 0, NULL),
(12, 'CRS328-24P-4S+', 9, 1, 0, NULL),
(14, 'CRS328-4C-20S-4S+', 9, 0, 0, NULL),
(15, 'DGS-3120-48TC', 7, 0, 0, NULL),
(16, 'ES-2024', 4, 0, 0, NULL),
(17, 'ES-2024A', 4, 0, 0, NULL),
(18, 'ES-2108', 4, 0, 0, NULL),
(19, 'ES-2108-G', 4, 0, 0, NULL),
(20, 'ES-3124-4F', 4, 0, 0, NULL),
(21, 'GS110TP', 10, 0, 1, NULL),
(22, 'GS-4024', 4, 0, 0, NULL),
(23, 'HP 1910', 15, 0, 0, NULL),
(24, 'ISCOM2110A-MA', 5, 0, 0, NULL),
(25, 'ISCOM2110EA-MA', 5, 0, 0, NULL),
(26, 'ISCOM2126EA-MA', 5, 0, 0, NULL),
(27, 'ISCOM2128EA-MA', 5, 0, 0, NULL),
(28, 'Linux server', 1, 0, 0, ''),
(29, 'MES2124F', 2, 0, 0, NULL),
(30, 'MES2124MB', 2, 0, 0, NULL),
(31, 'MES5248', 2, 0, 0, NULL),
(32, 'RB2011UAS', 9, 1, 0, NULL),
(33, 'RB3011UiAS', 9, 1, 0, NULL),
(34, 'RB960PGS', 9, 1, 1, NULL),
(35, 'RBD52G-5HacD2HnD', 9, 1, 0, NULL),
(36, 'S2940-8G-v2', 6, 0, 0, NULL),
(37, 'S2980G-24T', 6, 0, 0, NULL),
(38, 'S3750G-24S-E', 6, 0, 0, NULL),
(39, 'S5300-52P-LI-AC', 3, 0, 0, NULL),
(40, 'S5320-52X-PWR-SI-AC', 3, 0, 0, NULL),
(41, 'S5321-28X-SI-AC', 3, 0, 0, NULL),
(42, 'S5321-52X-SI-AC', 3, 0, 0, NULL),
(43, 'S6320-54C-EI-48S-AC', 3, 0, 0, NULL),
(44, 'SNR-S2980G-24T', 6, 0, 0, NULL),
(45, 'V1910-16G', 15, 0, 0, NULL),
(46, 'V1910-24G-PoE', 15, 0, 0, NULL),
(47, 'Windows server', 1, 0, 0, NULL),
(48, 'WS-C2960G-24TC-L', 16, 0, 0, NULL),
(49, 'WS-C3560G-24TS-S', 16, 0, 0, NULL),
(50, 'x210-16GT', 8, 0, 0, NULL),
(51, 'x210-24GT', 8, 0, 0, NULL),
(52, 'x610-24Ts/X', 8, 0, 0, NULL),
(53, 'x610-48Ts', 8, 0, 0, NULL),
(54, 'XGS-4728', 4, 0, 0, NULL),
(55, 'ZyWall 310', 4, 0, 0, NULL),
(56, 'APC Smart-UPS_3000', 20, 0, 0, 'ups.cfg'),
(57, 'APC Smart-UPS_5000', 20, 0, 0, 'ups.cfg'),
(58, 'Schneider Smart-UPS_3000', 21, 0, 0, NULL),
(59, 'SMG-1016M', 2, 0, 0, NULL),
(60, 'EATON 9PX 1500i RT 2U', 64, 0, 0, NULL),
(61, 'EATON 9PX3000i_RT_2U', 64, 0, 0, NULL),
(62, 'EATON 9PX_6000i', 64, 0, 0, NULL),
(63, 'EATON PW9130_3000', 64, 0, 0, NULL),
(64, 'EATON PW9130_3000VA-R', 64, 0, 0, NULL),
(65, 'Epson WF-5620 Series', 59, 0, 0, NULL),
(66, 'Epson WF-8590 Series', 59, 0, 0, NULL),
(67, 'HP Officejet-7000', 15, 0, 0, 'hp.cfg'),
(68, 'OKI C610', 62, 0, 0, NULL),
(69, 'OKI MB472', 62, 0, 0, NULL),
(70, 'OKI MB491', 62, 0, 0, NULL),
(71, 'OKI MC562', 62, 0, 0, NULL),
(72, 'OKI MC573', 62, 0, 0, NULL),
(73, 'OKI MC861', 62, 0, 0, NULL),
(74, 'Panasonic KX-MB2000RU', 61, 0, 0, NULL),
(75, 'PT-MZ10KE', 61, 0, 0, NULL),
(76, 'PT-VX41', 61, 0, 0, NULL),
(77, 'Rave 522AA', 33, 0, 0, NULL),
(78, 'DZ570E', 61, 0, 0, NULL),
(79, 'DZ6700', 61, 0, 0, NULL),
(80, 'Rcq80', 61, 0, 0, NULL),
(81, 'RZ12K', 61, 0, 0, NULL),
(82, 'RZ660', 61, 0, 0, NULL),
(83, 'RZ770', 61, 0, 0, NULL),
(84, 'RZ970', 61, 0, 0, NULL),
(85, 'XVR-5216', 66, 0, 0, NULL),
(86, 'HWg-STE', 68, 0, 0, NULL),
(87, 'Computer', 1, 0, 0, ''),
(88, 'Mobile Phone', 1, 0, 0, NULL),
(89, 'Switch', 1, 0, 0, NULL),
(90, 'Projectiondesign F22', 34, 0, 0, NULL),
(91, 'DS-I252', 36, 0, 0, NULL),
(92, 'LTV-CNE-720-48', 37, 0, 0, NULL),
(93, 'U-100', 38, 0, 0, NULL),
(94, 'TAU-8', 2, 0, 0, NULL),
(95, 'SIP-T21P E2', 39, 0, 0, NULL),
(96, 'A510 IP', 40, 0, 0, NULL),
(97, 'W60B', 39, 0, 0, NULL),
(98, 'TAU-2M', 2, 0, 0, NULL),
(99, 'PAP2T', 41, 0, 0, NULL),
(100, 'VP-12', 2, 0, 0, NULL),
(101, 'SIP-T23P', 39, 0, 0, NULL),
(102, 'SPA-2102', 16, 0, 0, NULL),
(103, 'RB760iGS', 9, 1, 0, NULL),
(104, 'MES2324B', 2, 0, 0, NULL),
(105, 'MES2324FB', 2, 0, 0, NULL),
(106, 'MES2124P', 2, 0, 1, NULL),
(107, 'MES2428P', 2, 0, 1, NULL),
(108, 'Symmetra LX 16000', 20, 0, 0, 'symmetra.cfg'),
(109, 'SNR-UPS-ONT20', 6, 0, 0, 'ups3.cfg'),
(110, 'MES-3728', 4, 0, 0, NULL),
(111, 'SNR-S5210G-24TX-UPS-R', 6, 0, 0, NULL),
(112, 'SNR-S2985G-24TC', 6, 0, 0, NULL),
(113, 'MES-5248', 2, 0, 0, NULL),
(114, 'SNR-S5210G-24TX-POE', 6, 0, 1, NULL),
(115, 'SNR-S5210G-24TX-UPS', 6, 0, 0, NULL),
(116, 'SNR-S5210X-8F-UPS', 6, 0, 0, NULL),
(117, 'SNR-S2982G-8T-UPS', 6, 0, 0, NULL)
ON CONFLICT (id) DO UPDATE SET
    model_name = EXCLUDED.model_name,
    vendor_id = EXCLUDED.vendor_id,
    poe_in = EXCLUDED.poe_in,
    poe_out = EXCLUDED.poe_out,
    nagios_template = EXCLUDED.nagios_template;

-- Device types
INSERT INTO device_types (id, "name.russian", "name.english")
VALUES
(0, 'Роутер', 'Router'),
(1, 'Свич', 'Switch'),
(2, 'Шлюз', 'Gateway'),
(3, 'Сервер', 'Server'),
(4, 'Точка доступа', 'WiFi Access Point'),
(5, 'Сетевое устройство', 'Network device')
ON CONFLICT (id) DO UPDATE SET
    "name.russian" = EXCLUDED."name.russian",
    "name.english" = EXCLUDED."name.english";

-- Filter instances
INSERT INTO filter_instances (id, name, comment)
VALUES (1, 'default', NULL)
ON CONFLICT (id) DO UPDATE SET
    name = EXCLUDED.name,
    comment = EXCLUDED.comment;

-- Filter groups (русские названия)
INSERT INTO Group_list (id, instance_id, group_name, comment)
VALUES
(0, 1, 'default', 'Всё запрещено'),
(1, 1, 'Allow all', 'Разрешено всё'),
(2, 1, 'Users', 'Для пользователей')
ON CONFLICT (id) DO UPDATE SET
    instance_id = EXCLUDED.instance_id,
    group_name = EXCLUDED.group_name,
    comment = EXCLUDED.comment;

-- Organizational Units (русские названия)
INSERT INTO OU (id, ou_name, comment, default_users, default_hotspot, nagios_dir, nagios_host_use, nagios_ping, nagios_default_service, enabled, filter_group_id, queue_id, dynamic, life_duration, parent_id)
VALUES
(0, '!Всё', NULL, 0, 0, '/etc/nagios/any', 'generic-host', 1, NULL, 0, 0, 0, 0, 24.00, NULL),
(1, 'Сервера', NULL, 0, 0, NULL, NULL, 1, NULL, 1, 1, 0, 0, 24.00, NULL),
(2, 'Администраторы', NULL, 0, 0, NULL, NULL, 1, NULL, 0, 0, 0, 0, 24.00, NULL),
(3, 'Пользователи', NULL, 0, 0, NULL, NULL, 1, NULL, 0, 0, 0, 0, 24.00, NULL),
(4, 'VOIP', NULL, 0, 0, 'voip', 'voip', 1, NULL, 1, 4, 5, 0, 24.00, NULL),
(5, 'IPCAM', NULL, 0, 0, 'videocam', 'ip-cam', 1, NULL, 0, 0, 0, 0, 24.00, NULL),
(6, 'Принтеры', NULL, 0, 0, 'printers', 'printers', 1, 'printer-service', 0, 0, 0, 0, 24.00, NULL),
(7, 'Свичи', NULL, 0, 0, 'switches', 'switches', 1, NULL, 0, 0, 0, 0, 24.00, NULL),
(8, 'UPS', NULL, 0, 0, 'ups', 'ups', 1, NULL, 0, 0, 0, 0, 24.00, NULL),
(9, 'Охрана', NULL, 0, 0, 'security', 'security', 1, NULL, 0, 0, 0, 0, 24.00, NULL),
(10, 'Роутеры', NULL, 0, 0, 'routers', 'routers', 1, NULL, 0, 0, 0, 0, 24.00, NULL),
(11, 'WiFi AP', NULL, 0, 0, 'ap', 'ap', 1, NULL, 0, 0, 0, 0, 24.00, NULL),
(12, 'DHCP', NULL, 1, 0, NULL, NULL, 1, NULL, 0, 0, 0, 0, 24.00, NULL),
(13, 'Гости', NULL, 0, 0, NULL, NULL, 1, NULL, 1, 1, 4, 1, 24.00, NULL)
ON CONFLICT (id) DO UPDATE SET
    ou_name = EXCLUDED.ou_name,
    comment = EXCLUDED.comment,
    default_users = EXCLUDED.default_users,
    default_hotspot = EXCLUDED.default_hotspot,
    nagios_dir = EXCLUDED.nagios_dir,
    nagios_host_use = EXCLUDED.nagios_host_use,
    nagios_ping = EXCLUDED.nagios_ping,
    nagios_default_service = EXCLUDED.nagios_default_service,
    enabled = EXCLUDED.enabled,
    filter_group_id = EXCLUDED.filter_group_id,
    queue_id = EXCLUDED.queue_id,
    dynamic = EXCLUDED.dynamic,
    life_duration = EXCLUDED.life_duration,
    parent_id = EXCLUDED.parent_id;

-- Traffic shaping queues
INSERT INTO Queue_list (id, queue_name, Download, Upload)
VALUES
(0, 'unlimited', 0, 0),
(1, '2M/2M', 2048, 2048),
(2, '10M/10M', 10240, 10240),
(3, '100M/100M', 102400, 102400),
(4, '50M/50M', 50000, 50000),
(5, '20M/20M', 20480, 20480),
(6, '200M/200M', 212400, 212400),
(7, '1G/1G', 1024000, 1024000),
(8, '2G/2G', 2048000, 2048000)
ON CONFLICT (id) DO UPDATE SET
    queue_name = EXCLUDED.queue_name,
    Download = EXCLUDED.Download,
    Upload = EXCLUDED.Upload;

-- Network subnets
INSERT INTO subnets (id, subnet, vlan_tag, ip_int_start, ip_int_stop, dhcp_start, dhcp_stop, dhcp_lease_time, gateway, office, hotspot, vpn, free, dhcp, static, dhcp_update_hostname, discovery, notify, comment)
VALUES (1, '192.168.2.0/24', 2, 3232236032, 3232236287, 3232236132, 3232236182, 480, 3232236033, 1, 0, 0, 0, 1, 0, 1, 1, 7, 'LAN')
ON CONFLICT (id) DO UPDATE SET
    subnet = EXCLUDED.subnet,
    vlan_tag = EXCLUDED.vlan_tag,
    ip_int_start = EXCLUDED.ip_int_start,
    ip_int_stop = EXCLUDED.ip_int_stop,
    dhcp_start = EXCLUDED.dhcp_start,
    dhcp_stop = EXCLUDED.dhcp_stop,
    dhcp_lease_time = EXCLUDED.dhcp_lease_time,
    gateway = EXCLUDED.gateway,
    office = EXCLUDED.office,
    hotspot = EXCLUDED.hotspot,
    vpn = EXCLUDED.vpn,
    free = EXCLUDED.free,
    dhcp = EXCLUDED.dhcp,
    static = EXCLUDED.static,
    dhcp_update_hostname = EXCLUDED.dhcp_update_hostname,
    discovery = EXCLUDED.discovery,
    notify = EXCLUDED.notify,
    comment = EXCLUDED.comment;

-- Device vendors
INSERT INTO vendors (id, name)
VALUES
(1, 'Unknown'),
(2, 'Eltex'),
(3, 'Huawei'),
(4, 'Zyxel'),
(5, 'Raisecom'),
(6, 'SNR'),
(7, 'Dlink'),
(8, 'Allied Telesis'),
(9, 'Mikrotik'),
(10, 'NetGear'),
(11, 'Ubiquiti'),
(15, 'HP'),
(16, 'Cisco'),
(17, 'Maipu'),
(18, 'Asus'),
(19, 'Linux'),
(20, 'APC'),
(21, 'Schneider'),
(33, 'QSC'),
(34, 'Projectiondesign'),
(35, 'Lenovo'),
(36, 'HiWatch'),
(37, 'LTV'),
(38, 'Yeastar'),
(39, 'Yealink'),
(40, 'Gigaset'),
(41, 'Linksys'),
(42, 'Samsung'),
(43, 'Supermicro'),
(44, 'RDP'),
(45, 'SANYO'),
(46, 'Extreme'),
(47, 'Intel'),
(48, 'Micron'),
(49, 'Gigabyte'),
(50, 'Acer'),
(51, 'Seagate'),
(52, 'SanDisk'),
(53, 'Toshiba'),
(54, 'Kingston'),
(55, 'AddPac'),
(56, 'Devline'),
(57, 'Canon'),
(58, 'Brother'),
(59, 'Epson'),
(60, 'IP-COM'),
(61, 'Panasonic'),
(62, 'OKI'),
(63, 'Apple'),
(64, 'Eaton'),
(65, 'Barco'),
(66, 'Trassir'),
(67, 'Testo'),
(68, 'Hw-group'),
(69, 'Tp-link')
ON CONFLICT (id) DO UPDATE SET
    name = EXCLUDED.name;

-- Filter rules list
INSERT INTO Filter_list (id, name, comment, proto, dst, dstport, srcport, type)
VALUES
(1, 'pop3', NULL, 'tcp', '0/0', '110', NULL, 0),
(3, 'http', NULL, 'tcp', '0/0', '80', NULL, 0),
(4, 'https', NULL, 'tcp', '0/0', '443', NULL, 0),
(5, 'icq', NULL, 'tcp', '0/0', '5190', NULL, 0),
(6, 'jabber', NULL, 'tcp', '0/0', '5222', NULL, 0),
(9, 'allow_all', 'любой трафик', 'all', '0/0', '0', '0', 0),
(10, 'icmp', NULL, 'icmp', '0/0', '0', NULL, 0),
(11, 'ftp', NULL, 'tcp', '0/0', '20-21', NULL, 0),
(15, 'telnet', NULL, 'tcp', '0/0', '23', NULL, 0),
(16, 'ssh', NULL, 'tcp', '0/0', '22', NULL, 0),
(28, 'smtp', NULL, 'tcp', '0/0', '25', NULL, 0),
(32, 'rdp', NULL, 'tcp', '0/0', '3389', NULL, 0),
(40, 'ntp', NULL, 'udp', '0/0', '123', NULL, 0),
(44, 'vnc', NULL, 'tcp', '0/0', '5800-5900', NULL, 0),
(55, 'unprivileged tcp', NULL, 'tcp', '0/0', '1024-65500', NULL, 0),
(76, 'ipsec', NULL, 'udp', '0/0', '500', NULL, 0),
(77, 'isakmp', NULL, 'udp', '0/0', '4500', NULL, 0),
(79, 'pop3s', NULL, 'tcp', '0/0', '995', NULL, 0),
(80, 'smtps', NULL, 'tcp', '0/0', '465,587', NULL, 0),
(81, 'imap', NULL, 'tcp', '0/0', '143', NULL, 0),
(82, 'imaps', NULL, 'tcp', '0/0', '993', NULL, 0),
(83, 'unprivileged udp', NULL, 'udp', '0/0', '1024-65000', NULL, 0),
(84, 'pptp', NULL, 'tcp', '0/0', '1723', NULL, 0),
(85, 'openvpn-udp', NULL, 'udp', '0/0', '1194', NULL, 0),
(90, 'dns_udp', NULL, 'udp', '0/0', '53', NULL, 0),
(91, 'dns_tcp', NULL, 'tcp', '0/0', '53', NULL, 0),
(94, 'squid', NULL, 'tcp', '0/0', '3128', NULL, 0),
(101, 'snmp', NULL, 'udp', '0/0', '161', NULL, 0),
(105, 'http_udp', NULL, 'udp', '0/0', '80', NULL, 0),
(106, 'https_udp', NULL, 'udp', '0/0', '443', NULL, 0),
(107, 'l2tp-ipsec', NULL, 'udp', '0/0', '1701,4500,500', NULL, 0),
(108, 'gre', NULL, 'gre', '0/0', NULL, NULL, 0)
ON CONFLICT (id) DO UPDATE SET
    name = EXCLUDED.name,
    comment = EXCLUDED.comment,
    proto = EXCLUDED.proto,
    dst = EXCLUDED.dst,
    dstport = EXCLUDED.dstport,
    srcport = EXCLUDED.srcport,
    type = EXCLUDED.type;

-- Filter group assignments
INSERT INTO Group_filters (id, group_id, filter_id, "order", action)
VALUES
(1, 2, 90, 1, 1),
(2, 2, 91, 2, 1),
(3, 2, 11, 3, 1),
(5, 2, 3, 4, 1),
(6, 2, 105, 5, 1),
(7, 2, 4, 6, 1),
(8, 2, 106, 7, 1),
(9, 2, 10, 8, 1),
(10, 2, 81, 9, 1),
(11, 2, 82, 10, 1),
(15, 2, 40, 11, 1),
(16, 2, 1, 12, 1),
(17, 2, 79, 13, 1),
(18, 2, 80, 14, 1),
(19, 1, 9, 1, 1)
ON CONFLICT (id) DO UPDATE SET
    group_id = EXCLUDED.group_id,
    filter_id = EXCLUDED.filter_id,
    "order" = EXCLUDED."order",
    action = EXCLUDED.action;

-- System version
INSERT INTO version (id, version)
VALUES (1, '2.9.1')
ON CONFLICT (id) DO UPDATE SET
    version = EXCLUDED.version;

-- Обновление последовательностей после импорта данных
SELECT setval(pg_get_serial_sequence('acl', 'id'), COALESCE((SELECT MAX(id) FROM acl), 0) + 1);
SELECT setval(pg_get_serial_sequence('building', 'id'), COALESCE((SELECT MAX(id) FROM building), 0) + 1);
SELECT setval(pg_get_serial_sequence('config_options', 'id'), COALESCE((SELECT MAX(id) FROM config_options), 0) + 1);
SELECT setval(pg_get_serial_sequence('config', 'id'), COALESCE((SELECT MAX(id) FROM config), 0) + 1);
SELECT setval(pg_get_serial_sequence('Customers', 'id'), COALESCE((SELECT MAX(id) FROM Customers), 0) + 1);
SELECT setval(pg_get_serial_sequence('device_models', 'id'), COALESCE((SELECT MAX(id) FROM device_models), 0) + 1);
SELECT setval(pg_get_serial_sequence('device_types', 'id'), COALESCE((SELECT MAX(id) FROM device_types), 0) + 1);
SELECT setval(pg_get_serial_sequence('filter_instances', 'id'), COALESCE((SELECT MAX(id) FROM filter_instances), 0) + 1);
SELECT setval(pg_get_serial_sequence('Group_list', 'id'), COALESCE((SELECT MAX(id) FROM Group_list), 0) + 1);
SELECT setval(pg_get_serial_sequence('OU', 'id'), COALESCE((SELECT MAX(id) FROM OU), 0) + 1);
SELECT setval(pg_get_serial_sequence('Queue_list', 'id'), COALESCE((SELECT MAX(id) FROM Queue_list), 0) + 1);
SELECT setval(pg_get_serial_sequence('subnets', 'id'), COALESCE((SELECT MAX(id) FROM subnets), 0) + 1);
SELECT setval(pg_get_serial_sequence('vendors', 'id'), COALESCE((SELECT MAX(id) FROM vendors), 0) + 1);
SELECT setval(pg_get_serial_sequence('Filter_list', 'id'), COALESCE((SELECT MAX(id) FROM Filter_list), 0) + 1);
SELECT setval(pg_get_serial_sequence('Group_filters', 'id'), COALESCE((SELECT MAX(id) FROM Group_filters), 0) + 1);

-- Информация о завершении импорта
DO $$
BEGIN
    RAISE NOTICE 'Импорт данных для русской локали завершен успешно!';
    RAISE NOTICE 'Всего импортировано таблиц: 16';
    RAISE NOTICE 'Всего импортировано записей:';
    RAISE NOTICE '  - acl: %', (SELECT COUNT(*) FROM acl);
    RAISE NOTICE '  - building: %', (SELECT COUNT(*) FROM building);
    RAISE NOTICE '  - config_options: %', (SELECT COUNT(*) FROM config_options);
    RAISE NOTICE '  - config: %', (SELECT COUNT(*) FROM config);
    RAISE NOTICE '  - Customers: %', (SELECT COUNT(*) FROM Customers);
    RAISE NOTICE '  - device_models: %', (SELECT COUNT(*) FROM device_models);
    RAISE NOTICE '  - device_types: %', (SELECT COUNT(*) FROM device_types);
    RAISE NOTICE '  - filter_instances: %', (SELECT COUNT(*) FROM filter_instances);
    RAISE NOTICE '  - Group_list: %', (SELECT COUNT(*) FROM Group_list);
    RAISE NOTICE '  - OU: %', (SELECT COUNT(*) FROM OU);
    RAISE NOTICE '  - Queue_list: %', (SELECT COUNT(*) FROM Queue_list);
    RAISE NOTICE '  - subnets: %', (SELECT COUNT(*) FROM subnets);
    RAISE NOTICE '  - vendors: %', (SELECT COUNT(*) FROM vendors);
    RAISE NOTICE '  - Filter_list: %', (SELECT COUNT(*) FROM Filter_list);
    RAISE NOTICE '  - Group_filters: %', (SELECT COUNT(*) FROM Group_filters);
    RAISE NOTICE '  - version: %', (SELECT COUNT(*) FROM version);
END $$;
