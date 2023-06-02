ALTER TABLE `Filter_list` ADD `srcport` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL AFTER `dstport`;
UPDATE `Filter_list` set dstport='0' WHERE dstport IS NULL or dstport='';
UPDATE `Filter_list` set srcport='0' WHERE srcport IS NULL or srcport='';
