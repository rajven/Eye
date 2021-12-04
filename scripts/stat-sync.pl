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
use Getopt::Long;
use Proc::Daemon;
use Cwd;
use Net::Netmask;

my $pf = '/var/run/stat-sync.pid';

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

    while (1) {
        eval {
        # Create new database handle. If we can't connect, die()
        my $hdb = DBI->connect("dbi:mysql:database=$DBNAME;host=$DBHOST","$DBUSER","$DBPASS");
        if (time()-$last_refresh_config>=60) { init_option($hdb); }
        if ( !defined $hdb ) { die "Cannot connect to mySQL server: $DBI::errstr\n"; }
        $urgent_sync=get_option($hdb,50);
        if ($urgent_sync) {
    	    do_sql($hdb,"UPDATE User_auth SET changed=0 WHERE ou_id=".$default_user_ou_id." OR ou_id=".$default_hotspot_ou_id);
            my $changed = get_record_sql($hdb,"SELECT COUNT(*) as c_count from User_auth WHERE changed=1");
	    if ($changed->{"c_count"}>0) {
                log_info("Found changed records: ".$changed->{'c_count'});
    	        my %result=do_exec_ref($HOME_DIR."/sync_mikrotik.pl");
    	        if ($result{status} ne 0) { log_error("Error sync status at gateways"); }
    	    	}
    	    }
    	sleep(60);
        };
        if ($@) { log_error("Exception found: $@"); sleep(300); }
        }
    } else {
        print "Already Running with pid $pid\n";
    }
}

sub usage {
    print "usage: stat-sync.pl (start|stop|restart)\n";
    exit(0);
}

sub reload {
    print "reload process not implemented.\n";
}

sub restart {
    stop;
    run;
}
