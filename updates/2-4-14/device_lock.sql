ALTER TABLE `devices` ADD `discovery_locked` BOOLEAN NOT NULL DEFAULT FALSE AFTER `deleted`, ADD `locked_timestamp` DATETIME NULL DEFAULT NULL AFTER `discovery_locked`;
