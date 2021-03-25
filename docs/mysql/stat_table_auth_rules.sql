CREATE TABLE `auth_rules` ( `id` INT NOT NULL AUTO_INCREMENT , `user_id` INT NOT NULL , `type` INT NOT NULL , `rule` VARCHAR(40) NULL DEFAULT NULL , PRIMARY KEY (`id`), INDEX (`user_id`)) ENGINE = InnoDB;
ALTER TABLE `auth_rules` ADD UNIQUE(`rule`);
