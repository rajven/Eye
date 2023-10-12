#!/usr/bin/perl -w

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use FindBin '$Bin';
use lib "$Bin/";
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
use eyelib::mysql;
use IPTables::libiptc;
use DBI;
use utf8;
use open ":encoding(utf8)";
use Net::DNS;

#exit;

$|=1;

my $gate = get_record_sql($dbh,"SELECT * FROM devices WHERE device_type=2 and user_acl=1 and deleted=0 and vendor_id=19 and device_name='".$HOSTNAME."'");

if (!$gate) { exit 0; }

my $router_name=$gate->{device_name};
my $router_ip=$gate->{ip};
my $shaper_enabled = $gate->{queue_enabled};
my $connected_users_only = $gate->{connected_user_only};

my @lan_int=();
my @wan_int=();

my @l3_int = get_records_sql($dbh,'SELECT * FROM device_l3_interfaces WHERE device_id='.$gate->{'id'});
foreach my $l3 (@l3_int) {
    if ($l3->{'interface_type'} eq '0') { push(@lan_int,$l3->{'name'}); }
    if ($l3->{'interface_type'} eq '1') { push(@wan_int,$l3->{'name'}); }
}

my $connected_users = new Net::Patricia;

if ($connected_users_only) {
    foreach my $int (@lan_int) {
    $int=trim($int);
    next if (!$int);
    #get ip addr at interface
    foreach my $int_str (@lan_int) {
	$int_str=trim($int_str);
	my $int_addr=do_exec('/sbin/ip addr show '.$int_str.' | grep "scope global"');
	foreach my $address (split(/\n/,$int_addr)) {
	    if ($address=~/inet\s+(.*)\s+brd/i) {
		if ($1) { $connected_users->add_string($1); }
		}
	    }
	}
    }
}

db_log_verbose($dbh,"Sync user state at router $router_name started.");

#get userid list
my $user_auth_sql="SELECT User_auth.ip, User_auth.filter_group_id, User_auth.queue_id, User_auth.id
FROM User_auth, User_list
WHERE User_auth.user_id = User_list.id
AND User_auth.deleted =0
AND User_auth.enabled =1
AND User_auth.blocked =0
AND User_list.blocked =0
AND User_list.enabled =1
AND User_auth.ou_id <> $default_hotspot_ou_id
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

log_debug("Users status:".Dumper(\%users));

#full list
$lists{'group_all'}=1;

#get queue list
my @queuelist_ref = get_records_sql($dbh,"SELECT * FROM Queue_list");

my %queues;
foreach my $row (@queuelist_ref) {
$lists{'queue_'.$row->{id}}=1;
next if ((!$row->{Download}) and !($row->{Upload}));
$queues{'queue_'.$row->{id}}{id}=$row->{id};
$queues{'queue_'.$row->{id}}{down}=$row->{Download};
$queues{'queue_'.$row->{id}}{up}=$row->{Upload};
}

log_debug("Queues status:".Dumper(\%queues));

my @filterlist_ref = get_records_sql($dbh,"SELECT * FROM Filter_list where type=0");

my %filters;
my %dyn_filters;

my $max_filter_rec = get_record_sql($dbh,"SELECT MAX(id) FROM Filter_list");
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

log_debug("Filters status:". Dumper(\%filters));
log_debug("DNS-filters status:". Dumper(\%dyn_filters));

#clean unused filter records
do_sql($dbh,"DELETE FROM Group_filters WHERE group_id NOT IN (SELECT id FROM Group_list)");
do_sql($dbh,"DELETE FROM Group_filters WHERE filter_id NOT IN (SELECT id FROM Filter_list)");

my @grouplist_ref = get_records_sql($dbh,"SELECT `group_id`,`filter_id`,`order`,`action` FROM Group_filters ORDER BY Group_filters.group_id,Group_filters.order");

my %group_filters;
my $index=0;
foreach my $row (@grouplist_ref) {
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

log_debug("Group filters: ".Dumper(\%group_filters));

my %cur_users;

my @new_iptables_users=();
foreach my $group_name (keys %lists) {
#new users chains
push(@new_iptables_users,"-A USERS -m set --match-set $group_name src -j $group_name");
push(@new_iptables_users,"-A USERS -m set --match-set $group_name dst -j $group_name");
#current user chains members
my $address_lists=do_exec('/sbin/ipset list '.$group_name.' 2>/dev/null');
$cur_users{$group_name}{found}=0;
foreach my $row (split(/\n/,$address_lists)) {
    $row=trim($row);
    next if (!$row);
    if ($row=~/^Error$/i) {  $cur_users{$group_name}{found}=0; last; }
    next if ($row !~ /^[0-9]/);
    $cur_users{$group_name}{ips}{$row}=1;
    $cur_users{$group_name}{found}=1;
    }
}

#recreate ipsets if not found
foreach my $group_name (keys %lists) {
next if ($cur_users{$group_name}{found});
do_exec("/sbin/ipset create $group_name hash:net family inet maxelem 2655360 2>/dev/null");
}

my @cmd_list=();

#new-ips
foreach my $group_name (keys %users) {
    next if (!$users{$group_name}{ips});
    foreach my $user_ip (keys %{$users{$group_name}{ips}}) {
    if (!exists($cur_users{$group_name}{ips}{$user_ip})) {
	db_log_verbose($dbh,"Add user with ip: $user_ip to access-list $group_name");
	do_exec("/sbin/ipset add $group_name $user_ip");
	}
    }
}

#old-ips
foreach my $group_name (keys %cur_users) {
    next if (!$cur_users{$group_name}{ips});
    foreach my $user_ip (keys %{$cur_users{$group_name}{ips}}) {
    if (!exists($users{$group_name}{ips}{$user_ip})) {
	db_log_verbose($dbh,"Remove user with ip: $user_ip from access-list $group_name");
        do_exec("/sbin/ipset del $group_name $user_ip");
	}
    }
}

timestamp;

#filters
my %chain_rules;
foreach my $group_name (keys %lists) {
next if (!$group_name);
next if (!exists($group_filters{$group_name}));
push(@{$chain_rules{$group_name}},"-N $group_name");
foreach my $filter_index (sort keys %{$group_filters{$group_name}}) {
    my $filter_id=$group_filters{$group_name}->{$filter_index}->{filter_id};
    next if (!$filters{$filter_id});
    my $src_rule='-A '.$group_name;
    my $dst_rule='-A '.$group_name;
    if ($filters{$filter_id}->{proto} and ($filters{$filter_id}->{proto}!~/all/i)) {
	$src_rule=$src_rule." -p ".$filters{$filter_id}->{proto};
	$dst_rule=$dst_rule." -p ".$filters{$filter_id}->{proto};
	}
    if ($filters{$filter_id}->{dst} and $filters{$filter_id}->{dst} ne '0/0') {
	$src_rule=$src_rule." -s ".trim($filters{$filter_id}->{dst});
	$dst_rule=$dst_rule." -d ".trim($filters{$filter_id}->{dst});
	}
    if ($filters{$filter_id}->{port} and $filters{$filter_id}->{port} ne '0') {
	my $module=" -m ".$filters{$filter_id}->{proto};
	if ($filters{$filter_id}->{port}=~/\-/ or $filters{$filter_id}->{port}=~/\,/ or $filters{$filter_id}->{port}=~/\:/) {
	    $module=" -m multiport";
	    $filters{$filter_id}->{port}=~s/\-/:/g;
	    }
	$src_rule=$src_rule.$module." --sport ".trim($filters{$filter_id}->{port});
	$dst_rule=$dst_rule.$module." --dport ".trim($filters{$filter_id}->{port});
	}
    if ($group_filters{$group_name}->{$filter_index}->{action}) {
	$src_rule=$src_rule." -j ACCEPT";
	$dst_rule=$dst_rule." -j ACCEPT";
	} else {
	$src_rule=$src_rule." -j REJECT";
	$dst_rule=$dst_rule." -j REJECT";
	}
    if ($src_rule ne $dst_rule) {
        push(@{$chain_rules{$group_name}},$src_rule);
        push(@{$chain_rules{$group_name}},$dst_rule);
        } else {
        push(@{$chain_rules{$group_name}},$src_rule);
        }
    }
}

######## get current iptables USERS chain state

my $cur_iptables = do_exec("/sbin/iptables --list-rules USERS 2>/dev/null");
my @cur_iptables_users = split(/\n/,$cur_iptables);

my $users_chain_ok=(scalar @cur_iptables_users eq scalar @new_iptables_users);
#if count records in chain ok - check stuff
if ($users_chain_ok) {
    for (my $i = 0; $i <= $#cur_iptables_users; $i++) {
	if ($cur_iptables_users[$i]!~/$new_iptables_users[$i]/i) { $users_chain_ok=0; last; }
	}
    }

#group rules
my %cur_chain_rules;
foreach my $group_name (keys %lists) {
next if (!$group_name);
my $tmp=do_exec("/sbin/iptables --list-rules $group_name 2>/dev/null");
foreach my $rule (split(/\n/,$tmp)) {
    if ($rule=~/Error/i) {
	$lists{$group_name}=0;
	last;
	}
    push(@{$cur_chain_rules{$group_name}},$rule);
    }
}

#check filter group chain
foreach my $group_name (keys %lists) {
my @tmp = ();
if ($chain_rules{$group_name}) { @tmp = @{$chain_rules{$group_name}}; }
my @cur_tmp = ();
if ($cur_chain_rules{$group_name}) { @cur_tmp=@{$cur_chain_rules{$group_name}}; }
my $group_chain_ok=($#tmp eq $#cur_tmp);
#if count records in chain ok - check stuff
if ($group_chain_ok) {
    for (my $i = 0; $i <= $#tmp; $i++) {
	    if ($tmp[$i]!~/$cur_tmp[$i]/i) { $group_chain_ok=0; last; }
	    }
        }
if (!$group_chain_ok) {
    if ($lists{$group_name}) {
        push(@cmd_list,"-D USERS -m set --match-set $group_name src -j $group_name");
	push(@cmd_list,"-D USERS -m set --match-set $group_name dst -j $group_name");
        push(@cmd_list,"-D $group_name");
	}
    push(@cmd_list,@{$chain_rules{$group_name}});
    if ($users_chain_ok) {
	push(@cmd_list,"-A USERS -m set --match-set $group_name src -j $group_name");
	push(@cmd_list,"-A USERS -m set --match-set $group_name dst -j $group_name");
	}
    }
}

#recreate users chain
if (!$users_chain_ok) {
    for (my $i = 0; $i <= $#new_iptables_users; $i++) {
	push(@cmd_list,$new_iptables_users[$i]);
	}
    }

my $table = IPTables::libiptc::init('filter');
foreach my $row (@cmd_list) {
print "$row\n" if ($debug);
my @cmd_array = split(" ",$row);
$table->iptables_do_command(\@cmd_array);
}
$table->commit();

db_log_verbose($dbh,"Sync user state at router $router_name stopped.");
$dbh->disconnect();

exit;
