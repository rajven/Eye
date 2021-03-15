
-- --------------------------------------------------------

--
-- Структура таблицы `device_ports`
--

CREATE TABLE `device_ports` (
  `id` int(11) NOT NULL,
  `device_id` int(11) DEFAULT NULL,
  `snmp_index` int(11) DEFAULT NULL,
  `port` int(11) DEFAULT NULL,
  `comment` varchar(50) DEFAULT NULL,
  `target_port_id` int(11) NOT NULL DEFAULT 0,
  `auth_id` int(11) DEFAULT NULL,
  `last_mac_count` int(11) DEFAULT 0,
  `uplink` tinyint(1) NOT NULL DEFAULT 0,
  `nagios` tinyint(1) NOT NULL DEFAULT 0,
  `skip` tinyint(1) NOT NULL DEFAULT 0,
  `vlan` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
