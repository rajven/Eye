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
use POSIX;
use Net::Netmask;
use Text::Iconv;
use File::Tail;

my $pf = '/var/run/dhcp-log.pid';

my $log_file='/var/log/dhcp.log';

my $mute_time=300;

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

    my $converter = Text::Iconv->new("cp866", "utf8");

    while (1) {
        eval {

        my %leases;

        # Create new database handle. If we can't connect, die()
        my $hdb = DBI->connect("dbi:mysql:database=$DBNAME;host=$DBHOST","$DBUSER","$DBPASS");
        if ( !defined $hdb ) { die "Cannot connect to mySQL server: $DBI::errstr\n"; }

        #parse log
        my $dhcp_log=File::Tail->new(name=>$log_file,maxinterval=>5,interval=>1) || die "$log_file not found!";
        while (defined(my $logline=$dhcp_log->read)) {

            if (!$logline) { select(undef, undef, undef, 0.15); next; }

            chomp($logline);

            log_verbose("GET CLIENT REQUEST: $logline");
            my ($type,$mac,$ip,$hostname,$timestamp,$tags,$sup_hostname,$old_hostname) = split (/\;/, $logline);
            next if (!$type);
            next if ($type!~/(old|add|del)/i);

            if (exists $leases{$ip} and time()-$leases{$ip}{last_time} <= $mute_time) { next; }

            if (time()-$last_refresh_config>=60) { init_option($hdb); }

            my $client_hostname='UNDEFINED';
            if ($hostname and $hostname ne "undef") { $client_hostname=$hostname; } else {
                if ($sup_hostname) { $client_hostname=$sup_hostname; } else {
                    if ($old_hostname) { $client_hostname=$old_hostname; }
                    }
                }

            my $auth_network = $office_networks->match_string($ip);
            if (!$auth_network) {
                log_error("Unknown network in dhcp request! IP: $ip");
                next;
                }

            if (!$timestamp) { $timestamp=time(); }

            my $dhcp_event_time = GetNowTime($timestamp);

            my $ip_aton=StrToIp($ip);
            $mac=mac_splitted(isc_mac_simplify($mac));

            my $dhcp_record;
            $dhcp_record->{mac}=$mac;
            $dhcp_record->{ip}=$ip;
            $dhcp_record->{ip_aton}=$ip_aton;
            $dhcp_record->{hostname}=$client_hostname;
            $dhcp_record->{tags}=$tags;
            $dhcp_record->{network}=$auth_network;
            $dhcp_record->{type}=$type;
            $dhcp_record->{hostname_utf8}=$converter->convert($client_hostname);
            $dhcp_record->{timestamp} = $timestamp;
            $dhcp_record->{last_time} = time();
            $dhcp_record->{hotspot}=is_hotspot($dbh,$dhcp_record->{ip});
            $leases{$ip}=$dhcp_record;

            log_debug(uc($type).">>");
            log_debug("MAC:      ".$dhcp_record->{mac});
            log_debug("IP:       ".$dhcp_record->{ip});
            log_debug("TAGS:     ".$dhcp_record->{tags});
            log_debug("HOSTNAME: ".$dhcp_record->{hostname});
            log_debug("TYPE:     ".$dhcp_record->{type});
            log_debug("TIME:     ".$dhcp_event_time);
            log_debug("UTF8 HOSTNAME: ".$dhcp_record->{hostname_utf8});
            log_debug("END GET");

            my $auth_record = get_record_sql($hdb,'SELECT * FROM User_auth WHERE ip="'.$dhcp_record->{ip}.'" and mac="'.$mac.'" and deleted=0 ORDER BY last_found DESC');
	        if (!$auth_record and $type eq 'old' ) { $type='add'; }

            if ($type eq 'add') {
                my $res_id = resurrection_auth($hdb,$dhcp_record);
                next if (!$res_id);
                $auth_record = get_record_sql($hdb,'SELECT * FROM User_auth WHERE id='.$res_id);
                db_log_info($hdb,"Check for new auth. Found id: $res_id",$res_id);
                } else { $auth_record = get_record_sql($hdb,'SELECT * FROM User_auth WHERE ip="'.$dhcp_record->{ip}.'" and mac="'.$mac.'" and deleted=0 ORDER BY last_found DESC'); }

            my $auth_id = $auth_record->{id};
	        my $auth_ou_id = $auth_record->{ou_id};

            update_dns_record($hdb,$dhcp_record,$auth_record);

            if ($type=~/add/i and $dhcp_record->{hostname_utf8} and $dhcp_record->{hostname_utf8} !~/UNDEFINED/i) {
                my $auth_rec;
                $auth_rec->{dhcp_hostname} = $dhcp_record->{hostname_utf8};
                $auth_rec->{dhcp_time}=$dhcp_event_time;
                db_log_verbose($hdb,"Add lease by dhcp event for dynamic clients id:$auth_id ip: $dhcp_record->{ip}",$auth_id);
                update_record($hdb,'User_auth',$auth_rec,"id=$auth_id");
                }

            if ($dhcp_record->{hotspot} and $ignore_hotspot_dhcp_log) { next; }

            if ($ignore_update_dhcp_event and $type=~/old/i) { next; }

            if ($type=~/old/i) {
                    my $auth_rec;
                    $auth_rec->{dhcp_action}=$type;
                    $auth_rec->{dhcp_time}=$dhcp_event_time;
                    db_log_verbose($hdb,"Update lease by dhcp event for dynamic clients id:$auth_id ip: $dhcp_record->{ip}",$auth_id);
                    update_record($hdb,'User_auth',$auth_rec,"id=$auth_id");
                }

            if ($type=~/del/i and $auth_id) {
                if ($auth_record->{dhcp_time} =~ /([0-9]{4})-([0-9]{2})-([0-9]{2}) ([0-9]{2}):([0-9]{2}):([0-9]{2})/) {
                    my $d_time = mktime($6,$5,$4,$3,$2-1,$1-1900);
                    if (time()-$d_time>60 and ($auth_ou_id == $default_user_ou_id or $auth_ou_id==$default_hotspot_ou_id)) {
                        db_log_info($hdb,"Remove user ip record by dhcp release event for dynamic clients id:$auth_id ip: $dhcp_record->{ip}",$auth_id);
                        my $auth_rec;
                        $auth_rec->{deleted}="1";
                        $auth_rec->{dhcp_action}=$type;
                        $auth_rec->{dhcp_time}=$dhcp_event_time;
                        update_record($hdb,'User_auth',$auth_rec,"id=$auth_id");
                        my $u_count=get_count_records($hdb,'User_auth','deleted=0 and user_id='.$auth_record->{'user_id'});
		        if (!$u_count) {
				delete_record($hdb,"User_list","id=".$auth_record->{'user_id'});
	                        db_log_info($hdb,"Remove dynamic user id: $auth_record->{'user_id'} by dhcp request",$auth_id);
	                        }
                        }
                    }
                }

            my $dhcp_log;
            $dhcp_log->{auth_id} = $auth_id;
            $dhcp_log->{ip} = $dhcp_record->{ip};
            $dhcp_log->{ip_int} = $dhcp_record->{ip_aton};
            $dhcp_log->{mac} = $dhcp_record->{mac};
            $dhcp_log->{action} = $type;
            $dhcp_log->{timestamp} = $dhcp_event_time;
            insert_record($hdb,'dhcp_log',$dhcp_log);
            }
        };
        if ($@) { log_error("Exception found: $@"); sleep(60); }
        }
    } else {
        print "Already Running with pid $pid\n";
    }
}

sub usage {
    print "usage: dhcp-log.pl (start|stop|status|restart)\n";
    exit(0);
}

sub reload {
    print "reload process not implemented.\n";
}

sub restart {
    stop;
    run;
}
