#!/usr/bin/perl

use lib "/etc/nagios4/scripts/plugins";
use Net::SNMP;
use Config::Tiny;
use File::Path qw( mkpath );
use POSIX;
use strict;
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
$port_speed->{timestamp}=time();
$port_speed->{counter}=$counter;
$port_speed->{in} = $result_in->{$IN_OID};
$port_speed->{out} = $result_out->{$OUT_OID};
$port_speed->{speed} = $result_speed->{$SPEED_OID};

return $port_speed;
}

my $start_time = time();
my $host_spool_dir = "/var/spool/nagios4/plugins/bandwidth/";
if (!-e "$host_spool_dir") { mkpath( $host_spool_dir, 0, 0770 ); }
my $host_data = $host_spool_dir.'/'.$host;

my $old_info;
my $cur_info;

my $clean_start = 0;

my $host_spool = Config::Tiny->new;
if (-e "$host_data") {
    $host_spool = Config::Tiny->read($host_data, 'utf8' );
    $old_info=$host_spool->{$port};
    } else { $clean_start=1; }

$cur_info=get_snmp_band($port,$counter);
#speed patch for x64 counter
if ($counter eq 64) { $cur_info->{speed}=$cur_info->{speed}*1000; }
#extreme patch
if ($cur_info->{speed} eq 4294967295) { $cur_info->{speed}=10000000; }

$host_spool->{$port} = $cur_info;
$host_spool->write($host_data);

my $perf="IN=%s%%;$warning;$critical OUT=%s%%;$warning;$critical";

if ($clean_start or $cur_info->{speed} eq 0) {
    printf("OK: Bandwidth in=%s%% out=%s%% |".$perf."\n",0,0,0,0);
    exit $RET_OK;
    }

my $deltaIn = $cur_info->{in} - $old_info->{in};
my $deltaOut = $cur_info->{out} - $old_info->{out};
my $deltaTime = $cur_info->{timestamp} - $old_info->{timestamp};

my $counter_div = 10;

if ($counter eq 32) { $counter_div = 1; }

my $band_in = ceil((($deltaIn * 8) / $deltaTime) / $cur_info->{speed} / $counter_div);
my $band_out = ceil((($deltaOut * 8) / $deltaTime) / $cur_info->{speed} / $counter_div);

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
