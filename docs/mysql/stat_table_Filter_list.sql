
-- --------------------------------------------------------

--
-- Структура таблицы `Filter_list`
--

CREATE TABLE `Filter_list` (
  `id` int(11) NOT NULL,
  `name` varchar(20) NOT NULL DEFAULT '',
  `proto` varchar(10) DEFAULT NULL,
  `dst` text DEFAULT NULL,
  `dstport` varchar(20) DEFAULT NULL,
  `action` int(11) NOT NULL DEFAULT 0,
  `type` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Дамп данных таблицы `Filter_list`
--

INSERT INTO `Filter_list` (`id`, `name`, `proto`, `dst`, `dstport`, `action`, `type`) VALUES
(1, 'pop3', 'tcp', '0/0', '110', 1, 0),
(3, 'http', 'tcp', '0/0', '80', 1, 0),
(4, 'https', 'tcp', '0/0', '443', 1, 0),
(5, 'icq', 'tcp', '0/0', '5190', 1, 0),
(6, 'jabber', 'tcp', '0/0', '5222', 1, 0),
(9, 'allow_all', 'all', '0/0', '0', 1, 0),
(10, 'icmp', 'icmp', '0/0', '0', 1, 0),
(11, 'ftp', 'tcp', '0/0', '20-21', 1, 0),
(14, 'radmin', 'tcp', '0/0', '4899', 1, 0),
(15, 'telnet', 'tcp', '0/0', '23', 1, 0),
(16, 'ssh', 'tcp', '0/0', '22', 1, 0),
(23, 'webmoney', 'tcp', '0/0', '2802', 1, 0),
(24, 'skype', 'udp', '0/0', '39082', 1, 0),
(26, 'bank zenit', 'tcp', '0/0', '1352', 1, 0),
(28, 'smtp', 'tcp', '0/0', '25', 1, 0),
(32, 'tsclient', 'tcp', '0/0', '3389', 1, 0),
(34, 'sberbank', 'udp', '0/0', '87', 1, 0),
(40, 'ntp', 'udp', '0/0', '123', 1, 0),
(44, 'vnc', 'tcp', '0/0', '5800-5900', 1, 0),
(55, 'unprivileged tcp', 'tcp', '0/0', '1024-65500', 1, 0),
(76, 'ipsec', 'udp', '0/0', '500', 1, 0),
(77, 'isakmp', 'udp', '0/0', '4500', 1, 0),
(79, 'pop3s', 'tcp', '0/0', '995', 1, 0),
(80, 'smtps', 'tcp', '0/0', '465,587', 1, 0),
(81, 'imap', 'tcp', '0/0', '143', 1, 0),
(82, 'imaps', 'tcp', '0/0', '993', 1, 0),
(83, 'unprivileged udp', 'udp', '0/0', '1024-65000', 1, 0),
(84, 'pptp', 'tcp', '0/0', '1723', 1, 0),
(85, 'openvpn-udp', 'udp', '0/0', '1194', 1, 0),
(88, 'pos-server', 'tcp', '0/0', '21101', 1, 0),
(89, 'ofdp.platformaofd.ru', 'all', '185.170.204.91', '0', 1, 0),
(90, 'dns_udp', 'udp', '0/0', '53', 1, 0),
(91, 'dns_tcp', 'tcp', '0/0', '53', 1, 0),
(92, 'sber-online', 'tcp', '0/0', '4477', 1, 0),
(93, 'devline.tv', 'tcp', '0/0', '843', 1, 0),
(94, 'squid', 'tcp', '0/0', '3128', 1, 0),
(95, 'fe.ls.tv.ttk.ru', 'all', '37.18.127.6', '0', 1, 0),
(96, 'cvs', 'tcp', '0/0', '5000-5012', 1, 0),
(97, 'devline', 'tcp', '0/0', '9780-9786,9877', 1, 0),
(98, 'atomsbt-lk', 'tcp', '0/0', '9080', 1, 0),
(99, 'raif-tcp-1403-1405', 'tcp', '0/0', '1403-1405', 1, 0),
(101, 'snmp', 'udp', '0/0', '161', 1, 0),
(104, 'sberbank2', 'tcp', '0/0', '9443', 1, 0);
