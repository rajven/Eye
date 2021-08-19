-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Хост: localhost
-- Время создания: Авг 19 2021 г., 10:46
-- Версия сервера: 10.3.28-MariaDB-log
-- Версия PHP: 7.3.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `stat`
--

--
-- Дамп данных таблицы `device_models`
--

REPLACE INTO `device_models` VALUES(1, '2011LS', 9, NULL);
REPLACE INTO `device_models` VALUES(2, '2011UAS-2HnD', 9, NULL);
REPLACE INTO `device_models` VALUES(3, 'AT-8000S', 8, NULL);
REPLACE INTO `device_models` VALUES(4, 'AT-8100S/48POE', 8, NULL);
REPLACE INTO `device_models` VALUES(5, 'AT-9000/28', 8, NULL);
REPLACE INTO `device_models` VALUES(6, 'AT-GS950/24', 8, NULL);
REPLACE INTO `device_models` VALUES(7, 'CCR1009-7G-1C-1S+', 9, NULL);
REPLACE INTO `device_models` VALUES(8, 'CCR1036-8G-2S+', 9, NULL);
REPLACE INTO `device_models` VALUES(10, 'CRS317-1G-16S+', 9, NULL);
REPLACE INTO `device_models` VALUES(11, 'CRS326-24S+2Q+', 9, NULL);
REPLACE INTO `device_models` VALUES(12, 'CRS328-24P-4S+', 9, NULL);
REPLACE INTO `device_models` VALUES(14, 'CRS328-4C-20S-4S+', 9, NULL);
REPLACE INTO `device_models` VALUES(15, 'DGS-3120-48TC', 7, NULL);
REPLACE INTO `device_models` VALUES(16, 'ES-2024', 4, NULL);
REPLACE INTO `device_models` VALUES(17, 'ES-2024A', 4, NULL);
REPLACE INTO `device_models` VALUES(18, 'ES-2108', 4, NULL);
REPLACE INTO `device_models` VALUES(19, 'ES-2108-G', 4, NULL);
REPLACE INTO `device_models` VALUES(20, 'ES-3124-4F', 4, NULL);
REPLACE INTO `device_models` VALUES(21, 'GS110TP', 10, NULL);
REPLACE INTO `device_models` VALUES(22, 'GS-4024', 4, NULL);
REPLACE INTO `device_models` VALUES(23, 'HP 1910', 15, NULL);
REPLACE INTO `device_models` VALUES(24, 'ISCOM2110A-MA', 5, NULL);
REPLACE INTO `device_models` VALUES(25, 'ISCOM2110EA-MA', 5, NULL);
REPLACE INTO `device_models` VALUES(26, 'ISCOM2126EA-MA', 5, NULL);
REPLACE INTO `device_models` VALUES(27, 'ISCOM2128EA-MA', 5, NULL);
REPLACE INTO `device_models` VALUES(28, 'Linux server', 1, NULL);
REPLACE INTO `device_models` VALUES(29, 'MES2124F', 2, NULL);
REPLACE INTO `device_models` VALUES(30, 'MES2124MB', 2, NULL);
REPLACE INTO `device_models` VALUES(31, 'MES5248', 2, NULL);
REPLACE INTO `device_models` VALUES(32, 'RB2011UAS', 9, NULL);
REPLACE INTO `device_models` VALUES(33, 'RB3011UiAS', 9, NULL);
REPLACE INTO `device_models` VALUES(34, 'RB960PGS', 9, NULL);
REPLACE INTO `device_models` VALUES(35, 'RBD52G-5HacD2HnD', 9, NULL);
REPLACE INTO `device_models` VALUES(36, 'S2940-8G-v2', 6, NULL);
REPLACE INTO `device_models` VALUES(37, 'S2980G-24T', 6, NULL);
REPLACE INTO `device_models` VALUES(38, 'S3750G-24S-E', 6, NULL);
REPLACE INTO `device_models` VALUES(39, 'S5300-52P-LI-AC', 3, NULL);
REPLACE INTO `device_models` VALUES(40, 'S5320-52X-PWR-SI-AC', 3, NULL);
REPLACE INTO `device_models` VALUES(41, 'S5321-28X-SI-AC', 3, NULL);
REPLACE INTO `device_models` VALUES(42, 'S5321-52X-SI-AC', 3, NULL);
REPLACE INTO `device_models` VALUES(43, 'S6320-54C-EI-48S-AC', 3, NULL);
REPLACE INTO `device_models` VALUES(44, 'SNR-S2980G-24T', 6, NULL);
REPLACE INTO `device_models` VALUES(45, 'V1910-16G', 15, NULL);
REPLACE INTO `device_models` VALUES(46, 'V1910-24G-PoE', 15, NULL);
REPLACE INTO `device_models` VALUES(47, 'Windows server', 1, NULL);
REPLACE INTO `device_models` VALUES(48, 'WS-C2960G-24TC-L', 16, NULL);
REPLACE INTO `device_models` VALUES(49, 'WS-C3560G-24TS-S', 16, NULL);
REPLACE INTO `device_models` VALUES(50, 'x210-16GT', 8, NULL);
REPLACE INTO `device_models` VALUES(51, 'x210-24GT', 8, NULL);
REPLACE INTO `device_models` VALUES(52, 'x610-24Ts/X', 8, NULL);
REPLACE INTO `device_models` VALUES(53, 'x610-48Ts', 8, NULL);
REPLACE INTO `device_models` VALUES(54, 'XGS-4728', 4, NULL);
REPLACE INTO `device_models` VALUES(55, 'ZyWall 310', 4, NULL);
REPLACE INTO `device_models` VALUES(56, 'APC Smart-UPS_3000', 20, 'ups.cfg');
REPLACE INTO `device_models` VALUES(57, 'APC Smart-UPS_5000', 20, 'ups.cfg');
REPLACE INTO `device_models` VALUES(58, 'Schneider Smart-UPS_3000', 27, 'ups.cfg');
REPLACE INTO `device_models` VALUES(59, 'SMG-1016M', 2, NULL);
REPLACE INTO `device_models` VALUES(60, 'EATON 9PX 1500i RT 2U', 24, 'ups.cfg');
REPLACE INTO `device_models` VALUES(61, 'EATON 9PX3000i_RT_2U', 24, 'ups.cfg');
REPLACE INTO `device_models` VALUES(62, 'EATON 9PX_6000i', 24, 'ups.cfg');
REPLACE INTO `device_models` VALUES(63, 'EATON PW9130_3000', 24, 'ups.cfg');
REPLACE INTO `device_models` VALUES(64, 'EATON PW9130_3000VA-R', 24, 'ups.cfg');
REPLACE INTO `device_models` VALUES(65, 'Epson WF-5620 Series', 26, 'epson.cfg');
REPLACE INTO `device_models` VALUES(66, 'Epson WF-8590 Series', 26, 'epson.cfg');
REPLACE INTO `device_models` VALUES(67, 'HP Officejet-7000', 15, 'hp.cfg');
REPLACE INTO `device_models` VALUES(68, 'OKI C610', 22, 'oki.cfg');
REPLACE INTO `device_models` VALUES(69, 'OKI MB472', 22, 'oki.cfg');
REPLACE INTO `device_models` VALUES(70, 'OKI MB491', 22, 'oki.cfg');
REPLACE INTO `device_models` VALUES(71, 'OKI MC562', 22, 'oki.cfg');
REPLACE INTO `device_models` VALUES(72, 'OKI MC573', 22, 'oki.cfg');
REPLACE INTO `device_models` VALUES(73, 'OKI MC861', 22, 'oki.cfg');
REPLACE INTO `device_models` VALUES(74, 'Panasonic KX-MB2000RU', 21, 'panasonic.cfg');
REPLACE INTO `device_models` VALUES(75, 'PT-MZ10KE', 21, NULL);
REPLACE INTO `device_models` VALUES(76, 'PT-VX41', 21, NULL);
REPLACE INTO `device_models` VALUES(77, 'Rave 522AA', 33, NULL);
REPLACE INTO `device_models` VALUES(78, 'DZ570E', 21, NULL);
REPLACE INTO `device_models` VALUES(79, 'DZ6700', 21, NULL);
REPLACE INTO `device_models` VALUES(80, 'Rcq80', 21, NULL);
REPLACE INTO `device_models` VALUES(81, 'RZ12K', 21, NULL);
REPLACE INTO `device_models` VALUES(82, 'RZ660', 21, NULL);
REPLACE INTO `device_models` VALUES(83, 'RZ770', 21, NULL);
REPLACE INTO `device_models` VALUES(84, 'RZ970', 21, NULL);
REPLACE INTO `device_models` VALUES(85, 'XVR-5216', 32, NULL);
REPLACE INTO `device_models` VALUES(86, 'HWg-STE', 30, NULL);
REPLACE INTO `device_models` VALUES(87, 'Computer', 1, NULL);
REPLACE INTO `device_models` VALUES(88, 'Mobile Phone', 1, NULL);
REPLACE INTO `device_models` VALUES(89, 'Switch', 1, NULL);
REPLACE INTO `device_models` VALUES(90, 'Projectiondesign F22', 34, NULL);
REPLACE INTO `device_models` VALUES(91, 'DS-I252', 36, NULL);
REPLACE INTO `device_models` VALUES(92, 'LTV-CNE-720-48', 37, NULL);
REPLACE INTO `device_models` VALUES(93, 'U-100', 38, NULL);
REPLACE INTO `device_models` VALUES(94, 'TAU-8', 2, NULL);
REPLACE INTO `device_models` VALUES(95, 'SIP-T21P E2', 39, NULL);
REPLACE INTO `device_models` VALUES(96, 'A510 IP', 40, NULL);
REPLACE INTO `device_models` VALUES(97, 'W60B', 39, NULL);
REPLACE INTO `device_models` VALUES(98, 'TAU-2M', 2, NULL);
REPLACE INTO `device_models` VALUES(99, 'PAP2T', 41, NULL);
REPLACE INTO `device_models` VALUES(100, 'VP-12', 2, NULL);
REPLACE INTO `device_models` VALUES(101, 'SIP-T23P', 39, NULL);
REPLACE INTO `device_models` VALUES(102, 'SPA-2102', 16, NULL);
REPLACE INTO `device_models` VALUES(103, 'RB760iGS', 9, NULL);

--
-- Дамп данных таблицы `vendors`
--

REPLACE INTO `vendors` VALUES(1, 'Unknown');
REPLACE INTO `vendors` VALUES(2, 'Eltex');
REPLACE INTO `vendors` VALUES(3, 'Huawei');
REPLACE INTO `vendors` VALUES(4, 'Zyxel');
REPLACE INTO `vendors` VALUES(5, 'Raisecom');
REPLACE INTO `vendors` VALUES(6, 'SNR');
REPLACE INTO `vendors` VALUES(7, 'Dlink');
REPLACE INTO `vendors` VALUES(8, 'Allied Telesis');
REPLACE INTO `vendors` VALUES(9, 'Mikrotik');
REPLACE INTO `vendors` VALUES(10, 'NetGear');
REPLACE INTO `vendors` VALUES(11, 'Ubiquiti');
REPLACE INTO `vendors` VALUES(15, 'HP');
REPLACE INTO `vendors` VALUES(16, 'Cisco');
REPLACE INTO `vendors` VALUES(17, 'Maipu');
REPLACE INTO `vendors` VALUES(18, 'Asus');
REPLACE INTO `vendors` VALUES(19, 'Linux');
REPLACE INTO `vendors` VALUES(20, 'APC');
REPLACE INTO `vendors` VALUES(21, 'Schneider');
REPLACE INTO `vendors` VALUES(33, 'QSC');
REPLACE INTO `vendors` VALUES(34, 'Projectiondesign');
REPLACE INTO `vendors` VALUES(35, 'Lenovo');
REPLACE INTO `vendors` VALUES(36, 'HiWatch');
REPLACE INTO `vendors` VALUES(37, 'LTV');
REPLACE INTO `vendors` VALUES(38, 'Yeastar');
REPLACE INTO `vendors` VALUES(39, 'Yealink');
REPLACE INTO `vendors` VALUES(40, 'Gigaset');
REPLACE INTO `vendors` VALUES(41, 'Linksys');
REPLACE INTO `vendors` VALUES(42, 'Samsung');
REPLACE INTO `vendors` VALUES(43, 'Supermicro');
REPLACE INTO `vendors` VALUES(44, 'RDP');
REPLACE INTO `vendors` VALUES(45, 'SANYO');
REPLACE INTO `vendors` VALUES(46, 'Extreme');
REPLACE INTO `vendors` VALUES(47, 'Intel');
REPLACE INTO `vendors` VALUES(48, 'Micron');
REPLACE INTO `vendors` VALUES(49, 'Gigabyte');
REPLACE INTO `vendors` VALUES(50, 'Acer');
REPLACE INTO `vendors` VALUES(51, 'Seagate');
REPLACE INTO `vendors` VALUES(52, 'SanDisk');
REPLACE INTO `vendors` VALUES(53, 'Toshiba');
REPLACE INTO `vendors` VALUES(54, 'Kingston');
REPLACE INTO `vendors` VALUES(55, 'AddPac');
REPLACE INTO `vendors` VALUES(56, 'Devline');
REPLACE INTO `vendors` VALUES(57, 'Canon');
REPLACE INTO `vendors` VALUES(58, 'Brother');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
