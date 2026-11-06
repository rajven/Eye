<?php

if (!defined("CONFIG"))die("Not defined");

setlocale(LC_ALL, 'ru_RU.UTF8');
define("HTML_LANG","russian");

define("HTML_STYLE","eye");

define("DB_HOST","localhost");
//db_type => mysql OR pgsql
define("DB_TYPE","db_type");
define("DB_NAME","stat");
define("DB_USER","user");
define("DB_PASS","password");

define("CACTI_DB_HOST","localhost");
define("CACTI_DB_NAME","cacti");
define("CACTI_DB_USER","");
define("CACTI_DB_PASS","");

define("IPCAM_GROUP_ID","5");

#snmp timeout in microsecond
define("SNMP_timeout","500000");
#snmp retry after timeout
define("SNMP_retry","1");

#crypt config - CHANGE IT!!!
define("ENCRYPTION_KEY","!!!CHANGE_ME!!!");
define("ENCRYPTION_IV","0123456789012345");

#session timeout, sec
define("SESSION_LIFETIME","86400");

#the api keys of the customers are kept as a keyed hash of them, and the key of that hash
#is ENCRYPTION_KEY above. changing ENCRYPTION_KEY invalidates every api key of the
#installation, each customer then needs a new key

#verify the ssl certificate of the zabbix server. the default false lets a self-signed
#certificate work; set true when the certificate is signed or its CA is imported into
#the system bundle of the server
define("ZABBIX_SSL_VERIFY", false);

?>
