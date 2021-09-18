ALTER TABLE `device_ports` ADD `ifName` VARCHAR(40) NULL AFTER `port`;
ALTER TABLE `device_ports` ADD `port_name` VARCHAR(40) NULL AFTER `ifName`;
