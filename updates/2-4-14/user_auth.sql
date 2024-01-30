ALTER TABLE `User_auth` CHANGE `dhcp_changed` `dhcp_changed` INT(11) NOT NULL DEFAULT '0';
ALTER TABLE `User_auth` CHANGE `dns_name` `dns_name` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL;
DELETE FROM `User_auth_alias` WHERE `auth_id` in (SELECT `id` FROM `User_auth` WHERE `deleted`=1);
DELETE FROM `User_auth_alias` WHERE `auth_id` in (SELECT `id` FROM `User_auth` WHERE `dns_name`='' or `dns_name` IS NULL);
