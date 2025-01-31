#!/usr/bin/perl

use FindBin;
use Net::SNMP;
use POSIX;
use strict;
use lib "$FindBin::/etc/nagios4/scripts/";
use lib "/etc/nagios4/scripts/plugins";
use snmp;
use Nag::mysql;
use Data::Dumper;
use DateTime;

my $TIMEOUT = 30;
$SIG{ALRM} = sub { print "ERROR: No response\n"; exit 3; };
alarm($TIMEOUT);

### return codes
my $RET_OK=0;
my $RET_WARNING=1;
my $RET_UNKNOWN=3;
my $RET_CRITICAL=2;

if (scalar @ARGV <= 2) {
    print "Usage: $0 <host> <snmp_string> <port> [warning] [critical]\n";
    print "<snmp_string> => community;version;user;auth;priv\n";
    print "for version 3: community = password\n";
    exit $RET_OK;
    }

my $host = shift @ARGV;
my $snmp_str = shift @ARGV;
my ($community,$version,$user,$auth,$priv) = split(/;/,$snmp_str);

my $snmp;
$snmp->{version} = $version || '2';
$snmp->{timeout} = 30;
$snmp->{community} = $community || 'public';
$snmp->{user} = $user || 'public';
$snmp->{auth} = $auth || 'sha1';
$snmp->{priv} = $priv || 'aes';

my $port = shift @ARGV;

#minimal sporadic value
my $err_step = 100;

##########################################################################

sub get_snmp_crc {
my $port = shift;

my $CRC_OID = ".1.3.6.1.2.1.2.2.1.14.".$port;
my $session = init_snmp($host,$snmp);

my $result_crc = $session->get_request( -varbindlist => [$CRC_OID]);
$session->close;
my $result = $result_crc->{$CRC_OID} || 0;
return $result;
}

###########################################################################

my $start = DateTime->now(time_zone => 'local');
my $start_time = $start->epoch;

my $clean_start = 0;

my $dbh = init_db();

if (!$dbh) {
    print("ERROR connect to database\n");
    exit $RET_UNKNOWN;
    }

my $ip_aton = StrToIp($host);

#data_type = 1 for crc
my $old_crc_info = get_record_sql($dbh,"SELECT * FROM netdevices WHERE ip=".$ip_aton." AND data_type=1 AND data_id=".$port);

if (!$old_crc_info) { $clean_start=1; }

my $cur_crc_info;

if (!$old_crc_info or !defined $old_crc_info->{data_value1}) { $old_crc_info->{data_value1}=0; }
if (!$old_crc_info or !defined $old_crc_info->{data_id}) { $old_crc_info->{data_id}=$port; }
if (!$old_crc_info or !defined $old_crc_info->{data_type}) { $old_crc_info->{data_type}=1; }
if (!$old_crc_info or !$old_crc_info->{changed}) { $old_crc_info->{changed} = $start_time - 600; }
if (!$old_crc_info or !$old_crc_info->{ip}) { $old_crc_info->{ip}=$ip_aton; }

if ($clean_start) { insert_record($dbh,"netdevices",$old_crc_info); }

$cur_crc_info->{data_id} = $port;
$cur_crc_info->{ip} = $ip_aton;
$cur_crc_info->{data_type} = 1;
$cur_crc_info->{changed} = $start_time;

$cur_crc_info->{data_value1} =get_snmp_crc($port);

update_record($dbh,"netdevices",$cur_crc_info,"ip=".$ip_aton." AND data_type=1 AND data_id=".$port);
$dbh->disconnect;

my $diff_crc = $cur_crc_info->{data_value1} - $old_crc_info->{data_value1};

my $time_diff = $cur_crc_info->{changed} - $old_crc_info->{changed};
if (!$time_diff) { $time_diff = 600; }

my $perf="ErrSpeed=%s;0;0;0;10000000;";

my $speed_crc = int($diff_crc/$time_diff/60);

if ($speed_crc>0 and $diff_crc >0 and $diff_crc >= $err_step) {
    printf("CRIT: CRC error found! Speedup $speed_crc by minute!|".$perf."\n",$speed_crc);
    exit $RET_CRITICAL;
    }

printf("OK: Errors not found. |".$perf."\n",0);

exit $RET_OK;
