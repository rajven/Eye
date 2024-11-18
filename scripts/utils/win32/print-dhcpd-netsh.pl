#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#
use FindBin '$Bin';
use lib "/opt/Eye/scripts";
use strict;
use DBI;
use Time::Local;
use Net::Patricia;
use Data::Dumper;
use eyelib::config;
use eyelib::main;
use eyelib::net_utils;
use eyelib::database;

my $time_shift=$ARGV[0];

my $time_filter='';

if ($time_shift) {
    my ($sec,$min,$hour,$day,$month,$year,$zone) = localtime(time()-$time_shift*3600);
    $month += 1;
    $year += 1900;
    my $filter_str="$year-$month-$day $hour-$min-$sec";
    my $filter_date=$dbh->quote($filter_str);
#    $time_filter=' and dhcp_time>='.$filter_date;
    $time_filter=' and timestamp>='.$filter_date;
    }

my %nets;

foreach my $net (@office_network_list) {
my $scope_name=$net;
$scope_name =~s/\/\d+$//g;
$nets{$scope_name}= new Net::Patricia;
$nets{$scope_name}->add_string($net);
}

#get userid list
my $user_auth_list = $dbh->prepare( "SELECT id,ip,ip_int,mac,dns_name FROM User_auth where deleted=0 $time_filter ORDER by ip_int" );
if ( !defined $user_auth_list ) { die "Cannot prepare statement: $DBI::errstr\n"; }

$user_auth_list->execute;

# user auth list
my $authlist_ref = $user_auth_list->fetchall_arrayref();
$user_auth_list->finish();
$dbh->disconnect;

foreach my $row (@$authlist_ref) {
next if (!$row);
my $ip=trim($row->[1]);
my $ip_int=trim($row->[2]);
my $mac=trim($row->[3]);
my $dns_name=trim($row->[4]);
next if (!$ip_int);
next if (!$mac);
next if (!$ip);
$mac=mac_simplify($mac);

my $scope_name;
foreach my $scope (keys %nets) {
    if ($nets{$scope}->match_string($ip)) { $scope_name=$scope; }
    }

next if (!$scope_name);

my $default_name;
if ($dns_name) { $default_name=$dns_name; } else {
    $default_name = $ip;
    $default_name =~s/192.168.//g;
    }

$default_name =~s/_/-/g;
$default_name =~s/[.]/-/g;
$default_name =~s/ /-/g;

print 'Dhcp Server \\\\127.0.0.1 Scope '.$scope_name.' Add reservedip '.$ip.' '.$mac.' "'.$default_name.'" "" "DHCP"'."\n";
}

exit 0;
