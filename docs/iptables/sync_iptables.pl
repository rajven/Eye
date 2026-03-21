#!/usr/bin/perl

#
# Copyright (C) Roman Dmitriev, rnd@rajven.ru
#

use utf8;
use warnings;
use Encode;
use open qw(:std :encoding(UTF-8));
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
use Net::DNS;
use File::Path qw(make_path);

#$debug = 1;

open(SELF,"<",$0) or die "Cannot open $0 - $!";
flock(SELF, LOCK_EX|LOCK_NB) or exit 1;

$|=1;

if (IsNotRun($SPID)) { Add_PID($SPID); }  else { die "Warning!!! $SPID already running!\n"; }

my $router_id    = $ARGV[0];

$debug = 1;

# ============================================================================
# КОНСТАНТЫ ДЛЯ IPTABLES/IPSET
# ============================================================================
my $IPSET_CMD = '/usr/sbin/ipset';
my $IPTABLES_CMD = '/usr/sbin/iptables';
my $IPTABLES_TABLE_NAME = 'eye_acl';
my $IPTABLES_CHAIN_PREFIX = 'EYE_';
my $IPSET_SAVE_DIR = '/etc/ipset.d';
my $IPSET_SAVE_EXTENSION = '.conf';

# ============================================================================
# ОСНОВНАЯ ЛОГИКА
# ============================================================================

my $gate = get_record_sql($dbh, 'SELECT * FROM devices WHERE id=?', $router_id );

exit 100 if (!$gate);

my $gate_ident = $gate->{device_name}." [$gate->{ip}]:: ";

my @cmd_list=();
my @cmd_ipset_list=();

my $router_name=$gate->{device_name};
my $router_ip=$gate->{ip};
my $connected_users_only = $gate->{connected_user_only};

my @changed_ref=();

# Лог начала обработки
log_verbose($gate_ident."=== ACL Sync STARTED ===");
log_info($gate_ident."Starting ACL synchronization for router $router_name [$router_ip]");

# все сети роутера, которые к нему подключены по информации из БД
my $connected_users = new Net::Patricia;
my %connected_nets_hash;
my %hotspot_exceptions;
my @lan_int=();
my @wan_int=();

# настройки используемых l3-интерфейсов
my %l3_interfaces;

my @l3_int = get_records_sql($dbh,'SELECT * FROM device_l3_interfaces WHERE device_id=?',$gate->{'id'});
foreach my $l3 (@l3_int) {
    $l3->{'name'}=~s/\"//g;
    $l3_interfaces{$l3->{'name'}}{type} = $l3->{'interface_type'};
    $l3_interfaces{$l3->{'name'}}{bandwidth} = 0;
    if ($l3->{'bandwidth'}) { $l3_interfaces{$l3->{'name'}}{bandwidth} = $l3->{'bandwidth'}; }
    if ($l3->{'interface_type'} eq '0') { push(@lan_int,$l3->{'name'}); }
    if ($l3->{'interface_type'} eq '1') { push(@wan_int,$l3->{'name'}); }
}

log_verbose($gate_ident."Loaded ".scalar(@l3_int)." L3 interfaces (".scalar(@lan_int)." LAN, ".scalar(@wan_int)." WAN)");

# формируем список подключенных к роутеру сетей
my @gw_subnets = get_records_sql($dbh,"SELECT gateway_subnets.*,subnets.subnet FROM gateway_subnets LEFT JOIN subnets ON gateway_subnets.subnet_id = subnets.id WHERE gateway_subnets.device_id=?",$gate->{'id'});
if (@gw_subnets and scalar @gw_subnets) {
    foreach my $gw_subnet (@gw_subnets) {
        if ($gw_subnet and $gw_subnet->{'subnet'}) {
            $connected_users->add_string($gw_subnet->{'subnet'});
            $connected_nets_hash{$gw_subnet->{'subnet'}} = $gw_subnet;
        }
    }
}

log_verbose($gate_ident."Loaded ".scalar(@gw_subnets)." gateway subnets");

my %users;
my %lists;


my $group_sql = "SELECT DISTINCT filter_group_id FROM user_auth WHERE deleted = 0 ORDER BY filter_group_id;";
my @grouplist_ref = get_records_sql($dbh,$group_sql);
foreach my $row (@grouplist_ref) {
    $lists{'group_'.$row->{filter_group_id}}=1;
}
$lists{'group_all'}=1;

log_verbose($gate_ident."Loaded ".scalar(keys %lists)." filter groups");

my $chains_created = 0;
my $chains_removed = 0;
my $rules_added = 0;
my $rules_removed = 0;

my $ipset_created = 0;
my $ipset_added = 0;
my $ipset_removed = 0;

# ============================================================================
# ACCESS LISTS CONFIG
# ============================================================================
if ($gate->{user_acl}) {


    log_verbose($gate_ident."Sync user state at router $router_name [".$router_ip."] started.");
    db_log_verbose($dbh,$gate_ident."Sync user state at router $router_name [".$router_ip."] started.");

    my $user_auth_sql="SELECT user_auth.ip, user_auth.filter_group_id, user_auth.id
    FROM user_auth, user_list
    WHERE user_auth.user_id = user_list.id
    AND user_auth.deleted =0
    AND user_auth.enabled =1
    AND user_auth.blocked =0
    AND user_list.blocked =0
    AND user_list.enabled =1
    AND user_auth.ou_id <> ?
    ORDER BY ip_int";

    my @authlist_ref = get_records_sql($dbh,$user_auth_sql,$default_hotspot_ou_id);

    foreach my $row (@authlist_ref) {
        if ($connected_users_only) { next if (!$connected_users->match_string($row->{ip})); }
        next if (!$office_networks->match_string($row->{ip}));
        $users{'group_'.$row->{filter_group_id}}->{$row->{ip}}=1;
        $users{'group_all'}->{$row->{ip}}=1;
    }

    # Подсчёт пользователей
    my $users_count = 0;
    foreach my $group (values %users) {
        $users_count += scalar keys %$group;
    }
    log_verbose($gate_ident."Loaded $users_count user IPs for ACL processing");

    log_debug($gate_ident."Users status by ACL:".Dumper(\%users));

    my @filter_instances = get_records_sql($dbh,"SELECT * FROM filter_instances");
    my @filterlist_ref = get_records_sql($dbh,"SELECT * FROM filter_list where filter_type=0");

    my %filters;
    my %dyn_filters;

    my $max_filter_rec = get_record_sql($dbh,"SELECT MAX(id) as max_filter FROM filter_list");
    my $max_filter_id = $max_filter_rec->{max_filter};
    my $dyn_filters_base = $max_filter_id+1000;
    my $dyn_filters_index = $dyn_filters_base;

    foreach my $row (@filterlist_ref) {
        if (is_ip($row->{dst})) {
            $filters{$row->{id}}->{id}=$row->{id};
            $filters{$row->{id}}->{proto}=$row->{proto};
            $filters{$row->{id}}->{dst}=$row->{dst};
            $filters{$row->{id}}->{dstport}=$row->{dstport};
            $filters{$row->{id}}->{srcport}=$row->{srcport};
            $filters{$row->{id}}->{dns_dst}=0;
        } else {
            my @dns_record=ResolveNames($row->{dst},undef);
            my $resolved_ips = (scalar @dns_record>0);
            next if (!$resolved_ips);
            foreach my $resolved_ip (sort @dns_record) {
                next if (!$resolved_ip);
                $filters{$row->{id}}->{dns_dst}=1;
                $filters{$dyn_filters_index}->{id}=$row->{id};
                $filters{$dyn_filters_index}->{proto}=$row->{proto};
                $filters{$dyn_filters_index}->{dst}=$resolved_ip;
                $filters{$dyn_filters_index}->{dstport}=$row->{dstport};
                $filters{$dyn_filters_index}->{srcport}=$row->{srcport};
                $filters{$dyn_filters_index}->{dns_dst}=0;
                push(@{$dyn_filters{$row->{id}}},$dyn_filters_index);
                $dyn_filters_index++;
            }
        }
    }

    log_debug($gate_ident."Filters status:". Dumper(\%filters));
    log_debug($gate_ident."DNS-filters status:". Dumper(\%dyn_filters));
    log_verbose($gate_ident."Loaded ".scalar(keys %filters)." filters (".scalar(keys %dyn_filters)." with DNS resolution)");

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
        if (!$filters{$row->{filter_id}}->{dns_dst}) {
            $group_filters{'group_'.$row->{group_id}}->{$index}->{filter_id}=$row->{filter_id};
            $group_filters{'group_'.$row->{group_id}}->{$index}->{action}=$row->{action};
            $index++;
        } else {
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
    log_verbose($gate_ident."Prepared ".scalar(keys %group_filters)." group filter configurations");

    # ============================================================================
    # СИНХРОНИЗАЦИЯ IPSET
    # ============================================================================

    my %cur_users;
    my %final_ipsets;  # Хранилище для финальных IPset данных


    # Создаем таблицу ipset если не существует
    log_verbose($gate_ident."Ensure ipset exists: ${IPTABLES_TABLE_NAME}_group_all");
    push(@cmd_ipset_list, "$IPSET_CMD create ${IPTABLES_TABLE_NAME}_group_all hash:net family inet hashsize 1024 maxelem 2655360");
#    log_debug(Dumper(\%lists));
    foreach my $group_name (keys %lists) {
        my $set_name = $IPTABLES_TABLE_NAME . '_' . $group_name;
        log_verbose($gate_ident."Ensure ipset exists: $set_name");
        push(@cmd_ipset_list, "$IPSET_CMD create $set_name hash:net family inet hashsize 1024 maxelem 2655360");

        my @address_list = get_ipset_members($set_name);
        foreach my $ip (@address_list) {
            $cur_users{$group_name}{$ip} = 1;
            $final_ipsets{$group_name}{$ip} = 1;  # Сохраняем в финальный хэш
        }
        log_verbose($gate_ident."Current ipset $group_name has ".scalar(@address_list)." entries");
    }

    # Добавляем новые IP
    foreach my $group_name (keys %users) {
        my $set_name = $IPTABLES_TABLE_NAME . '_' . $group_name;
        foreach my $user_ip (keys %{$users{$group_name}}) {
            if (!exists($cur_users{$group_name}{$user_ip})) {
                log_info($gate_ident."ADD ipset entry: $user_ip -> $group_name");
                db_log_verbose($dbh, $gate_ident."Add user with ip: $user_ip to ipset $group_name");
                push(@cmd_ipset_list, "$IPSET_CMD add $set_name $user_ip");
                $final_ipsets{$group_name}{$user_ip} = 1;  # Добавляем в финальный хэш
                $ipset_added++;
            }
        }
    }

    # Удаляем старые IP
    foreach my $group_name (keys %cur_users) {
        my $set_name = $IPTABLES_TABLE_NAME . '_' . $group_name;
        foreach my $user_ip (keys %{$cur_users{$group_name}}) {
            if (!exists($users{$group_name}{$user_ip})) {
                log_info($gate_ident."REMOVE ipset entry: $user_ip <- $group_name");
                db_log_verbose($dbh, $gate_ident."Remove user with ip: $user_ip from ipset $group_name");
                push(@cmd_ipset_list, "$IPSET_CMD del $set_name $user_ip");
                delete $final_ipsets{$group_name}{$user_ip};  # Удаляем из финального хэша
                $ipset_removed++;
            }
        }
    }

    log_verbose($gate_ident."IPset changes: $ipset_added added, $ipset_removed removed");

    # ============================================================================
    # СОХРАНЕНИЕ IPSET В ФАЙЛЫ
    # ============================================================================

    save_ipsets_to_files(\%final_ipsets);

    timestamp;


    # ========================================================================
    # СИНХРОНИЗАЦИЯ IPTABLES
    # ========================================================================

    foreach my $filter_instance (@filter_instances) {
        my $instance_name = 'Users';
        if ($filter_instance->{id}>1) {
            $instance_name = 'Users-'.$filter_instance->{name};
            my $instance_ok = get_record_sql($dbh,"SELECT * FROM device_filter_instances WHERE device_id= ? AND instance_id=?", $gate->{'id'}, $filter_instance->{id});
            if (!$instance_ok) {
                log_verbose($gate_ident."Skip filter instance '$instance_name' - not assigned to this device");
                next;
            }
        }

        # Создаем цепочку iptables если не существует
        my $chain_name = $IPTABLES_CHAIN_PREFIX . $instance_name;
        log_verbose($gate_ident."Ensure chain exists: $chain_name");
        push(@cmd_list, "$IPTABLES_CMD -N $chain_name 2>/dev/null || true");

        # Проверяем существующие цепочки для групп
        my %cur_chain = get_iptables_jumps($chain_name);

        # Удаляем старые цепочки
        foreach my $group_name (keys %cur_chain) {
            if (!exists($group_filters{$group_name}) or $groups{$group_name}->{instance_id} ne $filter_instance->{id}) {
                my $filter_chain_name = $IPTABLES_CHAIN_PREFIX . $group_name;
                my $user_ipset_group = $IPTABLES_TABLE_NAME . "_" . $group_name;
                log_info($gate_ident."REMOVE iptables chain link: $group_name -> $instance_name");
                push(@cmd_list, "$IPTABLES_CMD -D $chain_name -m set --match-set $user_ipset_group src -j $filter_chain_name");
                push(@cmd_list, "$IPTABLES_CMD -D $chain_name -m set --match-set $user_ipset_group dst -j $filter_chain_name");
                push(@cmd_list, "$IPTABLES_CMD -F $filter_chain_name 2>/dev/null");
                push(@cmd_list, "$IPTABLES_CMD -X $filter_chain_name 2>/dev/null");
                $chains_removed++;
            }
        }

        # Добавляем новые цепочки
        foreach my $group_name (keys %group_filters) {
            # Пропускаем фильтры, которых нет у пользователей данного шлюза
            next if (!exists $lists{$group_name});
            if (!exists($cur_chain{$group_name}) and $groups{$group_name}->{instance_id} eq $filter_instance->{id}) {
                my $filter_chain_name = $IPTABLES_CHAIN_PREFIX . $group_name;
                my $user_ipset_group = $IPTABLES_TABLE_NAME . "_" . $group_name;
                log_info($gate_ident."ADD iptables chain link: $group_name -> $instance_name");
                push(@cmd_list, "$IPTABLES_CMD -N $filter_chain_name 2>/dev/null || true");
                push(@cmd_list, "$IPTABLES_CMD -A $chain_name -m set --match-set $user_ipset_group src -j $filter_chain_name");
                push(@cmd_list, "$IPTABLES_CMD -A $chain_name -m set --match-set $user_ipset_group dst -j $filter_chain_name");
                $chains_created++;
            }
        }
    }

    # Формируем правила для цепочек
    my %chain_rules;
    foreach my $group_name (sort keys %group_filters) {
        next if (!$group_name);

        if (!exists $lists{$group_name}) {
            log_info($gate_ident."Filter group $group_name not found at users this device. Skip create");
            next;
            }

        my %group_filter = %{$group_filters{$group_name}};
        my $group_rules_count = 0;

        foreach my $filter_index (sort keys %group_filter) {
            my $filter = $group_filter{$filter_index};
            my $filter_id=$filter->{filter_id};

            next if (!$filters{$filter_id});
            next if ($filters{$filter_id}->{dns_dst});

            my $chain_name = $IPTABLES_CHAIN_PREFIX . $group_name;
            my $action = $filter->{action} ? 'ACCEPT' : 'REJECT';
            my $proto = $filters{$filter_id}->{proto};
            my $dstport = $filters{$filter_id}->{dstport} || 0;
            my $srcport = $filters{$filter_id}->{srcport} || 0;

            $dstport=~s/\-/:/g;
            $srcport=~s/\-/:/g;

            my $src_rule = '-A '.$chain_name;
            my $dst_rule = '-A '.$chain_name;


            if ($filters{$filter_id}->{dst} and $filters{$filter_id}->{dst} ne '0/0' and $filters{$filter_id}->{dst} !~ /\/\d{1,2}/) { $filters{$filter_id}->{dst} .='/32'; }
            my $dst = $filters{$filter_id}->{dst};

            if (defined $dst && $dst ne '' && $dst ne '0/0') {
                $src_rule .= " -s $dst";
                $dst_rule .= " -d $dst";
            }

            if ($filters{$filter_id}->{proto} and ($filters{$filter_id}->{proto}!~/all/i)) {
                $src_rule=$src_rule." -p ".$filters{$filter_id}->{proto};
                $dst_rule=$dst_rule." -p ".$filters{$filter_id}->{proto};
                }

            if ($dstport ne '0' and $srcport ne '0') {
                        $src_rule=$src_rule." -m multiport --dports ".trim($srcport)." --sports ".trim($dstport);
                        $dst_rule=$dst_rule." -m multiport --sports ".trim($srcport)." --dports ".trim($dstport);
                        }
            if ($dstport eq '0' and $srcport ne '0') {
                        $src_rule=$src_rule." -m multiport --dports ".trim($srcport);
                        $dst_rule=$dst_rule." -m multiport --sports ".trim($srcport);
                        }
            if ($dstport ne '0' and $srcport eq '0') {
                        $src_rule=$src_rule." -m multiport --sports ".trim($dstport);
                        $dst_rule=$dst_rule." -m multiport --dports ".trim($dstport);
                        }

            if ($filter->{action}) {
                $src_rule=$src_rule." -j ACCEPT";
                $dst_rule=$dst_rule." -j ACCEPT";
                } else {
                $src_rule=$src_rule." -j REJECT --reject-with icmp-port-unreachable";
                $dst_rule=$dst_rule." -j REJECT --reject-with icmp-port-unreachable";
                }

            if ($src_rule ne $dst_rule) {
                push(@{$chain_rules{$group_name}},$src_rule);
                push(@{$chain_rules{$group_name}},$dst_rule);
                $group_rules_count += 2;
                } else {
                push(@{$chain_rules{$group_name}},$src_rule);
                $group_rules_count++;
                }
        }
        log_verbose($gate_ident."Prepared $group_rules_count rules for chain $IPTABLES_CHAIN_PREFIX$group_name");
        $rules_added += $group_rules_count;
    }

    # Применяем правила цепочек
    foreach my $group_name (sort keys %group_filters) {
        next if (!$group_name);
        next if (!exists $lists{$group_name});

        my $chain_name = $IPTABLES_CHAIN_PREFIX . $group_name;
        my @cur_filter = get_iptables_chain_rules($chain_name);
        my $chain_ok = 1;

        if (scalar @cur_filter != scalar @{$chain_rules{$group_name}}) {
            $chain_ok = 0;
            log_verbose($gate_ident."Chain $chain_name rules count mismatch (current: ".scalar(@cur_filter).", expected: ".scalar(@{$chain_rules{$group_name}}).")");
        } else {
            # Проверка текущих правил
            for (my $f_index=0; $f_index<scalar(@cur_filter); $f_index++) {
                my $filter_str = trim($cur_filter[$f_index]);
                if (!$chain_rules{$group_name}[$f_index] or $filter_str !~ /$chain_rules{$group_name}[$f_index]/i) {
                    log_error($gate_ident."Check chain $chain_name error! Rule mismatch at position $f_index");
                    log_verbose($gate_ident."Expected: $chain_rules{$group_name}[$f_index]");
                    log_verbose($gate_ident."Current: $filter_str");
                    $chain_ok = 0;
                    last;
                }
            }
        }

        if (!$chain_ok) {
            log_info($gate_ident."RECREATE iptables chain: $chain_name (".scalar(@{$chain_rules{$group_name}})." rules)");
            push(@cmd_list, "$IPTABLES_CMD -F $chain_name 2>/dev/null || true");
            push(@cmd_list, "$IPTABLES_CMD -N $chain_name 2>/dev/null || true");
            foreach my $filter_str (@{$chain_rules{$group_name}}) {
                push(@cmd_list, "$IPTABLES_CMD $filter_str");
            }
        } else {
            log_verbose($gate_ident."Chain $chain_name is up to date");
        }
    }

    log_verbose($gate_ident."IPTables chains: $chains_created created, $chains_removed removed, $rules_added rules total");
}

# ============================================================================
# ВЫПОЛНЕНИЕ КОМАНД
# ============================================================================

my $cmd_executed = 0;
my $cmd_failed = 0;

log_verbose($gate_ident."Executing ipset ".scalar(@cmd_ipset_list)." commands...");

foreach my $cmd (@cmd_ipset_list) {
    log_debug($gate_ident."EXEC: $cmd");
    my %result = do_exec_ref($cmd);
    $cmd_executed++;
    if (%result && $result{status} && $result{status} != 0) {
        log_error($gate_ident."Command failed (exit code $result{status}): $cmd");
        $cmd_failed++;
    }
}

log_verbose($gate_ident."Ipset ommands executed: $cmd_executed total, $cmd_failed failed");

$cmd_executed = 0;
$cmd_failed = 0;

log_verbose($gate_ident."Executing iptables ".scalar(@cmd_list)." commands...");

foreach my $cmd (@cmd_list) {
    log_debug($gate_ident."EXEC: $cmd");
    my %result = do_exec_ref($cmd);
    $cmd_executed++;
    if (%result && $result{status} && $result{status} != 0) {
        log_error($gate_ident."Command failed (exit code $result{status}): $cmd");
        $cmd_failed++;
    }
}

log_verbose($gate_ident."Iptables commands executed: $cmd_executed total, $cmd_failed failed");

# ============================================================================
# ЗАВЕРШЕНИЕ
# ============================================================================

if (IsMyPID($SPID)) { Remove_PID($SPID); };

log_info($gate_ident."=== ACL Sync COMPLETED ===");
log_verbose($gate_ident."Summary: chains=$chains_created created/$chains_removed removed, rules=$rules_added, ipset=$ipset_added added/$ipset_removed removed, commands=$cmd_executed executed/$cmd_failed failed");

do_exit 0;

# ============================================================================
# ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
# ============================================================================

sub get_iptables_jumps {
    my ($chain) = @_;
    my %jumps;
    my %result = do_exec_ref("$IPTABLES_CMD --list-rules $chain");
    my $output = $result{output};
    my @lines = split(/\n/, $output);
    foreach my $line (@lines) {
        next if ($line !~/^\-A\s+/);
        if ($line =~ /\-j\s+(\S+)$/) {
            my $target = $1;
            $target =~ s/^${IPTABLES_CHAIN_PREFIX}//;
            $jumps{$target}++ if ($target);
            }
        }
    log_debug($gate_ident."Get target chain for $chain: ".Dumper(\%jumps));
    return %jumps;
}

sub get_iptables_chain_rules {
    my ($chain) = @_;
    my @rules;
    my %result = do_exec_ref("$IPTABLES_CMD --list-rules  $chain");
    my $output = $result{output};
    my @lines = split(/\n/, $output);
    foreach my $line (@lines) {
        next if ($line !~ /^\-A/);
        push(@rules, trim($line));
    }
    log_debug($gate_ident."Current rules for $chain: ".Dumper(\@rules));
    return @rules;
}

sub get_ipset_members {
    my ($set_name) = @_;
    my @members;
    my %result = do_exec_ref("$IPSET_CMD list $set_name");
    if ($result{status} != 0) {
        do_exec("$IPSET_CMD create $set_name hash:net family inet hashsize 1024 maxelem 2655360 2>/dev/null || true");
        do_exec("$IPSET_CMD flush $set_name 2>/dev/null || true");
        return @members;
        }
    my $output = $result{output};
    while ($output =~ /^(\d+\.\d+\.\d+\.\d+(?:\/\d+)?)\s*$/gm) {
        push(@members, $1);
    }
    log_debug($gate_ident."Current ipset $set_name members:".Dumper(\@members));
    return @members;
}

# ============================================================================
# ФУНКЦИИ ДЛЯ СОХРАНЕНИЯ IPSET В ФАЙЛЫ
# ============================================================================

sub save_ipsets_to_files {
    my ($ipsets_ref) = @_;
    # Создаем директорию если не существует
    unless (-d $IPSET_SAVE_DIR) {
        make_path($IPSET_SAVE_DIR) or die "Cannot create $IPSET_SAVE_DIR: $!";
        log_verbose($gate_ident."Created directory: $IPSET_SAVE_DIR");
    }
    # Сохраняем каждый ipset в отдельный файл
    my $files_saved = 0;
    foreach my $group_name (sort keys %$ipsets_ref) {
        my $set_name = $IPTABLES_TABLE_NAME . '_' . $group_name;
        my $filename = $IPSET_SAVE_DIR . '/' . $group_name . $IPSET_SAVE_EXTENSION;
        my $fh = FileHandle->new();
        if ($fh->open(">$filename")) {
            # Заголовок файла с метаинформацией
            print $fh "# ipset configuration file\n";
            print $fh "# Generated: " . scalar(localtime) . "\n";
            print $fh "# Router: $router_name ($router_ip)\n";
            print $fh "# Set name: $set_name\n";
            print $fh "#\n\n";
            print $fh "create $set_name hash:net family inet hashsize 1024 maxelem 2655360\n";
            # Добавляем все IP адреса
            foreach my $ip (sort keys %{$ipsets_ref->{$group_name}}) {
                print $fh "add $set_name $ip\n";
            }
            $fh->close();
            log_verbose($gate_ident."Saved ipset $group_name to $filename (".scalar(keys %{$ipsets_ref->{$group_name}})." entries)");
            log_info($gate_ident."SAVE ipset file: $filename (".scalar(keys %{$ipsets_ref->{$group_name}})." entries)");
            $files_saved++;
        } else {
            log_error($gate_ident."ERROR: Cannot write to $filename: $!");
        }
    }
    log_verbose($gate_ident."Saved $files_saved ipset configuration files");
}
