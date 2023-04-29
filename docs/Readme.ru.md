Установка

1. Включаем дополнительные репозитории:

для CentOS 8:

yum install dnf-plugins-core
yum config-manager --set-enabled powertools
yum config-manager --set-enabled extras
dnf install epel-release elrepo-release

2. Ставим пакеты:

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

3. Качаем исходники и раскидываем по каталогам:

git clone https://github.com/rajven/statV2
mkdir -p /opt/Eye/scripts
mkdir -p /opt/Eye/scripts/cfg
cd statV2/
cp -R scripts/ /opt/Eye/
cp docs/addons/cfg/config /opt/Eye/scripts/cfg/
cp -R html/ /var/www

4. Можно скачать дополнительные скрипты (красивости)

mkdir -p /opt/Eye/html/js/jq
mkdir -p /opt/Eye/html/js/select2

download from https://jquery.com/download/ production jQuery to /opt/Eye/html/js/jq
#wget https://code.jquery.com/jquery-3.6.0.min.js -O /opt/Eye/html/js/jq/jquery.min.js

download from https://github.com/select2/select2 release
#wget https://github.com/select2/select2/archive/4.0.12.tar.gz
#tar -xzf 4.0.12.tar.gz -C /opt/Eye/html/js/select2/ --strip-components=2 select2-4.0.12/dist

download jstree from  https://github.com/vakata/jstree/
#wget https://github.com/vakata/jstree/zipball/3.3.12 -O js.zip
#unzip js.zip "vakata-jstree-7a03954/dist/*" -d "/opt/Eye/html/"
#mv /opt/Eye/html/vakata-jstree-7a03954/dist/ /opt/Eye/html/js/jstree

5. Настраиваем mysql 

systemctl enable mariadb
systemctl start mariadb

mysql_secure_installation - утсановить пароль для root

#mysql -u root -p

Создать юзера и базу данных

MariaDB [(none)]> 
CREATE DATABASE `stat` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
grant all privileges on stat.* to stat@localhost identified by 'password';
flush privileges;
quit

cat docs/mysql/mysql.sql | mysql -u root -p stat

6. Настраиваем конфиги для вэба и скриптов:

cp html/inc/config.php.sample /opt/Eye/html/cfg/
mv /opt/Eye/html/cfg/config.php.sample /opt/Eye/html/cfg/config.php

edit: /opt/Eye/html/cfg/config.php

cp scripts/cfg/config.sample /opt/Eye/scripts/cfg/config

edit: /opt/Eye/scripts/cfg/config

Надо указать пароль в  mysql и базу данных!!!

7. Настраиваем апач и php:

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

Не забудьте раскомментировать в кроне неоходимые скрипты

9. Минимальная настройка готова! Заходим: http://[ip]/admin/ user: admin password: admin, настраиваем список устройств, используемые сети и т.д.

######################################### DHCP Server at Linux ###############################################################

Можно использовать dhcp-сервер как на миркотике, так и на сервере с Linux. Имхо, dnsmasq - предпочтительнее.

dnf install dnsmasq -y

or 

apt install dnsmasq -y

cp docs/systemd/dnsmasq.service /etc/systemd/system
cp docs/systemd/dhcp-log.service /etc/systemd/system
cp /etc/dnsmasq.conf /etc/dnsmasq.conf.default
cat docs/addons/dnsmasq.conf >/etc/dnsmasq.conf

#edit /etc/dnsmasq.conf for you network

systemctl enable dnsmasq
systemctl enable dhcp-log
systemctl start dnsmasq
systemctl start dhcp-log

######################################### Additional ##################################################################

1. Для определения вендора оборудования по маку, необходимо импортировать базу маков:

cp docs/mac-oids/download-macs.sh /opt/Eye/scripts/
cp docs/mac-oids/update-mac-vendors.pl /opt/Eye/scripts/

chmod +x /opt/Eye/scripts/download-macs.sh
chmod +x /opt/Eye/scripts/update-mac-vendors.pl

Run:
/opt/Eye/scripts/download-macs.sh
/opt/Eye/scripts/update-mac-vendors.pl

И удалите скрипты после завершения их работы

2. Для изменения списков доступа на маршрутизаторе сразу после внесения изменений необходимо включить сервис stat-sync

cp docs/systemd/stat-sync.service /etc/systemd/system

systemctl enable stat-sync.service

######################################### Netflow #####################################################################

dnf install nfdump -y

cp docs/systemd/nfcapd@.service /etc/systemd/system/nfcapd@.service
mkdir -p /etc/nfcapd
cp docs/systemd/nfcapd/office.conf /etc/nfcapd/office.conf

Указываем порт, место хранения статистики и id роутера, с которого снимается трафик

systemctl enable nfcapd@office
systemctl start nfcapd@office

Включаем netflow на микротике:
/ip traffic-flow
set enabled=yes
/ip traffic-flow target
add dst-address=[IP-SERVER] port=[PORT nfcapd]

######################################### Remote syslog ###############################################################

Если нужно писать логи с устройств:

dnf install syslog-ng -y

cp /etc/syslog-ng/syslog-ng.conf  /etc/syslog-ng/syslog-ng.conf.default
cat docs/syslog-ng/syslog-ng.conf >/etc/syslog-ng/syslog-ng.conf

systemctl enable syslog-ng
systemctl start syslog-ng

cp docs/systemd/syslog-stat.service /etc/systemd/system/syslog-stat.service

systemctl enable syslog-stat
systemctl start syslog-stat

######################################### Mikrotik managment ##########################################################

настраиваем параметры доступа по телнету к роутеру в админке (login|password|port)  http://[IP]/admin/customers/control-options.php

указываем в роутере (http://[IP]/admin/devices/) внешние и внутренние интерфейсы, включаем использование шейперов, dhcp-сервера (не нужно, если исопльзуем dnsmasq)

Добавляем правила в фаервол:

/ip firewall filter

add action=jump chain=forward comment="users set" in-interface-list=WAN jump-target=Users
add action=jump chain=forward jump-target=Users out-interface-list=WAN

#указанные выше правила надо поставить выше этих дефалтных:
#add action=drop chain=forward comment="drop forward invalid" connection-state=invalid
#add action=accept chain=forward comment=related,established connection-state=established,related

#А эти правила должны быть ниже дефолтных
add action=reject chain=forward comment="deny default wan" in-interface-list=WAN reject-with=icmp-network-unreachable
add action=reject chain=forward out-interface-list=WAN reject-with=icmp-network-unreachable

шейпер:
/queue tree
add max-limit=[YOU BANDWIDTH] name=upload_root_[WAN_INTERFACE_NAME] parent=[WAN_INTERFACE_NAME] queue=pcq-upload-default
add name=download_root_[LAN_INTERFACE_NAME] parent=[LAN_INTERFACE_NAME] queue=pcq-download-default

запускаем /opt/Eye/scripts/sync_mikrotik.pl
Скрипт создаст правила фильтрации и шейпера

#dhcp script
/tool fetch mode=http keep-result=no url="http://<STAT_IP_OR_HOSTNAME>/admin/users/add_dhcp.php\?login=<LOGIN>&password=<PASSWORD_HASH>&mac=$leaseActMAC&ip=$leaseActIP&action=$leaseBound&hostname=$"lease-hostname""

#просмотреть хэши паролей - print-customers.pl

#расширенный скрипт, создаёт список доступа для дальнейшей блокировки клиентов с статическими адресами
/tool fetch mode=http keep-result=no url="http://<STAT_IP_OR_HOSTNAME>/admin/users/add_dhcp.php\?login=<LOGIN>&password=<PASSWORD_HASH>&mac=$leaseActMAC&ip=$leaseActIP&action=$leaseBound&hostname=$"lease-hostname""
:if ($leaseBound = 0) do={
/log info ("Dhcp del: $leaseActIP list: dmz-dhcp")
/ip firewall address-list remove [ find where list=dmz-dhcp and address=$leaseActIP ].
}
:if ($leaseBound = 1) do={
/log info ("Dhcp add: $leaseActIP list: dmz-dhcp")
/ip firewall address-list add address=$leaseActIP list=dmz-dhcp timeout=4h
/ip firewall address-list set [ find where list=dmz-dhcp and address=$leaseActIP ] timeout=4h
}

#########################################################################################################################
