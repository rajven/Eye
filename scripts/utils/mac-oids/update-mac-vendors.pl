#!/usr/bin/perl -CS

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use utf8;
use FindBin '$Bin';
use lib "/opt/Eye/scripts";
use Data::Dumper;
use eyelib::config;
use eyelib::main;
use eyelib::mysql;
use eyelib::net_utils;
use strict;
use warnings;

my $clean_run = $ARGV[0] || '';

my @nSQL=read_file("manuf.csv");

if ($clean_run eq 'clean') {
    do_sql($dbh,"TRUNCATE TABLE mac_vendors");
    }

chomp(@nSQL);
my @fSQL=();
foreach my $row (@nSQL) {
my ($oui,$company,$address)=split(/;/,$row);
if (!$address) { $address=''; }
$oui =~ s/\/[0-9]+//;
$oui = mac_splitted(trim($oui));
if ($clean_run ne 'clean') {
    my $vendor = get_record_sql($dbh,"SELECT id FROM mac_vendors WHERE oui='".$oui."'");
    next if ($vendor);
    }
print "Added: ".$row."\n";
my $row_str = "INSERT INTO mac_vendors (oui,companyName,companyAddress) VALUES('".$oui."',".$dbh->quote($company).",".$dbh->quote($address).");";
push(@fSQL,$row_str);
}

batch_db_sql($dbh,\@fSQL);

print "Done!\n";

exit;
