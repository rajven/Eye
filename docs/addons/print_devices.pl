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
use Date::Parse;
use Socket;
use eyelib::config;
use eyelib::main;
use eyelib::net_utils;
use eyelib::mysql;
use NetAddr::IP;

setpriority(0,0,19);

my $router_list = $dbh->prepare( "SELECT device_name,device_model,ip,snmp_version,community FROM devices ORDER by ip" );
if ( !defined $router_list ) { die "Cannot prepare statement: $DBI::errstr\n"; }
$router_list->execute;
my $router_ref = $router_list->fetchall_arrayref();
$router_list->finish();

foreach my $router (@$router_ref) {
my $name=$router->[0];
my $model=$router->[1];
my $router_ip=$router->[2];
my $snmp_version=$router->[3];
my $community=$router->[4];

print "Device $name $model $router_ip $snmp_version $community\n";
}

$dbh->disconnect;

exit 0;
