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
use Date::Parse;
use eyelib::config;
use eyelib::database;
use eyelib::common;
use eyelib::net_utils;
use eyelib::main;
use DateTime;
use Fcntl qw(:flock);

# Ensure only one instance of the script runs at a time
open(SELF, "<", $0) or die "Cannot open $0 - $!";
flock(SELF, LOCK_EX | LOCK_NB) or exit 1;

# Number of days to retain debug-level log entries
my $debug_history = 3;

# List of database tables eligible for optimization
#my @db_tables = (
#    'connections',
#    'device_l3_interfaces',
#    'device_ports',
#    'user_list',
#    'user_auth',
#    'unknown_mac',
#    'user_stats',
#    'user_stats_full',
#    'dhcp_log',
#    'worklog',
#    'remote_syslog',
#    'traffic_detail',
#);


# Optimization flag (disabled by default)
#my $optimize = 0;

# Enable table optimization if "optimize" is passed as the first argument
#if ($ARGV[0] =~ /optimize/i) {
#    $optimize = 1;
#}

log_info($dbh, 'Garbage collection started.');

# Helper: Check if a given IP (as integer) belongs to any DHCP pool
sub is_dhcp_pool {
    my $pools   = shift;
    my $ip_int  = shift;
    foreach my $subnet (keys %{$pools}) {
        # Undescription for debugging:
        # print "net: $subnet ip: $ip_int pool: $pools->{$subnet}->{first_ip} .. $pools->{$subnet}->{last_ip}\n";
        if ($ip_int <= $pools->{$subnet}->{last_ip} && $ip_int >= $pools->{$subnet}->{first_ip}) {
            return $subnet;
        }
    }
    return 0;
}

# Get current date components
my ($sec, $min, $hour, $day, $month, $year, $zone) = localtime(time());

my $history_sql;
my $history_rf;

# Build Patricia tries for office networks (used for IP matching)
my %nets;
my %dhcp_conf;

foreach my $net (@office_network_list) {
    my $scope_name = $net;
    $scope_name =~ s/\/\d+$//g;  # Strip CIDR suffix (e.g., /24)
    $nets{$scope_name} = Net::Patricia->new;
    $nets{$scope_name}->add_string($net);
}

# Define the current month’s start and end (for monthly stats)
my $now = DateTime->now(time_zone => 'local');
$now->set(day => 1);
my $month_start = $now->ymd("-") . " 00:00:00";

my $month_dur = DateTime::Duration->new(months => 1);
my $next_month = $now + $month_dur;
$next_month->set(day => 1);
my $month_stop = $next_month->ymd("-") . " 00:00:00";

# Build DHCP network structures for lease validation
my $dhcp_networks = Net::Patricia->new;
my @subnets = get_records_sql($dbh, 'SELECT * FROM subnets WHERE office = 1 AND dhcp = 1 AND vpn = 0 ORDER BY ip_int_start');
foreach my $subnet (@subnets) {
    $dhcp_networks->add_string($subnet->{subnet});
    my $subnet_name = $subnet->{subnet};
    $subnet_name =~ s/\/\d+$//g;
    $dhcp_conf{$subnet_name}->{first_ip} = $subnet->{dhcp_start};
    $dhcp_conf{$subnet_name}->{last_ip}  = $subnet->{dhcp_stop};
}

# On the 1st of the month: perform "monthly amnesty" — unblock all traffic-blocked users
if ($day == 1) {
    log_info($dbh, 'Monthly amnesty started');
    db_log_info($dbh, "Unblocking all users blocked due to traffic quota");
    do_sql($dbh, "UPDATE user_list SET blocked = 0");
    do_sql($dbh, "UPDATE user_auth SET blocked = 0, changed = 1 WHERE blocked = 1 AND deleted = 0");
    log_info($dbh, 'Monthly amnesty completed');
} else {
    # Daily: unblock users whose monthly traffic is now below quota
    log_info($dbh, 'Daily traffic-based unblocking started');

    my $month_sql = "
    SELECT
        ul.id,
        SUM(us.byte_in + us.byte_out) AS traf_sum,
        ul.month_quota AS uquota
    FROM user_stats us
    JOIN user_auth ua ON us.auth_id = ua.id
    JOIN user_list ul ON ua.user_id = ul.id
    WHERE ul.blocked = 1 AND us.ts >= ? AND us.ts < ?
    GROUP BY ul.id, ul.month_quota;
    ";

    my @month_stats = get_records_sql($dbh, $month_sql, $month_start, $month_stop);
    foreach my $row (@month_stats) {
        my $m_quota = $row->{uquota} * $KB * $KB;  # Convert MB to bytes
        next if ($m_quota < $row->{traf_sum});     # Skip if still over quota
        unblock_user($dbh, $row->{id});
    }
    log_info($dbh, 'Daily traffic-based unblocking completed');
}

# Clean expired DHCP leases for dynamic users (hotspot and default OU only)
log_info($dbh, 'Cleaning DHCP leases with overdue expiration for dynamic hosts');

my $users_sql = "SELECT * FROM user_auth WHERE deleted = 0 AND (ou_id = ? OR ou_id = ?)";
my @users_auth = get_records_sql($dbh, $users_sql, $default_user_ou_id, $default_hotspot_ou_id);
foreach my $row (@users_auth) {
    # Skip if IP is not in any DHCP pool
    next if (!is_dhcp_pool(\%dhcp_conf, $row->{ip_int}));

    # Only process IPs that belong to a DHCP-managed subnet
    if ($dhcp_networks->match_string($row->{ip})) {
        my $last_dhcp_time = GetUnixTimeByStr($row->{dhcp_time});
        # Lease timeout = last DHCP time + (60 * lease time in minutes)
        my $clean_dhcp_time = $last_dhcp_time + 60 * $dhcp_networks->match_string($row->{ip});

        if (time() > $clean_dhcp_time) {
            db_log_verbose($dbh, "Cleaning overdue DHCP lease for IP: $row->{ip}, auth_id: $row->{id}, last DHCP: $row->{dhcp_time}, clean time: " . GetTimeStrByUnixTime($clean_dhcp_time) . ", now: " . GetNowTime());
            delete_user_auth($dbh, $row->{id});

            # Also delete parent user if no other active sessions remain
            my $u_count = get_count_records($dbh, 'user_auth', "deleted = 0 AND user_id = ? ", $row->{user_id});
            if (!$u_count) {
                delete_user($dbh, $row->{'user_id'});
                db_log_info($dbh, "Removed dynamic user id: $row->{'user_id'} due to DHCP lease timeout");
            }
        }
    }
}

$now = DateTime->now(time_zone => 'local');

# Clean old DHCP log entries (if retention policy is set)
if ($history_dhcp) {
    my $day_dur = DateTime::Duration->new(days => $history_dhcp);
    my $clean_date = $now - $day_dur;
    my $clean_str = $clean_date->ymd("-") . " 00:00:00";
    log_info($dbh, 'Clearing outdated DHCP log records');
    do_sql($dbh, "DELETE FROM dhcp_log WHERE ts < ?",$clean_str);
    log_verbose($dbh, "Removed DHCP log entries older than $clean_str");
}

# Clean old connection records (based on $connections_history setting)
if ($connections_history) {
    log_info($dbh, 'Clearing outdated connection records');
    my $day_dur = DateTime::Duration->new(days => $connections_history);
    my $clean_date = $now - $day_dur;
    my $clean_str = $clean_date->ymd("-") . " 00:00:00";
    $users_sql = "SELECT id FROM user_auth WHERE last_found < ? AND last_found IS NOT NULL";
    @users_auth = get_records_sql($dbh, $users_sql, $clean_str);
    foreach my $row (@users_auth) {
        log_debug($dbh, "Clearing old connection for auth_id: " . $row->{id});
        do_sql($dbh, "DELETE FROM connections WHERE auth_id = ?", $row->{id});
    }
}

# Remove duplicate connection records (same auth_id + port_id)
log_info($dbh, 'Clearing duplicate connection records');
my $conn_sql = "SELECT id, port_id, auth_id FROM connections ORDER BY port_id";
my @conn_ref = get_records_sql($dbh, $conn_sql);
my $old_port_id = 0;
my $old_auth_id = 0;
foreach my $row (@conn_ref) {
    my $c_id        = $row->{id};
    my $c_port_id   = $row->{port_id} || 0;
    my $c_auth_id   = $row->{auth_id} || 0;

    if ($old_port_id == 0 || $old_auth_id == 0) {
        $old_port_id = $c_port_id;
        $old_auth_id = $c_auth_id;
        next;
    }

    # If we're still on the same (port, auth) pair, this is a duplicate
    if ($old_port_id == $c_port_id && $old_auth_id == $c_auth_id) {
        do_sql($dbh, "DELETE FROM connections WHERE id = ?",$c_id);
        log_info($dbh, "Removed duplicate connection id=$c_id: port=$c_port_id auth=$c_auth_id");
    } else {
        $old_port_id = $c_port_id;
        $old_auth_id = $c_auth_id;
    }
}

# Clean empty dynamic/hotspot user accounts (no active authentications)
log_info($dbh, 'Clearing empty user accounts and associated devices for dynamic users and hotspot');
my $u_sql = "SELECT * FROM user_list AS U WHERE (U.ou_id = ? OR U.ou_id = ? ) AND (SELECT COUNT(*) FROM user_auth WHERE user_auth.deleted = 0 AND user_auth.user_id = U.id) = 0";
my @u_ref = get_records_sql($dbh, $u_sql, $default_hotspot_ou_id, $default_user_ou_id);
foreach my $row (@u_ref) {
    db_log_info($dbh, "Removing empty dynamic user with id: $row->{id}, login: $row->{login}");
    delete_user($dbh, $row->{id});
}

# Clean empty non-permanent user accounts (if enabled in config)
if ($config_ref{clean_empty_user}) {
    log_info($dbh, 'Clearing empty non-permanent user accounts and associated devices');
    my $u_sql = "SELECT * FROM user_list AS U WHERE U.permanent = 0 AND (SELECT COUNT(*) FROM user_auth WHERE user_auth.deleted = 0 AND user_auth.user_id = U.id) = 0 AND (SELECT COUNT(*) FROM auth_rules WHERE auth_rules.user_id = U.id) = 0;";
    my @u_ref = get_records_sql($dbh, $u_sql);
    foreach my $row (@u_ref) {
        db_log_info($dbh, "Removing empty user with id: $row->{id}, login: $row->{login}");
        delete_user($dbh, $row->{id});
    }
}

# Remove orphaned auth rules (no corresponding user)
do_sql($dbh, "DELETE FROM auth_rules WHERE user_id NOT IN (SELECT id FROM user_list)");

# Clean unknown MAC entries that now belong to known users
log_info($dbh, 'Removing unknown MAC records that are now associated with active users');
$users_sql = "SELECT mac FROM user_auth WHERE deleted = 0";
@users_auth = get_records_sql($dbh, $users_sql);
foreach my $row (@users_auth) {
    next if (!$row->{mac});
    do_sql($dbh, "DELETE FROM unknown_mac WHERE mac = ?", mac_splitted($row->{mac}));
}

# Clean old detailed traffic records (based on global $history setting)
if ($history) {
    my $day_dur = DateTime::Duration->new(days => $history);
    my $clean_date = $now - $day_dur;
    my $clean_str = $clean_date->ymd("-") . " 00:00:00";
    log_info($dbh, "Cleaning traffic detail records older than $clean_str");
    do_sql($dbh, "DELETE FROM traffic_detail WHERE ts < ?", $clean_str);
}

# Clean verbose (non-info) worklog entries
if ($history_log_day) {
    my $day_dur = DateTime::Duration->new(days => $history_log_day);
    my $clean_date = $now - $day_dur;
    my $clean_str = $clean_date->ymd("-") . " 00:00:00";
    log_info($dbh, "Cleaning VERBOSE worklog entries older than $clean_str");
    do_sql($dbh, "DELETE FROM worklog WHERE level > ? AND ts < ?", $L_INFO, $clean_str);
}

# Clean debug-level worklog entries older than $debug_history days (hardcoded to 3)
if ($debug_history) {
    my $day_dur = DateTime::Duration->new(days => 3);
    my $clean_date = $now - $day_dur;
    my $clean_str = $clean_date->ymd("-") . " 00:00:00";
    log_info($dbh, "Cleaning debug worklog entries older than $clean_str");
    do_sql($dbh, "DELETE FROM worklog WHERE level >= ? AND ts < ?",$L_DEBUG, $clean_str);
}

# Clean old remote syslog entries
if ($history_syslog_day) {
    my $day_dur = DateTime::Duration->new(days => $history_syslog_day);
    my $clean_date = $now - $day_dur;
    my $clean_str = $clean_date->ymd("-") . " 00:00:00";
    log_info($dbh, "Cleaning syslog entries older than $clean_str");
    do_sql($dbh, "DELETE FROM remote_syslog WHERE ts < ?",$clean_str);
}

# Clean old aggregated traffic statistics
if ($history_trafstat_day) {
    my $day_dur = DateTime::Duration->new(days => $history_trafstat_day);
    my $clean_date = $now - $day_dur;
    my $clean_str = $clean_date->ymd("-") . " 00:00:00";
    log_info($dbh, "Cleaning traffic statistics older than $clean_str");
    do_sql($dbh, "DELETE FROM user_stats WHERE ts < ?",$clean_str);
}

# Clean old per-IP full traffic statistics (if retention is configured)
my $iptraf_history = $config_ref{traffic_ipstat_history};
if ($iptraf_history) {
    my $day_dur = DateTime::Duration->new(days => $iptraf_history);
    my $clean_date = $now - $day_dur;
    my $clean_str = $clean_date->ymd("-") . " 00:00:00";
    log_info($dbh, "Cleaning full traffic statistics older than $clean_str");
    do_sql($dbh, "DELETE FROM user_stats_full WHERE ts < ?",$clean_str);
}

# Clean dangling user_auth records (deleted, but with no MAC — likely artifacts)
do_sql($dbh, "DELETE FROM user_auth WHERE (mac IS NULL OR mac = '') AND deleted = 1");

# Ensure current user locations are recorded in mac_history
my %connections;
my @connections_list = get_records_sql($dbh, "SELECT * FROM connections ORDER BY auth_id");
foreach my $connection (@connections_list) {
    next if (!$connection);
    $connections{$connection->{auth_id}} = $connection;
}

# Build a set of currently active, non-empty MACs with valid connections
my $auth_sql = "SELECT * FROM user_auth WHERE mac IS NOT NULL AND mac != '' AND deleted = 0 ORDER BY last_found DESC";
my %auth_table;
my @auth_full_list = get_records_sql($dbh, $auth_sql);
foreach my $auth (@auth_full_list) {
    next if (!$auth);
    my $auth_mac = mac_splitted($auth->{mac});
    next if (exists $auth_table{$auth_mac});
    next if (!exists $connections{$auth->{id}});

    $auth_table{$auth_mac} = 1;

    # Check if location history already exists
    my $h_sql = "SELECT * FROM mac_history WHERE mac = ? ORDER BY ts";
    my $history = get_record_sql($dbh, $h_sql, $auth_mac);

    my $cur_conn = $connections{$auth->{id}};

    if (!$history) {
        # First-time location: insert new history record
        my $new;
        $new->{device_id} = $cur_conn->{device_id};
        $new->{port_id}   = $cur_conn->{port_id};
        $new->{auth_id}   = $auth->{id};
        $new->{ip}        = $auth->{ip};
        $new->{mac}       = $auth_mac;
        $new->{ts}        = $auth->{mac_found};
        db_log_info($dbh, "Auth id: $auth->{id} ($auth_mac) found at location: device_id=$new->{device_id}, port_id=$new->{port_id}");
        insert_record($dbh, "mac_history", $new);
        next;
    }

    # Check if location has changed since last history entry
    if ($history->{device_id} != $cur_conn->{device_id} || $history->{port_id} != $cur_conn->{port_id}) {
        my $new;
        $new->{device_id} = $cur_conn->{device_id};
        $new->{port_id}   = $cur_conn->{port_id};
        $new->{auth_id}   = $auth->{id};
        $new->{ip}        = $auth->{ip};
        $new->{mac}       = $auth_mac;
        $new->{ts}        = $auth->{mac_found};
        db_log_info($dbh, "Auth id: $auth->{id} ($auth_mac) moved to new location: device_id=$new->{device_id}, port_id=$new->{port_id}");
        insert_record($dbh, "mac_history", $new);
    }
}

# Optional: Optimize database tables to reclaim space and improve performance
#if ($optimize) {
#    log_info($dbh, 'Starting table optimization');
#    foreach my $table (@db_tables) {
#        my $opt_sql = "OPTIMIZE TABLE $table";
#        my $opt_rf = $dbh->prepare($opt_sql) or die "Unable to prepare $opt_sql: " . $dbh->errstr;
#        $opt_rf->execute();
#        # Alternative (manual rebuild) is descriptioned out:
#        # CREATE TABLE $table.new LIKE $table;
#        # INSERT INTO $table.new SELECT * FROM $table;
#        # RENAME TABLE $table TO $table.backup, $table.new TO $table;
#        # DROP TABLE $table.backup;
#    }
#    log_info($dbh, 'Table optimization completed.');
#}

log_info($dbh, 'Garbage collection finished.');
$dbh->disconnect;
exit 0;
