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

open(SELF,"<",$0) or die "Cannot open $0 - $!";
flock(SELF, LOCK_EX|LOCK_NB) or exit 1;

my @db_tables =(
'connections',
'device_l3_interfaces',
'device_ports',
'User_list',
'User_auth',
'Unknown_mac',
'User_stats',
'User_stats_full',
'dhcp_log',
'worklog',
'remote_syslog',
'Traffic_detail',
);

my $debug_history = 3;

my $optimize = 0;

if ($ARGV[0] =~/optimize/i) { $optimize = 1; }

log_info($dbh,'Garbage started.');

sub is_dhcp_pool {
my $pools = shift;
my $ip_int = shift;
foreach my $subnet (keys %{$pools}) {
#print "net: $subnet ip: $ip_int pool: $pools->{$subnet}->{first_ip} .. $pools->{$subnet}->{last_ip}\n";
if ($ip_int <= $pools->{$subnet}->{last_ip} and $ip_int>= $pools->{$subnet}->{first_ip}) { return $subnet; }
}
return 0;
}

#unblock users
my ($sec,$min,$hour,$day,$month,$year,$zone) = localtime(time());

my $history_sql;
my $history_rf;

my %nets;
my %dhcp_conf;

foreach my $net (@office_network_list) {
my $scope_name=$net;
$scope_name =~s/\/\d+$//g;
$nets{$scope_name}= new Net::Patricia;
$nets{$scope_name}->add_string($net);
}


my $now = DateTime->now(time_zone=>'local');
$now->set(day=>1);
my $month_start=$dbh->quote($now->ymd("-")." 00:00:00");

my $month_dur = DateTime::Duration->new( months => 1 );
my $next_month = $now + $month_dur;
$next_month->set(day=>1);
my $month_stop = $dbh->quote($next_month->ymd("-")." 00:00:00");

my $dhcp_networks = new Net::Patricia;
my @subnets=get_records_sql($dbh,'SELECT * FROM subnets WHERE office=1 AND dhcp=1 AND vpn=0 ORDER BY ip_int_start');
foreach my $subnet (@subnets) {
$dhcp_networks->add_string($subnet->{subnet});
my $subnet_name = $subnet->{subnet};
$subnet_name=~s/\/\d+$//g;
$dhcp_conf{$subnet_name}->{first_ip}=$subnet->{dhcp_start};
$dhcp_conf{$subnet_name}->{last_ip}=$subnet->{dhcp_stop};
}

if ($day==1) {
    log_info($dbh,'Monthly amnesty started');
    db_log_info($dbh,"Amnistuyemo all blocked user by traffic for a month");
    do_sql($dbh,"Update User_list set blocked=0");
    do_sql($dbh,"Update User_auth set blocked=0, changed=1  WHERE blocked=1 and deleted=0");
    log_info($dbh,'Monthly amnesty stopped');
    } else {
    #month stat
    log_info($dbh,'Daily statistics started');
    my $month_sql="SELECT User_list.id, User_list.login, SUM( traf_all ) AS traf_sum, User_list.month_quota as uquota
    FROM ( SELECT User_stats.auth_id, SUM( byte_in + byte_out ) AS traf_all FROM User_stats
    WHERE User_stats.`timestamp`>=$month_start AND User_stats.`timestamp`< $month_stop
    GROUP BY User_stats.auth_id ) AS V, User_auth, User_list
    WHERE V.auth_id = User_auth.id AND User_auth.user_id = User_list.id and User_list.blocked=1 GROUP BY login";
    my @month_stats = get_records_sql($dbh,$month_sql);
    foreach my $row (@month_stats) {
        my $m_quota=$row->{uquota}*$KB*$KB;
        next if ($m_quota < $row->{traf_sum});
        unblock_user($dbh,$row->{id});
        }
    log_info($dbh,'Daily statistics stopped');
    }

log_info($dbh,'Clean dhcp leases for dynamic hosts with overdue lease time');
#clean temporary dhcp leases & connections only for dhcp pool ip
my $users_sql = "SELECT * FROM User_auth WHERE deleted=0 AND (`ou_id`=".$default_user_ou_id." OR `ou_id`=".$default_hotspot_ou_id.")";
my @users_auth = get_records_sql($dbh,$users_sql);
foreach my $row (@users_auth) {
next if (!is_dhcp_pool(\%dhcp_conf,$row->{ip_int}));
my $last_dhcp_time = GetUnixTimeByStr($row->{dhcp_time});
if ($dhcp_networks->match_string($row->{ip})) {
    my $clean_dhcp_time = $last_dhcp_time + 60*$dhcp_networks->match_string($row->{ip});
    if (time() - $clean_dhcp_time>0) {
        db_log_verbose($dbh,"Clean overdue dhcp leases for ip: $row->{ip} id: $row->{id} last dhcp: $row->{dhcp_time} clean time: ".GetTimeStrByUnixTime($clean_dhcp_time)." now: ".GetNowTime());
        delete_user_auth($dbh,$row->{id});
        my $u_count=get_count_records($dbh,'User_auth','deleted=0 and user_id='.$row->{user_id});
        if (!$u_count) {
                delete_user($dbh,$row->{'user_id'});
                db_log_info($dbh,"Remove dynamic user id: $row->{'user_id'} by dhcp lease timeout");
                }
        }
    }
}

$now = DateTime->now(time_zone=>'local');

#clean dhcp log
if ($history_dhcp) {
    my $day_dur = DateTime::Duration->new( days => $history_dhcp );
    my $clean_date = $now - $day_dur;
    my $clean_str = $dbh->quote($clean_date->ymd("-")." 00:00:00");
    log_info($dbh,'Clearing outdated records dhcp log');
    do_sql($dbh,"DELETE FROM dhcp_log WHERE `timestamp` < $clean_str" );
    log_verbose($dbh,"Clean dhcp leases for all older that ".$clean_str);
}

##### clean old connections ########
if ($connections_history) {
    log_info($dbh,'Clearing outdated connection records');
    my $day_dur = DateTime::Duration->new( days => $connections_history );
    my $clean_date = $now - $day_dur;
    my $clean_str = $dbh->quote($clean_date->ymd("-")." 00:00:00");
    $users_sql = "SELECT id FROM User_auth WHERE `last_found` < $clean_str and last_found>0";
    log_debug($dbh,$users_sql) if ($debug);
    @users_auth=get_records_sql($dbh,$users_sql);
    foreach my $row (@users_auth) {
        log_debug($dbh,"Clear old connection for user_auth ".$row->{id});
        do_sql($dbh,"DELETE FROM connections WHERE auth_id='".$row->{id}."'");
    }
}

##### clean dup connections ########
log_info($dbh,'Clearing duplicated connection records');
my $conn_sql = "SELECT id,port_id,auth_id FROM connections order by port_id";
my @conn_ref = get_records_sql($dbh,$conn_sql);
my $old_port_id=0;
my $old_auth_id=0;
foreach my $row (@conn_ref) {
my $c_id = $row->{id};
my $c_port_id = $row->{port_id};
my $c_auth_id = $row->{auth_id};
if (!$c_port_id) { $c_port_id=0; }
if (!$c_auth_id) { $c_auth_id=0; }
if ($old_port_id ==0 or $old_auth_id==0) { $old_port_id=$c_port_id; $old_auth_id=$c_auth_id; next; }
if ($old_port_id >0 and $old_port_id != $c_port_id) { $old_port_id=$c_port_id; $old_auth_id=$c_auth_id; next; }
if ($old_auth_id >0 and $old_auth_id != $c_auth_id) { $old_port_id=$c_port_id; $old_auth_id=$c_auth_id; next; }
do_sql($dbh,"DELETE FROM connections WHERE id='".$c_id."'");
log_info($dbh,"Remove dup connection $c_id: $c_port_id $c_auth_id");
}

##### clean empty user account and corresponded devices for dynamic users and hotspot ################
log_info($dbh,'Clearing empty user account and corresponded devices for dynamic users and hotspot');
my $u_sql = "SELECT * FROM User_list as U WHERE (U.ou_id=".$default_hotspot_ou_id." OR U.ou_id=".$default_user_ou_id.") AND (SELECT COUNT(*) FROM User_auth WHERE User_auth.deleted=0 AND User_auth.user_id = U.id)=0";
my @u_ref = get_records_sql($dbh,$u_sql);
foreach my $row (@u_ref) {
db_log_info($dbh,"Remove empty dynamic user with id: $row->{id} login: $row->{login}");
delete_user($dbh,$row->{id});
}

##### clean empty user account and corresponded devices ################
if ($config_ref{clean_empty_user}) {
    log_info($dbh,'Clearing empty user account and corresponded devices');
#    my $u_sql = "SELECT * FROM User_list as U WHERE (SELECT COUNT(*) FROM User_auth WHERE User_auth.deleted=0 AND User_auth.user_id = U.id)=0 AND (SELECT COUNT(*) FROM auth_rules WHERE auth_rules.user_id = U.id)=0";
#    my $u_sql = "SELECT * FROM User_list as U WHERE (SELECT COUNT(*) FROM User_auth WHERE User_auth.deleted=0 AND User_auth.user_id = U.id)=0";
    my $u_sql = "SELECT * FROM User_list as U WHERE U.permanent=0 AND (SELECT COUNT(*) FROM User_auth WHERE User_auth.deleted=0 AND User_auth.user_id = U.id)=0 AND (SELECT COUNT(*) FROM auth_rules WHERE auth_rules.user_id = U.id)=0;";
    my @u_ref = get_records_sql($dbh,$u_sql);
    foreach my $row (@u_ref) {
        db_log_info($dbh,"Remove empty user with id: $row->{id} login: $row->{login}");
        delete_user($dbh,$row->{id});
        }
    }

##### Remove unreferensed auth rules
do_sql($dbh, "DELETE FROM `auth_rules` WHERE user_id NOT IN (SELECT id FROM User_list)");

##### unknown mac clean ############
log_info($dbh,'Clearing unknown mac if it found in current User_auth table');
$users_sql = "SELECT mac FROM User_auth WHERE deleted=0";
@users_auth = get_records_sql($dbh,$users_sql);
foreach my $row (@users_auth) {
next if (!$row->{mac});
do_sql($dbh,"DELETE FROM Unknown_mac WHERE mac='".mac_simplify($row->{mac})."'");
}

##### traffic detail ######

if ($history) {
    my $day_dur = DateTime::Duration->new( days => $history );
    my $clean_date = $now - $day_dur;
    my $clean_str = $dbh->quote($clean_date->ymd("-")." 00:00:00");
    log_info($dbh,"Clean traffic detail older that ".$clean_str);
    #clean old traffic detail
    do_sql($dbh,"DELETE FROM Traffic_detail WHERE `timestamp` < $clean_str" );
}

##### log  ######

if ($history_log_day) {
    my $day_dur = DateTime::Duration->new( days => $history_log_day );
    my $clean_date = $now - $day_dur;
    my $clean_str = $dbh->quote($clean_date->ymd("-")." 00:00:00");
    log_info($dbh,"Clean VERBOSE worklog older that ".$clean_str);
    do_sql($dbh,"DELETE FROM worklog WHERE level>$L_INFO AND `timestamp` < $clean_str" );
}

#clean debug logs older than $debug_history days
if ($debug_history) {
    my $day_dur = DateTime::Duration->new( days => 3 );
    my $clean_date = $now - $day_dur;
    my $clean_str = $dbh->quote($clean_date->ymd("-")." 00:00:00");
    log_info($dbh,"Clean debug worklog older that ".$clean_str);
    do_sql($dbh,"DELETE FROM worklog WHERE level>=$L_DEBUG AND `timestamp` < $clean_str" );
}

##### remote syslog  ######

if ($history_syslog_day) {
    my $day_dur = DateTime::Duration->new( days => $history_syslog_day );
    my $clean_date = $now - $day_dur;
    my $clean_str = $dbh->quote($clean_date->ymd("-")." 00:00:00");
    log_info($dbh,"Clean syslog older that ".$clean_str);
    do_sql($dbh,"DELETE FROM remote_syslog WHERE `date` < $clean_str" );
}

##### Traffic stats  ######

if ($history_trafstat_day) {
    my $day_dur = DateTime::Duration->new( days => $history_trafstat_day );
    my $clean_date = $now - $day_dur;
    my $clean_str = $dbh->quote($clean_date->ymd("-")." 00:00:00");
    log_info($dbh,"Clean traffic statistics older that ".$clean_str);
    do_sql($dbh,"DELETE FROM User_stats WHERE `timestamp` < $clean_str" );
}

##### Traffic stats full ######
my $iptraf_history = $config_ref{traffic_ipstat_history};
if ($iptraf_history) {
    my $day_dur = DateTime::Duration->new( days => $iptraf_history );
    my $clean_date = $now - $day_dur;
    my $clean_str = $dbh->quote($clean_date->ymd("-")." 00:00:00");
    log_info($dbh,"Clean traffic full statistics older that ".$clean_str);
    do_sql($dbh,"DELETE FROM User_stats_full WHERE `timestamp` < $clean_str" );
}

#### clean unknown user ip
do_sql($dbh,"DELETE FROM User_auth WHERE (mac is NULL or mac='') and deleted=1");

#### save location changes
my %connections;
my @connections_list=get_records_sql($dbh,"SELECT * FROM connections ORDER BY auth_id");
foreach my $connection (@connections_list) {
    next if (!$connection);
    $connections{$connection->{auth_id}}=$connection;
    }

my $auth_sql="SELECT * FROM User_auth WHERE mac IS NOT NULL AND mac !='' AND deleted=0 ORDER BY last_found DESC";
my %auth_table;
my @auth_full_list=get_records_sql($dbh,$auth_sql);
foreach my $auth (@auth_full_list) {
    next if (!$auth);
    my $auth_mac=mac_simplify($auth->{mac});
    next if (exists $auth_table{$auth_mac});
    next if (!exists $connections{$auth->{id}});
    $auth_table{$auth_mac}=1;
    my $h_sql = "SELECT * FROM mac_history WHERE mac='".$auth_mac."' ORDER BY `timestamp` DESC";
    my $history = get_record_sql($dbh,$h_sql);
    if (!$history) {
        #add record to history
        my $cur_conn = $connections{$auth->{id}};
        my $new;
        $new->{device_id}=$cur_conn->{device_id};
        $new->{port_id}=$cur_conn->{port_id};
        $new->{auth_id}=$auth->{id};
        $new->{ip}=$auth->{ip};
        $new->{mac}=$auth_mac;
        $new->{timestamp}=$auth->{last_found};
        db_log_info($dbh,"Auth id: $auth->{id} $auth_mac found at location device_id: $new->{device_id} port_id: $new->{port_id}");
        insert_record($dbh,"mac_history",$new);
        next;
        }
    my $cur_conn = $connections{$auth->{id}};
    #check record history
    if ($history->{device_id} != $cur_conn->{device_id} or $history->{port_id} != $cur_conn->{port_id}) {
            #add new record
            my $new;
            $new->{device_id}=$cur_conn->{device_id};
            $new->{port_id}=$cur_conn->{port_id};
            $new->{auth_id}=$auth->{id};
            $new->{ip}=$auth->{ip};
            $new->{mac}=$auth_mac;
            $new->{timestamp}=$auth->{last_found};
            db_log_info($dbh,"Auth id: $auth->{id} $auth_mac moved to another location device_id: $new->{device_id} port_id: $new->{port_id}");
            insert_record($dbh,"mac_history",$new);
            }
}

if ( $optimize ) {
    log_info($dbh,'Start optimize tables');
    foreach my $table (@db_tables) {
        my $opt_sql = "optimize table ".$table;
        my $opt_rf=$dbh->prepare($opt_sql) or die "Unable to prepare $opt_sql:" . $dbh->errstr;
        my $opt_result = $opt_rf->execute();
        #CREATE TABLE `".$table.".new` LIKE $table;
        #INSERT INTO `".$table.".new` SELECT * FROM $table;
        #RENAME TABLE $table TO `".$table.".backup`;
        #RENAME TABLE `".$table.".new` TO $table;
        #DROP TABLE `".$table.".backup`;";
        }
    log_info($dbh,'Optimize ended.');
    }

log_info($dbh,'Garbage stopped.');
$dbh->disconnect;

exit 0;
