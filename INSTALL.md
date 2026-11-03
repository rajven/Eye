## 🔧 Manual Installation

### Installing Required Packages

#### For ALT Linux:

```bash
# Update repositories
apt-get update

# Install Eye packages
apt-get install git xxd wget fping hwdata

# Database (choose one)
apt-get install mariadb-server mariadb-client    # For MySQL
# OR
apt-get install postgresql postgresql-client     # For PostgreSQL

# Web server and PHP
apt-get install apache2 php8.2 php8.2-mysqlnd php8.2-pgsql \
php8.2-intl php8.2-mbstring php8.2-fpm-fcgi apache2-mod_fcgid

# Perl modules
apt-get install perl-Net-Patricia perl-NetAddr-IP perl-Config-Tiny \
perl-Net-DNS perl-DateTime perl-Net-Ping \
perl-Net-Netmask perl-Text-Iconv perl-Net-SNMP \
perl-Net-Telnet perl-DBI \
perl-Parallel-ForkManager perl-Proc-Daemon \
perl-DateTime-Format-DateParse perl-DateTime-Format-Strptime \
perl-Net-OpenSSH perl-File-Tail perl-Tie-File \
perl-Crypt-Rijndael perl-Crypt-CBC perl-CryptX perl-Crypt-DES \
perl-File-Path-Tiny perl-Expect perl-Proc-ProcessTable \
perl-Text-CSV perl-Log-Log4perl \
perl-DBD-Pg perl-DBD-mysql

# Additional services
apt-get install dnsmasq syslog-ng syslog-ng-journal pwgen
```

#### For Debian / Ubuntu:

```bash
# Update repositories
apt-get update

# Install Eye packages
apt-get install git xxd bsdmainutils pwgen wget fping ieee-data

# Database (choose one)
apt-get install mariadb-server mariadb-client    # For MySQL
# OR
apt-get install postgresql postgresql-client     # For PostgreSQL

# Web server and PHP
apt-get install apache2 php php-mysql php-pgsql php-bcmath \
php-intl php-mbstring php-date php-mail php-snmp php-zip \
php-db php-fpm libapache2-mod-fcgid

# Perl modules
apt-get install -y perl \
libnet-patricia-perl libnetaddr-ip-perl libconfig-tiny-perl \
libnet-dns-perl libdatetime-perl libnet-netmask-perl \
libtext-iconv-perl libnet-snmp-perl libnet-telnet-perl \
libdbi-perl libparallel-forkmanager-perl libproc-daemon-perl \
libdatetime-format-dateparse-perl libnetwork-ipv4addr-perl \
libnet-openssh-perl libfile-tail-perl libdatetime-format-strptime-perl \
libcrypt-rijndael-perl libcrypt-cbc-perl libcryptx-perl \
libcrypt-des-perl libfile-path-tiny-perl libexpect-perl \
libtext-csv-perl liblog-log4perl-perl \
libdbd-pg-perl libdbd-mysql-perl

# Additional services
apt-get install dnsmasq syslog-ng
```

---

### Creating User and Group

```bash
# Create group
groupadd --system eye

# Create user
adduser --system --disabled-password --disabled-login \
--ingroup eye --home=/opt/Eye eye

# Create directory
mkdir -p /opt/Eye
chown eye:eye /opt/Eye
chmod 770 /opt/Eye

# Optional: add nagios to eye group
usermod -a -G eye nagios
```

---

### Downloading and Installing Source Code

```bash
# Clone repository
git clone https://github.com/rajven/Eye

# Create directory structure
mkdir -p /opt/Eye/scripts/cfg
mkdir -p /opt/Eye/scripts/log
mkdir -p /opt/Eye/html/cfg
mkdir -p /opt/Eye/html/js
mkdir -p /opt/Eye/docs

# Set permissions
chmod 750 /opt/Eye
chmod 770 /opt/Eye/scripts/log
chmod 750 /opt/Eye/scripts

# Copy files
cp -R scripts/ /opt/Eye/
cp -R html/ /opt/Eye/
cp -R docs/ /opt/Eye/

# Set ownership
chown -R eye:eye /opt/Eye
```

---

### Applying SNMP SHA512 Patch (Optional)

```bash
# Patch for SNMPv3 SHA512 support
# File: /opt/Eye/docs/patches/sha512.patch
# For ALT Linux: /opt/Eye/docs/patches/sha512.alt.patch

# You can apply the patch or simply replace the module
cp /opt/Eye/docs/patches/USM.pm /usr/share/perl5/Net/SNMP/Security/USM.pm
```

---

### Downloading Additional JavaScript Libraries

```bash
# Create directories
mkdir -p /opt/Eye/html/js/jq
mkdir -p /opt/Eye/html/js/jstree

# Download jQuery
wget https://code.jquery.com/jquery-3.7.0.min.js \
-O /opt/Eye/html/js/jq/jquery.min.js


# Download jsTree
wget https://github.com/vakata/jstree/archive/3.3.12.tar.gz -O jstree.tar.gz
tar -xzf jstree.tar.gz -C /opt/Eye/html/js/
mv /opt/Eye/html/js/jstree-3.3.12/dist/* /opt/Eye/html/js/jstree
rm -rf /opt/Eye/html/js/jstree-3.3.12
rm -f jstree.tar.gz
```

---

### Database Configuration

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

# Add this line to pg_hba.conf:
# local   stat            stat                                    md5
```

---

### Configuration Files

```bash
cp /opt/Eye/html/cfg/config.sample.php /opt/Eye/html/cfg/config.php
cp /opt/Eye/scripts/cfg/config.sample /opt/Eye/scripts/cfg/config

ENCRYPTION_KEY=$(pwgen 16 1)
ENCRYPTION_IV=$(tr -dc 0-9 </dev/urandom | head -c 16)

chown -R eye:eye /opt/Eye/html/cfg /opt/Eye/scripts/cfg
chmod 660 /opt/Eye/html/cfg/config.php /opt/Eye/scripts/cfg/config
```

---

### Apache and PHP Configuration

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

### Cron and Logrotate

```bash
cp /opt/Eye/docs/cron/stat /etc/cron.d/eye
chmod 644 /etc/cron.d/eye

cp /opt/Eye/docs/logrotate/dnsmasq /etc/logrotate.d/dnsmasq-eye
cp /opt/Eye/docs/logrotate/scripts /etc/logrotate.d/eye-scripts
```

---

### Additional Services (Optional)

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

### MAC Address Database Import

```bash
cd /opt/Eye/scripts/utils/mac-oids/
bash download-macs.sh
perl update-mac-vendors.pl
```

## 📊 MikroTik Integration

### Firewall:

Fasttrack rules must be DISABLED!!!

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

### Working with DHCP Server

For DHCP server events on MikroTik, you need to attach a script that will notify Eye about IP address leases/releases. Both GET and POST requests are supported.
Using HTTPS & POST is strongly recommended.

### DHCP Script (RouterOS 6):

```routeros
/tool fetch mode=http keep-result=no url="http://<EYE_IP>/api.php?login=<LOGIN>&api_key=<API_KEY>&send=dhcp&mac=\$leaseActMAC&ip=\$leaseActIP&action=\$leaseBound&hostname=\$lease-hostname"
```

With HTTPS:
```routeros
/tool fetch mode=https keep-result=no url="https://<EYE_URL>/api.php?login=<LOGIN>&api_key=<API_KEY>&send=dhcp&mac=\$leaseActMAC&ip=\$leaseActIP&action=\$leaseBound&hostname=\$lease-hostname"
```

### DHCP Script (RouterOS 7):

```routeros
/tool fetch url="http://<EYE_IP>/api.php"  mode=http  http-method=post \
    http-data="login=<LOGIN>&api_key=<API_KEY>&send=dhcp&mac=$leaseActMAC&ip=$leaseActIP&action=$leaseBound&hostname=$"lease-hostname"" \
    keep-result=no
```

With HTTPS:
```routeros
/tool fetch url="https://<EYE_DNS_NAME>/api.php"  mode=https  http-method=post \
    http-data="login=<LOGIN>&api_key=<API_KEY>&send=dhcp&mac=$leaseActMAC&ip=$leaseActIP&action=$leaseBound&hostname=$"lease-hostname"" \
    keep-result=no
```

The DHCP server name should be derived from the interface name it operates on. E.g., when running on interface `bridge` => `dhcp-bridge`

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

## HTTP API

All methods are called on `/api.php` with the credentials of a customer of the interface:
`api_login=<login>&api_key=<key>`. The key is shown once, at the creation of the customer.

Read-only records of any table: `get=table_record`, `get=table_list` with `table=<name>`,
`filter={"column":"value"}`, `limit`, `offset`. The passwords and the api keys never
leave the answer.

The entities of eye carry the full set of methods, where `<e>` is one of `group`
(an organizational unit), `user`, `address` (an ip record of a subscriber), `filter`,
`filtergroup`, `filtergrouprule`, `queue` (a shaper), `device`:

| method | meaning |
|---|---|
| `get=<e>s` | the search: `search=<substring>`, the exact columns of the entity, `limit`, `offset` |
| `get=<e>s&id=<id>` | one record, `&with=addresses,devices` adds the records which hang on it |
| `send=add_<e>` | creation, the fields of the record come in a json body or in the query |
| `send=update_<e>&id=<id>` | editing of the allowed columns |
| `send=del_<e>&id=<id>` | removal; a record which is in use answers 400 with the list of its holders, `force=1` releases the references first: the shapers and the groups of filters are taken away from the subscribers, the units and the rules, the subscribers of a removed organizational unit move to the default one |

The rights are the same as in the interface: a customer with the view only rights reads
but changes nothing (403). An unknown field, or a field which belongs to another record,
is answered by 400 with the explanation, nothing is skipped in silence.

```bash
# a subscriber into a new organizational unit, with his address and his device
curl -s "http://eye.local/api.php?api_login=admin&api_key=SECRET&send=add_group" \
     -H 'Content-Type: application/json' -d '{"ou_name":"offices","enabled":1}'
curl -s "http://eye.local/api.php?api_login=admin&api_key=SECRET&send=add_user" \
     -H 'Content-Type: application/json' -d '{"login":"petrov","ou_id":14}'
curl -s "http://eye.local/api.php?api_login=admin&api_key=SECRET&send=add_address" \
     -H 'Content-Type: application/json' -d '{"user_id":1,"ip":"10.10.20.20","mac":"aa:bb:cc:dd:ee:ff"}'
curl -s "http://eye.local/api.php?api_login=admin&api_key=SECRET&get=users&ip=10.10.20.20&with=devices"
```

## Important!

Do not modify system scripts! If you need to change something, create a copy of the script and work with that. Otherwise, your changes will be overwritten during use automatic updates.
