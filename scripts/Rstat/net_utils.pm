package Rstat::net_utils;

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use strict;
use English;
use FindBin qw($Bin);
use lib "$Bin";
use base 'Exporter';
use vars qw(@EXPORT @ISA);
use FileHandle;
use POSIX;
use Rstat::config;
use Rstat::main;
use Net::Ping;
use Net::Patricia;
use NetAddr::IP;
use Net::DNS;

our @ISA = qw(Exporter);
our @EXPORT = qw(
$RFC1918
$Special_Nets
$Loopback
IPv4Numeric
is_gate_valid
CheckIP
GetDhcpRange
GetIpRange
GetIP
GetSubNet
is_ipip_valid
is_ip_valid
print_net
ping
HostIsLive
InitSubnets
mac_simplify
isc_mac_simplify
mac_cisco
mac2dec
mac_splitted
ResolveNames
);

BEGIN
{

#local nets
our $RFC1918;
our $Special_Nets;
our $Loopback;

#------------------------------------------------------------------------------------------------------------

sub ResolveNames {
my $hostname = shift;
my @result=();
my $res = Net::DNS::Resolver->new;
$res->nameservers($dns_server);
my $query = $res->search($hostname);
my $result;
if ($query) {
    foreach my $rr ($query->answer) {
        if ($rr->type eq "A") { push(@result,$result = $rr->address); }
	}
    }
return (@result);
}

#------------------------------------------------------------------------------------------------------------

sub IPv4Numeric {
my $ip = shift;
return 0 if (!$ip);
my $net=NetAddr::IP->new($ip);
return $net->numeric();
}

#------------------------------------------------------------------------------------------------------------

sub is_gate_valid {
my $ip_str = trim($_[0]);
if ($ip_str =~ /([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})(\/[0-9]{1,2}){0,1}/) {
        my $mask = $5;
        return 0 if($1 > 255 || $2 > 255 || $3 > 255 || $4 > 255);
        $mask =~s/\/// if ($mask);
        $mask = 32 if (!$mask);
        return 0 if ($mask > 31);
        if ($Special_Nets->match_string($ip_str)) {  log_error("$ip_str in illegal net range"); return 0; };
        return 1;
        }
return 0;
}

#---------------------------------------------------------------------------------------------------------

sub CheckIP {
my $ip_str = shift;
return 0 if (!$ip_str);
$ip_str = trim($ip_str);
if ($ip_str =~ /([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})(\/[0-9]{1,2}){0,1}/) {
        my $mask = $5;
        return 0 if($1 > 255 || $2 > 255 || $3 > 255 || $4 > 255);
        $mask =~s/\/// if ($mask);
        $mask = 32 if (!$mask);
        my $ip = NetAddr::IP->new($ip_str)->addr();
        if ($mask<32) { $ip = $ip."/".$mask; }
        return $ip;
        }
return 0;
}

#--------------------------------------------------------------------------------------------------------

sub GetDhcpRange {
my $gate_ip = trim($_[0]);
my $mask;
if ($gate_ip =~ /([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})(\/[0-9]{1,2}){0,1}/) {
        $mask = $5;
        return if($1 > 255 || $2 > 255 || $3 > 255 || $4 > 255);
        $mask =~s/\/// if ($mask);
        $mask = 32 if ((!$mask) or ($mask >32));
        } else { return; }
my $gate = NetAddr::IP->new($gate_ip)->addr();
my $net=NetAddr::IP->new($gate."/".$mask);
my %dhcp;
$dhcp{gate} = $gate;
$dhcp{network} = $net->network()->addr();
$dhcp{broadcast} = $net->broadcast()->addr();
$dhcp{mask} = $net->mask();
$dhcp{masklen} = $net->masklen();
$dhcp{first_ip} = $net->first()->addr();
$dhcp{count} = $net->broadcast() - $net->network() - 2;
if ($mask < 32) {
    if ($dhcp{first_ip} eq $dhcp{gate}) {
	$dhcp{first_ip} = $net->nth(1)->addr();
	}
    $dhcp{last_ip} = $net->last()->addr();
    if ($dhcp{last_ip} eq $dhcp{gate}) {
	$dhcp{last_ip} =  $net->nth($dhcp{count}-1)->addr();
	}
    }
return \%dhcp;
}

#--------------------------------------------------------------------------------------------------------

sub GetIpRange {
my $gate_ip = trim($_[0]);
my $mask = 30;
if ($gate_ip =~ /([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})(\/[0-9]{1,2}){0,1}/) {
        $mask = $5;
        return if($1 > 255 || $2 > 255 || $3 > 255 || $4 > 255);
        $mask =~s/\/// if ($mask);
        $mask = 30 if ((!$mask) or ($mask >30));
        } else { return; }
my $gate = NetAddr::IP->new($gate_ip)->addr();
my $net=NetAddr::IP->new($gate."/".$mask);
my @range=();
if ($mask >=29) {
    my $ip_count = $net->broadcast() - $net->network() - 2;
    for (my $index = 1; $index<=$ip_count; $index++) {
        my $ip = $net->nth($index)->addr();
        next if ($ip eq $gate);
        push(@range,$ip."/32");
        }
    } else {
        push(@range,$net->network()->addr()."/".$mask);
    }
return \@range;
}

#--------------------------------------------------------------------------------------------------------

sub GetIP {
my $ip_str = trim($_[0]);
if ($ip_str =~ /([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})(\/[0-9]{1,2}){0,1}/) {
        return if($1 > 255 || $2 > 255 || $3 > 255 || $4 > 255);
        return NetAddr::IP->new($ip_str)->addr();
        }
return;
}

#---------------------------------------------------------------------------------------------------------

sub GetSubNet {
my $ip_str = trim($_[0]);
my $mask = 32;
if ($ip_str =~ /([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})(\/[0-9]{1,2}){0,1}/) {
        $mask = $5;
        return if($1 > 255 || $2 > 255 || $3 > 255 || $4 > 255);
        $mask =~s/\/// if ($mask);
        $mask = 32 if (!$mask);
        }
        else { return; }
my $ip = NetAddr::IP->new($ip_str)->network()->addr();
return $ip."/".$mask;
}

#---------------------------------------------------------------------------------------------------------

sub is_ipip_valid {
my $ip1 = shift;
my $ip2 = shift;
my $lo1 = 0;
my $lo2 = 0;
my $ok = 0;
eval {
if ($ip1) {
    if ($ip1 ne "0/0") {
        $lo1 = $Loopback->match_string($ip1);
        $lo1 = 1 if ($lo1);
        }
    };
if ($ip2) {
    if ($ip2 ne "0/0") {
        $lo2 = $Loopback->match_string($ip1);
        $lo2 = 1 if ($lo2);
        }
    };
if (!$lo1) { $lo1=0; };
if (!$lo2) { $lo2=0; };
$ok = ((($ip1 ne "0/0") or ($ip2 ne "0/0")) and ($lo1==0) and ($lo2==0));
};
return $ok;
}

#---------------------------------------------------------------------------------------------------------

sub is_ip_valid {
my $ip_str = trim($_[0]);
if ($ip_str =~ /([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})(\/[0-9]{1,2}){0,1}/) {
        my $mask = $5;
        return 0 if($1 > 255 || $2 > 255 || $3 > 255 || $4 > 255);
        $mask =~s/\/// if ($mask);
        $mask = 32 if (!$mask);
        return 0 if ($mask > 32);
        if ($Special_Nets->match_string($ip_str)) {  log_error("$ip_str in illegal net range"); return 0; };
        return 1;
        }
return 0;
}

#---------------------------------------------------------------------------------------------------------

sub print_net {

my $ip = shift;
my $max_mask = shift || 26;
return if (!$ip);

my @result = ();

my $user_ip=NetAddr::IP->new($ip);
my $netmask = $user_ip->masklen();
return if ($netmask<$max_mask);

my $ip_first = $user_ip->network();
my $ip_last = $user_ip->broadcast();
my $ip_count = $ip_last - $ip_first;

if ($ip_count) {
    for (my $i=0; $i<=$ip_count; $i++) {
        my $ip1 = $ip_first;
        $ip1=~s/\/\d+$//g;
        push(@result,$ip1);
        $ip_first ++;
        }
    } else {
    $ip_first=~s/\/\d+$//g;
    push(@result,$ip_first);
    }
return @result;
}

#---------------------------------------------------------------------------------------------------------

sub ping {
use Net::Ping;
use Time::HiRes;
my ($host,$time) = @_;
my $p = Net::Ping->new();
$p->hires();
my ($ret, $duration, $ip) = $p->ping($host, $time);
$p->close();
$ret ? return 1: return 0;
}

#---------------------------------------------------------------------------------------------------------

sub HostIsLive {
my $host=shift;
my $proto=shift || "tcp";
if ($< eq 0) { $proto="icmp"; }
my $p = Net::Ping->new($proto);
my $ok= $p->ping($host,5);
$p->close();
return $ok;
}

#----------------------------------------------------------------------------------

sub InitSubnets {

$RFC1918 = new Net::Patricia;
#local nets RFC1918
$RFC1918->add_string("192.168.0.0/16");
$RFC1918->add_string("10.0.0.0/8");
$RFC1918->add_string("172.16.0.0/12");

#----------------------------------------------------------------------------------

$Special_Nets = new Net::Patricia;
#"This" Network [RFC1700, page 4]
$Special_Nets->add_string("0.0.0.0/8");

#Public-Data Networks [RFC1700, page 181]
#$Special_Nets->add_string("14.0.0.0/8");
#Cable Television Networks
#$Special_Nets->add_string("24.0.0.0/8");
#Reserved - [RFC1797]
#$Special_Nets->add_string("39.0.0.0/8");
#loopback [RFC1700, page 5]
$Special_Nets->add_string("127.0.0.0/8");
#Reserved
$Special_Nets->add_string("128.0.0.0/16");
#Link Local
$Special_Nets->add_string("169.254.0.0/16");
#Reserved
#$Special_Nets->add_string("191.255.0.0/16");
#Reserved
#$Special_Nets->add_string("192.0.0.0/24");
#Test-Net
#$Special_Nets->add_string("192.0.2.0/24");
#6to4 Relay Anycast [RFC3068]
$Special_Nets->add_string("192.88.99.0/24");
#Network Interconnect Device Benchmark Testing [RFC2544]
#$Special_Nets->add_string("198.18.0.0/15");
#Reserved
#$Special_Nets->add_string("223.255.255.0/24");
#Multicast [RFC3171]
$Special_Nets->add_string("224.0.0.0/4");
#Reserved for Future Use [RFC1700, page 4]
#$Special_Nets->add_string("240.0.0.0/4");

#----------------------------------------------------------------------------------

$Loopback = new Net::Patricia;
#loopback [RFC1700, page 5]
$Loopback->add_string("127.0.0.0/8");
}

#--------------------------------- Utils ------------------------------------------

sub mac_simplify{
my $mac=shift;
return if (!$mac);
$mac=~s/\.//g;
$mac=~s/://g;
$mac=~s/-//g;
$mac=~tr/[A-Z]/[a-z]/;
return $mac;
}

#--------------------------------------------------------------------------------

sub mac_cisco{
my $mac=shift;
return if (!$mac);
$mac=mac_simplify($mac);
$mac=~s/(\S{4})(\S{4})(\S{4})/$1\.$2\.$3/g;
return $mac;
}

#--------------------------------------------------------------------------------

sub mac2dec{
my $mac=shift;
return if (!$mac);
$mac=mac_simplify($mac);
$mac=mac_splitted($mac);
my @m=split(":",$mac);
$mac="";
foreach my $i (@m) { $mac=$mac.".".hex($i); }
$mac=~s/^\.//;
return $mac;
}

#--------------------------------------------------------------------------------

sub isc_mac_simplify{
my $mac=shift;
return if (!$mac);
my @mac_array;
foreach my $octet (split(/\:/,$mac)){
my $dec=hex($octet);
push(@mac_array,sprintf("%02x",$dec));
}
$mac=join('',@mac_array);
return $mac;
}

#--------------------------------------------------------------------------------

sub mac_splitted{
my $mac=shift;
return if (!$mac);
my $ch=shift || ":";
$mac=mac_simplify($mac);
$mac=~s/(\S{2})(\S{2})(\S{2})(\S{2})(\S{2})(\S{2})/$1:$2:$3:$4:$5:$6/g;
if ($ch ne ":") { $mac=~s/\:/$ch/g; }
return $mac;
}


InitSubnets();

1;
}
