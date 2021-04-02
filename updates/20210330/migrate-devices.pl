#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use FindBin '$Bin';
use lib "$Bin/";
use Data::Dumper;
use Rstat::config;
use Rstat::main;
use Rstat::mysql;
use Rstat::net_utils;
use strict;
use warnings;

print "Stage 1: Devices\n";

my @devices = get_records_sql($dbh,"SELECT * FROM devices");
foreach my $row (@devices) {
next if (!$row->{device_model});
print "Dev: $row->{id} $row->{device_name} Model: $row->{device_model} =>";
my $model = get_record_sql($dbh,"SELECT * FROM device_models WHERE vendor_id=".$row->{vendor_id}." AND model_name='".trim($row->{device_model})."'");
if (!$model) { print "... unknown!\n"; next; }
print "id: $model->{id} $model->{model_name}\n";
do_sql($dbh,"UPDATE devices SET device_model_id=".$model->{id}." WHERE id=".$row->{id});
}
print "Done!\n";

print "Stage 2: Auth\n";

@devices = get_records_sql($dbh,"SELECT * FROM User_auth WHERE host_model IS NOT NULL");
foreach my $row (@devices) {
next if (!$row->{host_model});
print "Auth: $row->{id} $row->{ip} Model: $row->{host_model} =>";
my $model = get_record_sql($dbh,"SELECT * FROM device_models WHERE model_name='".trim($row->{host_model})."'");
if (!$model) { print "... unknown!\n"; next; }
print "id: $model->{id} $model->{model_name}\n";
do_sql($dbh,"UPDATE User_auth SET device_model_id=".$model->{id}." WHERE id=".$row->{id});
}
print "Done!\n";

do_sql($dbh,"ALTER TABLE `User_auth` DROP `host_model`");
do_sql($dbh,"ALTER TABLE `devices` DROP `device_model`");

print "Done!\n";

exit;
