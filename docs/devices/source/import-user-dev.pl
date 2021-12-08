#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

#Импортируем юзеровские устройства в системную БД
# !!!!! НЕ ЗАПУСКАТЬ НА РАБОЧЕЙ КОНФИГУРАЦИИ !!!!!!

use FindBin '$Bin';
use lib "$Bin/";
use Data::Dumper;
use Rstat::config;
use Rstat::main;
use Rstat::mysql;
use Rstat::net_utils;
use strict;
use warnings;

print "Stage 0: Read user devices\n";

my @user_devices=();

if (-e "user-devs.csv") {
    my @nSQL=read_file("user-devs.csv");
    foreach my $row (@nSQL) {
	my ($dev_id,$dev_model,$dev_vendor) = split(/;/,$row);
	my $device;
	$device->{id}=$dev_id;
	$device->{model_name}=$dev_model;
	$device->{vendor_id}=$dev_vendor;
	push(@user_devices,$device);
	}
    }

print "Stage 1: Read vendors\n";

my @user_vendors=();
if (-e "user-vendors.csv") {
    my @nSQL=read_file("user-vendors.csv");
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
    $vendor_migration{$vendor->{id}}=$vendor_exist->{id};
    next;
    }
print "id: $vendor->{name} Migrated.\n";
my $vendor_max = get_record_sql($dbh,"SELECT MAX(id) as fmax FROM vendors WHERE id<10000");
$vendor_migration{$vendor->{id}}=$vendor_max->{'fmax'}+1;
$vendor->{id}=$vendor_max->{'fmax'}+1;
insert_record($dbh,"vendors",$vendor);
}
print "Done!\n";

print "Import devices\n";

foreach $device (@user_devices) {

$device->{vendor_id}=$vendor_migration{$device->{vendor_id}};

#seach exists device
my $device_exist = get_record_sql($dbh,"SELECT * FROM device_models WHERE vendor_id=".$device->{vendor_id}." AND LOWER(model_name)='".lc(trim($device->{model_name}))."'");
next if ($device_exist);
print "id: $device->{model_name} Migrated.\n";
my $device__max = get_record_sql($dbh,"SELECT MAX(id) as fmax FROM device_models WHERE id<10000");
$device->{id}=$device_max->{'fmax'}+1;
insert_record($dbh,"device_models",$device);
}

print "Done!\n";

exit;
