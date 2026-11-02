the monitoring parameters of an ip record move to the device of its owner
removed: user_auth.monitoring, user_auth.monitoring_old, user_auth.monitoring_status, user_auth.nagios_handler, user_auth.link_check, user_auth.wikiname
added: devices.link_check, devices.nagios_handler, devices.wikiname (devices.monitoring, devices.monitoring_old and devices.monitoring_status existed before)
changed: a monitoring device is a host of the monitoring system, an ip record is not a host anymore, sync_zabbix.pl and gen_nagios_config.pl export the devices only
note: the values of the addresses are copied to the device of the same owner, the switch and the router of the owner keep their own monitoring state, a device which is already monitored stays monitored
note: the hosts created in zabbix for the addresses before are left untouched, eye does not manage them anymore
note: the sql patches contain no comments, upgrade.pl skips a statement which starts with a comment
