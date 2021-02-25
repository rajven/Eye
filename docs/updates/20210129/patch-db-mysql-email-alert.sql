INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`) VALUES ('51', 'Email_alert', 'Отправлять e-mail сообщения для уровней сообщений WARNING & ERROR', '1', 'bool', '1');
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`) VALUES ('52', 'Sender email', 'E-mail адрес, с которого рассылается почта', '1', 'text', 'root');
INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`) VALUES ('53', 'log level', 'Каждый уровень включает в себя предыдущий:\r\n0 - ERROR - писать только ошибки\r\n1 - WARNING - писать предупреждения\r\n2 - INFO - писать информационные сообщения\r\n3 - VERBOSE - писать подробную информацию о выполняемых операциях', '1', 'int', '2');

UPDATE syslog set level = 255 WHERE level=2;
UPDATE syslog set level = 2 WHERE level=1;
UPDATE syslog set level = 1 WHERE level=0;
UPDATE syslog set level = 0 WHERE level=3;
