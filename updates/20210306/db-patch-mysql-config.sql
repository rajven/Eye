INSERT INTO `config_options` (`id`, `option_name`, `description`, `uniq`, `type`, `default_value`, `min_value`, `max_value`) VALUES ('56', 'traffic_ipstat_history', 'Время хранения полной статистики по трафику для каждого ip-адреса в сутках. Таблица в 6 раз больше обычной часовой статистики. Врядли кому-то потребуется глубина хранения более месяца.', '1', 'int', '30', '7', '365');

