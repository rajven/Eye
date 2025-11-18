Установка

1. Ставим пакеты

#общая часть
apt install git xxd bsdmainutils

#для сервера с БД
apt install mariadb-server

#для сервера с вэбом
apt install apache2 libapache2-mod-fcgid \
php php-mysql php-bcmath php-intl php-mbstring php-date php-mail php-snmp php-zip php-fpm php-db php-pgsql

#для ядра
apt install perl libnet-patricia-perl libnetaddr-ip-perl libconfig-tiny-perl libnet-dns-perl libdatetime-perl \
libnet-netmask-perl libtext-iconv-perl libnet-snmp-perl libnet-telnet-perl libdbi-perl \
libdbd-mysql-perl libparallel-forkmanager-perl libproc-daemon-perl libdatetime-format-dateparse-perl \
libnetwork-ipv4addr-perl libnet-openssh-perl libfile-tail-perl libcrypt-rijndael-perl \
libcrypt-cbc-perl libcryptx-perl libdbd-pg-perl libfile-path-tiny-perl libexpect-perl libcrypt-des-perl

#дополнительно
apt install dnsmasq syslog-ng 
apt install bind9 bind9-utils bind9-host

2. Create user and group

addgroup --system eye
adduser --system  --disabled-password --disabled-login --ingroup eye --home=/opt/Eye eye
chmod 770 /opt/Eye

2.1 Если нужна работа с nagios
usermod -a -G eye nagios

3. Качаем исходники и раскидываем по каталогам:

git clone https://github.com/rajven/Eye
mkdir -p /opt/Eye/scripts
mkdir -p /opt/Eye/scripts/cfg
mkdir -p /opt/Eye/scripts/log
cd Eye/
cp -R scripts/ /opt/Eye/
cp -R html/ /opt/Eye/

patch perl snmp for support SHA512

#cp -f docs/patches/USM.pm /usr/share/perl5/Net/SNMP/Security/USM.pm

4. Можно скачать дополнительные скрипты (красивости)

mkdir -p /opt/Eye/html/js/jq
mkdir -p /opt/Eye/html/js/select2

download from https://jquery.com/download/ production jQuery to /opt/Eye/html/js/jq
#wget https://code.jquery.com/jquery-1.12.4.min.js -O /opt/Eye/html/js/jq/jquery.min.js
or
#wget https://code.jquery.com/jquery-3.7.0.min.js -O /opt/Eye/html/js/jq/jquery.min.js

download from https://github.com/select2/select2 release
#wget https://github.com/select2/select2/archive/4.0.12.tar.gz
#tar -xzf 4.0.12.tar.gz -C /opt/Eye/html/js/select2/ --strip-components=2 select2-4.0.12/dist
#rm -f 4.0.12.tar.gz

download jstree from  https://github.com/vakata/jstree/
#wget https://github.com/vakata/jstree/zipball/3.3.12 -O js.zip
#unzip js.zip "vakata-jstree-7a03954/dist/*" -d "/opt/Eye/html/"
#mv /opt/Eye/html/vakata-jstree-7a03954/dist/ /opt/Eye/html/js/jstree
#rm -d /opt/Eye/html/vakata-jstree-7a03954
#rm -f js.zip

5. Настраиваем mysql 

set password for root
#mysql_secure_installation

Создаём базу данных
#cat docs/mysql/create_db.sql | mysql -u root -p

Импортируем таблицы
#cat docs/mysql/latest-mysql.sql | mysql -u root -p stat

Create user and database

#mysql -u root -p

MariaDB [(none)]>
grant all privileges to stat.* stat@localhost, identified with a "password";
flush privileges;
exit

6. Настраиваем конфиги для вэба и скриптов:

cp html/inc/config.sample.php /opt/Eye/html/cfg/
mv /opt/Eye/html/cfg/config.sample.php /opt/Eye/html/cfg/config.php

edit: /opt/Eye/html/cfg/config.php

cp scripts/cfg/config.sample /opt/Eye/scripts/cfg/config

edit: /opt/Eye/scripts/cfg/config

Надо указать пароль в  mysql и базу данных!

Для шифрования паролей на устройства используется симметричное ишфрование AES-128-CBC. Необходимо сгенерировать пароль и вектор инициализации, внести в оба конфига:
Пароль: pwgen 16
Вектор: tr -dc 0-9 </dev/urandom | head -c 16 ; echo ''

7. Настраиваем апач и php:

sed -i 's/short_open_tag = Off/short_open_tag = On/' /etc/php/7.4/apache2/php.ini
sed -i 's/;date.timezone =/date.timezone = Europe\/Moscow/' /etc/php/7.4/apache2/php.ini
sed -i -E 's/DocumentRoot\s+\/var\/www\/html/DocumentRoot \/opt\/Eye\/html/' /etc/apache2/sites-available/000-default.conf

systemctl enable apache2
systemctl start apache2

cp docs/sudoers.d/www-data /etc/sudoers.d/www-data

8. Cron & logrotate

cp docs/cron/stat /etc/cron.d/stat
cp docs/logrotate/dnsmasq /etc/logrotate.d/dnsmasq
cp docs/logrotate/scripts /etc/logrotate.d/scripts

Не забудьте раскомментировать в кроне неоходимые скрипты

9. Минимальная настройка готова! Заходим: http://[ip]/admin/ user: admin password: admin, настраиваем список устройств, используемые сети и т.д.

10. Change admin password and api key!!!

######################################### DHCP Server at Linux ###############################################################

Можно использовать dhcp-сервер как на миркотике, так и на сервере с Linux. Имхо, dnsmasq - предпочтительнее.

apt install dnsmasq -y

cp docs/systemd/dhcp-log.service /etc/systemd/system
cp docs/systemd/dhcp-log-truncate.service /etc/systemd/system
cp /etc/dnsmasq.conf /etc/dnsmasq.conf.default
cat docs/addons/dnsmasq.conf >/etc/dnsmasq.conf

#edit /etc/dnsmasq.conf for you network

systemctl enable dnsmasq dhcp-log dhcp-log-truncate --now

######################################### Additional ##################################################################

1. Для определения вендора оборудования по маку, необходимо импортировать базу маков:

scripts/utils/mac-oids/download-macs.sh
scripts/utils/mac-oids/update-mac-vendors.pl

2. Для изменения списков доступа на маршрутизаторе сразу после внесения изменений в вэб-интерфейсе необходимо включить сервис stat-sync

cp docs/systemd/stat-sync.service /etc/systemd/system

systemctl enable stat-sync.service

######################################### Network flow #####################################################################

Включаем netflow на роутере микротик:
#for ROS 6
set enabled=yes
#for ROS 7
set enabled=yes  interfaces=WAN

/ip traffic-flow target
add dst-address=[IP-SERVER] port=2055

#cp docs/systemd/eye-statd.service /etc/systemd/system
#systemctl enable eye-statd

######################################### Remote syslog ###############################################################

Если нужно писать логи с устройств:

apt install syslog-ng -y

cp /etc/syslog-ng/syslog-ng.conf  /etc/syslog-ng/syslog-ng.conf.default
cat docs/syslog-ng/syslog-ng.conf >/etc/syslog-ng/syslog-ng.conf

systemctl enable syslog-ng
systemctl start syslog-ng

cp docs/systemd/syslog-stat.service /etc/systemd/system/syslog-stat.service

systemctl enable syslog-stat
systemctl start syslog-stat

######################################### Mikrotik managment ##########################################################

настраиваем параметры доступа по ssh к роутеру в админке (login|password|port)  http://[IP]/admin/customers/control-options.php

указываем в роутере (http://[IP]/admin/devices/) внешние и внутренние интерфейсы, включаем использование шейперов, dhcp-сервера (не нужно, если исопльзуем dnsmasq)

Добавляем правила в фаервол:

/ip firewall filter

add action=jump chain=forward comment="users set" in-interface-list=WAN jump-target=Users
add action=jump chain=forward jump-target=Users out-interface-list=WAN

#указанные выше правила надо поставить выше этих дефалтных:
#add action=drop chain=forward comment="drop forward invalid" connection-state=invalid
#add action=accept chain=forward comment=related,established connection-state=established,related

#А эти правила должны быть ниже дефолтных
add action=reject chain=forward comment="deny default wan" in-interface-list=WAN log=yes log-prefix=unk_wan: reject-with=icmp-network-unreachable 
add action=drop chain=forward out-interface-list=WAN

шейпер:
/queue tree
add max-limit=[YOU BANDWIDTH] name=upload_root_[WAN_INTERFACE_NAME] parent=[WAN_INTERFACE_NAME] queue=pcq-upload-default
add name=download_root_[LAN_INTERFACE_NAME] parent=[LAN_INTERFACE_NAME] queue=pcq-download-default

запускаем /opt/Eye/scripts/sync_mikrotik.pl
Скрипт создаст правила фильтрации и шейпера

#dhcp script
#ROS6
/tool fetch mode=http keep-result=no url="http://<STAT_IP_OR_HOSTNAME>/api.php\?login=<LOGIN>&api_key=<API_CUSTOMER_KEY>&send=dhcp&mac=$leaseActMAC&ip=$leaseActIP&action=$leaseBound&hostname=$"lease-hostname""
#ROS7
/tool fetch mode=http keep-result=no url="http://<STAT_IP_OR_HOSTNAME>/api.php?login=<LOGIN>&api_key=<API_CUSTOMER_KEY>&send=dhcp&mac=$leaseActMAC&ip=$leaseActIP&action=$leaseBound&hostname=$"lease-hostname""

#########################################################################################################################
