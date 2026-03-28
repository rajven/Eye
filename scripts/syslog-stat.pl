#!/usr/bin/perl

#
# Copyright (C) Roman Dmitriev, rnd@rajven.ru
#

use utf8;
use warnings;
use Encode;
use open qw(:std :encoding(UTF-8));
no warnings 'utf8';

use English;
use base;
use FindBin '$Bin';
use lib "/opt/Eye/scripts";
use strict;
use Time::Local;
use FileHandle;
use eyelib::config;
use eyelib::main;
use eyelib::database;
use eyelib::common;
use eyelib::logconfig;
use Data::Dumper;
use DBI;
use Time::Local;
use Date::Parse;
use IO::Socket::UNIX qw( SOCK_STREAM );
use Cwd;

my $socket_path='/run/syslog-ng.socket';

wrlog($W_INFO,"Starting...");

setpriority(0,0,19);

my %trash_patterns = (
    'Receive illegal destination ip packet 255.0.0.0 ,drop it' =>'1',
    'Receive illegal destination ip packet 0.0.0.0 ,drop it' =>'1',
    'SD Normal' =>'1',
    'SD Abnormal' =>'1',
    'source:0.0.0.0 destination:0.0.0.0 user:admin cmd:login' =>'1',
    'FAN\'S speed level - 1 changed to level - 0.' => '1',
    'FAN\'S speed level - 0 changed to level - 1.' => '1',
    "Environment-I-FANS-SPEED-CHNG: FAN'S speed level"=>'1'
    );

my %warning_patterns = (
  'SHUTDOWN-CTRL' => '1',
  'PORT_FLOW' => '1',
  'System ColdStart' => '1',
  'Deny user/' => '1',
  'LOOP-BACK-DETECTED' => 'loop',
  'Find loop' =>'loop',
  'SYS-5-LOOP' => 'loop',
  'drifting from' => 'loop',
  'Port-security has reached' => '1',
  'Unauthenticated IP-MAC' => '1',
  'FAN_FAILED' => '0',
  'has the same IP Address' => '1',
  'Loop detected on port e0' => 'loop',
  'loopguard' => 'zyxel_loop',
  'without management command' => '1',
  'System cold start' =>'1',
  'topology changes' => '1',
  'HMON-0-power'=>'1',
  'On battery power in response to an input power problem'=>'1',
  'No longer on battery power'=>'1',
  'Environment-W-PS-STAT-CHNG'=>'1',
  'System warm start' => '1'
  );

while (1) {
eval {
    my $db = init_db();
    open(SYSLOG,$socket_path) || die("Error open fifo socket $socket_path: $!");
    while (my $logline = <SYSLOG>) {
        next unless defined $logline;
        chomp($logline);
        my ($timestamp,$host_ip,$message) = split (/\|/, $logline);
        next if (!$message);
        $message =~ s/\r/ /g;
        $message =~ s/\\015//g;
        $message =~ s/\\012//g;
        next if (!$message);
        next if (!$host_ip);
        if (time()-$last_refresh_config>=60) { init_option($db); }
        log_debug("Raw message: $message");
        #is trash messages?
        my $trash = 0;
        foreach my $pattern (keys %trash_patterns) {
            next if (!$pattern);
            if ($message=~/$pattern/i) {
                    log_debug("Trash pattern: $pattern");
                    $trash = 1;
                    last;
                    }
            }
        next if ($trash);
        my $hostname=$host_ip;
        my $netdev = get_device_by_ip($db,$host_ip);
        my $id = 0;
        if ($netdev) {
            $hostname = $netdev->{device_name};
            $id = $netdev->{id};
            } else {
            log_debug("Host with $host_ip is not found in netdevices!");
            }

        my $q_msg=$db->quote($message);
        my $ssql="INSERT INTO remote_syslog(device_id,ip,message) values(?,?,?)";
        do_sql($db,$ssql,$id,$host_ip,$q_msg);

        foreach my $pattern (keys %warning_patterns) {
            next if (!$pattern);
            if ($message=~/$pattern/i) {
                wrlog($W_INFO,"Warning pattern $pattern found! Send email.",1);
                sendEmail("Syslog warning for $hostname [".$host_ip."]!",$host_ip." ".$message);
                last;
                }
            }
        }

    close(SYSLOG);
    };
if ($@) { wrlog($W_ERROR,"Exception found: $@"); sleep(60); }
}

wrlog($W_INFO,"Process stopped.");
exit 0;
