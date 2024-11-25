ALTER TABLE `device_l3_interfaces` ADD `snmpin` INT NULL DEFAULT NULL AFTER `device_id`;
ALTER TABLE `device_l3_interfaces` ADD `deleted` BOOLEAN NOT NULL DEFAULT FALSE AFTER `name`;
