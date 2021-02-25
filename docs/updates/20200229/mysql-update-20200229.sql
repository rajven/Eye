ALTER TABLE `config_options` ADD `description` TEXT NULL DEFAULT NULL AFTER `option_name`;

INSERT INTO `config_options` (`id`,`option_name`,`description`,`uniq`,`type`) VALUES('45','ignore update dhcp event','Не писать события обновления ip-адреса dhcp-сервера. ','1','text');
INSERT INTO `config_options` (`id`, `option_name`,`description`,`uniq`, `type`) VALUES ('46', 'update hostname from dhcp','Обновлять имя хоста в DNS при получении адреса по DHCP','1', 'bool');

UPDATE `config_options` SET `id` = 1,`option_name` = 'KB',`description` = 'Число байт в килобайте',`uniq` = 1,`type` = 'int' WHERE `config_options`.`id` = 1;
UPDATE `config_options` SET `id` = 3,`option_name` = 'dns server',`description` = 'ip-адрес DNS-сервера',`uniq` = 1,`type` = 'text' WHERE `config_options`.`id` = 3;
UPDATE `config_options` SET `id` = 5,`option_name` = 'dhcp server',`description` = 'ip-адрес DHCP-сервера',`uniq` = 1,`type` = 'text' WHERE `config_options`.`id` = 5;
UPDATE `config_options` SET `id` = 9,`option_name` = 'default snmp version',`description` = 'Версия snmp по умолчанию. В настоящий момент поддерживаются 1 и 2. Поддержка версии 3 в разработке.',`uniq` = 1,`type` = 'int' WHERE `config_options`.`id` = 9;
UPDATE `config_options` SET `id` = 11,`option_name` = 'default snmp community',`description` = 'Read snmp community по умолчанию',`uniq` = 1,`type` = 'text' WHERE `config_options`.`id` = 11;
UPDATE `config_options` SET `id` = 13,`option_name` = 'office net',`description` = 'Сеть, принадлежащя организации в формате IP/CIDR, например 192.168.1.0/24',`uniq` = 0,`type` = 'text' WHERE `config_options`.`id` = 13;
UPDATE `config_options` SET `id` = 17,`option_name` = 'mac discavery',`description` = 'Выполнять опрос mac-таблицы коммутаторов при сканировании сети. Если опция отключена будет нельзя сопоставить ip-адрес порту коммутатора',`uniq` = 1,`type` = 'bool' WHERE `config_options`.`id` = 17;
UPDATE `config_options` SET `id` = 19,`option_name` = 'arp discavery',`description` = 'Выполнять сканирование arp-таблицы роутеров при сканировании сети. Если опция отключена сопоставление mac-адреса ip-адресу будет возможно только из логов dhcp-сервера.',`uniq` = 1,`type` = 'bool' WHERE `config_options`.`id` = 19;
UPDATE `config_options` SET `id` = 20,`option_name` = 'default user id',`description` = 'Id записи пользователя, в которую помещаются все вновь создаваемые при сканировании ip-адреса. Здесь же создаются записи для адресов, которые выдаёт dhcp-сервер',`uniq` = 1,`type` = 'int' WHERE `config_options`.`id` = 20;
UPDATE `config_options` SET `id` = 21,`option_name` = 'admin email',`description` = 'E-mail администратора',`uniq` = 1,`type` = 'text' WHERE `config_options`.`id` = 21;
UPDATE `config_options` SET `id` = 22,`option_name` = 'add user from netflow',`description` = 'Создавать ли новые записи для неизвестных адресов из анализа трафика netflow. Обычно выключается, поскольку может создавать много фэйковых адресов - например при пинге подсети, когда пакеты идут на все, даже не существующие адреса. Имеет смысл включать только на маршрутизаторах во внешние сети.',`uniq` = 1,`type` = 'bool' WHERE `config_options`.`id` = 22;
UPDATE `config_options` SET `id` = 23,`option_name` = 'save traffic detail',`description` = 'Сохранять ли детализацию трафика из netflow по ip-адресам пользователей',`uniq` = 1,`type` = 'bool' WHERE `config_options`.`id` = 23;
UPDATE `config_options` SET `id` = 26,`option_name` = 'history detail traffic',`description` = 'Глубина хранения детализации в сутках. Установка значения больше 3-7 дней приведёт к разрастанию базы данных и увеличит время отображения детализации в интерфейсе администратора',`uniq` = 1,`type` = 'int' WHERE `config_options`.`id` = 26;
UPDATE `config_options` SET `id` = 27,`option_name` = 'history dhcp lease',`description` = 'Глубина хранения логов dhcp-сервера',`uniq` = 1,`type` = 'int' WHERE `config_options`.`id` = 27;
UPDATE `config_options` SET `id` = 28,`option_name` = 'router_login',`description` = 'Логин для входа на маршрутизаторы Mikrotik для управления dhcp-сервером и контролем доступа',`uniq` = 1,`type` = 'text' WHERE `config_options`.`id` = 28;
UPDATE `config_options` SET `id` = 29,`option_name` = 'router_password',`description` = 'Пароль для входа на маршрутизаторы Mikrotik для управления dhcp-сервером и контролем доступа',`uniq` = 1,`type` = 'text' WHERE `config_options`.`id` = 29;
UPDATE `config_options` SET `id` = 30,`option_name` = 'router_port',`description` = 'Порт telnet маршрутизатора',`uniq` = 1,`type` = 'int' WHERE `config_options`.`id` = 30;
UPDATE `config_options` SET `id` = 32,`option_name` = 'org name',`description` = 'Название организации',`uniq` = 1,`type` = 'text' WHERE `config_options`.`id` = 32;
UPDATE `config_options` SET `id` = 33,`option_name` = 'office domain',`description` = 'Домен организации',`uniq` = 1,`type` = 'text' WHERE `config_options`.`id` = 33;
UPDATE `config_options` SET `id` = 34,`option_name` = 'debug',`description` = 'Включить отладку',`uniq` = 1,`type` = 'bool' WHERE `config_options`.`id` = 34;
UPDATE `config_options` SET `id` = 35,`option_name` = 'connections history, days',`description` = 'Время хранения истории мест подключения ip-адресов',`uniq` = 1,`type` = 'int' WHERE `config_options`.`id` = 35;
UPDATE `config_options` SET `id` = 36,`option_name` = 'clear user auth',`description` = 'Удалять ли старые записи ip-адресов',`uniq` = 1,`type` = 'bool' WHERE `config_options`.`id` = 36;
UPDATE `config_options` SET `id` = 37,`option_name` = 'refresh access lists',`description` = 'Расположение скрипта управления контролем доступа для роутеров Mikrotik',`uniq` = 1,`type` = 'text' WHERE `config_options`.`id` = 37;
UPDATE `config_options` SET `id` = 38,`option_name` = 'regenerate dhcp cconfig',`description` = 'Расположение скрипта управления конфигурацией dhcp-серверами',`uniq` = 1,`type` = 'text' WHERE `config_options`.`id` = 38;
UPDATE `config_options` SET `id` = 39,`option_name` = 'regenerate dns cconfig',`description` = 'Расположение скрипта управления dns-сервером',`uniq` = 1,`type` = 'text' WHERE `config_options`.`id` = 39;
UPDATE `config_options` SET `id` = 40,`option_name` = 'regenerate nagios cconfig',`description` = 'Расположение скрипта конфигурирования Nagios',`uniq` = 1,`type` = 'text' WHERE `config_options`.`id` = 40;
UPDATE `config_options` SET `id` = 41,`option_name` = 'discavery network',`description` = 'Расположение скрипта сканирования сети',`uniq` = 1,`type` = 'text' WHERE `config_options`.`id` = 41;
UPDATE `config_options` SET `id` = 42,`option_name` = 'hotspot_network',`description` = 'Сеть, закреплённая за хот-спотом, формат тот же, что и для обычной сети.',`uniq` = 0,`type` = 'text' WHERE `config_options`.`id` = 42;
UPDATE `config_options` SET `id` = 43,`option_name` = 'hotspot_user_id',`description` = 'Id запись юзера, в которой создаются ip-адреса, выдаваемые хот-спотом. По умолчанию, используется та же запись, что и для обычных пользователей, но лучше завести отдельную учётку, чтобы не мешать временные записи и постоянные записи компьютеров организации',`uniq` = 1,`type` = 'int' WHERE `config_options`.`id` = 43;
UPDATE `config_options` SET `id` = 44,`option_name` = 'Ignore hotspot dhcp log',`description` = 'Не писать лог событий dhcp-сервера хотспота. Имеет смысл вклчючать, поскольку время аренды в хот-споте как правило маленькое и в записях хот-спота становятся незаметны логи обычных пользователей',`uniq` = 1,`type` = 'bool' WHERE `config_options`.`id` = 44;

CREATE TABLE `subnets` (
  `id` int(11) NOT NULL,
  `subnet` varchar(18) DEFAULT NULL,
  `ip_int_start` bigint(20) NOT NULL,
  `ip_int_stop` bigint(20) NOT NULL,
  `office` tinyint(1) NOT NULL DEFAULT 1,
  `hotspot` tinyint(1) NOT NULL DEFAULT 0,
  `dhcp_update_hostname` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE `subnets` ADD PRIMARY KEY (`id`);
