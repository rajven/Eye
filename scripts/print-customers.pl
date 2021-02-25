#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use FindBin '$Bin';
use lib "$Bin/";
use strict;
use DBI;
use Time::Local;
use Rstat::config;
use Rstat::main;
use Rstat::mysql;
use Rstat::net_utils;

my @customers = get_custom_records($dbh,"SELECT * FROM Customers WHERE readonly=0");

foreach my $row (@customers) {
next if (!$row);
print "id: $row->{id} name: $row->{Login} hash: $row->{Pwd}\n";
}

exit 0;
