#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use utf8;
use English;
use base;
use FindBin '$Bin';
use lib "$Bin/";
use strict;
use DBI;
use Time::Local;
use Net::Patricia;
use NetAddr::IP;
use Data::Dumper;
use Rstat::config;
use Rstat::main;
use Rstat::mysql;
use Rstat::net_utils;
use File::Basename;
use File::Path;

my $RET_OK=0;
my $RET_WARNING=1;
my $RET_UNKNOWN=3;
my $RET_CRITICAL=2;

my $MSG_OK="OK";
my $MSG_WARNING="WARN";
my $MSG_CRITICAL="CRIT";

my $warning_limit=$ARGV[1] || 10;
my $crit_limit=$ARGV[2] || 5;

setpriority(0,0,19);

my %dhcp_conf;

my $dhcp_networks = new Net::Patricia;

my @subnets=get_records_sql($dbh,'SELECT * FROM subnets WHERE dhcp=1 and office=1 and vpn=0 and hotspot=0 ORDER BY ip_int_start');
foreach my $subnet (@subnets) {
next if (!$subnet->{gateway});
my $subnet_name = $subnet->{subnet};
$subnet_name=~s/\/\d+$//g;
$dhcp_networks->add_string($subnet->{subnet},$subnet_name);
$dhcp_conf{$subnet_name}->{first_ip}=IpToStr($subnet->{dhcp_start});
$dhcp_conf{$subnet_name}->{last_ip}=IpToStr($subnet->{dhcp_stop});
$dhcp_conf{$subnet_name}->{first_ip_aton}=$subnet->{dhcp_start};
$dhcp_conf{$subnet_name}->{last_ip_aton}=$subnet->{dhcp_stop};
$dhcp_conf{$subnet_name}->{dhcp_pool_size}=$subnet->{dhcp_stop}-$subnet->{dhcp_start};
}

#get userid list
my $sSQL="SELECT id,ip,ip_int,mac,comments,dns_name FROM User_auth where dhcp=1 and deleted=0 and ou_id<>$default_hotspot_ou_id and ou_id<>$default_user_ou_id ORDER by ip_int";
my @users = get_records_sql($dbh,$sSQL);
foreach my $row (@users) {
next if (!$row);
next if (!$dhcp_networks->match_string($row->{ip}));
next if (!$row->{mac});
next if (!$row->{ip});
my $subnet_name = $dhcp_networks->match_string($row->{ip});
if ($row->{ip_int}~~[$dhcp_conf{$subnet_name}->{first_ip_aton} .. $dhcp_conf{$subnet_name}->{last_ip_aton}]) { $dhcp_conf{$subnet_name}->{dhcp_pool_size}--; }
}

my @warning=();
my @critical=();

foreach my $subnet_name (keys %dhcp_conf) {
if ($dhcp_conf{$subnet_name}->{dhcp_pool_size}>$warning_limit) { next; }

my $free_count = $dhcp_conf{$subnet_name}->{dhcp_pool_size};

if ($free_count <=$warning_limit and $free_count>$crit_limit ) { 
    push(@warning,"$subnet_name - there are $free_count free addresses left!");
    next;
    }

if ($free_count <=$crit_limit) {
    push(@critical,"$subnet_name - there are $free_count free addresses left!");
    next;
    }
}


if (scalar(@critical)>0) {
    foreach my $row (@critical) { print "$MSG_CRITICAL: $row\n"; }
    foreach my $row (@warning) { print "$MSG_WARNING: $row\n"; }
    exit $RET_CRITICAL;
    }

if (scalar(@critical)>0) {
    foreach my $row (@warning) { print "$MSG_WARNING: $row\n"; }
    exit $RET_WARNING;
    }

print "$MSG_OK: Dhcp pool OK!\n";

exit $RET_OK;
