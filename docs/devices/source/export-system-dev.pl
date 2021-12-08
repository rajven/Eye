#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

#Мигрируем текующие устройства в системные, экспортируем БД
# !!!!!!!!!!!!!!!!! НЕ ЗАПУСКАТЬ НА РАБОЧЕЙ КОНФИГУРАЦИИ !!!!!!!!!!!!!!!

use FindBin '$Bin';
use lib "$Bin/";
use Data::Dumper;
use Rstat::config;
use Rstat::main;
use Rstat::mysql;
use Rstat::net_utils;
use strict;
use warnings;

print "Migrate User dev to System dev\n";

my @dev_models = get_records_sql($dbh,"SELECT * FROM device_models WHERE id>=10000");
foreach my $dev (@dev_models) {
my $device_max = get_record_sql($dbh,"SELECT MAX(id) as fmax FROM device_models WHERE id<10000");
my $old_id = $dev->{id};
$dev->{id}=$device_max->{'fmax'}+1;
update_record($dbh,"device_models",$dev,"id=".$old_id);
do_sql($dbh,"UPDATE devices SET device_model_id=".$dev->{id}." WHERE device_model_id=".$old_id);
}

my @vendors = get_records_sql($dbh,"SELECT * FROM vendors WHERE id>=10000");
foreach my $vendor (@vendors) {
my $vendor_max = get_record_sql($dbh,"SELECT MAX(id) as fmax FROM vendors WHERE id<10000");
my $old_id = $vendor->{id};
$vendor->{id}=$vendor_max->{'fmax'}+1;
update_record($dbh,"vendors",$vendor,"id=".$old_id);
do_sql($dbh,"UPDATE device_models SET vendor_id=".$vendor->{id}." WHERE vendor_id=".$old_id);
do_sql($dbh,"UPDATE devices SET vendor_id=".$vendor->{id}." WHERE vendor_id=".$old_id);
}

print "Export Devices\n";

#new models
@dev_models = get_records_sql($dbh,"SELECT * FROM device_models WHERE id<10000");
foreach my $dev (@dev_models) {
write_to_file("system-devs.csv",$dev->{id}.";".$dev->{model_name}.";".$dev->{vendor_id},1);
}

print "Done!\n";
print "Export Vendors\n";
my @dev_vendors = get_records_sql($dbh,"SELECT * FROM vendors WHERE id<10000");
foreach my $row (@dev_vendors) {
write_to_file("system-vendors.csv",$row->{id}.";".$row->{name},1);
}

print "Done!\n";

exit;
