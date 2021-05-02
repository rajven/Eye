#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

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
if ($row=~/\%META\:FIELD\{name\=\"Description\"/) { next; }
if ($row=~/\%META\:FIELD\{name\=\"Parent\"/) { next; }
if ($row=~/\%META\:FIELD\{name\=\"ParentPort\"/) { next; }
if ($row=~/\%META\:FIELD\{name\=\"DeviceIP\"/) {
    if ($row=~/value\=\"([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})\"/) { $ip = $1; }
    }
if ($row=~/\%META\:FIELD\{name\=\"Mac\"/) { next; }
push(@wiki_dev,$row);
}

if (!$ip) { next; }
my $auth  = get_record_sql($dbh,"SELECT * FROM User_auth WHERE deleted=0 and ip='".$ip."'");
if (!$auth) { next; }

my $dSQL = "SELECT D.ip, D.building_id, D.user_id ,DP.port FROM devices AS D, device_ports AS DP, connections AS C WHERE D.deleted=0 and D.id = DP.device_id AND DP.id = C.port_id AND C.auth_id=".$auth->{id};
my $device = get_record_sql($dbh,$dSQL);
if (!$device or !$device->{user_id}) { next; }
$dSQL = "SELECT * FROM User_auth WHERE WikiName IS NOT NULL AND user_id=".$device->{user_id}." AND deleted=0 AND ip='".$device->{ip}."'";
my $device_auth = get_record_sql($dbh,$dSQL);
if (!$device_auth) { next; }
my $device_name = $device_auth->{WikiName};
my $device_port = $device->{port};

if ($auth->{comments}) { push(@wiki_dev,'%META:FIELD{name="Description" title="Description" value="'.$auth->{comments}.'"}%'); }
push(@wiki_dev,'%META:FIELD{name="Parent" title="Parent" value="'.$device_name.'"}%');
push(@wiki_dev,'%META:FIELD{name="ParentPort" title="Parent Port" value="'.$device_port.'"}%');
push(@wiki_dev,'%META:FIELD{name="Mac" title="Mac" value="'.$auth->{mac}.'"}%');

print "Found: $auth->{ip} $auth->{mac} $device_name $device_port \n";

open (LG,">$content{$fname}") || die("Error open file $content{$fname}!!! die...");
foreach my $row (@wiki_dev) {
next if (!$row);
print LG $row."\n";
}
close (LG);
}

print "Done!\n";
exit;

sub wanted {
my $filename = $File::Find::name;
my $dev_name = basename($filename);
if ($filename =~/\.txt$/ and $filename=~/Device/) {
    $dev_name=~s/\.txt$//;
    $content{$dev_name}=$filename;
    }
return;
}

exit;
