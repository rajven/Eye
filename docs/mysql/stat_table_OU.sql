
-- --------------------------------------------------------

--
-- Table structure for table `OU`
--

CREATE TABLE `OU` (
  `id` int(11) NOT NULL,
  `ou_name` varchar(40) NOT NULL DEFAULT '',
  `nagios_dir` varchar(255) DEFAULT NULL,
  `nagios_host_use` varchar(50) DEFAULT NULL,
  `nagios_ping` tinyint(1) NOT NULL DEFAULT 1,
  `nagios_default_service` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `OU`
--

INSERT INTO `OU` (`id`, `ou_name`, `nagios_dir`, `nagios_host_use`, `nagios_ping`, `nagios_default_service`) VALUES
(0, '!Всё', 'any', 'generic-host', 1, NULL),
(1, 'Сервера', NULL, NULL, 1, NULL),
(2, 'Администраторы', NULL, NULL, 1, NULL),
(4, 'VOIP', 'voip', 'voip', 1, NULL),
(5, 'IPCAM', 'videocam', 'ip-cam', 0, NULL),
(6, 'Принтеры', 'printers', 'printers', 1, 'printer-service'),
(8, 'UPS', 'ups', 'ups', 1, NULL),
(9, 'Охрана', 'security', 'security', 1, NULL),
(10, 'Роутеры', 'routers', 'routers', 1, NULL),
(12, 'WiFi AP', 'ap', 'ap', 1, NULL),
(16, 'Пользователи', NULL, NULL, 1, NULL),
(17, 'Свичи', NULL, NULL, 1, NULL);
