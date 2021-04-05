
-- --------------------------------------------------------

--
-- Table structure for table `device_types`
--

CREATE TABLE `device_types` (
  `id` int(11) NOT NULL,
  `name` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Truncate table before insert `device_types`
--

TRUNCATE TABLE `device_types`;
--
-- Dumping data for table `device_types`
--

INSERT INTO `device_types` (`id`, `name`) VALUES
(1, 'Свич'),
(2, 'Роутер'),
(3, 'Сервер'),
(4, 'Точка доступа');
