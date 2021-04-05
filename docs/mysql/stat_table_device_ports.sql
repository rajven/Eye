
-- --------------------------------------------------------

--
-- Table structure for table `device_ports`
--

CREATE TABLE `device_ports` (
  `id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL DEFAULT 0,
  `snmp_index` int(11) NOT NULL DEFAULT 0,
  `port` int(11) NOT NULL DEFAULT 0,
  `comment` varchar(50) DEFAULT '',
  `target_port_id` int(11) NOT NULL DEFAULT 0,
  `auth_id` int(11) NOT NULL DEFAULT 0,
  `last_mac_count` int(11) NOT NULL DEFAULT 0,
  `uplink` tinyint(1) NOT NULL DEFAULT 0,
  `nagios` tinyint(1) NOT NULL DEFAULT 0,
  `skip` tinyint(1) NOT NULL DEFAULT 0,
  `vlan` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
