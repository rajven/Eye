
-- --------------------------------------------------------

--
-- Table structure for table `User_list`
--

CREATE TABLE `User_list` (
  `id` int(10) UNSIGNED NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `login` varchar(40) NOT NULL DEFAULT '',
  `fio` varchar(60) NOT NULL DEFAULT '',
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `blocked` tinyint(1) NOT NULL DEFAULT 0,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  `ou_id` int(11) NOT NULL DEFAULT 0,
  `filter_group_id` int(11) NOT NULL DEFAULT 0,
  `queue_id` int(11) NOT NULL DEFAULT 0,
  `day_quota` int(11) NOT NULL DEFAULT 0,
  `month_quota` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `User_list`
--

INSERT INTO `User_list` (`id`, `timestamp`, `login`, `fio`, `enabled`, `blocked`, `deleted`, `ou_id`, `filter_group_id`, `queue_id`, `day_quota`, `month_quota`) VALUES
(1, '2017-11-02 10:54:36', 'default', '', 0, 0, 0, 32, 0, 0, 0, 0);
