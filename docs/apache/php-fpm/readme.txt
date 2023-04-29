a2dismod php7.4
a2dismod mpm_prefork
a2enmod mpm_event

apt install php-fpm libapache2-mod-fcgid

a2enconf php7.4-fpm
a2enmod proxy

apachectl configtest

systemctl enable php7.4-fpm.service
systemctl restart php7.4-fpm.service

systemctl restart apache2

#test
# apachectl -M | grep 'mpm'
 mpm_event_module (shared)

# apachectl -M | grep 'proxy'
 proxy_module (shared)
 proxy_fcgi_module (shared)
