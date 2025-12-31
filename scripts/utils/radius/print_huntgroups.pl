#!/usr/bin/perl 
#
# Copyright (C) Roman Dmitriev, rnd@rajven.ru
#
use utf8;
use open ":encoding(utf8)";
use FindBin '$Bin';
use lib "/opt/Eye/scripts";
use strict;
use DBI;
use Data::Dumper;
use Socket;
use eyelib::config;
use eyelib::main;
use eyelib::net_utils;
use eyelib::database;
use eyelib::common;

my %huntgroups=(
'2'=>'eltex',
'3'=>'huawei',
'4'=>'zyxel',
'5'=>'raisecom',
'6'=>'snr',
'7'=>'dlink',
'8'=>'aliedtelesys',
'9'=>'mikrotik',
'10'=>'netgear',
'11'=>'ubnt',
'15'=>'hp',
'16'=>'cisco',
'17'=>'maipu',
);

my @device_list = get_records_sql($dbh,"SELECT * FROM devices WHERE device_type<=2 ORDER BY device_name" );
foreach my $device (sort @device_list) {
my @auth_list = get_records_sql($dbh,"SELECT * FROM User_auth WHERE deleted=0 AND user_id=".$device->{user_id});
    print "#$device->{device_name}\n";
    foreach my $auth (sort @auth_list) {
    if (exists $huntgroups{$device->{vendor_id}}) {
        print "$huntgroups{$device->{vendor_id}} NAS-IP-Address == $auth->{ip}\n";
        }
    }
}

$dbh->disconnect;

exit 0;
