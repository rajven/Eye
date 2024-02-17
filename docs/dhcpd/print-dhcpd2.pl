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
use NetAddr::IP;
use Data::Dumper;
use eyelib::config;
use eyelib::main;
use eyelib::mysql;
use eyelib::net_utils;
use File::Basename;
use File::Path;
use utf8;

binmode(STDOUT,':utf8');

setpriority(0,0,19);

my %dhcp_conf;

my $connected = new Net::Patricia;
my $dhcp_networks = new Net::Patricia;

my $int_addr=do_exec('/sbin/ip addr show | grep "scope global"');
foreach my $address (split(/\n/,$int_addr)) {
if ($address=~/inet\s+(.*)\s+brd/i) {
    if ($1) { $connected->add_string($1); }
    }
}

my @subnets=get_records_sql($dbh,'SELECT * FROM subnets WHERE dhcp=1 and office=1 and vpn=0 and hotspot=0 ORDER BY ip_int_start');
foreach my $subnet (@subnets) {
next if (!$subnet->{gateway});
$dhcp_networks->add_string($subnet->{subnet});
my $subnet_name = $subnet->{subnet};
$subnet_name=~s/\/\d+$//g;
$dhcp_conf{$subnet_name}->{first_ip}=IpToStr($subnet->{dhcp_start});
$dhcp_conf{$subnet_name}->{last_ip}=IpToStr($subnet->{dhcp_stop});
$dhcp_conf{$subnet_name}->{relay_ip}=IpToStr($subnet->{gateway});
my $dhcp=GetDhcpRange($subnet->{subnet});
$dhcp_conf{$subnet_name}->{mask}=$dhcp->{mask};
$dhcp_conf{$subnet_name}->{masklen}=$dhcp->{masklen};
$dhcp_conf{$subnet_name}->{gateway}=IpToStr($subnet->{gateway});
$dhcp_conf{$subnet_name}->{network}=$dhcp->{network};
$dhcp_conf{$subnet_name}->{dhcp_lease_time}=$subnet->{dhcp_lease_time}*60;
$dhcp_conf{$subnet_name}->{deny_unknown_clients} = $subnet->{static};
if ($connected->match_string(IpToStr($subnet->{gateway}))) { $dhcp_conf{$subnet_name}->{relay_ip}='direct'; }
}

foreach my $zone (keys %dhcp_conf) {
$dhcp_conf{$zone}->{first_aton} = StrToIp($dhcp_conf{$zone}->{first_ip});
$dhcp_conf{$zone}->{last_aton} = StrToIp($dhcp_conf{$zone}->{last_ip});
for (my $i=$dhcp_conf{$zone}->{first_aton}; $i <= $dhcp_conf{$zone}->{last_aton}; $i++) {
    $dhcp_conf{$zone}->{pool}->{$i}=0;
    }
}

my $dir_name = "/etc/dhcp/eye.d";
my $new_dir = $dir_name.".new";

if (! -d "$dir_name" ) { mkpath($dir_name); }
if (! -d "$new_dir" ) { mkpath($new_dir); }

#get userid list
my $sSQL="SELECT id,ip,ip_int,mac,comments,dns_name FROM User_auth where dhcp=1 and deleted=0 and ou_id !=".$default_user_ou_id." and ou_id !=".$default_hotspot_ou_id." ORDER by ip_int";

my @users = get_records_sql($dbh,$sSQL);

foreach my $row (@users) {
next if (!$row);

next if (!$row);
next if (!$dhcp_networks->match_string($row->{ip}));
next if (!$row->{mac});
next if (!$row->{ip});
next if ($hotspot_networks->match_string($row->{ip}));
my $info = $office_networks->match_string($row->{ip});
next if (!$info);
my $zone_name = $info->{subnet};
$zone_name=~s/(\/\d+)$//;
push(@{$dhcp_conf{$zone_name}->{conf}},"# Data for $row->{id} $row->{dns_name} $row->{comments}");
if ($row->{dns_name}) {
    push(@{$dhcp_conf{$zone_name}->{conf}},"host ".$row->{id}." { hardware ethernet ".$row->{mac}."; fixed-address ".$row->{ip}."; option host-name ".$row->{dns_name}."; }");
    } else {
    push(@{$dhcp_conf{$zone_name}->{conf}},"host ".$row->{id}." { hardware ethernet ".$row->{mac}."; fixed-address ".$row->{ip}."; }");
    }
$dhcp_conf{$zone_name}->{pool}->{$row->{ip_int}} = 1;
}

foreach my $zone (keys %dhcp_conf) {
    my $start_pool = 0;
    for (my $i=$dhcp_conf{$zone}->{first_aton}; $i <= $dhcp_conf{$zone}->{last_aton}; $i++) {
	if (($dhcp_conf{$zone}->{pool}->{$i} or $i==$dhcp_conf{$zone}->{last_aton}) and $start_pool) {
		my $conf_str="range dynamic-bootp ".IpToStr($start_pool)." ".IpToStr($i-1).";";
		push(@{$dhcp_conf{$zone}->{conf}},$conf_str);
		$start_pool = 0;
		}
	if (!$dhcp_conf{$zone}->{pool}->{$i} and !$start_pool) {
		$start_pool = $i;
		}
	}
}

write_to_file($new_dir."/eye.conf","#dynamic generated file");
write_to_file($new_dir."/eye.conf",'shared-network "company" {',1);

foreach my $zone (keys %dhcp_conf) {
my $full_zone_path=$new_dir."/".$zone.".conf";
write_to_file($full_zone_path,$dhcp_conf{$zone}->{conf});
write_to_file($new_dir."/eye.conf",'subnet '.$dhcp_conf{$zone}->{network}.' netmask '.$dhcp_conf{$zone}->{mask}.' {',1);
write_to_file($new_dir."/eye.conf","\toption routers ".$dhcp_conf{$zone}->{gateway}.';',1);
write_to_file($new_dir."/eye.conf","\tmax-lease-time ".$dhcp_conf{$zone}->{dhcp_lease_time}.';',1);
write_to_file($new_dir."/eye.conf","\tdefault-lease-time ".$dhcp_conf{$zone}->{dhcp_lease_time}.';',1);
write_to_file($new_dir."/eye.conf","\tauthoritative;",1);
write_to_file($new_dir."/eye.conf","\tallow duplicates;",1);
write_to_file($new_dir."/eye.conf","\t".'include "'.$full_zone_path.'";',1);
if ($dhcp_conf{$zone}->{deny_unknown_clients}) { write_to_file($new_dir."/eye.conf","\tdeny unknown-clients;",1); }
write_to_file($new_dir."/eye.conf","\t}",1);
}
write_to_file($new_dir."/eye.conf",'}',1);

exit 0;
