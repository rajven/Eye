CREATE TABLE `ad_comp_cache` ( `id` INT NOT NULL AUTO_INCREMENT , `name` VARCHAR(63) NOT NULL , `last_found` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP , PRIMARY KEY (`id`), UNIQUE `comp_name` (`name`)) ENGINE = InnoDB;