a2dismod php8.1
a2dismod mpm_prefork
a2enmod mpm_event

apt install php-fpm libapache2-mod-fcgid

a2enconf php8.1-fpm
a2enmod proxy

mkdir -p /var/log/php-fpm/

apachectl configtest

systemctl enable php8.1-fpm.service
systemctl restart php8.1-fpm.service

systemctl restart apache2

#test
# apachectl -M | grep 'mpm'
 mpm_event_module (shared)

# apachectl -M | grep 'proxy'
 proxy_module (shared)
 proxy_fcgi_module (shared)
