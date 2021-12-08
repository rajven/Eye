#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

#Обновляем БД устрйств

use FindBin '$Bin';
use lib "$Bin/";
use Data::Dumper;
use Rstat::config;
use Rstat::main;
use Rstat::mysql;
use Rstat::net_utils;
use strict;
use warnings;

print "Stage 0: Read system devices\n";

my @user_devices=();

if (-e "system-devs.csv") {
    my @nSQL=read_file("system-devs.csv");
    foreach my $row (@nSQL) {
	my ($dev_id,$dev_model,$dev_vendor) = split(/;/,$row);
	my $device;
	$device->{id}=$dev_id;
	$device->{model_name}=$dev_model;
	$device->{vendor_id}=$dev_vendor;
	push(@user_devices,$device);
	}
    }

print "Stage 1: Read system vendors\n";

my @user_vendors=();
if (-e "system-vendors.csv") {
    my @nSQL=read_file("system-vendors.csv");
    foreach my $row (@nSQL) {
	my ($vendor_id,$vendor_name) = split(/;/,$row);
	my $vendor;
	$vendor->{id}=$vendor_id;
	$vendor->{name}=$vendor_name;
	push(@user_vendors,$vendor);
	}
    }
print "Done!\n";

my %vendor_migration;

print "Import Vendors\n";
foreach $vendor (@user_vendors) {
#seach exists vendor
my $vendor_exist = get_record_sql($dbh,"SELECT * FROM vendors WHERE LOWER(name)='".lc(trim($vendor->{name}))."'");
if ($vendor_exist) {
    next if ($vendor_exist->{id} = $vendor->{id});
    do_sql($dbh,"UPDATE vendors SET id=".$vendor->{id}." WHERE id=".$vendor_exist->{id});
    do_sql($dbh,"UPDATE device_models SET vendor_id=".$vendor->{id}." WHERE vendor_id=".$vendor_exist->{id});
    do_sql($dbh,"UPDATE devices SET vendor_id=".$vendor->{id}." WHERE vendor_id=".$vendor_exist->{id});
    next;
    }
print "id: $vendor->{name} Migrated.\n";
insert_record($dbh,"vendors",$vendor);
}
print "Done!\n";

print "Import devices\n";

foreach $device (@user_devices) {
#seach exists device
my $device_exist = get_record_sql($dbh,"SELECT * FROM device_models WHERE vendor_id=".$device->{vendor_id}." AND LOWER(model_name)='".lc(trim($device->{model_name}))."'");
if ($device_exist) {
    next if ($device_exist->{id} = $device->{id});
    do_sql($dbh,"UPDATE device_models SET id=".$device->{id}." WHERE id=".$device_exist->{id});
    do_sql($dbh,"UPDATE devices SET device_model_id=".$device->{id}." WHERE device_model_id=".$device_exist->{id});
    next;
    }
print "id: $device->{model_name} Migrated.\n";
insert_record($dbh,"device_models",$device);
}

print "Done!\n";

exit;
