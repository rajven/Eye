<?php

if (!defined("CONFIG"))die("Not defined");

define("PORT_STATUS_OID",".1.3.6.1.2.1.2.2.1.8");
define("PORT_ADMIN_STATUS_OID",".1.3.6.1.2.1.2.2.1.7");
define("PORT_SPEED_OID",".1.3.6.1.2.1.2.2.1.5");
define("PORT_ERRORS_OID",".1.3.6.1.2.1.2.2.1.14");

//VLANS
define("dot1qVlanStaticName",".1.3.6.1.2.1.17.7.1.4.3.1.1");
define("dot1qVlanStaticEgressPorts",".1.3.6.1.2.1.17.7.1.4.3.1.2");
define("dot1qVlanForbiddenEgressPorts",".1.3.6.1.2.1.17.7.1.4.3.1.3");
define("dot1qVlanStaticUntaggedPorts",".1.3.6.1.2.1.17.7.1.4.3.1.4");
define("dot1qVlanStaticRowStatus",".1.3.6.1.2.1.17.7.1.4.3.1.5");
define("dot1qPortVlanEntry",".1.3.6.1.2.1.17.7.1.4.5.1.1");

//fucking Cisco
//vlan list
define("vtpVlanName",".1.3.6.1.4.1.9.9.46.1.3.1.1.4.1");
define("vtpVlanState",".1.3.6.1.4.1.9.9.46.1.3.1.1.2");
//port vlan member - bit => port number
define("vmMembershipSummaryMemberPorts",".1.3.6.1.4.1.9.9.68.1.2.1.1.2");
//encapsulation type ( INTEGER {isl(1),dot10(2),lane(3),dot1Q(4),negotiate(5)})
define("vlanTrunkPortEncapsulationType",".1.3.6.1.4.1.9.9.46.1.6.1.1.3");
//trunk vlan - bit => vlan number
define("vlanTrunkPortVlansEnabled",".1.3.6.1.4.1.9.9.46.1.6.1.1.4");
//native vlan - all ports always exists
define("vlanTrunkPortNativeVlan",".1.3.6.1.4.1.9.9.46.1.6.1.1.5");
//pvid - if port exists => port mode access
define("vmVlanPvid",".1.3.6.1.4.1.9.9.68.1.2.2.1.2");

//tp-link
define("TPLINK_dot1qPortVlanEntry",".1.3.6.1.4.1.11863.6.14.1.1.1.1.2");

define("IFMIB_IFINDEX",".1.3.6.1.2.1.2.2.1.1");
define("IFMIB_IFINDEX_MAP",".1.3.6.1.2.1.17.1.4.1.2");
define("IFMIB_IFDESCR",".1.3.6.1.2.1.2.2.1.2");
define("IFMIB_IFNAME",".1.3.6.1.2.1.31.1.1.1.1");
define("IFMIB_IFALIAS",".1.3.6.1.2.1.31.1.1.1.18");

define("MAC_TABLE_OID",".1.3.6.1.2.1.17.4.3.1.2");
define("MAC_TABLE_OID2",".1.3.6.1.2.1.17.7.1.2.2.1.2");

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

define("HUAWEI_SFP_VENDOR",".1.3.6.1.4.1.2011.5.25.31.1.1.2.1.11");
define("HUAWEI_SFP_SPEED",".1.3.6.1.4.1.2011.5.25.31.1.1.2.1.2");
define("HUAWEI_SFP_VOLT",".1.3.6.1.4.1.2011.5.25.31.1.1.3.1.6");
define("HUAWEI_SFP_OPTRX",".1.3.6.1.4.1.2011.5.25.31.1.1.3.1.32");
define("HUAWEI_SFP_OPTTX",".1.3.6.1.4.1.2011.5.25.31.1.1.3.1.33");
define("HUAWEI_SFP_BIASCURRENT",".1.3.6.1.4.1.2011.5.25.31.1.1.3.1.31");
define("HUAWEI_SFP_RX",".1.3.6.1.4.1.2011.5.25.31.1.1.3.1.8");
define("HUAWEI_SFP_TX",".1.3.6.1.4.1.2011.5.25.31.1.1.3.1.9");

//POE Default mib
//POE class
define("PETH_PSE_PORT_POE_CLASS",".1.3.6.1.2.1.105.1.1.1.10.1");
//POE enable
define("PETH_PSE_PORT_ADMIN_ENABLE",".1.3.6.1.2.1.105.1.1.1.3.1");

//SNR
// Class
define("SNR_POE_CLASS",".1.3.6.1.4.1.40418.7.100.26.10.1.9");
// Status
define("SNR_POE_OID",".1.3.6.1.4.1.40418.7.100.26.10.1.2");
// VOLTAGE
define("SNR_POE_VOLT",".1.3.6.1.4.1.40418.7.100.26.10.1.7");
// CURRENT
define("SNR_POE_CURRENT",".1.3.6.1.4.1.40418.7.100.26.10.1.6");
// POWER USAGE
define("SNR_POE_USAGE",".1.3.6.1.4.1.40418.7.100.26.10.1.5");

//Eltex
define("ELTEX_POE_OID",".1.3.6.1.4.1.14988.1.1.15.1.1.3");
// VOLTAGE
define("ELTEX_POE_VOLT",".1.3.6.1.4.1.89.108.1.1.3.1");
// CURRENT
define("ELTEX_POE_CURRENT",".1.3.6.1.4.1.89.108.1.1.4.1");
// POWER USAGE
define("ELTEX_POE_USAGE",".1.3.6.1.4.1.89.108.1.1.5.1");

// huawei
define("HUAWEI_POE_OID",".1.3.6.1.4.1.2011.5.25.195.3.1.3");
// VOLTAGE
define("HUAWEI_POE_VOLT",".1.3.6.1.4.1.2011.5.25.195.3.1.14");
// CURRENT
define("HUAWEI_POE_CURRENT",".1.3.6.1.4.1.4526.11.15.1.1.1.3.1");
// POWER USAGE
define("HUAWEI_POE_USAGE",".1.3.6.1.4.1.2011.5.25.195.3.1.10");

// AT
define("ALLIED_POE_OID",".1.3.6.1.2.1.105.1.1.1.3.1");
// VOLTAGE
define("ALLIED_POE_VOLT",".1.3.6.1.4.1.89.108.1.1.3.1");
// CURRENT
define("ALLIED_POE_CURRENT",".1.3.6.1.4.1.89.108.1.1.4.1");
// POWER USAGE
define("ALLIED_POE_USAGE",".1.3.6.1.4.1.89.108.1.1.5.1");

// netgear
define("NETGEAR_POE_OID",".1.3.6.1.4.1.4526.11.15.1.1.1.6.1");
// VOLTAGE
define("NETGEAR_POE_VOLT",".1.3.6.1.4.1.4526.11.15.1.1.1.4.1");
// CURRENT
define("NETGEAR_POE_CURRENT",".1.3.6.1.4.1.4526.11.15.1.1.1.3.1");
// POWER USAGE
define("NETGEAR_POE_USAGE",".1.3.6.1.4.1.4526.11.15.1.1.1.2.1");

// HP
define("HP_POE_OID",".1.3.6.1.2.1.105.1.1.1.3.1");
// VOLTAGE
define("HP_POE_VOLT",".1.3.6.1.4.1.25506.2.14.1.1.3.1");
// CURRENT
define("HP_POE_CURRENT",".1.3.6.1.4.1.25506.2.14.1.1.2.1");
// POWER USAGE
define("HP_POE_USAGE",".1.3.6.1.4.1.25506.2.14.1.1.4.1");

//MIKROTIK
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

//TP-Link
// index port in poe tables
define("TPLINK_POE_PORT_INDEX",".1.3.6.1.4.1.11863.6.56.1.1.2.1.1.1");
// INTEGER {off(0), turning-on(1), on(2), overload(3), short(4), nonstandard-pd(5),voltage-high(6), voltage-low(7),hardware-fault(8),overtemperature(9)
define("TPLINK_POE_STATUS",".1.3.6.1.4.1.11863.6.56.1.1.2.1.1.11");
//POE class -  INTEGER {class0(0),class1(1),class2(2),class3(3),class4(4),class-not-defined(7)}
define("TPLINK_POE_CLASS",".1.3.6.1.4.1.11863.6.56.1.1.2.1.1.10");
//POE Port Config - enable - 1, disable 0
define("TPLINK_POE_OID",".1.3.6.1.4.1.11863.6.56.1.1.2.1.1.2");    
//POE POWER, Displays the port's real time power supply in 0.1W.
define("TPLINK_POE_USAGE",".1.3.6.1.4.1.11863.6.56.1.1.2.1.1.7");
//POE CURRENT, Displays the port's real time current in 1mA.
define("TPLINK_POE_CURRENT",".1.3.6.1.4.1.11863.6.56.1.1.2.1.1.8");
//POE VOLT, Displays the port's real time voltage in 0.1V.
define("TPLINK_POE_VOLT",".1.3.6.1.4.1.11863.6.56.1.1.2.1.1.9");

//default mib for detect snmp work
//SNMPv2-MIB::system
define("SYSINFO_MIB",".1.3.6.1.2.1.1");
//sysDescr.0
define("SYS_DESCR_MIB",".1.3.6.1.2.1.1.1.0");

//ident Mikrotik
//MikroTik DHCP server
define("MIKROTIK_DHCP_SERVER",".1.3.6.1.2.1.9999.1.1.1.1.0");
//MikroTik RouterOS version - for patch mac-address-table
define("MIKROTIK_ROS_VERSION",".1.0.8802.1.1.2.1.3.4.0");

// log levels
define("L_ERROR",0);
define("L_WARNING",1);
define("L_INFO",2);
define("L_VERBOSE",3);
define("L_DEBUG",255);

//mysql field types
define('MYSQL_FIELD_DIGIT', array(1,2,3,4,5,8,9,246));
define('MYSQL_FIELD_STRING', array(252,253,254));

//lock device by snmp access
define('SNMP_LOCK_TIMEOUT',30);

?>
