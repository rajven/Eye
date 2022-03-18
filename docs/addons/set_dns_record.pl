#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use utf8;
use English;
use base;
use FindBin '$Bin';
no if $] >= 5.018, warnings =>  "experimental::smartmatch";
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

my $h_name = $ARGV[0];
my $ip = $ARGV[1];

my $reverse = $ARGV[2] || '0';

exit if (!$h_name and !$ip);

my $ad_zone = get_option($dbh,33);
my $ad_dns = get_option($dbh,3);

my $subnets_dhcp = get_subnets_ref($dbh);
my $enable_ad_dns_update = ($ad_zone and $ad_dns);

my $subnet = GetSubNet($ip);

log_debug("Subnet: $subnet");
log_debug("DNS update flags - zone: $ad_zone dns: $ad_dns config: $update_hostname_from_dhcp subnet: $subnets_dhcp->{$subnet}->{dhcp_update_hostname}");

#update dns block
my $fqdn;
if ($h_name) {
    $fqdn=lc($h_name);
    $fqdn=~s/_/-/g;
    if ($fqdn!~/$ad_zone$/i) {
            $fqdn=~s/\.$//;
            $fqdn=lc($fqdn.'.'.$ad_zone);
            }
    }

db_log_info($dbh,"Manual create dns record $fqdn");
update_ad_hostname($fqdn,$ip,$ad_zone,$ad_dns,$dbh);

if ($reverse) {
    db_log_info($dbh,"Manual create dns ptr-record $fqdn => $ip");
    update_ad_ptr($fqdn,$ip,$ad_dns,$dbh);
    }

exit;
