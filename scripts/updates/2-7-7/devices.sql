ALTER TABLE `devices` ADD `ip_int` BIGINT(10) UNSIGNED NULL DEFAULT NULL AFTER `ip`;
UPDATE `devices` SET `ip_int` = INET_ATON(`ip`);
