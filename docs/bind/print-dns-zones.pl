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
use eyelib::config;
use eyelib::main;
use eyelib::mysql;

setpriority(0,0,19);

my $named_root='';
my $named_db_fullpath=$named_root.'/etc/bind/masters';
my $named_db_path='/etc/bind/masters';

my $ns1 = 'ns1';

my $DNS1=$config_ref{dns_server};

exit if ($config_ref{dns_server_type!='bind');

my $named_conf=$named_root.'/etc/bind/named.dynamic';

# user auth list
my @authlist_ref = get_records_sql($dbh,"SELECT id,ip,dns_name FROM User_auth WHERE `ip_int`>0 AND `deleted`=0 ORDER BY ip_int");

my %zones;

$zones{$domain_name}{$ns1}=$dns_server;

foreach my $row (@authlist_ref) {
next if (!$row);

my $ip=trim($row->{ip});
my $dns_name=trim($row->{dns_name});
next if (!$ip);
next if (!$office_networks->match_string($ip));

my $default_name=$ip;
$default_name=~s/\./-/g;

if ($dns_name) {
    $default_name=$dns_name;
    $default_name =~s/$domain_name$//g;
    $default_name =~s/\.$/-/g;
    $default_name =~s/_/-/g;
    $default_name =~s/[.]/-/g;
    $default_name =~s/ /-/g;
    $default_name =~s/-$//g;
    $zones{$domain_name}{$default_name}=$ip;
    }

my @dns_names=get_records_sql($dbh,"SELECT * FROM User_auth_alias WHERE auth_id=$row->{id} ORDER BY alias");
foreach my $alias (@dns_names) {
        my $dns = $alias->{alias};
        $dns =~s/$domain_name$//g;
        $dns =~s/\.$/-/g;
        $dns =~s/_/-/g;
        $dns =~s/[.]/-/g;
        $dns =~s/ /-/g;
        $dns =~s/-$//g;
        $zones{$domain_name}{$dns}=$ip;
        }

if ($ip=~/([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})\.([0-9]{1,3})/) {
    my $zone_name=$1;
    my $ip_in_zone=$2;
    $zones{$zone_name}{$ip_in_zone}=$default_name;
    }
}

$dbh->disconnect;

my ($min, $hour, $mday, $mon, $year) = (localtime())[1,2,3,4,5];
$mon += 1;
$year += 1900;
my $yy = $year - 2000;

open(F2,">$named_conf.new") or die "Unamed to open config $named_conf.new!";
flock(F2,2);

foreach my $ZONE (keys %zones) {

my $ZONE_DB=$named_db_fullpath."/db.".$ZONE.".new";
my $reverse=0;
my $zone_name=$ZONE;

if ($ZONE!~/$domain_name/) {
    $reverse=1;
    #skip reverse dns zone
    next;
    if ($ZONE=~/([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})/) {
        $zone_name=$3.".".$2.".".$1.".in-addr.arpa";
        } else {
        print "Unknown zone name: $ZONE!!!\n";
        next;
        }
    }

print F2 "zone $zone_name \{\n";
print F2 "type master;\n";
print F2 "file \"$named_db_path"."/db.".$ZONE."\";\n";
print F2 "allow-update { key rndc-key; };\n";
#print F2 "allow-transfer { second; };\n";
print F2 "\};\n";
print F2 "\n";

open(F1,">$ZONE_DB") or die "Unable to open config file $ZONE_DB!" ;
flock(F1,2);

print F1  "\$ORIGIN .\n";
print F1  "\$TTL 3600\t; 1 hour\n";
print F1  "$zone_name\t\t\t\tIN SOA\t\t$DNS1. root.$DNS1. (\n";
printf F1 "\t\t\t\t%04d%02d%02d%02d ; serial\n",$year,$mon,$mday,$hour;
print F1  "\t\t\t\t900\t; refresh (15 minutes)\n";
print F1  "\t\t\t\t600\t; retry (10 minutes)\n";
print F1  "\t\t\t\t86400\t; expire (1 day)\n";
print F1  "\t\t\t\t3600\t; minimum (1 hour)\n";
print F1  "\t\t\t\t)\n";
print F1  "\t\t\t\tNS\t $DNS1.\n";
if ($dns_server) {
print F1  "\t\t\t\tA\t $dns_server\n";
}
print F1  ";\n";
print F1  "\$TTL 3600\t; 1 hour\n";
print F1  "; host list\n";
print F1  "\$ORIGIN $zone_name.\n";

foreach my $record (sort keys %{$zones{$ZONE}}) {
if ($reverse) {
    print  F1 "$record\t\t\tIN\tPTR\t$zones{$ZONE}->{$record}.$domain_name.\n";
    } else {
    print  F1 "$record\t\t\t\tA\t$zones{$ZONE}->{$record}\n";
    }
}
}

close(F1);
close(F2);

exit;
