ALTER TABLE `User_auth` ADD `dns_ptr_only` BOOLEAN NOT NULL DEFAULT FALSE AFTER `dns_name`;
ALTER TABLE `User_auth` CHANGE `dns_name` `dns_name` VARCHAR(253) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL;
