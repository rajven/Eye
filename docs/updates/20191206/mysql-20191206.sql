ALTER TABLE `remote_syslog` ADD INDEX(`ip`);
ALTER TABLE `remote_syslog` ADD INDEX(`date`);
ALTER TABLE `devices` ADD INDEX(`ip`);
