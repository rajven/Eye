<?php

if (!defined("CONFIG"))die("Not defined");

define("PORT_STATUS_OID",".1.3.6.1.2.1.2.2.1.8.");
define("PORT_ADMIN_STATUS_OID",".1.3.6.1.2.1.2.2.1.7.");
define("PORT_SPEED_OID",".1.3.6.1.2.1.2.2.1.5.");
define("PORT_ERRORS_OID",".1.3.6.1.2.1.2.2.1.14.");
define("PORT_VLAN_OID",".1.3.6.1.2.1.17.7.1.4.5.1.1.");

define("MAC_TABLE_OID",".1.3.6.1.2.1.17.7.1.2.2.1.2");
define("MAC_TABLE_OID2",".1.3.6.1.2.1.17.4.3.1.2");
define("MAC_TABLE_STR_OID",".1.3.6.1.2.1.17.4.3.1.2");
define("MAC_TABLE_STR_OID2","1.3.6.1.2.1.17.7.1.2.2.1.2");

define("ELTEX_SFP_STATUS",".1.3.6.1.4.1.89.90.1.2.1.3");
define("ELTEX_SFP_VENDOR",".1.3.6.1.4.1.35265.1.23.53.1.1.1.5");
define("ELTEX_SFP_SN",".1.3.6.1.4.1.35265.1.23.53.1.1.1.6");
define("ELTEX_SFP_FREQ",".1.3.6.1.4.1.35265.1.23.53.1.1.1.4");
define("ELTEX_SFP_LENGTH",".1.3.6.1.4.1.35265.1.23.53.1.1.1.8");

define("CISCO_DESCR",".1.3.6.1.2.1.1.1.0");
define("CISCO_MODULES",".1.3.6.1.2.1.47.1.1.1.1.7");
define("CISCO_SFP_SENSORS",".1.3.6.1.4.1.9.9.91.1.1.1.1.4");
define("CISCO_SFP_PRECISION",".1.3.6.1.4.1.9.9.91.1.1.1.1.3");
define("CISCO_VLAN_OID",".1.3.6.1.4.1.9.9.9.46.1.3.1.1.2");

define("IFMIB_IFINDEX",".1.3.6.1.2.1.2.2.1.1");
define("IFMIB_IFINDEX_MAP",".1.3.6.1.2.1.17.1.4.1.2");
define("IFMIB_IFDESCR",".1.3.6.1.2.1.2.2.1.2");
define("IFMIB_IFNAME",".1.3.6.1.2.1.31.1.1.1.1");

define("HUAWEI_SFP_VENDOR",".1.3.6.1.4.1.2011.5.25.31.1.1.2.1.11");
define("HUAWEI_SFP_SPEED",".1.3.6.1.4.1.2011.5.25.31.1.1.2.1.2");
define("HUAWEI_SFP_VOLT",".1.3.6.1.4.1.2011.5.25.31.1.1.3.1.6");
define("HUAWEI_SFP_OPTRX",".1.3.6.1.4.1.2011.5.25.31.1.1.3.1.32");
define("HUAWEI_SFP_OPTTX",".1.3.6.1.4.1.2011.5.25.31.1.1.3.1.33");
define("HUAWEI_SFP_BIASCURRENT",".1.3.6.1.4.1.2011.5.25.31.1.1.3.1.31");
define("HUAWEI_SFP_RX",".1.3.6.1.4.1.2011.5.25.31.1.1.3.1.8");
define("HUAWEI_SFP_TX",".1.3.6.1.4.1.2011.5.25.31.1.1.3.1.9");

define("PETH_PSE_PORT_ADMIN_ENABLE",".1.3.6.1.2.1.105.1.1.1.3.1");
define("HUAWEI_POE_OID",".1.3.6.1.4.1.2011.5.25.195.3.1.3");
define("ALLIED_POE_OID",".1.3.6.1.2.1.105.1.1.1.3.1");
define("HP_POE_OID",".1.3.6.1.2.1.105.1.1.1.3.1");
define("NETGEAR_POE_OID",".1.3.6.1.4.1.4526.11.15.1.1.1.6.1");
define("MIKROTIK_POE_OID",".1.3.6.1.4.1.14988.1.1.15.1.1.3");

//INTERFACE ID
define("MIKROTIK_POE_INT",".1.3.6.1.4.1.14988.1.1.15.1.1.1");
// INTERFACE NAMES
define("MIKROTIK_POE_INT_NAMES",".1.3.6.1.4.1.14988.1.1.15.1.1.2");
// VOLTAGE IN DV (DECIVOLT)
define("MIKROTIK_POE_VOLT",".1.3.6.1.4.1.14988.1.1.15.1.1.4");
// CURRENT IN MA
define("MIKROTIK_POE_CURRENT",".1.3.6.1.4.1.14988.1.1.15.1.1.5");
// POWER USAGE IN DW (DEVIWATT)
define("MIKROTIK_POE_USAGE",".1.3.6.1.4.1.14988.1.1.15.1.1.6");

define("SYSINFO_MIB",".1.3.6.1.2.1.1");

define("L_ERROR",0);
define("L_WARNING",1);
define("L_INFO",2);
define("L_VERBOSE",3);
define("L_DEBUG",255);

?>
