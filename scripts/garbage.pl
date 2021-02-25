#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use FindBin '$Bin';
use lib "$Bin/";
use strict;
use DBI;
use Date::Parse;
use Rstat::config;
use Rstat::mysql;
use Rstat::net_utils;

db_log_info($dbh,'Garbage started.');

#unblock users
my ($sec,$min,$hour,$day,$month,$year,$zone) = localtime(time());

my $history_sql;
my $history_rf;

my %nets;

foreach my $net (@office_network_list) {
my $scope_name=$net;
$scope_name =~s/\/\d+$//g;
$nets{$scope_name}= new Net::Patricia;
$nets{$scope_name}->add_string($net);
}

if ($day==1) {
    do_sql($dbh,"Update User_list set blocked=0");
    do_sql($dbh,"Update User_auth set blocked=0 where deleted=0");
    db_log_verbose($dbh,"Amnistuyemo all blocked user by traffic for a month");
    } else {
    #month stat
    my $month_sql="SELECT User_list.id, User_list.login, SUM( traf_all ) AS traf_sum, User_list.month_quota
    FROM ( SELECT User_stats.auth_id, SUM( byte_in + byte_out ) AS traf_all FROM User_stats WHERE ( YEAR(User_stats.timestamp) = $year and MONTH(User_stats.timestamp) = $month ) GROUP BY User_stats.auth_id ) AS V, User_auth, User_list
    WHERE V.auth_id = User_auth.id AND User_auth.user_id = User_list.id and User_list.blocked=1 GROUP BY login";
    my $fth = $dbh->prepare($month_sql);
    $fth->execute;
    my $month_stats=$fth->fetchall_arrayref();
    $fth->finish;
    foreach my $row (@$month_stats) {
	my ($u_id,$u_login,$u_traf,$u_quota)=@$row;
        my $m_quota=$u_quota*$KB*$KB;
	next if ($m_quota<$u_traf);
        db_log_info($dbh,"Amnistuyemo blocked user $u_login [$u_id] by traffic for a day");
	do_sql($dbh,"UPDATE User_list set blocked=0 where id=$u_id");
        do_sql($dbh,"UPDATE User_auth set blocked=0 where user_id=$u_id");
	}
    }

#### dhcpd ####
my $clean_dhcp_time = time();
my ($sec,$min,$hour,$day,$month,$year,$zone) = localtime($clean_dhcp_time);
$month++;
$year += 1900;
my $clean_dhcp_str="$year-$month-$day";
my $clean_dhcp_date=$dbh->quote($clean_dhcp_str);

#clean temporary dhcp leases & connections
my $users_sql = "Select id from User_auth where date(dhcp_time) < $clean_dhcp_date and (user_id=$default_user_id or user_id=$hotspot_user_id)";
my $users_db = $dbh->prepare($users_sql);
$users_db->execute;
my $users_auth=$users_db->fetchall_arrayref();
$users_db->finish;
foreach my $row (@$users_auth) {
my ($u_id)=@$row;
do_sql($dbh,"delete from connections where auth_id='".$u_id."'");
do_sql($dbh,"delete from dhcp_log where date(timestamp) < $clean_dhcp_date and auth_id='".$u_id."'" );
}
do_sql($dbh,"update User_auth set deleted=1 where date(dhcp_time) < $clean_dhcp_date and (user_id=$default_user_id or user_id=$hotspot_user_id)");
db_log_verbose($dbh,"Clean dhcp leases for user Default older that ".$clean_dhcp_str);

#clean dhcp log
my $clean_dhcp_log = time()- $history_dhcp*3600*24;
my ($sec,$min,$hour,$day,$month,$year,$zone) = localtime($clean_dhcp_log);
$month++;
$year += 1900;
my $clean_dhcp_log_str="$year-$month-$day";
my $clean_dhcp_log_date=$dbh->quote($clean_dhcp_log_str);
do_sql($dbh,"delete from dhcp_log where date(timestamp) < $clean_dhcp_log_date" );
db_log_verbose($dbh,"Clean dhcp leases for all older that ".$clean_dhcp_log_str);

##### clean old connections ########
my $clean_con_time = time()-$connections_history*60*60*24;
my ($sec,$min,$hour,$day,$month,$year,$zone) = localtime($clean_con_time);
$month++;
$year += 1900;
my $clean_con_str="$year-$month-$day";
my $clean_con_date=$dbh->quote($clean_con_str);
$users_sql = "Select id from User_auth where date(last_found) < $clean_con_date and last_found>0 and deleted=0";
db_log_debug($dbh,$users_sql) if ($debug);
$users_db = $dbh->prepare($users_sql);
$users_db->execute;
$users_auth=$users_db->fetchall_arrayref();
$users_db->finish;
foreach my $row (@$users_auth) {
my ($u_id)=@$row;
db_log_debug($dbh,"Clear old connection for user_auth ".$u_id) if ($debug);
do_sql($dbh,"Delete from connections where auth_id='".$u_id."'");
if ($auth_clear) {
    db_log_debug($dbh,"Clear old user_auth ".$u_id) if ($debug);
    do_sql($dbh,"Update User_auth set deleted=1 where id='".$u_id."'"); 
    }
}

##### clean dup connections ########
my $conn_sql = "Select id,port_id,auth_id from connections order by port_id";
my $conn_db = $dbh->prepare($conn_sql);
$conn_db->execute;
my $conn_ref=$conn_db->fetchall_arrayref();
$conn_db->finish;
my $old_port_id=0;
my $old_auth_id=0;
foreach my $row (@$conn_ref) {
my ($c_id,$c_port_id,$c_auth_id)=@$row;
if (!$c_port_id) { $c_port_id=0; }
if (!$c_auth_id) { $c_auth_id=0; }
if ($old_port_id ==0 or $old_auth_id==0) { $old_port_id=$c_port_id; $old_auth_id=$c_auth_id; next; }
if ($old_port_id >0 and $old_port_id != $c_port_id) { $old_port_id=$c_port_id; $old_auth_id=$c_auth_id; next; }
if ($old_auth_id >0 and $old_auth_id != $c_auth_id) { $old_port_id=$c_port_id; $old_auth_id=$c_auth_id; next; }
do_sql($dbh,"delete from connections where id='".$c_id."'");
db_log_verbose($dbh,"Remove dup connection $c_id: $c_port_id $c_auth_id");
}

##### unknown mac clean ############
$users_sql = "Select mac from User_auth where deleted=0";
$users_db = $dbh->prepare($users_sql);
$users_db->execute;
$users_auth=$users_db->fetchall_arrayref();
$users_db->finish;
foreach my $row (@$users_auth) {
my ($u_mac)=@$row;
do_sql($dbh,"Delete from Unknown_mac where mac='".mac_simplify($u_mac)."'");
}

##### traffic detail ######

my $clean_time = time()-$history*60*60*24;
my ($sec,$min,$hour,$day,$month,$year,$zone) = localtime($clean_time);
$month++;
$year += 1900;
my $clean_str="$year-$month-$day";
my $clean_date=$dbh->quote($clean_str);
db_log_verbose($dbh,"Clean traffic detail older that ".$clean_str);
#clean old traffic detail
do_sql($dbh,"delete from Traffic_detail where date(timestamp) < $clean_date" );

##### log  ######

$clean_time = time()-$history_log_day*60*60*24;
my ($sec,$min,$hour,$day,$month,$year,$zone) = localtime($clean_time);
$month++;
$year += 1900;
$clean_str="$year-$month-$day";
$clean_date=$dbh->quote($clean_str);
db_log_verbose($dbh,"Clean worklog older that ".$clean_str);
do_sql($dbh,"delete from syslog where date(timestamp) < $clean_date" );

##### syslog  ######

$clean_time = time()-$history_syslog_day*60*60*24;
my ($sec,$min,$hour,$day,$month,$year,$zone) = localtime($clean_time);
$month++;
$year += 1900;
$clean_str="$year-$month-$day";
$clean_date=$dbh->quote($clean_str);
db_log_verbose($dbh,"Clean syslog older that ".$clean_str);
do_sql($dbh,"delete from remote_syslog where date(`date`) < $clean_date" );

##### Traffic stats  ######

$clean_time = time()-$history_trafstat_day*60*60*24;
my ($sec,$min,$hour,$day,$month,$year,$zone) = localtime($clean_time);
$month++;
$year += 1900;
$clean_str="$year-$month-$day";
$clean_date=$dbh->quote($clean_str);
db_log_verbose($dbh,"Clean traffic statistics older that ".$clean_str);
do_sql($dbh,"delete from User_stats where date(timestamp) < $clean_date" );
do_sql($dbh,"delete from User_traffic where date(timestamp) < $clean_date" );

db_log_info($dbh,'Garbage stopped.');
$dbh->disconnect;
print "Done\n"  if ($debug);

exit 0;
