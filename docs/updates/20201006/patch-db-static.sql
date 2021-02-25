ALTER TABLE `subnets` ADD `static` BOOLEAN NOT NULL DEFAULT FALSE AFTER `dhcp`, ADD INDEX `static` (`static`);
ALTER TABLE `subnets` ADD `dhcp_lease_time` INT NOT NULL DEFAULT '480' AFTER `dhcp_stop`;
