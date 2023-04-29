UPDATE `config_options` SET `default_value` = REPLACE(`default_value`, '/usr/local/scripts/', '/opt/Eye/scripts/') WHERE `default_value` LIKE '%/usr/local/scripts/%';
UPDATE `config` SET `value` = REPLACE(`value`, '/usr/local/scripts/', '/opt/Eye/scripts/') WHERE `value` LIKE '%/usr/local/scripts/%';
