
-- --------------------------------------------------------

--
-- Table structure for table `User_stats`
--

CREATE TABLE `User_stats` (
  `id` int(11) NOT NULL,
  `router_id` int(11) DEFAULT 0,
  `auth_id` int(11) NOT NULL DEFAULT 0,
  `timestamp` datetime NOT NULL,
  `byte_in` bigint(20) NOT NULL DEFAULT 0,
  `byte_out` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
