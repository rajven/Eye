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
use eyelib::logconfig;
use Net::SNMP;

@ISA = qw(Exporter);
@EXPORT = qw(
snmp_get_request
snmp_set_int
get_arp_table
snmp_ping
get_ifmib_index_table
get_mac_table
get_fdb_table
get_vlan_at_port
get_switch_vlans
get_snmp_ifindex
getIpAdEntIfIndex
get_interfaces
get_router_state
snmp_get_req
snmp_get_oid
snmp_walk_oid
oid_base_match
snmp_oid_compare
table_callback
init_snmp
setCommunity

$ifAlias
$ifName
$ifDescr
$ifIndex
$ifIndex_map
$ipAdEntIfIndex
$arp_oid
$ipNetToMediaPhysAddress
$fdb_table_oid
$fdb_table_oid2
$dot1qPortVlanEntry
$cisco_vlan_oid
$sysUpTimeInstance
$ifPortStatus
$ifPortAdminStatus
$bgp_prefixes
$bgp_aslist
$hrDeviceDescr
$hrProcessorLoad
$hrMemorySize
$hrStorageIndex
$hrStorageType
$hrStorageDescr
$hrStorageAllocationUnits
$hrStorageSize
$hrStorageUsed
$fdb_table
$snmp_timeout

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

our $sysUpTimeInstance = '1.3.6.1.2.1.1.3.0';
our $ifPortStatus      = '1.3.6.1.2.1.2.2.1.8';
our $ifPortAdminStatus = '1.3.6.1.2.1.2.2.1.7';
our $bgp_prefixes      = '1.3.6.1.4.1.9.9.187.1.2.4.1.1';
our $bgp_aslist        = '1.3.6.1.2.1.15.3.1.9';
our $hrDeviceDescr     = '1.3.6.1.2.1.25.3.2.1.3';
our $hrProcessorLoad   = '1.3.6.1.2.1.25.3.3.1.2';
our $hrMemorySize      = '1.3.6.1.2.1.25.2.2.0';
our $hrStorageIndex    = '1.3.6.1.2.1.25.2.3.1.1';
our $hrStorageType     = '1.3.6.1.2.1.25.2.3.1.2';
our $hrStorageDescr    = '1.3.6.1.2.1.25.2.3.1.3';
our $hrStorageAllocationUnits = '1.3.6.1.2.1.25.2.3.1.4';
our $hrStorageSize     = '1.3.6.1.2.1.25.2.3.1.5';
our $hrStorageUsed     = '1.3.6.1.2.1.25.2.3.1.6';

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

#---------------------------------------------------------------------------------

sub snmp_ping {
    my ($host, $snmp) = @_;
    my @test_oids = (
        '.1.3.6.1.2.1.1.1.0',  # sysDescr (model)
        '.1.3.6.1.2.1.1.3.0',  # sysUpTime (uptime)
        '.1.3.6.1.2.1.1.5.0',  # sysName (hostname)
    );
    my $result;
    my $old_sig_alarm = $SIG{ALRM};
    my $fast_snmp = $snmp;
    $fast_snmp->{timeout}=10;
    $SIG{ALRM} = sub { die "Timeout ${WAIT_TIME}s reached.\n" };
    alarm($WAIT_TIME // 11);
    eval {
        foreach my $oid (@test_oids) {
            log_debug("SNMP ping: trying $oid on $host");
            $result = snmp_get_request($host, $oid, $fast_snmp);
            if (defined $result) {
                log_debug("SNMP ping: SUCCESS $oid = '$result' on $host");
                last;
            }
            log_debug("SNMP ping: failed $oid on $host");
        }
    };
    my $eval_error = $@;
    alarm(0);
    $SIG{ALRM} = $old_sig_alarm;
    my $success = (defined $result && !$eval_error) ? 1 : 0;
    if ($success) {
        log_debug("SNMP ping: $host is UP");
    } else {
        my $reason = $eval_error ? "timeout/error: $eval_error" : "no OID responded";
        log_warning("SNMP ping: $host is DOWN ($reason)");
    }
    return $success;
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
    my $fdb1=get_mac_table($host,$snmp,$fdb_table_oid,$ifindex_map);
    my $fdb2=get_mac_table($host,$snmp,$fdb_table_oid2,$ifindex_map);

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
    my ($host, $snmp, $oid, $opt) = @_;
    $opt ||= {};

    my $nonblocking = $opt->{nonblocking} // 1;
    log_debug("Starting SNMP walk on $host, OID: $oid, nonblocking=" . ($nonblocking ? 1 : 0));
    my $session = init_snmp($host, $snmp, 'ro', $nonblocking);
    unless ($session) {
        log_debug("Failed to initialize SNMP session for $host");
        return;
    }
    my %table;
    if ($nonblocking) {
        # Async walk через callback
        log_debug("Sending first get_bulk_request for OID $oid");
        my $result = $session->get_bulk_request(
            -varbindlist    => [$oid],
            -callback       => [\&table_callback, \%table, $oid, undef],
            -maxrepetitions => 10,
        );
        unless (defined $result) {
            log_debug("SNMP request error ($host): " . $session->error);
            $session->close();
            return;
        }
        # Запускаем dispatcher для обработки async запросов
        eval {
            log_debug("Starting snmp_dispatcher for $host");
            snmp_dispatcher();
        };
        if ($@) {
            log_debug("SNMP dispatcher exception ($host): $@");
            $session->close();
            return;
        }
    }
    else {
        # Blocking walk через get_next
        log_debug("Starting blocking SNMP walk for OID $oid");
        my $current_oid = $oid;
        while (1) {
            my $result = $session->get_next_request(-varbindlist => [$current_oid]);
            unless (defined $result) {
                log_debug("SNMP request error ($host): " . $session->error);
                last;
            }
            my $list = $session->var_bind_list();
            last unless defined $list;
            my $stop = 0;
            for my $k (keys %$list) {
                unless (oid_base_match($oid, $k)) {
                    log_debug("OID $k outside root $oid, stopping walk");
                    $stop = 1;
                    last;
                }
                $table{$k} = $list->{$k};
                log_debug("Stored OID $k = $list->{$k}");
                $current_oid = $k;
            }
            last if $stop;
        }
    }
    if ($session->error) {
        log_debug("SNMP runtime error ($host): " . $session->error);
    }
    $session->close();
    log_debug("SNMP walk finished on $host, total OIDs collected: " . scalar keys %table);
    return \%table;
}

#-------------------------------------------------------------------------------------

sub table_callback {
    my ($session, $table, $root_oid, $last_oid) = @_;

    my $list = $session->var_bind_list();
    unless (defined $list) {
        log_debug("SNMP error: " . $session->error);
        return;
    }

    my @names = $session->var_bind_names();
    unless (@names) {
        log_debug("No OIDs returned in this callback");
        return;
    }

    $root_oid = _normalize_oid($root_oid);

    my $next;
    my $processed_count = 0;
    my $seen_in_batch = {};  # ← Дубликаты ВНУТРИ одного ответа

    while (@names) {
        $next = shift @names;
        $next = _normalize_oid($next);
        # Выход за пределы таблицы
        unless (oid_base_match($root_oid, $next)) {
            log_debug("OID $next outside of root $root_oid. Exiting.");
            return;
        }
        my $value = $list->{$next};
        unless (defined $value) {
            log_debug("endOfMibView at $next. Exiting.");
            return;
        }
        # Пропускаем дубликаты ВНУТРИ этого пакета
        if ($seen_in_batch->{$next}) {
            log_debug("Duplicate in batch: $next. Skipping.");
            next;
        }
        $seen_in_batch->{$next} = 1;
        # Пропускаем если УЖЕ есть в таблице (из предыдущих пакетов)
        if (exists $table->{$next}) {
            log_debug("Already in table: $next. Skipping.");
            next;
        }
        # Сохраняем
        $table->{$next} = $value;
        $processed_count++;
        log_debug("Stored OID $next = $value");
        # Обновляем last_oid для следующего запроса (максимальный из обработанных)
        if (!defined $last_oid || snmp_oid_compare($next, $last_oid) > 0) {
            $last_oid = $next;
        }
    }

    return unless $processed_count > 0;
    return unless defined $next;

    # Следующий запрос — от последнего "максимального" OID
    my $result = $session->get_bulk_request(
        -varbindlist    => [$last_oid],
        -maxrepetitions => 10,
        -callback       => [\&table_callback, $table, $root_oid, $last_oid],
    );

    unless (defined $result) {
        log_debug("get_bulk_request failed: " . $session->error);
    }
}

#-------------------------------------------------------------------------------------

# проверка что OID начинается с root
sub oid_base_match {
    my ($base, $oid) = @_;
    return defined($oid) && defined($base) && index($oid, $base) == 0;
}

#-------------------------------------------------------------------------------------

sub snmp_oid_compare {
    my ($oid1, $oid2) = @_;
    return 0  if !defined $oid1 && !defined $oid2;
    return 1  if !defined $oid2;
    return -1 if !defined $oid1;
    # Удаляем ведущую точку для единообразия
    $oid1 =~ s/^\.//;
    $oid2 =~ s/^\.//;
    my @a = split /\./, $oid1;
    my @b = split /\./, $oid2;
    my $len = @a < @b ? @a : @b;
    # Сравниваем покомпонентно как числа
    for (my $i = 0; $i < $len; $i++) {
        return -1 if $a[$i] < $b[$i];
        return 1  if $a[$i] > $b[$i];
    }
    # Если префиксы равны, сравниваем длину
    return @a <=> @b;
}


#-------------------------------------------------------------------------------------

# Функция нормализации OID
sub _normalize_oid {
    my ($oid) = @_;
    return undef unless defined $oid;
    # 1. Trim whitespace (leading/trailing)
    $oid =~ s/^\s+|\s+$//g;
    return $oid;
}

#-------------------------------------------------------------------------------------

sub init_snmp {
    my ($host, $snmp, $rw, $nonblocking) = @_;
    return unless defined $host && $host ne '';

    $rw ||= 'ro';

    my $community = ($rw eq 'rw')
        ? $snmp->{'rw-community'}
        : $snmp->{'ro-community'};

    my %opts = (
        -hostname  => $host,
        -port      => $snmp->{port} // 161,
        -timeout   => $snmp->{timeout} // 5,
        -translate => [-octetstring => 0],
    );

    $opts{-nonblocking} = 1 if $nonblocking;

    my ($session, $error);

    if (($snmp->{version} // 2) <= 2) {
        ($session, $error) = Net::SNMP->session(
            %opts,
            -community => $community,
            -version   => $snmp->{version} // 5,
        );
    }
    else {
        ($session, $error) = Net::SNMP->session(
            %opts,
            -version      => 'snmpv3',
            -username     => $snmp->{$rw . '-user'},
            -authprotocol => $snmp->{'auth-proto'},
            -privprotocol => $snmp->{'priv-proto'},
            -authpassword => $snmp->{$rw . '-password'},
            -privpassword => $snmp->{$rw . '-password'},
        );
    }

    if (!defined $session) {
        log_debug("SNMP init failed for $host: $error");
        return;
    }

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
