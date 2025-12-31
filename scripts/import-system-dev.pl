#!/usr/bin/perl

#
# Copyright (C) Roman Dmitriev, rnd@rajven.ru
#

#Обновляем БД устрйств

use FindBin '$Bin';
use lib "$Bin/";
use Data::Dumper;
use Rstat::config;
use Rstat::main;
use Rstat::database;
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
foreach my $vendor (@user_vendors) {
#seach exists vendor created by user
my $vendor_exist = get_record_sql($dbh,"SELECT * FROM vendors WHERE id>=10000 and LOWER(name)='".lc(trim($vendor->{name}))."'");
print "Check: $vendor->{name} id: $vendor->{id} ...";
if ($vendor_exist) {
    if ($vendor_exist->{id} == $vendor->{id}) { print "OK\n"; next; }
    print " created by user. Switch to system pool.";
    do_sql($dbh,"UPDATE vendors SET id=".$vendor->{id}." WHERE id=".$vendor_exist->{id});
    do_sql($dbh,"UPDATE device_models SET vendor_id=".$vendor->{id}." WHERE vendor_id=".$vendor_exist->{id});
    do_sql($dbh,"UPDATE devices SET vendor_id=".$vendor->{id}." WHERE vendor_id=".$vendor_exist->{id});
    print " Migrated.\n";
    next;
    }
#check system pool
$vendor_exist = get_record_sql($dbh,"SELECT * FROM vendors WHERE id<10000 and LOWER(name)='".lc(trim($vendor->{name}))."'");
if ($vendor_exist) {
    if ($vendor_exist->{id} == $vendor->{id}) { print "OK\n"; next ; }
    print "Warning! System vendor mismatch! ";
    my $vendor2 = get_record_sql($dbh,"SELECT * FROM vendors WHERE id=$vendor->{id}");
    if ($vendor2) {
        print "Found another vendor with this id =>".$vendor2->{name};
        my $last_id = get_record_sql($dbh,"SELECT MAX(id) as last FROM vendors");
        my $new_vendor_id = $last_id->{'last'}+1;
        if ($new_vendor_id <=10000 ) { $new_vendor_id = 10001; }
        print " Move vendor $vendor2->{name} to user custom block. Run script again\n";
        do_sql($dbh,"UPDATE vendors SET id=".$new_vendor_id." WHERE id=".$vendor2->{id});
        do_sql($dbh,"UPDATE device_models SET vendor_id=".$new_vendor_id." WHERE vendor_id=".$vendor2->{id});
        next;
        }
    do_sql($dbh,"UPDATE vendors SET id=".$vendor->{id}." WHERE id=".$vendor_exist->{id});
    do_sql($dbh,"UPDATE device_models SET vendor_id=".$vendor->{id}." WHERE vendor_id=".$vendor_exist->{id});
    do_sql($dbh,"UPDATE devices SET vendor_id=".$vendor->{id}." WHERE vendor_id=".$vendor_exist->{id});
    print "Fixed.\n";
    next;
    }
#check another record with this id
my $vendor2 = get_record_sql($dbh,"SELECT * FROM vendors WHERE id=$vendor->{id}");
if ($vendor2) {
    print "Found another vendor with this id =>".$vendor2->{name};
    my $last_id = get_record_sql($dbh,"SELECT MAX(id) as last FROM vendors");
    my $new_vendor_id = $last_id->{'last'}+1;
    if ($new_vendor_id <=10000 ) { $new_vendor_id = 10001; }
    print " Move vendor $vendor2->{name} to user custom block. Run script again\n";
    do_sql($dbh,"UPDATE vendors SET id=".$new_vendor_id." WHERE id=".$vendor2->{id});
    do_sql($dbh,"UPDATE device_models SET vendor_id=".$new_vendor_id." WHERE vendor_id=".$vendor2->{id});
    next;
    }
insert_record($dbh,"vendors",$vendor);
print " Imported.\n";
}
print "Done!\n";

print "Import devices\n";

foreach my $device (@user_devices) {
#seach exists device created by user
print "Check id: $device->{id} name: $device->{model_name}..";
my $device_exist = get_record_sql($dbh,"SELECT * FROM device_models WHERE id>=10000 AND vendor_id=".$device->{vendor_id}." AND LOWER(model_name)='".lc(trim($device->{model_name}))."'");
if ($device_exist) {
    if ($device_exist->{id} == $device->{id}) { print "OK\n"; next; }
    do_sql($dbh,"UPDATE device_models SET id=".$device->{id}." WHERE id=".$device_exist->{id});
    do_sql($dbh,"UPDATE devices SET device_model_id=".$device->{id}." WHERE device_model_id=".$device_exist->{id});
    print "Migrated\n";
    next;
    }
#system model table
$device_exist = get_record_sql($dbh,"SELECT * FROM device_models WHERE id<10000 AND vendor_id=".$device->{vendor_id}." AND LOWER(model_name)='".lc(trim($device->{model_name}))."'");
if ($device_exist) {
    if ($device_exist->{id} == $device->{id}) { print "OK\n"; next; }
    print "Warning! System device model mismatch! ";
    my $device2 = get_record_sql($dbh,"SELECT * FROM device_models WHERE id=".$device->{id});
    if ($device2) {
        print "Found another device model with this id =>".$device2->{model_name};
        my $last_id = get_record_sql($dbh,"SELECT MAX(id) as last FROM device_models");
        my $new_model_id = $last_id->{'last'}+1;
        if ($new_model_id <=10000 ) { $new_model_id = 10001; }
        print " Move device model $device2->{model_name} to user custom block. Run script again\n";
        do_sql($dbh,"UPDATE device_models SET id=".$new_model_id." WHERE id=".$device2->{id});
        do_sql($dbh,"UPDATE devices SET device_model_id=".$new_model_id." WHERE device_model_id=".$device2->{id});
        next;
        }
    do_sql($dbh,"UPDATE device_models SET id=".$device->{id}." WHERE id=".$device_exist->{id});
    do_sql($dbh,"UPDATE devices SET device_model_id=".$device->{id}." WHERE device_model_id=".$device_exist->{id});
    print "Migrated\n";
    next;
    }
#check another record with this id
my $device2 = get_record_sql($dbh,"SELECT * FROM device_models WHERE id=".$device->{id});
if ($device2) {
    print "Found another device model with this id =>".$device2->{model_name};
    my $last_id = get_record_sql($dbh,"SELECT MAX(id) as last FROM device_models");
    my $new_model_id = $last_id->{'last'}+1;
    if ($new_model_id <=10000 ) { $new_model_id = 10001; }
    print " Move device model $device2->{model_name} to user custom block. Run script again\n";
    do_sql($dbh,"UPDATE device_models SET id=".$new_model_id." WHERE id=".$device2->{id});
    do_sql($dbh,"UPDATE devices SET device_model_id=".$new_model_id." WHERE device_model_id=".$device2->{id});
    next;
    }
insert_record($dbh,"device_models",$device);
print " Imported.\n";
}

print "Done!\n";

exit;
