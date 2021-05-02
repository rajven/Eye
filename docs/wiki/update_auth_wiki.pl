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

my $ip;

foreach my $row (@tmp) {
if ($row=~/\%META\:FIELD\{name\=\"DeviceIP\"/) {
    if ($row=~/value\=\"([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})\"/) { $ip = $1; }
    }
}

if (!$ip) { next; }
my $auth  = get_record_sql($dbh,"SELECT * FROM User_auth WHERE deleted=0 and ip='".$ip."'");
if (!$auth) { next; }
print "Update id: $auth->{id} $ip => $fname\n";
my $auth_rec;
$auth->{WikiName}=$fname;
update_record($dbh,'User_auth',$auth,'id='.$auth->{id});
}

print "Done!\n";
exit;

sub wanted {
my $filename = $File::Find::name;
my $dev_name = basename($filename);
if ($filename =~/\.txt$/ and $dev_name=~/^(Device|Switch|Router|Gateway|Ups|Sensor)/) {
    $dev_name=~s/\.txt$//;
    $content{$dev_name}=$filename;
    }
return;
}

exit;
