
--
-- Структура таблицы `device_models`
--

CREATE TABLE `device_models` (
  `id` int(11) NOT NULL,
  `model_name` varchar(200) DEFAULT NULL,
  `vendor_id` int(11) DEFAULT 1,
  `nagios_template` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


TRUNCATE TABLE `device_models`;
--
-- Dumping data for table `device_models`
--

INSERT INTO `device_models` (`id`, `model_name`, `vendor_id`, `nagios_template`) VALUES
(1, '2011LS', 9, NULL),
(2, '2011UAS-2HnD', 9, NULL),
(3, 'AT-8000S', 8, NULL),
(4, 'AT-8100S/48POE', 8, NULL),
(5, 'AT-9000/28', 8, NULL),
(6, 'AT-GS950/24', 8, NULL),
(7, 'CCR1009-7G-1C-1S+', 9, NULL),
(8, 'CCR1036-8G-2S+', 9, NULL),
(10, 'CRS317-1G-16S+', 9, NULL),
(11, 'CRS326-24S+2Q+', 9, NULL),
(12, 'CRS328-24P-4S+', 9, NULL),
(14, 'CRS328-4C-20S-4S+', 9, NULL),
(15, 'DGS-3120-48TC', 7, NULL),
(16, 'ES-2024', 4, NULL),
(17, 'ES-2024A', 4, NULL),
(18, 'ES-2108', 4, NULL),
(19, 'ES-2108-G', 4, NULL),
(20, 'ES-3124-4F', 4, NULL),
(21, 'GS110TP', 10, NULL),
(22, 'GS-4024', 4, NULL),
(23, 'HP 1910', 15, NULL),
(24, 'ISCOM2110A-MA', 5, NULL),
(25, 'ISCOM2110EA-MA', 5, NULL),
(26, 'ISCOM2126EA-MA', 5, NULL),
(27, 'ISCOM2128EA-MA', 5, NULL),
(28, 'Linux server', 1, NULL),
(29, 'MES2124F', 2, NULL),
(30, 'MES2124MB', 2, NULL),
(31, 'MES5248', 2, NULL),
(32, 'RB2011UAS', 9, NULL),
(33, 'RB3011UiAS', 9, NULL),
(34, 'RB960PGS', 9, NULL),
(35, 'RBD52G-5HacD2HnD', 9, NULL),
(36, 'S2940-8G-v2', 6, NULL),
(37, 'S2980G-24T', 6, NULL),
(38, 'S3750G-24S-E', 6, NULL),
(39, 'S5300-52P-LI-AC', 3, NULL),
(40, 'S5320-52X-PWR-SI-AC', 3, NULL),
(41, 'S5321-28X-SI-AC', 3, NULL),
(42, 'S5321-52X-SI-AC', 3, NULL),
(43, 'S6320-54C-EI-48S-AC', 3, NULL),
(44, 'SNR-S2980G-24T', 6, NULL),
(45, 'V1910-16G', 15, NULL),
(46, 'V1910-24G-PoE', 15, NULL),
(47, 'Windows server', 1, NULL),
(48, 'WS-C2960G-24TC-L', 16, NULL),
(49, 'WS-C3560G-24TS-S', 16, NULL),
(50, 'x210-16GT', 8, NULL),
(51, 'x210-24GT', 8, NULL),
(52, 'x610-24Ts/X', 8, NULL),
(53, 'x610-48Ts', 8, NULL),
(54, 'XGS-4728', 4, NULL),
(55, 'ZyWall 310', 4, NULL),
(56, 'APC Smart-UPS_3000', 20, 'ups.cfg'),
(57, 'APC Smart-UPS_5000', 20, 'ups.cfg'),
(58, 'Schneider Smart-UPS_3000', 27, 'ups.cfg'),
(59, 'SMG-1016M', 2, NULL),
(60, 'EATON 9PX 1500i RT 2U', 24, 'ups.cfg'),
(61, 'EATON 9PX3000i_RT_2U', 24, 'ups.cfg'),
(62, 'EATON 9PX_6000i', 24, 'ups.cfg'),
(63, 'EATON PW9130_3000', 24, 'ups.cfg'),
(64, 'EATON PW9130_3000VA-R', 24, 'ups.cfg'),
(65, 'Epson WF-5620 Series', 26, 'epson.cfg'),
(66, 'Epson WF-8590 Series', 26, 'epson.cfg'),
(67, 'HP Officejet-7000', 15, 'hp.cfg'),
(68, 'OKI C610', 22, 'oki.cfg'),
(69, 'OKI MB472', 22, 'oki.cfg'),
(70, 'OKI MB491', 22, 'oki.cfg'),
(71, 'OKI MC562', 22, 'oki.cfg'),
(72, 'OKI MC573', 22, 'oki.cfg'),
(73, 'OKI MC861', 22, 'oki.cfg'),
(74, 'Panasonic KX-MB2000RU', 21, 'panasonic.cfg'),
(75, 'PT-MZ10KE', 21, NULL),
(76, 'PT-VX41', 21, NULL),
(77, 'Rave 522AA', 33, NULL),
(78, 'DZ570E', 21, NULL),
(79, 'DZ6700', 21, NULL),
(80, 'Rcq80', 21, NULL),
(81, 'RZ12K', 21, NULL),
(82, 'RZ660', 21, NULL),
(83, 'RZ770', 21, NULL),
(84, 'RZ970', 21, NULL),
(85, 'XVR-5216', 32, NULL),
(86, 'HWg-STE', 30, NULL),
(87, 'Computer', 1, NULL),
(88, 'Mobile Phone', 1, NULL),
(89, 'Switch', 1, NULL),
(90, 'Projectiondesign F22', 34, NULL),
(91, 'MES2124P', 2, NULL),
(92, 'MES2124P rev.C', 2, NULL),
(93, 'MES2324B', 2, NULL),
(94, 'MES2324P', 2, NULL),
(95, 'MES-3528', 2, NULL),
(96, 'ME-C6524GS-8S', 16, NULL),
(97, 'SM3100-28TC', 17, NULL),
(98, 'SM3200-50T', 17, NULL),
(99, 'SM3200-52T', 17, NULL),
(100, 'APC6000XL', 20, NULL),
(101, 'MP RT 10K', 35, NULL),
(102, 'LaserJet P2035n', 15, NULL),
(103, 'MES3116F', 2, NULL),
(104, 'MES2124M', 2, NULL),
(105, 'RB750r2', 9, NULL),
(106, 'DES-1210-52', 7, NULL),
(107, 'DES-1210-52/ME', 7, NULL),
(108, 'GS-4012F', 4, NULL),
(109, 'MES2308', 2, NULL),
(110, 'NanoBeam M5 16', 11, NULL),
(111, 'MES2428B', 2, NULL),
(112, 'CRS109-8G-1S-2HnD', 9, NULL),
(113, 'MES2324', 2, NULL);


--
-- Индексы таблицы `device_models`
--
ALTER TABLE `device_models`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `device_models`
--
ALTER TABLE `device_models`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10000;
