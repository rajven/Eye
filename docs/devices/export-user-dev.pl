#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

#Экспортируем текующую юзеровскую БД устройств

use FindBin '$Bin';
use lib "$Bin/";
use Data::Dumper;
use eyelib::config;
use eyelib::main;
use eyelib::mysql;
use eyelib::net_utils;
use strict;
use warnings;

print "Export Devices\n";

#new models
my @dev_models = get_records_sql($dbh,"SELECT * FROM device_models WHERE id>=10000");
foreach my $dev (@dev_models) {
write_to_file("user-devs.csv",$dev->{id}.";".$dev->{model_name}.";".$dev->{vendor_id},1);
}

print "Done!\n";
print "Export Vendors\n";
my @dev_vendors = get_records_sql($dbh,"SELECT * FROM vendors WHERE id>=10000");
foreach my $row (@dev_vendors) {
write_to_file("user-vendors.csv",$row->{id}.";".$row->{name},1);
}

print "Done!\n";

exit;
