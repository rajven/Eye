#!/usr/bin/perl

use utf8;
use open ":encoding(utf8)";
use FindBin '$Bin';
use lib "/opt/Eye/scripts";
use DBI;
use File::Basename;
use File::Find;
use File::stat qw(:FIELDS);
use File::Spec::Functions;
use Sys::Hostname;
use DirHandle;
use Time::localtime;
use Fcntl;
use Tie::File;
use Data::Dumper;
use Net::Ping;
use Net::SNMP qw(ticks_to_time TRANSLATE_NONE);
use eyelib::config;
use eyelib::main;
use eyelib::database;
use Fcntl qw(:flock);

open(SELF,"<",$0) or die "Cannot open $0 - $!";
flock(SELF, LOCK_EX|LOCK_NB) or exit 1;

my %hik_snmp_oids=(
'.1.3.6.1.4.1.39165.1.1.0'=>'Model',
'.1.3.6.1.4.1.39165.1.3.0'=>'Firmware',
'.1.3.6.1.4.1.39165.1.5.0'=>'Number',
'.1.3.6.1.4.1.39165.1.6.0'=>'Vendor',
);

my @hik_oids=();
foreach my $oid (keys %hik_snmp_oids) {
push (@hik_oids,$oid);
}

sub scan_ipcam {

my $ip = shift;
my $community = shift;
my $version = shift;

my $result;

#print "$ip $community $version\n";

eval {
my ($session, $error) = Net::SNMP->session(
   -hostname  => $ip,
   -community => $community,
   -port      => 161,
   -version   => $version
);

$session->translate(TRANSLATE_NONE);
my $ret = $session->get_request( -varbindlist => [@hik_oids] );
$result->{text} = 'ip: '.$ip;
foreach my $oid (keys %hik_snmp_oids) {
    $result->{text} = $result->{text}." ".$hik_snmp_oids{$oid}.": ".$ret->{$oid};
    if ($hik_snmp_oids{$oid}=~/Model/i) { $result->{model_name}=$ret->{$oid}; }
    if ($hik_snmp_oids{$oid}=~/Firmware/i) { $result->{firmware}=$ret->{$oid}; }
    }
$result->{text} = trim($result->{text});
};

return $result;
}

my @auth_list=get_records_sql($dbh,'SELECT * FROM User_auth WHERE deleted=0 and nagios=1');

##################################### User auth analyze ################################################

if (scalar(@auth_list)>0) {
    foreach my $auth (@auth_list) {
        next if (!$auth);
        my $ip = $auth->{'ip'};
        $ip =~s/\/\d+$//g;
        #get user
        my $login = get_record_sql($dbh,"SELECT * FROM User_list WHERE id=".$auth->{'user_id'});
        next if ($login->{ou_id} ne 5);

        my $cam_dev = get_record_sql($dbh,"SELECT * FROM devices WHERE user_id=".$auth->{'user_id'});

        my $d_community=undef;
        my $d_version=undef;

        if ($cam_dev) {
            if ($cam_dev->{snmp_version}) { $d_version=$cam_dev->{snmp_version}; }
            if ($cam_dev->{community}) { $d_community=$cam_dev->{community}; }
            }
        if (!$d_version) { $d_version=$config_ref{snmp_default_version}; }
        if (!$d_community) { $d_community=$config_ref{snmp_default_community}; }

        my $snmp_info = scan_ipcam($auth->{ip},$d_community,$d_version);
        if ($snmp_info) {
            if (!$cam_dev) {
                $cam_dev->{user_id}=$auth->{'user_id'};
                $cam_dev->{device_type}=5;
                $cam_dev->{ip}=$auth->{'ip'};
                $cam_dev->{device_name}=$login->{login};
                $cam_dev->{snmp_version}=$config_ref{snmp_default_version};
                $cam_dev->{community}=$config_ref{snmp_default_community};
                my $new_dev_id = insert_record($dbh,"devices",$cam_dev);
                $cam_dev = get_record_sql($dbh,"SELECT * FROM devices WHERE user_id=".$auth->{'user_id'});
                }
            if ($snmp_info->{firmware} and ($cam_dev->{firmware} ne $snmp_info->{firmware})) {
                do_sql($dbh,"UPDATE devices SET firmware='".$snmp_info->{firmware}."' WHERE id=".$cam_dev->{'id'});
                }
            if ($snmp_info->{model_name}) {
                my $model = get_record_sql($dbh,"SELECT id FROM device_models WHERE model_name='".$snmp_info->{model_name}."'");
                if ($model and $cam_dev->{device_model_id} ne $model->{id}) {
                    print "Updated: ".$snmp_info->{text}."\n";
                    do_sql($dbh,"UPDATE devices SET device_model_id=".$model->{id}." WHERE id=".$cam_dev->{'id'});
                    } else {
                    print "OK: ".$snmp_info->{text}."\n";
                    }
                }
            } else { print "Fail: ".$ip."\n"; }
        }
    }

exit;
