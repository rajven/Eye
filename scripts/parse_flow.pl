#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use utf8;
use open ":encoding(utf8)";
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
use eyelib::database;
use Parallel::ForkManager;

if (!$ARGV[0]) { exit 110; }

my $router_id=$ARGV[0];

my $fork_count = $cpu_count*10;

my $timeshift = get_option($dbh,55)*60;

db_log_debug($dbh,"Import traffic from router id: $router_id start. Timestep $timeshift sec.") if ($debug);

my %stats;
$stats{pkt}{all}=0;
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
my @auth_list_ref = get_records_sql($dbh,"SELECT id,ip,user_id,save_traf FROM User_auth where deleted=0 ORDER by user_id");

my %user_stats;

foreach my $row (@auth_list_ref) {
$users->add_string($row->{ip},$row->{id});
$user_stats{$row->{ip}}{net}=$row->{ip};
$user_stats{$row->{ip}}{auth_id}=$row->{id};
$user_stats{$row->{ip}}{user_id}=$row->{user_id};
$user_stats{$row->{ip}}{save_traf}=$row->{save_traf};
$user_stats{$row->{ip}}{in}=0;
$user_stats{$row->{ip}}{out}=0;
$user_stats{$row->{ip}}{pkt_in}=0;
$user_stats{$row->{ip}}{pkt_out}=0;
}

my $last_time = localtime();

my $hour_date;
my $minute_date;

my @batch_sql_traf=();

my $pm = Parallel::ForkManager->new($fork_count);

$pm->run_on_finish(
sub {
my ($pid, $exit, $ident, $signal, $core, $data) = @_;
if ($data) {
    my $dataref = ${$data};
    my $f_dbh=init_db();
    db_log_debug($f_dbh,"Run get data from child") if ($debug);
    foreach my $user_ip (keys %{$dataref->{stats}}) {
        $user_stats{$user_ip}{in} += $dataref->{stats}{$user_ip}{in};
        $user_stats{$user_ip}{pkt_in} +=$dataref->{stats}{$user_ip}{pkt_in};
        $user_stats{$user_ip}{out} += $dataref->{stats}{$user_ip}{out};
        $user_stats{$user_ip}{pkt_out} +=$dataref->{stats}{$user_ip}{pkt_out};
        }
    $stats{pkt}{all}+=$dataref->{pkt}{all};
    $stats{pkt}{user_in}+=$dataref->{pkt}{user_in};
    $stats{pkt}{user_out}+=$dataref->{pkt}{user_out};
    $stats{pkt}{free}+=$dataref->{pkt}{free};
    $stats{pkt}{unknown}+=$dataref->{pkt}{unknown};
    $stats{line}{all}+=$dataref->{line}{all};
    $stats{line}{user}+=$dataref->{line}{user};
    $stats{line}{free}+=$dataref->{line}{free};
    $stats{line}{unknown}+=$dataref->{line}{unknown};
    $last_time = $dataref->{last_time};
    db_log_debug($f_dbh,"Get data from child stopped") if ($debug);
    $f_dbh->disconnect;
    }
}
);

$dbh->disconnect;

my @input_buf=();
my $line_count = 0;

my $child_count = 0;

while (my $raw_line = <STDIN>) {
chomp($raw_line);
$raw_line=~s/\s+//g;
$line_count++;
push(@input_buf,$raw_line);
if ($line_count < 50000 and $raw_line =~ /\S/) { next; }
$line_count = 0;
$child_count ++;
my @tmp = ();
push (@tmp,@input_buf);
undef @input_buf;
$pm->start and next;
my $ret = calc_stats(\@tmp);
$pm->finish(0, \$ret);
}

if (scalar(@input_buf)) {
    $child_count ++;
    $pm->start;
    my $ret = calc_stats(\@input_buf);
    $pm->finish(0, \$ret);
    }

$pm->wait_all_children;

undef(@input_buf);

sub calc_stats {

my $lines = shift;

return if (!$lines or !scalar @$lines);

my $f_dbh = init_db();

db_log_debug($f_dbh,"Started child $child_count for ".scalar @$lines." lines count") if ($debug);

my $lines_stats;

$lines_stats->{pkt}{all}=0;
$lines_stats->{pkt}{user_in}=0;
$lines_stats->{pkt}{user_out}=0;
$lines_stats->{pkt}{free}=0;
$lines_stats->{pkt}{unknown}=0;
$lines_stats->{line}{all}=0;
$lines_stats->{line}{user}=0;
$lines_stats->{line}{free}=0;
$lines_stats->{line}{unknown}=0;

my @detail_traffic=();
foreach my $line (@$lines) {
next if (!$line);
my ($l_time,$l_proto,$l_src_ip,$l_dst_ip,$l_src_port,$l_dst_port,$l_packets,$l_bytes,$l_in_dev,$l_out_dev) = split(/;/,$line);
next if (!$l_time or !$l_src_ip or !$l_dst_ip);

$lines_stats->{pkt}{all}+=$l_packets;
$lines_stats->{line}{all}++;
$lines_stats->{last_time} = $l_time;

if (!$l_time) { $lines_stats->{line}{illegal}++; $lines_stats->{pkt}{illegal}+=$l_packets; next; }
if ($l_src_ip eq '0.0.0.0') { $lines_stats->{line}{illegal}++; $lines_stats->{pkt}{illegal}+=$l_packets; next; }
if ($l_dst_ip eq '0.0.0.0') { $lines_stats->{line}{illegal}++; $lines_stats->{pkt}{illegal}+=$l_packets; next; }
if ($l_src_ip eq '255.255.255.255') { $lines_stats->{line}{illegal}++; $lines_stats->{pkt}{illegal}+=$l_packets; next; }
if ($l_dst_ip eq '255.255.255.255') { $lines_stats->{line}{illegal}++; $lines_stats->{pkt}{illegal}+=$l_packets; next; }

#special networks
if ($Special_Nets and $Special_Nets->match_string($l_src_ip) or $Special_Nets->match_string($l_dst_ip)) { $lines_stats->{line}{illegal}++; $lines_stats->{pkt}{illegal}+=$l_packets; next; }
#unknown networks
if ($office_networks and (!$office_networks->match_string($l_src_ip) and !$office_networks->match_string($l_dst_ip))) { $lines_stats->{line}{illegal}++; $lines_stats->{pkt}{illegal}+=$l_packets; next; }
#local forward
if ($office_networks and ($office_networks->match_string($l_src_ip) and $office_networks->match_string($l_dst_ip))) { $lines_stats->{line}{free}++; $lines_stats->{line}{free}+=$l_packets; next; }
#free forward
if ($office_networks and ($office_networks->match_string($l_src_ip) and $free_networks->match_string($l_dst_ip))) { $lines_stats->{line}{free}++; $lines_stats->{line}{free}+=$l_packets; next; }
if ($free_networks and ($free_networks->match_string($l_src_ip) and $office_networks->match_string($l_dst_ip))) { $lines_stats->{line}{free}++; $lines_stats->{line}{free}+=$l_packets; next; }

my $l_src_ip_aton=StrToIp($l_src_ip);
my $l_dst_ip_aton=StrToIp($l_dst_ip);

my ($sec,$min,$hour,$day,$month,$year,$zone) = (localtime($l_time))[0,1,2,3,4,5];
$month++;
$year += 1900;
#my $full_time = $f_dbh->quote(sprintf "%04d-%02d-%02d %02d:%02d:%02d",$year,$month,$day,$hour,$min,$sec);
my $full_time = sprintf "%04d-%02d-%02d %02d:%02d:%02d",$year,$month,$day,$hour,$min,$sec;

my $user_ip;
my $auth_id;

# find user
if ($users->match_string($l_src_ip)) {
    $user_ip = $l_src_ip;
    $lines_stats->{stats}{$user_ip}{ip}=$user_ip;
    if (!$lines_stats->{stats}{$user_ip}{out}) { $lines_stats->{stats}{$user_ip}{out}=0; }
    if (!$lines_stats->{stats}{$user_ip}{pkt_out}) { $lines_stats->{stats}{$user_ip}{pkt_out}=0; }
    $lines_stats->{stats}{$user_ip}{out} += $l_bytes;
    $lines_stats->{stats}{$user_ip}{pkt_out} +=$l_packets;
    $lines_stats->{line}{user}++;
    $lines_stats->{pkt}{user_out}+=$l_packets;
    }
if ($users->match_string($l_dst_ip)) {
    $user_ip = $l_dst_ip;
    $lines_stats->{stats}{$user_ip}{ip}=$l_dst_ip;
    if (!$lines_stats->{stats}{$user_ip}{in}) { $lines_stats->{stats}{$user_ip}{in}=0; }
    if (!$lines_stats->{stats}{$user_ip}{pkt_in}) { $lines_stats->{stats}{$user_ip}{pkt_in}=0; }
    $lines_stats->{stats}{$user_ip}{in} += $l_bytes;
    $lines_stats->{stats}{$user_ip}{pkt_in} +=$l_packets;
    $lines_stats->{line}{user}++;
    $lines_stats->{pkt}{user_in}+=$l_packets;
    }

my $auth_id;
if ($user_ip) { $auth_id = $users->match_string($user_ip); } else { $auth_id = 0; }

#save full packet
if ($config_ref{save_detail})  {
    my @detail_array = ($auth_id,$router_id,$full_time,$l_proto,$l_src_ip_aton,$l_dst_ip_aton,$l_src_port,$l_dst_port,$l_bytes,$l_packets);
    if ($auth_id and $user_stats{$user_ip}{save_traf}) { push(@detail_traffic,\@detail_array); }
    if (!$auth_id and $config_ref{add_unknown_user}) { push(@detail_traffic,\@detail_array); }
    }

if ($auth_id) { next; }

if (!$config_ref{add_unknown_user}) { $lines_stats->{line}{illegal}++; $lines_stats->{pkt}{illegal}+=$l_packets; next; }

#add user by src ip only if dst not office network!!!!
#ignore dst traffic for create user
if (!$office_networks->match_string($l_dst_ip) and $office_networks->match_string($l_src_ip)) {
    $user_ip = $l_src_ip;
    $users->add_string($user_ip,0);
    }

if (!$user_ip) { $lines_stats->{line}{illegal}++; $lines_stats->{pkt}{illegal}+=$l_packets; next; }

if ($user_ip eq $l_src_ip) {
    $lines_stats->{stats}{$user_ip}{ip}=$user_ip;
    $lines_stats->{stats}{$user_ip}{auth_id}= 0;
    if (!$lines_stats->{stats}{$user_ip}{out}) { $lines_stats->{stats}{$user_ip}{out}=0; }
    if (!$lines_stats->{stats}{$user_ip}{pkt_out}) { $lines_stats->{stats}{$user_ip}{pkt_out}=0; }
    $lines_stats->{stats}{$user_ip}{out} += $l_bytes;
    $lines_stats->{stats}{$user_ip}{pkt_out} +=$l_packets;
    $lines_stats->{line}{user}++;
    $lines_stats->{pkt}{user_out}+=$l_packets;
    }
}

db_log_debug($f_dbh,"Stopped child $child_count analyze data") if ($debug);

if (scalar(@detail_traffic)) {
        db_log_debug($f_dbh,"Start write traffic detail to DB. ".scalar @detail_traffic." lines count") if ($debug);
	batch_db_sql_cached("INSERT INTO Traffic_detail (auth_id,router_id,timestamp,proto,src_ip,dst_ip,src_port,dst_port,bytes,pkt) VALUES(?,?,?,?,?,?,?,?,?,?)",\@detail_traffic);
        db_log_debug($f_dbh,"Write traffic detail to DB stopped") if ($debug);
	}

$f_dbh->disconnect;
return $lines_stats;
}

my $m_dbh=init_db();

####################################################################################################

if (!$last_time) { $last_time = localtime(); }

#start hour
my ($min,$hour,$day,$month,$year) = (localtime($last_time))[1,2,3,4,5];
#flow time
my $flow_date = $m_dbh->quote(sprintf "%04d-%02d-%02d %02d:%02d:00",$year+1900,$month+1,$day,$hour,$min);
#start stat time
my $hour_date1 = $m_dbh->quote(sprintf "%04d-%02d-%02d %02d:00:00",$year+1900,$month+1,$day,$hour);
#end hour
($hour,$day,$month,$year) = (localtime($last_time+3600))[2,3,4,5];
my $hour_date2 = $m_dbh->quote(sprintf "%04d-%02d-%02d %02d:00:00",$year+1900,$month+1,$day,$hour);

# update database
foreach my $user_ip (keys %user_stats) {

my $user_ip_aton=StrToIp($user_ip);
my $auth_id = $user_stats{$user_ip}{auth_id};

if (!$auth_id) {
    $auth_id=new_auth($m_dbh,$user_ip);
    $user_stats{$user_ip}{auth_id}=$auth_id;
    #fix traffic detail for new users
    push(@batch_sql_traf,"UPDATE Traffic_detail set auth_id=$auth_id WHERE auth_id=0 AND `timestamp`>=$hour_date1 AND `timestamp`<$hour_date2 AND (src_ip=$user_ip_aton OR dst_ip=$user_ip_aton)");
    }

#skip empty stats
if ($user_stats{$user_ip}{in} + $user_stats{$user_ip}{out} ==0) { next; }

#current stats
my $tSQL="INSERT INTO User_stats_full (timestamp,auth_id,router_id,byte_in,byte_out,pkt_in,pkt_out,step) VALUES($flow_date,'$auth_id','$router_id','$user_stats{$user_ip}{in}','$user_stats{$user_ip}{out}','$user_stats{$user_ip}{pkt_in}','$user_stats{$user_ip}{pkt_out}','$timeshift')";
push (@batch_sql_traf,$tSQL);

#last found timestamp
$tSQL="UPDATE User_auth SET `last_found`=$flow_date WHERE id='$auth_id'";
push (@batch_sql_traf,$tSQL);

#hour stats
# get current stats
my $sql = "SELECT id, byte_in, byte_out FROM User_stats WHERE `timestamp`>=$hour_date1 AND `timestamp`<$hour_date2 AND router_id=$router_id AND auth_id=$auth_id";
my $hour_stat = get_record_sql($m_dbh,$sql);

if (!$hour_stat) {
    my $dSQL="INSERT INTO User_stats (timestamp,auth_id,router_id,byte_in,byte_out) VALUES($flow_date,'$auth_id','$router_id','$user_stats{$user_ip}{in}','$user_stats{$user_ip}{out}')";
    push (@batch_sql_traf,$dSQL);
    next;
    }

if (!$hour_stat->{byte_in}) { $hour_stat->{byte_in}=0; }
if (!$hour_stat->{byte_out}) { $hour_stat->{byte_out}=0; }
$hour_stat->{byte_in} += $user_stats{$user_ip}{in};
$hour_stat->{byte_out} += $user_stats{$user_ip}{out};
$tSQL="UPDATE User_stats SET byte_in='".$hour_stat->{byte_in}."', byte_out='".$hour_stat->{byte_out}."' WHERE id='".$auth_id."' AND router_id='".$router_id."'";
push (@batch_sql_traf,$tSQL);
}

batch_db_sql($m_dbh,\@batch_sql_traf);

db_log_debug($m_dbh,"Import traffic from router id: $router_id stop") if ($debug);

db_log_verbose($m_dbh,"Recalc quotes started");
recalc_quotes($m_dbh,$router_id);
db_log_verbose($m_dbh,"Recalc quotes stopped");

db_log_verbose($m_dbh,"router id: $router_id stop Traffic statistics, lines: all => $stats{line}{all}, user=> $stats{line}{user}, free => $stats{line}{free}, illegal=> $stats{line}{illegal}");
db_log_verbose($m_dbh,sprintf("router id: %d stop Traffic speed, line/s: all => %.2f, user=> %.2f, free => %.2f, unknown=> %.2f", $router_id, $stats{line}{all}/$timeshift, $stats{line}{user}/$timeshift, $stats{line}{free}/$timeshift, $stats{line}{illegal}/$timeshift));
db_log_verbose($m_dbh,"router id: $router_id stop Traffic statistics, pkt: all => $stats{pkt}{all}, user_in=> $stats{pkt}{user_in}, user_in=> $stats{pkt}{user_out}, free => $stats{pkt}{free}, illegal=> $stats{pkt}{illegal}");
db_log_verbose($m_dbh,sprintf("router id: %d stop Traffic speed, pkt/s: all => %.2f, user_in=> %.2f, user_out=> %.2f, free => %.2f, unknown=> %.2f", $router_id, $stats{pkt}{all}/$timeshift, $stats{pkt}{user_in}/$timeshift, $stats{pkt}{user_out}/$timeshift, $stats{pkt}{free}/$timeshift, $stats{pkt}{illegal}/$timeshift));

$m_dbh->disconnect;

exit 0;
