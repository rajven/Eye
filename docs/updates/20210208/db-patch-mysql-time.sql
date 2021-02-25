ALTER TABLE `User_auth` ADD `changed_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `changed`, ADD INDEX `changed_time` (`changed_time`);
UPDATE User_auth set changed_time=last_found;

