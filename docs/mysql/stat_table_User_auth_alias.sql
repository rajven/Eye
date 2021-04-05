
-- --------------------------------------------------------

--
-- Table structure for table `User_auth_alias`
--

CREATE TABLE `User_auth_alias` (
  `id` int(11) NOT NULL,
  `auth_id` int(11) NOT NULL,
  `alias` varchar(100) DEFAULT NULL,
  `description` varchar(100) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
