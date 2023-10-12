#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use utf8;
use FindBin '$Bin';
use lib "$Bin/";
use Data::Dumper;
use eyelib::config;
use eyelib::main;
use eyelib::mysql;
use eyelib::net_utils;
use strict;
use warnings;

my @nSQL=read_file("manuf.csv");
chomp(@nSQL);
my @fSQL=();
foreach my $row (@nSQL) {
my ($oui,$company,$address)=split(/;/,$row);
if (!$address) { $address=''; }
my $vendor = get_record_sql($dbh,"SELECT id FROM mac_vendors WHERE oui='".$oui."'");
next if ($vendor);
my $row_str = "INSERT INTO mac_vendors (oui,companyName,companyAddress) VALUES('".$oui."',".$dbh->quote($company).",".$dbh->quote($address).");";
push(@fSQL,$row_str);
}

batch_db_sql($dbh,\@fSQL);

print "Done!\n";

exit;
