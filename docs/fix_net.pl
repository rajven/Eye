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
use Rstat::config;
use Rstat::main;
use Rstat::net_utils;
use Rstat::snmp;
use Rstat::mysql;
use NetAddr::IP;

setpriority(0,0,19);

#get userid list
my $user_auth_list = $dbh->prepare( "SELECT id,ip FROM User_auth ORDER by ip" );
if ( !defined $user_auth_list ) { die "Cannot prepare statement: $DBI::errstr\n"; }
$user_auth_list->execute;
my $authlist_ref = $user_auth_list->fetchall_arrayref();
$user_auth_list->finish();

my @batch_sql=();

foreach my $row (@$authlist_ref) {
my $ip=$row->[1];
my $id=$row->[0];
my $net=GetDhcpRange($ip);
print "Auth id: $id Found network: $ip\n";
print "NETWORK: $net->{network}\nBROADCAST: $net->{broadcast}\nMASK: $net->{mask}\n";
my $ip_aton=StrToIp($net->{network});
my $ip_aton_end=StrToIp($net->{broadcast});
push(@batch_sql,"Update User_auth set ip_int=".$ip_aton.", ip_int_end=".$ip_aton_end." where id=".$id);
}


if (scalar @batch_sql) {
    $dbh->{AutoCommit} = 0;
    my $sth;
    foreach my $sSQL(@batch_sql) {
    print "$sSQL\n";
    $sth = $dbh->prepare($sSQL);
    $sth->execute;
    }
    $sth->finish;
    $dbh->{AutoCommit} = 1;
    }

$dbh->disconnect;

exit 0;
