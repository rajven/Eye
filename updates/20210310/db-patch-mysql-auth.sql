ALTER TABLE `User_auth` DROP `ip_int_end`;
ALTER TABLE `User_auth` ADD INDEX(`deleted`);
