# Око

Обычный быдло-кодинг, разросшийся за последние 18 лет. Выкладываю сюда - может кого-то сподвигнет сделать что-то своё нормально).

## 📋 Обзор

Eye — это комплексная система сетевого мониторинга и управления, обеспечивающая:

- Управление выходом в интернет для IP-адресов через маршрутизаторы MikroTik/Linux (настраивается фильтрация, лимиты трафика в сутки и за месяц).
- Ограничение скорости доступа (реализовано на MikroTik; функциональность для Linux ранее существовала, но была удалена).
- Генерация конфигураций для DHCP-серверов  (dnsmasq, MikroTik).
- Генерация конфигураций для DNS-сервера (BIND).
- Опрос коммутаторов и маршрутизаторов по SNMP с последующим анализом и определением портов подключения IP-адресов.
- Мониторинг и управление сетевыми устройствами.
- Анализ трафика и контроль пропускной способности.
- Сбор и анализ Syslog-сообщений.
- Статистика и отчёты в реальном времени.

---

# Eye Monitoring System — Руководство по установке

### Системные требования

#### Поддерживаемые дистрибутивы:

* ALT Linux 11.1+
* Debian 11+
* Ubuntu 20.04+

---

## 🚀 Быстрая установка с помощью скрипта

Для автоматизированной установки используйте установочный скрипт:

```bash
# Сделать скрипт исполняемым
chmod +x install-eye.sh

# Запуск установки
sudo ./install-eye.sh

# Автоматический режим (минимальное вмешательство)
sudo ./install-eye.sh --auto
````

### Возможности скрипта

* Поддержка ALT Linux, Debian и Ubuntu
* Поддержка двух СУБД: MySQL/MariaDB или PostgreSQL (экспериментально, не для production!)
* Многоязычный интерфейс: английский или русский
* Автоматическая установка зависимостей
* Настройка конфигурационных файлов
* Инициализация базы данных

---

## 🔧 Ручная установка


### Установка требуемых пакетов

#### Для ALT Linux:

```bash
# Обновление репозиториев
apt-get update

# Установка пакетов Eye
apt-get install git xxd wget fping hwdata

# База данных (выберите одну)
apt-get install mariadb-server mariadb-client    # Для MySQL
# ИЛИ
apt-get install postgresql postgresql-client     # Для PostgreSQL

# Веб-сервер и PHP
apt-get install apache2 php8.2 php8.2-mysqlnd php8.2-pgsql \
php8.2-intl php8.2-mbstring php8.2-fpm-fcgi apache2-mod_fcgid

# Perl-модули
apt-get install perl-Net-Patricia perl-NetAddr-IP perl-Config-Tiny \
perl-Net-DNS perl-DateTime perl-Net-Ping \
perl-Net-Netmask perl-Text-Iconv perl-Net-SNMP \
perl-Net-Telnet perl-DBI \
perl-Parallel-ForkManager perl-Proc-Daemon \
perl-DateTime-Format-DateParse perl-DateTime-Format-Strptime \
perl-Net-OpenSSH perl-File-Tail perl-Tie-File \
perl-Crypt-Rijndael perl-Crypt-CBC perl-CryptX perl-Crypt-DES \
perl-File-Path-Tiny perl-Expect perl-Proc-ProcessTable \
perl-Text-CSV \
perl-DBD-Pg perl-DBD-mysql

# Дополнительные сервисы
apt-get install dnsmasq syslog-ng syslog-ng-journal pwgen
```

#### Для Debian / Ubuntu:

```bash
# Обновление репозиториев
apt-get update

# Установка пакетов Eye
apt-get install git xxd bsdmainutils pwgen wget fping ieee-data

# База данных (выберите одну)
apt-get install mariadb-server mariadb-client    # Для MySQL
# ИЛИ
apt-get install postgresql postgresql-client     # Для PostgreSQL

# Веб-сервер и PHP
apt-get install apache2 php php-mysql php-pgsql php-bcmath \
php-intl php-mbstring php-date php-mail php-snmp php-zip \
php-db php-fpm libapache2-mod-fcgid

# Perl-модули
apt-get install -y perl \
libnet-patricia-perl libnetaddr-ip-perl libconfig-tiny-perl \
libnet-dns-perl libdatetime-perl libnet-netmask-perl \
libtext-iconv-perl libnet-snmp-perl libnet-telnet-perl \
libdbi-perl libparallel-forkmanager-perl libproc-daemon-perl \
libdatetime-format-dateparse-perl libnetwork-ipv4addr-perl \
libnet-openssh-perl libfile-tail-perl libdatetime-format-strptime-perl \
libcrypt-rijndael-perl libcrypt-cbc-perl libcryptx-perl \
libcrypt-des-perl libfile-path-tiny-perl libexpect-perl \
libtext-csv-perl \
libdbd-pg-perl libdbd-mysql-perl

# Дополнительные сервисы
apt-get install dnsmasq syslog-ng
```

---

### Создание пользователя и группы

```bash
# Создание группы
groupadd --system eye

# Создание пользователя
adduser --system --disabled-password --disabled-login \
--ingroup eye --home=/opt/Eye eye

# Создание каталога
mkdir -p /opt/Eye
chown eye:eye /opt/Eye
chmod 770 /opt/Eye

# Опционально: добавить nagios в группу eye
usermod -a -G eye nagios
```

---

### Загрузка и установка исходного кода

```bash
# Клонирование репозитория
git clone https://github.com/rajven/Eye

# Создание структуры каталогов
mkdir -p /opt/Eye/scripts/cfg
mkdir -p /opt/Eye/scripts/log
mkdir -p /opt/Eye/html/cfg
mkdir -p /opt/Eye/html/js
mkdir -p /opt/Eye/docs

# Установка прав
chmod 750 /opt/Eye
chmod 770 /opt/Eye/scripts/log
chmod 750 /opt/Eye/scripts

# Копирование файлов
cp -R scripts/ /opt/Eye/
cp -R html/ /opt/Eye/
cp -R docs/ /opt/Eye/

# Установка прав
chown -R eye:eye /opt/Eye
```

---

### Применение патча SNMP SHA512 (опционально)

```bash
# Патч для поддержки SNMPv3 SHA512
# Файл: /opt/Eye/docs/patches/sha512.patch
# Для ALT Linux: /opt/Eye/docs/patches/sha512.alt.patch

# Можете применить патч или просто заменить модуль
cp /opt/Eye/docs/patches/USM.pm /usr/share/perl5/Net/SNMP/Security/USM.pm
```

---

### Загрузка дополнительных JavaScript-библиотек

```bash
# Создание каталогов
mkdir -p /opt/Eye/html/js/jq
mkdir -p /opt/Eye/html/js/select2
mkdir -p /opt/Eye/html/js/jstree

# Загрузка jQuery
wget https://code.jquery.com/jquery-3.7.0.min.js \
-O /opt/Eye/html/js/jq/jquery.min.js

# Загрузка Select2
wget https://github.com/select2/select2/archive/4.0.12.tar.gz -O 4.0.12.tar.gz
tar -xzf 4.0.12.tar.gz -C /opt/Eye/html/js/select2/ \
--strip-components=2 select2-4.0.12/dist
rm -f 4.0.12.tar.gz

# Загрузка jsTree
wget https://github.com/vakata/jstree/archive/3.3.12.tar.gz -O jstree.tar.gz
tar -xzf jstree.tar.gz -C /opt/Eye/html/js/
mv /opt/Eye/html/js/jstree-3.3.12/dist/* /opt/Eye/html/js/jstree
rm -rf /opt/Eye/html/js/jstree-3.3.12
rm -f jstree.tar.gz
```

---

### Настройка базы данных

#### MySQL / MariaDB:

```bash
systemctl enable mariadb
systemctl start mariadb

mysql_secure_installation

mysql -u root -p < /opt/Eye/docs/databases/mysql/en/create_db.sql
mysql -u root -p stat < /opt/Eye/docs/databases/mysql/en/data.sql

mysql -u root -p <<EOF
CREATE USER 'stat'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON stat.* TO 'stat'@'localhost';
FLUSH PRIVILEGES;
EOF
```

#### PostgreSQL:

```bash
systemctl enable postgresql
systemctl start postgresql

sudo -u postgres psql -f /opt/Eye/docs/databases/postgres/en/create_db.sql
sudo -u postgres psql -d stat -f /opt/Eye/docs/databases/postgres/en/data.sql
sudo -u postgres psql -c "ALTER USER stat WITH PASSWORD 'your_password';"

# В pg_hba.conf добавить строку:
# local   stat            stat                                    md5
```

---

### Конфигурационные файлы

```bash
cp /opt/Eye/html/cfg/config.sample.php /opt/Eye/html/cfg/config.php
cp /opt/Eye/scripts/cfg/config.sample /opt/Eye/scripts/cfg/config

ENCRYPTION_KEY=$(pwgen 16 1)
ENCRYPTION_IV=$(tr -dc 0-9 </dev/urandom | head -c 16)

chown -R eye:eye /opt/Eye/html/cfg /opt/Eye/scripts/cfg
chmod 660 /opt/Eye/html/cfg/config.php /opt/Eye/scripts/cfg/config
```

---

### Настройка Apache и PHP

```bash
cp /opt/Eye/docs/apache/000-default.conf /etc/apache2/sites-available/
a2enmod setenvif proxy proxy_fcgi
cp /opt/Eye/docs/php-fpm/eye.conf /etc/php/8.2/fpm/pool.d/

cp /opt/Eye/docs/sudoers.d/www-data /etc/sudoers.d/eye
sed -i 's/www-data/eye/g' /etc/sudoers.d/eye
chmod 440 /etc/sudoers.d/eye

systemctl restart apache2
systemctl restart php8.2-fpm
```

---

### Cron и Logrotate

```bash
cp /opt/Eye/docs/cron/stat /etc/cron.d/eye
chmod 644 /etc/cron.d/eye

cp /opt/Eye/docs/logrotate/dnsmasq /etc/logrotate.d/dnsmasq-eye
cp /opt/Eye/docs/logrotate/scripts /etc/logrotate.d/eye-scripts
```

---

### Дополнительные сервисы (опционально)

#### DHCP (dnsmasq):

```bash
cp /etc/dnsmasq.conf /etc/dnsmasq.conf.backup
cat /opt/Eye/docs/addons/dnsmasq.conf > /etc/dnsmasq.conf

cp /opt/Eye/docs/systemd/dhcp-log.service /etc/systemd/system/
cp /opt/Eye/docs/systemd/dhcp-log-truncate.service /etc/systemd/system/

systemctl enable dnsmasq dhcp-log dhcp-log-truncate
systemctl start dnsmasq
```

#### Syslog-ng:

```bash
cp /etc/syslog-ng/syslog-ng.conf /etc/syslog-ng/syslog-ng.conf.backup
mkdir -p /etc/syslog-ng/conf.d
cp /opt/Eye/docs/syslog-ng/eye.conf /etc/syslog-ng/conf.d/

cp /opt/Eye/docs/systemd/syslog-stat.service /etc/systemd/system/

systemctl enable syslog-ng syslog-stat
systemctl restart syslog-ng
systemctl start syslog-stat
```

#### NetFlow:

```bash
cp /opt/Eye/docs/systemd/eye-statd.service /etc/systemd/system/
systemctl enable eye-statd
```

#### Stat-sync:

```bash
cp /opt/Eye/docs/systemd/stat-sync.service /etc/systemd/system/
systemctl enable stat-sync.service
```

---

### Импорт базы MAC-адресов

```bash
cd /opt/Eye/scripts/utils/mac-oids/
bash download-macs.sh
perl update-mac-vendors.pl
```

---

## 🌐 Доступ к веб-интерфейсу

* URL: `http://your-server-ip/`
* Админ-панель: `http://your-server-ip/admin/`
* Логин: `admin`
* Пароль: `admin`

---

## 🔐 Рекомендации по безопасности

* **НЕМЕДЛЕННО смените пароль администратора**
* Сгенерируйте новый API-ключ
* Ограничьте доступ с помощью firewall
* Используйте HTTPS
* Делайте регулярные обновления и резервные копии

---

## 📊 Интеграция с MikroTik

### Firewall:

Правила с fasttrack надо вылкючить!!!

```routeros
/ip firewall filter
set disabled=yes [ find action =fasttrack-connection ]
set disabled=yes [ find chain =forward and comment~"established" ]
add action=drop chain=forward comment="deny unknown ips" out-interface-list=WAN src-address-list=!group_all
add action=jump chain=forward comment="users set" in-interface-list=WAN jump-target=Users
add action=jump chain=forward jump-target=Users out-interface-list=WAN
add action=accept chain=forward comment=related,established connection-state=established,related
add action=reject chain=forward comment="deny default wan" in-interface-list=WAN reject-with=icmp-network-unreachable
add action=drop chain=forward out-interface-list=WAN
```


### Управление трафиком:

Для внешнего и внутренних интерфейсов надо создать родительсую очередь с вашим каналом в интернет. Имя очререди образовывается из имени интерфейса!

```routeros
/queue tree
add max-limit=100M name=upload_root_ether1 parent=ether1 queue=pcq-upload-default
add name=download_root_bridge parent=bridge queue=pcq-download-default
```

### DHCP-скрипт (RouterOS 6):

```routeros
/tool fetch mode=http keep-result=no url="http://<EYE_IP>/api.php?login=<LOGIN>&api_key=<API_KEY>&send=dhcp&mac=\$leaseActMAC&ip=\$leaseActIP&action=\$leaseBound&hostname=\$lease-hostname"
```

С https:
```routeros
/tool fetch mode=https keep-result=no url="https://<EYE_URL>/api.php?login=<LOGIN>&api_key=<API_KEY>&send=dhcp&mac=\$leaseActMAC&ip=\$leaseActIP&action=\$leaseBound&hostname=\$lease-hostname"
```


### DHCP-скрипт (RouterOS 7):

```routeros
/tool fetch url="http://<EYE_IP>/api.php?login=<LOGIN>&api_key=<API_KEY>&send=dhcp&mac=$leaseActMAC&ip=$leaseActIP&action=$leaseBound&hostname=$"lease-hostname"" mode=http keep-result=no
```

С https:
```routeros
/tool fetch url="https://<EYE_URL>/api.php?login=<LOGIN>&api_key=<API_KEY>&send=dhcp&mac=$leaseActMAC&ip=$leaseActIP&action=$leaseBound&hostname=$"lease-hostname"" mode=https keep-result=no
```

Имя dhcp-сервера должно быть образовано от имени интерфейса, на котором он работает. Т.е. при работе на интерфейсе bridge => dhcp-bridge

### NetFlow:

```routeros
# RouterOS 6
/ip traffic-flow
set enabled=yes

# RouterOS 7
/ip traffic-flow
set enabled=yes interfaces=WAN

/ip traffic-flow target
add dst-address=<NETFLOW_SERVER_IP> port=2055
```

---
