#!/usr/bin/perl

#
# Copyright (C) Roman Dmitriev, rnd@rajven.ru
#

use utf8;
use open ":encoding(utf8)";
use Encode;
no warnings 'utf8';
use English;
use base;
use FindBin '$Bin';
use lib "/opt/Eye/scripts";
use strict;
use Time::Local;
use FileHandle;
use Data::Dumper;
use eyelib::config;
use eyelib::main;
use eyelib::cmd;
use Net::Patricia;
use Date::Parse;
use eyelib::net_utils;
use eyelib::database;
use eyelib::common;
use DBI;
use Fcntl qw(:flock);
use Parallel::ForkManager;
use Net::DNS;

#$debug = 1;

open(SELF,"<",$0) or die "Cannot open $0 - $!";
flock(SELF, LOCK_EX|LOCK_NB) or exit 1;

$|=1;

if (IsNotRun($SPID)) { Add_PID($SPID); }  else { die "Warning!!! $SPID already runnning!\n"; }

my $fork_count = $cpu_count*10;

#flag for operation status
my $all_ok = 1;

my @gateways =();
#select undeleted mikrotik routers only
if ($ARGV[0]) {
    my $router = get_record_sql($dbh,'SELECT * FROM devices WHERE (device_type=2 OR device_type=0) and protocol>=0 and (user_acl=1 or dhcp=1) and deleted=0 and vendor_id=9 and id='.$ARGV[0]);
    if ($router) { push(@gateways,$router); }
    } else {
    @gateways = get_records_sql($dbh,'SELECT * FROM devices WHERE (device_type=2 OR device_type=0) and protocol>=0 and (user_acl=1 or dhcp=1) and deleted=0 and vendor_id=9');
    }

#все сети организации, работающие по dhcp
my $dhcp_networks = new Net::Patricia;
my %dhcp_conf;

my @subnets=get_records_sql($dbh,'SELECT * FROM subnets WHERE dhcp=1 and office=1 and vpn=0 ORDER BY ip_int_start');
foreach my $subnet (@subnets) {
next if (!$subnet->{gateway});
my $dhcp_info=GetDhcpRange($subnet->{subnet});
$dhcp_networks->add_string($subnet->{subnet},$subnet->{subnet});
$dhcp_conf{$subnet->{subnet}}->{first_pool_ip}=IpToStr($subnet->{dhcp_start});
$dhcp_conf{$subnet->{subnet}}->{last_pool_ip}=IpToStr($subnet->{dhcp_stop});
$dhcp_conf{$subnet->{subnet}}->{relay_ip}=IpToStr($subnet->{gateway});
$dhcp_conf{$subnet->{subnet}}->{gateway}=IpToStr($subnet->{gateway});
#раскрываем подсеть
$dhcp_conf{$subnet->{subnet}}->{network} = $dhcp_info->{network};
$dhcp_conf{$subnet->{subnet}}->{masklen} = $dhcp_info->{masklen};
$dhcp_conf{$subnet->{subnet}}->{first_ip} = $dhcp_info->{first_ip};
$dhcp_conf{$subnet->{subnet}}->{last_ip} = $dhcp_info->{last_ip};
$dhcp_conf{$subnet->{subnet}}->{first_ip_aton}=StrToIp($dhcp_info->{first_ip});
$dhcp_conf{$subnet->{subnet}}->{last_ip_aton}=StrToIp($dhcp_info->{last_ip});
}

my $pm = Parallel::ForkManager->new($fork_count);

#save changed records
my @changes_found = get_records_sql($dbh,"SELECT id FROM user_auth WHERE changed=1");

foreach my $gate (@gateways) {
next if (!$gate);

my $gate_ident = $gate->{device_name}." [$gate->{ip}]:: ";

$pm->start and next;
$dbh = init_db();

my @cmd_list=();

$gate = netdev_set_auth($gate);
$gate->{login}.='+ct400w';
my $t = netdev_login($gate);

if (!$t) {
    log_error($gate_ident."Login to $gate->{device_name} [$gate->{ip}] failed! Skip gateway.");
    $dbh->disconnect();
    $pm->finish;
    next;
    }

my $router_name=$gate->{device_name};
my $router_ip=$gate->{ip};
my $shaper_enabled = $gate->{queue_enabled};
my $connected_users_only = $gate->{connected_user_only};

my @changed_ref=();

#все сети роутера, которые к нему подключены по информации из БД - Patricia Object
my $connected_users = new Net::Patricia;
#сети, которые должен отдавать роутер по dhcp - simple hash
my %connected_nets_hash;
#исключения из авторизации хот-спота
my %hotspot_exceptions;
my @lan_int=();
my @wan_int=();

my @l3_int = get_records_sql($dbh,'SELECT * FROM device_l3_interfaces WHERE device_id='.$gate->{'id'});
foreach my $l3 (@l3_int) {
$l3->{'name'}=~s/\"//g;
if ($l3->{'interface_type'} eq '0') { push(@lan_int,$l3->{'name'}); }
if ($l3->{'interface_type'} eq '1') { push(@wan_int,$l3->{'name'}); }
}

#формируем список подключенных к роутеру сетей
my @gw_subnets = get_records_sql($dbh,"SELECT gateway_subnets.*,subnets.subnet FROM gateway_subnets LEFT JOIN subnets ON gateway_subnets.subnet_id = subnets.id WHERE gateway_subnets.device_id=".$gate->{'id'});
if (@gw_subnets and scalar @gw_subnets) {
    foreach my $gw_subnet (@gw_subnets) {
        if ($gw_subnet and $gw_subnet->{'subnet'}) {
            $connected_users->add_string($gw_subnet->{'subnet'});
            $connected_nets_hash{$gw_subnet->{'subnet'}} = $gw_subnet;
            }
    }
}

#dhcp config
if ($gate->{dhcp}) {

#все сети роутера, которые к нему подключены напрямую фактически  - Patricia Object
my $fact_connected_nets = new Net::Patricia;

#интерфейсы, которые будут использоваться для конфигурирования dhcp-сервера
my @work_int=();

#dhcp-сервер, обрабатывающий запросы от dhcp-relay
my @relayed_dhcp = netdev_cmd($gate,$t,"/ip dhcp-server print terse without-paging where relay=255.255.255.255",1);
my $relayed_dhcp_server;
my $relayed_dhcp_interface;
if (@relayed_dhcp and scalar @relayed_dhcp) {
    my $dhcp_server = $relayed_dhcp[0];
    if ($dhcp_server and $dhcp_server=~/name=(\S+)\s+/i) { $relayed_dhcp_server = $1; }
    if ($dhcp_server and $dhcp_server=~/interface=(\S+)\s+/i) { $relayed_dhcp_interface= $1; }
    }

#ищем интерфейсы, на которых поднята необходимая для dhcp сервера сеть
foreach my $int (@lan_int) { #interface loop
    next if (!$int);
    $int=trim($int);
    #get ip addr at interface
    my @int_addr=netdev_cmd($gate,$t,'/ip address print terse without-paging where interface='.$int,1);
    log_debug($gate_ident."Get interfaces: ".Dumper(\@int_addr));
    my $found_subnet;
    foreach my $int_str(@int_addr) {
        $int_str=trim($int_str);
        next if (!$int_str);
        if ($int_str=~/\s+address=(\S*)\s+/i) {
                my $gate_interface=$1;
                if ($gate_interface) {
                        my $gate_ip=$gate_interface;
                        my $gate_net = GetDhcpRange($gate_interface);
                        $fact_connected_nets->add_string($gate_net->{network}."/".$gate_net->{masklen});
                        $gate_ip=~s/\/.*$//;
                        #search for first match
                        $found_subnet=$dhcp_networks->match_string($gate_ip);
                        last;
                        }
                }
        }

    if (!$found_subnet) {  db_log_verbose($dbh,$gate_ident."DHCP subnet for interface $int not found! Skip interface.");  next; }
    my $dhcp_state;
    $dhcp_state->{subnet}=$found_subnet;
    $dhcp_state->{interface}=$int;
    #формируем список локальных интерфейсов, на котором есть dhcp-сеть
    push(@work_int,$dhcp_state);
}

#формируем список сетей, не подключенных к роутеру непосредтственно
my @relayed_subnets=();
foreach my $gw_subnet (keys %connected_nets_hash ) {
next if (!$gw_subnet);
next if ($fact_connected_nets->match_string($gw_subnet));
push(@relayed_subnets,$gw_subnet);
}

if (scalar @relayed_subnets and $relayed_dhcp_interface) {
    my $dhcp_state;
    $dhcp_state->{interface} = $relayed_dhcp_interface;
    $dhcp_state->{subnet} = join(",",@relayed_subnets);
    push(@work_int,$dhcp_state);
    }

#interface dhcp loop
foreach my $dhcpd_int (@work_int) {

my $found_subnet=$dhcpd_int->{subnet};
my @dhcp_subnets = split(/\,/,$found_subnet);
my $int=$dhcpd_int->{interface};

db_log_verbose($dbh,$gate_ident."Analyze interface $int. Found: ".Dumper($dhcp_conf{$found_subnet}));

#fetch current dhcp records
my @ret_static_leases=netdev_cmd($gate,$t,'/ip dhcp-server lease print terse without-paging where server=dhcp-'.$int,1);

log_debug($gate_ident."Get dhcp leases:".Dumper(\@ret_static_leases));

my @current_static_leases=();
foreach my $str (@ret_static_leases) {
next if (!$str);
$str=trim($str);
if ($str=~/^\d/) {
    log_debug($gate_ident."Found current static lease record: ".$str);
    push(@current_static_leases,$str);
    }
}

#select users for this interface

my @auth_records=();
foreach my $dhcp_subnet (@dhcp_subnets) {
    next if (!$dhcp_subnet);
    next if (!exists $dhcp_conf{$dhcp_subnet});
    my @tmp1=get_records_sql($dbh,"SELECT * from user_auth WHERE dhcp=1 and ip_int>=".$dhcp_conf{$dhcp_subnet}->{first_ip_aton}." and ip_int<=".$dhcp_conf{$dhcp_subnet}->{last_ip_aton}." and deleted=0 and ou_id !=".$default_user_ou_id." and ou_id !=".$default_hotspot_ou_id." ORDER BY ip_int");
    push(@auth_records,@tmp1);
    undef @tmp1;
}

my %leases;
foreach my $lease (@auth_records) {
next if (!$lease);
next if (!$lease->{mac});
next if (!$lease->{ip});
my $found_subnet = $dhcp_networks->match_string($lease->{ip});
next if (!$found_subnet);
next if ($lease->{ip} eq $dhcp_conf{$found_subnet}->{relay_ip});
$leases{$lease->{ip}}{ip}=$lease->{ip};
$leases{$lease->{ip}}{description}=$lease->{id};
$leases{$lease->{ip}}{id}=$lease->{id};
$leases{$lease->{ip}}{dns_name}=$lease->{dns_name};
if ($lease->{description}) { $leases{$lease->{ip}}{description}=translit($lease->{description}); }
$leases{$lease->{ip}}{mac}=uc(mac_splitted($lease->{mac}));
if ($lease->{dhcp_acl}) {
    $leases{$lease->{ip}}{acl}=trim($lease->{dhcp_acl});
    $leases{$lease->{ip}}{acl}=~s/;/,/g;
    if ($leases{$lease->{ip}}{acl}=~/hotspot\-free/) {
        $hotspot_exceptions{$leases{$lease->{ip}}{mac}}=$leases{$lease->{ip}}{mac};
        $hotspot_exceptions{$leases{$lease->{ip}}{mac}}=$leases{$lease->{ip}}{description} if ($leases{$lease->{ip}}{description});
        }
    }
if ($lease->{dhcp_option_set}) {
    $leases{$lease->{ip}}{dhcp_option_set}=trim($lease->{dhcp_option_set});
    }
$leases{$lease->{ip}}{acl}='' if (!$leases{$lease->{ip}}{acl});
$leases{$lease->{ip}}{dhcp_option_set}='' if (!$leases{$lease->{ip}}{dhcp_option_set});
}


my %active_leases;
foreach my $lease (@current_static_leases) {

my @words = split(/\s+/,$lease);
my %tmp_lease;

if ($lease=~/^(\d*)\s+/) { $tmp_lease{id}=$1; };
next if (!defined($tmp_lease{id}));

foreach my $option (@words) {
next if (!$option);
$option=trim($option);
next if (!$option);
my @tmp = split(/\=/,$option);
my $token = trim($tmp[0]);
my $value = trim($tmp[1]);
next if (!$token);
next if (!$value);
$value=~s/\"//g;
if ($token=~/^address$/i) { $tmp_lease{ip}=GetIP($value); }
if ($token=~/^mac-address$/i) { $tmp_lease{mac}=uc(mac_splitted($value)); }
if ($token=~/^address-lists$/i) { $tmp_lease{acl}=$value; }
if ($token=~/^dhcp-option-set$/i) { $tmp_lease{dhcp_option_set}=$value; }
}

next if (!$tmp_lease{ip});
next if (!$tmp_lease{mac});
next if ($lease=~/^(\d*)\s+D\s+/);

$active_leases{$tmp_lease{ip}}{ip}=$tmp_lease{ip};
$active_leases{$tmp_lease{ip}}{mac}=$tmp_lease{mac};
$active_leases{$tmp_lease{ip}}{id}=$tmp_lease{id};

$active_leases{$tmp_lease{ip}}{acl}='';
$active_leases{$tmp_lease{ip}}{dhcp_option_set}='';
if ($tmp_lease{acl}) {
    $active_leases{$tmp_lease{ip}}{acl}=$tmp_lease{acl};
    }
if ($tmp_lease{dhcp_option_set}) {
    $active_leases{$tmp_lease{ip}}{dhcp_option_set}=$tmp_lease{dhcp_option_set};
    }
}

log_debug($gate_ident."Active leases: ".Dumper(\%active_leases));

#sync state
foreach my $ip (keys %active_leases) {
if (!exists $leases{$ip}) {
    db_log_verbose($dbh,$gate_ident."Address $ip not found in stat. Remove from router.");
    push(@cmd_list,':foreach i in [/ip dhcp-server lease find where address='.$ip.' ] do={/ip dhcp-server lease remove $i};');
    push(@cmd_list,'/ip dhcp-server lease remove [find address='.$ip.']');
    push(@cmd_list,'/ip arp remove [find address='.$ip.']');
    next;
    }
if ($leases{$ip}{mac}!~/$active_leases{$ip}{mac}/i) {
    db_log_verbose($dbh,$gate_ident."Mac-address mismatch for ip $ip. stat: $leases{$ip}{mac} active: $active_leases{$ip}{mac}. Remove lease from router.");
    push(@cmd_list,':foreach i in [/ip dhcp-server lease find where address='.$ip.' ] do={/ip dhcp-server lease remove $i};');
    push(@cmd_list,'/ip dhcp-server lease remove [find address='.$ip.']');
    push(@cmd_list,'/ip arp remove [find address='.$ip.']');
    next;
    }
if (!(!$leases{$ip}{acl} and !$active_leases{$ip}{acl}) and $leases{$ip}{acl} ne $active_leases{$ip}{acl}) {
    db_log_error($dbh,$gate_ident."Acl mismatch for ip $ip. stat: $leases{$ip}{acl} active: $active_leases{$ip}{acl}. Remove lease from router.");
    push(@cmd_list,':foreach i in [/ip dhcp-server lease find where address='.$ip.' ] do={/ip dhcp-server lease remove $i};');
    push(@cmd_list,'/ip dhcp-server lease remove [find address='.$ip.']');
    push(@cmd_list,'/ip arp remove [find address='.$ip.']');
    next;
    }
if (!(!$leases{$ip}{dhcp_option_set} and !$active_leases{$ip}{dhcp_option_set}) and $leases{$ip}{dhcp_option_set} ne $active_leases{$ip}{dhcp_option_set}) {
    db_log_error($dbh,$gate_ident."DHCP option-set mismatch for ip $ip. stat: $leases{$ip}{dhcp_option_set} active: $active_leases{$ip}{dhcp_option_set}. Remove lease from router.");
    push(@cmd_list,':foreach i in [/ip dhcp-server lease find where address='.$ip.' ] do={/ip dhcp-server lease remove $i};');
    push(@cmd_list,'/ip dhcp-server lease remove [find address='.$ip.']');
    push(@cmd_list,'/ip arp remove [find address='.$ip.']');
    next;
    }
}

foreach my $ip (keys %leases) {
my $acl='';
if ($leases{$ip}{acl}) { $acl = 'address-lists='.$leases{$ip}{acl}; }

my $dhcp_option_set='';
if ($leases{$ip}{dhcp_option_set}) { $dhcp_option_set = 'dhcp-option-set='.$leases{$ip}{dhcp_option_set}; }

my $description = $leases{$ip}{description};
$description =~s/\=//g;

my $dns_name='';
if ($leases{$ip}{dns_name}) { $dns_name = $leases{$ip}{dns_name}; }
$dns_name =~s/\=//g;

if ($dns_name) { $description = 'description="'.$dns_name." - ".$description.'"'; } else { $description = 'description="'.$description.'"'; }

if (!exists $active_leases{$ip}) {
    db_log_verbose($dbh,$gate_ident."Address $ip not found in router. Create static lease record.");
    #remove static and dynamic records for mac
    push(@cmd_list,':foreach i in [/ip dhcp-server lease find where mac-address='.uc($leases{$ip}{mac}).' ] do={/ip dhcp-server lease remove $i};');
    push(@cmd_list,'/ip dhcp-server lease remove [find mac-address='.uc($leases{$ip}{mac}).']');
    #remove current ip binding
    push(@cmd_list,':foreach i in [/ip dhcp-server lease find where address='.$ip.' ] do={/ip dhcp-server lease remove $i};');
    push(@cmd_list,'/ip dhcp-server lease remove [find address='.$ip.']');
    #add new bind
    push(@cmd_list,'/ip dhcp-server lease add address='.$ip.' mac-address='.$leases{$ip}{mac}.' '.$acl.' '.$dhcp_option_set.' server=dhcp-'.$int.' '.$description);
    #clear arp record
    push(@cmd_list,'/ip arp remove [find mac-address='.uc($leases{$ip}{mac}).']');
    next;
    }
if ($leases{$ip}{mac}!~/$active_leases{$ip}{mac}/i) {
    db_log_error($dbh,$gate_ident."Mac-address mismatch for ip $ip. stat: $leases{$ip}{mac} active: $active_leases{$ip}{mac}. Create static lease record.");
    #remove static and dynamic records for mac
    push(@cmd_list,':foreach i in [/ip dhcp-server lease find where mac-address='.uc($leases{$ip}{mac}).' ] do={/ip dhcp-server lease remove $i};');
    push(@cmd_list,'/ip dhcp-server lease remove [find mac-address='.uc($leases{$ip}{mac}).']');
    #remove current ip binding
    push(@cmd_list,':foreach i in [/ip dhcp-server lease find where address='.$ip.' ] do={/ip dhcp-server lease remove $i};');
    push(@cmd_list,'/ip dhcp-server lease remove [find address='.$ip.']');
    #add new bind
    push(@cmd_list,'/ip dhcp-server lease add address='.$ip.' mac-address='.$leases{$ip}{mac}.' '.$acl.' '.$dhcp_option_set.' server=dhcp-'.$int.' '.$description);
    #clear arp record
    push(@cmd_list,'/ip arp remove [find mac-address='.uc($leases{$ip}{mac}).']');
    next;
    }
if (!(!$leases{$ip}{acl} and !$active_leases{$ip}{acl}) and $leases{$ip}{acl} ne $active_leases{$ip}{acl}) {
    db_log_error($dbh,$gate_ident."Acl mismatch for ip $ip. stat: $leases{$ip}{acl} active: $active_leases{$ip}{acl}. Create static lease record.");
    push(@cmd_list,':foreach i in [/ip dhcp-server lease find where mac-address='.uc($leases{$ip}{mac}).' ] do={/ip dhcp-server lease remove $i};');
    push(@cmd_list,'/ip dhcp-server lease remove [find mac-address='.uc($leases{$ip}{mac}).']');
    push(@cmd_list,'/ip dhcp-server lease add address='.$ip.' mac-address='.$leases{$ip}{mac}.' '.$acl.' '.$dhcp_option_set.' server=dhcp-'.$int.' '.$description);
    #clear arp record
    push(@cmd_list,'/ip arp remove [find mac-address='.uc($leases{$ip}{mac}).']');
    next;
    }
if (!(!$leases{$ip}{dhcp_option_set} and !$active_leases{$ip}{dhcp_option_set}) and $leases{$ip}{dhcp_option_set} ne $active_leases{$ip}{dhcp_option_set}) {
    db_log_error($dbh,$gate_ident."Acl mismatch for ip $ip. stat: $leases{$ip}{acl} active: $active_leases{$ip}{acl}. Create static lease record.");
    push(@cmd_list,':foreach i in [/ip dhcp-server lease find where mac-address='.uc($leases{$ip}{mac}).' ] do={/ip dhcp-server lease remove $i};');
    push(@cmd_list,'/ip dhcp-server lease remove [find mac-address='.uc($leases{$ip}{mac}).']');
    push(@cmd_list,'/ip dhcp-server lease add address='.$ip.' mac-address='.$leases{$ip}{mac}.' '.$acl.' '.$dhcp_option_set.' server=dhcp-'.$int.' '.$description);
    #clear arp record
    push(@cmd_list,'/ip arp remove [find mac-address='.uc($leases{$ip}{mac}).']');
    next;
    }
}

}#end interface dhcp loop

#check hotspot
my @ret_hotspot = netdev_cmd($gate,$t,'/ip hotspot print terse where disabled=no',1);
#if hotspot found - apply exception
if (@ret_hotspot and scalar(@ret_hotspot)) {
    #hotspot exceptions
    my @ret_hotspot_bindings=netdev_cmd($gate,$t,'/ip hotspot ip-binding print terse without-paging where type=bypassed',1);
    my %actual_hotspot_bindings;
    foreach my $row (@ret_hotspot_bindings) {
        next if (!$row or $row !~ /^\s*\d/);
        my %data;
        # Используем регулярное выражение для извлечения пар ключ=значение
        while ($row =~/\b(\S+?)=([^\s=]+(?:\s(?!\S+=)[^\s=]+)*)/g) {
            my ($key, $value) = ($1, $2);
            $data{$key} = $value;
            }
        if (exists $data{'mac-address'}) {
            $actual_hotspot_bindings{$data{'mac-address'}} = $data{'mac-address'};
            $actual_hotspot_bindings{$data{'mac-address'}} = $data{description} if (exists $data{description});
            }
    }
    log_debug("Actual bindings:".Dumper(\%actual_hotspot_bindings));
    log_debug("Configuration exceptions:".Dumper(\%hotspot_exceptions));
    #update binding
    foreach my $actual_mac (keys %actual_hotspot_bindings) {
        if (!exists $hotspot_exceptions{$actual_mac}) {
            db_log_info($dbh,$gate_ident."Address $actual_mac removed from hotspot ip-binding");
            push(@cmd_list,':foreach i in [/ip hotspot ip-binding find where mac-address='.uc($actual_mac).' ] do={/ip hotspot ip-binding remove $i};');
            }
        }
    foreach my $actual_mac (keys %hotspot_exceptions) {
        if (!exists $actual_hotspot_bindings{$actual_mac}) {
            db_log_info($dbh,$gate_ident."Address $actual_mac added to hotspot ip-binding");
            push(@cmd_list,':foreach i in [/ip hotspot ip-binding find where mac-address='.uc($actual_mac).' ] do={/ip hotspot ip-binding remove $i};');
            push(@cmd_list,'/ip hotspot ip-binding add mac-address='.uc($actual_mac).'  type=bypassed  description="'.$hotspot_exceptions{$actual_mac}.'"');
            }
        }
    }

}#end dhcp config

#access lists config
if ($gate->{user_acl}) {

db_log_verbose($dbh,$gate_ident."Sync user state at router $router_name [".$router_ip."] started.");

#get userid list
my $user_auth_sql="SELECT user_auth.ip, user_auth.filter_group_id, user_auth.queue_id, user_auth.id
FROM user_auth, user_list
WHERE user_auth.user_id = user_list.id
AND user_auth.deleted =0
AND user_auth.enabled =1
AND user_auth.blocked =0
AND user_list.blocked =0
AND user_list.enabled =1
AND user_auth.ou_id <> $default_hotspot_ou_id
ORDER BY ip_int";

my @authlist_ref = get_records_sql($dbh,$user_auth_sql);
my %users;
my %lists;
my %found_users;

foreach my $row (@authlist_ref) {
if ($connected_users_only) { next if (!$connected_users->match_string($row->{ip})); }
#skip not office ip's
next if (!$office_networks->match_string($row->{ip}));
$found_users{$row->{'id'}}=$row->{ip};
#filter group acl's
$users{'group_'.$row->{filter_group_id}}->{$row->{ip}}=1;
$users{'group_all'}->{$row->{ip}}=1;
$lists{'group_'.$row->{filter_group_id}}=1;
#queue acl's
if ($row->{queue_id}) { $users{'queue_'.$row->{queue_id}}->{$row->{ip}}=1; }
}

log_debug($gate_ident."Users status:".Dumper(\%users));

#full list
$lists{'group_all'}=1;

#get queue list
my @queuelist_ref = get_records_sql($dbh,"SELECT * FROM queue_list");

my %queues;
foreach my $row (@queuelist_ref) {
$lists{'queue_'.$row->{id}}=1;
next if ((!$row->{Download}) and !($row->{Upload}));
$queues{'queue_'.$row->{id}}{id}=$row->{id};
$queues{'queue_'.$row->{id}}{down}=$row->{Download};
$queues{'queue_'.$row->{id}}{up}=$row->{Upload};
}

log_debug($gate_ident."Queues status:".Dumper(\%queues));

my @filter_instances = get_records_sql($dbh,"SELECT * FROM filter_instances");

my @filterlist_ref = get_records_sql($dbh,"SELECT * FROM filter_list where type=0");

my %filters;
my %dyn_filters;

my $max_filter_rec = get_record_sql($dbh,"SELECT MAX(id) FROM filter_list");
my $max_filter_id = $max_filter_rec->{id};

my $dyn_filters_base = $max_filter_id+1000;
my $dyn_filters_index = $dyn_filters_base;

foreach my $row (@filterlist_ref) {
    #if dst - ip address
    if (is_ip($row->{dst})) {
        $filters{$row->{id}}->{id}=$row->{id};
        $filters{$row->{id}}->{proto}=$row->{proto};
        $filters{$row->{id}}->{dst}=$row->{dst};
        $filters{$row->{id}}->{dstport}=$row->{dstport};
        $filters{$row->{id}}->{srcport}=$row->{srcport};
        #set false for dns dst flag
        $filters{$row->{id}}->{dns_dst}=0;
        } else {
        #if dst not ip - check dns record
        my @dns_record=ResolveNames($row->{dst},undef);
        my $resolved_ips = (scalar @dns_record>0);
        next if (!$resolved_ips);
        foreach my $resolved_ip (sort @dns_record) {
                next if (!$resolved_ip);
                #enable dns dst filters
                $filters{$row->{id}}->{dns_dst}=1;
                #add dynamic dns filter
                $filters{$dyn_filters_index}->{id}=$row->{id};
                $filters{$dyn_filters_index}->{proto}=$row->{proto};
                $filters{$dyn_filters_index}->{dst}=$resolved_ip;
                $filters{$dyn_filters_index}->{dstport}=$row->{dstport};
                $filters{$dyn_filters_index}->{srcport}=$row->{srcport};
                $filters{$dyn_filters_index}->{dns_dst}=0;
                #save new filter dns id for original filter id
                push(@{$dyn_filters{$row->{id}}},$dyn_filters_index);
                $dyn_filters_index++;
            }
        }
}

log_debug($gate_ident."Filters status:". Dumper(\%filters));
log_debug($gate_ident."DNS-filters status:". Dumper(\%dyn_filters));

#clean unused filter records
do_sql($dbh,"DELETE FROM group_filters WHERE group_id NOT IN (SELECT id FROM group_list)");
do_sql($dbh,"DELETE FROM group_filters WHERE filter_id NOT IN (SELECT id FROM filter_list)");

my @groups_list = get_records_sql($dbh,"SELECT * FROM group_list");
my %groups;
foreach my $group (@groups_list) { $groups{'group_'.$group->{id}}=$group; }

my @grouplist_ref = get_records_sql($dbh,"SELECT group_id,filter_id,rule_order,action FROM group_filters ORDER BY group_filters.group_id,group_filters.rule_order");

my %group_filters;
my $index = 0;
my $cur_group;

foreach my $row (@grouplist_ref) {

    if (!$cur_group) { $cur_group = $row->{group_id}; }

    if ($cur_group != $row->{group_id}) {
        $index = 0;
        $cur_group = $row->{group_id};
        }

    #if dst dns filter not found
    if (!$filters{$row->{filter_id}}->{dns_dst}) {
        $group_filters{'group_'.$row->{group_id}}->{$index}->{filter_id}=$row->{filter_id};
        $group_filters{'group_'.$row->{group_id}}->{$index}->{action}=$row->{action};
        $index++;
    } else {
        #if found dns dst filters - add
        if (exists $dyn_filters{$row->{filter_id}}) {
                my @dyn_ips = @{$dyn_filters{$row->{filter_id}}};
                if (scalar @dyn_ips >0) {
                        for (my $i = 0; $i < scalar @dyn_ips; $i++) {
                            $group_filters{'group_'.$row->{group_id}}->{$index}->{filter_id}=$dyn_ips[$i];
                            $group_filters{'group_'.$row->{group_id}}->{$index}->{action}=$row->{action};
                            $index++;
                        }
                }
            }
    }
}

log_debug($gate_ident."Group filters: ".Dumper(\%group_filters));

my %cur_users;

foreach my $group_name (keys %lists) {
my @address_lists=netdev_cmd($gate,$t,'/ip firewall address-list print terse without-paging where list='.$group_name,1);

log_debug($gate_ident."Get address lists:".Dumper(\@address_lists));

foreach my $row (@address_lists) {
    $row=trim($row);
    next if (!$row);
    my @address=split(' ',$row);
    foreach my $row (@address) {
        if ($row=~/address\=(.*)/i) { $cur_users{$group_name}{$1}=1; }
        }
    }
}

#new-ips
foreach my $group_name (keys %users) {
    foreach my $user_ip (keys %{$users{$group_name}}) {
    if (!exists($cur_users{$group_name}{$user_ip})) {
        db_log_verbose($dbh,$gate_ident."Add user with ip: $user_ip to access-list $group_name");
        push(@cmd_list,"/ip firewall address-list add address=".$user_ip." list=".$group_name);
        }
    }
}

#old-ips
foreach my $group_name (keys %cur_users) {
    foreach my $user_ip (keys %{$cur_users{$group_name}}) {
    if (!exists($users{$group_name}{$user_ip})) {
        db_log_verbose($dbh,$gate_ident."Remove user with ip: $user_ip from access-list $group_name");
        push(@cmd_list,":foreach i in [/ip firewall address-list find where address=".$user_ip." and list=".$group_name."] do={/ip firewall address-list remove \$i};");
        }
    }
}

timestamp;

#sync firewall rules

#sync group chains
foreach my $filter_instance (@filter_instances) {

my $instance_name = 'Users';
if ($filter_instance->{id}>1) {
    $instance_name = 'Users-'.$filter_instance->{name};
    #check filter instance exist at gateway
    my $instance_ok = get_record_sql($dbh,"SELECT * FROM device_filter_instances WHERE device_id=$gate->{'id'} AND instance_id=$filter_instance->{id}");
    #skip insatnce if not found
    if (!$instance_ok) { next; }
    }

my @chain_list=netdev_cmd($gate,$t,'/ip firewall filter  print terse without-paging where chain='.$instance_name.' and action=jump',1);

log_debug($gate_ident."Get firewall chains:".Dumper(\@chain_list));

my %cur_chain;
foreach my $jump_list (@chain_list) {
    next if (!$jump_list);
    $jump_list=trim($jump_list);
    if ($jump_list=~/jump-target=(\S*)\s+/i) {
	if ($1) { $cur_chain{$1}++; }
	}
    }

#old chains
foreach my $group_name (keys %cur_chain) {
    if (!exists($group_filters{$group_name}) or $groups{$group_name}->{instance_id} ne $filter_instance->{id}) {
        push (@cmd_list,":foreach i in [/ip firewall filter find where chain=".$instance_name." and action=jump and jump-target=".$group_name."] do={/ip firewall filter remove \$i};");
        } else {
        if ($cur_chain{$group_name} != 2) {
            push (@cmd_list,":foreach i in [/ip firewall filter find where chain=".$instance_name." and action=jump and jump-target=".$group_name."] do={/ip firewall filter remove \$i};");
            push (@cmd_list,"/ip firewall filter add chain=".$instance_name." action=jump jump-target=".$group_name." src-address-list=".$group_name);
            push (@cmd_list,"/ip firewall filter add chain=".$instance_name." action=jump jump-target=".$group_name." dst-address-list=".$group_name);
            }
        }
    }

#new chains
foreach my $group_name (keys %group_filters) {
    if (!exists($cur_chain{$group_name}) and $groups{$group_name}->{instance_id} eq $filter_instance->{id}) {
        push (@cmd_list,"/ip firewall filter add chain=".$instance_name." action=jump jump-target=".$group_name." src-address-list=".$group_name);
        push (@cmd_list,"/ip firewall filter add chain=".$instance_name." action=jump jump-target=".$group_name." dst-address-list=".$group_name);
        }
    }
}

my %chain_rules;
foreach my $group_name (sort keys %group_filters) {

next if (!$group_name);

next if (!exists($group_filters{$group_name}));

my %group_filter = %{$group_filters{$group_name}};

foreach my $filter_index (sort keys %group_filter) {

    my $filter = $group_filter{$filter_index};

    my $filter_id=$filter->{filter_id};

    next if (!$filters{$filter_id});

    next if ($filters{$filter_id}->{dns_dst});

    my $src_rule='chain='.$group_name;
    my $dst_rule='chain='.$group_name;

    if ($filter->{action}) {
        $src_rule=$src_rule." action=accept";
        $dst_rule=$dst_rule." action=accept";
        } else {
        $src_rule=$src_rule." action=reject";
        $dst_rule=$dst_rule." action=reject";
        }

    if ($filters{$filter_id}->{proto} and ($filters{$filter_id}->{proto}!~/all/i)) {
        $src_rule=$src_rule." protocol=".$filters{$filter_id}->{proto};
        $dst_rule=$dst_rule." protocol=".$filters{$filter_id}->{proto};
        }

    if ($filters{$filter_id}->{dst} and $filters{$filter_id}->{dst} ne '0/0') {
        $src_rule=$src_rule." src-address=".trim($filters{$filter_id}->{dst});
        $dst_rule=$dst_rule." dst-address=".trim($filters{$filter_id}->{dst});
        }

    #dstport and srcport
    if (!$filters{$filter_id}->{dstport}) { $filters{$filter_id}->{dstport}=0; }
    if (!$filters{$filter_id}->{srcport}) { $filters{$filter_id}->{srcport}=0; }

    if ($filters{$filter_id}->{dstport} ne '0' and $filters{$filter_id}->{srcport} ne '0') {
                $src_rule=$src_rule." dst-port=".trim($filters{$filter_id}->{srcport})." src-port=".trim($filters{$filter_id}->{dstport});
                $dst_rule=$dst_rule." src-port=".trim($filters{$filter_id}->{srcport})." dst-port=".trim($filters{$filter_id}->{dstport});
                }

    if ($filters{$filter_id}->{dstport} eq '0' and $filters{$filter_id}->{srcport} ne '0') {
                $src_rule=$src_rule." dst-port=".trim($filters{$filter_id}->{srcport});
                $dst_rule=$dst_rule." src-port=".trim($filters{$filter_id}->{srcport});
                }

    if ($filters{$filter_id}->{dstport} ne '0' and $filters{$filter_id}->{srcport} eq '0') {
                $src_rule=$src_rule." src-port=".trim($filters{$filter_id}->{dstport});
                $dst_rule=$dst_rule." dst-port=".trim($filters{$filter_id}->{dstport});
                }

    if ($src_rule ne $dst_rule) {
        push(@{$chain_rules{$group_name}},$src_rule);
        push(@{$chain_rules{$group_name}},$dst_rule);
        } else {
        push(@{$chain_rules{$group_name}},$src_rule);
        }
    }
}

#chain filters
foreach my $group_name (sort keys %group_filters) {
next if (!$group_name);
my @get_filter=netdev_cmd($gate,$t,'/ip firewall filter print terse without-paging where chain='.$group_name,1);
chomp(@get_filter);
my @cur_filter=();
my $chain_ok=1;
foreach (my $f_index=0; $f_index<scalar(@get_filter); $f_index++) {
    my $filter_str=trim($get_filter[$f_index]);
    next if (!$filter_str);
    next if ($filter_str!~/^(\d){1,3}/);
    $filter_str=~s/[^[:ascii:]]//g;
    $filter_str=~s/^\d{1,3}\s+//;
    $filter_str=trim($filter_str);
    next if (!$filter_str);
    push(@cur_filter,$filter_str);
}
log_debug($gate_ident."Current filters:".Dumper(\@cur_filter));
log_debug($gate_ident."New filters:".Dumper($chain_rules{$group_name}));

#current state rules
foreach (my $f_index=0; $f_index<scalar(@cur_filter); $f_index++) {
    my $filter_str=trim($cur_filter[$f_index]);
    if (!$chain_rules{$group_name}[$f_index] or $filter_str!~/$chain_rules{$group_name}[$f_index]/i) {
        print "Check chain $group_name error! $filter_str not found in new config. Recreate chain.\n";
        $chain_ok=0;
        last;
        }
    }
#new rules
if ($chain_ok and $chain_rules{$group_name} and scalar(@{$chain_rules{$group_name}})) {
    foreach (my $f_index=0; $f_index<scalar(@{$chain_rules{$group_name}}); $f_index++) {
        my $filter_str=trim($cur_filter[$f_index]);
        if (!$filter_str) {
                print "Check chain $group_name error! Not found: $chain_rules{$group_name}[$f_index]. Recreate chain.\n";
                $chain_ok=0;
                last;
        }
        $filter_str=~s/^\d//;
        $filter_str=trim($filter_str);
        if ($filter_str!~/$chain_rules{$group_name}[$f_index]/i) {
                print "Check chain $group_name error! Expected: $chain_rules{$group_name}[$f_index] Found: $filter_str. Recreate chain.\n";
                $chain_ok=0;
                last;
        }
    }
}

if (!$chain_ok) {
    push(@cmd_list,":foreach i in [/ip firewall filter find where chain=".$group_name." ] do={/ip firewall filter remove \$i};");
    foreach my $filter_str (@{$chain_rules{$group_name}}) {
        push(@cmd_list,'/ip firewall filter add '.$filter_str);
        }
    }
}

if ($shaper_enabled) {

#shapers
my %get_queue_type=();
my %get_queue_tree=();
my %get_filter_mangle=();

my @tmp=netdev_cmd($gate,$t,'/queue type print terse without-paging where name~"pcq_(down|up)load"',1);

log_debug($gate_ident."Get queues: ".Dumper(\@tmp));

# 0   name=pcq_upload_3 kind=pcq pcq-rate=102401k pcq-limit=500KiB pcq-classifier=src-address pcq-total-limit=2000KiB pcq-burst-rate=0 pcq-burst-threshold=0 pcq-burst-time=10s
#pcq-src-address-mask=32 pcq-dst-address-mask=32 pcq-src-address6-mask=64 pcq-dst-address6-mask=64
foreach my $row (@tmp) {
next if (!$row);
$row = trim($row);
next if ($row!~/^(\d){1,3}/);
$row=~s/^\d{1,3}\s+//;
next if (!$row);
if ($row=~/name=pcq_(down|up)load_(\d){1,3}\s+/i) {
    next if (!$1);
    next if (!$2);
    my $direct = $1;
    my $index = $2;
    $get_queue_type{$index}{$direct}=$row;
    if ($row=~/pcq-rate=(\S*)\s+\S/i) {
            my $rate = $1;
            if ($rate=~/k$/i) { $rate =~s/k$//i; }
            $get_queue_type{$index}{$direct."-rate"}=$rate;
            }
    if ($row=~/pcq-classifier=(\S*)\s+\S/i) { $get_queue_type{$index}{$direct."-classifier"}=$1; }
    if ($row=~/pcq-src-address-mask=(\S*)\s+\S/i) { $get_queue_type{$index}{$direct."-src-address-mask"}=$1; }
    if ($row=~/pcq-dst-address-mask=(\S*)\s+\S/i) { $get_queue_type{$index}{$direct."-dst-address-mask"}=$1; }
    }
}

@tmp=();
@tmp=netdev_cmd($gate,$t,'/queue tree print terse without-paging where parent~"(download|upload)_root"',1);
log_debug($gate_ident."Get root queues: ".Dumper(\@tmp));

#print Dumper(\@tmp);
# 0 I name=queue_3_out parent=upload_root packet-mark=upload_3 limit-at=0 queue=*2A priority=8 max-limit=0 burst-limit=0 burst-threshold=0 burst-time=0s bucket-size=0.1
# 5 I name=queue_3_vlan2_in parent=download_root_vlan2 packet-mark=download_3_vlan2 limit-at=0 queue=*2B priority=8 max-limit=0 burst-limit=0 burst-threshold=0 burst-time=0s bucket-size=0.1
foreach my $row (@tmp) {
next if (!$row);
$row = trim($row);
next if ($row!~/^(\d)/);
$row=~s/^(\d*)\s+//;
next if (!$row);
if ($row=~/queue=pcq_(down|up)load_(\d){1,3}/i) {
    if ($row=~/name=queue_(\d){1,3}_(\S*)_out\s+/i) {
        next if (!$1);
        next if (!$2);
        my $index = $1;
        my $int_name = $2;
        $get_queue_tree{$index}{$int_name}{up}=$row;
        if ($row=~/parent=(\S*)\s+\S/i) { $get_queue_tree{$index}{$int_name}{'up-parent'}=$1; }
        if ($row=~/packet-mark=(\S*)\s+\S/i) { $get_queue_tree{$index}{$int_name}{'up-mark'}=$1; }
        if ($row=~/queue=(\S*)\s+\S/i) { $get_queue_tree{$index}{$int_name}{'up-queue'}=$1; }
        }
    if ($row=~/name=queue_(\d){1,3}_(\S*)_in\s+/i) {
        next if (!$1);
        next if (!$2);
        my $index = $1;
        my $int_name = $2;
        $get_queue_tree{$index}{$int_name}{down}=$row;
        if ($row=~/parent=(\S*)\s+\S/i) { $get_queue_tree{$index}{$int_name}{'down-parent'}=$1; }
        if ($row=~/packet-mark=(\S*)\s+\S/i) { $get_queue_tree{$index}{$int_name}{'down-mark'}=$1; }
        if ($row=~/queue=(\S*)\s+\S/i) { $get_queue_tree{$index}{$int_name}{'down-queue'}=$1; }
        }
    }
}

@tmp=();

@tmp=netdev_cmd($gate,$t,'/ip firewall mangle print terse without-paging where action=mark-packet and new-packet-mark~"(upload|download)_[0-9]{1,3}"',1);
log_debug($gate_ident."Get firewall mangle rules for queues:".Dumper(\@tmp));

# 0    chain=forward action=mark-packet new-packet-mark=upload_0 passthrough=yes src-address-list=queue_0 out-interface=sfp-sfpplus1-wan log=no log-prefix=""
# 0    chain=forward action=mark-packet new-packet-mark=download_3_vlan2 passthrough=yes dst-address-list=queue_3 out-interface=vlan2 in-interface-list=WAN log=no log-prefix=""

foreach my $row (@tmp) {
next if (!$row);
$row = trim($row);
next if ($row!~/^(\d){1,3}/);
$row=~s/^\d{1,3}\s+//;
next if (!$row);
if ($row=~/new-packet-mark=upload_(\d){1,3}_(\S*)\s+/i) {
    next if (!$1);
    next if (!$2);
    my $index = $1;
    my $int_name = $2;
    $get_filter_mangle{$index}{$int_name}{up}=$row;
    if ($row=~/src-address-list=(\S*)\s+\S/i) { $get_filter_mangle{$index}{$int_name}{'up-list'}=$1; }
    if ($row=~/out-interface=(\S*)\s+\S/i) { $get_filter_mangle{$index}{$int_name}{'up-dev'}=$1; }
    if ($row=~/new-packet-mark=(\S*)\s+\S/i) { $get_filter_mangle{$index}{$int_name}{'up-mark'}=$1; }
    }
if ($row=~/new-packet-mark=download_(\d){1,3}_(\S*)\s+/i) {
    next if (!$1);
    next if (!$2);
    my $index = $1;
    my $int_name = $2;
    $get_filter_mangle{$index}{$int_name}{down}=$row;
    if ($row=~/dst-address-list=(\S*)\s+\S/i) { $get_filter_mangle{$index}{$int_name}{'down-list'}=$1; }
    if ($row=~/new-packet-mark=(\S*)\s+\S/i) { $get_filter_mangle{$index}{$int_name}{'down-mark'}=$1; }
    if ($row=~/out-interface=(\S*)\s+\S/i) { $get_filter_mangle{$index}{$int_name}{'down-dev'}=$1; }
    }
}

log_debug($gate_ident."Queues type status:".Dumper(\%get_queue_type));
log_debug($gate_ident."Queues tree status:".Dumper(\%get_queue_tree));
log_debug($gate_ident."Firewall mangle status:".Dumper(\%get_filter_mangle));

my %queue_type;
my %queue_tree;
my %filter_mangle;

#generate new config
foreach my $queue_name (keys %queues) {
my $q_id=$queues{$queue_name}{id};
my $q_up=$queues{$queue_name}{up}+1;
my $q_down=$queues{$queue_name}{down}+1;

#queue_types
$queue_type{$q_id}{up}="name=pcq_upload_".$q_id." kind=pcq pcq-rate=".$q_up."k pcq-limit=500KiB pcq-classifier=src-address pcq-total-limit=2000KiB pcq-burst-rate=0 pcq-burst-threshold=0 pcq-burst-time=10s pcq-src-address-mask=32 pcq-dst-address-mask=32 pcq-src-address6-mask=64 pcq-dst-address6-mask=64";
$queue_type{$q_id}{down}="name=pcq_download_".$q_id." kind=pcq pcq-rate=".$q_down."k pcq-limit=500KiB pcq-classifier=dst-address pcq-total-limit=2000KiB pcq-burst-rate=0 pcq-burst-threshold=0 pcq-burst-time=10s pcq-src-address-mask=32 pcq-dst-address-mask=32 pcq-src-address6-mask=64 pcq-dst-address6-mask=64";

my $queue_ok=1;
if (!$get_queue_type{$q_id}{up}) { $queue_ok=0; }
if ($queue_ok and abs($q_up - $get_queue_type{$q_id}{'up-rate'})>10) { $queue_ok=0; }
if ($queue_ok and $get_queue_type{$q_id}{'up-classifier'}!~/src-address/i)  { $queue_ok=0; }

if (!$queue_ok) {
    push(@cmd_list,':foreach i in [/queue type find where name~"pcq_upload_'.$q_id.'" ] do={/queue type remove $i};');
    push(@cmd_list,'/queue type add '.$queue_type{$q_id}{up});
    }

$queue_ok=1;
if (!$get_queue_type{$q_id}{down}) { $queue_ok=0; }
if ($queue_ok and abs($q_up - $get_queue_type{$q_id}{'down-rate'})>10) { $queue_ok=0; }
if ($queue_ok and $get_queue_type{$q_id}{'down-classifier'}!~/dst-address/i)  { $queue_ok=0; }

if (!$queue_ok) {
    push(@cmd_list,':foreach i in [/queue type find where name~"pcq_download_'.$q_id.'" ] do={/queue type remove $i};');
    push(@cmd_list,'/queue type add '.$queue_type{$q_id}{down});
    }

#upload queue
foreach my $int (@wan_int) {
$queue_tree{$q_id}{$int}{up}="name=queue_".$q_id."_".$int."_out parent=upload_root_".$int." packet-mark=upload_".$q_id."_".$int." limit-at=0 queue=pcq_upload_".$q_id." priority=8 max-limit=0 burst-limit=0 burst-threshold=0 burst-time=0s bucket-size=0.1";
$filter_mangle{$q_id}{$int}{up}="chain=forward action=mark-packet new-packet-mark=upload_".$q_id."_".$int." passthrough=yes src-address-list=queue_".$q_id." out-interface=".$int." log=no log-prefix=\"\"";

$queue_ok=1;
if (!$get_queue_tree{$q_id}{$int}{up}) { $queue_ok=0; }
if ($queue_ok and ($get_queue_tree{$q_id}{$int}{'up-parent'} ne "upload_root_".$int)) { $queue_ok=0;}
if ($queue_ok and ($get_queue_tree{$q_id}{$int}{'up-mark'} ne "upload_".$q_id."_".$int)) { $queue_ok=0; }
if ($queue_ok and ($get_queue_tree{$q_id}{$int}{'up-queue'} ne "pcq_upload_".$q_id)) { $queue_ok=0; }

if (!$queue_ok) {
    push(@cmd_list,':foreach i in [/queue tree find where name~"queue_'.$q_id."_".$int."_out".'" ] do={/queue tree remove $i};');
    push(@cmd_list,'/queue tree add '.$queue_tree{$q_id}{$int}{up});
    }

$queue_ok=1;
if (!$get_filter_mangle{$q_id}{$int}{up}) { $queue_ok=0; }
if ($queue_ok and ($get_filter_mangle{$q_id}{$int}{'up-mark'} ne "upload_".$q_id."_".$int)) { $queue_ok=0; }
if ($queue_ok and ($get_filter_mangle{$q_id}{$int}{'up-list'} ne "queue_".$q_id)) { $queue_ok=0; }
if ($queue_ok and ($get_filter_mangle{$q_id}{$int}{'up-dev'} ne $int)) { $queue_ok=0; }

if (!$queue_ok) {
    push(@cmd_list,':foreach i in [/ip firewall mangle find where action=mark-packet and new-packet-mark~"upload_'.$q_id."_".$int.'" ] do={/ip firewall mangle remove $i};');
    push(@cmd_list,'/ip firewall mangle add '.$filter_mangle{$q_id}{$int}{up});
    }
}

#download
foreach my $int (@lan_int) {
next if (!$int);
$queue_tree{$q_id}{$int}{down}="name=queue_".$q_id."_".$int."_in parent=download_root_".$int." packet-mark=download_".$q_id."_".$int." limit-at=0 queue=pcq_download_".$q_id." priority=8 max-limit=0 burst-limit=0 burst-threshold=0 burst-time=0s bucket-size=0.1";
$filter_mangle{$q_id}{$int}{down}="chain=forward action=mark-packet new-packet-mark=download_".$q_id."_".$int." passthrough=yes dst-address-list=queue_".$q_id." out-interface=".$int." in-interface-list=WAN log=no log-prefix=\"\"";

$queue_ok=1;
if (!$get_queue_tree{$q_id}{$int}{down}) { $queue_ok=0; }
if ($queue_ok and ($get_queue_tree{$q_id}{$int}{'down-parent'} ne "download_root_".$int)) { $queue_ok=0; }
if ($queue_ok and ($get_queue_tree{$q_id}{$int}{'down-mark'} ne "download_".$q_id."_".$int)) { $queue_ok=0; }
if ($queue_ok and ($get_queue_tree{$q_id}{$int}{'down-queue'} ne "pcq_download_".$q_id)) { $queue_ok=0; }

if (!$queue_ok) {
    push(@cmd_list,':foreach i in [/queue tree find where name~"queue_'.$q_id."_".$int."_in".'" ] do={/queue tree remove $i};');
    push(@cmd_list,'/queue tree add '.$queue_tree{$q_id}{$int}{down});
    }

$queue_ok=1;
if (!$get_filter_mangle{$q_id}{$int}{down}) { $queue_ok=0; }
if ($queue_ok and ($get_filter_mangle{$q_id}{$int}{'down-mark'} ne "download_".$q_id."_".$int)) { $queue_ok=0; }
if ($queue_ok and ($get_filter_mangle{$q_id}{$int}{'down-list'} ne "queue_".$q_id)) { $queue_ok=0; }
if ($queue_ok and ($get_filter_mangle{$q_id}{$int}{'down-dev'} ne $int)) { $queue_ok=0; }

if (!$queue_ok) {
    push(@cmd_list,':foreach i in [/ip firewall mangle find where action=mark-packet and new-packet-mark~"download_'.$q_id."_".$int.'" ] do={/ip firewall mangle remove $i};');
    push(@cmd_list,'/ip firewall mangle add '.$filter_mangle{$q_id}{$int}{down});
    }
}
#end shaper
}

}

}#end access lists config

if (scalar(@cmd_list)) {
    log_debug($gate_ident."Apply:");
    if ($debug) { foreach my $cmd (@cmd_list) { log_debug($gate_ident."$cmd"); } }
    eval {
        netdev_cmd($gate,$t,\@cmd_list,1);
    };
    if ($@) {
        $all_ok = 0;
        log_debug($gate_ident."Error programming gateway! Err: ".$@);
        }
    }

db_log_verbose($dbh,$gate_ident."Sync user state stopped.");
$dbh->disconnect();
$pm->finish;
}

$pm->wait_all_children;

#clear changed
if ($all_ok) {
    foreach my $row (@changes_found) {
        do_sql($dbh,"UPDATE user_auth SET changed=0 WHERE id=".$row->{id});
        }
    }

if (IsMyPID($SPID)) { Remove_PID($SPID); };

do_exit 0;
