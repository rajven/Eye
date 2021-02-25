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
use Rstat::mysql;
use NetAddr::IP;

setpriority(0,0,19);

#get userid list
my $user_auth_list = $dbh->prepare( "SELECT id,ip,mac,user_id FROM User_auth Where deleted=0 ORDER by id,ip_int,mac" );
if ( !defined $user_auth_list ) { die "Cannot prepare statement: $DBI::errstr\n"; }
$user_auth_list->execute;
my $authlist_ref = $user_auth_list->fetchall_arrayref();
$user_auth_list->finish();

$dbh->disconnect;

my %nethash;

foreach my $net (@all_network_list) {
    $nethash{$net}{network}= new Net::Patricia;
    $nethash{$net}{network}->add_string($net);
}

foreach my $row (@$authlist_ref) {
    my $id=$row->[0];
    my $ip=$row->[1];
    my $mac=mac_splitted($row->[2]);
    my $user_id=$row->[3];
    foreach my $net (keys %nethash) {
        if ($nethash{$net}{network}->match_string($ip)) {
            if (exists $nethash{$net}{$mac}) {
                print "Dup found! id:$id mac: $mac ip:$ip First id: $nethash{$net}{$mac}{id} ip: $nethash{$net}{$mac}{ip}\n";
                last;
                }
            $nethash{$net}{$mac}{id}=$id;
            $nethash{$net}{$mac}{ip}=$ip;
            last;
            }
    }
}


exit 0;

