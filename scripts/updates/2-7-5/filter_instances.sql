CREATE TABLE `filter_instances` (`id` INT NOT NULL AUTO_INCREMENT , `name` VARCHAR(50) NULL DEFAULT NULL , `comment` VARCHAR(200) NULL DEFAULT NULL , PRIMARY KEY (`id`), UNIQUE (`name`)) ENGINE = InnoDB;
INSERT INTO `filter_instances` (`id`, `name`) VALUES ('1', 'default');
