#!/usr/bin/perl

use lib "/etc/nagios4/scripts/plugins";
use snmp;
use Net::SNMP;
use Config::Tiny;
use File::Path qw( mkpath );
use strict;

my $TIMEOUT = 30;
$SIG{ALRM} = sub { print "ERROR: No response\n"; exit 3; };
alarm($TIMEOUT);

### return codes
my $RET_OK=0;
my $RET_WARNING=1;
my $RET_UNKNOWN=3;
my $RET_CRITICAL=2;

my $time_cache = 1800;

my $err_step = 100;


if (scalar @ARGV <= 2) {
    print "Usage: $0 <host> <snmp_string> <port> <32|64> [warning] [critical]\n";
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

sub get_snmp_crc {

my $CRC_OID = ".1.3.6.1.2.1.2.2.1.14";

my $session = init_snmp($host,$snmp);

my $result = $session->get_table($CRC_OID);
$session->close;

my %port_crc;
foreach my $row (keys (%$result)) {
next if ($row !~ /$CRC_OID/);
my $crc_value = $result->{$row};
$row =~ s/$CRC_OID//;
$row =~ s/^(\.*)//;
$port_crc{$row}=$crc_value;
}

return \%port_crc;
}

my $time_cache_min = int($time_cache/60);

my $start_time = time();
my $host_spool_dir = "/var/spool/nagios4/plugins/crc/";

if (!-e "$host_spool_dir") { mkpath( $host_spool_dir, 0, 0770 ); }

my $host_data = $host_spool_dir.$host;

my $old_crc_info;
my $cur_crc_info;

my $need_rescan = 0;

if (-e "$host_data") {
    my $host_spool = Config::Tiny->new;
    $host_spool = Config::Tiny->read($host_data, 'utf8' );
    my $old_time=$host_spool->{_}->{timestamp};
    foreach my $port (keys %{$host_spool->{crc_data}}) { $old_crc_info->{$port} = $host_spool->{crc_data}->{$port}; }
    if (($start_time - $old_time) >=$time_cache) { $need_rescan = 1; } else { $need_rescan = 0; }
    } else { $need_rescan = 1; }

if (!$old_crc_info->{$port}) { $old_crc_info->{$port}=0; }

if ($need_rescan) {
    $cur_crc_info=get_snmp_crc;
    my $host_spool = Config::Tiny->new;
    $host_spool->{_}->{timestamp}=$start_time;
    $host_spool->{crc_data} = $cur_crc_info;
    $host_spool->write($host_data);
    } else { $cur_crc_info = $old_crc_info; }

my $diff_crc = $cur_crc_info->{$port} - $old_crc_info->{$port};

my $perf="ErrSpeed=%s;0;0;0;10000000;";

if ($diff_crc >0 and $diff_crc >= $err_step) {
    my $speed_crc = int($diff_crc/$time_cache_min);
    printf("CRIT: CRC error found! Speedup $speed_crc by minute!|".$perf."\n",$speed_crc);
    exit $RET_CRITICAL;
    }

printf("OK: Errors not found. |".$perf."\n",0);

exit $RET_OK;
