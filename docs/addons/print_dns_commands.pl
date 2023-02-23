#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use utf8;
use English;
use base;
use FindBin '$Bin';
use lib "$Bin/";
use strict;
use DBI;
use Time::Local;
use Rstat::config;
use Rstat::main;
use Rstat::mysql;
use Rstat::net_utils;

my @devices=get_records_sql($dbh,'SELECT * FROM devices WHERE deleted=0 and (vendor_id=3 or vendor_id=8 or vendor_id=9) and device_type<=2 ORDER BY device_name');
foreach my $device (@devices) {
print "./set_dns_record.pl '$device->{device_name}' '$device->{ip}' 1\n";
}


my @devices=get_records_sql($dbh,'SELECT * FROM User_auth WHERE deleted=0 and dns_name IS NOT NULL and dns_name >"" ORDER BY dns_name');
foreach my $device (@devices) {
print "./set_dns_record.pl '$device->{dns_name}' '$device->{ip}' 1\n";
}

exit 0;
