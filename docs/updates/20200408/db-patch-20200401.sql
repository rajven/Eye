ALTER TABLE `devices` ADD `user_id` INT NULL DEFAULT NULL AFTER `connected_user_only`;
ALTER TABLE `subnets` ADD `discovery` BOOLEAN NOT NULL DEFAULT TRUE AFTER `dhcp_update_hostname`;
