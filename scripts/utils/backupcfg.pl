#!/usr/bin/perl -CS

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use utf8;
use open ":encoding(utf8)";
use English;
use base;
use FindBin '$Bin';
use lib "/opt/Eye/scripts";
use strict;
use Time::Local;
use FileHandle;
use Data::Dumper;
use eyelib::config;
use eyelib::main;
use eyelib::mysql;
use eyelib::net_utils;
use eyelib::cmd;
use Fcntl qw(:flock);

open(SELF,"<",$0) or die "Cannot open $0 - $!";
flock(SELF, LOCK_EX|LOCK_NB) or exit 1;

$|=1;

my $debug = 1;

if ($ARGV[0]) {
    my $device=get_record_sql($dbh,'SELECT * FROM devices WHERE id='.$ARGV[0]);
    $device = netdev_set_auth($device);
    print "Backup switch $device->{device_name} ip: $device->{ip} ...";
    netdev_backup($device,$tftp_server);
    print " end.\n";
    } else {
    my @devices=get_records_sql($dbh,'SELECT * FROM devices WHERE deleted=0 and (vendor_id=3 or vendor_id=8 or vendor_id=9)');
    foreach my $device (@devices) {
        $device = netdev_set_auth($device);
        print "Backup switch $device->{device_name} ip: $device->{ip} ...";
        netdev_backup($device,$tftp_server);
        print " end.\n";
        }
    }

exit 0;
