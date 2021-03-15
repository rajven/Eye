
-- --------------------------------------------------------

--
-- Структура таблицы `Traffic_detail`
--

CREATE TABLE `Traffic_detail` (
  `id` bigint(20) NOT NULL,
  `auth_id` int(11) DEFAULT NULL,
  `router_id` int(11) NOT NULL DEFAULT 0,
  `timestamp` timestamp NULL DEFAULT NULL,
  `proto` int(11) DEFAULT NULL,
  `src_ip` int(10) UNSIGNED NOT NULL,
  `dst_ip` int(10) UNSIGNED NOT NULL,
  `src_port` smallint(5) UNSIGNED NOT NULL,
  `dst_port` smallint(5) UNSIGNED NOT NULL,
  `bytes` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
