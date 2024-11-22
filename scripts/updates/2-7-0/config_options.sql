INSERT INTO `config_options` (`id`, `option_name`, `description.russian`, `description.english`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES ('72', 'netflow_path', 'Каталог для хранения данных, полученных по netflow от маршрутизаторов', 'The directory for storing data received via netflow from routers', '1', 'text', '/opt/Eye/netflow', '0', '0');
INSERT INTO `config_options` (`id`, `option_name`, `description.russian`, `description.english`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES ('73', 'check_computer_exists', 'Проверять существование компьютера в домене перед обновлением DNS по DHCP запросу', 'Verify the existence of a computer in the domain before updating DNS by DHCP request', '1', 'bool', '1', '0', '0');
ALTER TABLE `config_options` ADD `draft` BOOLEAN NOT NULL DEFAULT FALSE AFTER `description.english`;
UPDATE `config_options` SET `draft` = '1' WHERE `config_options`.`id` = 65;
UPDATE `config_options` SET `draft` = '1' WHERE `config_options`.`id` = 66;
UPDATE `config_options` SET `draft` = '1' WHERE `config_options`.`id` = 67;

