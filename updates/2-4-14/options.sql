INSERT INTO `config_options` (`id`, `option_name`, `description.russian`, `description.english`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES ('69', 'clean_empty_user', 'Автоматически удалять записи пользователей, не содержащие ip-адресов или автоматических привязок', 'Automatically delete user records that do not contain IP addresses or automatic bindings', '1', 'bool', '0', '0', '1');
UPDATE `config_options` SET `min_value` = '0' WHERE `config_options`.`min_value`<>0 AND `config_options`.`type`='bool';
UPDATE `config_options` SET `max_value` = '1' WHERE `config_options`.`max_value`<>1 AND `config_options`.`type`='bool';

