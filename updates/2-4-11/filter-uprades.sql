ALTER TABLE `Group_filters` ADD `action` BOOLEAN NOT NULL DEFAULT FALSE AFTER `order`;
UPDATE `Group_filters` as G set action = (SELECT action FROM Filter_list WHERE G.filter_id = id);
ALTER TABLE `Filter_list` DROP `action`;
DELETE FROM Group_filters WHERE filter_id NOT IN (SELECT id FROM Filter_list);
DELETE FROM Group_filters WHERE group_id NOT IN (SELECT id FROM Group_list);
