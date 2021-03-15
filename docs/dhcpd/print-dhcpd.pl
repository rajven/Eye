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
use Rstat::config;
use Rstat::main;
use Rstat::mysql;
use Rstat::net_utils;
use File::Basename;
use File::Path;

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
if ($connected->match_string(IpToStr($subnet->{gateway}))) { $dhcp_conf{$subnet_name}->{relay_ip}='direct'; } else { $dhcp_conf{$subnet_name}->{relay_ip}=IpToStr($subnet->{gateway}); }
}

foreach my $zone (keys %dhcp_conf) {
$dhcp_conf{$zone}->{first_aton} = StrToIp($dhcp_conf{$zone}->{first_ip});
$dhcp_conf{$zone}->{last_aton} = StrToIp($dhcp_conf{$zone}->{last_ip});
for (my $i=$dhcp_conf{$zone}->{first_aton}; $i <= $dhcp_conf{$zone}->{last_aton}; $i++) {
    $dhcp_conf{$zone}->{pool}->{$i}=0;
    }
}

my $dir_name = "/etc/dhcp/stat";
my $new_dir = $dir_name.".new";

if (! -d "$dir_name" ) { mkpath($dir_name); }
if (! -d "$new_dir" ) { mkpath($new_dir); }

#get userid list
my $sSQL="SELECT id,ip,ip_int,mac,comments FROM User_auth where dhcp=1 and deleted=0 and user_id<>$hotspot_user_id and user_id<>$default_user_id ORDER by ip_int";

my @users = get_records_sql($dbh,$sSQL);

foreach my $row (@users) {
next if (!$row);
next if (!$dhcp_networks->match_string($row->{ip}));
next if ($hotspot_networks->match_string($row->{ip}));
my $info = $office_networks->match_string($row->{ip});
next if (!$info);
next if (!$row->{mac});
next if (!$row->{ip});
$info=~s/(\/\d+)$//;
#push(@{$dhcp_conf{$info}},"host $row->{id} { hardware ethernet $row->{mac}; fixed-address $row->{ip}; }");

my @u_mac_array;
foreach my $octet (split(/:/,$row->{mac})){$octet=~s/0(\S:?)/$1/g;push(@u_mac_array,$octet);}
my $u_mac=join(':',@u_mac_array);

push(@{$dhcp_conf{$info}->{conf}},"# Data for $row->{id}");
push(@{$dhcp_conf{$info}->{conf}},"class \"".$row->{id}."_fixed\" {");
push(@{$dhcp_conf{$info}->{conf}},"match if (");
push(@{$dhcp_conf{$info}->{conf}},"binary-to-ascii(16,8,\":\",substring(hardware,1,6))=\"".$u_mac.'"');
if ($dhcp_conf{$info}->{relay_ip}!~/direct/i) {
    push(@{$dhcp_conf{$info}->{conf}},"and binary-to-ascii(10,8,\".\",packet(24,4))=\"".$dhcp_conf{$info}->{relay_ip}.'"');
    }
push(@{$dhcp_conf{$info}->{conf}},");");
push(@{$dhcp_conf{$info}->{conf}},"}");
push(@{$dhcp_conf{$info}->{conf}},"pool {");
push(@{$dhcp_conf{$info}->{conf}},"range $row->{ip} $row->{ip};");
push(@{$dhcp_conf{$info}->{conf}},"allow members of \"".$row->{id}."_fixed\";");
push(@{$dhcp_conf{$info}->{conf}},"}");
$dhcp_conf{$info}->{pool}->{$row->{ip_int}} = 1;
}

foreach my $zone (keys %dhcp_conf) {
#    print "Analyze zone: $zone\n";
    my $start_pool = 0;
    for (my $i=$dhcp_conf{$zone}->{first_aton}; $i <= $dhcp_conf{$zone}->{last_aton}; $i++) {
	if (($dhcp_conf{$zone}->{pool}->{$i} or $i==$dhcp_conf{$zone}->{last_aton}) and $start_pool) {
		my $conf_str="range ".IpToStr($start_pool)." ".IpToStr($i-1).";";
#		print "$conf_str\n";
		push(@{$dhcp_conf{$zone}->{conf}},$conf_str);
		$start_pool = 0;
		}
	if (!$dhcp_conf{$zone}->{pool}->{$i} and !$start_pool) {
		$start_pool = $i;
		}
	}
}

foreach my $lease_file (keys %dhcp_conf) {
my $full_zone_path=$new_dir."/".$lease_file.".conf";
#my $full_zone_path=$dir_name."/".$lease_file.".conf";
write_to_file($full_zone_path,$dhcp_conf{$lease_file}->{conf});
}

exit 0;
