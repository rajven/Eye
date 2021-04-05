
-- --------------------------------------------------------

--
-- Table structure for table `subnets`
--

CREATE TABLE `subnets` (
  `id` int(11) NOT NULL,
  `subnet` varchar(18) DEFAULT NULL,
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
  `comment` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `subnets`
--

INSERT INTO `subnets` (`id`, `subnet`, `ip_int_start`, `ip_int_stop`, `dhcp_start`, `dhcp_stop`, `dhcp_lease_time`, `gateway`, `office`, `hotspot`, `vpn`, `free`, `dhcp`, `static`, `dhcp_update_hostname`, `discovery`, `comment`) VALUES
(1, '192.168.0.0/16', 3232235520, 3232301055, 0, 0, 480, 0, 0, 0, 0, 1, 0, 0, 0, 0, 'Не считать трафик'),
(2, '10.0.0.0/8', 167772160, 184549375, 0, 0, 480, 0, 0, 0, 0, 1, 0, 0, 0, 0, 'Не считать трафик'),
(3, '172.16.0.0/12', 2886729728, 2887778303, 0, 0, 480, 0, 0, 0, 0, 1, 0, 0, 0, 0, 'Не считать трафик');
