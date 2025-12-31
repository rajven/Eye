#!/usr/bin/perl

#
# Copyright (C) Roman Dmitriev, rnd@rajven.ru
#

use utf8;
use open ":encoding(utf8)";
use Encode;
no warnings 'utf8';
use English;
use base;
use FindBin '$Bin';
use lib "/opt/Eye/scripts";
use strict;
use DBI;
use Time::Local;
use Net::Patricia;
use NetAddr::IP;
use Data::Dumper;
use eyelib::config;
use eyelib::main;
use eyelib::database;
use eyelib::common;
use eyelib::net_utils;
use File::Basename;
use File::Path;
use Fcntl qw(:flock);
open(SELF,"<",$0) or die "Cannot open $0 - $!";
flock(SELF, LOCK_EX|LOCK_NB) or exit 1;

binmode(STDOUT,':utf8');

setpriority(0,0,19);

my $dhcp_networks = new Net::Patricia;

my %dhcp_conf;
my %static_hole;
my %mac_subnets;

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
my $sSQL="SELECT id,ip,ip_int,mac,comments,dns_name,dhcp_option_set,dhcp_acl,ou_id FROM User_auth where dhcp=1 and deleted=0 ORDER by ip_int";
my @users = get_records_sql($dbh,$sSQL);
foreach my $row (@users) {
next if (!$row);
next if (!$dhcp_networks->match_string($row->{ip}));
next if (!$row->{mac});
next if (!$row->{ip});
next if (is_default_ou($dbh,$row->{ou_id}));
if (exists $static_hole{$row->{ip}}) { $static_hole{$row->{ip}}{skip}=1; }

my $subnet = $dhcp_networks->match_string($row->{ip});
$mac_subnets{$subnet} ||= {
        name => $subnet,
        macs => {}
    };
if (exists $mac_subnets{$subnet}{macs}{$row->{mac}}) {
    my $old_row = $mac_subnets{$subnet}{macs}{$row->{mac}};
    db_log_warning($dbh,"Mac $row->{mac} already exists in DHCP fo subnet $subnet! auth_id: $row->{id} and auth_id: $old_row->{id}");
    next;
    }

$mac_subnets{$subnet}{macs}{$row->{mac}} = $row;

print '#Comment:'.$row->{comments}."\n" if ($row->{comments});
my $dns_name = '';
if ($row->{dns_name}) {
    print '#DNS:'.$row->{dns_name}."\n";
    $dns_name = ','.$row->{dns_name};
    }

my $dhcp_set = '';
if ($row->{dhcp_option_set}) {
    $dhcp_set = ',set:'.$row->{dhcp_option_set};
    }

print 'dhcp-host='.$row->{mac}.$dns_name.','.$row->{ip}.$dhcp_set."\n";
}

foreach my $ip (keys %static_hole) {
if (!$static_hole{$ip}{skip}) {
    print '#BlackHole for static subnet\n';
    print 'dhcp-host='.$static_hole{$ip}->{mac}.', '.$ip."\n";
    }
}

# DNS
print "#--- DNS ---#\n";

#get userid list
my $sSQL="SELECT id,ou_id,ip,dns_name,dhcp_hostname,dns_ptr_only FROM User_auth WHERE deleted=0 AND ip>'' AND (dns_name>'' OR dhcp_hostname>'') AND dns_name NOT LIKE '%.' ORDER by ip_int;";
my @users = get_records_sql($dbh,$sSQL);
foreach my $row (@users) {
next if (!$row);
next if (is_default_ou($dbh,$row->{ou_id}));
next if (!$office_networks->match_string($row->{ip}));

my $dns_name = trim($row->{dns_name});
if ($dns_name) {
    $dns_name =~s/_/-/g;
#    $dns_name =~s/[\.]/-/g;
    $dns_name =~s/ /-/g;
    $dns_name =~s/-$//g;
    $dns_name = trim($dns_name);
    if ($dns_name and $dns_name!~/\.$domain_name$/) { $dns_name = $dns_name .".".$domain_name; }
    } else { $dns_name=''; }

next if (!$dns_name);

#if (!$row->{dns_ptr_only} and ($dns_name or $row->{dhcp_hostname})) {
if (!$row->{dns_ptr_only} and $dns_name) {
    print '#Comment:'.$row->{comments}."\n" if ($row->{comments});
    if ($dns_name) {
        print '#DNS A-record '.$dns_name."\n";
        print 'address=/'.$dns_name.'/'.$row->{ip}."\n";
        } 
#        else {
#        if ($row->{dhcp_hostname} and $row->{dhcp_hostname}!~/UNDEFINED/i) {
#            $dns_name = $row->{dhcp_hostname};
#            $dns_name = $dns_name .".".$domain_name; }
#            $dns_name =~s/_/-/g;
##            $dns_name =~s/[\.]/-/g;
#            $dns_name =~s/ /-/g;
#            $dns_name =~s/-$//g;
#            $dns_name = trim($dns_name);
#            if ($dns_name) {
#                print '#DNS-from-DHCP A-record '.$dns_name."\n";
#                print 'address=/'.$dns_name.'/'.$row->{ip}."\n";
#                }
#            }
    #aliases
    if ($dns_name) {
        my $aSQL="SELECT * FROM `User_auth_alias` WHERE auth_id=$row->{id} AND alias>'' AND alias NOT LIKE '%.';";
        my @aliases = get_records_sql($dbh,$aSQL);
        print '#DNS aliases for '.$dns_name."\n" if (@aliases and scalar @aliases);
        foreach my $alias (@aliases) {
            my $dns_alias = trim($alias->{alias});
#            $dns_alias =~s/$domain_name//i;
            $dns_alias =~s/_/-/g;
            $dns_alias =~s/[\.]/-/g;
            $dns_alias =~s/ /-/g;
            $dns_alias =~s/-$//g;
            $dns_alias = trim($dns_alias);
            if ($dns_alias and $dns_alias !~ /\.\Q$domain_name\E$/i) { $dns_alias = $dns_alias .".".$domain_name; }
            print 'address=/'.$dns_alias.'/'.$row->{ip}."\n" if ($dns_alias);
            }
        }
    }

my $ptr_record='';
if ($dns_name and $row->{ip}=~/([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})/) {
    $ptr_record=$4.".".$3.".".$2.".".$1.".in-addr.arpa";
    print '#PTR for '.$dns_name."\n";
    print 'ptr-record='.$ptr_record.','.$dns_name."\n";
    }
}

exit 0;
