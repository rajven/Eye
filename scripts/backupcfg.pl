#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use FindBin '$Bin';
use lib "$Bin";
use strict;
use Time::Local;
use FileHandle;
use Data::Dumper;
use Rstat::config;
use Rstat::main;
use Rstat::mysql;
use Rstat::net_utils;
use Rstat::cmd;
use Fcntl qw(:flock);

open(SELF,"<",$0) or die "Cannot open $0 - $!";
flock(SELF, LOCK_EX|LOCK_NB) or exit 1;

$|=1;

my $debug = 1;

if ($ARGV[0]) {
    my $device=get_record_sql($dbh,'SELECT * FROM devices WHERE id='.$ARGV[0]);
    print "Backup switch $device->{device_name} ip: $device->{ip} ...";
    #router
    if ($device->{device_type} eq '2') {
        #mikrotik
        if ($device->{vendor_id} eq '9') { $device->{port}='60023'; }
        $device->{login}=$router_login;
        $device->{password}=$router_password;
        }
    #switch
    if ($device->{device_type} eq '1') {
        #mikrotik
        if ($device->{vendor_id} eq '9') { $device->{port}='60023'; }
        $device->{login}='admin';
        $device->{password}=$sw_password;
        }
    netdev_backup($device,$tftp_server);
    print " end.\n";
    } else {
    my @devices=get_records_sql($dbh,'SELECT * FROM devices WHERE deleted=0 and (vendor_id=3 or vendor_id=8 or vendor_id=9)');
    foreach my $device (@devices) {
        print "Backup switch $device->{device_name} ip: $device->{ip} ...";
        #router
        if ($device->{device_type} eq '2') {
            #mikrotik
            if ($device->{vendor_id} eq '9') { $device->{port}='60023'; }
            $device->{login}=$router_login;
            $device->{password}=$router_password;
            }
        #switch
        if ($device->{device_type} eq '1') {
            #mikrotik
            if ($device->{vendor_id} eq '9') { $device->{port}='60023'; }
            $device->{login}='admin';
            $device->{password}=$sw_password;
            }
        netdev_backup($device,$tftp_server);
        print " end.\n";
	}
    }

exit 0;
