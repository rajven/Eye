#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use utf8;
use open ":encoding(utf8)";
use Encode;
no warnings 'utf8';
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
use POSIX;
use Net::Netmask;
use Text::Iconv;
use File::Tail;
use Fcntl qw(:flock);

open(SELF,"<",$0) or die "Cannot open $0 - $!";
flock(SELF, LOCK_EX|LOCK_NB) or exit 1;

setpriority(0,0,19);

my $mute_time=300;

my $log_file='/var/log/dhcp.log';

my $proc_name = $MY_NAME;
$proc_name =~ s/\.[^.]+$//;
my $pid_file = '/run/eye/'.$proc_name;

my $pf = $pid_file.'.pid';

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
        my $hdb = init_db();

        #parse log
        my $dhcp_log=File::Tail->new(name=>$log_file,maxinterval=>5,interval=>1,ignore_nonexistant=>1) || die "$log_file not found!";

        #truncate current log file
        #truncate $log_file, 0;

        while (my $logline=$dhcp_log->read) {

            next if (!$logline);

            chomp($logline);
            log_verbose("GET CLIENT REQUEST: $logline");

            $logline =~ s/[^\p{L}\p{N}\p{P}\p{Z}]//g;
            log_debug("Filter printable : $logline");

            my ($type,$mac,$ip,$hostname,$timestamp,$tags,$sup_hostname,$old_hostname,$circuit_id,$remote_id,$client_id,$decoded_circuit_id,$decoded_remote_id) = split (/\;/, $logline);
            next if (!$type);
            next if ($type!~/(old|add|del)/i);

            #mute doubles
            if (exists $leases{$ip} and $leases{$ip}{'type'} eq $type and time()-$leases{$ip}{'last_time'} <= $mute_time) { next; }

            #update config variables every 1 minute
            if (time()-$last_refresh_config>=60) { init_option($hdb); }

            my $dhcp_record = process_dhcp_request($hdb, $type, $mac, $ip, $hostname, $client_id, $decoded_circuit_id, $decoded_remote_id);
            next if (!$dhcp_record);

            #save record for mute
            $leases{$ip}=$dhcp_record;
            my $auth_id = $dhcp_record->{auth_id};

            my $switch;
            my $switch_port;

            my $t_remote_id;
            my $t_circuit_id = $circuit_id;

            #detect connection
            if ($type =~/(add|old)/) {

                #detect switch by decoded remote-id
                if ($decoded_remote_id) {
                    $t_remote_id = $decoded_remote_id;
                    #fill '0' to remote-id for full mac lenght
                    if (length($t_remote_id)<12) {
                        for (my $i = length($decoded_remote_id); $i < 12; $i++) { $t_remote_id = $t_remote_id."0"; }
                        }
                    $t_remote_id=mac_splitted(isc_mac_simplify($t_remote_id));
                    my $devSQL = "SELECT D.id, D.device_name, D.ip, A.mac FROM `devices` AS D,`User_auth` AS A WHERE D.user_id=A.User_id AND D.ip=A.ip AND A.deleted=0 AND A.mac='".$t_remote_id."'";
                    log_debug($devSQL);
                    $switch = get_record_sql($hdb,$devSQL);
                    if ($switch) {
                        $remote_id = $t_remote_id;
                        $circuit_id = $decoded_circuit_id;
                        $dhcp_record->{'circuit-id'} = $circuit_id;
                        $dhcp_record->{'remote-id'} = $remote_id;
                        }
                    }

                #detect switch by original remote-id
                if (!$switch and $remote_id) {
                    $t_remote_id = $remote_id;
                    #fill '0' to remote-id for full mac lenght
                    if (length($t_remote_id)<12) {
                        for (my $i = length($decoded_remote_id); $i < 12; $i++) { $t_remote_id = $t_remote_id."0"; }
                    }
                    $t_remote_id=mac_splitted(isc_mac_simplify($t_remote_id));
                    my $devSQL = "SELECT D.id, D.device_name, D.ip, A.mac FROM `devices` AS D,`User_auth` AS A WHERE D.user_id=A.User_id AND D.ip=A.ip AND A.deleted=0 AND A.mac='".$t_remote_id."'";
                    log_debug($devSQL);
                    $switch = get_record_sql($hdb,$devSQL);
                    if ($switch) {
                        $remote_id = $t_remote_id;
                        $dhcp_record->{'circuit-id'} = $circuit_id;
                        $dhcp_record->{'remote-id'} = $remote_id;
                        }
                }

                #maybe remote-id is string name device?
                if (!$switch and $remote_id) {
                    my @id_words = split(/ /,$remote_id);
                    if ($id_words[0]) {
                        my $devSQL = "SELECT D.id, D.device_name, D.ip, A.mac FROM `devices` AS D,`User_auth` AS A WHERE D.user_id=A.User_id AND D.ip=A.ip AND A.deleted=0 AND D.device_name like '".$id_words[0]."%'";
                        log_debug($devSQL);
                        $switch = get_record_sql($hdb,$devSQL);
                        }
                    }

                #maybe mikrotik?!
                if (!$switch and $circuit_id) {
                    my @id_words = split(/ /,$circuit_id);
                    if ($id_words[0]) {
                        my $devSQL = "SELECT D.id, D.device_name, D.ip, A.mac FROM `devices` AS D,`User_auth` AS A WHERE D.user_id=A.User_id AND D.ip=A.ip AND A.deleted=0 AND D.device_name like '".$id_words[0]."%'";
                        log_debug($devSQL);
                        $switch = get_record_sql($hdb,$devSQL);
                        #fucking mikrotik - swap variables
                        if ($switch) {
                            $circuit_id = $remote_id;
                            $remote_id = $t_circuit_id;
                            $dhcp_record->{'circuit-id'} = $circuit_id;
                            $dhcp_record->{'remote-id'} = $remote_id;
                            }
                        }
                    }

                if ($switch) {
                    $t_circuit_id=~s/[\+\-\s]+/ /g;
                    #detect port by name
                    my @device_ports = get_records_sql($dbh,"SELECT * FROM device_ports WHERE device_id=".$switch->{id});
                    my %device_ports_h;
                    foreach my $port_data (@device_ports) {
                        if (!$port_data->{snmp_index}) { $port_data->{snmp_index} = $port_data->{port}; }
                        $device_ports_h{$port_data->{port}} = $port_data;
                        if ($t_circuit_id=~/\s*$port_data->{'ifName'}$/i or $t_circuit_id=~/^$port_data->{'ifName'}\s+/i ) { $switch_port = $port_data; last; }
                        }

                    #detect hex - get last 2 byte
                    if (!$switch_port) {
                        my $hex_port = substr($decoded_circuit_id, -2);
                        if ($hex_port) {
                            my $t_port = hex($hex_port);
                            #try find port by index
                            if (exists $device_ports_h{$t_port}) { $switch_port =$device_ports_h{$t_port}; }
                            }
                        }

                    if ($switch_port) {
                        db_log_verbose($hdb,"Dhcp request type: ".$type." ip=".$ip." and mac=".$mac." from ".$switch->{'device_name'}." and port ".$switch_port->{'ifName'});
                        #check connection
                        my $connection=get_records_sql($dbh,"SELECT * FROM connections WHERE auth_id=".$auth_id);
                        my $new_connection;
                        if (!$connection) {
                            $new_connection->{port_id} = $switch_port->{id};
                            $new_connection->{device_id} = $switch->{id};
                            $new_connection->{auth_id} = $auth_id;
                            insert_record($hdb,'connections',$new_connection);
                            }
#                            else
#                            {
#                            $new_connection->{port_id} = $switch_port->{id};
#                            $new_connection->{device_id} = $switch->{id};
#                            update_record($hdb,'connections',$new_connection,"id=".$connection->{id});
#                            }
                        } else {
                        db_log_verbose($hdb,"Dhcp request type: ".$type." ip=".$ip." and mac=".$mac." from ".$switch->{'device_name'}." from unknown port");
                        }
                    }
                }
            log_debug("SWITCH:     ".$switch->{'device_name'}) if ($switch);
            log_debug("SWITCH PORT:".$switch_port->{'ifName'}) if ($switch_port);
            }
        };
        if ($@) { log_error("Exception found: $@"); sleep(60); }
        }
    } else {
        print "Already Running with pid $pid\n";
    }
}

sub usage {
    print "usage: $MY_NAME (start|stop|status|restart)\n";
    exit(0);
}

sub reload {
    print "reload process not implemented.\n";
}

sub restart {
    stop;
    run;
}
