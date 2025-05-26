ALTER TABLE `worklog` ADD `ip` VARCHAR(18) NOT NULL DEFAULT '127.0.0.1' AFTER `customer`;

ALTER TABLE `worklog` DROP INDEX `timestamp`;
ALTER TABLE `worklog` DROP INDEX `level`;
ALTER TABLE `worklog` DROP INDEX `customer`;
ALTER TABLE `worklog` DROP INDEX `auth_id`;

ALTER TABLE `worklog` ADD INDEX `idx_timestamp` (`level`, `timestamp`);
ALTER TABLE `worklog` ADD INDEX `idx_customer` (`customer`, `level`, `timestamp`);
ALTER TABLE `worklog` ADD INDEX `idx_auth_id` (`auth_id`, `level`, `timestamp`);
