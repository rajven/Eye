#log files
log_dir=/var/log/scripts

#dnsmasq configs
dhcpd_conf=/etc/dnsmasq.d

#admin email
admin_email=admin@org.local

#sender address
from_addr=admin@org.local

#database
DBSERVER=localhost
DBUSER=rstat
DBNAME=stat
DBPASS=password

#login for windows AD
domain_auth=ORG\dhcp%password
winexe=/usr/bin/winexe

enable_ad_dns_update=0
ad_dns=192.168.1.100
ad_zone=org.local

#fping
fping=/sbin/fping

#mysql
MYSQL=/usr/bin/mysql

#log file owners
user=root
group=root

#switch access
sw_login=admin
sw_password=password
