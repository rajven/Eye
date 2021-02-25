#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use FindBin '$Bin';
use lib "$Bin/";
use strict;
use DBI;
use Time::Local;
use Data::Dumper;
use Date::Parse;
use Socket;
use Rstat::config;
use Rstat::main;
use Rstat::net_utils;
use Rstat::mysql;

setpriority(0,0,19);

InitSubnets();

my ($sec,$min,$hour,$day,$month,$year,$zone) = strptime(localtime());
$month++;
$year += 1900;
my $day_date=$dbh->quote("$year-$month-$day");

my $dbt = init_traf_db();

#get user limits
my $user_auth_list=$dbh->prepare( "SELECT A.id, U.id, U.day_quota, U.month_quota, A.day_quota, A.month_quota FROM User_auth as A,User_list as U where A.deleted=0 ORDER by user_id" );
if ( !defined $user_auth_list ) { die "Cannot prepare statement: $DBI::errstr\n"; }
$user_auth_list->execute;
my $authlist_ref = $user_auth_list->fetchall_arrayref();
$user_auth_list->finish();

my %user_stats;
my %auth_info;
foreach my $row (@$authlist_ref) {
    $auth_info{$row->[0]}{user_id}=$row->[1];
    $auth_info{$row->[0]}{day_limit}=$row->[4];
    $auth_info{$row->[0]}{month_limit}=$row->[5];
    $auth_info{$row->[0]}{day}=0;
    $auth_info{$row->[0]}{month}=0;
    $user_stats{$row->[1]}{day_limit}=$row->[2];
    $user_stats{$row->[1]}{month_limit}=$row->[3];
    $user_stats{$row->[1]}{day}=0;
    $user_stats{$row->[1]}{month}=0;
}

#recalc quotes - global
#day
my $day_sql="SELECT User_stats.auth_id, SUM( byte_in + byte_out ) AS traf_all FROM User_stats 
WHERE ( Date( User_stats.timestamp ) = $day_date ) GROUP BY User_stats.auth_id";
my $fth = $dbt->prepare($day_sql);
$fth->execute;
my $day_stats=$fth->fetchall_arrayref();
$fth->finish;
foreach my $row (@$day_stats) {
    my ($a_id,$a_traf)=@$row;
    my $user_id=$auth_info{$a_id}{user_id};
    $auth_info{$a_id}{day}=$a_traf;
    $user_stats{$user_id}{day}+=$a_traf;
}

#month
my $month_sql="SELECT User_stats.auth_id, SUM( byte_in + byte_out ) AS traf_all FROM User_stats 
WHERE ( YEAR(User_stats.timestamp) = $year and MONTH(User_stats.timestamp) = $month ) GROUP BY User_stats.auth_id";
my $mth = $dbt->prepare($month_sql);
$mth->execute;
my $month_stats=$mth->fetchall_arrayref();
$mth->finish;
foreach my $row (@$month_stats) {
    my ($a_id,$a_traf)=@$row;
    my $user_id=$auth_info{$a_id}{user_id};
    $auth_info{$a_id}{month}=$a_traf;
    $user_stats{$user_id}{month}+=$a_traf;
}

foreach my $auth_id (keys %auth_info) {
next if (!$auth_info{$auth_id}{day_limit});
next if (!$auth_info{$auth_id}{month_limit});
my $day_limit=$auth_info{$auth_id}{day_limit}*$KB*$KB;
my $month_limit=$auth_info{$auth_id}{month_limit}*$KB*$KB;
my $blocked_d=($auth_info{$auth_id}{day}>$day_limit);
my $blocked_m=($auth_info{$auth_id}{month}>$month_limit);
if ($blocked_d or $blocked_m) {
    my $history_msg;
    if ($blocked_d) { $history_msg=printf "Day quota limit found for auth_id: $auth_id - Current: %d Max: %d",$auth_info{$auth_id}{day},$day_limit; }
    if ($blocked_m) { $history_msg=printf "Month quota limit found for auth_id: $auth_id - Current: %d Max: %d",$auth_info{$auth_id}{month},$month_limit; }
    do_sql($dbh,"UPDATE User_auth set blocked=1 where id=$auth_id");
    db_log_verbose($dbh,$history_msg);
    }
}

foreach my $user_id (keys %user_stats) {
next if (!$user_stats{$user_id}{day_limit});
next if (!$user_stats{$user_id}{month_limit});
my $day_limit=$user_stats{$user_id}{day_limit}*$KB*$KB;
my $month_limit=$user_stats{$user_id}{month_limit}*$KB*$KB;
my $blocked_d=($user_stats{$user_id}{day}>$day_limit);
my $blocked_m=($user_stats{$user_id}{month}>$month_limit);
if ($blocked_d or $blocked_m) {
    my $history_msg;
    if ($blocked_d) { $history_msg=printf "Day quota limit found for user_id: $user_id - Current: %d Max: %d",$user_stats{$user_id}{day},$day_limit; }
    if ($blocked_m) { $history_msg=printf "Month quota limit found for user_id: $user_id - Current: %d Max: %d",$user_stats{$user_id}{month},$month_limit; }
    do_sql($dbh,"UPDATE User_user set blocked=1 where id=$user_id");
    do_sql($dbh,"UPDATE User_auth set blocked=1 where user_id=$user_id");
    db_log_verbose($dbh,$history_msg);
    }
}

$dbh->disconnect;
$dbt->disconnect;

print "Done\n"  if ($debug);

exit 0;
