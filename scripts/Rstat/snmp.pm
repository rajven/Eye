package Rstat::snmp;

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use utf8;
use strict;
use English;
use FindBin '$Bin';
use lib "$Bin";
use base 'Exporter';
use vars qw(@EXPORT @ISA);
use Data::Dumper;
use Rstat::config;
use Rstat::main;
use Net::SNMP;

@ISA = qw(Exporter);
@EXPORT = qw(
snmp_set_int
snmp_get_request
get_arp_table
get_fdb_table
get_mac_table
get_vlan_at_port
get_switch_vlans
get_snmp_ifindex
get_ifmib_index_table
get_interfaces
get_router_state
snmp_get_req
snmp_get_oid
snmp_walk_oid
table_callback
$ifAlias
$ifName
$ifDescr
$ifIndex
$ifIndex_map
$arp_oid
$ipNetToMediaPhysAddress
$fdb_table_oid
$fdb_table_oid2
$cisco_vlan_oid
$dot1qPortVlanEntry
$fdb_table;
$snmp_timeout
);


BEGIN
{

our $ifAlias      ='.1.3.6.1.2.1.31.1.1.1.18';
our $ifName       ='.1.3.6.1.2.1.31.1.1.1.1';
our $ifDescr      ='.1.3.6.1.2.1.2.2.1.2';
our $ifIndex      ='.1.3.6.1.2.1.2.2.1.1';
our $ifIndex_map  ='.1.3.6.1.2.1.17.1.4.1.2';

#RFC1213::atPhysAddress
our $arp_oid      ='.1.3.6.1.2.1.3.1.1.2';
#RFC1213::ipNetToMediaPhysAddress
our $ipNetToMediaPhysAddress = '.1.3.6.1.2.1.4.22.1.2';
#RFC1493::dot1dTpFdbTable
our $fdb_table_oid ='.1.3.6.1.2.1.17.4.3.1.2';
#Q-BRIDGE-MIB::dot1qTpFdbPort
our $fdb_table_oid2='.1.3.6.1.2.1.17.7.1.2.2.1.2';
#Q-BRIDGE-MIB::dot1qPortVlanEntry
our $dot1qPortVlanEntry ='.1.3.6.1.2.1.17.7.1.4.5.1.1';
#CISCO-ES-STACK-MIB::
our $cisco_vlan_oid='.1.3.6.1.4.1.9.9.46.1.3.1.1.2';

our $fdb_table;

our $snmp_timeout = 15;

#---------------------------------------------------------------------------------

sub snmp_get_request {
my $ip = shift;
my $oid = shift;
my $community = shift || $snmp_default_community;
my $port = shift || '161';
my $snmp_version = shift || '2';
my ($session, $error) = Net::SNMP->session(
   -hostname  => $ip,
   -community => $community,
   -port      => $port,
   -version   => $snmp_version,
   -timeout   => 5
);
return if (!defined($session));
my $result = $session->get_request( -varbindlist => [$oid]);
$session->close;
return if (!$result->{$oid});
return $result->{$oid};
}

#---------------------------------------------------------------------------------

sub snmp_set_int {
my $ip = shift;
my $oid = shift;
my $value = shift;
my $community = shift || $snmp_default_community;
my $port = shift || '161';
my $snmp_version = shift || '2';

my ($session, $error) = Net::SNMP->session(
   -hostname  => $ip,
   -community => $community,
   -port      => $port,
   -version   => $snmp_version,
   -timeout   => $snmp_timeout
);
return if (!defined($session));
my $result = $session->set_request( -varbindlist => [$oid,INTEGER,$value]);
$session->close;
return $result->{$oid};
}

#-------------------------------------------------------------------------------------

sub get_arp_table {
    my ($host,$community,$version) = @_;
#    return if (!HostIsLive($host));
    my $port = 161;
    my $timeout = 5;
    if (!$version) { $version='2'; }

    ### open SNMP session
    my ($snmp_session, $error) = Net::SNMP->session( -hostname  => $host, -community => $community , -version=>$version, -timeout   => $snmp_timeout);
    return if (!defined($snmp_session));
    $snmp_session->translate([-all]);

    my $arp;
    my $arp_table1 = $snmp_session->get_table($arp_oid);
    my $arp_table2 = $snmp_session->get_table($ipNetToMediaPhysAddress);

    if ($arp_table1) {
        foreach my $row (keys(%$arp_table1)) {
        my ($mac_h) = unpack("H*",$arp_table1->{$row});
        next if (!$mac_h or $mac_h eq '000000000000' or $mac_h eq 'ffffffffffff');
        my $mac;
        if (length($mac_h)==12) { $mac=lc $mac_h; }
        next if (!$mac);
        $row=trim($row);
        my $ip;
        if ($row=~/\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})$/) { $ip=$1.".".$2.".".$3.".".$4; }
        next if (!$ip);
        $arp->{$ip}=$mac;
        };
    }

    if ($arp_table2) {
        foreach my $row (keys(%$arp_table2)) {
        my ($mac_h) = unpack("H*",$arp_table2->{$row});
        next if (!$mac_h or $mac_h eq '000000000000' or $mac_h eq 'ffffffffffff');
        my $mac;
        if (length($mac_h)==12) { $mac=lc $mac_h; }
        next if (!$mac);
        $row=trim($row);
        my $ip;
        if ($row=~/\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})$/) { $ip=$1.".".$2.".".$3.".".$4; }
        next if (!$ip);
        $arp->{$ip}=$mac;
        };
    }

    return $arp;
}

#-------------------------------------------------------------------------------------

sub get_ifmib_index_table {
my $ip = shift;
my $community = shift;
my $version = shift;
my $ifmib_map;

my $is_mikrotik = snmp_get_request($ip, '.1.3.6.1.2.1.9999.1.1.1.1.0', $community, 161, $version);
my $mk_ros_version = 0;

if ($is_mikrotik=~/MikroTik/i) {
    my $mikrotik_version = snmp_get_request($ip, '.1.0.8802.1.1.2.1.3.4.0', $community, 161, $version);
    $mk_ros_version = 6491;
    #"MikroTik RouterOS 6.46.8 (long-term) CRS326-24S+2Q+"
    if ($mikrotik_version =~/RouterOS\s+(\d)\.(\d{1,3})\.(\d{1,3})\s+/) {
        $mk_ros_version = $1*1000 + $2*10 + $3;
        }
    }

if (!$mk_ros_version or $mk_ros_version > 6468) {
    my $index_map_table =  snmp_get_oid($ip, $community, $ifIndex_map, $version);
    if (!$index_map_table) { $index_map_table =  snmp_walk_oid($ip, $community, $ifIndex_map, $version); }
    if ($index_map_table) {
        foreach my $row (keys(%$index_map_table)) {
            my $port_index = $index_map_table->{$row};
            next if (!$port_index);
                my $value;
            if ($row=~/\.([0-9]{1,10})$/) { $value = $1; }
            next if (!$value);
            $ifmib_map->{$value}=$port_index;
            }
        }
    }

if (!$ifmib_map) {
    my $index_table =  snmp_get_oid($ip, $community, $ifIndex, $version);
    if (!$index_table) { $index_table =  snmp_walk_oid($ip, $community, $ifIndex, $version); }
    foreach my $row (keys(%$index_table)) {
            my $port_index = $index_table->{$row};
            next if (!$port_index);
        my $value;
        if ($row=~/\.([0-9]{1,10})$/) { $value = $1; }
            next if (!$value);
        $ifmib_map->{$value}=$value;
        };
    }
return $ifmib_map;
}

#-------------------------------------------------------------------------------------

sub get_mac_table {
    my ($host,$community,$oid,$version,$index_map) = @_;
    my $port = 161;
    my $timeout = 5;
    if (!$version) { $version='2'; }
    my $fdb;
    #need for callback
    $fdb_table=$oid;
    my $fdb_table1 = snmp_get_oid($host,$community,$oid,$version);
    if (!$fdb_table1) { $fdb_table1=snmp_walk_oid($host,$community,$oid,$version,undef); }
    if ($fdb_table1) {
        foreach my $row (keys(%$fdb_table1)) {
                my $port_index = $fdb_table1->{$row};
            next if (!$port_index);
                my $mac;
                if ($row=~/\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})$/) {
                    $mac=sprintf "%02x%02x%02x%02x%02x%02x",$1,$2,$3,$4,$5,$6;
                    }
            next if (!$mac);
            if ($index_map and exists $index_map->{$port_index}) { $port_index = $index_map->{$port_index}; }
                $fdb->{$mac}=$port_index;
                };
        return $fdb;
            }
}

#-------------------------------------------------------------------------------------

sub get_fdb_table {
    my ($host,$community,$version,$iflist) = @_;
#    return if (!HostIsLive($host));
    my $port = 161;
    my $timeout = 5;
    if (!$version) { $version='2'; }

    my $ifindex_map = get_ifmib_index_table($host,$community,$version);
    my $fdb1=get_mac_table($host,$community,$fdb_table_oid,$version,$ifindex_map);
    my $fdb2=get_mac_table($host,$community,$fdb_table_oid2,$version);

    my $fdb3;

    if ($fdb2 and $iflist) {
        foreach my $mac (keys %$fdb2) {
            if (exists $iflist->{$fdb2->{$mac}}) { $fdb3->{$mac}=$iflist->{$fdb2->{$mac}}; }
            }
        }

    my $fdb;
    if ($fdb1 and !$fdb3) { $fdb = $fdb1; }
    if (!$fdb1 and $fdb3) { $fdb = $fdb3; }
    if ($fdb1 and $fdb3) { $fdb = { %$fdb1,%$fdb3 }; }

    #maybe cisco?!
    if (!$fdb) {
        my $vlan_table=snmp_get_oid($host,$community,$cisco_vlan_oid,$version);
        if (!$vlan_table) { $vlan_table=snmp_walk_oid($host,$community,$cisco_vlan_oid,$version); }
        #fuck!
        if (!$vlan_table) { return; }
        my %fdb_vlan;
            foreach my $vlan_oid (keys %$vlan_table) {
                next if (!$vlan_oid);
                my $vlan_id;
                if ($vlan_oid=~/\.([0-9]{1,4})$/) { $vlan_id=$1; }
                next if (!$vlan_id);
                next if ($vlan_id>1000 and $vlan_id<=1009);
                $fdb_vlan{$vlan_id}=get_mac_table($host,$community.'@'.$vlan_id,$fdb_table_oid,$version,$ifindex_map);
                if (!$fdb_vlan{$vlan_id}) { $fdb_vlan{$vlan_id}=get_mac_table($host,$community.'@'.$vlan_id,$fdb_table_oid2,$version,$ifindex_map); }
                }
            foreach my $vlan_id (keys %fdb_vlan) {
                next if (!exists $fdb_vlan{$vlan_id});
                if (defined $fdb_vlan{$vlan_id}) {
                        my %tmp=%{$fdb_vlan{$vlan_id}};
                        foreach my $mac (keys %tmp) {
                                next if (!$mac);
                                $fdb->{$mac}=$tmp{$mac};
                                }
                    }
            }
        }
    return $fdb;
}

#-------------------------------------------------------------------------------------

sub get_vlan_at_port {
    my ($host,$community,$version,$port_index) = @_;
    my $port = 161;
    my $timeout = 5;
    if (!$version) { $version='2'; }
    my $vlan_oid=$dot1qPortVlanEntry.".".$port_index;
#    print "$host,$community,$vlan_oid,$version\n";
    my $vlan = snmp_get_req($host,$community,$vlan_oid,$version);
    return "1" if (!$vlan);
    return "1" if ($vlan=~/noSuchObject/i);
    return "1" if ($vlan=~/noSuchInstance/i);
    return $vlan;
}

#-------------------------------------------------------------------------------------

sub get_switch_vlans {
    my ($host,$community,$version) = @_;
    my $port = 161;
    my $timeout = 5;
    if (!$version) { $version='2'; }
    my $result;
    #need for callback
    my $vlan_table = snmp_get_oid($host,$community,$dot1qPortVlanEntry,$version);
    if (!$vlan_table) { $vlan_table=snmp_walk_oid($host,$community,$dot1qPortVlanEntry,$version); }
    if ($vlan_table) {
        foreach my $vlan_oid (keys %$vlan_table) {
            if ($vlan_oid=~/\.([0-9]*)$/) { $result->{$1} = $vlan_table->{$vlan_oid}; }
            }
        }
    return $result;
}

#-------------------------------------------------------------------------------------

sub get_snmp_ifindex {
    my ($host,$community,$snmp) = @_;
    ### open SNMP session
    my ($snmp_session, $error) = Net::SNMP->session( -hostname  => $host, -community => $community, -version => $snmp, -timeout => 5);
    return if (!defined($snmp_session));
    my $if_index = $snmp_session->get_table($ifIndex);
    my $result;
    foreach my $row (keys(%$if_index)) {
        my $value = $if_index->{$row};
        $row=~s/^$ifIndex\.//;
        $result->{$row}=$value;
        };
    return $result;
}

#-------------------------------------------------------------------------------------

sub get_interfaces {
    my ($host,$community,$snmp,$skip_empty) = @_;
#    return if (!HostIsLive($host));
    my $port = 161;
    ### open SNMP session
    my ($snmp_session, $error) = Net::SNMP->session( -hostname  => $host, -community => $community, -version => $snmp, -timeout => $snmp_timeout );
    return if (!defined($snmp_session));
    $snmp_session->translate([-timeticks]);
    my $if_name = $snmp_session->get_table($ifName);
    my $if_alias = $snmp_session->get_table($ifAlias);
    my $if_descr = $snmp_session->get_table($ifDescr);
    my $if_index = $snmp_session->get_table($ifIndex);
    my $dev_cap;

    foreach my $row (keys(%$if_index)) {
    my $index = $if_index->{$row};
    next if ($if_name->{$ifName.".".$index} =~/^lo/i);
    next if ($if_name->{$ifName.".".$index} =~/^dummy/i);
    next if ($if_name->{$ifName.".".$index} =~/^enet/i);
    next if ($if_name->{$ifName.".".$index} =~/^Nu/i);
#    next if ($if_name->{$ifName.".".$index} =~/^Po/i);
    my $ifc_alias;
    $ifc_alias=$if_alias->{$ifAlias.".".$index} if ($if_alias->{$ifAlias.".".$index});
    my $ifc_name;
    $ifc_name=$if_name->{$ifName.".".$index} if ($if_name->{$ifName.".".$index});
    my $ifc_desc;
    $ifc_desc=$if_descr->{$ifDescr.".".$index} if ($if_descr->{$ifDescr.".".$index});

    $dev_cap->{$index}->{alias}=$ifc_alias if ($ifc_alias);
    $dev_cap->{$index}->{name}=$ifc_name if ($ifc_name);
    $dev_cap->{$index}->{desc}=$ifc_desc if ($ifc_desc);
    $dev_cap->{$index}->{index} = $index;
    };
    return $dev_cap;
}

#-------------------------------------------------------------------------------------

sub get_router_state {
    my ($host,$community,$snmp,$skip_empty) = @_;
#    return if (!HostIsLive($host));
    my $port = 161;
    ### open SNMP session
    my ($snmp_session, $error) = Net::SNMP->session( -hostname  => $host, -community => $community, -version => $snmp, -timeout => $snmp_timeout );
    return if (!defined($snmp_session));
    $snmp_session->translate([-timeticks]);
    my $router_status = $snmp_session->get_table("1.3.6.1.4.1.10.1");
    return ($router_status);
}

#-------------------------------------------------------------------------------------

sub snmp_get_req {
my ($host,$community,$oid,$version) = @_;
#return if (!HostIsLive($host));
if (!$version) { $version='2'; }
### open SNMP session
my ($snmp_session, $error) = Net::SNMP->session( -hostname  => $host, -community => $community , -version=>$version, -timeout => $snmp_timeout );
return if (!defined($snmp_session));
$snmp_session->translate([-timeticks]);
my $result = $snmp_session->get_request(-varbindlist => [$oid]) or return;
$snmp_session->close();
return $result->{$oid};
}

#-------------------------------------------------------------------------------------

sub snmp_get_oid {
my ($host,$community,$oid,$version) = @_;
#return if (!HostIsLive($host));
if (!$version) { $version='2'; }
### open SNMP session
my ($snmp_session, $error) = Net::SNMP->session( -hostname  => $host, -community => $community , -version=>$version , -timeout     => $snmp_timeout, );
return if (!defined($snmp_session));
$snmp_session->translate([-timeticks]);
my $table = $snmp_session->get_table($oid);
$snmp_session->close();
return $table;
}

#-------------------------------------------------------------------------------------

sub snmp_walk_oid {

my $host = shift;
my $community = shift;
my $oid = shift;
my $version = shift || '2c';

#return if (!HostIsLive($host));

my ($session, $error) = Net::SNMP->session(
      -hostname    => $host,
      -community   => $community,
      -nonblocking => 1,
      -translate   => [-octetstring => 0],
      -version     => $version,
      -timeout     => $snmp_timeout,
   );

if (!defined $session) {
      printf "ERROR: %s.\n", $error;
      return;
}

my %table; # Hash to store the results

my $result = $session->get_bulk_request(
      -varbindlist    => [ $oid ],
      -callback       => [ \&table_callback, \%table ],
      -maxrepetitions => 10,
   );

snmp_dispatcher();
$session->close();

return \%table;
}

#-------------------------------------------------------------------------------------

sub table_callback  {
my ($session, $table) = @_;
my $list = $session->var_bind_list();

if (!defined $list) {
    printf "ERROR: %s\n", $session->error();
    return;
    }

my @names = $session->var_bind_names();
my $next  = undef;

while (@names) {
    $next = shift @names;
    if (!oid_base_match($fdb_table, $next)) { return; }
    $table->{$next} = $list->{$next};
    }

my $result = $session->get_bulk_request( -varbindlist    => [ $next ], -maxrepetitions => 10);

if (!defined $result) {
     printf "ERROR: %s.\n", $session->error();
    }
return;
}

#-------------------------------------------------------------------------------------

1;
}
