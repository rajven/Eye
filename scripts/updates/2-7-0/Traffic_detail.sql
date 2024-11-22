ALTER TABLE `Traffic_detail` CHANGE `proto` `proto` TINYINT UNSIGNED NULL DEFAULT NULL;
ALTER TABLE `Traffic_detail` CHANGE `src_ip` `src_ip` INT UNSIGNED NOT NULL;
ALTER TABLE `Traffic_detail` CHANGE `dst_ip` `dst_ip` INT UNSIGNED NOT NULL;
ALTER TABLE `Traffic_detail` CHANGE `pkt` `pkt` INT UNSIGNED NOT NULL DEFAULT '0';
