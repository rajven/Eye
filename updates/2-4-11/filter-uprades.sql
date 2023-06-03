ALTER TABLE `Group_filters` ADD `action` BOOLEAN NOT NULL DEFAULT FALSE AFTER `order`;
UPDATE `Group_filters` as G set action = (SELECT action FROM Filter_list WHERE G.filter_id = id);
ALTER TABLE `Filter_list` DROP `action`;
