#!/usr/bin/perl

use FindBin;
use Net::SNMP;
use POSIX;
use strict;
use lib "$FindBin::/etc/nagios4/scripts/";
use lib "/etc/nagios4/scripts/plugins";
use Nag::mysql;
use Data::Dumper;
use DateTime;
use snmp;

my $TIMEOUT = 30;
$SIG{ALRM} = sub { print "ERROR: No response\n"; exit 3; };
alarm($TIMEOUT);

my $ifSpeed       = '.1.3.6.1.2.1.2.2.1.5';
my $ifInOctets    = '.1.3.6.1.2.1.2.2.1.10';
my $ifOutOctets   = '.1.3.6.1.2.1.2.2.1.16';

my $ifHighSpeed   = '.1.3.6.1.2.1.31.1.1.1.15';
my $ifHCInOctets  = '.1.3.6.1.2.1.31.1.1.1.6';
my $ifHCOutOctets = '.1.3.6.1.2.1.31.1.1.1.10';

### return codes
my $RET_OK=0;
my $RET_WARNING=1;
my $RET_UNKNOWN=3;
my $RET_CRITICAL=2;

if (scalar @ARGV <= 2) {
    print "Run: ".join(';',@ARGV)."\n";
    print "Usage: $0 <host> <snmp_string> <port> <32|64> [warning] [critical]\n";
    print "<snmp_string> => community::version::user::auth::priv\n";
    print "for version 3: community = password\n";
    exit $RET_OK;
    }

my $host = shift @ARGV;
my $snmp_str = shift @ARGV;
my ($community,$version,$user,$auth,$priv) = split(/\:\:/,$snmp_str);

my $snmp;
$snmp->{version} = $version || '2';
$snmp->{timeout} = 30;
$snmp->{community} = $community || 'public';
$snmp->{user} = $user || 'public';
$snmp->{auth} = $auth || 'sha1';
$snmp->{priv} = $priv || 'aes';

my $port = shift @ARGV;

my $counter = shift @ARGV || 64;
my $warning = shift (@ARGV) || 80;
my $critical = shift (@ARGV) || 90;

###################################################

sub get_snmp_band {
my $port = shift;
my $counter = shift;

my $IN_OID = $ifHCInOctets.".".$port;
my $OUT_OID = $ifHCOutOctets.".".$port;
my $SPEED_OID = $ifHighSpeed.".".$port;

if ($counter eq 32) {
    $IN_OID = $ifInOctets.".".$port;
    $OUT_OID = $ifOutOctets.".".$port;
    $SPEED_OID = $ifSpeed.".".$port;
    }

my $session = init_snmp($host,$snmp);

my $result_in = $session->get_request( -varbindlist => [$IN_OID]);
my $result_out = $session->get_request( -varbindlist => [$OUT_OID]);
my $result_speed = $session->get_request( -varbindlist => [$SPEED_OID]);
$session->close;

my $port_speed;
$port_speed->{data_value4} = $counter;
$port_speed->{data_value1} = $result_in->{$IN_OID} || 0;
$port_speed->{data_value2} = $result_out->{$OUT_OID} || 0;
$port_speed->{data_value3} = $result_speed->{$SPEED_OID} || 10000000;

return $port_speed;
}

###################################################

my $start = DateTime->now(time_zone => 'local');

my $start_time = $start->epoch;

my $old_info;
my $cur_info;

my $clean_start = 0;

my $dbh = init_db();

if (!$dbh) {
    print("ERROR connect to database\n");
    exit $RET_UNKNOWN;
    }

my $ip_aton = StrToIp($host);

my $old_info = get_record_sql($dbh,"SELECT * FROM netdevices WHERE ip=".$ip_aton." AND data_type=0 AND data_id=".$port);

#data_value1 - in
#data_value2 - out
#data_value3 - speed

if (!$old_info) { $clean_start=1; }

if (!$old_info or !defined $old_info->{data_value1}) { $old_info->{data_value1}=0; }
if (!$old_info or !defined $old_info->{data_value2}) { $old_info->{data_value2}=0; }
if (!$old_info or !defined $old_info->{data_value3}) { $old_info->{data_value3} = 10000000; }
if (!$old_info or !defined $old_info->{data_value4}) { $old_info->{data_value4} = 64; }
if (!$old_info or !defined $old_info->{data_id}) { $old_info->{data_id}=$port; }
if (!$old_info or !defined $old_info->{data_type}) { $old_info->{data_type}=0; }
if (!$old_info or !$old_info->{changed}) { $old_info->{changed} = $start_time - 600; }
if (!$old_info or !$old_info->{ip}) { $old_info->{ip}=$ip_aton; }

if ($clean_start) { insert_record($dbh,"netdevices",$old_info); }

$cur_info=get_snmp_band($port,$counter);

#add record info
$cur_info->{data_id} = $port;
$cur_info->{ip} = $ip_aton;
$cur_info->{data_type} = 0;
$cur_info->{changed} = $start_time;

#speed patch for x64 counter
if ($counter eq 64) { $cur_info->{data_value3}=$cur_info->{data_value3}*1000; }
#extreme patch
if ($cur_info->{data_value3} eq 4294967295) { $cur_info->{data_value3}=10000000; }

update_record($dbh,"netdevices",$cur_info,"ip=".$ip_aton." AND data_type=0 AND data_id=".$port);

$dbh->disconnect;

my $perf="IN=%s%%;$warning;$critical OUT=%s%%;$warning;$critical";

if ($clean_start or $cur_info->{data_value3} <= 1) {
    printf("OK: Bandwidth in=%s%% out=%s%% |".$perf."\n",0,0,0,0);
    exit $RET_OK;
    }

my $deltaIn = $cur_info->{data_value1} - $old_info->{data_value1};
my $deltaOut = $cur_info->{data_value2} - $old_info->{data_value2};
my $deltaTime = $cur_info->{changed} - $old_info->{changed};

if (!$deltaTime) { $deltaTime = 600; }

my $counter_div = 10;

if ($counter eq 32) { $counter_div = 1; }

my $band_in = ceil((($deltaIn * 8) / $deltaTime) / $cur_info->{data_value3} / $counter_div);
my $band_out = ceil((($deltaOut * 8) / $deltaTime) / $cur_info->{data_value3} / $counter_div);

if ($band_in >100) { $band_in = 1; }
if ($band_out >100) { $band_out = 1; }

if ($band_in <$warning and $band_out<$warning) {
    printf("OK: Bandwidth in=%s%% out=%s%%|".$perf."\n",$band_in,$band_out,$band_in,$band_out);
    exit $RET_OK;
    }

if ($band_in >=$critical or $band_out>=$critical) {
    printf("CRIT: Bandwidth in=%s%% out=%s%%|".$perf."\n",$band_in,$band_out,$band_in,$band_out);
    exit $RET_CRITICAL;
    }

if ($band_in >=$warning and $band_in<$critical) {
    printf("WARN: Bandwidth in=%s%% out=%s%%|".$perf."\n",$band_in,$band_out,$band_in,$band_out);
    exit $RET_WARNING;
    }

if ($band_out >=$warning and $band_out<$critical) {
    printf("WARN: Bandwidth in=%s%% out=%s%%|".$perf."\n",$band_in,$band_out,$band_in,$band_out);
    exit $RET_WARNING;
    }

printf("OK: You don't see this! in=%s%% out=%s%% |".$perf."\n",$band_in,$band_out);
exit $RET_OK;
