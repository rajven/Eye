Утсановка для CentOS 8:

1. Включаем дополнительные репозитории:

yum install dnf-plugins-core
yum config-manager --set-enabled powertools
yum config-manager --set-enabled extras
dnf install epel-release elrepo-release

2. Ставим пакеты:

dnf install httpd php php-common perl mariadb-server git fping net-snmp-utils \
php-mysqlnd php-bcmath php-intl php-mbstring php-pear-Date php-pear-Mail php-snmp perl-Net-Patricia \
perl-NetAddr-IP perl-Config-Tiny perl-Net-DNS perl-DateTime perl-Proc-Daemon perl-Net-Netmask \
perl-Text-Iconv perl-DateTime-Format-DateParse perl-Net-SNMP perl-Net-Telnet perl-Net-IPv4Addr \
perl-DBI -y

3. Качаем исходники и раскидываем по каталогам:

git clone https://github.com/rajven/statV2
mkdir -p /usr/local/scripts
cd statV2/
cp -R scripts/ /usr/local/
mkdir -p /usr/local/scripts/cfg
cp docs/addons/cfg/config /usr/local/scripts/cfg/
cp -R html/ /var/www

4. Можно скачать дополнительные скрипты (красивости)

download from https://jquery.com/download/ production jQuery to /var/www/html/js/jq
example: wget https://code.jquery.com/jquery-3.6.0.min.js
rename jquery-3.6.0.min.js to jquery.min.js

download from https://github.com/select2/select2 release
example: https://github.com/select2/select2/archive/4.0.12.tar.gz
extract contents from directory dist archive to /var/www/html/js/select2/

5. Настраиваем mysql 

systemctl enable mariadb
systemctl start mariadb

mysql_secure_installation - утсановить пароль для root

#mysql -u root -p

Создать юзера и базу данных

MariaDB [(none)]> create database stat;
MariaDB [(none)]> grant all privileges on stat.* to stat@localhost identified by 'password';
MariaDB [(none)]> flush privileges;
MariaDB [(none)]> quit

cat docs/mysql/stat_table_*.sql | mysql -u root -p stat
cat docs/mysql/stat_extra.sql | mysql -u root -p stat

6. Настраиваем конфиги для вэба и скриптов:

cp html/inc/config.php.sample /var/www/html/cfg/
mv /var/www/html/cfg/config.php.sample /var/www/html/cfg/config.php

edit: /var/www/html/cfg/config.php & /usr/local/scripts/cfg/config

Надо указать пароль в  mysql и базу данных!!!

7. Настраиваем апач и php:

sed -i 's/short_open_tag = Off/short_open_tag = On/' /etc/php.ini

#set timezone
sed -i 's/;date.timezone =/date.timezone = Europe\/Moscow/' /etc/php.ini

#enable php
sed -i 's/#LoadModule mpm_prefork_module/LoadModule mpm_prefork_module/' /etc/httpd/conf.modules.d/00-mpm.conf
sed -i 's/LoadModule mpm_event_module/#LoadModule mpm_event_module/' /etc/httpd/conf.modules.d/00-mpm.conf

systemctl enable httpd
systemctl start httpd

cp docs/addons/sudoers.d/apache /etc/sudoers.d/apache

8. Cron & logrotate

cp docs/cron/stat /etc/cron.d/stat
cp docs/logrotate/dnsmasq /etc/logrotate.d/dnsmasq
cp docs/logrotate/scripts /etc/logrotate.d/scripts

Не забудьте раскомментировать в кроне неоходимые скрипты

9. Минимальная настрофка готова! Заходим: http://[ip]/admin/ user: admin password: admin, настраиваем список устройств, используемые сети и т.д.

######################################### DHCP Server at Linux ###############################################################

Можно исопльзовать dhcp-сервер как на миркотике, так и на сервере с Linux. Имхо, dnsmasq - предпочтительнее. 

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
#dd action=accept chain=forward comment=related,established connection-state=established,related

#А эти правила должны быть ниже дефолтных
add action=reject chain=forward comment="deny default wan" in-interface-list=WAN reject-with=icmp-network-unreachable
add action=reject chain=forward out-interface-list=WAN reject-with=icmp-network-unreachable

шейпер:
/queue tree
add max-limit=[YOU BANDWIDTH] name=upload_root_[WAN_INTERFACE_NAME] parent=[WAN_INTERFACE_NAME] queue=pcq-upload-default
add name=download_root_[LAN_INTERFACE_NAME] parent=[LAN_INTERFACE_NAME] queue=pcq-download-default

запускаем /usr/local/scripts/sync_mikrotik.pl
Скрипт создаст правила фильтрации и шейпера

#########################################################################################################################
