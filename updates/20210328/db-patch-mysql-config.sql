INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES ('57', 'nagios_url', 'Адрес сайта nagios', '1', 'text', 'http://127.0.0.1/nagios', '0', '0'), ('58', 'cacti_url', 'Адрес сайта cacti', '1', 'text', 'http://127.0.0.1/cacti', '0', '0')
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES ('59', 'torrus_url', 'Адрес сайта Torrus', '1', 'text', 'http://127.0.0.1/torrus', '0', '0'), ('60', 'wiki_url', 'Адрес wiki', '1', 'text', 'http://127.0.0.1/wiki', '0', '0');
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES ('61', 'wiki_path', 'Путь к каталогу данных вики', '1', 'text', '/var/www/foswiki/data/', '0', '0'), ('62', 'stat_url', 'Адрес этого сайта', '1', 'text', 'http://127.0.0.1/stat', '0', '0');
ALTER TABLE `OU` ADD `nagios_dir` VARCHAR(255) NULL DEFAULT NULL AFTER `ou_name`;
UPDATE `OU` SET `ou_name` = '_ВСЕ_', `nagios_dir` = '/etc/nagios/any' WHERE `OU`.`id` = 0;
UPDATE `OU` SET `nagios_dir` = '/etc/nagios/voip' WHERE `OU`.`id` = 4;
UPDATE `OU` SET `nagios_dir` = '/etc/nagios/videocam' WHERE `OU`.`id` = 5;
UPDATE `OU` SET `nagios_dir` = '/etc/nagios/printers' WHERE `OU`.`id` = 6;
UPDATE `OU` SET `nagios_dir` = '/etc/nagios/switches' WHERE `OU`.`id` = 7;
UPDATE `OU` SET `nagios_dir` = '/etc/nagios/ups' WHERE `OU`.`id` = 8;
UPDATE `OU` SET `nagios_dir` = '/etc/nagios/security' WHERE `OU`.`id` = 9;
UPDATE `OU` SET `nagios_dir` = '/etc/nagios/routers' WHERE `OU`.`id` = 10;
UPDATE `OU` SET `nagios_dir` = '/etc/nagios/ap' WHERE `OU`.`id` = 12;
