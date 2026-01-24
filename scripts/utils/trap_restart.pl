#!/usr/bin/perl 

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#
use utf8;
use warnings;
use Encode;
use open qw(:std :encoding(UTF-8));
no warnings 'utf8';

use FindBin '$Bin';
use lib "/opt/Eye/scripts";
use strict;
use Time::Local;
use FileHandle;
use Data::Dumper;
use Rstat::config;
use Rstat::main;
use Rstat::database;
use Rstat::net_utils;
use DBI;

$debug=0;
$log_enable = 0;

my $host = <STDIN>;     # Read the Hostname - First line of input from STDIN
chomp($host);
my $ip = <STDIN>;       # Read the IP - Second line of input
chomp($ip);

my %oids;

my @lines=();

while(<STDIN>) {
chomp($_);
push(@lines,$_);
}

########## write log #############

my ($host_ip) = $oids{'SNMP-COMMUNITY-MIB::snmpTrapAddress.0'} || ($ip =~ m#^UDP\:\s+\[([\d\.]+)\]\:\d+.*$#o);

my $TRAP_DIR="/var/log/trapd/$host_ip";
if (! -e $TRAP_DIR) { mkdir $TRAP_DIR, 0755; }
my $TRAP_FILE = $TRAP_DIR. "/trap.log";
my ($sec,$min,$hour,$mday,$mon,$year) = (localtime())[0,1,2,3,4,5];
$mon += 1; $year += 1900;
my $date = sprintf "%04d%02d%02d-%02d%02d%02d",$year,$mon,$mday,$hour,$min,$sec;
open(TRAPFILE, ">> $TRAP_FILE");

my $coldstart=0;

foreach my $trap_lines (@lines) {
print(TRAPFILE "$date [".$$."] LINE: $trap_lines\n");
my $key;
my $value;
if ($trap_lines=~/^(\S+)\s+(.*)/) {
    $key = $1;
    $value = $2;
    }
next if (!$key);
$value='' if (!$value);
$value=~s/\"//g;
$oids{$key}=$value;
}

foreach my $key (keys %oids) {
print(TRAPFILE "$date [".$$."] IP: $host_ip HOST: $host TRAP: $key VALUE: $oids{$key}\n");
if ($oids{$key}=~/coldstart/i) { $coldstart=1; }
}

exit if (!$coldstart);

############ find device ###############

my $IP_ATON=StrToIp($host_ip);

print(TRAPFILE "$date [".$$."] search host by ip [$host_ip] aton: $IP_ATON\n");

#get device
my $sSQL="SELECT dns_name FROM user_auth where deleted=0 and ip_int='".$IP_ATON."'";
my $ret=get_custom_record($dbh,$sSQL);
my $device_name=$ret->{dns_name};
print(TRAPFILE "$date [".$$."] name: $device_name\n");

exit if (!$device_name);

############ notify nagios #############

my $svc_description = 'Uptime';
my $stringoutput = 'WARN: Trap - Restart device! '.$svc_description;
my $retcode = 1;
my $run_cmd = "/etc/nagios4/scripts/eventhandlers/submit_check_result '".$device_name."' '".$svc_description."' $retcode '".$stringoutput."'";
print(TRAPFILE "$date [".$$."] run: $run_cmd\n");
system($run_cmd);

close(TRAPFILE);

exit;
