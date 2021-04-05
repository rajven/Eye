
-- --------------------------------------------------------

--
-- Table structure for table `Unknown_mac`
--

CREATE TABLE `Unknown_mac` (
  `id` int(11) NOT NULL,
  `mac` varchar(12) DEFAULT NULL,
  `port_id` int(11) DEFAULT NULL,
  `device_id` int(11) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
