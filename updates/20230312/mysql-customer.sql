ALTER TABLE `Customers` ADD `api_key` VARCHAR(255) NULL DEFAULT NULL AFTER `Pwd`;
UPDATE `Customers` set `api_key`=MD5(`Pwd`) where `api_key` is null;
ALTER TABLE `Customers` CHANGE `Pwd` `password` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'NULL';
ALTER TABLE `Customers` CHANGE `api_key` `api_key` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL;
