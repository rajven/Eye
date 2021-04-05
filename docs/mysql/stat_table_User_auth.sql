
-- --------------------------------------------------------

--
-- Table structure for table `User_auth`
--

CREATE TABLE `User_auth` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `ip` varchar(18) NOT NULL DEFAULT '',
  `ip_int` int(10) UNSIGNED NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `dhcp` tinyint(1) NOT NULL DEFAULT 1,
  `filter_group_id` tinyint(1) NOT NULL DEFAULT 0,
  `deleted` tinyint(4) NOT NULL DEFAULT 0,
  `comments` text DEFAULT NULL,
  `dns_name` varchar(60) DEFAULT NULL,
  `WikiName` varchar(250) DEFAULT NULL,
  `dhcp_acl` text DEFAULT NULL,
  `queue_id` int(11) NOT NULL DEFAULT 1,
  `mac` varchar(20) DEFAULT NULL,
  `dhcp_action` varchar(10) DEFAULT NULL,
  `dhcp_time` datetime DEFAULT NULL,
  `dhcp_hostname` varchar(60) DEFAULT NULL,
  `last_found` datetime DEFAULT NULL,
  `blocked` tinyint(1) NOT NULL DEFAULT 0,
  `day_quota` int(11) NOT NULL DEFAULT 0,
  `month_quota` int(11) NOT NULL DEFAULT 0,
  `device_model_id` int(11) DEFAULT 87,
  `firmware` varchar(100) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `clientid` varchar(50) DEFAULT NULL,
  `nagios` tinyint(1) NOT NULL DEFAULT 0,
  `nagios_status` varchar(10) DEFAULT NULL,
  `nagios_handler` varchar(50) DEFAULT NULL,
  `link_check` tinyint(1) NOT NULL DEFAULT 0,
  `changed` tinyint(1) NOT NULL DEFAULT 0,
  `changed_time` datetime NOT NULL DEFAULT current_timestamp(),
  `save_traf` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
