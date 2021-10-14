#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use English;
use base;
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
use Parallel::ForkManager;

my $router_id;
if (scalar @ARGV>1) { $router_id=shift(@ARGV); } else { $router_id=$ARGV[0]; }

if (!$router_id) {
    db_log_error($dbh,"Router id not defined! Bye...");
    exit 110;
    }

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

my $start_time = localtime();

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
    if (scalar(@{$dataref->{sql}})) { batch_db_sql($f_dbh,\@{$dataref->{sql}}); }
    $f_dbh->disconnect;
    }
}
);

$dbh->disconnect;

my @input_buf=();
my $line_count = 0;
my $first_step = 0;

my $child_count = 0;

while (my $line = <STDIN>) {
chomp($line);
$line=~s/\s+//g;
if (!$first_step) {
    my ($l_time,$l_proto,$l_src_ip,$l_dst_ip,$l_src_port,$l_dst_port,$l_packets,$l_bytes,$l_in_dev,$l_out_dev) = split(/;/,$line);
    $start_time = $l_time;
    $first_step = 1;
    }
$line_count++;
push(@input_buf,$line);
if ($line_count < 50000) { next; }
$line_count = 0;
$child_count ++;
my @tmp = @input_buf;
undef @input_buf;
$pm->start and next;
db_log_debug($dbh,"Started child $child_count") if ($debug);
my $ret = calc_stats(\@tmp);
$pm->finish(0, \$ret);
db_log_debug($dbh,"Stopped child $child_count") if ($debug);
}

if (scalar(@input_buf)) {
    $pm->start;
    my $ret = calc_stats(\@input_buf);
    $pm->finish(0, \$ret);
    }

$pm->wait_all_children;

undef(@input_buf);

sub calc_stats {

my $lines = shift;

my $f_dbh = init_db();
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

foreach my $line (@$lines) {
my ($l_time,$l_proto,$l_src_ip,$l_dst_ip,$l_src_port,$l_dst_port,$l_packets,$l_bytes,$l_in_dev,$l_out_dev) = split(/;/,$line);

$lines_stats->{pkt}{all}+=$l_packets;
$lines_stats->{line}{all}++;

if (!$l_time) { $lines_stats->{line}{illegal}++; $lines_stats->{pkt}{illegal}+=$l_packets; next; }
if ($l_src_ip eq '0.0.0.0') { $lines_stats->{line}{illegal}++; $lines_stats->{pkt}{illegal}+=$l_packets; next; }
if ($l_dst_ip eq '0.0.0.0') { $lines_stats->{line}{illegal}++; $lines_stats->{pkt}{illegal}+=$l_packets; next; }
if ($l_src_ip eq '255.255.255.255') { $lines_stats->{line}{illegal}++; $lines_stats->{pkt}{illegal}+=$l_packets; next; }
if ($l_dst_ip eq '255.255.255.255') { $lines_stats->{line}{illegal}++; $lines_stats->{pkt}{illegal}+=$l_packets; next; }
#special networks
if ($Special_Nets->match_string($l_src_ip) or $Special_Nets->match_string($l_dst_ip)) { $lines_stats->{line}{illegal}++; $lines_stats->{pkt}{illegal}+=$l_packets; next; }
#unknown networks
if (!$office_networks->match_string($l_src_ip) and !$office_networks->match_string($l_dst_ip)) { $lines_stats->{line}{illegal}++; $lines_stats->{pkt}{illegal}+=$l_packets; next; }
#local forward
if ($office_networks->match_string($l_src_ip) and $office_networks->match_string($l_dst_ip)) { $lines_stats->{line}{free}++; $lines_stats->{line}{free}+=$l_packets; next; }
#free forward
if ($office_networks->match_string($l_src_ip) and $free_networks->match_string($l_dst_ip)) { $lines_stats->{line}{free}++; $lines_stats->{line}{free}+=$l_packets; next; }
if ($free_networks->match_string($l_src_ip) and $office_networks->match_string($l_dst_ip)) { $lines_stats->{line}{free}++; $lines_stats->{line}{free}+=$l_packets; next; }

my $l_src_ip_aton=StrToIp($l_src_ip);
my $l_dst_ip_aton=StrToIp($l_dst_ip);

my ($sec,$min,$hour,$day,$month,$year,$zone) = (localtime($l_time))[0,1,2,3,4,5];
$month++;
$year += 1900;
my $full_time = $f_dbh->quote(sprintf "%04d-%02d-%02d %02d:%02d:%02d",$year,$month,$day,$hour,$min,$sec);

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

#save full packet
if ($save_detail)  {
    if (($user_ip and $user_stats{$user_ip}{save_traf}) or (!$auth_id and $config_ref{save_detail})) {
        if ($user_ip) { $auth_id = $users->match_string($user_ip); }
        if (!$auth_id) { $auth_id = 0; }
        push(@{$lines_stats->{sql}},"INSERT INTO Traffic_detail (auth_id,router_id,timestamp,proto,src_ip,dst_ip,src_port,dst_port,bytes,pkt) VALUES($auth_id,$router_id,$full_time,'$l_proto',$l_src_ip_aton,$l_dst_ip_aton,'$l_src_port','$l_dst_port','$l_bytes','$l_packets')");
        }
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

$f_dbh->disconnect;

return $lines_stats;
}

my $m_dbh=init_db();

####################################################################################################

#start hour
my ($min,$hour,$day,$month,$year) = (localtime($start_time))[1,2,3,4,5];
#flow time
my $flow_date = $m_dbh->quote(sprintf "%04d-%02d-%02d %02d:%02d:00",$year+1900,$month+1,$day,$hour,$min);
#start stat time
my $hour_date1 = $m_dbh->quote(sprintf "%04d-%02d-%02d %02d:00:00",$year+1900,$month+1,$day,$hour);
#end hour
($hour,$day,$month,$year) = (localtime($start_time+3600))[2,3,4,5];
my $hour_date2 = $m_dbh->quote(sprintf "%04d-%02d-%02d %02d:00:00",$year+1900,$month+1,$day,$hour);

# update database
foreach my $user_ip (keys %user_stats) {

my $user_ip_aton=StrToIp($user_ip);
my $auth_id = $user_stats{$user_ip}{auth_id};

if (!$auth_id) {
    $auth_id=new_auth($m_dbh,$user_ip);
    $user_stats{$user_ip}{auth_id}=$auth_id;
    #fix traffic detail for new users
    push(@batch_sql_traf,"UPDATE Traffic_detail set auth_id=$auth_id WHERE auth_id=0 AND `timestamp`>='$hour_date1' AND `timestamp`<'$hour_date2' AND (src_ip=$user_ip_aton OR dst_ip=$user_ip_aton)");
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
$tSQL="UPDATE User_stats SET byte_in='".$hour_stat->{byte_in}."', byte_out='".$hour_stat->{byte_out}."' WHERE id=".$auth_id;
push (@batch_sql_traf,$tSQL);
}

print Dumper(\@batch_sql_traf);
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
