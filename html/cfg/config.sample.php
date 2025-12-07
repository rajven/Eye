<?php

if (!defined("CONFIG"))die("Not defined");

setlocale(LC_ALL, 'ru_RU.UTF8');
define("HTML_LANG","russian");

define("HTML_STYLE","white");

define("DB_HOST","localhost");
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
define("ENCRYPTION_IV","123456782345");

#session timeout, sec
define("SESSION_LIFETIME","86400");

?>
