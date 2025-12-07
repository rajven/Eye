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
use eyelib::common;

my $OU_ID=$ARGV[0];
my $ou_filter=" and L.ou_id=$OU_ID ";
if (!$OU_ID) { $ou_filter=''; }

#get userid list
my $user_auth_list = $dbh->prepare( "SELECT A.ip,A.ip_int,A.dns_name FROM User_auth as A, User_list as L where L.id=A.user_id and A.deleted=0 $ou_filter ORDER by ip_int" );
if ( !defined $user_auth_list ) { die "Cannot prepare statement: $DBI::errstr\n"; }

$user_auth_list->execute;

# user auth list
my $authlist_ref = $user_auth_list->fetchall_arrayref();
$user_auth_list->finish();
$dbh->disconnect;

foreach my $row (@$authlist_ref) {
next if (!$row);
my $ip=trim($row->[0]);
my $ip_int=trim($row->[1]);
my $dns_name=trim($row->[2]);
next if (!$ip_int);
next if (!$ip);
my $default_name;
if ($dns_name) { $default_name=$dns_name; } else {
    $default_name = $ip;
    $default_name =~s/192.168.//g;
    $default_name =~s/10.1.//g;
    }

$default_name =~s/_/-/g;
$default_name =~s/[.]/-/g;
$default_name =~s/ /-/g;

print $ip.' '.$default_name."\n";
}

exit 0;
