Installation

1. Install the packages

apt install apache2 git fping perl mariadb-server php php-mysql php-bcmath php-intl \
php-mbstring php-date php-mail php-snmp \
libnet-patricia-perl libnetaddr-ip-perl libconfig-tiny-perl libnet-dns-perl libdatetime-perl \
libnet-netmask-perl libtext-iconv-perl libnet-snmp-perl libnet-telnet-perl libdbi-perl \
libdbd-mysql-perl libparallel-forkmanager-perl libproc-daemon-perl libdatetime-format-dateparse-perl \
libnetwork-ipv4addr-perl libnet-openssh-perl libfile-tail-perl php-fpm pdo-mysql libapache2-mod-fcgid \
libcrypt-cbc-perl

2. Download the source code and spread it in catalogs:

git clone https://github.com/rajven/Eye
mkdir -p /opt/Eye/scripts
mkdir -p /opt/Eye/scripts/cfg
mkdir -p /opt/Eye/scripts/log
cd statV2/
cp -R scripts/ /opt/Eye/
cp docs/addons/cfg/config /opt/Eye/scripts/cfg/
cp -R html/ /opt/Eye/

3. You can download additional scripts (prettiness)

mkdir -p /opt/Eye/html/js/jq
mkdir -p /opt/Eye/html/js/select2

download from https://jquery.com/download/ production jQuery to /opt/Eye/html/js/jq
#wget https://code.jquery.com/jquery-1.12.4.min.js -O /opt/Eye/html/js/jq/jquery.min.js
or
#wget https://code.jquery.com/jquery-3.7.0.min.js -O /opt/Eye/html/js/jq/jquery.min.js

download from https://github.com/select2/select2 release
#wget https://github.com/select2/select2/archive/4.0.12.tar.gz
#tar -xzf 4.0.12.tar.gz -C /opt/Eye/html/js/select2/ --strip-components=2 select2-4.0.12/dist

download jstree from  https://github.com/vakata/jstree/
#wget https://github.com/vakata/jstree/zipball/3.3.12 -O js.zip
#unzip js.zip "vakata-jstree-7a03954/dist/*" -d "/opt/Eye/html/"
#mv /opt/Eye/html/vakata-jstree-7a03954/dist/ /opt/Eye/html/js/jstree

4. Setting up mysql 

systemctl enable mariadb
systemctl start mariadb

mysql_secure_installation - set password for root

#mysql -u root -p

Create user and database

MariaDB [(none)]>
CREATE DATABASE `stat` DEFAULT utf8mb4 CHARACTER SET MATCH utf8mb4_unicode_ci;
grant all privileges to stat.* stat@localhost, identified with a "password";
reset privileges;
go out

Import default tables
documents cat/mysql/mysql.sql | mysql -u root -p stat

5. Edit configs for web and scripts:

cp html/inc/config.php.sample /opt/Eye/html/cfg/
mv /opt/Eye/html/cfg/config.php.sample /opt/Eye/html/cfg/config.php

edit: /opt/Eye/html/cfg/config.php

cp scripts/cfg/config.sample /opt/Eye/scripts/cfg/config

edit: /opt/Eye/scripts/cfg/config

You need to specify the password in mysql and the database!

Symmetric AES-128-CBC encryption is used to encrypt passwords to devices. It is necessary to generate a password and an initialization vector, enter in both configs:
Password: pwgen 16
Vector: tr -dc 0-9 </dev/urandom | head -c 16 ; echo ''

6. Configuring apache and php:

sed -i 's/short_open_tag = Off/short_open_tag = On/' /etc/php/7.4/apache2/php.ini
sed -i 's/;date.time zone =/date.time zone = Europe\/Moscow/' /etc/php/7.4/apache2/php.ini

systemctl enable apache2
systemctl start apache2

cp docs/add-ons/sudoers.d/www-data /etc/sudoers.d/www-data

7. Cron and logrotate

cp docs/cron/stat /etc/cron.d/stat
cp docs/logrotate/dnsmasq /etc/logrotate.d/dnsmasq
cp docs/logrotate/scripts/etc/logrotate.d/scripts

Do not forget to uncomment the necessary scripts in the crown

8. Minimal setup is ready! Log in: http://[ip]/admin/ user: admin password: admin, configure the user interface, user networks, etc.

9. Change the administrator password and api key!!!

######################################### DHCP server on Linux ###############################################################

You can use a dhcp server both on mirkotik and on a server with Linux. Imho, dnsmasq is preferable.

apt install dnsmasq -y

cp docs/systemd/dhcp-log.service /etc/systemd/system
cp /etc/dnsmasq.conf /etc/dnsmasq.conf.default
cat docs/addons/dnsmasq.conf >/etc/dnsmasq.conf

#edit /etc/dnsmasq.conf for you network

systemctl enable dnsmasq
systemctl enable dhcp-log
systemctl start dnsmasq
systemctl start dhcp-log

######################################### Additional ##################################################################

1. (Not necessary. Is in the last database dump). To determine the vendor of equipment by mac, you need to import a database of macs:

cp docs/mac-oids/download-macs.sh /opt/Eye/scripts/
cp docs/mac-oids/update-mac-vendors.pl /opt/Eye/scripts/

chmod +x /opt/Eye/scripts/download-macs.sh
chmod +x /opt/Eye/scripts/update-mac-vendors.pl

Escape:
/opt/Eye/scripts/download-macs.sh
/opt/Eye/scripts/update-mac-vendors.pl

And delete the scripts after completing their work

2. enable stat-sync service

cp docs/systemd/stat-sync.service /etc/systemd/system

systemctl enable stat-sync.service

######################################### Network flow #####################################################################

apt install nfdump -y

cp docs/systemd/nfcapd@.service /etc/systemd/system/nfcapd@.service
mkdir -p /etc/nfcapd
cp docs/systemd/nfcapd/office.conf /etc/nfcapd/office.conf

Set nfdump port, path for collected files and router id. Router id see in url for edit device:
#http://[IP]/admin/devices/editdevice.php?id=1

systemctl enable nfcapd@office
systemctl start nfcapd@office

Enable netflow at mikrotik:
/ip traffic-flow
set enabled=yes
/ip traffic-flow target
add dst-address=[IP-SERVER] port=[PORT nfcapd]

######################################### Remote System Log ###############################################################

If you need to write logs from devices:

apt install syslog-ng -y

cp /etc/syslog-ng/syslog-ng.conf  /etc/syslog-ng/syslog-ng.conf.default
cat docs/syslog-ng/syslog-ng.conf >/etc/syslog-ng/syslog-ng.conf

systemctl enable syslog-ng
systemctl start syslog-ng

cp docs/systemd/syslog-stat.service /etc/systemd/system/syslog-stat.service

systemctl enable syslog-stat
systemctl start syslog-stat

######################################### Mikrotik Management ##########################################################

configure ssh access parameters to the router in the admin panel (login | password | port) http://[IP]/admin/customers/control-options.php

we register in the router (http:// [IP]/admin/devices/), enter and disable servers, enable the use of servers, a dhcp server (not necessary if we use dnsmasq)

Adding rules to the firewall:

/ip firewall filter

add action=jump chain=forward comment="users set" in-interface-list=WAN jump-target=Users
add action=jump chain=forward jump-target=Users out-interface-list=WAN

#the above rules should be put above these default ones:
#add action=drop chain=forward comment="drop forward invalid" connection-state=invalid
#add action=accept chain=forward comment=related,established connection-state=established,related

#And these rules should be lower than the default ones
add action=reject chain=forward comment="deny default wan" in-interface-list=WAN log=yes log-prefix=unk_wan: reject-with=icmp-network-unreachable 
add action=drop chain=forward out-interface-list=WAN

shaper:
/queue tree
add max-limit=[YOU BANDWIDTH] name=upload_root_[WAN_INTERFACE_NAME] parent=[WAN_INTERFACE_NAME] queue=pcq-upload-default
add name=download_root_[LAN_INTERFACE_NAME] parent=[LAN_INTERFACE_NAME] queue=pcq-download-default

launching /opt/Eye/scripts/sync_mikrotik.pl
The script will create filtering and shaper rules

#dhcp script sampling
/tool fetch mode=http keep-result=no url="http://<STAT_IP_OR_HOSTNAME>/admin/users/add_dhcp.php\?login=<LOGIN>&api_key=<API_CUSTOMER_KEY>&mac=$leaseActMAC&ip=$leaseActIP&action=$leaseBound&hostname=$"lease-hostname""

#########################################################################################################################