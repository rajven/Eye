CREATE TABLE `sessions` (
`id` INT NOT NULL AUTO_INCREMENT , 
`customer_id` INT NULL DEFAULT NULL , 
`session_id` VARCHAR(256) NULL DEFAULT NULL , 
`session_key` VARCHAR(40) NULL DEFAULT NULL , 
`start_time` INT NULL DEFAULT NULL ,
PRIMARY KEY (`id`)) ENGINE = InnoDB;
