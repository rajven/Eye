The file names of the ipset sets have been changed. Now the file name corresponds to the name of the ipset. 
The database update script now supports multi‑line SQL queries. 
The iptables synchronization script can find the current router in the list of devices by its IP address. It is no longer necessary to pass the device code.
The logrotate and cron configuration settings have been changed. cron is split into two parts — for operation as root and as eye. logrotate currently depends on the installed services.
