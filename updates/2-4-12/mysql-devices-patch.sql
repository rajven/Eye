ALTER TABLE `connections` ADD `last_found` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `auth_id`;
UPDATE `connections` as C set last_found=(SELECT last_found FROM User_auth AS U WHERE U.last_found>0 and U.id=C.`auth_id`);
ALTER TABLE `devices` ADD `login` VARCHAR(50) NULL DEFAULT NULL AFTER `ip`, ADD `password` VARCHAR(255) NULL DEFAULT NULL AFTER `login`, ADD `protocol` INT NOT NULL DEFAULT '1' AFTER `password`, ADD `control_port` INT NOT NULL DEFAULT '23' AFTER `protocol`;
INSERT INTO `device_types` (`id`, `name`) VALUES ('6', 'Роутер');
UPDATE `device_types` SET `id` = '0' WHERE `device_types`.`id` = 6;
ALTER TABLE `device_types` CHANGE `name` `name.russian` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL;
ALTER TABLE `device_types` ADD `name.english` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER `name.russian`;
UPDATE `device_types` SET `name.russian` = 'Шлюз' WHERE `device_types`.`id` = 2;
UPDATE `device_types` SET `name.english` = 'Router' WHERE `device_types`.`id` = 0;
UPDATE `device_types` SET `name.english` = 'Switch' WHERE `device_types`.`id` = 1;
UPDATE `device_types` SET `name.english` = 'Gateway' WHERE `device_types`.`id` = 2;
UPDATE `device_types` SET `name.english` = 'Server' WHERE `device_types`.`id` = 3;
UPDATE `device_types` SET `name.english` = 'WiFi Access Point' WHERE `device_types`.`id` = 4;
UPDATE `device_types` SET `name.english` = 'Network device' WHERE `device_types`.`id` = 5;
UPDATE `config_options` SET `description.russian` = 'Логин для входа на сетевые устройства по умолчанию' WHERE `config_options`.`id` = 28;
UPDATE `config_options` SET `description.russian` = 'Пароль по умолчанию на сетевые устройства' WHERE `config_options`.`id` = 29;
UPDATE `config_options` SET `description.russian` = 'Порт ssh по умолчанию' WHERE `config_options`.`id` = 30;
UPDATE `config_options` SET `description.english` = 'Default login for network devices' WHERE `config_options`.`id` = 28;
UPDATE `config_options` SET `description.english` = 'Default password for network devices' WHERE `config_options`.`id` = 29;
UPDATE `config_options` SET `description.english` = 'SSH default port' WHERE `config_options`.`id` = 30;
DELETE FROM `config_options` WHERE `config_options`.`id` = 17;
DELETE FROM `config_options` WHERE `config_options`.`id` = 19;
DELETE FROM `config` WHERE `option_id` = 17;
DELETE FROM `config` WHERE `option_id` = 19;
INSERT INTO `config_options` (`id`, `option_name`, `description.russian`, `description.english`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES (68, 'config_mode', 'Режим конфигурирования. Скрипт опроса устройств не выполняется.', 'Configuration mode. The device polling script is not running.', '1', 'bool', '0', '0', '1');
ALTER TABLE `User_auth` ADD `dhcp_changed` INT NULL DEFAULT NULL AFTER `changed`;
ALTER TABLE `device_ports` ADD `tagged_vlan` VARCHAR(250) NULL DEFAULT NULL AFTER `vlan`, ADD `untagged_vlan` VARCHAR(250) NULL DEFAULT NULL AFTER `tagged_vlan`, ADD `forbidden_vlan` VARCHAR(250) NULL DEFAULT NULL AFTER `untagged_vlan`;
ALTER TABLE `dhcp_log` ADD `client-id` VARCHAR(250) NULL DEFAULT NULL AFTER `remote-id`;
ALTER TABLE `User_auth` CHANGE `clientid` `client-id` VARCHAR(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL;
