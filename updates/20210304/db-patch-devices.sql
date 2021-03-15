ALTER TABLE `devices` ADD `device_type` INT NOT NULL DEFAULT '1' AFTER `device_model`, ADD INDEX (`device_type`);
ALTER TABLE `devices` DROP `mac`;
ALTER TABLE `devices` ADD `SN` VARCHAR(80) NULL AFTER `port_count`;

