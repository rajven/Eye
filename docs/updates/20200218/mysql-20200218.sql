CREATE TABLE `mac_vendors` (
  `id` int(11) NOT NULL,
  `oui` varchar(18) NOT NULL,
  `isprivate` tinyint(1) NOT NULL DEFAULT 0,
  `companyName` text DEFAULT NULL,
  `companyAddress` text DEFAULT NULL,
  `countryCode` varchar(3) DEFAULT NULL,
  `assignmentBlockSize` varchar(10) DEFAULT NULL,
  `dateCreated` date DEFAULT NULL,
  `dateUpdated` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `mac_vendors` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `oui` (`oui`);
ALTER TABLE `mac_vendors` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `devices` CHANGE `snmp_version` `snmp_version` TINYINT(4) NOT NULL DEFAULT '0';
ALTER TABLE `devices` ADD `snmp3_user_ro` VARCHAR(20) NULL DEFAULT NULL AFTER `snmp_version`, ADD `snmp3_user_ro_password` VARCHAR(20) NULL DEFAULT NULL AFTER `snmp3_user_ro`;
ALTER TABLE `devices` ADD `snmp3_user_rw` VARCHAR(20) NULL DEFAULT NULL AFTER `snmp_version`, ADD `snmp3_user_rw_password` VARCHAR(20) NULL DEFAULT NULL AFTER `snmp3_user_rw`;
ALTER TABLE `devices` ADD `queue_enabled` BOOLEAN NOT NULL DEFAULT TRUE AFTER `nagios_status`, ADD `connected_user_only` BOOLEAN NOT NULL DEFAULT TRUE AFTER `queue_enabled`;
ALTER TABLE `syslog` ADD `level` INT NOT NULL DEFAULT '1' AFTER `message`, ADD INDEX `level` (`level`);

INSERT INTO `config_options` (`id`, `option_name`, `uniq`, `type`) VALUES ('44', 'Ignore hotspot dhcp log', '1', 'bool');
UPDATE syslog SET `level`=4 WHERE `message` LIKE 'Change table%';
