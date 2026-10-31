zabbix macros list is global now
changed: zabbix_macros_list - the global dictionary of the zabbix macros, macro_name is unique, device_model_id is removed
changed: zabbix_macros - a macro of the model refers to zabbix_macros_list, macro_name is removed, empty macro_value means the default value
note: the macros which were bound to several models as copies are merged into one record of the list
note: the sql patches contain no comments, upgrade.pl skips a statement which starts with a comment
