ALTER TABLE `syslog` DROP INDEX `timestamp`, ADD INDEX `timestamp` (`timestamp`) USING BTREE;
ALTER TABLE `syslog` ADD INDEX(`level`);
ALTER TABLE `syslog` ADD FULLTEXT(`customer`);
ALTER TABLE `syslog` ADD INDEX(`auth_id`);

ALTER TABLE `mac_history` DROP INDEX `timestamp`, ADD INDEX `timestamp` (`timestamp`) USING BTREE;
ALTER TABLE `mac_history` DROP INDEX `timestamp_2`;
ALTER TABLE `mac_history` ADD INDEX(`mac`);
ALTER TABLE `mac_history` ADD INDEX(`ip`);
