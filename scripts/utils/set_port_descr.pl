#!/usr/bin/perl 

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
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
use Time::Local;
use FileHandle;
use Data::Dumper;
use eyelib::config;
use eyelib::main;
use eyelib::net_utils;
use eyelib::database;
use eyelib::common;
use eyelib::snmp;
use eyelib::cmd;
use Net::SNMP qw(:snmp);
use Fcntl qw(:flock);

open(SELF,"<",$0) or die "Cannot open $0 - $!";
flock(SELF, LOCK_EX|LOCK_NB) or exit 1;

my @auth_list = get_records_sql($dbh,"SELECT A.id,A.user_id,A.ip,A.mac,A.dns_name,A.comments,A.dhcp_hostname,A.WikiName,K.login,K.ou_id FROM user_auth as A, user_list as K WHERE K.id=A.user_id AND A.deleted=0 ORDER BY A.id");

my %auth_ref;
foreach my $auth (@auth_list) {
$auth_ref{$auth->{id}}{id}=$auth->{id};
$auth_ref{$auth->{id}}{ou_id}=$auth->{ou_id};
$auth_ref{$auth->{id}}{ip}=$auth->{ip};
$auth_ref{$auth->{id}}{mac}=$auth->{mac};
$auth_ref{$auth->{id}}{dns_name}=$auth->{dns_name};
$auth_ref{$auth->{id}}{comments}=$auth->{comments};
$auth_ref{$auth->{id}}{dhcp_hostname}=$auth->{dhcp_hostname};
$auth_ref{$auth->{id}}{WikiName}=$auth->{WikiName};
$auth_ref{$auth->{id}}{login}=$auth->{login};
my $a_netdev = get_record_sql($dbh,"SELECT * FROM devices WHERE user_id = ".$auth->{user_id});
$auth_ref{$auth->{id}}{device}=$a_netdev;
if ($auth->{dns_name}) { $auth_ref{$auth->{id}}{description} = $auth->{dns_name}; }
if (!$auth_ref{$auth->{id}}{description} and $auth->{WikiName}) { $auth_ref{$auth->{id}}{description} = $auth->{WikiName}; }
if (!$auth_ref{$auth->{id}}{description} and $auth->{comments}) { $auth_ref{$auth->{id}}{description} = translit($auth->{comments}); }
if (!$auth_ref{$auth->{id}}{description}) { $auth_ref{$auth->{id}}{description} = $auth->{ip}; }
$auth_ref{$auth->{id}}{description}=~s/\./-/g;
$auth_ref{$auth->{id}}{description}=~s/\(/_/g;
$auth_ref{$auth->{id}}{description}=~s/\)/_/g;
}

my %port_info;

my $d_sql="SELECT DP.id, D.ip, D.device_name, D.device_model_id, DP.port, DP.snmp_index, DP.comment, DP.target_port_id, D.vendor_id, D.device_type
FROM devices AS D, device_ports AS DP
WHERE D.id = DP.device_id AND (D.device_type <=1) AND D.deleted=0
ORDER BY D.device_name,DP.port";

my @port_list = get_records_sql($dbh,$d_sql);

foreach my $port (@port_list) {
$port_info{$port->{id}}{id}=$port->{id};
$port_info{$port->{id}}{device_name}=lc($port->{device_name});
$port_info{$port->{id}}{ip}=$port->{ip};
$port_info{$port->{id}}{device_model_id}=$port->{device_model_id};
$port_info{$port->{id}}{port}=$port->{port};
$port_info{$port->{id}}{snmp_index}=$port->{snmp_index};
$port_info{$port->{id}}{comment}=$port->{comment};
$port_info{$port->{id}}{target_port_id}=$port->{target_port_id};
$port_info{$port->{id}}{vendor_id}=$port->{vendor_id};
$port_info{$port->{id}}{device_type}=$port->{device_type};
}

my %conn_info;

$d_sql="SELECT C.id, C.port_id, C.auth_id FROM connections AS C, user_auth as A WHERE A.id=C.auth_id AND A.deleted=0 ORDER BY C.id";
my @conn_list = get_records_sql($dbh,$d_sql);

foreach my $conn (@conn_list) {
$conn_info{$conn->{id}}{id}=$conn->{id};
$conn_info{$conn->{id}}{port_id}=$conn->{port_id};
if ($conn->{auth_id}) {
    $conn_info{$conn->{id}}{auth_id}=$conn->{auth_id};
    $conn_info{$conn->{id}}{description}=$auth_ref{$conn->{auth_id}}->{description};
    $conn_info{$conn->{id}}{ou_id}=$auth_ref{$conn->{auth_id}}->{ou_id};
    }
}


foreach my $conn_id (keys %conn_info) {
if (exists $port_info{$conn_info{$conn_id}{port_id}}{count}) {
    $port_info{$conn_info{$conn_id}{port_id}}{count}++;
    #OU: Switches, Routers, WiFi AP
    if ($conn_info{$conn_id}{device} and $conn_info{$conn_id}{description}) {
        if ($conn_info{$conn_id}{device}{device_name}) {
            $port_info{$conn_info{$conn_id}{port_id}}{description} = $conn_info{$conn_id}{device}{device_name};
            } else {
            $port_info{$conn_info{$conn_id}{port_id}}{description} = $conn_info{$conn_id}{description};
            }
        }
    next;
    } else { $port_info{$conn_info{$conn_id}{port_id}}{count}=1; }

if (!exists $port_info{$conn_info{$conn_id}{port_id}}{description} and $conn_info{$conn_id}{description}) {
    $port_info{$conn_info{$conn_id}{port_id}}{description} = $conn_info{$conn_id}{description};
    }
}

my %devices;

foreach my $port_id (keys %port_info) {
if ($port_info{$port_id}{target_port_id}) {
    $port_info{$port_id}{description}=$port_info{$port_info{$port_id}{target_port_id}}{device_name}." [".$port_info{$port_info{$port_id}{target_port_id}}{port}.']';
    }
if (!$port_info{$port_id}{description} and $port_info{$port_id}{comment}) { $port_info{$port_id}{description}=translit($port_info{$port_id}{comment}); }
$devices{$port_info{$port_id}{device_name}}{ports}{$port_info{$port_id}{port}}{description}=$port_info{$port_id}{description};
$devices{$port_info{$port_id}{device_name}}{ports}{$port_info{$port_id}{port}}{snmp_index}=$port_info{$port_id}{snmp_index};
$devices{$port_info{$port_id}{device_name}}{device_name}=$port_info{$port_id}{device_name};
$devices{$port_info{$port_id}{device_name}}{ip}=$port_info{$port_id}{ip};
$devices{$port_info{$port_id}{device_name}}{device_model_id}=$port_info{$port_id}{device_model_id};
$devices{$port_info{$port_id}{device_name}}{vendor_id}=$port_info{$port_id}{vendor_id};
$devices{$port_info{$port_id}{device_name}}{device_type}=$port_info{$port_id}{device_type};
}


#$Net::OpenSSH::debug=-1;

foreach my $device_name (sort keys %devices) {
my $device = $devices{$device_name};

#skip unknown vendor
next if (!$switch_auth{$device->{vendor_id}});


my $ip = $device->{ip};

my $netdev = get_record_sql($dbh,"SELECT * FROM devices WHERE ip='".$ip."'");

next if (!$netdev);

print "Device: $device_name IP: $ip ";

if (!HostIsLive($ip)) { print "... Down! Skip.\n"; next; }

print "... Programming:\n";

setCommunity($netdev);

eval {
#get interface names
my $int = get_interfaces($ip,$netdev->{snmp},0);

$netdev = netdev_set_auth($netdev);

$device->{login}= $netdev->{login};
$device->{password}= $netdev->{password};
$device->{enable_password}='';
$device->{proto} = $netdev->{proto};
$device->{port} = $netdev->{port};

my $session = netdev_login($device);

if ($session) {
    netdev_set_hostname($session,$device);
    foreach my $port (sort  { $a <=> $b } keys %{$device->{ports}}) {
        my $descr = $device->{ports}{$port}{description};
        next if ($descr =~ /^-port-$/);
        next if ($descr =~ /^\s+\[\]$/);
        my $index = $device->{ports}{$port}{snmp_index};
        print "Port: $port index: $index Descr: $descr\n";
        netdev_set_port_descr($session,$device,$int->{$index}->{name},$port,$descr);
        }
    netdev_wr_mem($session,$device);
    } else { print "Login error!\n"; next; }
};
if ($@) { print "Error! Apply failed!\n"; next; }

print "Programming finished.\n";
}

exit;
