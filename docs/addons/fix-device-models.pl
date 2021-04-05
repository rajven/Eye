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

#new models
my @dev_models = get_records_sql($dbh,"SELECT * FROM device_models WHERE id>=10000");
foreach my $row (@dev_models) {
print "Dev: $row->{id} $row->{model_name}  =>";
#search hardcoded models with some name
my $model = get_record_sql($dbh,"SELECT * FROM device_models WHERE id <>".$row->{id}." AND model_name='".trim($row->{model_name})."'");
if ($model) { 
    print "... found id: $model->{id}. Migrated.";
    do_sql($dbh,"DELETE FROM device_models WHERE id=".$row->{id});
    do_sql($dbh,"UPDATE devices SET device_model_id=".$model->{id}." WHERE device_model_id=".$row->{id});
    do_sql($dbh,"UDPATE User_auth SET device_model_id=".$model->{id}." WHERE device_model_id=".$row->{id});
    next;
    }

my $max = get_record_sql($dbh,"SELECT MAX(id) as max_id FROM device_models WHERE id<10000");
if ($max and $max->{max_id}) {
    print ".. Moved to harcoded list\n";
    $max->{max_id}++;
    do_sql($dbh,"UPDATE device_models SET id=".$max->{max_id}." WHERE id=".$row->{id});
    do_sql($dbh,"UPDATE devices SET device_model_id=".$max->{max_id}." WHERE device_model_id=".$row->{id});
    do_sql($dbh,"UPDATE User_auth SET device_model_id=".$max->{max_id}." WHERE device_model_id=".$row->{id});
    }
}
print "Done!\n";

print "Stage 2: Vendors\n";
#new vendors
my @dev_vendors = get_records_sql($dbh,"SELECT * FROM vendors WHERE id>=10000");
foreach my $row (@dev_vendors) {
print "Dev: $row->{id} $row->{name}  =>";
#search hardcoded vendors with some name
my $vendor = get_record_sql($dbh,"SELECT * FROM vendors WHERE id <>".$row->{id}." AND name='".trim($row->{name})."'");
if ($vendor) {
    print "... found id: $vendor->{id}. Migrated.";
    do_sql($dbh,"DELETE FROM vendors WHERE id=".$row->{id});
    do_sql($dbh,"UPDATE device_models SET vendor_id=".$vendor->{id}." WHERE vendor_id=".$row->{id});
    do_sql($dbh,"UDPATE devices SET vendor_id=".$vendor->{id}." WHERE vendor_id=".$row->{id});
    next;
    }

my $max = get_record_sql($dbh,"SELECT MAX(id) as max_id FROM vendors WHERE id<10000");
if ($max and $max->{max_id}) {
    print ".. Moved to harcoded list\n";
    $max->{max_id}++;
    do_sql($dbh,"UPDATE vendors SET id=".$max->{max_id}." WHERE id=".$row->{id});
    do_sql($dbh,"UPDATE device_models SET vendor_id=".$max->{max_id}." WHERE vendor_id=".$row->{id});
    do_sql($dbh,"UPDATE devices SET vendor_id=".$max->{max_id}." WHERE vendor_id=".$row->{id});
    }
}
print "Done!\n";

exit;
