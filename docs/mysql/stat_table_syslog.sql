
-- --------------------------------------------------------

--
-- Table structure for table `syslog`
--

CREATE TABLE `syslog` (
  `id` int(11) UNSIGNED NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `auth_id` int(11) NOT NULL DEFAULT 0,
  `customer` varchar(50) NOT NULL DEFAULT 'system',
  `message` text NOT NULL,
  `level` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
