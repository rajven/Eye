#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use FindBin '$Bin';
use lib "$Bin/";
use strict;
use DBI;
use Time::Local;
use Net::Patricia;
use NetAddr::IP;
use Data::Dumper;
use Rstat::config;
use Rstat::main;
use Rstat::mysql;
use Rstat::net_utils;
use File::Basename;
use File::Path;

print "Start migration: ";

#1 - switch
#2 - router
#3 - server

#get userid list
my $sSQL="SELECT * FROM devices";
my @devices = get_custom_records($dbh,$sSQL);
foreach my $row (@devices) {
next if (!$row);

if (exists($row->{'is_router'})) {
    if ($row->{'is_router'}) { $row->{device_type}=2; }
    update_record($dbh,'devices',$row,"id=$row->{id}");
    print ".";
    }

if (exists($row->{wan_int})) {
    my @wan_int=split(/;/,$row->{'wan_int'});
    foreach my $dev (@wan_int) {
        next if (!$dev);
        my $new_l3;
        $new_l3->{'name'}=trim($dev);
        $new_l3->{'interface_type'}=1;
        $new_l3->{'device_id'}=$row->{'id'};
        insert_record($dbh,'device_l3_interfaces',$new_l3);
        }
    }

if (exists($row->{lan_int})) {
    my @lan_int=split(/;/,$row->{'lan_int'});
    foreach my $dev (@lan_int) {
        next if (!$dev);
        my $new_l3;
        $new_l3->{'name'}=trim($dev);
        $new_l3->{'interface_type'}=0;
        $new_l3->{'device_id'}=$row->{'id'};
        insert_record($dbh,'device_l3_interfaces',$new_l3);
        }
    }
}

do_sql($dbh,"ALTER TABLE `devices` DROP `lan_int`;");
do_sql($dbh,"ALTER TABLE `devices` DROP `wan_int`;");
do_sql($dbh,"ALTER TABLE `devices` DROP `is_router`;");
do_sql($dbh,"ALTER TABLE `devices` CHANGE `internet_gateway` `user_acl` TINYINT(1) NOT NULL DEFAULT '0';");

exit 0;
