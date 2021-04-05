
-- --------------------------------------------------------

--
-- Table structure for table `building`
--

CREATE TABLE `building` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `comment` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Truncate table before insert `building`
--

TRUNCATE TABLE `building`;
--
-- Dumping data for table `building`
--

INSERT INTO `building` (`id`, `name`, `comment`) VALUES
(1, 'Earth', 'Somewhere');
