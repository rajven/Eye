#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use utf8;
use FindBin '$Bin';
use lib "$Bin/";
use Data::Dumper;
use Rstat::config;
use Rstat::main;
use Rstat::mysql;
use Rstat::net_utils;
use strict;
use warnings;

do_sql($dbh,"DROP TABLE `mac_vendors`");
do_sql($dbh,"CREATE TABLE `mac_vendors` (`id` int(11) NOT NULL,`oui` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,`companyName` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL, `companyAddress` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
do_sql($dbh,"ALTER TABLE `mac_vendors` ADD PRIMARY KEY (`id`),  ADD KEY `oui` (`oui`)");
do_sql($dbh,"ALTER TABLE `mac_vendors` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT");

my @nSQL=read_file("manuf.csv");
chomp(@nSQL);
my @fSQL=();
foreach my $row (@nSQL) {
my ($oui,$company,$address)=split(/;/,$row);
if (!$address) { $address=''; }
my $row_str = "INSERT INTO mac_vendors (oui,companyName,companyAddress) VALUES('".$oui."',".$dbh->quote($company).",".$dbh->quote($address).");";
push(@fSQL,$row_str);
}

batch_db_sql($dbh,\@fSQL);

print "Done!\n";

exit;
