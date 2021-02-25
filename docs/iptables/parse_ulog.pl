#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

#-A FORWARD -j ULOG --ulog-prefix "FORWARD" --ulog-cprange 48 --ulog-qthreshold 50
#-A FORWARD -j USERS

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
use Rstat::mysql;

setpriority(0,0,19);

my $router_id;
my %fields=('device_name'=>'1', 'id'=>'1');
my $gate = get_record($dbh,'devices',\%fields,"deleted=0 and internet_gateway=1 and vendor_id=19 and device_name='".$HOSTNAME."'");
if (!$gate) { exit 0; }
my $router_name=$gate->{device_name};
$router_id = $gate->{id};

db_log_debug($dbh,"Import traffic from router id: $router_id start") if ($debug);

# net objects
my $users = new Net::Patricia;

InitSubnets();

my $dbt = init_traf_db();

#get userid list
my $user_auth_list = $dbh->prepare( "SELECT id,ip,user_id,save_traf FROM User_auth where deleted=0 ORDER by user_id,ip" );
if ( !defined $user_auth_list ) { die "Cannot prepare statement: $DBI::errstr\n"; }

$user_auth_list->execute;

# user auth list
my $authlist_ref = $user_auth_list->fetchall_arrayref();
$user_auth_list->finish();

my %user_stats;

print "\nUser auth ip:\n" if ($debug);
foreach my $row (@$authlist_ref) {
$users->add_string($row->[1],$row->[0]);
print "ip: $row->[1] auth_id: $row->[0]\n" if ($debug);
$user_stats{$row->[0]}{net}=$row->[1];
$user_stats{$row->[0]}{id}=$row->[0];
$user_stats{$row->[0]}{user_id}=$row->[2];
$user_stats{$row->[0]}{save_traf}=$row->[3];
$user_stats{$row->[0]}{in}=0;
$user_stats{$row->[0]}{out}=0;
}

my $last_time = localtime();

my $time_string;
my $dbtime;
my $hour_date;
my $minute_date;

my @batch_sql_traf=();

open(FH,"-");

while (my $line=<FH>) {
#1555573194.980;17   ;     77.243.0.12;   172.20.178.71;    53; 43432;       1;     134;     2;     1
$line=~s/\s+//g;
my ($l_time,$l_proto,$l_src_ip,$l_src_port,$l_dst_ip,$l_dst_port,$l_packets,$l_bytes,$l_input,$l_output,$l_prefix) = split(/ /,$line);

next if (!$l_time);
next if ($l_src_ip eq '0.0.0.0');
next if ($l_dst_ip eq '0.0.0.0');
next if ($l_src_ip eq '255.255.255.255');
next if ($l_dst_ip eq '255.255.255.255');
next if ($l_prefix !~ /FORWARD/i);
next if ($Special_Nets->match_string($l_src_ip) or $Special_Nets->match_string($l_dst_ip));

#unknown networks
if (!$office_networks->match_string($l_src_ip) and !$office_networks->match_string($l_dst_ip)) {
    print "Unknown packet! src: $l_src_ip dst: $l_dst_ip \n";
    next;
    }

#local forward
if ($office_networks->match_string($l_src_ip) and $office_networks->match_string($l_dst_ip)) { next; }

#free forward
if ($office_networks->match_string($l_src_ip) and $free_networks->match_string($l_dst_ip)) { next; }
if ($free_networks->match_string($l_src_ip) and $office_networks->match_string($l_dst_ip)) { next; }

print "Flow: $line\n"  if ($debug);

my $l_src_ip_aton=StrToIp($l_src_ip);
my $l_dst_ip_aton=StrToIp($l_dst_ip);

if ($l_time ne $l_time+0) { $l_time=time-600; }

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
        print "OUT: $out_user + $l_bytes sum: $user_stats{$out_user}{out}\n"  if ($debug);
        $user_found = 1;
        if ($save_detail and $user_stats{$out_user}{save_traf}) {
            my $dSQL="INSERT INTO Traffic_detail (auth_id,router_id,timestamp,proto,src_ip,dst_ip,src_port,dst_port,bytes) VALUES($out_user,$router_id,$dbtime,'$l_proto',$l_src_ip_aton,$l_dst_ip_aton,'$l_src_port','$l_dst_port','$l_bytes')";
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
        print "IN: $in_user + $l_bytes sum: $user_stats{$in_user}{in}\n"  if ($debug);
        $user_found = 1;
        if ($save_detail and $user_stats{$in_user}{save_traf}) {
            my $dSQL="INSERT INTO Traffic_detail (auth_id,router_id,timestamp,proto,src_ip,dst_ip,src_port,dst_port,bytes) VALUES($in_user,$router_id,$dbtime,'$l_proto',$l_src_ip_aton,$l_dst_ip_aton,'$l_src_port','$l_dst_port','$l_bytes')";
            push (@batch_sql_traf,$dSQL);
            }
        }
    }

next if ($users->match_string($l_src_ip) or $users->match_string($l_dst_ip));
next if (!$add_unknown_user);

# find user ip
my $user_ip;
my $user_ip_aton;
undef $user_ip;

#add user by src ip only if dst not office network!!!!
if (!$office_networks->match_string($l_dst_ip) and $office_networks->match_string($l_src_ip)) { $user_ip = $l_src_ip; }

#skip unknown packet
if (!$user_ip) { next; }

$user_ip_aton=StrToIp($user_ip);

db_log_warning($dbh,"New ip $user_ip added by netflow!");

#default user
my $new_user_id=get_new_user_id($dbh,$user_ip);

my $insert_auth;
$insert_auth->{ip}=$user_ip;
$insert_auth->{ip_int}=$user_ip_aton;
$insert_auth->{ip_int_end}=$user_ip_aton;
$insert_auth->{user_id}=$new_user_id;
$insert_auth->{enabled}="0";
$insert_auth->{deleted}="0";
$insert_auth->{save_traf}="$save_detail";
insert_record($dbh,'User_auth',$insert_auth);

my $sSQL="SELECT id,ip,user_id FROM User_auth where ip_int=\"$user_ip_aton\" and deleted=0";
my $get_user_auth = $dbh->prepare($sSQL);
if ( !defined $get_user_auth ) { die "Cannot prepare statement: $DBI::errstr\n"; }
$get_user_auth->execute;

# user auth list
my $new_user = $get_user_auth->fetchall_arrayref();
$get_user_auth->finish();

my $auth_id;
foreach my $row (@$new_user) {
        next if (!$row->[0]);
        $auth_id = $row->[0];
        $users->add_string($user_ip,$auth_id);
        $user_stats{$auth_id}{net}=$user_ip;
        $user_stats{$auth_id}{user_id}=$row->[2];
        $user_stats{$auth_id}{id}=$auth_id;
        $user_stats{$auth_id}{in}=0;
        $user_stats{$auth_id}{out}=0;
        db_log_info($dbh,"Added user_auth id: $auth_id ip: $user_ip user_id: $row->[2]");
        last;
        }

print "ERROR add user_auth!\n" if (!$users->match_string($user_ip));
if ($auth_id) {
        if ($save_detail) {
            my $dSQL="INSERT INTO Traffic_detail (auth_id,router_id,timestamp,proto,src_ip,dst_ip,src_port,dst_port,bytes) VALUES($auth_id,$router_id,$dbtime,'$l_proto',$l_src_ip_aton,$l_dst_ip_aton,'$l_src_port','$l_dst_port','$l_bytes')";
            push (@batch_sql_traf,$dSQL);
            }
        if ($l_src_ip eq $user_ip) {
            $user_stats{$auth_id}{out} += $l_bytes;
            }
        if ($l_dst_ip eq $user_ip) {
            $user_stats{$auth_id}{in} += $l_bytes;
            }
        $user_stats{$auth_id}{dbtime} = $minute_date;
        $user_stats{$auth_id}{htime} = $hour_date;
        } else {
        undef $user_ip;
        undef $user_ip_aton;
        }
}

my ($min,$hour,$day,$month,$year) = (localtime($last_time))[1,2,3,4,5];
$month ++;
$year += 1900;

######## user statistics

print "Update traffic table...\n"  if ($debug);

# update database
foreach my $row (keys %user_stats) {
next if ($user_stats{$row}{in} + $user_stats{$row}{out} <= 0);
# insert row
my $statSQL="INSERT INTO User_traffic (timestamp,auth_id,router_id,byte_in,byte_out,byte_proxy) VALUES($user_stats{$row}{dbtime},$user_stats{$row}{id},$router_id,$user_stats{$row}{in},$user_stats{$row}{out},'0')";
print "$statSQL\n"  if ($debug);
push (@batch_sql_traf,$statSQL);
}

### hour stats
print "Update hourly stats table...\n"  if ($debug);

# get current stats
my $sql = "Select auth_id, SUM(byte_in),SUM(byte_out) from User_stats WHERE ((YEAR(timestamp)=$year) and (MONTH(timestamp)=$month) and (DAY(timestamp)=$day) and (HOUR(timestamp)=$hour) and router_id=$router_id) Group by auth_id order by auth_id";
my $fth = $dbt->prepare($sql);
$fth->execute;

my $hour_stats=$fth->fetchall_arrayref();
$fth->finish;

# update database
foreach my $row (keys %user_stats) {
next if (!$user_stats{$row}{htime});
my $found = 0;
### find current statistics
foreach my $row2 (@$hour_stats) {
    my ($f_s,$f_id,$f_in,$f_out) = @$row2;
    if ($user_stats{$row}{id} eq $f_id) {
        $f_in += $user_stats{$row}{in};
        $f_out += $user_stats{$row}{out};
        $found = 1;
        my $ssql="UPDATE User_stats set byte_in='$f_in', byte_out='$f_out' WHERE (id=$f_s and router_id=$router_id)";
        my $res = $dbt->do($ssql);
        unless ($res) {
            my $dSQL="INSERT INTO User_stats (timestamp,auth_id,router_id,byte_in,byte_out) VALUES($user_stats{$row}{htime},'$user_stats{$row}{id}','$router_id','$f_in','$f_out')";
            push (@batch_sql_traf,$dSQL);
            }
        last;
        }
    }
next if ($found);
my $dSQL="INSERT INTO User_stats (timestamp,auth_id,router_id,byte_in,byte_out) VALUES($user_stats{$row}{htime},'$user_stats{$row}{id}','$router_id','$user_stats{$row}{in}','$user_stats{$row}{out}')";
push (@batch_sql_traf,$dSQL);
}

$dbt->{AutoCommit} = 0;
my $sth;
foreach my $sSQL(@batch_sql_traf) {
$sth = $dbt->prepare($sSQL);
$sth->execute;
}
$sth->finish;
$dbt->{AutoCommit} = 1;

db_log_debug($dbh,"Import traffic from router id: $router_id stop") if ($debug);

$dbt->disconnect;
$dbh->disconnect;

print "Done\n"  if ($debug);

exit 0;
