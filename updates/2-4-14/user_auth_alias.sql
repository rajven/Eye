ALTER TABLE `User_auth_alias` ADD `dns_changed` INT NOT NULL DEFAULT '0' AFTER `timestamp`;
ALTER TABLE `User_auth_alias` ADD `old_alias` VARCHAR(100) NULL DEFAULT NULL AFTER `alias`;
ALTER TABLE `User_auth_alias` ADD `deleted` INT NOT NULL DEFAULT '0' AFTER `changed`;
