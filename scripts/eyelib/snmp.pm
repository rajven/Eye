package eyelib::snmp;

#
# Copyright (C) Roman Dmitriev, rnd@rajven.ru
#

use utf8;
use open ":encoding(utf8)";
use strict;
use English;
use FindBin '$Bin';
use lib "/opt/Eye/scripts";
use base 'Exporter';
use vars qw(@EXPORT @ISA);
use Data::Dumper;
use eyelib::config;
use eyelib::main;
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
getIpAdEntIfIndex
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
$ipAdEntIfIndex
$fdb_table_oid
$fdb_table_oid2
$cisco_vlan_oid
$dot1qPortVlanEntry
$fdb_table;
$snmp_timeout
setCommunity
init_snmp
);


BEGIN
{

our $ifAlias        ='.1.3.6.1.2.1.31.1.1.1.18';
our $ifName         ='.1.3.6.1.2.1.31.1.1.1.1';
our $ifDescr        ='.1.3.6.1.2.1.2.2.1.2';
our $ifIndex        ='.1.3.6.1.2.1.2.2.1.1';
our $ifIndex_map    ='.1.3.6.1.2.1.17.1.4.1.2';
our $ipAdEntIfIndex ='.1.3.6.1.2.1.4.20.1.2';

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
my $snmp = shift;

my $session = init_snmp ($ip,$snmp);
return if (!defined($session) or !$session);

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
my $snmp = shift;

my $session = init_snmp ($ip,$snmp,1);
return if (!defined($session) or !$session);

my $result = $session->set_request( -varbindlist => [$oid,INTEGER,$value]);
$session->close;
return $result->{$oid};
}

#-------------------------------------------------------------------------------------

sub get_arp_table {
    my ($host,$snmp) = @_;

    my $session = init_snmp ($host,$snmp,0);
    return if (!defined($session) or !$session);

    $session->translate([-all]);

    my $arp;
    my $arp_table1 = $session->get_table($arp_oid);
    my $arp_table2 = $session->get_table($ipNetToMediaPhysAddress);
    $session->close();

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
my $snmp = shift;
my $ifmib_map;

my $is_mikrotik = snmp_get_request($ip, '.1.3.6.1.2.1.9999.1.1.1.1.0', $snmp);
my $mk_ros_version = 0;

if ($is_mikrotik=~/MikroTik/i) {
    my $mikrotik_version = snmp_get_request($ip, '.1.0.8802.1.1.2.1.3.4.0', $snmp);
    $mk_ros_version = 6491;
    #"MikroTik RouterOS 6.46.8 (long-term) CRS326-24S+2Q+"
    if ($mikrotik_version =~/RouterOS\s+(\d)\.(\d{1,3})\.(\d{1,3})\s+/) {
        $mk_ros_version = $1*1000 + $2*10 + $3;
        }
    }

if (!$mk_ros_version or $mk_ros_version > 6468) {
    my $index_map_table =  snmp_get_oid($ip, $snmp, $ifIndex_map);
    if (!$index_map_table) { $index_map_table =  snmp_walk_oid($ip, $snmp, $ifIndex_map); }
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
    my $index_table =  snmp_get_oid($ip, $snmp, $ifIndex);
    if (!$index_table) { $index_table =  snmp_walk_oid($ip, $snmp, $ifIndex); }
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
    my ($host,$snmp,$oid,$index_map) = @_;
    my $fdb;
    #need for callback
    $fdb_table=$oid;
    my $fdb_table1 = snmp_get_oid($host,$snmp,$oid);
    if (!$fdb_table1) { $fdb_table1=snmp_walk_oid($host,$snmp,$oid,undef); }
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
    my ($host,$snmp) = @_;
    my $ifindex_map = get_ifmib_index_table($host,$snmp);
#    print "IFINDEX_MAP: " . Dumper($ifindex_map);
    my $fdb1=get_mac_table($host,$snmp,$fdb_table_oid,$ifindex_map);
#    print "FDB1: " . Dumper($fdb1);
    my $fdb2=get_mac_table($host,$snmp,$fdb_table_oid2,$ifindex_map);
#    print "FDB2: " . Dumper($fdb1);

    my $fdb;
    #join tables
    if (!$fdb1 and $fdb2) { $fdb = $fdb2; }
    if (!$fdb2 and $fdb1) { $fdb = $fdb1; }
    if ($fdb1 and $fdb2) { $fdb = { %$fdb1,%$fdb2 }; }

    my $snmp_cisco = $snmp;

    #maybe cisco?!
    if (!$fdb) {
        my $vlan_table=snmp_get_oid($host,$snmp,$cisco_vlan_oid);
        if (!$vlan_table) { $vlan_table=snmp_walk_oid($host,$snmp,$cisco_vlan_oid); }
        # just empty
        if (!$vlan_table) { return; }
        #fucking cisco!
        my %fdb_vlan;
        foreach my $vlan_oid (keys %$vlan_table) {
                next if (!$vlan_oid);
                my $vlan_id;
                if ($vlan_oid=~/\.([0-9]{1,4})$/) { $vlan_id=$1; }
                next if (!$vlan_id);
                next if ($vlan_id>1000 and $vlan_id<=1009);
                $snmp_cisco->{'ro-community'} = $snmp->{'ro-community'}.'@'.$vlan_id;
                $fdb_vlan{$vlan_id}=get_mac_table($host,$snmp_cisco,$fdb_table_oid,$ifindex_map);
                if (!$fdb_vlan{$vlan_id}) { $fdb_vlan{$vlan_id}=get_mac_table($host,$snmp_cisco,$fdb_table_oid2,$ifindex_map); }
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
    my ($host,$snmp,$port_index) = @_;
    my $vlan_oid=$dot1qPortVlanEntry.".".$port_index;
    my $vlan = snmp_get_req($host,$snmp,$vlan_oid);
    return "1" if (!$vlan);
    return "1" if ($vlan=~/noSuchObject/i);
    return "1" if ($vlan=~/noSuchInstance/i);
    return $vlan;
}

#-------------------------------------------------------------------------------------

sub get_switch_vlans {
    my ($host,$snmp) = @_;
    my $result;
    #need for callback
    my $vlan_table = snmp_get_oid($host,$snmp,$dot1qPortVlanEntry);
    if (!$vlan_table) { $vlan_table=snmp_walk_oid($host,$snmp,$dot1qPortVlanEntry); }
    if ($vlan_table) {
        foreach my $vlan_oid (keys %$vlan_table) {
            if ($vlan_oid=~/\.([0-9]*)$/) { $result->{$1} = $vlan_table->{$vlan_oid}; }
            }
        }
    return $result;
}

#-------------------------------------------------------------------------------------

sub get_snmp_ifindex {
    my ($host,$snmp) = @_;
    my $session = init_snmp($host,$snmp,0);
    return if (!defined($session) or !$session);

    my $if_index = $session->get_table($ifIndex);
    my $result;
    foreach my $row (keys(%$if_index)) {
        my $value = $if_index->{$row};
        $row=~s/^$ifIndex\.//;
        $result->{$row}=$value;
        };
    $session->close();
    return $result;
}

#-------------------------------------------------------------------------------------

#get ip interfaces
sub getIpAdEntIfIndex {
    my ($host,$snmp) = @_;
    my $session = init_snmp ($host,$snmp,0);
    return if (!defined($session) or !$session);

    $session->translate([-timeticks]);
    my $if_ipaddr = $session->get_table($ipAdEntIfIndex);
    my $l3_list;
    foreach my $row (keys(%$if_ipaddr)) {
        my $ipaddr = $row;
        $ipaddr=~s/$ipAdEntIfIndex\.//;
        $l3_list->{$ipaddr}=$if_ipaddr->{$row};
    }
    $session->close();
    return $l3_list;
}

#-------------------------------------------------------------------------------------

sub get_interfaces {
    my ($host,$snmp,$skip_empty) = @_;

    my $session = init_snmp ($host,$snmp,0);
    return if (!defined($session) or !$session);

    $session->translate([-timeticks]);
    my $if_name = $session->get_table($ifName);
    my $if_alias = $session->get_table($ifAlias);
    my $if_descr = $session->get_table($ifDescr);
    my $if_index = $session->get_table($ifIndex);
    $session->close();
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
    my ($host,$snmp,$skip_empty) = @_;
    my $session = init_snmp ($host,$snmp,0);
    return if (!defined($session) or !$session);
    $session->translate([-timeticks]);
    my $router_status = $session->get_table("1.3.6.1.4.1.10.1");
    $session->close();
    return ($router_status);
}

#-------------------------------------------------------------------------------------

sub snmp_get_req {
my ($host,$snmp,$oid) = @_;
my $session = init_snmp ($host,$snmp,0);
return if (!defined($session) or !$session);
$session->translate([-timeticks]);
my $result = $session->get_request(-varbindlist => [$oid]) or return;
$session->close();
return $result->{$oid};
}

#-------------------------------------------------------------------------------------

sub snmp_get_oid {
my ($host,$snmp,$oid) = @_;
my $port = 161;
my $session = init_snmp ($host,$snmp,0);
return if (!defined($session) or !$session);
$session->translate([-timeticks]);
my $table = $session->get_table($oid);
$session->close();
return $table;
}

#-------------------------------------------------------------------------------------

sub snmp_walk_oid {

my $host = shift;
my $snmp = shift;
my $oid = shift;
my $rw = 'ro';

### open SNMP session
my ($session, $error);

if ($snmp->{version} <= 2) {
        ($session, $error) = Net::SNMP->session(
		-hostname  => $host,
		-community => $snmp->{'ro-community'} ,
		-version   => $snmp->{version},
		-port      => $snmp->{port},
		-timeout   => $snmp->{timeout},
		-nonblocking => 1,
		-translate   => [-octetstring => 0],
		);
	} else {
	($session, $error) = Net::SNMP->session(
		-hostname     => $host,
		-version      => 'snmpv3',
		-username     => $snmp->{$rw.'-user'},
		-authprotocol => $snmp->{'auth-proto'},
		-privprotocol => $snmp->{'priv-proto'},
		-authpassword => $snmp->{$rw.'-password'},
		-privpassword => $snmp->{$rw.'-password'},
		-port         => $snmp->{port},
		-timeout      => $snmp->{timeout},
		-nonblocking  => 1,
		-translate    => [-octetstring => 0],
		);
	}

return if (!defined($session) or !$session);

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

sub init_snmp {

    my ($host,$snmp,$rw) = @_;

    return if (!$host);

    my $community = $snmp->{'ro-community'};
    if (!$rw) { $rw = 'ro' }
	    else {
	    $rw = 'rw';
	    $community = $snmp->{'rw-community'};
	    }

    ### open SNMP session
    my ($session, $error);

    if ($snmp->{version} <=2) {
        ($session, $error) = Net::SNMP->session(
		-hostname  => $host,
		-community => $community ,
		-version   => $snmp->{'version'},
		-port      => $snmp->{port},
		-timeout   => $snmp->{timeout},
		);
	} else {
	($session, $error) = Net::SNMP->session(
		-hostname     => $host,
		-version      => 'snmpv3',
		-username     => $snmp->{$rw.'-user'},
		-authprotocol => $snmp->{'auth-proto'},
		-privprotocol => $snmp->{'priv-proto'},
		-authpassword => $snmp->{$rw.'-password'},
		-privpassword => $snmp->{$rw.'-password'},
		-port         => $snmp->{port},
		-timeout      => $snmp->{timeout},
		);
	}
    if ($error) {
        log_debug("SNMP init-request status for $host:");
        log_debug(Dumper($error));
        }
    return if (!defined($session) or !$session);
    return $session;
}

#-------------------------------------------------------------------------------------

sub setCommunity {
my $device = shift;
$device->{snmp}->{'port'}         = 161;
$device->{snmp}->{'timeout'}      = $snmp_timeout;
$device->{snmp}->{'version'}      = $device->{snmp_version} || '2';
$device->{snmp}->{'ro-community'} = $device->{community} || $snmp_default_community;
$device->{snmp}->{'rw-community'} = $device->{rw_community} || $snmp_default_community;
#snmpv3
$device->{snmp}->{'auth-proto'}   = $device->{snmp3_auth_proto} || 'sha512';
$device->{snmp}->{'priv-proto'}   = $device->{snmp3_priv_proto} || 'aes128';
$device->{snmp}->{'ro-user'}      = $device->{snmp3_user_ro} || '';
$device->{snmp}->{'rw-user'}      = $device->{snmp3_user_rw} || '';
$device->{snmp}->{'ro-password'}  = $device->{snmp3_user_ro_password} || $snmp_default_community;
$device->{snmp}->{'rw-password'}  = $device->{snmp3_user_rw_password} || $snmp_default_community;
}

#-------------------------------------------------------------------------------------

1;
}
