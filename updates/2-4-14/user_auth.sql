ALTER TABLE `User_auth` ADD `dns_changed` INT NOT NULL DEFAULT '0' AFTER `dhcp_changed`;
ALTER TABLE `User_auth` CHANGE `dhcp_changed` `dhcp_changed` INT(11) NOT NULL DEFAULT '0';
ALTER TABLE `User_auth_alias` ADD `changed` INT NOT NULL DEFAULT '0' AFTER `timestamp`;
