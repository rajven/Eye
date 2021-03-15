ALTER TABLE `syslog` ADD INDEX (`timestamp`, `level`, `customer`);
ALTER TABLE `mac_history` ADD INDEX (`timestamp`, `mac`);
ALTER TABLE `mac_history` ADD INDEX (`timestamp`, `ip`);
ALTER TABLE `Unknown_mac` ADD INDEX (`timestamp`, `device_id`, `port_id`, `mac`);
ALTER TABLE `dhcp_log` ADD INDEX (`timestamp`, `action`);
