UPDATE `config_options` SET `option_name` = 'router_ssh_port' WHERE `config_options`.`id` = 30
INSERT INTO `config_options` (`id`, `option_name`, `description.russian`, `description.english`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES ('65', 'mikrotik_command_interface', 'Используемый способ конфигурирования (0 - cli для ROS 6, 1 - rest api для ROS 7)', 'Configuration method used (0 - cli for ROS 6, 1 - rest api for ROS 7)', '1', 'int', '0', '0', '1');
INSERT INTO `config_options` (`id`, `option_name`, `description.russian`, `description.english`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES ('66', 'mikrotik_rest_api_ssl', 'Использовать https для rest api', 'Use HTTPS for rest api', '1', 'bool', '1', '0', '1');
INSERT INTO `config_options` (`id`, `option_name`, `description.russian`, `description.english`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES ('67', 'mikrotik_rest_api_port', 'Порт вэб-интерфейса для rest api', 'Web interface port for rest API', '1', 'int', '443', '0', '0');
UPDATE `config_options` SET `option_name` = 'discovery network' WHERE `config_options`.`id` = 41;
