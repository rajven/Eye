#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use utf8;
use English;
use base;
use FindBin '$Bin';
use lib "$Bin/";
use Data::Dumper;
use Rstat::config;
use Rstat::main;
use Rstat::mysql;
use Rstat::net_utils;
use strict;
use warnings;
use File::Find;
use File::Basename;
use Fcntl qw(:flock);
open(SELF,"<",$0) or die "Cannot open $0 - $!";
flock(SELF, LOCK_EX|LOCK_NB) or exit 1;

if (!$config_ref{wiki_path}) { exit; }

my %content;
find( \&wanted, $config_ref{wiki_path});

foreach my $fname (keys %content) {
open (FF,"<$content{$fname}") or die "unable to open file $content{$fname}!" ;
my @tmp=<FF>;
close(FF);
chomp(@tmp);

my @wiki_dev=();
my $ip;

foreach my $row (@tmp) {
if ($row=~/\%META\:FIELD\{name\=\"DeviceIP\"/) {
    if ($row=~/value\=\"([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})\"/) { $ip = $1; }
    }
}

if (!$ip) { next; }

my $auth  = get_record_sql($dbh,"SELECT * FROM User_auth WHERE deleted=0 and ip='".$ip."'");

if (!$auth) { next; }
if (!$auth->{WikiName}) { next; }
if ($auth->{WikiName} =~/^Gateway/) { next; }


my $device;
my $device_name;
my $device_port;

print "Found: $auth->{ip} $auth->{mac} ";

eval {
if ($auth->{WikiName} =~/^(Switch|Router)/) {
    $device = get_record_sql($dbh,"SELECT * FROM devices WHERE IP='".$ip."'");
    if (!$device) { die "Unknown device"; }
    if ($device->{comment}) { $auth->{comments} = $device->{comment}; }
    my $parent_connect = get_record_sql($dbh,"SELECT * FROM device_ports DP WHERE DP.uplink=1 AND DP.device_id=".$device->{id});
    if (!$parent_connect) { die "Unknown connection"; }
    my $parent_port =  get_record_sql($dbh,"SELECT * FROM device_ports DP WHERE id=".$parent_connect->{target_port_id});
    if (!$parent_port) { die "Unknown port connection"; }
    my $device_parent = get_record_sql($dbh,"SELECT * FROM devices WHERE id=".$parent_port->{device_id});
    if (!$parent_port) { die "Unknown parent device"; }
    my $auth_parent = get_record_sql($dbh,"SELECT * FROM User_auth WHERE deleted=0 AND ip='".$device_parent->{ip}."'");
    if (!$parent_port) { die "Unknown auth for device"; }
    $device_name = $auth_parent->{WikiName};
    $device_port = $parent_port->{port};
    } else {
    my $dSQL = "SELECT D.ip, D.building_id, D.user_id ,DP.port FROM devices AS D, device_ports AS DP, connections AS C WHERE D.deleted=0 and D.id = DP.device_id AND DP.id = C.port_id AND C.auth_id=".$auth->{id};
    $device = get_record_sql($dbh,$dSQL);
    if (!$device or !$device->{user_id}) { die "Unknown connection"; }
    $dSQL = "SELECT * FROM User_auth WHERE WikiName IS NOT NULL AND user_id=".$device->{user_id}." AND deleted=0 AND ip='".$device->{ip}."'";
    my $device_auth = get_record_sql($dbh,$dSQL);
    if (!$device_auth) { die "Unknown device auth"; }
    $device_name = $device_auth->{WikiName};
    $device_port = $device->{port};
    }
};
if ($@) { print "Error: $@\n"; next; }

#add non-existent field
my %empty_fields;
$empty_fields{descr}=1;
$empty_fields{parent}=1;
$empty_fields{parent_port}=1;
$empty_fields{mac}=1;

#apply patch
foreach my $row (@tmp) {
if ($row=~/\%META\:FIELD\{name\=\"Parent\"/) {
    $empty_fields{parent}=0;
    if ($device_name) { push(@wiki_dev,'%META:FIELD{name="Parent" title="Parent" value="'.$device_name.'"}%'); next; }
    }
if ($row=~/\%META\:FIELD\{name\=\"ParentPort\"/) {
    $empty_fields{parent_port}=0;
    if ($device_port) { push(@wiki_dev,'%META:FIELD{name="ParentPort" title="Parent Port" value="'.$device_port.'"}%'); next; }
    }
if ($row=~/\%META\:FIELD\{name\=\"Mac\"/) {
    $empty_fields{mac}=0;
    if ($auth->{mac}) { push(@wiki_dev,'%META:FIELD{name="Mac" title="Mac" value="'.$auth->{mac}.'"}%'); next; }
    }
push(@wiki_dev,$row);
}

foreach my $field (keys %empty_fields) {
next if (!$empty_fields{$field});
if ($field eq 'parent' and $device_name) { push(@wiki_dev,'%META:FIELD{name="Parent" title="Parent" value="'.$device_name.'"}%'); next; }
if ($field eq 'parent_port' and $device_port) { push(@wiki_dev,'%META:FIELD{name="ParentPort" title="Parent Port" value="'.$device_port.'"}%'); next; }
if ($field eq 'mac' and $auth->{mac}) { push(@wiki_dev,'%META:FIELD{name="Mac" title="Mac" value="'.$auth->{mac}.'"}%'); next; }
}

#print Dumper(\@wiki_dev);
#next;

if (!$device_name) { $device_name='None'; };
if (!$device_port) { $device_port='None'; };

print "at $device_name $device_port \n";

open (LG,">$content{$fname}") || die("Error open file $content{$fname}!!! die...");
foreach my $row (@wiki_dev) {
if (!$row) { $row=''; }
print LG $row."\n";
}
close (LG);
}

print "Done!\n";
exit;

sub wanted {
my $filename = $File::Find::name;
my $dev_name = basename($filename);
if ($dev_name =~/\.txt$/ and $dev_name=~/^(Device|Switch|Ups|Sensor|Gateway|Router|Server|Bras)/) {
    $dev_name=~s/\.txt$//;
    $content{$dev_name}=$filename;
    }
return;
}

exit;
