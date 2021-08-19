#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

#Скрипт нужен для переноса юзеровских девайсов в системные при нахождении юзеровскийх моделей/вендоров в новой системной таблице

use FindBin '$Bin';
use lib "$Bin/";
use Data::Dumper;
use Rstat::config;
use Rstat::main;
use Rstat::mysql;
use Rstat::net_utils;
use strict;
use warnings;

print "Stage 0: Upgrade DB\n";

my @nSQL=read_file("last-vendors-models.sql");
foreach my $row (@nSQL) {
next if ($row!~/^REPLACE/);
do_sql($dbh,$row);
}

print "Stage 1: Devices\n";

#new models
my @dev_models = get_records_sql($dbh,"SELECT * FROM device_models WHERE id>=10000");
foreach my $row (@dev_models) {
print "Dev: $row->{id} $row->{model_name}  =>";
#search hardcoded models with some name
my $model = get_record_sql($dbh,"SELECT * FROM device_models WHERE id <>".$row->{id}." AND LOWER(model_name)='".lc(trim($row->{model_name}))."'");
if ($model) { 
    print "... found id: $model->{id}. Migrated.";
    do_sql($dbh,"DELETE FROM device_models WHERE id=".$row->{id});
    do_sql($dbh,"UPDATE devices SET device_model_id=".$model->{id}." WHERE device_model_id=".$row->{id});
    do_sql($dbh,"UDPATE User_auth SET device_model_id=".$model->{id}." WHERE device_model_id=".$row->{id});
    }
}
print "Done!\n";

print "Stage 2: Vendors\n";
#new vendors
my @dev_vendors = get_records_sql($dbh,"SELECT * FROM vendors WHERE id>=10000");
foreach my $row (@dev_vendors) {
print "Dev: $row->{id} $row->{name}  =>";
#search hardcoded vendors with some name
my $vendor = get_record_sql($dbh,"SELECT * FROM vendors WHERE id <>".$row->{id}." AND LOWER(name)='".lc(trim($row->{name}))."'");
if ($vendor) {
    print "... found id: $vendor->{id}. Migrated.";
    do_sql($dbh,"DELETE FROM vendors WHERE id=".$row->{id});
    do_sql($dbh,"UPDATE device_models SET vendor_id=".$vendor->{id}." WHERE vendor_id=".$row->{id});
    do_sql($dbh,"UDPATE devices SET vendor_id=".$vendor->{id}." WHERE vendor_id=".$row->{id});
    }
}
print "Done!\n";

exit;
