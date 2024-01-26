ALTER TABLE `devices` ADD `discovery_locked` BOOLEAN NOT NULL DEFAULT FALSE AFTER `deleted`, ADD `locked_timestamp` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER `discovery_locked`;

