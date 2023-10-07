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
        my $dhcp_log=File::Tail->new(name=>$log_file,maxinterval=>5,interval=>1,ignore_nonexistant=>1) || die "$log_file not found!";
        while (my $logline=$dhcp_log->read) {

            next if (!$logline);

            chomp($logline);

            log_verbose("GET CLIENT REQUEST: $logline");
            my ($type,$mac,$ip,$hostname,$timestamp,$tags,$sup_hostname,$old_hostname,$circut_id,$remote_id,$client_id,$decoded_circuit_id,$decoded_remote_id) = split (/\;/, $logline);
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

            my $ip_aton=StrToIp($ip);
            $mac=mac_splitted(isc_mac_simplify($mac));

            #detect switch
            if (!$decoded_remote_id) {
                if (length($decoded_remote_id)<12) {
                    for (my $i = length($decoded_remote_id); $i <= 12; $i++) {
                        $decoded_remote_id = $decoded_remote_id."0";
                    }
                }
                $decoded_remote_id=mac_splitted(isc_mac_simplify($decoded_remote_id));
                my $device = get_record_sql($hdb,"SELECT D.device_name,D.ip,A.mac FROM `devices` AS D,`User_auth` AS A WHERE D.user_id=A.User_id AND D.ip=A.ip AND A.deleted=0 AND A.mac='".$decoded_remote_id."'");
                if (!$device) { 
                    $remote_id = $decoded_remote_id;
                    $circut_id = $decoded_circuit_id;
                    db_log_verbose($hdb,"Dhcp request type: ".$type." ip=".$ip." and mac=".$mac." from ".$device->{'device_name'});
                    }
            }

            my $dhcp_event_time = GetNowTime($timestamp);

            my $dhcp_record;
            $dhcp_record->{'mac'}=$mac;
            $dhcp_record->{'ip'}=$ip;
            $dhcp_record->{'ip_aton'}=$ip_aton;
            $dhcp_record->{'hostname'}=$client_hostname;
            $dhcp_record->{'tags'}=$tags;
            $dhcp_record->{'network'}=$auth_network;
            $dhcp_record->{'type'}=$type;
            $dhcp_record->{'hostname_utf8'}=$converter->convert($client_hostname);
            $dhcp_record->{'timestamp'} = $timestamp;
            $dhcp_record->{'last_time'} = time();
            $dhcp_record->{'circuit-id'} = $circut_id;
            $dhcp_record->{'client-id'} = $client_id;
            $dhcp_record->{'remote-id'} = $remote_id;
            $dhcp_record->{'hotspot'}=is_hotspot($dbh,$dhcp_record->{ip});
            $leases{$ip}=$dhcp_record;

            log_debug(uc($type).">>");
            log_debug("MAC:       ".$dhcp_record->{'mac'});
            log_debug("IP:        ".$dhcp_record->{'ip'});
            log_debug("TAGS:      ".$dhcp_record->{'tags'});
            log_debug("CIRCUIT-ID:".$dhcp_record->{'circuit-id'});
            log_debug("REMOTE-ID: ".$dhcp_record->{'remote-id'});
            log_debug("HOSTNAME:  ".$dhcp_record->{'hostname'});
            log_debug("TYPE:      ".$dhcp_record->{'type'});
            log_debug("TIME:      ".$dhcp_event_time);
            log_debug("UTF8 NAME: ".$dhcp_record->{'hostname_utf8'});
            log_debug("END GET");

            my $auth_record = get_record_sql($hdb,'SELECT * FROM User_auth WHERE ip="'.$dhcp_record->{ip}.'" and mac="'.$mac.'" and deleted=0 ORDER BY last_found DESC');
	        if (!$auth_record and $type eq 'old' ) { $type='add'; }

            if ($type eq 'add') {
                    my $res_id = resurrection_auth($hdb,$dhcp_record);
                    if (!$res_id) {
                        db_log_error($hdb,"Error creating an ip address record for ip=".$dhcp_record->{ip}." and mac=".$mac."!");
                        next;
                        }
                    $auth_record = get_record_sql($hdb,'SELECT * FROM User_auth WHERE id='.$res_id);
                    db_log_info($hdb,"Check for new auth. Found id: $res_id",$res_id);
                } else {
                    $auth_record = get_record_sql($hdb,'SELECT * FROM User_auth WHERE ip="'.$dhcp_record->{ip}.'" and mac="'.$mac.'" and deleted=0 ORDER BY last_found DESC'); 
                }

            #create new record for refresh dhcp packet
            if (!$auth_record) {
                #don't create record by del request! 
                #because when the host address is changed, the new address will be overwritten by the old one being released
                if ($type=~/old/i) {
                    db_log_warning($hdb,"Record for dhcp request type: ".$type." ip=".$dhcp_record->{ip}." and mac=".$mac." does not exists!");
                    my $res_id = resurrection_auth($hdb,$dhcp_record);
                    if (!$res_id) {
                        db_log_error($hdb,"Error creating an ip address record for ip=".$dhcp_record->{ip}." and mac=".$mac."!");
                        next;
                        }
                    $auth_record = get_record_sql($hdb,'SELECT * FROM User_auth WHERE id='.$res_id);
                    db_log_info($hdb,"Check for new auth. Found id: $res_id",$res_id);
                    } else { next; }
                }
                
            my $auth_id = $auth_record->{id};
	        my $auth_ou_id = $auth_record->{ou_id};

            update_dns_record($hdb,$dhcp_record,$auth_record);

            if ($type=~/add/i and $dhcp_record->{hostname_utf8} and $dhcp_record->{hostname_utf8} !~/UNDEFINED/i) {
                my $auth_rec;
                $auth_rec->{dhcp_hostname} = $dhcp_record->{hostname_utf8};
                $auth_rec->{dhcp_time}=$dhcp_event_time;
                db_log_verbose($hdb,"Add lease by dhcp event for dynamic clients id: $auth_id ip: $dhcp_record->{ip}",$auth_id);
                update_record($hdb,'User_auth',$auth_rec,"id=$auth_id");
                }

            if ($dhcp_record->{hotspot} and $ignore_hotspot_dhcp_log) { next; }

            if ($ignore_update_dhcp_event and $type=~/old/i) { next; }

            if ($type=~/old/i) {
                    my $auth_rec;
                    $auth_rec->{dhcp_action}=$type;
                    $auth_rec->{dhcp_time}=$dhcp_event_time;
                    db_log_verbose($hdb,"Update lease by dhcp event for dynamic clients id: $auth_id ip: $dhcp_record->{ip}",$auth_id);
                    update_record($hdb,'User_auth',$auth_rec,"id=$auth_id");
                }

            if ($type=~/del/i and $auth_id) {
                if ($auth_record->{dhcp_time} =~ /([0-9]{4})-([0-9]{2})-([0-9]{2}) ([0-9]{2}):([0-9]{2}):([0-9]{2})/) {
                    my $d_time = mktime($6,$5,$4,$3,$2-1,$1-1900);
                    if (time()-$d_time>60 and ($auth_ou_id == $default_user_ou_id or $auth_ou_id==$default_hotspot_ou_id)) {
                        db_log_info($hdb,"Remove user ip record by dhcp release event for dynamic clients id: $auth_id ip: $dhcp_record->{ip}",$auth_id);
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
            if (!$auth_id) { $auth_id=0; }
            $dhcp_log->{'auth_id'} = $auth_id;
            $dhcp_log->{'ip'} = $dhcp_record->{'ip'};
            $dhcp_log->{'ip_int'} = $dhcp_record->{'ip_aton'};
            $dhcp_log->{'mac'} = $dhcp_record->{'mac'};
            $dhcp_log->{'action'} = $type;
            $dhcp_log->{'dhcp_hostname'} = $dhcp_record->{'hostname_utf8'};
            $dhcp_log->{'timestamp'} = $dhcp_event_time;
            $dhcp_log->{'circuit-id'} = $circut_id;
            $dhcp_log->{'client-id'} = $client_id;
            $dhcp_log->{'remote-id'} = $remote_id;

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
