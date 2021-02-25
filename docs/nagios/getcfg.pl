#!/usr/bin/perl -w

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use FindBin '$Bin';
use lib "$Bin";
use strict;
use Time::Local;
use FileHandle;
use Data::Dumper;
use Rstat::config;
use Rstat::main;
use Rstat::mysql;
use Rstat::alliedtelesys;
use Rstat::huawei;
use Net::SSH::Expect;
use Rstat::net_utils;

$|=1;

my $debug = 0;
my %switches;

my $tftp_server="192.168.11.107";

sub backup_allied610 {
my $ip = shift;
my $name = shift || $ip;
my $debug = shift;
return "Fail!" if (!$ip);
return "Fail!" if (!ping($ip));
my $rest_cmd="sh run";
eval {
my $t = login_610($ip,$debug);
telnet_string($t,$rest_cmd);
};
if ($@) { return "Fail!"; } else { return "OK!"; }
}

sub backup_allied8000 {
my $ip = shift;
my $name = shift || $ip;
my $debug = shift;
return "Fail!" if (!$ip);
return "Fail!" if (!ping($ip));
my $rest_cmd="copy running-config tftp://$tftp_server/$name.cfg";
eval {
my $t = login_8000($ip,$debug);
telnet_string($t,$rest_cmd);
};
if ($@) { return "Fail!"; } else { return "OK!"; }
}

sub backup_allied8100 {
my $ip = shift;
my $name = shift || $ip;
my $debug = shift;
return "Fail!" if (!$ip);
return "Fail!" if (!ping($ip));
my $rest_cmd="copy flash tftp $tftp_server boot.cfg";
eval {
my $t = login_8100($ip,$debug);
telnet_string($t,$rest_cmd);
};
if ($@) { return "Fail!"; } else { return "OK!"; }
}

sub backup_huawei {
my $ip = shift;
my $name = shift || $ip;
my $debug = shift;
return "Fail!" if (!$ip);
return "Fail!" if (!ping($ip));
eval {
my $ssh = login_huawei($ip,'admin',$sw_password,$debug);
huawei_run_cmd($ssh,"tftp $tftp_server put vrpcfg.zip $name.zip");
$ssh->close if ($ssh);
};
if ($@) { return "Fail!"; } else { return "OK!"; }
}

sub backup_sw {
my $sw_ip = shift;
return if (!$sw_ip);
print "Backup switch $switches{$sw_ip}->{name} [$sw_ip]: ";
my $ret = "Skip!";
if ($switches{$sw_ip}->{type}=~/huawei/i) { $ret = backup_huawei($sw_ip,$switches{$sw_ip}->{name},$debug); }
if ($switches{$sw_ip}->{type}=~/allied8000/i) { $ret = backup_allied8000($sw_ip,$switches{$sw_ip}->{name},$debug); }
if ($switches{$sw_ip}->{type}=~/allied8100/i) { 
    $ret = backup_allied8100($sw_ip,$switches{$sw_ip}->{name},$debug); 
    if ($ret) { rename "/var/lib/tftpboot/boot.cfg","/var/lib/tftpboot/$switches{$sw_ip}->{name}".".cfg"; }
    }
if ($switches{$sw_ip}->{type}=~/allied610/i) { $ret = backup_allied610($sw_ip,$switches{$sw_ip}->{name},$debug); }
print "$ret\n";
}

$debug=1;

my @ret=get_custom_records($dbh,'Select device_name as name, device_model as model, vendor_id, ip from devices where deleted=0 and (vendor_id=3 or vendor_id=8)');
foreach my $dev (@ret) {
$switches{$dev->{ip}}{ip}=$dev->{ip};
$switches{$dev->{ip}}{model}=$dev->{model};
$switches{$dev->{ip}}{name}=$dev->{name};
if ($dev->{vendor_id}==3) { $switches{$dev->{ip}}{type}='huawei'; }
if ($dev->{vendor_id}==8) {
    if ($dev->{model}=~/8100/) { $switches{$dev->{ip}}{type}='allied8100'; }
    if ($dev->{model}=~/8000/) { $switches{$dev->{ip}}{type}='allied8000'; }
    if ($dev->{model}=~/x610/) { $switches{$dev->{ip}}{type}='allied610'; }
    if ($dev->{model}=~/x210/) { $switches{$dev->{ip}}{type}='allied610'; }
    }
}

if ($ARGV[0]) {
    backup_sw($ARGV[0]);
    } else {
    foreach my $sw_ip (sort keys %switches) { 
	next if (!exists $switches{$sw_ip}{type});
	backup_sw($sw_ip); 
	}
    }

exit 0;
