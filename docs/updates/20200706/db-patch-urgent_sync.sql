INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`) VALUES ('50', 'urgent sync access', 'Немедленное изменение списков доступа на роутере после правки записи пользователя', '1', 'bool', '0');
ALTER TABLE `User_auth` ADD `changed` BOOLEAN NOT NULL DEFAULT FALSE AFTER `link_check`, ADD INDEX `changed` (`changed`);
