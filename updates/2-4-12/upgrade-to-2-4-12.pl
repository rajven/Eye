#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use utf8;
use FindBin '$Bin';
use lib "$Bin/";
use Data::Dumper;
use Rstat::config;
use Rstat::main;
use Rstat::mysql;
use Rstat::net_utils;
use strict;
use warnings;

if ($config_ref{encryption_key}=~/change_me/i)) { print "Set encryption key please!\n"; exit 100; }

print "Stage 1: Migrate default password\n";

my $current_password = get_option($dbh,29);
my $crypted_password = crypt_string($current_password);

do_sql($dbh,"UPDATE config set value='".$crypted_password."' WHERE id=29");

print "Stage 2: Add default access settings for all netdevices\n";

my $default_login = get_option($dbh,28);
my $default_port = get_option($dbh,30);

my @dev_list = get_records_sql($dbh,"SELECT * FROM devices WHERE device_type <= 2");
foreach my $row (@dev_list) {
#0 - 'Router'
#1 - 'Switch'
#2 - 'Gateway'
my $device;
$device->{login} = $default_login;
$device->{password} = $crypted_password;
#control
#0 - ssh
#1 - telnet
#2 - api
$device->{protocol} = 1;
if ($row->{device_type} eq '2') { 
    $device->{control_port} = $default_port;
    $device->{protocol} = 0;
    }
update_record($dbh,'devices',$device,"id=".$row->{id});
}

print "Done!\n";

exit;
