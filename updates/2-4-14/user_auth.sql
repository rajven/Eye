ALTER TABLE `User_auth` CHANGE `dhcp_changed` `dhcp_changed` INT(11) NOT NULL DEFAULT '0';
ALTER TABLE `User_auth` CHANGE `dns_name` `dns_name` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL;
ALTER TABLE `User_auth` DROP IF EXISTS `802_1x`;
ALTER TABLE `User_auth` DROP IF EXISTS `subnet_id`;
ALTER TABLE `User_auth` DROP IF EXISTS `dns_changed`;
