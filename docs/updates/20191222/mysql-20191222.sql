ALTER TABLE `device_ports` CHANGE `vlan` `vlan` INT(11) NULL DEFAULT '1';
UPDATE device_ports set vlan=1 where vlan IS NULL;
ALTER TABLE `device_ports` CHANGE `vlan` `vlan` INT(11) NOT NULL DEFAULT '1';
