#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use FindBin '$Bin';
use lib "$Bin/";
use strict;
use DBI;
use Time::Local;
use Data::Dumper;
use eyelib::config;
use eyelib::main;
use eyelib::net_utils;
use eyelib::mysql;
use Text::Iconv;

exit;

my $dhcp_server=$ARGV[0] || '192.168.7.17';

my $test_only=1;

my %nets;

foreach my $net (@office_network_list) {
my $scope_name=$net;
$scope_name =~s/\/\d+$//g;
$nets{$scope_name}= new Net::Patricia;
$nets{$scope_name}->add_string($net);
}

######################################### current state ###############################################
my %dhcp_state_current;
my %dhcp_state_new;
my %dynamic_ip;

my $converter = Text::Iconv->new("cp866", "utf8");

my %dhcp_scope;

my $run_cmd=$winexe." -U '".$domain_auth."' '//".$dhcp_server."' \"netsh Dhcp Server show scope\" 2>/dev/null";


my @scope_dump=`$run_cmd`;

foreach my $row (@scope_dump) {
$row =~s/^\s+//;
$row=~s/\"//g;
$row=~s/\-\s+//g;
next if ($row!~/(^192.168|^10.|^172.16)/);
my ($scope,$a,$a2,$scope_name,$a4)=split(/\s+/,$row);
$dhcp_scope{$scope}=$scope;
}

foreach my $scope (keys %dhcp_scope) {
    next if (!$scope);
    next if ($scope!~/(^192.168|^10.|^172.16)/);
    $run_cmd=$winexe." -U '".$domain_auth."' '//".$dhcp_server."' \"netsh Dhcp Server Scope ".$scope." dump\" 2>/dev/null";
    @scope_dump=`$run_cmd`;
    foreach my $row (@scope_dump) {
        next if (!$row);
        chomp($row);
        next if (!$row);
        next if ($row!~/^Dhcp Server/i);
        next if ($row!~/Add reservedip/i);
        $row=~s/\"//g;
        $row = $converter->convert($row);
        my ($a1,$a2,$a3,$a4,$a5,$a6,$a7,$reserved_ip,$reserved_mac,$hostname,$comment,$dhcp_type)=split(/ /,$row);
        if (length($reserved_mac)>12) {
            $dhcp_state_current{$scope}{$reserved_ip}{clientid}=$reserved_mac;
            } else {
            $dhcp_state_current{$scope}{$reserved_ip}{mac}=mac_simplify($reserved_mac);
            }
        $dhcp_state_current{$scope}{$reserved_ip}{hostname}=$hostname;
        $dhcp_state_current{$scope}{$reserved_ip}{comment}=$comment;
        }
    $run_cmd=$winexe." -U '".$domain_auth."' '//".$dhcp_server."' \"netsh Dhcp Server Scope ".$scope." show clients\" 2>/dev/null";
    @scope_dump=`$run_cmd`;
    foreach my $row (@scope_dump) {
        next if (!$row);
        chomp($row);
        next if (!$row);
        next if ($row!~/(^192\.168\.|^10\.|^172\.16\.)/);
        $row=~s/\-//g;
        $row = $converter->convert($row);
        my ($active_ip,$a1,$reserved_mac,$a5,$a6,$a7)=split(/\s+/,$row);
        #skip static ip
        next if ($dhcp_state_current{$scope}{$active_ip});
        #detect client-id
        if (length($reserved_mac)>12) { next; }
        $dynamic_ip{$active_ip}{mac}=mac_simplify($reserved_mac);
        }
    }

foreach my $dhcp_ip (keys %dynamic_ip) {
next if (!$office_networks->match_string($dhcp_ip));
print "New dynamic ip: ".$dhcp_ip." ".$dynamic_ip{$dhcp_ip}{mac}."\n";
if (!$test_only) {
    do_exec("/opt/Eye/scripts/add-to-stat.pl '".$dhcp_ip."' '".$dynamic_ip{$dhcp_ip}{mac}."' '' 'old'");
    }
}

######################################### configuration ###############################################

#get userid list
my $user_auth_list = $dbh->prepare( "SELECT id,ip,ip_int,mac,clientid,dns_name FROM User_auth where deleted=0 ORDER by ip_int" );
if ( !defined $user_auth_list ) { die "Cannot prepare statement: $DBI::errstr\n"; }

$user_auth_list->execute;

# user auth list
my $authlist_ref = $user_auth_list->fetchall_arrayref();
$user_auth_list->finish();
$dbh->disconnect;

foreach my $row (@$authlist_ref) {
next if (!$row);
my $ip=trim($row->[1]);
my $ip_int=trim($row->[2]);
my $mac=trim($row->[3]);
my $clientid=trim($row->[4]);
my $dns_name=trim($row->[5]);

next if (!$ip_int);
next if (!$mac);
next if (!$ip);
$mac=mac_simplify($mac);

my $scope_name;
foreach my $scope (keys %nets) {
    if ($nets{$scope}->match_string($ip)) { $scope_name=$scope; }
    }

next if (!$scope_name);

my $default_name;
if ($dns_name) { $default_name=$dns_name; } else {
    $default_name = $ip;
    $default_name =~s/192.168.//g;
    }

$default_name =~s/_/-/g;
$default_name =~s/[.]/-/g;
$default_name =~s/ /-/g;

$dhcp_state_new{$scope_name}{$ip}{mac}=mac_simplify($mac);
$dhcp_state_new{$scope_name}{$ip}{hostname}=$default_name;
$dhcp_state_new{$scope_name}{$ip}{clientid}=$clientid;
}

######################################## diff #############################################

my @run_cmd=();
foreach my $scope (keys %dhcp_scope) {

    foreach my $check_ip (keys %{$dhcp_state_new{$scope}}) {
    if ($dhcp_state_current{$scope}{$check_ip}{mac} or $dhcp_state_current{$scope}{$check_ip}{clientid}) {
        my $old_mac='';
        if ($dhcp_state_current{$scope}{$check_ip}{mac}) { $old_mac=$dhcp_state_current{$scope}{$check_ip}{mac}; }
        if ($dhcp_state_current{$scope}{$check_ip}{clientid}) { $old_mac=$dhcp_state_current{$scope}{$check_ip}{clientid}; }
        #check clientid
        if ($dhcp_state_new{$scope}{$check_ip}{clientid}) {
            if ($dhcp_state_new{$scope}{$check_ip}{clientid}=~/$dhcp_state_current{$scope}{$check_ip}{clientid}/) { next; }
            push(@run_cmd,'Dhcp Server Scope '.$scope.' del reservedip '.$check_ip.' '.$old_mac);
            push(@run_cmd,'Dhcp Server Scope '.$scope.' add reservedip '.$check_ip.' '.$dhcp_state_new{$scope}{$check_ip}{clientid}.' "'.$dhcp_state_new{$scope}{$check_ip}{hostname}.'" "" "DHCP"');
            next;
            }
        #check mac
        if ($dhcp_state_new{$scope}{$check_ip}{mac}=~/$dhcp_state_current{$scope}{$check_ip}{mac}/i) { next; }
        push(@run_cmd,'Dhcp Server Scope '.$scope.' del reservedip '.$check_ip.' '.$old_mac);
        push(@run_cmd,'Dhcp Server Scope '.$scope.' add reservedip '.$check_ip.' '.$dhcp_state_new{$scope}{$check_ip}{mac}.' "'.$dhcp_state_new{$scope}{$check_ip}{hostname}.'" "" "DHCP"');
        next;
        }
    my $mac=$dhcp_state_new{$scope}{$check_ip}{mac};
    if ($dhcp_state_new{$scope}{$check_ip}{clientid}) { $mac=$dhcp_state_new{$scope}{$check_ip}{clientid}; }
    push(@run_cmd,'Dhcp Server Scope '.$scope.' add reservedip '.$check_ip.' '.$mac.' "'.$dhcp_state_new{$scope}{$check_ip}{hostname}.'" "" "DHCP"');
    }

    foreach my $check_ip (keys %{$dhcp_state_current{$scope}}) {
#    if ($dhcp_state_current{$scope}{$check_ip}{clientid}) { print "Found clientid for $check_ip: $dhcp_state_current{$scope}{$check_ip}{clientid}\n"; }
    if ($dhcp_state_new{$scope}{$check_ip}{mac}) { next; }
    if ($dhcp_state_new{$scope}{$check_ip}{clientid}) { next; }
    my $mac='';
    my $clientid='';
    if ($dhcp_state_current{$scope}{$check_ip}{mac}) { $mac=$dhcp_state_current{$scope}{$check_ip}{mac}; }
    if ($dhcp_state_current{$scope}{$check_ip}{clientid}) { $clientid=$dhcp_state_current{$scope}{$check_ip}{clientid}; }
    next if (!$office_networks->match_string($check_ip));
    print "Unknown reserved ip: Dhcp Server Scope ".$scope.' del reservedip '.$check_ip.' '.$mac."\n";
    push(@run_cmd,'Dhcp Server Scope '.$scope.' del reservedip '.$check_ip.' '.$mac);
#    if (!$test_only) {
#        do_exec("/opt/Eye/scripts/add-to-stat.pl '".$check_ip."' '".$mac."' '' 'old' '".$clientid."'");
#        }
    }
}

foreach my $cmd (@run_cmd) {
next if (!$cmd);
my $run_cmd=$winexe." -U '".$domain_auth."' '//".$dhcp_server."' \"netsh ".$cmd."\"";
print "$cmd\n";
if (!$test_only) { do_exec($run_cmd);}
}

exit 0;
