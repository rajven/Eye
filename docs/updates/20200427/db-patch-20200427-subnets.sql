ALTER TABLE `subnets` ADD `dhcp_start` BIGINT NOT NULL DEFAULT '0' AFTER `ip_int_stop`, ADD `dhcp_stop` BIGINT NOT NULL DEFAULT '0' AFTER `dhcp_start`;
ALTER TABLE `subnets` ADD `gateway` BIGINT NOT NULL DEFAULT '0' AFTER `dhcp_stop`;
