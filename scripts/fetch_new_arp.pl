#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use FindBin '$Bin';
use lib "$Bin/";
use strict;
use DBI;
use Time::Local;
use Net::Patricia;
use Data::Dumper;
use Date::Parse;
use Socket;
use Rstat::config;
use Rstat::main;
use Rstat::net_utils;
use Rstat::snmp;
use Rstat::mysql;
use NetAddr::IP;

setpriority(0,0,19);

my %mac_history;

my ($sec,$min,$hour,$day,$month,$year,$zone) = localtime(time());
$month += 1;
$year += 1900;

my $now_str=sprintf "%04d-%02d-%02d %02d:%02d:%02d",$year,$month,$day,$hour,$min,$sec;
my $now_day=sprintf "%04d-%02d-%02d",$year,$month,$day;

if (!$arp_discovery) {
    db_log_verbose($dbh,'Arp discovery disabled by config');
    } else {

db_log_verbose($dbh,'Arp discovery started.');

if ($ARGV[0]) {
    db_log_verbose($dbh,'Active check started!');
    my $subnets=get_subnets_ref($dbh);
    foreach my $net (keys %$subnets) {
            next if (!$net);
            next if (!$subnets->{$net}{discovery});
            my $run_cmd="$fping -g $subnets->{$net}{subnet} -B1.0 -c 1 >/dev/null 2>&1";
            db_log_debug($dbh,"Checked network $subnets->{$net}{subnet}") if ($debug);
            do_exec($run_cmd);
            }
    }

my $router_list = $dbh->prepare( "SELECT ip,snmp_version,community FROM devices where deleted=0 and is_router=1 and discovery=1 and snmp_version>0 ORDER by ip" );
if ( !defined $router_list ) { die "Cannot prepare statement: $DBI::errstr\n"; }
$router_list->execute;
my $router_ref = $router_list->fetchall_arrayref();
$router_list->finish();

#get userid list
my $user_auth_list = $dbh->prepare( "SELECT id,ip,mac,comments FROM User_auth where deleted=0 ORDER by ip" );
if ( !defined $user_auth_list ) { die "Cannot prepare statement: $DBI::errstr\n"; }
$user_auth_list->execute;
my $authlist_ref = $user_auth_list->fetchall_arrayref();
$user_auth_list->finish();

my $users = new Net::Patricia;

my %ip_list;

foreach my $row (@$authlist_ref) {
$users->add_string($row->[1],$row->[0]);
$ip_list{$row->[0]}->{id}=$row->[0];
$ip_list{$row->[0]}->{ip}=$row->[1];
$ip_list{$row->[0]}->{mac}=mac_splitted($row->[2]) || '';
}

foreach my $router (@$router_ref) {
my $router_ip=$router->[0];
my $snmp_version=$router->[1];
my $community=$router->[2];
#print "Analyze $router_ip $snmp_version $community\n";
my $arp_table=get_arp_table($router_ip,$community,$snmp_version);
next if (!$arp_table);
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
    resurrection_auth($dbh,$ip,$mac,'arp');
    my $cur_auth_id=get_id_record($dbh,'User_auth',"ip='$ip' and mac='$mac' and deleted=0 order by last_found DESC");
    $mac_history{$simple_mac}{auth_id}=$cur_auth_id;
    if ($auth_id ne $cur_auth_id) {
	$mac_history{$simple_mac}{changed}=1;
	}
    }
}
db_log_verbose($dbh,'Arp discovery stopped.');
}

#MAC Discavery
if (!$mac_discovery) {
    db_log_verbose($dbh,'Mac discovery disabled by config');
    } else {

sleep(1);
db_log_verbose($dbh,'Mac discovery started.');

my %connections=();
my $connections_list=do_sql($dbh,"Select id,auth_id,port_id from connections order by auth_id");
foreach my $connection (@$connections_list) {
    next if (!$connection);
    my ($conn_id,$conn_auth_id,$conn_port_id)=@$connection;
    $connections{$conn_auth_id}{port}=$conn_port_id;
    $connections{$conn_auth_id}{id}=$conn_id;
    }

my $auth_filter='';
if ($arp_discovery) { $auth_filter=" and last_found >='".$now_day."' "; }
my $auth_sql="Select id,mac from User_auth where mac is not null and deleted=0 $auth_filter order by id asc";

my $auth_list=do_sql($dbh,$auth_sql);

my %auth_table;
foreach my $auth (@$auth_list) {
    next if (!$auth);
    my ($auth_id,$auth_mac)=@$auth;
    $auth_mac=mac_simplify($auth_mac);
    $auth_table{oper_table}{$auth_mac}=$auth_id;
    }


$auth_sql="Select id,mac from User_auth where mac is not null and deleted=0 order by id asc";
my $auth_full_list=do_sql($dbh,$auth_sql);

foreach my $auth (@$auth_full_list) {
    next if (!$auth);
    my ($auth_id,$auth_mac)=@$auth;
    $auth_mac=mac_simplify($auth_mac);
    $auth_table{full_table}{$auth_mac}=$auth_id;
    }


my $unknown_list=do_sql($dbh,"Select id,mac,port_id,device_id from Unknown_mac where mac !='' order by mac");
my %unknown_table;
foreach my $unknown (@$unknown_list) {
    next if (!$unknown);
    my ($unknown_id,$unknown_mac,$unknown_port_id,$unknown_device_id)=@$unknown;
    $unknown_mac=mac_simplify($unknown_mac);
    $unknown_table{$unknown_mac}{unknown_id}=$unknown_id;
    $unknown_table{$unknown_mac}{port_id}=$unknown_port_id;
    $unknown_table{$unknown_mac}{device_id}=$unknown_device_id;
    }

my $device_ref = do_sql($dbh,"SELECT ip,snmp_version,community,fdb_snmp_index,device_name,id from devices WHERE deleted=0 and discovery=1 and snmp_version>0" );
foreach my $device (@$device_ref) {

my %port_snmp_index=();
my %port_index=();
my %mac_port_count=();
my %mac_address_table=();
my %port_links=();

my ($dev_ip,$dev_snmp_ver,$dev_community,$dev_fdb_index,$dev_name,$dev_id)=@$device;

next if (!$dev_id);

#print "$dev_ip,$dev_snmp_ver,$dev_community,$dev_fdb_index,$dev_name,$dev_id\n" if ($debug);

my $fdb=get_fdb_table($dev_ip,$dev_community,$dev_snmp_ver);

next if (!$fdb);


my $device_ports = do_sql($dbh,"Select port,snmp_index,target_port_id,id,skip,vlan from device_ports where device_id=$dev_id");

foreach my $port (@$device_ports) {
    my ($port,$snmp_index,$target_port_id,$port_id,$skip_port,$vlan)=@$port;

    my $port_index=$port;
    if (!$vlan) { $vlan=1; }

    if ($dev_fdb_index) {
        if (!$snmp_index) { next; }
        $port_index=$snmp_index;
        }

    my $current_vlan =  get_vlan_at_port($dev_ip,$dev_community,$dev_snmp_ver,$port_index);

    if (!$current_vlan or $current_vlan=~/noSuchInstance/i or !is_integer($current_vlan)) { $current_vlan=1; }

    if ($current_vlan != $vlan) {
	my $dev_ports;
	$dev_ports->{vlan}=$current_vlan;
	update_record($dbh,'device_ports',$dev_ports,"device_id=$dev_id and port=$port");
        db_log_verbose($dbh,"Vlan changed at device $dev_name [$port] old: $vlan current: $current_vlan");
        }

    next if ($skip_port);
    $port_snmp_index{$snmp_index}=$port;
    $port_index{$port}=$port_id;
    $port_links{$port}=$target_port_id;
    $mac_port_count{$port}=0;
    }

foreach my $mac (keys %$fdb) {
    my $port = $fdb->{$mac};
    next if (!$port);
    if ($dev_fdb_index) {
        if (!exists $port_snmp_index{$port}) { next; }
        $port=$port_snmp_index{$port};
        }
    if (!exists $port_index{$port}) { next; }
    $mac_port_count{$port}++;
    $mac_address_table{$mac}=$port;
    }

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
    $mac_history{$simple_mac}{port_id}=$port_index{$port};
    $mac_history{$simple_mac}{dev_id}=$dev_id;
    if (!$mac_history{$simple_mac}{changed}) { $mac_history{$simple_mac}{changed}=0; }

    my $port_id=$port_index{$port};

    if (exists $auth_table{full_table}{$mac} or exists $auth_table{oper_table}{$mac}) {
                my $auth_id;
                if (exists $auth_table{oper_table}{$mac}) { $auth_id=$auth_table{oper_table}{$mac}; } else {
                    $auth_id=$auth_table{full_table}{$mac};
                    if ($debug) {
                        db_log_debug($dbh,"Mac not found in oper ARP-table. Use old values auth_id: $auth_id [$mac] at device $dev_name [$port]");
                        }
                    }

                if (exists $connections{$auth_id}) {
                    if ($port_id == $connections{$auth_id}{port}) {
                        if (exists $auth_table{oper_table}{$mac}) {
                    	    my $auth_rec;
                    	    $auth_rec->{last_found}=$now_str;
	                    update_record($dbh,'User_auth',$auth_rec,"id=".$auth_id);
                    	    }
                        next;
                        }

                    $connections{$auth_id}{port}=$port_id;
                    $mac_history{$simple_mac}{changed}=1;
                    $mac_history{$simple_mac}{auth_id}=$auth_id;
                    db_log_info($dbh,"Found auth_id: $auth_id [$mac] at device $dev_name [$port]. Update connection");
                    my $auth_rec;
                    $auth_rec->{last_found}=$now_str;
                    update_record($dbh,'User_auth',$auth_rec,"id=".$auth_id);
                    my $conn_rec;
                    $conn_rec->{port_id}=$port_id;
                    $conn_rec->{device_id}=$dev_id;
                    update_record($dbh,'connections',$conn_rec,"auth_id=$auth_id");
                    } else {
                    $mac_history{$simple_mac}{changed}=1;
                    $mac_history{$simple_mac}{auth_id}=$auth_id;
                    $connections{$auth_id}{port}=$port_id;
                    db_log_info($dbh,"Found auth_id: $auth_id [$mac] at device $dev_name [$port]. Create connection.");
                    my $auth_rec;
                    $auth_rec->{last_found}=$now_str;
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
                        $mac=mac_splitted($mac);
                        db_log_debug($dbh,"Unknown mac $mac moved to $dev_name [$port]") if ($debug);
                        my $unknown_rec;
                        $unknown_rec->{port_id}=$port_id;
                        $unknown_rec->{device_id}=$dev_id;
                        update_record($dbh,'Unknown_mac',$unknown_rec,"id=$unknown_table{$simple_mac}{unknown_id}");
                        } else {
                        $mac=mac_splitted($mac);
                        $mac_history{$simple_mac}{changed}=1;
                        $mac_history{$simple_mac}{auth_id}=0;
                        db_log_debug($dbh,"Unknown mac $mac found at $dev_name [$port]") if ($debug);
                        my $unknown_rec;
                        $unknown_rec->{port_id}=$port_id;
                        $unknown_rec->{device_id}=$dev_id;
                        $unknown_rec->{mac}=$simple_mac;
                        insert_record($dbh,'Unknown_mac',$unknown_rec);
                        }
                }
    }
}
db_log_verbose($dbh,'Mac discovery stopped.');
}

foreach my $mac (keys %mac_history) {
next if (!$mac);
next if (!$mac_history{$mac}->{changed});
my $h_dev_id='';
$h_dev_id=$mac_history{$mac}->{dev_id} if ($mac_history{$mac}->{dev_id});
my $h_port_id='';
$h_port_id=$mac_history{$mac}->{port_id} if ($mac_history{$mac}->{port_id});
my $h_ip='';
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
