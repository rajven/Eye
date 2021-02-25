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

@office_network_list=();
@hotspot_network_list=();

#office nets
$office_networks = new Net::Patricia;
my $ret=get_option($dbh,13);
if (ref($ret) eq 'ARRAY') {
    foreach my $net (@$ret) {
        next if (!$net);
        push(@office_network_list,$net);
        }
    } else {
    push(@office_network_list,$ret);
    }

#hotspot nets
$hotspot_networks = new Net::Patricia;
$ret=get_option($dbh,42);
if (ref($ret) eq 'ARRAY') {
    foreach my $net (@$ret) {
        next if (!$net);
        push(@hotspot_network_list,$net);
        }
    } else {
    if ($ret) {
        push(@hotspot_network_list,$ret);
        }
    }

foreach my $net (@office_network_list) {
next if (!$net);
my $dhcp=GetDhcpRange($net);
my $start = StrToIp($dhcp->{first_ip})-1;
my $stop = StrToIp($dhcp->{last_ip})+1;
my $sSQL='INSERT INTO subnets (subnet,ip_int_start,ip_int_stop,office,hotspot) VALUES("'.$net.'",'."$start,$stop,1,0)";
do_sql($dbh,$sSQL);
}

foreach my $net (@hotspot_network_list) {
next if (!$net);
my $dhcp=GetDhcpRange($net);
my $start = StrToIp($dhcp->{first_ip})-1;
my $stop = StrToIp($dhcp->{last_ip})+1;
my $sSQL='INSERT INTO subnets (subnet,ip_int_start,ip_int_stop,office,hotspot) VALUES("'.$net.'",'."$start,$stop,0,1)";
do_sql($dbh,$sSQL);
}

do_sql($dbh,'DELETE FROM config WHERE option_id=42 or option_id=13');
do_sql($dbh,'DELETE FROM config_options WHERE id=42 or id=13');

exit;
