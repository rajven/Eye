#!/usr/bin/perl 

#
# Copyright (C) Roman Dmitriev, rnd@rajven.ru
#

use utf8;
use warnings;
use Encode;
use open qw(:std :encoding(UTF-8));
no warnings 'utf8';

use FindBin '$Bin';
use lib "/opt/Eye/scripts";
use strict;
use DBI;
use Time::Local;
use Net::Patricia;
use Data::Dumper;
use eyelib::config;
use eyelib::main;
use eyelib::database;
use eyelib::common;

setpriority(0,0,19);

my $named_root='';
my $named_db_fullpath=$named_root.'/etc/bind/masters';
my $named_db_path='/etc/bind/masters';

my $DNS1=$config_ref{dns_server};
my $DNS1_IP=$config_ref{dns_server};

my $dns_server_record = get_record_sql($dbh,"SELECT id,ip,dns_name FROM user_auth WHERE deleted=0 AND ip=?",$DNS1_IP);

if ($dns_server_record and $dns_server_record->{dns_name}) { 
    my $ns1=$dns_server_record->{dns_name};
    $ns1 =~s/\.$//g;
    $ns1 =~s/_/-/g;
#    $dns_name =~s/[\.]/-/g;
    $ns1 =~s/ /-/g;
    $ns1 =~s/-$//g;
    $ns1 = trim($ns1);
    if ($ns1 and $ns1 !~ /\.\Q$domain_name\E$/i) { $ns1 = $ns1 .".".$domain_name; }
    $DNS1 = $ns1;
    }

#exit if ($config_ref{dns_server_type!='bind');

my $named_conf=$named_root.'/etc/bind/named.dynamic';

my %zones;

my $sSQL="SELECT id,ou_id,ip,dns_name,dhcp_hostname,dns_ptr_only FROM user_auth WHERE deleted=0 AND ip>'' AND (dns_name>'' OR dhcp_hostname>'') AND dns_name NOT LIKE '%.' ORDER by ip_int;";
my @authlist_ref = get_records_sql($dbh,$sSQL);
foreach my $row (@authlist_ref) {
next if (!$row);
next if (is_default_ou($dbh,$row->{ou_id}));
my $dns_name = trim($row->{dns_name});
if ($dns_name) {
#    $dns_name =~s/$domain_name//i;
    $dns_name =~s/\.$//g;
    $dns_name =~s/_/-/g;
#    $dns_name =~s/[\.]/-/g;
    $dns_name =~s/ /-/g;
    $dns_name =~s/-$//g;
    $dns_name = trim($dns_name);
    if ($dns_name and $dns_name !~ /\.\Q$domain_name\E$/i) { $dns_name = $dns_name .".".$domain_name; }
    } else { $dns_name=''; }

next if (!$dns_name);

my $ip=trim($row->{ip});
next if (!$ip);
next if (!$office_networks->match_string($ip));

my $default_name=$dns_name;
$zones{$domain_name}{A}{$default_name}=$ip;

my @dns_aliases=get_records_sql($dbh,"SELECT * FROM user_auth_alias WHERE auth_id=$row->{id} AND alias>'' AND alias NOT LIKE '%.' ORDER BY alias");
foreach my $alias (@dns_aliases) {
        my $dns_alias = trim($alias->{alias});
#        $dns_alias =~s/$domain_name//i;
        $dns_alias =~s/_/-/g;
        $dns_alias =~s/[\.]/-/g;
        $dns_alias =~s/ /-/g;
        $dns_alias =~s/-$//g;
        $dns_alias = trim($dns_alias);
        if ($dns_alias and $dns_alias !~ /\.\Q$domain_name\E$/i) { $dns_alias = $dns_alias .".".$domain_name; }
        $zones{$domain_name}{CNAME}{$dns_alias}=$default_name if ($dns_alias);
        }

if ($ip=~/([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})\.([0-9]{1,3})/) {
    my $zone_name=$1;
    my $ip_in_zone=$2;
    $zones{$zone_name}{PTR}{$ip_in_zone}=$default_name;
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
print F1  $zone_name."\t\tIN SOA\t\t".$DNS1." root.".$DNS1.". (\n";
printf F1 "\t\t\t\t%04d%02d%02d%02d ; serial\n",$year,$mon,$mday,$hour;
print F1  "\t\t\t\t900\t; refresh (15 minutes)\n";
print F1  "\t\t\t\t600\t; retry (10 minutes)\n";
print F1  "\t\t\t\t86400\t; expire (1 day)\n";
print F1  "\t\t\t\t3600\t; minimum (1 hour)\n";
print F1  "\t\t\t\t)\n";
print F1  "\t\t\t\tNS\t $DNS1\n";
print F1  ";\n";

#A-record for domain
if ($DNS1) { 
    print F1  ";A-record for domain\n";
    print F1  "\t\t\t\tA\t $DNS1_IP\n"; 
    }

print F1  "\$TTL 3600\t; 1 hour\n";
print F1  "; host list\n";

if ($reverse) {
    print F1  "\$ORIGIN $zone_name.\n";
    foreach my $record (sort keys %{$zones{$ZONE}->{PTR}}) {
        print  F1 "$record\t\t\tIN\tPTR\t$zones{$ZONE}->{PTR}->{$record}.\n";
        }
    } else {
    #print F1  "\$ORIGIN $zone_name.\n";
    foreach my $record (sort keys %{$zones{$ZONE}->{A}}) {
        print  F1 "$record\t\t\t\tA\t$zones{$ZONE}->{A}->{$record}\n";
        };
    foreach my $record (sort keys %{$zones{$ZONE}->{CNAME}}) {
        print  F1 "$record\t\t\t\tCNAME\t$zones{$ZONE}->{CNAME}->{$record}.\n";
        };
    }
}

close(F1);
close(F2);

exit;
