CREATE TABLE `version` (`id` int(11) NOT NULL DEFAULT 1,`version` varchar(10) NOT NULL DEFAULT '2.4.14') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `version`  ADD UNIQUE KEY `id` (`id`);
REPLACE INTO `version` (`version`) VALUES ('2.4.14');
