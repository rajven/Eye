UPDATE `config_options` SET `default_value` = REPLACE(`default_value`, '/usr/local/scripts/', '/opt/Eye/scripts/') WHERE `default_value` LIKE '%/usr/local/scripts/%';
UPDATE `config` SET `value` = REPLACE(`value`, '/usr/local/scripts/', '/opt/Eye/scripts/') WHERE `value` LIKE '%/usr/local/scripts/%';
ALTER TABLE `Filter_list` CHANGE `dst` `dst` VARCHAR(253) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL;
