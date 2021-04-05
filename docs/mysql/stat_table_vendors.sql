
-- --------------------------------------------------------

--
-- Table structure for table `vendors`
--

CREATE TABLE `vendors` (
  `id` int(11) NOT NULL,
  `name` varchar(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Truncate table before insert `vendors`
--

TRUNCATE TABLE `vendors`;
--
-- Dumping data for table `vendors`
--

INSERT INTO `vendors` (`id`, `name`) VALUES
(1, 'Unknown'),
(2, 'Eltex'),
(3, 'Huawei'),
(4, 'Zyxel'),
(5, 'Raisecom'),
(6, 'SNR'),
(7, 'Dlink'),
(8, 'Allied Telesis'),
(9, 'Mikrotik'),
(10, 'NetGear'),
(11, 'Ubiquiti'),
(15, 'HP'),
(16, 'Cisco'),
(17, 'Maipu'),
(18, 'Asus'),
(19, 'Linux'),
(20, 'APC'),
(21, 'Panasonic'),
(22, 'OKI'),
(23, 'Samsung'),
(24, 'EATON'),
(25, 'Apple'),
(26, 'Epson'),
(27, 'Schneider'),
(28, 'Avaya'),
(29, 'Hikvision'),
(30, 'HW-group'),
(31, 'Netping'),
(32, 'Trassir'),
(33, 'QSC'),
(34, 'Projectiondesign'),
(35, 'Lenovo'),
(36, 'SIPOWER'),
(37, 'TP-Link');
