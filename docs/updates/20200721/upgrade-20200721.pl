#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use FindBin '$Bin';
use lib "$Bin/";
use strict;
use DBI;
use Time::Local;
use Net::Patricia;
use Data::Dumper;
use Rstat::config;
use Rstat::main;
use Rstat::mysql;
use Rstat::net_utils;

my $list = $dbh->prepare('SELECT * FROM syslog');
if ( !defined $list ) { die "Cannot prepare statement: $DBI::errstr\n"; }
$list->execute;

while (my $row = $list ->fetchrow_hashref) {
my $auth_id = 0;
if ($row->{message}=~/auth_id: (\d*)\s+/i) { $auth_id = $1; }
if ($row->{message}=~/auth_id:(\d*)\s+/i) { $auth_id = $1; }
if ($row->{message}=~/User_auth where id=(\d*)\s+/i) { $auth_id = $1; }
if ($row->{message}=~/User_auth id: (\d*)\s+/i) { $auth_id = $1; }
if ($auth_id) { do_sql($dbh,'UPDATE syslog SET auth_id='.$auth_id.' WHERE id='.$row->{id}); }
print "*";
}

$list->finish();

print "\n";

exit;
