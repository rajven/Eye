CREATE TABLE `acl` (`id` int(11) NOT NULL,`name` varchar(30) NOT NULL,`description.english` varchar(250) NOT NULL,`description.russian` varchar(250) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `acl` VALUES(1, 'Full access', 'Full access', 'Полный доступ');
INSERT INTO `acl` VALUES(2, 'Operator', 'Editing parameters that are not related to access rights', 'Редактирование параметров, не связанных с правами доступа');
INSERT INTO `acl` VALUES(3, 'View only', 'View only', 'Только просмотр');
ALTER TABLE `Customers` CHANGE `readonly` `rights` TINYINT(1) NOT NULL DEFAULT '3';
UPDATE `Customers` set rights=3 WHERE rights=1;
UPDATE `Customers` set rights=1 WHERE rights=0;
ALTER TABLE `acl`  ADD PRIMARY KEY (`id`);
ALTER TABLE `acl`  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
