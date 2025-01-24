ALTER TABLE `devices` ADD `snmp3_auth_proto` VARCHAR(10) NOT NULL DEFAULT 'sha512' AFTER `snmp_version`, ADD `snmp3_priv_proto` VARCHAR(10) NOT NULL DEFAULT 'aes128' AFTER `snmp3_auth_proto`;
