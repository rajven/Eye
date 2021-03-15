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
use Getopt::Long;
use Proc::Daemon;
use Cwd;
use IO::Socket::UNIX qw( SOCK_STREAM );
use Net::Netmask;
use File::Spec::Functions;
use File::Copy qw(move);
use Text::Iconv;

my $iplist_file = "iplist.txt";
open(IPLIST,$iplist_file) || die("Error open $iplist_file: $!");
while (my $logline = <IPLIST>) {
next unless defined $logline;
chomp($logline);
my ($ip,$mac,$comment) = split (/\;/, $logline);
my $auth_network = $office_networks->match_string($ip);
if (!$auth_network) {
    log_error("Unknown network in dhcp request! IP: $ip");
    next;
    }
log_info("Check for new auth...");
my $auth_id=resurrection_auth($dbh,$ip,$mac,'add');
if ($comment) {
    my $auth;
    $auth->{comments}=$comment;
    update_record($dbh,'User_auth',$auth,'id='.$auth_id);
    }
}
close IPLIST;
exit;
