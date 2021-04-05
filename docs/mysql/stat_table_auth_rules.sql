
-- --------------------------------------------------------

--
-- Table structure for table `auth_rules`
--

CREATE TABLE `auth_rules` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` int(11) NOT NULL,
  `rule` varchar(40) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
