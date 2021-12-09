Installation steps for CentOS 8:

1. Enable repo:

для CentOS 8:

yum install dnf-plugins-core
yum config-manager --set-enabled powertools
yum config-manager --set-enabled extras
dnf install epel-release elrepo-release

2. Install packages:

Centos:

dnf install httpd php php-common perl mariadb-server git fping net-snmp-utils \
php-mysqlnd php-bcmath php-intl php-mbstring php-pear-Date php-pear-Mail php-snmp perl-Net-Patricia \
perl-NetAddr-IP perl-Config-Tiny perl-Net-DNS perl-DateTime perl-Proc-Daemon perl-Net-Netmask \
perl-Text-Iconv perl-DateTime-Format-DateParse perl-Net-SNMP perl-Net-Telnet perl-Net-IPv4Addr \
perl-DBI perl-DBD-MySQL perl-Net-OpenSSH perl-Parallel-ForkManager -y

Ubuntu:
apt install apache2 git fping perl mariadb-server php php-mysql php-bcmath php-intl \
php-mbstring php-date php-mail php-snmp \
libnet-patricia-perl libnetaddr-ip-perl libconfig-tiny-perl libnet-dns-perl libdatetime-perl \
libnet-netmask-perl libtext-iconv-perl libnet-snmp-perl libnet-telnet-perl libdbi-perl \
libdbd-mysql-perl libparallel-forkmanager-perl libproc-daemon-perl libdatetime-format-dateparse-perl \
libnetwork-ipv4addr-perl libnet-openssh-perl

3. Download project:

git clone https://github.com/rajven/statV2
mkdir -p /usr/local/scripts
cd statV2/
cp -R scripts/ /usr/local/
mkdir -p /usr/local/scripts/cfg
cp docs/addons/cfg/config /usr/local/scripts/cfg/
cp -R html/ /var/www

4. Download additional scripts (optional)

download from https://jquery.com/download/ production jQuery to /var/www/html/js/jq
example: wget https://code.jquery.com/jquery-3.6.0.min.js
rename jquery-3.6.0.min.js to jquery.min.js

download from https://github.com/select2/select2 release
example: wget https://github.com/select2/select2/archive/4.0.12.tar.gz
extract contents from directory dist archive to /var/www/html/js/select2/

download jstree from  https://github.com/vakata/jstree/
wget https://github.com/vakata/jstree/zipball/3.3.12 -O js.zip
extract contents from directory dist archive to /var/www/html/js/jstree

5. Configure mysql

systemctl enable mariadb
systemctl start mariadb

mysql_secure_installation - configure root password!!!

#mysql -u root -p

MariaDB [(none)]> CREATE DATABASE `stat` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
MariaDB [(none)]> grant all privileges on stat.* to stat@localhost identified by 'password';
MariaDB [(none)]> flush privileges;
MariaDB [(none)]> quit

cat docs/mysql/mysql.sql | mysql -u root -p stat

6. Save configuration for web and scripts:

cp html/inc/config.php.sample /var/www/html/cfg/
mv /var/www/html/cfg/config.php.sample /var/www/html/cfg/config.php

edit: /var/www/html/cfg/config.php & /usr/local/scripts/cfg/config

set mysql database|user|password

7. Configure apache & php:

Centos:
sed -i 's/short_open_tag = Off/short_open_tag = On/' /etc/php.ini
#set timezone
sed -i 's/;date.timezone =/date.timezone = Europe\/Moscow/' /etc/php.ini
#enable php
sed -i 's/#LoadModule mpm_prefork_module/LoadModule mpm_prefork_module/' /etc/httpd/conf.modules.d/00-mpm.conf
sed -i 's/LoadModule mpm_event_module/#LoadModule mpm_event_module/' /etc/httpd/conf.modules.d/00-mpm.conf

systemctl enable httpd
systemctl start httpd

Ubuntu:
sed -i 's/short_open_tag = Off/short_open_tag = On/' /etc/php/7.4/apache2/php.ini
sed -i 's/;date.timezone =/date.timezone = Europe\/Moscow/' /etc/php/7.4/apache2/php.ini

systemctl enable apache2
systemctl start apache2

cp docs/addons/sudoers.d/apache /etc/sudoers.d/apache

8. Cron & logrotate

cp docs/cron/stat /etc/cron.d/stat
cp docs/logrotate/dnsmasq /etc/logrotate.d/dnsmasq
cp docs/logrotate/scripts /etc/logrotate.d/scripts

uncomment needed scripts...

9. Minimal configuration done! login: http://[ip]/admin/ user: admin password: admin

######################################### DHCP Server at Linux ###############################################################

if you need dhcp server:

dnf install dnsmasq -y

cp docs/systemd/dnsmasq.service /etc/systemd/system
cp docs/systemd/dhcp-log.service /etc/systemd/system
cp /etc/dnsmasq.conf /etc/dnsmasq.conf.default
cat docs/addons/dnsmasq.conf >/etc/dnsmasq.conf

#edit /etc/dnsmasq.conf for you network

systemctl enable dnsmasq
systemctl enable dhcp-log
systemctl start dnsmasq
systemctl start dhcp-log

######################################### Netflow #####################################################################

dnf install nfdump -y

cp docs/systemd/nfcapd@.service /etc/systemd/system/nfcapd@.service
mkdir -p /etc/nfcapd
cp docs/systemd/nfcapd/office.conf /etc/nfcapd/office.conf

Change port, directory for netflow data and specify the id of the device that the netflow stream is coming from

systemctl enable nfcapd@office
systemctl start nfcapd@office

enable netflow at mikrotik router:
/ip traffic-flow
set enabled=yes
/ip traffic-flow target
add dst-address=[IP-SERVER] port=[PORT nfcapd]

######################################### Remote syslog ###############################################################

dnf install syslog-ng -y

cp /etc/syslog-ng/syslog-ng.conf  /etc/syslog-ng/syslog-ng.conf.default
cat docs/syslog-ng/syslog-ng.conf >/etc/syslog-ng/syslog-ng.conf

systemctl enable syslog-ng
systemctl start syslog-ng

cp docs/systemd/syslog-stat.service /etc/systemd/system/syslog-stat.service

systemctl enable syslog-stat
systemctl start syslog-stat

######################################### Mikrotik managment ##########################################################

Configure mikrotik login|password|port for telnet service in http://[IP]/admin/customers/control-options.php

at device record (http://[IP]/admin/devices/) setup WAN & LAN intefaces for router, enable options acl,queue,connected-user-only

at mikrotik add iptables filter rules:

/ip firewall filter

add action=jump chain=forward comment="users set" in-interface-list=WAN jump-target=Users
add action=jump chain=forward jump-target=Users out-interface-list=WAN

#before this standart rules!!!
add action=drop chain=forward comment="drop forward invalid" connection-state=invalid
add action=accept chain=forward comment=related,established connection-state=established,related

#default deny forward rule - after standart rules!!!
add action=reject chain=forward comment="deny default wan" in-interface-list=WAN reject-with=icmp-network-unreachable
add action=reject chain=forward out-interface-list=WAN reject-with=icmp-network-unreachable

/queue tree
add max-limit=[YOU BANDWIDTH] name=upload_root_[WAN_INTERFACE_NAME] parent=[WAN_INTERFACE_NAME] queue=pcq-upload-default
add name=download_root_[LAN_INTERFACE_NAME] parent=[LAN_INTERFACE_NAME] queue=pcq-download-default

run /usr/local/scripts/sync_mikrotik.pl

#simple dhcp script
/tool fetch mode=http keep-result=no url="http://<STAT_IP_OR_HOSTNAME>/admin/users/add_dhcp.php\?login=<LOGIN>&password=<PASSWORD_HASH>&mac=$leaseActMAC&ip=$leaseActIP&action=$leaseBound&hostname=$"lease-hostname""

#show password hash - print-customers.pl

#advanced dhcp script - create ip list for allow work only dhcp clients
/tool fetch mode=http keep-result=no url="http://<STAT_IP_OR_HOSTNAME>/admin/users/add_dhcp.php\?login=<LOGIN>&password=<PASSWORD_HASH>&mac=$leaseActMAC&ip=$leaseActIP&action=$leaseBound&hostname=$"lease-hostname""
:if ($leaseBound = 0) do={
/log info ("Dhcp del: $leaseActIP list: dmz-dhcp")
/ip firewall address-list remove [ find where list=dmz-dhcp and address=$leaseActIP ] 
}
:if ($leaseBound = 1) do={
/log info ("Dhcp add: $leaseActIP list: dmz-dhcp")
/ip firewall address-list add address=$leaseActIP list=dmz-dhcp timeout=4h
/ip firewall address-list set [ find where list=dmz-dhcp and address=$leaseActIP ] timeout=4h
}


#########################################################################################################################
