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
use Date::Parse;
use Rstat::config;
use Rstat::mysql;
use Rstat::net_utils;
use DateTime;
use Fcntl qw(:flock);
open(SELF,"<",$0) or die "Cannot open $0 - $!";
flock(SELF, LOCK_EX|LOCK_NB) or exit 1;

db_log_info($dbh,'Garbage started.');

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
    do_sql($dbh,"Update User_list set blocked=0");
    do_sql($dbh,"Update User_auth set blocked=0, changed=1  WHERE blocked=1 and deleted=0");
    db_log_verbose($dbh,"Amnistuyemo all blocked user by traffic for a month");
    } else {
    #month stat
    my $month_sql="SELECT User_list.id, User_list.login, SUM( traf_all ) AS traf_sum, User_list.month_quota as uquota
    FROM ( SELECT User_stats.auth_id, SUM( byte_in + byte_out ) AS traf_all FROM User_stats 
    WHERE User_stats.`timestamp`>=$month_start AND User_stats.`timestamp`< $month_stop  
    GROUP BY User_stats.auth_id ) AS V, User_auth, User_list
    WHERE V.auth_id = User_auth.id AND User_auth.user_id = User_list.id and User_list.blocked=1 GROUP BY login";
    my @month_stats = get_records_sql($dbh,$month_sql);
    foreach my $row (@month_stats) {
        my $m_quota=$row->{uquota}*$KB*$KB;
        next if ($m_quota < $row->{traf_sum});
        db_log_info($dbh,"Amnistuyemo blocked user $row->{login} [$row->{id}] by traffic for a day");
        do_sql($dbh,"UPDATE User_list set blocked=0 WHERE id=$row->{id}");
        do_sql($dbh,"UPDATE User_auth set blocked=0, changed=1 WHERE user_id=$row->{id}");
        }
    }

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
#        do_sql($dbh,"DELETE FROM connections WHERE auth_id='".$row->{id}."'");
        do_sql($dbh,"UPDATE User_auth SET deleted=1 WHERE id='".$row->{id}."'");
        my $u_count=get_count_records($dbh,'User_auth','deleted=0 and user_id='.$row->{user_id});
        if (!$u_count) {
		delete_record($dbh,"User_list","id=".$row->{'user_id'});
		db_log_verbose($dbh,"Remove dynamic user id: $row->{'user_id'} by dhcp lease timeout");
	        }
        }
    }
}

$now = DateTime->now(time_zone=>'local');
my $day_dur = DateTime::Duration->new( days => $history_dhcp );
my $clean_date = $now - $day_dur;
my $clean_str = $dbh->quote($clean_date->ymd("-")." 00:00:00");

#clean dhcp log
do_sql($dbh,"DELETE FROM dhcp_log WHERE `timestamp` < $clean_str" );
db_log_verbose($dbh,"Clean dhcp leases for all older that ".$clean_str);

##### clean old connections ########
$day_dur = DateTime::Duration->new( days => $connections_history );
$clean_date = $now - $day_dur;
$clean_str = $dbh->quote($clean_date->ymd("-")." 00:00:00");

$users_sql = "SELECT id FROM User_auth WHERE `last_found` < $clean_str and last_found>0";
db_log_debug($dbh,$users_sql) if ($debug);
@users_auth=get_records_sql($dbh,$users_sql);
foreach my $row (@users_auth) {
db_log_debug($dbh,"Clear old connection for user_auth ".$row->{id}) if ($debug);
do_sql($dbh,"DELETE FROM connections WHERE auth_id='".$row->{id}."'");
}

##### clean dup connections ########
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
db_log_verbose($dbh,"Remove dup connection $c_id: $c_port_id $c_auth_id");
}

##### unknown mac clean ############
$users_sql = "SELECT mac FROM User_auth WHERE deleted=0";
@users_auth = get_records_sql($dbh,$users_sql);
foreach my $row (@users_auth) {
next if (!$row->{mac});
do_sql($dbh,"DELETE FROM Unknown_mac WHERE mac='".mac_simplify($row->{mac})."'");
}

##### traffic detail ######

$day_dur = DateTime::Duration->new( days => $history );
$clean_date = $now - $day_dur;
$clean_str = $dbh->quote($clean_date->ymd("-")." 00:00:00");

db_log_verbose($dbh,"Clean traffic detail older that ".$clean_str);
#clean old traffic detail
do_sql($dbh,"DELETE FROM Traffic_detail WHERE `timestamp` < $clean_str" );

##### log  ######

$day_dur = DateTime::Duration->new( days => $history_log_day );
$clean_date = $now - $day_dur;
$clean_str = $dbh->quote($clean_date->ymd("-")." 00:00:00");

db_log_verbose($dbh,"Clean worklog older that ".$clean_str);
do_sql($dbh,"DELETE FROM syslog WHERE `timestamp` < $clean_str" );

##### syslog  ######

$day_dur = DateTime::Duration->new( days => $history_syslog_day );
$clean_date = $now - $day_dur;
$clean_str = $dbh->quote($clean_date->ymd("-")." 00:00:00");

db_log_verbose($dbh,"Clean syslog older that ".$clean_str);
do_sql($dbh,"DELETE FROM remote_syslog WHERE `date` < $clean_str" );

##### Traffic stats  ######

$day_dur = DateTime::Duration->new( days => $history_trafstat_day );
$clean_date = $now - $day_dur;
$clean_str = $dbh->quote($clean_date->ymd("-")." 00:00:00");

db_log_verbose($dbh,"Clean traffic statistics older that ".$clean_str);
do_sql($dbh,"DELETE FROM User_stats WHERE `timestamp` < $clean_str" );

##### Traffic stats full ######

my $iptraf_history = $config_ref{traffic_ipstat_history} || 30;

$day_dur = DateTime::Duration->new( days => $iptraf_history );
$clean_date = $now - $day_dur;
$clean_str = $dbh->quote($clean_date->ymd("-")." 00:00:00");

db_log_verbose($dbh,"Clean traffic full statistics older that ".$clean_str);
do_sql($dbh,"DELETE FROM User_stats_full WHERE `timestamp` < $clean_str" );

#### clean unknown user ip
do_sql($dbh,"DELETE FROM User_auth WHERE (mac is NULL or mac='') and deleted=1");

db_log_info($dbh,'Garbage stopped.');
$dbh->disconnect;

exit 0;
