#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use FindBin '$Bin';
use lib "$Bin/";
use strict;
use Time::Local;
use FileHandle;
use Data::Dumper;
use eyelib::config;
use eyelib::main;
use eyelib::net_utils;
use eyelib::database;
use eyelib::snmp;
use Net::SNMP qw(:snmp);

my $pethPsePortAdminEnable ='.1.3.6.1.2.1.105.1.1.1.3.1.';

my $huawei_poe_oid         ='.1.3.6.1.4.1.2011.5.25.195.3.1.3.';
my $allied_poe_oid         ='.1.3.6.1.2.1.105.1.1.1.3.1.';
my $hp_poe_oid             ='.1.3.6.1.2.1.105.1.1.1.3.1.';
my $netgear_poe_oid        ='.1.3.6.1.4.1.4526.11.15.1.1.1.6.1.';
my $mikrotik_poe_oid       ='.1.3.6.1.4.1.14988.1.1.15.1.1.3.';

my $admin_status_oid       ='.1.3.6.1.2.1.2.2.1.7.';

#wait for up interface
my $sleep_time = 15;

$|=1;

exit if (!$ARGV[0]);

my $HOST_IP = $ARGV[0];

my $IP_ATON=StrToIp($HOST_IP);

my $auth_rec = get_record_sql($dbh,'SELECT * FROM User_auth WHERE deleted=0 and ip_int='.$IP_ATON);
if (!$auth_rec) { db_log_error("Record with ip $HOST_IP not found! Bye."); exit; }

my $auth_id = $auth_rec->{id};
my $auth_name = $auth_rec->{dns_name};

my $auth_ident = $HOST_IP;
if ($auth_name) { $auth_ident = $auth_name."[".$HOST_IP."]"; }

my $d_sql="SELECT D.id, D.ip, D.device_name, D.vendor_id, D.device_model_id, DP.port, DP.snmp_index  FROM devices AS D, device_ports AS DP, connections AS C WHERE D.snmp_version>0 and D.id = DP.device_id AND DP.id = C.port_id AND C.auth_id=$auth_id AND DP.uplink=0";

my $dev_port = get_record_sql($dbh,$d_sql);

if (!$dev_port) { db_log_error($dbh,"Connection for $HOST_IP not found! Bye."); exit; }

my $switch = get_record_sql($dbh,'SELECT * FROM devices WHERE id='.$dev_port->{id});

if (!$switch) { db_log_error($dbh,"Switch for $HOST_IP not found! Bye."); exit; }

setCommunity($switch);

my $ip=$dev_port->{ip};
my $model_id=$dev_port->{device_model_id};
my $model_rec = get_record_sql($dbh,'SELECT model_name FROM device_models WHERE id='.$model_id);
my $model = $model_rec->{model_name};
my $port=$dev_port->{port};
my $vendor_id = $dev_port->{vendor_id};
my $snmp_index=$dev_port->{snmp_index};
my $device_name = $dev_port->{device_name};

db_log_warning($dbh,"Restart $auth_ident at $device_name ($model $ip) [$port] request found. Try.");

my $poe_oid;
my $admin_oid;

my $poe_enabled_value = 1;
my $poe_disabled_value = 2;

my $ret;

#################### PORT STATE ###################

#default
$admin_oid=$admin_status_oid.$port;

##################### POE #########################

#default
$poe_oid=$pethPsePortAdminEnable.$snmp_index;

#Huawei
if ($vendor_id eq 3) {
    $poe_oid=$huawei_poe_oid.$snmp_index;
    }

#NetGear
if ($vendor_id eq 10) {
    $poe_oid=$netgear_poe_oid.$snmp_index;
    }

##################### Action ########################

db_log_debug($dbh,"POE oid: $poe_oid");
db_log_debug($dbh,"Admin oid: $admin_oid");

if ($poe_oid) {
    $ret=snmp_set_int($ip,$poe_oid,$poe_disabled_value,$switch->{snmp});
    db_log_info($dbh,"Try disable POE at port $port.");
    db_log_debug($dbh,"Send to oid: $poe_oid value: $poe_disabled_value");
    }

if ($admin_oid) {
    $ret=snmp_set_int($ip,$admin_oid,$poe_disabled_value,$switch->{snmp});
    db_log_info($dbh,"Try shutdown port $port.");
    db_log_debug($dbh,"Send to oid: $admin_oid value: $poe_disabled_value");
    }

sleep($sleep_time);

if ($admin_oid) {
    $ret=snmp_set_int($ip,$admin_oid,$poe_enabled_value,$switch->{snmp});
    db_log_info($dbh,"Enable POE at port $port.");
    db_log_debug($dbh,"Send to oid: $admin_oid value: $poe_enabled_value");
    }

if ($poe_oid) {
    $ret=snmp_set_int($ip,$poe_oid,$poe_enabled_value,$switch->{snmp});
    db_log_info($dbh,"Up port $port.");
    db_log_debug($dbh,"Send to oid: $poe_oid value: $poe_enabled_value");
    }

db_log_info($dbh,'Done!');

exit;
