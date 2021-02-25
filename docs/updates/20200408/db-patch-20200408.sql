ALTER TABLE `subnets` ADD `comment` TEXT NULL AFTER `discovery`;
ALTER TABLE `subnets` ADD `vpn` BOOLEAN NOT NULL DEFAULT FALSE AFTER `hotspot`;
ALTER TABLE `subnets` ADD `free` BOOLEAN NOT NULL DEFAULT FALSE AFTER `vpn`;
ALTER TABLE `subnets` CHANGE `comment` `comment` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;
INSERT INTO `subnets` (`subnet`, `ip_int_start`, `ip_int_stop`, `office`, `hotspot`, `vpn`, `free`, `dhcp_update_hostname`, `discovery`, `comment`) VALUES
('192.168.0.0/16', 3232235520, 3232301055, 0, 0, 0, 1, 0, 0, 'Не считать трафик'),
('10.0.0.0/8', 167772160, 184549375, 0, 0, 0, 1, 0, 0, 'Не считать трафик'),
('172.16.0.0/12', 2886729728, 2887778303, 0, 0, 0, 1, 0, 0, 'Не считать трафик');
