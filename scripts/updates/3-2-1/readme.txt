# zabbix support: monitoring system config options, zabbix tables, monitoring fields rename
added: config options 74 (monitoring system), 75 (zabbix api url), 76 (zabbix api key)
added: zabbix_templates - zabbix templates bound to the device models
added: zabbix_macros_list - zabbix macros bound to the device models
added: zabbix_macros - macros used by the device models
added: ou.zabbix_group_id - zabbix host group of the organizational unit
changed: devices, device_ports, user_auth - nagios => monitoring, nagios_status => monitoring_status
added: monitoring_old - previous value of the monitoring field in devices, device_ports, user_auth
note: the sql patches contain no comments, upgrade.pl skips a statement which starts with a comment
