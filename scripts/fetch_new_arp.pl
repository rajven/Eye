#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use utf8;
use English;
use base;
use FindBin '$Bin';
use lib "$Bin/";
use strict;
use DBI;
use Time::Local;
use Net::Patricia;
use Data::Dumper;
use Date::Parse;
use Socket;
use eyelib::config;
use eyelib::main;
use eyelib::net_utils;
use eyelib::snmp;
use eyelib::mysql;
use NetAddr::IP;
use Fcntl qw(:flock);
use Parallel::ForkManager;

open(SELF,"<",$0) or die "Cannot open $0 - $!";
flock(SELF, LOCK_EX|LOCK_NB) or exit 1;

setpriority(0,0,19);

if ($config_ref{config_mode}) { log_info("System in configuration mode! Skip discovery."); exit; }

my %mac_history;

my ($sec,$min,$hour,$day,$month,$year,$zone) = localtime(time());
$month += 1;
$year += 1900;

my $fork_count = $cpu_count*5;

#disable fork for debug
#if ($debug) { $fork_count = 0; }

my $now_str=sprintf "%04d-%02d-%02d %02d:%02d:%02d",$year,$month,$day,$hour,$min,$sec;
my $now_day=sprintf "%04d-%02d-%02d",$year,$month,$day;

db_log_verbose($dbh,'Arp discovery started.');

if ($ARGV[0]) {
    db_log_verbose($dbh,'Active check started!');
    my $subnets=get_subnets_ref($dbh);
    my @fping_cmd=();
    foreach my $net (keys %$subnets) {
            next if (!$net);
            next if (!$subnets->{$net}{discovery});
            my $run_cmd="$fping -g $subnets->{$net}{subnet} -B1.0 -c 1 >/dev/null 2>&1";
            db_log_debug($dbh,"Checked network $subnets->{$net}{subnet}") if ($debug);
            push(@fping_cmd,$run_cmd);
            }
    $parallel_process_count = $cpu_count*2;
    run_in_parallel(@fping_cmd);
    }

my @router_ref = get_records_sql($dbh,"SELECT * FROM devices WHERE deleted=0 AND (device_type=2 or device_type=0) AND discovery=1 AND snmp_version>0 ORDER by ip" );

$dbh->disconnect;

my @arp_array=();

my $pm_arp = Parallel::ForkManager->new($fork_count);

# data structure retrieval and handling
$pm_arp -> run_on_finish (
sub {
    my ($pid, $exit_code, $ident, $exit_signal, $core_dump, $data_structure_reference) = @_;
    if (defined($data_structure_reference)) {
        # children are not forced to send anything
        my $result = ${$data_structure_reference};
        push(@arp_array,$result);
        }
    }
);

DATA_LOOP:
foreach my $router (@router_ref) {
my $router_ip=$router->{ip};
my $snmp_version=$router->{snmp_version};
my $community=$router->{community};
if (!HostIsLive($router_ip)) { log_info("Host id: $router->{id} name: $router->{device_name} ip: $router_ip is down! Skip."); next; }
$pm_arp->start() and next DATA_LOOP;
my $arp_table;
my $tmp_dbh=init_db();
if (apply_device_lock($tmp_dbh,$router->{id})) {
    $arp_table=get_arp_table($router_ip,$community,$snmp_version);
    unset_lock_discovery($tmp_dbh,$router->{id});
    }
$tmp_dbh->disconnect;
$pm_arp->finish(0, \$arp_table);
}
$pm_arp->wait_all_children;

########################### end fork's #########################

$dbh=init_db();

#get userid list
my @authlist_ref = get_records_sql($dbh,"SELECT * FROM User_auth WHERE deleted=0 ORDER by ip_int" );

my $users = new Net::Patricia;
my %ip_list;
my %oper_arp_list;

foreach my $row (@authlist_ref) {
$users->add_string($row->{ip},$row->{id});
$ip_list{$row->{id}}->{id}=$row->{id};
$ip_list{$row->{id}}->{ip}=$row->{ip};
$ip_list{$row->{id}}->{mac}=mac_splitted($row->{mac}) || '';
}

foreach my $arp_table (@arp_array) {
    foreach my $ip (keys %$arp_table) {
        next if (!$arp_table->{$ip});
        my $mac=trim($arp_table->{$ip});
        $mac=mac_splitted($mac);
        next if (!$mac);
        next if ($mac=~/ff:ff:ff:ff:ff:ff/i);
        next if ($mac!~/(\S{2}):(\S{2}):(\S{2}):(\S{2}):(\S{2}):(\S{2})/);
        my $simple_mac=mac_simplify($mac);
        $ip=trim($ip);
        my $ip_aton=StrToIp($ip);
        $mac_history{$simple_mac}{changed}=0;
        $mac_history{$simple_mac}{ip}=$ip;
        $mac_history{$simple_mac}{auth_id}=0;
        next if (!$office_networks->match_string($ip));
        db_log_debug($dbh,"Analyze ip: $ip mac: $mac") if ($debug);
        my $auth_id = $users->match_string($ip);
        my $arp_record;
        $arp_record->{ip} = $ip;
        $arp_record->{mac} = $mac;
        $arp_record->{type} = 'arp';
        $arp_record->{ip_aton} = $ip_aton;
        $arp_record->{hotspot} = is_hotspot($dbh,$ip);
        my $cur_auth_id=resurrection_auth($dbh,$arp_record);
        if (!$cur_auth_id) { 
	    db_log_warning($dbh,"Unknown record ".Dumper($arp_record));
	    } else {
            $mac_history{$simple_mac}{auth_id}=$cur_auth_id;
	    $arp_record->{auth_id}=$cur_auth_id;
	    $arp_record->{updated}=0;
	    $oper_arp_list{$cur_auth_id}=$arp_record;
	    if ($auth_id ne $cur_auth_id) { $mac_history{$simple_mac}{changed}=1; }
	    }
    }
}

db_log_verbose($dbh,'Mac discovery started.');

my %connections=();
my @connections_list=get_records_sql($dbh,"SELECT * FROM connections ORDER BY auth_id");
foreach my $connection (@connections_list) {
    next if (!$connection);
    $connections{$connection->{auth_id}}{port}=$connection->{port_id};
    $connections{$connection->{auth_id}}{id}=$connection->{id};
    }

my $auth_filter=" AND last_found >='".$now_day."' ";
my $auth_sql="SELECT id,mac FROM User_auth WHERE mac IS NOT NULL AND deleted=0 $auth_filter ORDER BY id ASC";

my @auth_list=get_records_sql($dbh,$auth_sql);

my %auth_table;
foreach my $auth (@auth_list) {
    next if (!$auth);
    my $auth_mac=mac_simplify($auth->{mac});
    $auth_table{oper_table}{$auth_mac}=$auth->{id};
    }

$auth_sql="SELECT id,mac FROM User_auth WHERE mac IS NOT NULL AND deleted=0 ORDER BY last_found DESC";
my @auth_full_list=get_records_sql($dbh,$auth_sql);
foreach my $auth (@auth_full_list) {
    next if (!$auth);
    my $auth_mac=mac_simplify($auth->{mac});
    next if (exists $auth_table{full_table}{$auth_mac});
    $auth_table{full_table}{$auth_mac}=$auth->{id};
    }

my @unknown_list=get_records_sql($dbh,"SELECT id,mac,port_id,device_id FROM Unknown_mac WHERE mac !='' ORDER BY mac");
my %unknown_table;
foreach my $unknown (@unknown_list) {
    next if (!$unknown);
    my $unknown_mac=mac_simplify($unknown->{mac});
    $unknown_table{$unknown_mac}{unknown_id}=$unknown->{id};
    $unknown_table{$unknown_mac}{port_id}=$unknown->{port_id};
    $unknown_table{$unknown_mac}{device_id}=$unknown->{device_id};
    }

my @device_list = get_records_sql($dbh,"SELECT * FROM devices WHERE deleted=0 AND discovery=1 AND device_type<=2 AND snmp_version>0" );

my @fdb_array=();

my $pm_fdb = Parallel::ForkManager->new($fork_count);

# data structure retrieval and handling
$pm_fdb -> run_on_finish (
sub {
    my ($pid, $exit_code, $ident, $exit_signal, $core_dump, $data_structure_reference) = @_;
    if (defined($data_structure_reference)) {  # children are not forced to send anything
        my $result = ${$data_structure_reference};  # child passed a string reference
        push(@fdb_array,$result);
        }
    }
);

my %dev_ifindex=();

foreach my $device (@device_list) {
    my $dev_id = $device->{id};
    my @device_ports = get_records_sql($dbh,"SELECT * FROM device_ports WHERE device_id=$dev_id");
    foreach my $port_data (@device_ports) {
        if (!$port_data->{snmp_index}) { $port_data->{snmp_index} = $port_data->{port}; }
        $dev_ifindex{$dev_id}->{$port_data->{port}}=$port_data->{snmp_index};
    }
}

$dbh->disconnect;

FDB_LOOP:
foreach my $device (@device_list) {
my $int_list = get_snmp_ifindex($device->{ip},$device->{community},$device->{snmp_version});
if (!$int_list) { log_info("Host id: $device->{id} name: $device->{device_name} ip: $device->{ip} is down! Skip."); next; }
$pm_fdb->start() and next FDB_LOOP;
my $result;
my $tmp_dbh = init_db();
if (apply_device_lock($tmp_dbh,$device->{id})) {
    my $fdb=get_fdb_table($device->{ip},$device->{community},$device->{snmp_version},$dev_ifindex{$device->{id}});
    unset_lock_discovery($tmp_dbh,$device->{id});
    $result->{id}=$device->{id};
    $result->{fdb} = $fdb;
    }
$tmp_dbh->disconnect;
$pm_fdb->finish(0, \$result);
}
$pm_fdb->wait_all_children;

my %fdb_ref;
foreach my $fdb_table (@fdb_array){
next if (!$fdb_table);
$fdb_ref{$fdb_table->{id}}{fdb}=$fdb_table->{fdb};
}

################################ end fork's ##############################

$dbh=init_db();

foreach my $device (@device_list) {
my %port_snmp_index=();
my %port_index=();
my %mac_port_count=();
my %mac_address_table=();
my %port_links=();

my $dev_id = $device->{id};
my $dev_name = $device->{device_name};
my $fdb=$fdb_ref{$dev_id}{fdb};

next if (!$fdb);

my @device_ports = get_records_sql($dbh,"SELECT * FROM device_ports WHERE device_id=$dev_id");

foreach my $port_data (@device_ports) {
    my $fdb_port_index=$port_data->{port};
    my $port_id = $port_data->{id};
    if (!$port_data->{snmp_index}) { $port_data->{snmp_index} = $port_data->{port}; }
    $fdb_port_index=$port_data->{snmp_index};
    next if ($port_data->{skip});
    #snmp-индекс порта = номеру порта
    $port_snmp_index{$port_data->{snmp_index}}=$port_data->{port};
    # номер порта = id записи порта в таблице
    $port_index{$port_data->{port}}=$port_data->{id};
    # номер порта = id записи порта аплинка/даунлинка свича
    $port_links{$port_data->{port}}=$port_data->{target_port_id};
    $mac_port_count{$port_data->{port}}=0;
    }

my $sw_mac;
if ($device->{vendor_id} eq '9') {
    #get device mac
    my $sw_auth = get_record_sql($dbh,"SELECT mac FROM User_auth WHERE deleted=0 and ip='".$device->{ip}."'");
    $sw_mac = mac_simplify($sw_auth->{mac});
    $sw_mac =~s/.{2}$//s;
    }

foreach my $mac (keys %$fdb) {
    #port from fdb table
    my $port = $fdb->{$mac};
    next if (!$port);
    #real port number
    if (exists $port_snmp_index{$port}) {
        #get real port number by snmp our snmp index
        $port=$port_snmp_index{$port};
        }
    if (!exists $port_index{$port}) { next; }
    #mikrotik patch - skip mikrotik device mac
    if ($sw_mac and $mac=~/^$sw_mac/i) { next; }
    $mac_port_count{$port}++;
    #мак = номер порта
    $mac_address_table{$mac}=$port;
    }

# обновляем число маков на порту
foreach my $port (keys %mac_port_count) {
if (!$port) { next; }
if (!exists $port_index{$port}) { next; }
#skip uplink|downlink
if ($port_links{$port}>0) { next; }
my $dev_ports;
$dev_ports->{last_mac_count}=$mac_port_count{$port};
update_record($dbh,'device_ports',$dev_ports,"device_id=$dev_id and port=$port");
}

foreach my $mac (keys %mac_address_table) {
    my $port = $mac_address_table{$mac};
    if (!$port) { next; }
    if (!exists $port_index{$port}) { next; }
    #skip uplink|downlink
    if ($port_links{$port}>0) { next; }

    my $simple_mac=mac_simplify($mac);
    my $mac_splitted=mac_splitted($mac);

    $mac_history{$simple_mac}{port_id}=$port_index{$port};
    $mac_history{$simple_mac}{dev_id}=$dev_id;

    if (!$mac_history{$simple_mac}{changed}) { $mac_history{$simple_mac}{changed}=0; }

    my $port_id=$port_index{$port};

    if (exists $auth_table{full_table}{$simple_mac} or exists $auth_table{oper_table}{$simple_mac}) {
                my $auth_id;
                if (exists $auth_table{oper_table}{$simple_mac}) {
                    $auth_id=$auth_table{oper_table}{$simple_mac};
                    } else {
                    $auth_id=$auth_table{full_table}{$simple_mac};
                    db_log_debug($dbh,"Mac not found in oper ARP-table. Use old values auth_id: $auth_id [$simple_mac] at device $dev_name [$port]",$auth_id);
                    }

                if (exists $connections{$auth_id}) {
                    if ($port_id == $connections{$auth_id}{port}) {
                        if (exists $auth_table{oper_table}{$simple_mac}) {
                            my $auth_rec;
                            $auth_rec->{last_found}=$now_str;
			    $oper_arp_list{$auth_id}{updated}=1;
                            update_record($dbh,'User_auth',$auth_rec,"id=".$auth_id);
                            }
                        next;
                        }

                    $connections{$auth_id}{port}=$port_id;
                    $mac_history{$simple_mac}{changed}=1;
                    $mac_history{$simple_mac}{auth_id}=$auth_id;
                    db_log_info($dbh,"Found auth_id: $auth_id ip: $mac_history{$simple_mac}{ip} [$mac_splitted] at device $dev_name [$port]. Update connection",$auth_id);
                    my $auth_rec;
                    $auth_rec->{last_found}=$now_str;
		    $oper_arp_list{$auth_id}{updated}=1;
                    update_record($dbh,'User_auth',$auth_rec,"id=".$auth_id);
                    my $conn_rec;
                    $conn_rec->{port_id}=$port_id;
                    $conn_rec->{device_id}=$dev_id;
                    update_record($dbh,'connections',$conn_rec,"auth_id=$auth_id");
                    } else {
                    $mac_history{$simple_mac}{changed}=1;
                    $mac_history{$simple_mac}{auth_id}=$auth_id;
                    $connections{$auth_id}{port}=$port_id;
                    db_log_info($dbh,"Found auth_id: $auth_id ip: $mac_history{$simple_mac}{ip} [$mac_splitted] at device $dev_name [$port]. Create connection.",$auth_id);
                    my $auth_rec;
                    $auth_rec->{last_found}=$now_str;
		    $oper_arp_list{$auth_id}{updated}=1;
                    update_record($dbh,'User_auth',$auth_rec,"id=".$auth_id);
                    my $conn_rec;
                    $conn_rec->{port_id}=$port_id;
                    $conn_rec->{device_id}=$dev_id;
                    $conn_rec->{auth_id}=$auth_id;
                    insert_record($dbh,'connections',$conn_rec);
                    }
                } else {
                if (exists $unknown_table{$simple_mac}{unknown_id}) {
                        next if ($unknown_table{$simple_mac}{port_id} == $port_id and $unknown_table{$simple_mac}{device_id} == $dev_id);
                        $mac_history{$simple_mac}{changed}=1;
                        $mac_history{$simple_mac}{auth_id}=0;
                        db_log_debug($dbh,"Unknown mac $mac_splitted moved to $dev_name [$port]") if ($debug);
                        my $unknown_rec;
                        $unknown_rec->{port_id}=$port_id;
                        $unknown_rec->{device_id}=$dev_id;
                        update_record($dbh,'Unknown_mac',$unknown_rec,"id=$unknown_table{$simple_mac}{unknown_id}");
                        } else {
                        $mac_history{$simple_mac}{changed}=1;
                        $mac_history{$simple_mac}{auth_id}=0;
                        db_log_debug($dbh,"Unknown mac $mac_splitted found at $dev_name [$port]") if ($debug);
                        my $unknown_rec;
                        $unknown_rec->{port_id}=$port_id;
                        $unknown_rec->{device_id}=$dev_id;
                        $unknown_rec->{mac}=$simple_mac;
                        insert_record($dbh,'Unknown_mac',$unknown_rec);
                        }
                }
    }
}

foreach my $auth_id (keys %oper_arp_list) {
    next if ($oper_arp_list{$auth_id}->{updated});
    my $auth_rec;
    $auth_rec->{last_found}=$now_str;
    update_record($dbh,'User_auth',$auth_rec,"id=".$auth_id);
    db_log_debug($dbh,"Update by arp at unknown connect place for: ".Dumper($oper_arp_list{$auth_id})) if ($debug);
}

foreach my $mac (keys %mac_history) {
next if (!$mac);
next if (!$mac_history{$mac}->{changed});
my $h_dev_id='';
my $h_port_id='';
my $h_ip='';
$h_dev_id=$mac_history{$mac}->{dev_id} if ($mac_history{$mac}->{dev_id});
$h_port_id=$mac_history{$mac}->{port_id} if ($mac_history{$mac}->{port_id});
$h_ip=$mac_history{$mac}->{ip} if ($mac_history{$mac}->{ip});

my $h_auth_id=$mac_history{$mac}->{auth_id} if ($mac_history{$mac}->{auth_id});
if (!$h_auth_id) { $h_auth_id=0; }
next if (!$h_dev_id);
my $history_rec;
$history_rec->{device_id}=$h_dev_id;
$history_rec->{port_id}=$h_port_id;
$history_rec->{mac}=$mac;
$history_rec->{ip}=$h_ip;
$history_rec->{auth_id}=$h_auth_id;
insert_record($dbh,'mac_history',$history_rec);
}

$dbh->disconnect;

exit 0;