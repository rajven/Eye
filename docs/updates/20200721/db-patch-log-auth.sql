ALTER TABLE `syslog` ADD `auth_id` INT NOT NULL DEFAULT '0' AFTER `timestamp`, ADD INDEX `auth_idx` (`auth_id`);
