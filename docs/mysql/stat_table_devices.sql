
-- --------------------------------------------------------

--
-- Структура таблицы `devices`
--

CREATE TABLE `devices` (
  `id` int(11) NOT NULL,
  `device_model` varchar(50) DEFAULT NULL,
  `vendor_id` int(11) NOT NULL DEFAULT 1,
  `device_name` varchar(50) DEFAULT NULL,
  `building_id` int(11) NOT NULL DEFAULT 1,
  `ip` varchar(15) DEFAULT NULL,
  `mac` varchar(20) DEFAULT NULL,
  `port_count` int(11) NOT NULL DEFAULT 0,
  `wan_int` text DEFAULT NULL,
  `lan_int` text DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `snmp_version` tinyint(4) NOT NULL DEFAULT 0,
  `snmp3_user_rw` varchar(20) DEFAULT NULL,
  `snmp3_user_rw_password` varchar(20) DEFAULT NULL,
  `snmp3_user_ro` varchar(20) DEFAULT NULL,
  `snmp3_user_ro_password` varchar(20) DEFAULT NULL,
  `community` varchar(50) NOT NULL DEFAULT 'public',
  `rw_community` varchar(50) NOT NULL DEFAULT 'private',
  `fdb_snmp_index` tinyint(1) NOT NULL DEFAULT 0,
  `discovery` tinyint(1) NOT NULL DEFAULT 1,
  `is_router` tinyint(1) NOT NULL DEFAULT 0,
  `internet_gateway` tinyint(1) NOT NULL DEFAULT 0,
  `dhcp` tinyint(1) NOT NULL DEFAULT 0,
  `nagios` tinyint(1) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `nagios_status` varchar(10) NOT NULL DEFAULT 'UP',
  `queue_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `connected_user_only` tinyint(1) NOT NULL DEFAULT 1,
  `user_id` int(11) DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
