#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use utf8;
use open ":encoding(utf8)";
use English;
use base;
use FindBin '$Bin';
use lib "/opt/Eye/scripts";
use Data::Dumper;
use eyelib::config;
use eyelib::main;
use eyelib::database;
use eyelib::net_utils;
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
        my $hdb = init_db();
        if (time()-$last_refresh_config>=60) { init_option($hdb); }
        $urgent_sync=get_option($hdb,50);
        if ($urgent_sync) {
	    #clean changed for dynamic clients or hotspot
	    do_sql($hdb,"UPDATE User_auth SET changed=0 WHERE ou_id=".$default_user_ou_id." OR ou_id=".$default_hotspot_ou_id);
	    do_sql($hdb,"UPDATE User_auth SET dhcp_changed=0 WHERE ou_id=".$default_user_ou_id." OR ou_id=".$default_hotspot_ou_id);
	    #clean unmanagment ip changed
	    my @all_changed = get_records_sql($hdb,"SELECT id, ip FROM User_auth WHERE changed = 1 OR dhcp_changed = 1");
	    foreach my $row(@all_changed) {
		    next if ($office_networks->match_string($row->{ip}));
		    do_sql($hdb,"UPDATE User_auth SET changed = 0, dhcp_changed = 0  WHERE id=".$row->{id});
		}

            #dhcp changed records
            my $changed = get_record_sql($hdb,"SELECT COUNT(*) as c_count from User_auth WHERE dhcp_changed=1");
            if ($changed->{"c_count"}>0) {
        	    do_sql($hdb,"UPDATE User_auth SET dhcp_changed=0");
                    log_info("Found changed dhcp variables in records: ".$changed->{'c_count'});
                    my $dhcp_exec=get_option($hdb,38);
	            my %result=do_exec_ref($dhcp_exec);
	            if ($result{status} ne 0) { log_error("Error sync dhcp config"); }
	        }

            #dns changed records
            my @dns_changed = get_records_sql($hdb,"SELECT auth_id FROM `dns_queue` GROUP BY auth_id");
            if (@dns_changed and scalar @dns_changed) {
                    foreach my $auth (@dns_changed) {
                        update_dns_record($hdb,$auth->{auth_id});
                        log_info("Clear changed dns for auth id: ".$auth->{auth_id});
                        do_sql($hdb,"DELETE FROM `dns_queue` WHERE auth_id=".$auth->{auth_id});
                    }
	        }

            #acl & dhcp changed records 
            $changed = get_record_sql($hdb,"SELECT COUNT(*) as c_count from User_auth WHERE changed=1");
	    if ($changed->{"c_count"}>0) {
                    log_info("Found changed records: ".$changed->{'c_count'});
                    my $acl_exec=get_option($hdb,37);
                    my %result=do_exec_ref($acl_exec);
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
