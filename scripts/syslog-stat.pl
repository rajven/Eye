#!/usr/bin/perl

#
# Copyright (C) Roman Dmitriev, rnd@rajven.ru
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
use eyelib::config;
use eyelib::main;
use eyelib::database;
use eyelib::common;
use Data::Dumper;
use DBI;
use Time::Local;
use Date::Parse;
use Getopt::Long;
use IO::Socket::UNIX qw( SOCK_STREAM );
use Proc::Daemon;
use Cwd;


my $pf = '/run/eye/syslog-stat.pid';
my $socket_path='/run/syslog-ng.socket';

my $daemon = Proc::Daemon->new(
        pid_file => $pf,
        work_dir => $HOME_DIR
);

# are you running?  Returns 0 if not.
my $pid = $daemon->Status($pf);

my $daemonize = 1;

GetOptions(
    'daemon!' => \$daemonize,
    "help"    => \&usage,
    "reload"  => \&reload,
    "restart" => \&restart,
    "start"   => \&run,
    "status"  => \&status,
    "stop"    => \&stop
) or &usage;

exit(0);

sub stop {
        if ($pid) {
                print "Stopping pid $pid...";
                if ($daemon->Kill_Daemon($pf)) {
                        print "Successfully stopped.\n";
                } else {
                        print "Could not find $pid.  Was it running?\n";
                }
         } else {
                print "Not running, nothing to stop.\n";
         }
}

sub status {
        if ($pid) {
                print "Running with pid $pid.\n";
        } else {
                print "Not running.\n";
        }
}

sub run {
if (!$pid) {
    print "Starting...";
    if ($daemonize) {
        # when Init happens, everything under it runs in the child process.
        # this is important when dealing with file handles, due to the fact
        # Proc::Daemon shuts down all open file handles when Init happens.
        # Keep this in mind when laying out your program, particularly if
        # you use filehandles.
        $daemon->Init;
        }
    setpriority(0,0,19);

$SPID=~s/\.pl$/\.pid/;
write_to_file($SPID,$$);

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
        my $ssql="INSERT INTO remote_syslog(device_id,ip,message) values('".$id."','".$host_ip."',".$q_msg.")";
        do_sql($db,$ssql);

        foreach my $pattern (keys %warning_patterns) {
            next if (!$pattern);
            if ($message=~/$pattern/i) {
                log_info("Warning pattern $pattern found! Send email.",1);
                sendEmail("Syslog warning for $hostname [".$host_ip."]!",$host_ip." ".$message);
                last;
                }
            }
        }

    close(SYSLOG);
    };
if ($@) { log_error("Exception found: $@"); sleep(60); }
}
    } else {
        print "Already Running with pid $pid\n";
    }
}

sub usage {
    print "usage: syslog-monitord.pl (start|stop|status|restart)\n";
    exit(0);
}

sub reload {
    print "reload process not implemented.\n";
}

sub restart {
    stop;
    run;
}
