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
use eyelib::database;
use eyelib::common;
use NetAddr::IP;
use Fcntl qw(:flock);
use Parallel::ForkManager;

# Ensure only one instance of the script runs at a time
open(SELF, "<", $0) or die "Cannot open $0 - $!";
flock(SELF, LOCK_EX | LOCK_NB) or exit 1;

# Lower process priority to minimize system impact
setpriority(0, 0, 19);

# Skip discovery if the system is in configuration mode
if ($config_ref{config_mode}) {
    log_info("System in configuration mode! Skip discovery.");
    exit;
}

# Clean up empty user accounts and associated devices for dynamic users and hotspot
db_log_verbose($dbh, 'Clearing empty records.');

log_info($dbh, 'Clearing empty user accounts and associated devices for dynamic users and hotspot');
my $u_sql = "SELECT * FROM user_list AS U WHERE (U.ou_id = " . $default_hotspot_ou_id . " OR U.ou_id = " . $default_user_ou_id . ") AND (SELECT COUNT(*) FROM user_auth WHERE user_auth.deleted = 0 AND user_auth.user_id = U.id) = 0";
my @u_ref = get_records_sql($dbh, $u_sql);
foreach my $row (@u_ref) {
    db_log_info($dbh, "Remove empty dynamic user with id: $row->{id} login: $row->{login}");
    delete_user($dbh, $row->{id});
}

# Clean up empty non-permanent user accounts that have no authentications or auth rules
#if ($config_ref{clean_empty_user}) {
#    log_info($dbh, 'Clearing empty non-permanent user accounts and associated devices');
#    my $u_sql = "SELECT * FROM user_list AS U WHERE U.permanent = 0 AND (SELECT COUNT(*) FROM user_auth WHERE user_auth.deleted = 0 AND user_auth.user_id = U.id) = 0 AND (SELECT COUNT(*) FROM auth_rules WHERE auth_rules.user_id = U.id) = 0;";
#    my @u_ref = get_records_sql($dbh, $u_sql);
#    foreach my $row (@u_ref) {
#        db_log_info($dbh, "Remove empty user with id: $row->{id} login: $row->{login}");
#        delete_user($dbh, $row->{id});
#    }
#}

# Clean temporary (dynamic) user authentication records that have expired
my $now = DateTime->now(time_zone => 'local');
my $clear_time = $dbh->quote($now->strftime('%Y-%m-%d %H:%M:%S'));
my $users_sql = "SELECT * FROM user_auth WHERE deleted = 0 AND dynamic = 1 AND end_life <= " . $clear_time;
my @users_auth = get_records_sql($dbh, $users_sql);
if (@users_auth and scalar @users_auth) {
    foreach my $row (@users_auth) {
        delete_user_auth($dbh, $row->{id});
        db_log_info($dbh, "Removed dynamic user auth record for auth_id: $row->{'id'} by end_life time: $row->{'end_life'}", $row->{'id'});
        my $u_count = get_count_records($dbh, 'user_auth', 'deleted = 0 AND user_id = ' . $row->{user_id});
        if (!$u_count) {
            delete_user($dbh, $row->{'user_id'});
        }
    }
}

# Track MAC address history for change detection
my %mac_history;

# Get current timestamp components
my ($sec, $min, $hour, $day, $month, $year, $zone) = localtime(time());
$month += 1;
$year += 1900;

# Set parallelization level: 5 processes per CPU core
my $fork_count = $cpu_count * 5;

# Optional: disable forking during debugging (currently descriptioned out)
# if ($debug) { $fork_count = 0; }

my $now_str = sprintf "%04d-%02d-%02d %02d:%02d:%02d", $year, $month, $day, $hour, $min, $sec;
my $now_day = sprintf "%04d-%02d-%02d", $year, $month, $day;

db_log_verbose($dbh, 'ARP discovery started.');

# If script is called with an argument, perform active ping-based network discovery
if ($ARGV[0]) {
    db_log_verbose($dbh, 'Active network check started!');
    my $subnets = get_subnets_ref($dbh);
    my @fping_cmd = ();
    foreach my $net (keys %$subnets) {
        next if (!$net);
        next if (!$subnets->{$net}{discovery});
        my $run_cmd = "$fping -g $subnets->{$net}{subnet} -B1.0 -c 1 >/dev/null 2>&1";
        db_log_debug($dbh, "Checked network $subnets->{$net}{subnet}") if ($debug);
        push(@fping_cmd, $run_cmd);
    }
    $parallel_process_count = $cpu_count * 2;
    run_in_parallel(@fping_cmd);
}

# Fetch all SNMP-enabled routers and L3 switches eligible for ARP discovery
my @router_ref = get_records_sql($dbh, "SELECT * FROM devices WHERE deleted = 0 AND (device_type = 2 OR device_type = 0) AND discovery = 1 AND snmp_version > 0 ORDER BY ip");

# Release main DB handle before forking
$dbh->disconnect;

my @arp_array = ();

# Initialize parallel manager for ARP table collection
my $pm_arp = Parallel::ForkManager->new($fork_count);

# Callback to collect ARP table results from child processes
$pm_arp->run_on_finish(
    sub {
        my ($pid, $exit_code, $ident, $exit_signal, $core_dump, $data_structure_reference) = @_;
        if (defined($data_structure_reference)) {
            my $result = ${$data_structure_reference};
            push(@arp_array, $result);
        }
    }
);

# Iterate over each router and collect its ARP table in parallel
DATA_LOOP:
foreach my $router (@router_ref) {
    my $router_ip = $router->{ip};
    setCommunity($router);
    if (!HostIsLive($router_ip)) {
        log_info("Host id: $router->{id} name: $router->{device_name} ip: $router_ip is down! Skip.");
        next;
    }
    $pm_arp->start() and next DATA_LOOP;

    my $arp_table;
    my $tmp_dbh = init_db();
    if (apply_device_lock($tmp_dbh, $router->{id})) {
        $arp_table = get_arp_table($router_ip, $router->{snmp});
        unset_lock_discovery($tmp_dbh, $router->{id});
    }
    $tmp_dbh->disconnect;
    $pm_arp->finish(0, \$arp_table);
}

# Wait for all ARP collection processes to finish
$pm_arp->wait_all_children;

########################### End ARP collection forks #########################

# Reconnect to database after forking
$dbh = init_db();

# Load all active user authentications indexed by IP
my @authlist_ref = get_records_sql($dbh, "SELECT * FROM user_auth WHERE deleted = 0 ORDER BY ip_int");

# full user ip records
my $users = Net::Patricia->new;
my %ip_list;

foreach my $row (@authlist_ref) {
    $users->add_string($row->{ip}, $row->{id});
    $ip_list{$row->{id}}->{id}   = $row->{id};
    $ip_list{$row->{id}}->{ip}   = $row->{ip};
    $ip_list{$row->{id}}->{mac}  = mac_splitted($row->{mac}) || '';
}

# Process all collected ARP tables
foreach my $arp_table (@arp_array) {
    foreach my $ip (keys %$arp_table) {
        next if (!$arp_table->{$ip});
        my $mac = trim($arp_table->{$ip});
        $mac = mac_splitted($mac);
        next if (!$mac);
        next if ($mac =~ /ff:ff:ff:ff:ff:ff/i);                     # Skip broadcast MAC
        next if ($mac !~ /(\S{2}):(\S{2}):(\S{2}):(\S{2}):(\S{2}):(\S{2})/);  # Validate MAC format

        my $simple_mac = mac_simplify($mac);
        $ip = trim($ip);
        my $ip_aton = StrToIp($ip);

        # Initialize MAC history entry
        $mac_history{$simple_mac}{changed} = 0;
        $mac_history{$simple_mac}{ip}      = $ip;
        $mac_history{$simple_mac}{auth_id} = 0;

        # Skip IPs outside configured office networks
        next if (!$office_networks->match_string($ip));

        db_log_debug($dbh, "Analyze ip: $ip mac: $mac") if ($debug);

        my $auth_id = $users->match_string($ip);
        my $arp_record;
        $arp_record->{ip}        = $ip;
        $arp_record->{mac}       = $mac;
        $arp_record->{type}      = 'arp';
        $arp_record->{ip_aton}   = $ip_aton;
        $arp_record->{hotspot}   = is_hotspot($dbh, $ip);

        # Attempt to resurrect or map this ARP entry to a known auth record
        my $cur_auth_id = resurrection_auth($dbh, $arp_record);
        if (!$cur_auth_id) {
            db_log_warning($dbh, "Unknown record " . Dumper($arp_record));
        } else {
            $mac_history{$simple_mac}{auth_id} = $cur_auth_id;
            $arp_record->{auth_id} = $cur_auth_id;
            # Mark as changed if IP-to-auth mapping differs from previous state
            if ($auth_id ne $cur_auth_id) {
                $mac_history{$simple_mac}{changed} = 1;
            }
        }
    }
}

db_log_verbose($dbh, 'MAC (FDB) discovery started.');

# Load existing port connections for authenticated users
my %connections = ();
my @connections_list = get_records_sql($dbh, "SELECT * FROM connections ORDER BY auth_id");
foreach my $connection (@connections_list) {
    next if (!$connection);
    $connections{$connection->{auth_id}}{port} = $connection->{port_id};
    $connections{$connection->{auth_id}}{id}   = $connection->{id};
}

# Build operational and full MAC-to-auth lookup tables
my $auth_filter = " AND last_found >= '" . $now_day . "' ";
my $auth_sql = "SELECT id, mac FROM user_auth WHERE mac IS NOT NULL AND deleted = 0 $auth_filter ORDER BY id ASC";
my @auth_list = get_records_sql($dbh, $auth_sql);

my %auth_table;
foreach my $auth (@auth_list) {
    next if (!$auth);
    my $auth_mac = mac_simplify($auth->{mac});
    $auth_table{oper_table}{$auth_mac} = $auth->{id};
}

$auth_sql = "SELECT id, mac FROM user_auth WHERE mac IS NOT NULL AND deleted = 0 ORDER BY last_found DESC";
my @auth_full_list = get_records_sql($dbh, $auth_sql);
foreach my $auth (@auth_full_list) {
    next if (!$auth);
    my $auth_mac = mac_simplify($auth->{mac});
    next if (exists $auth_table{full_table}{$auth_mac});
    $auth_table{full_table}{$auth_mac} = $auth->{id};
}

# Load unknown MAC addresses from the database
my @unknown_list = get_records_sql($dbh, "SELECT id, mac, port_id, device_id FROM unknown_mac WHERE mac != '' ORDER BY mac");
my %unknown_table;
foreach my $unknown (@unknown_list) {
    next if (!$unknown);
    my $unknown_mac = mac_simplify($unknown->{mac});
    $unknown_table{$unknown_mac}{unknown_id} = $unknown->{id};
    $unknown_table{$unknown_mac}{port_id}    = $unknown->{port_id};
    $unknown_table{$unknown_mac}{device_id}  = $unknown->{device_id};
}

# Fetch all SNMP-enabled devices (switches, routers, etc.) for FDB discovery
my @device_list = get_records_sql($dbh, "SELECT * FROM devices WHERE deleted = 0 AND discovery = 1 AND device_type <= 2 AND snmp_version > 0");

my @fdb_array = ();

# Initialize parallel manager for FDB (forwarding database) collection
my $pm_fdb = Parallel::ForkManager->new($fork_count);

# Callback to collect FDB results from child processes
$pm_fdb->run_on_finish(
    sub {
        my ($pid, $exit_code, $ident, $exit_signal, $core_dump, $data_structure_reference) = @_;
        if (defined($data_structure_reference)) {
            my $result = ${$data_structure_reference};
            push(@fdb_array, $result);
        }
    }
);

# Release main DB handle before forking
$dbh->disconnect;

# Collect FDB tables from each device in parallel
FDB_LOOP:
foreach my $device (@device_list) {
    setCommunity($device);
    if (!HostIsLive($device->{ip})) {
        log_info("Host id: $device->{id} name: $device->{device_name} ip: $device->{ip} is down! Skip.");
        next;
    }

    my $int_list = get_snmp_ifindex($device->{ip}, $device->{snmp});
    if (!$int_list) {
        log_info("Host id: $device->{id} name: $device->{device_name} ip: $device->{ip} interfaces not found by SNMP request! Skip.");
        next;
    }

    $pm_fdb->start() and next FDB_LOOP;

    my $result;
    my $tmp_dbh = init_db();
    if (apply_device_lock($tmp_dbh, $device->{id})) {
        my $fdb = get_fdb_table($device->{ip}, $device->{snmp});
        unset_lock_discovery($tmp_dbh, $device->{id});
        $result->{id} = $device->{id};
        $result->{fdb} = $fdb;
    }
    $tmp_dbh->disconnect;
    $pm_fdb->finish(0, \$result);
}

# Wait for all FDB collection processes to finish
$pm_fdb->wait_all_children;

# Index FDB results by device ID
my %fdb_ref;
foreach my $fdb_table (@fdb_array) {
    next if (!$fdb_table);
    $fdb_ref{$fdb_table->{id}}{fdb} = $fdb_table->{fdb};
}

################################ End FDB collection forks ##############################

# Reconnect to database after forking
$dbh = init_db();

# Process FDB data for each device
foreach my $device (@device_list) {
    my %port_snmp_index = ();  # SNMP index → logical port number
    my %port_index      = ();  # logical port number → DB port ID
    my %mac_port_count  = ();  # port → number of learned MACs
    my %mac_address_table = (); # MAC → port
    my %port_links      = ();  # port → uplink/downlink target port ID

    my $dev_id   = $device->{id};
    my $dev_name = $device->{device_name};
    my $fdb      = $fdb_ref{$dev_id}{fdb};

    next if (!$fdb);

    # Load device port mappings from database
    my @device_ports = get_records_sql($dbh, "SELECT * FROM device_ports WHERE device_id = $dev_id");
    foreach my $port_data (@device_ports) {
        my $fdb_port_index = $port_data->{port};
        my $port_id = $port_data->{id};
        if (!$port_data->{snmp_index}) {
            $port_data->{snmp_index} = $port_data->{port};
        }
        $fdb_port_index = $port_data->{snmp_index};
        next if ($port_data->{skip});

        $port_snmp_index{$port_data->{snmp_index}} = $port_data->{port};
        $port_index{$port_data->{port}}           = $port_id;
        $port_links{$port_data->{port}}           = $port_data->{target_port_id};
        $mac_port_count{$port_data->{port}}       = 0;
    }

    # Special handling for MikroTik: skip device's own MAC addresses
    my $sw_mac;
    if ($device->{vendor_id} eq '9') {
        my $sw_auth = get_record_sql($dbh, "SELECT mac FROM user_auth WHERE deleted = 0 AND ip = '" . $device->{ip} . "'");
        $sw_mac = mac_simplify($sw_auth->{mac});
        $sw_mac =~ s/.{2}$//s;  # Strip last two hex chars for prefix match
    }

    # Process each MAC in the FDB
    foreach my $mac (keys %$fdb) {
        my $port = $fdb->{$mac};
        next if (!$port);

        # Resolve SNMP index to logical port number
        if (exists $port_snmp_index{$port}) {
            $port = $port_snmp_index{$port};
        }
        next if (!exists $port_index{$port});

        # Skip MikroTik's own MACs
        if ($sw_mac && $mac =~ /^$sw_mac/i) {
            next;
        }

        $mac_port_count{$port}++;
        $mac_address_table{$mac} = $port;
    }

    # Update MAC count per port in the database (skip uplinks/downlinks)
    foreach my $port (keys %mac_port_count) {
        next if (!$port || !exists $port_index{$port} || $port_links{$port} > 0);
        my $dev_ports;
        $dev_ports->{last_mac_count} = $mac_port_count{$port};
        update_record($dbh, 'device_ports', $dev_ports, "device_id = $dev_id AND port = $port");
    }

    # Process each learned MAC address
    foreach my $mac (keys %mac_address_table) {
        my $port = $mac_address_table{$mac};
        next if (!$port || !exists $port_index{$port} || $port_links{$port} > 0);

        my $simple_mac    = mac_simplify($mac);
        my $mac_splitted  = mac_splitted($mac);

        $mac_history{$simple_mac}{port_id} = $port_index{$port};
        $mac_history{$simple_mac}{dev_id}  = $dev_id;
        $mac_history{$simple_mac}{changed} //= 0;

        my $port_id = $port_index{$port};

        # Case 1: MAC belongs to a known authenticated user
        if (exists $auth_table{full_table}{$simple_mac} || exists $auth_table{oper_table}{$simple_mac}) {
            my $auth_id = exists $auth_table{oper_table}{$simple_mac}
                ? $auth_table{oper_table}{$simple_mac}
                : $auth_table{full_table}{$simple_mac};

            unless (exists $auth_table{oper_table}{$simple_mac}) {
                db_log_debug($dbh, "MAC not found in current ARP table. Using historical auth_id: $auth_id [$simple_mac] at device $dev_name [$port]", $auth_id);
            }

            if (exists $connections{$auth_id}) {
                if ($port_id == $connections{$auth_id}{port}) {
                    # No port change: just update last seen time if in current MAC
                    if (exists $auth_table{oper_table}{$simple_mac}) {
                        my $auth_rec;
                        $auth_rec->{last_found} = $now_str;
	                $auth_rec->{mac_found}  = $now_str;
                        update_record($dbh, 'user_auth', $auth_rec, "id = $auth_id");
                    }
                    next;
                }

                # Port changed: update connection and log
                $connections{$auth_id}{port} = $port_id;
                $mac_history{$simple_mac}{changed} = 1;
                $mac_history{$simple_mac}{auth_id} = $auth_id;
                db_log_info($dbh, "Found auth_id: $auth_id ip: $mac_history{$simple_mac}{ip} [$mac_splitted] at device $dev_name [$port]. Update connection.", $auth_id);

                my $auth_rec;
                $auth_rec->{last_found} = $now_str;
                $auth_rec->{mac_found}  = $now_str;
                update_record($dbh, 'user_auth', $auth_rec, "id = $auth_id");

                my $conn_rec;
                $conn_rec->{port_id}   = $port_id;
                $conn_rec->{device_id} = $dev_id;
                update_record($dbh, 'connections', $conn_rec, "auth_id = $auth_id");
            } else {
                # New connection for known user
                $mac_history{$simple_mac}{changed} = 1;
                $mac_history{$simple_mac}{auth_id} = $auth_id;
                $connections{$auth_id}{port} = $port_id;
                db_log_info($dbh, "Found auth_id: $auth_id ip: $mac_history{$simple_mac}{ip} [$mac_splitted] at device $dev_name [$port]. Create connection.", $auth_id);

                my $auth_rec;
                $auth_rec->{last_found} = $now_str;
                $auth_rec->{mac_found}  = $now_str;
                update_record($dbh, 'user_auth', $auth_rec, "id = $auth_id");

                my $conn_rec;
                $conn_rec->{port_id}   = $port_id;
                $conn_rec->{device_id} = $dev_id;
                $conn_rec->{auth_id}   = $auth_id;
                insert_record($dbh, 'connections', $conn_rec);
            }
        }
        # Case 2: MAC is unknown
        else {
            if (exists $unknown_table{$simple_mac}{unknown_id}) {
                # MAC already known but moved
                next if ($unknown_table{$simple_mac}{port_id} == $port_id && $unknown_table{$simple_mac}{device_id} == $dev_id);
                $mac_history{$simple_mac}{changed} = 1;
                $mac_history{$simple_mac}{auth_id} = 0;
                db_log_debug($dbh, "Unknown MAC $mac_splitted moved to $dev_name [$port]") if ($debug);
                my $unknown_rec;
                $unknown_rec->{port_id}   = $port_id;
                $unknown_rec->{device_id} = $dev_id;
                update_record($dbh, 'unknown_mac', $unknown_rec, "id = $unknown_table{$simple_mac}{unknown_id}");
            } else {
                # Brand new unknown MAC
                $mac_history{$simple_mac}{changed} = 1;
                $mac_history{$simple_mac}{auth_id} = 0;
                db_log_debug($dbh, "Unknown MAC $mac_splitted found at $dev_name [$port]") if ($debug);
                my $unknown_rec;
                $unknown_rec->{port_id} = $port_id;
                $unknown_rec->{device_id} = $dev_id;
                $unknown_rec->{mac} = $simple_mac;
                insert_record($dbh, 'unknown_mac', $unknown_rec);
            }
        }
    }
}

# Log all MAC movement/history events
foreach my $mac (keys %mac_history) {
    next if (!$mac || !$mac_history{$mac}->{changed});
    my $h_dev_id  = $mac_history{$mac}->{dev_id}  || '';
    my $h_port_id = $mac_history{$mac}->{port_id} || '';
    my $h_ip      = $mac_history{$mac}->{ip}      || '';
    my $h_auth_id = $mac_history{$mac}->{auth_id} || 0;
    next if (!$h_dev_id);

    my $history_rec;
    $history_rec->{device_id} = $h_dev_id;
    $history_rec->{port_id}   = $h_port_id;
    $history_rec->{mac}       = $mac;
    $history_rec->{ip}        = $h_ip;
    $history_rec->{auth_id}   = $h_auth_id;
    insert_record($dbh, 'mac_history', $history_rec);
}

$dbh->disconnect;
exit 0;
