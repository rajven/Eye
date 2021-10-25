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
use Fcntl qw(:flock);
open(SELF,"<",$0) or die "Cannot open $0 - $!";
flock(SELF, LOCK_EX|LOCK_NB) or exit 1;

binmode(STDOUT,':utf8');

setpriority(0,0,19);

my %dhcp_conf;

my $connected = new Net::Patricia;
my $dhcp_networks = new Net::Patricia;

my $int_addr=do_exec('/sbin/ip addr show | grep "scope global"');
foreach my $address (split(/\n/,$int_addr)) {
if ($address=~/inet\s+(.*)\s+brd/i) {
    if ($1) { $connected->add_string($1); }
    }
}

my %static_hole;

my @subnets=get_records_sql($dbh,'SELECT * FROM subnets WHERE dhcp=1 and office=1 and vpn=0 and hotspot=0 ORDER BY ip_int_start');
foreach my $subnet (@subnets) {
next if (!$subnet->{gateway});
$dhcp_networks->add_string($subnet->{subnet});
my $subnet_name = $subnet->{subnet};
$subnet_name=~s/\/\d+$//g;
$dhcp_conf{$subnet_name}->{first_ip}=IpToStr($subnet->{dhcp_start});
$dhcp_conf{$subnet_name}->{last_ip}=IpToStr($subnet->{dhcp_stop});
$dhcp_conf{$subnet_name}->{relay_ip}=IpToStr($subnet->{gateway});
my $dhcp=GetDhcpRange($subnet->{subnet});
if ($subnet->{static}) {
    $static_hole{$dhcp_conf{$subnet_name}->{last_ip}}->{mac}="01:02:03:04:05:06";
    $static_hole{$dhcp_conf{$subnet_name}->{last_ip}}->{skip}=0;
    print "dhcp-range=net-$subnet_name,$dhcp_conf{$subnet_name}->{last_ip},$dhcp_conf{$subnet_name}->{last_ip},$dhcp->{mask},$subnet->{dhcp_lease_time}m\n";
    } else {
    print "dhcp-range=net-$subnet_name,$dhcp_conf{$subnet_name}->{first_ip},$dhcp_conf{$subnet_name}->{last_ip},$dhcp->{mask},$subnet->{dhcp_lease_time}m\n";
    }
print "dhcp-option=net:net-$subnet_name,option:router,$dhcp_conf{$subnet_name}->{relay_ip}\n";
}

#get userid list
my $sSQL="SELECT id,ip,ip_int,mac,comments,dns_name FROM User_auth where dhcp=1 and deleted=0 and user_id<>$hotspot_user_id and user_id<>$default_user_id ORDER by ip_int";
my @users = get_records_sql($dbh,$sSQL);
foreach my $row (@users) {
next if (!$row);
next if (!$dhcp_networks->match_string($row->{ip}));
next if (!$row->{mac});
next if (!$row->{ip});
if (exists $static_hole{$row->{ip}}) { $static_hole{$row->{ip}}{skip}=1; }
#print '#'.$row->{comments}."\n" if ($row->{comments});
print '#Comment:'.$row->{comments}."\n" if ($row->{comments});
print '#DNS:'.$row->{dns_name}."\n" if ($row->{dns_name});
print 'dhcp-host='.$row->{mac}.', '.$row->{ip}."\n";
}

foreach my $ip (keys %static_hole) {
if (!$static_hole{$ip}{skip}) {
    print '#BlackHole for static subnet\n';
    print 'dhcp-host='.$static_hole{$ip}->{mac}.', '.$ip."\n";
    }
}

exit 0;
