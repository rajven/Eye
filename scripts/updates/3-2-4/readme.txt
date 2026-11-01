the zabbix host groups, macros and tags are redistributed
added: ou_zabbix_groups - an organizational unit has several zabbix host groups
removed: ou.zabbix_group_id - the single host group of an organizational unit
added: zabbix_tags_list - the global list of the zabbix host tag names, and zabbix_tags - the tags of an eye network device
changed: zabbix macros are bound to an eye network device (zabbix_macros.device_id) instead of a device model, the existing model bindings are dropped
removed: zabbix_macros_list.default_value - the global macros list contains names only, the value is set per device
note: the sql patches contain no comments, upgrade.pl skips a statement which starts with a comment
note: like the earlier patches, the update files carry the english column comments, a russian installation gets them after the upgrade
