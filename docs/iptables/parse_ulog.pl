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
use eyelib::config;
use eyelib::main;
use eyelib::net_utils;
use eyelib::database;

setpriority(0,0,19);


my $router_id;
if (scalar @ARGV>1) { $router_id=shift(@ARGV); } else { $router_id=$ARGV[0]; }

if (!$router_id) {
    db_log_error($dbh,"Router id not defined! Bye...");
    exit 110;
    }

my $timeshift = get_option($dbh,55)*60;

db_log_debug($dbh,"Import traffic from router id: $router_id start. Timestep $timeshift sec.") if ($debug);

my %stats;
$stats{pkt}{all}=0;
$stats{pkt}{user}=0;
$stats{pkt}{user_in}=0;
$stats{pkt}{user_out}=0;
$stats{pkt}{free}=0;
$stats{pkt}{unknown}=0;

$stats{line}{all}=0;
$stats{line}{user}=0;
$stats{line}{free}=0;
$stats{line}{unknown}=0;

# net objects
my $users = new Net::Patricia;

InitSubnets();

#get userid list
my $user_auth_list = $dbh->prepare( "SELECT id,ip,user_id,save_traf FROM user_auth where deleted=0 ORDER by user_id,ip" );
if ( !defined $user_auth_list ) { die "Cannot prepare statement: $DBI::errstr\n"; }

$user_auth_list->execute;

# user auth list
my $authlist_ref = $user_auth_list->fetchall_arrayref();
$user_auth_list->finish();

my %user_stats;

foreach my $row (@$authlist_ref) {
$users->add_string($row->[1],$row->[0]);
$user_stats{$row->[0]}{net}=$row->[1];
$user_stats{$row->[0]}{id}=$row->[0];
$user_stats{$row->[0]}{user_id}=$row->[2];
$user_stats{$row->[0]}{save_traf}=$row->[3];
$user_stats{$row->[0]}{in}=0;
$user_stats{$row->[0]}{out}=0;
$user_stats{$row->[0]}{pkt_in}=0;
$user_stats{$row->[0]}{pkt_out}=0;
}

my $last_time = localtime();

my $time_string;
my $dbtime;
my $hour_date;
my $minute_date;

my @batch_sql_traf=();

open(FH,"-");

while (my $line=<FH>) {
$stats{line}{all}++;
#1555573194.980;17   ;     77.243.0.12;   172.20.178.71;    53; 43432;       1;     134;     2;     1
$line=~s/\s+//g;

my ($l_time,$l_proto,$l_src_ip,$l_src_port,$l_dst_ip,$l_dst_port,$l_packets,$l_bytes,$l_input,$l_output,$l_prefix) = split(/ /,$line);
$stats{pkt}{all}+=$l_packets;

if (!$l_time) { $stats{line}{illegal}++; $stats{pkt}{illegal}+=$l_packets; next; }
if ($l_src_ip eq '0.0.0.0') { $stats{line}{illegal}++; $stats{pkt}{illegal}+=$l_packets; next; }
if ($l_dst_ip eq '0.0.0.0') { $stats{line}{illegal}++; $stats{pkt}{illegal}+=$l_packets; next; }
if ($l_src_ip eq '255.255.255.255') { $stats{line}{illegal}++; $stats{pkt}{illegal}+=$l_packets; next; }
if ($l_dst_ip eq '255.255.255.255') { $stats{line}{illegal}++; $stats{pkt}{illegal}+=$l_packets; next; }
if ($Special_Nets->match_string($l_src_ip) or $Special_Nets->match_string($l_dst_ip)) { $stats{line}{illegal}++; $stats{pkt}{illegal}+=$l_packets; next; }

#unknown networks
if (!$office_networks->match_string($l_src_ip) and !$office_networks->match_string($l_dst_ip)) { $stats{line}{illegal}++; $stats{pkt}{illegal}+=$l_packets; next; }

#local forward
if ($office_networks->match_string($l_src_ip) and $office_networks->match_string($l_dst_ip)) { $stats{line}{free}++; $stats{line}{free}+=$l_packets; next; }

#free forward
if ($office_networks->match_string($l_src_ip) and $free_networks->match_string($l_dst_ip)) { $stats{line}{free}++; $stats{line}{free}+=$l_packets; next; }
if ($free_networks->match_string($l_src_ip) and $office_networks->match_string($l_dst_ip)) { $stats{line}{free}++; $stats{line}{free}+=$l_packets; next; }

my $l_src_ip_aton=StrToIp($l_src_ip);
my $l_dst_ip_aton=StrToIp($l_dst_ip);

$last_time = $l_time;
my ($sec,$min,$hour,$day,$month,$year,$zone) = (localtime($l_time))[0,1,2,3,4,5];
$month++;
$year += 1900;

$time_string = sprintf "%04d-%02d-%02d %02d:%02d:%02d",$year,$month,$day,$hour,$min,$sec;
$dbtime = $dbh->quote($time_string);
$hour_date = $dbh->quote(sprintf "%04d-%02d-%02d %02d:00:00",$year,$month,$day,$hour);
$minute_date = $dbh->quote(sprintf "%04d-%02d-%02d %02d:%02d:00",$year,$month,$day,$hour,$min);

my $user_found = 0;
# find user id

if ($office_networks->match_string($l_src_ip)) {
    my $out_user = $users->match_string($l_src_ip);
    if ($out_user) {
        $user_stats{$out_user}{out} += $l_bytes;
        $user_stats{$out_user}{dbtime} = $minute_date;
        $user_stats{$out_user}{htime} = $hour_date;
        $user_stats{$out_user}{pkt_out} +=$l_packets;
        $user_found = 1;
        $stats{line}{user}++;
        $stats{pkt}{user_out}+=$l_packets;
        if ($save_detail and $user_stats{$out_user}{save_traf}) {
            my $dSQL="INSERT INTO traffic_detail (auth_id,router_id,timestamp,proto,src_ip,dst_ip,src_port,dst_port,bytes,pkt) VALUES($out_user,$router_id,$dbtime,'$l_proto',$l_src_ip_aton,$l_dst_ip_aton,'$l_src_port','$l_dst_port','$l_bytes','$l_packets')";
            push (@batch_sql_traf,$dSQL);
            }
        }
    }

if ($office_networks->match_string($l_dst_ip)) {
    my $in_user = $users->match_string($l_dst_ip);
    if ($in_user) {
        $user_stats{$in_user}{in} += $l_bytes;
        $user_stats{$in_user}{dbtime} = $minute_date;
        $user_stats{$in_user}{htime} = $hour_date;
        $user_stats{$in_user}{pkt_in} +=$l_packets;
        $stats{line}{user}++;
        $stats{pkt}{user_in}+=$l_packets;
        $user_found = 1;
        if ($save_detail and $user_stats{$in_user}{save_traf}) {
            my $dSQL="INSERT INTO traffic_detail (auth_id,router_id,timestamp,proto,src_ip,dst_ip,src_port,dst_port,bytes,pkt) VALUES($in_user,$router_id,$dbtime,'$l_proto',$l_src_ip_aton,$l_dst_ip_aton,'$l_src_port','$l_dst_port','$l_bytes','$l_packets')";
            push (@batch_sql_traf,$dSQL);
            }
        }
    }

if (scalar(@batch_sql_traf)>10000) {
    $dbh->{AutoCommit} = 0;
    my $f_sth;
    foreach my $sSQL(@batch_sql_traf) {
        $f_sth = $dbh->prepare($sSQL);
        $f_sth->execute;
        }
    $f_sth->finish;
    $dbh->{AutoCommit} = 1;
    @batch_sql_traf=();
    }

if ($users->match_string($l_src_ip) or $users->match_string($l_dst_ip)) { next; }
if (!$add_unknown_user) { $stats{line}{illegal}++; $stats{pkt}{illegal}+=$l_packets; next; }

# find user ip
my $user_ip;
my $user_ip_aton;
undef $user_ip;

#add user by src ip only if dst not office network!!!!
if (!$office_networks->match_string($l_dst_ip) and $office_networks->match_string($l_src_ip)) { $user_ip = $l_src_ip; }

#skip unknown packet
if (!$user_ip) { $stats{line}{illegal}++; $stats{pkt}{illegal}+=$l_packets; next; }

$stats{line}{user}++;

$user_ip_aton=StrToIp($user_ip);

#new user
my $auth_id=new_auth($dbh,$user_ip);
next if (!$auth_id);

my $new_user = get_record_sql($dbh,"SELECT * FROM user_auth WHERE id=$auth_id");

$users->add_string($user_ip,$auth_id);
$user_stats{$auth_id}{net}=$user_ip;
$user_stats{$auth_id}{user_id}=$new_user->{user_id};
$user_stats{$auth_id}{id}=$auth_id;
$user_stats{$auth_id}{in}=0;
$user_stats{$auth_id}{out}=0;
$user_stats{$auth_id}{pkt_in}=0;
$user_stats{$auth_id}{pkt_out}=0;

db_log_info($dbh,"Added user_auth id: $auth_id ip: $user_ip user_id: $new_user->{user_id}");

if ($auth_id) {
        if ($save_detail) {
            my $dSQL="INSERT INTO traffic_detail (auth_id,router_id,timestamp,proto,src_ip,dst_ip,src_port,dst_port,bytes) VALUES($auth_id,$router_id,$dbtime,'$l_proto',$l_src_ip_aton,$l_dst_ip_aton,'$l_src_port','$l_dst_port','$l_bytes')";
            push (@batch_sql_traf,$dSQL);
            }
        if ($l_src_ip eq $user_ip) {
            $user_stats{$auth_id}{out} += $l_bytes;
            $user_stats{$auth_id}{pkt_out} += $l_bytes;
            }
        if ($l_dst_ip eq $user_ip) {
            $user_stats{$auth_id}{in} += $l_bytes;
            $user_stats{$auth_id}{pkt_in} += $l_bytes;
            }
        $user_stats{$auth_id}{dbtime} = $minute_date;
        $user_stats{$auth_id}{htime} = $hour_date;
        } else {
        undef $user_ip;
        undef $user_ip_aton;
        }
}

#start hour
my ($min,$hour,$day,$month,$year) = (localtime($last_time))[1,2,3,4,5];
my $hour_date1 = $dbh->quote(sprintf "%04d-%02d-%02d %02d:00:00",$year+1900,$month+1,$day,$hour);
my $flow_date = $dbh->quote(sprintf "%04d-%02d-%02d %02d:%02d:00",$year+1900,$month+1,$day,$hour,$min);

#end hour
($min,$hour,$day,$month,$year) = (localtime($last_time+3600))[1,2,3,4,5];
my $hour_date2 = $dbh->quote(sprintf "%04d-%02d-%02d %02d:00:00",$year+1900,$month+1,$day,$hour);

# update database
foreach my $row (keys %user_stats) {
next if (!$user_stats{$row}{htime});

#current stats

my $tSQL="INSERT INTO user_stats_full (timestamp,auth_id,router_id,byte_in,byte_out,pkt_in,pkt_out,step) VALUES($flow_date,'$user_stats{$row}{id}','$router_id','$user_stats{$row}{in}','$user_stats{$row}{out}','$user_stats{$row}{pkt_in}','$user_stats{$row}{pkt_out}','$timeshift')";
push (@batch_sql_traf,$tSQL);

#hour stats

# get current stats
my $sql = "SELECT id, byte_in, byte_out FROM user_stats
WHERE ts>=$hour_date1 AND ts<$hour_date2 AND router_id=$router_id AND auth_id=$user_stats{$row}{id}";
my $hour_stat = get_record_sql($dbh,$sql);
if (!$hour_stat) {
    my $dSQL="INSERT INTO user_stats (timestamp,auth_id,router_id,byte_in,byte_out,pkt_in,pkt_out) VALUES($user_stats{$row}{htime},'$user_stats{$row}{id}','$router_id','$user_stats{$row}{in}','$user_stats{$row}{out}','$user_stats{$row}{pkt_in}','$user_stats{$row}{pkt_out}')";
    push (@batch_sql_traf,$dSQL);
    next;
    }
if (!$hour_stat->{byte_in}) { $hour_stat->{byte_in}=0; }
if (!$hour_stat->{byte_out}) { $hour_stat->{byte_out}=0; }
$hour_stat->{byte_in} += $user_stats{$row}{in};
$hour_stat->{byte_out} += $user_stats{$row}{out};
my $ssql="UPDATE user_stats SET byte_in='".$hour_stat->{byte_in}."', byte_out='".$hour_stat->{byte_out}."' WHERE id=".$hour_stat->{id};
my $res = $dbh->do($ssql);
}

$dbh->{AutoCommit} = 0;
my $sth;
foreach my $sSQL(@batch_sql_traf) {
$sth = $dbh->prepare($sSQL);
$sth->execute;
}
$sth->finish;
$dbh->{AutoCommit} = 1;

db_log_debug($dbh,"Import traffic from router id: $router_id stop") if ($debug);

db_log_verbose($dbh,"Recalc quotes started");
recalc_quotes($dbh,$router_id);
db_log_verbose($dbh,"Recalc quotes stopped");

db_log_verbose($dbh,"router id: $router_id stop Traffic statistics, lines: all => $stats{line}{all}, user=> $stats{line}{user}, free => $stats{line}{free}, illegal=> $stats{line}{illegal}");
db_log_verbose($dbh,sprintf("router id: %d stop Traffic speed, line/s: all => %.2f, user=> %.2f, free => %.2f, unknown=> %.2f", $router_id, $stats{line}{all}/$timeshift, $stats{line}{user}/$timeshift, $stats{line}{free}/$timeshift, $stats{line}{illegal}/$timeshift));
db_log_verbose($dbh,"router id: $router_id stop Traffic statistics, pkt: all => $stats{pkt}{all}, user_in=> $stats{pkt}{user_in}, user_in=> $stats{pkt}{user_out}, free => $stats{pkt}{free}, illegal=> $stats{pkt}{illegal}");
db_log_verbose($dbh,sprintf("router id: %d stop Traffic speed, pkt/s: all => %.2f, user_in=> %.2f, user_out=> %.2f, free => %.2f, unknown=> %.2f", $router_id, $stats{pkt}{all}/$timeshift, $stats{pkt}{user_in}/$timeshift, $stats{pkt}{user_out}/$timeshift, $stats{pkt}{free}/$timeshift, $stats{pkt}{illegal}/$timeshift));

$dbh->disconnect;

exit 0;
