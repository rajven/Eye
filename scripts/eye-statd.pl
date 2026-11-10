#!/usr/bin/perl -w

use utf8;
use strict;
use warnings;
use Encode;
use open qw(:std :encoding(UTF-8));
no warnings 'utf8';

use English;
use FindBin '$Bin';
use lib "/opt/Eye/scripts";
use DBI;
use Time::Local;
use Net::Patricia;
use Data::Dumper;
use Date::Parse;
use DateTime;
use POSIX ":sys_wait_h";
use IO::Socket::IP;
use Socket qw(AF_INET6 inet_ntop);
use eyelib::config;
use eyelib::main;
use eyelib::logconfig;
use eyelib::net_utils;
use eyelib::database;
use eyelib::common;
use eyelib::snmp;

use constant {
    MUTE_CHECK_INTERVAL => 120,
    MUTE_TIMEOUT        => 300,
    TEMPLATE_TTL        => 900,
    DB_BATCH_SIZE       => 500,
    MAX_READ_SIZE       => 9216,
};

my $proc_name = $MY_NAME;
$proc_name =~ s/\.[^.]+$//;

my $pid_file = '/run/eye-statd/'.$proc_name;

my @router_ref = ();
my @interfaces = ();

my %proto_map = (
    1  => 'ICMP',    2  => 'IGMP',    6  => 'TCP',
    17 => 'UDP',     47 => 'GRE',     50 => 'ESP',
    51 => 'AH',      89 => 'OSPF',    88 => 'EIGRP'
);

my %mute;
my $last_mute_prune = 0;

my %routers_svi;
my %routers_by_ip;
my %routers;

my %wan_dev;
my %lan_dev;

my @traffic = ();
our $child_count = 0;

my $check_interval = 10;
my $reload_config = 0;
our $flush_scheduled = 0;

# подавление спама в лог при пропуске форка
my $last_skip_log = 0;

# счётчик сбросов, каждый 5-й обновляет SNMP-карты роутеров
my $flush_counter = 0;

#user statistics for cached data
my %user_stats;
my %wan_stats;

my $timeshift = get_option($dbh, 55) * 60;
if (!$timeshift or $timeshift < 60) { $timeshift = 60; }

my $save_path = get_option($dbh, 72);

#traffic detail aggregation mode (rows are merged within one flush interval):
# 0 - no aggregation, one DB row per netflow record (legacy behaviour)
# 1 - aggregate by user/router/proto/IP pair/service port (recommended, ephemeral ports are zeroed)
# 2 - aggregate by user/router/proto/service port only (max compression, remote IPs are not stored)
my $detail_aggregation = 1;

#save traffic to DB
my $traf_lastflush = time();

# NetFlow
my $server_port = 2055;
my $netflow5_header_len = 24;
my $netflow5_flowrec_len = 48;
# fields of one flow record, the repeat of the unpack below
my $netflow5_fields = 20;
my $netflow9_header_len = 20;
my $netflow9_templates = {};

# TTL шаблонов NetFlow v9 — мягкая чистка раз в 15 минут
my $template_last_prune = time();

# reap dead children
$SIG{CHLD} = \&REAPER;
$SIG{TERM} = \&TERM;
$SIG{INT}  = \&TERM;
$SIG{HUP}  = sub { $reload_config = 1; };

if (IsNotRun($pid_file)) {
    Add_PID($pid_file);
} else {
    print "Daemon $MY_NAME already running!\n";
    exit 100;
}

# диагностика при смерти flush-ребёнка — данные потеряны
sub REAPER {
    while ((my $pid = waitpid(-1, WNOHANG)) > 0) {
        $child_count--;  # Уменьшаем счётчик
        my $exit = $? >> 8;
        my $sig  = $? & 127;
        if ($sig) {
            log_warning("Flush child $pid died by signal $sig — flushed data lost");
        } elsif ($exit != 0) {
            log_warning("Flush child $pid exited with code $exit — flushed data may be lost");
        }
    }
    $SIG{CHLD} = \&REAPER;
}

# ждём всех детей до конца, данные не теряем
sub TERM {
    print "SIGTERM received\n";
    flush_traffic(1);
    log_verbose("Waiting for flush children to finish...");
    while (wait() != -1) {}
    log_verbose("All flush children finished");
    if (IsMyPID($pid_file)) { Remove_PID($pid_file); }
    exit 0;
}

sub refresh_config {
    # the snmp walk of the routers is the costly part of the reload: every router is
    # asked for its own interface addresses, and one walk costs seconds. the flush
    # child inherits the maps of the parent through the fork, so it reloads the base
    # data only and skips the walk (call with 0)
    my $with_snmp = shift;
    $with_snmp = 1 if (!defined $with_snmp);

    log_verbose("Reloading configuration from database...");

    # Create new database handle. If we can't connect, die()
    my $hdb = init_db();
    log_debug("Database connection established");

    InitSubnets();
    log_debug("Subnets initialized");

    init_option($hdb);
    log_debug("Global options reloaded");

    # a directory for storing traffic details in text form
    $save_path = get_option($hdb, 72);

    # the period for resetting statistics from netflow to billing
    my $timeshift_minutes = get_option($hdb, 55);
    $timeshift = $timeshift_minutes * 60;
    log_verbose("Flush interval set to $timeshift seconds ($timeshift_minutes minutes)");

    @router_ref = get_records_sql($hdb, "SELECT * FROM devices WHERE deleted=0 AND device_type=2 AND snmp_version>0 ORDER by ip");
    log_verbose("Loaded " . scalar(@router_ref) . " active routers");

    @interfaces = get_records_sql($hdb, "SELECT * FROM device_l3_interfaces ORDER by device_id");
    log_verbose("Loaded " . scalar(@interfaces) . " L3 interfaces");

    # Clear all router-related caches
    if ($with_snmp) {
        %routers_svi = ();
        %routers_by_ip = ();
        %routers = ();

        # Rebuild router data
        foreach my $row (@router_ref) {
            setCommunity($row);
            $routers{$row->{id}} = $row;
            my $l3_list = getIpAdEntIfIndex($row->{ip}, $row->{snmp});

            foreach my $router_ip (keys %$l3_list) {
                $routers_svi{$row->{id}}{$l3_list->{$router_ip}}{$router_ip} = 1;
            }

            foreach my $router_ip (keys %$l3_list) {
                $routers_by_ip{$router_ip}->{id} = $row->{id};
                if ($config_ref{save_detail}) {
                    $routers_by_ip{$router_ip}->{save} = $row->{netflow_save};
                } else {
                    $routers_by_ip{$router_ip}->{save} = 0;
                }
            }
        }
        log_debug("Router IP mappings rebuilt");
    }

    # Clear and rebuild WAN/LAN interface mappings
    %wan_dev = ();
    %lan_dev = ();
    foreach my $row (@interfaces) {
        next if (!$row);
        next if (!exists $row->{device_id});
        next if (!exists $row->{snmpin});
        next if (!$row->{device_id} or !$row->{snmpin});
        if ($row->{interface_type}) {
            $wan_dev{$row->{device_id}}{$row->{snmpin}} = 1;
        } else {
            $lan_dev{$row->{device_id}}{$row->{snmpin}} = 1;
        }
    }
    log_debug("WAN/LAN interface mappings rebuilt");

    # Reload user list — FULL RESET of %user_stats
    my @auth_list_ref = get_records_sql($hdb, "SELECT id,ip,save_traf FROM user_auth WHERE deleted=0 ORDER BY id");
    log_verbose("Loaded " . scalar(@auth_list_ref) . " active user ip-addresses");

    my $save_traf_count = 0;
    %user_stats = ();
    foreach my $row (@auth_list_ref) {
        $user_stats{$row->{ip}}{auth_id} = $row->{id};
        if ($config_ref{save_detail}) {
            $user_stats{$row->{ip}}{save_traf} = $row->{save_traf};
        } else {
            $user_stats{$row->{ip}}{save_traf} = 0;
        }
        $save_traf_count++ if ($user_stats{$row->{ip}}{save_traf});
        $user_stats{$row->{ip}}{in}        = 0;
        $user_stats{$row->{ip}}{out}       = 0;
        $user_stats{$row->{ip}}{pkt_in}    = 0;
        $user_stats{$row->{ip}}{pkt_out}   = 0;
        $user_stats{$row->{ip}}{last_found}= 0;
    }
    log_verbose("Found " . $save_traf_count . " active ip-addresses with full save traffic log");

    $hdb->disconnect();
    log_verbose("Configuration reload completed. All runtime statistics cleared.");
}

############### MAIN ##########################

#close default database
$dbh->disconnect();

refresh_config();

my $lsn_nflow;
my $sel = IO::Select->new();

# prepare to listen for NetFlow UDP packets
if ($server_port > 0) {
    # IO::Socket::IP поддерживает и IPv4, и IPv6. Reuse=1 предотвращает ошибки при рестарте
    $lsn_nflow = IO::Socket::IP->new(
        LocalPort => $server_port,
        Proto     => "udp",
        Reuse     => 1
    ) or die "Couldn't be a NetFlow UDP server on port $server_port : $@\n";
    $sel->add($lsn_nflow);
}

my ($datagram, $flags);

while (1) {
    # Ждём новых пакетов НЕ дольше $check_interval секунд
    my @ready = $sel->can_read($check_interval);
    
    # Обрабатываем все готовые сокеты
    foreach my $server (@ready) {
        my $him = $server->recv($datagram, MAX_READ_SIZE);
        next if (!$him);
        
        # peerhost работает универсально для IPv4 и IPv6 в IO::Socket::IP
        my $peer_ip = $server->peerhost;

        if (defined($lsn_nflow) && $server == $lsn_nflow) {
            my ($version) = unpack("n", $datagram);
            eval {
                if ($version == 5) {
                    parse_netflow_v5($datagram, $peer_ip);
                } elsif ($version == 9) {
                    parse_netflow_v9($datagram, $peer_ip);
                } else {
                    print "unknown NetFlow version: $version\n";
                }
                1;
            } or do {
                log_warning("NetFlow packet dropped: $@");
            };
        }
    }

    if (time() - ($last_mute_prune ||= time()) >= MUTE_CHECK_INTERVAL) {
        $last_mute_prune = time();
        foreach my $ip (keys %mute) { 
            delete $mute{$ip} if (time() - $mute{$ip} > MUTE_TIMEOUT); 
        }
    }

    # мягкая чистка шаблонов NetFlow v9 по TTL раз в 15 минут
    if (time() - $template_last_prune >= TEMPLATE_TTL) {
        $template_last_prune = time();
        prune_netflow9_templates();
    }

    # Проверяем, не пора ли сбросить статистику
    my $elapsed = time() - $traf_lastflush;
    if (@traffic && $elapsed >= $timeshift) {
        unless ($flush_scheduled) {
            $flush_scheduled = 1;
            log_verbose("Check flush time. Now: ".time()." Last: $traf_lastflush Diff: $elapsed");
        }
        # каждый 5-й сброс обновляет SNMP-карты роутеров
        my $need_snmp = ($flush_counter % 5 == 0) ? 1 : 0;
        flush_traffic(0, $need_snmp);
    }

    if ($reload_config) {
        $reload_config = 0;
        log_verbose("Reloading config due to HUP signal");
        refresh_config();
    }
}

# одна распаковка всего пакета, без substr/unpack по каждой записи
sub parse_netflow_v5 {
    my $datagram = shift;
    my $ipaddr = shift;

    # защита от короткого пакета
    return if (length($datagram) < $netflow5_header_len);

    my ($version, $count, $sysuptime, $unix_secs, $unix_nsecs,
      $flow_sequence, $engine_type, $engine_id, $aggregation,
      $agg_version) = unpack("nnNNNNCCCC", $datagram);

    my $flowrecs = substr($datagram, $netflow5_header_len);

    my @raw = unpack("(NNNnnNNNNnnCCCCnnCCn)*", $flowrecs);
    my $records = int(scalar(@raw) / $netflow5_fields);
    # защита от битого заголовка — не больше, чем реально распаковано
    $count = $records if ($records < $count);

    my $now = time();
    for (my $i = 0; $i < $count; $i++) {
        my $o = $i * $netflow5_fields;
        my %flow;
        $flow{src_ip}    = inet_ntoa(pack 'N', $raw[$o]);
        $flow{dst_ip}    = inet_ntoa(pack 'N', $raw[$o+1]);
        $flow{snmp_in}   = $raw[$o+3] || 0;
        $flow{snmp_out}  = $raw[$o+4] || 0;
        $flow{pkts}      = $raw[$o+5] || 0;
        $flow{octets}    = $raw[$o+6] || 0;
        $flow{src_port}  = $raw[$o+9] || 0;
        $flow{dst_port}  = $raw[$o+10] || 0;
        $flow{proto}     = $raw[$o+13] || 0;
        $flow{xsrc_ip}   = $flow{src_ip};
        $flow{xdst_ip}   = $flow{dst_ip};
        $flow{starttime} = $now;
        $flow{netflow_v} = '5';
        $flow{ipv}       = '4';
        save_flow($ipaddr, \%flow);
    }
}

sub parse_netflow_v9 {
    my $datagram = shift;
    my $ipaddr = shift;

    # защита от короткого пакета
    return if (length($datagram) < $netflow9_header_len);

    my ($version, $count, $sysuptime, $unix_secs, $seqno, $source_id)
        = unpack("nnNNNN", $datagram);

    my $offset = $netflow9_header_len;
    my $dlen = length($datagram);

    while ($offset + 4 <= $dlen) {
        my ($flowsetid, $flowsetlen) = unpack("nn", substr($datagram, $offset, 4));
        last if ($flowsetlen < 4);
        last if ($offset + $flowsetlen > $dlen);

        # данные флоусета — без 4-байтового заголовка (id + length)
        my $flowsetdata = substr($datagram, $offset + 4, $flowsetlen - 4);
        $offset += $flowsetlen;

        if ($flowsetid == 0) {
            parse_netflow_v9_template_flowset($flowsetdata, $ipaddr, $source_id);
        } elsif ($flowsetid == 1) {
            # Options Template FlowSet - игнорируем
        } elsif ($flowsetid > 255) {
            parse_netflow_v9_data_flowset($flowsetid, $flowsetdata, $ipaddr, $source_id);
        } else {
            print "Unknown FlowSet ID $flowsetid found\n";
        }
    }
}

sub parse_netflow_v9_template_flowset {
    my $templatedata = shift;
    my $ipaddr = shift;
    my $source_id = shift;

    my @template_ints = unpack("n*", $templatedata);

    my $i = 0;
    while ($i < scalar @template_ints) {
        my $template_id = $template_ints[$i];
        my $fldcount = $template_ints[$i+1];

        last if (!defined($template_id) || !defined($fldcount));
        last if ($fldcount == 0);
        last if (($i + 2 + $fldcount*2) > scalar(@template_ints));

        my $template = [@template_ints[($i+2) .. ($i+2+$fldcount*2-1)]];
        $netflow9_templates->{$ipaddr}->{$source_id}->{$template_id}->{'template'} = $template;

        my $totallen = 0;
        for (my $j = 1; $j < scalar @$template; $j += 2) {
            $totallen += $template->[$j];
        }

        $netflow9_templates->{$ipaddr}->{$source_id}->{$template_id}->{'len'} = $totallen;
        # время последнего обновления шаблона для TTL-чистки
        $netflow9_templates->{$ipaddr}->{$source_id}->{$template_id}->{'ts'}  = time();

        $i += (2 + $fldcount*2);
    }
}

# мягкая чистка шаблонов NetFlow v9 по TTL
sub prune_netflow9_templates {
    my $now = time();
    foreach my $ip (keys %$netflow9_templates) {
        foreach my $src (keys %{$netflow9_templates->{$ip}}) {
            foreach my $tid (keys %{$netflow9_templates->{$ip}{$src}}) {
                my $ts = $netflow9_templates->{$ip}{$src}{$tid}{'ts'} || 0;
                if ($now - $ts > TEMPLATE_TTL) {
                    delete $netflow9_templates->{$ip}{$src}{$tid};
                }
            }
            delete $netflow9_templates->{$ip}{$src} if (!keys %{$netflow9_templates->{$ip}{$src}});
        }
        delete $netflow9_templates->{$ip} if (!keys %{$netflow9_templates->{$ip}});
    }
}

sub parse_netflow_v9_data_flowset {
    my ($flowsetid, $flowsetdata, $ipaddr, $source_id) = @_;

    my $template = $netflow9_templates->{$ipaddr}->{$source_id}->{$flowsetid}->{'template'};
    unless (defined $template) {
        return;
    }

    my $len = $netflow9_templates->{$ipaddr}->{$source_id}->{$flowsetid}->{'len'};
    # защита от битой длины шаблона
    return if (!$len || $len <= 0);

    my $offset = 0;
    my $datalen = length($flowsetdata);
    my $now = time();

    while (($offset + $len) <= $datalen) {
        my %flow = (
            netflow_v => '9',
            starttime => $now,
            ipv      => '4',
            snmp_in  => 0,
            snmp_out => 0,
            octets   => 0,
            pkts     => 0,
            proto    => 0,
            src_port => 0,
            dst_port => 0,
        );

        for (my $i = 0; $i < scalar @$template; $i += 2) {
            my $field_type = $template->[$i];
            my $field_length = $template->[$i+1];
            
            # Проверка на выход за границы данных перед unpack
            last if ($offset + $field_length > $datalen);
            
            my $value = substr($flowsetdata, $offset, $field_length);
            $offset += $field_length;

            if ($field_type == 1) { # IN_BYTES
                $flow{octets} = (length($value) == 8) ? unpack("Q>", $value) : 
                                (length($value) == 4) ? unpack("N", $value) : 0;
            }
            elsif ($field_type == 2) { # IN_PACKETS
                $flow{pkts} = (length($value) == 8) ? unpack("Q>", $value) : 
                              (length($value) == 4) ? unpack("N", $value) : 0;
            }
            elsif ($field_type == 4) { # IN_PROTOCOL
                $flow{proto} = (length($value) >= 1) ? unpack("C", $value) : 0;
            }
            elsif ($field_type == 7) { # L4_SRC_PORT
                $flow{src_port} = (length($value) >= 2) ? unpack("n", $value) : 0;
            }
            elsif ($field_type == 8) { # IPV4_SRC_ADDR
                $flow{src_ip} = inet_ntop(AF_INET, $value);
            }
            elsif ($field_type == 10) { # INPUT_SNMP
                $flow{snmp_in} = (length($value) == 4) ? unpack("N", $value) : 
                                 (length($value) >= 2) ? unpack("n", $value) : 0;
            }
            elsif ($field_type == 11) { # L4_DST_PORT
                $flow{dst_port} = (length($value) >= 2) ? unpack("n", $value) : 0;
            }
            elsif ($field_type == 12) { # IPV4_DST_ADDR
                $flow{dst_ip} = inet_ntop(AF_INET, $value);
            }
            elsif ($field_type == 14) { # OUTPUT_SNMP
                $flow{snmp_out} = (length($value) == 4) ? unpack("N", $value) : 
                                  (length($value) >= 2) ? unpack("n", $value) : 0;
            }
            elsif ($field_type == 32) { # ICMP_TYPE
                $flow{icmp_type} = (length($value) >= 1) ? unpack("C", $value) : 0;
            }
            elsif ($field_type == 33) { # ICMP_CODE
                $flow{icmp_code} = (length($value) >= 1) ? unpack("C", $value) : 0;
            }
            elsif ($field_type == 60) { # IP_PROTOCOL_VERSION
                my $ipversion = (length($value) >= 1) ? unpack("C", $value) : 4;
                if ($ipversion == 6) {
                    %flow = ();
                    last;
                }
                $flow{ipv} = $ipversion;
            }
            # Явный пропуск IPv6 адресов, чтобы $offset сдвигался корректно,
            # но данные не пытались интерпретироваться как IPv4
            elsif ($field_type == 27 || $field_type == 28) { 
                next; 
            }
            elsif ($field_type == 225) { # XLATE_SRC_ADDR_IPV4
                $flow{xsrc_ip} = inet_ntop(AF_INET, $value);
            }
            elsif ($field_type == 226) { # XLATE_DST_ADDR_IPV4
                $flow{xdst_ip} = inet_ntop(AF_INET, $value);
            }
        }

        # Обработка не-TCP/UDP трафика
        if ($flow{proto} == 1) {          # ICMP
            $flow{src_port} = $flow{icmp_type} || 0;
            $flow{dst_port} = $flow{icmp_code} || 0;
        }
        elsif ($flow{proto} == 2) {       # IGMP
            $flow{src_port} = $flow{igmp_type} || 0;
        }
        elsif ($flow{proto} == 47) {      # GRE
            $flow{src_port} = $flow{gre_version} || 0;
            $flow{dst_port} = $flow{gre_key} || 0;
        }
        elsif ($flow{proto} == 50 || $flow{proto} == 51) { # ESP / AH
            $flow{src_port} = $flow{dst_port} = 0;
        }
        elsif ($flow{proto} == 89) {      # OSPF
            $flow{src_port} = $flow{dst_port} = 0;
        }

        # Сохраняем только валидные потоки
        if (%flow && exists $flow{src_ip} && exists $flow{dst_ip}) {
            save_flow($ipaddr, \%flow);
        }
    }
}

sub get_proto_name {
    my ($proto_num) = @_;
    return $proto_map{$proto_num} || "Proto-$proto_num";
}

sub save_flow {
    my $router_ip = shift;
    my $flow = shift;

    if ($router_ip !~ /^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/) {
        $router_ip = inet_ntoa($router_ip);
    }

    $flow->{direction} = '0';
    my $router_id;
    
    if (exists $routers_by_ip{$router_ip}) {
        $router_id = $routers_by_ip{$router_ip}{id};
        $flow->{router_ip} = $router_ip;
        $flow->{device_id} = $router_id;
        $flow->{save} = $routers_by_ip{$router_ip}{save};
    } else {
        if (!exists $mute{$router_ip}) {
            $mute{$router_ip} = time();
        }
        if (abs(time() - $mute{$router_ip}) >= MUTE_CHECK_INTERVAL) {
            $mute{$router_ip} = time();
            log_warning("Found unknown router ip [".$router_ip."] in netflow!");
        }
        return;
    }

    #skip local traffic for router
    if (!exists $wan_dev{$router_id}->{$flow->{snmp_out}} and !exists $wan_dev{$router_id}->{$flow->{snmp_in}}) { 
        return; 
    }

    #detect traffic direction
    if (exists $wan_dev{$router_id}->{$flow->{snmp_out}}) { 
        $flow->{direction} = 1; 
    }

    push(@traffic, $flow);
}

sub flush_traffic {
    my $force = shift || 0;
    my $need_snmp = shift || 0;

    if (!$force && $child_count > 0) {
        if (time() - $last_skip_log >= 60) {
            $last_skip_log = time();
            log_verbose("Child exists ($child_count), skipping fork");
        }
        return 1;
    }

    my $pid = fork();

    if (!defined $pid) {
        log_error("Fork failed: $!");
        $flush_scheduled = 0;
        return 1;
    }

    if ($pid > 0) {
        # --- РОДИТЕЛЬСКИЙ ПРОЦЕСС ---
        log_verbose("Parent $$: forked child $pid");
        $child_count++;
        @traffic = (); 
        $traf_lastflush = time();
        $flush_scheduled = 0;
        $flush_counter++;
        return 0;
    }

    # --- ДОЧЕРНИЙ ПРОЦЕСС ---
    my @flush_table = @traffic;

    $SIG{HUP}  = 'IGNORE';
    $SIG{TERM} = 'DEFAULT';
    # предотвращает zombi-процессы внутри ребенка
    $SIG{CHLD} = 'DEFAULT';

    refresh_config($need_snmp);

    log_verbose("Start flush traffic to DB");

    if ($eyelib::config::debug) {
        log_debug("ROUTERS-SVI:".Dumper(\%routers_svi));
        log_debug("ROUTERS by IP::".Dumper(\%routers_by_ip));
        log_debug("ROUTERS:".Dumper(\%routers));
        log_debug("WAN-DEVS:".Dumper(\%wan_dev));
        log_debug("LAN-DEVS:".Dumper(\%lan_dev));
    }

    log_verbose("Child $$: Start flush traffic to DB");

    my $hdb = init_db();

    my @detail_traffic = ();
    my %detail_agg = ();
    my $detail_ts;
    my %saved_netflow = ();
    %wan_stats = ();
    my %routers_found = ();

    my $last_time = time();
    my $start_time;

    if ($detail_aggregation) {
        my ($sec,$min,$hour,$day,$month,$year) = (localtime($last_time))[0,1,2,3,4,5];
        $detail_ts = sprintf "%04d-%02d-%02d %02d:%02d:%02d", $year+1900, $month+1, $day, $hour, $min, $sec;
    }

    log_verbose("Netflow statistics calculation started for ".scalar @flush_table ." records");

    foreach my $traf_record (@flush_table) {
        my ($auth_id, $l_src_ip, $l_dst_ip, $user_ip, $router_id);

        next if (!$traf_record->{device_id});
        $router_id = $traf_record->{device_id};

        if ($config_ref{save_detail} and $traf_record->{save}) {
            push(@{$saved_netflow{$traf_record->{device_id}}}, 
                 join(';', $traf_record->{starttime}, $traf_record->{proto}, $traf_record->{snmp_in}, 
                      $traf_record->{snmp_out}, $traf_record->{src_ip}, $traf_record->{dst_ip}, 
                      $traf_record->{xsrc_ip}, $traf_record->{xdst_ip}, $traf_record->{src_port}, 
                      $traf_record->{dst_port}, $traf_record->{octets}, $traf_record->{pkts}));
        }

        $routers_found{$router_id} = 1;
        $start_time = $traf_record->{starttime} if (!$start_time);

        #--- router statistics
        if (!$traf_record->{snmp_out} or !$traf_record->{snmp_in}) {
            if (!$traf_record->{snmp_out} and exists $routers_svi{$router_id}{$traf_record->{snmp_in}}{$traf_record->{dst_ip}}) {
                if (!$free_networks->match_string($traf_record->{src_ip})) {
                    $wan_stats{$router_id}{$traf_record->{snmp_in}}{in} = 
                        (exists $wan_stats{$router_id}{$traf_record->{snmp_in}}{in} ? $wan_stats{$router_id}{$traf_record->{snmp_in}}{in} : 0) + $traf_record->{octets};
                }
                next;
            }
            if (!$traf_record->{snmp_in} and exists $routers_svi{$router_id}{$traf_record->{snmp_out}}{$traf_record->{src_ip}}) {
                if (!$free_networks->match_string($traf_record->{dst_ip})) {
                    $wan_stats{$router_id}{$traf_record->{snmp_out}}{out} = 
                        (exists $wan_stats{$router_id}{$traf_record->{snmp_out}}{out} ? $wan_stats{$router_id}{$traf_record->{snmp_out}}{out} : 0) + $traf_record->{octets};
                }
                next;
            }
            next;
        }

        if (exists $wan_dev{$router_id}->{$traf_record->{snmp_out}} and exists $wan_dev{$router_id}->{$traf_record->{snmp_in}}) {
            if (exists $routers_svi{$router_id}{$traf_record->{snmp_out}}{$traf_record->{src_ip}}) {
                if (!$free_networks->match_string($traf_record->{dst_ip})) {
                    $wan_stats{$router_id}{$traf_record->{snmp_out}}{out} = 
                        (exists $wan_stats{$router_id}{$traf_record->{snmp_out}}{out} ? $wan_stats{$router_id}{$traf_record->{snmp_out}}{out} : 0) + $traf_record->{octets};
                }
                next;
            }
            if (exists $routers_svi{$router_id}{$traf_record->{snmp_in}}{$traf_record->{dst_ip}}) {
                if (!$free_networks->match_string($traf_record->{src_ip})) {
                    $wan_stats{$router_id}{$traf_record->{snmp_in}}{in} = 
                        (exists $wan_stats{$router_id}{$traf_record->{snmp_in}}{in} ? $wan_stats{$router_id}{$traf_record->{snmp_in}}{in} : 0) + $traf_record->{octets};
                }
                next;
            }
            next;
        } else {
            if (!$free_networks->match_string($traf_record->{src_ip}) and !$free_networks->match_string($traf_record->{dst_ip})) {
                if ($traf_record->{direction}) {
                    $wan_stats{$router_id}{$traf_record->{snmp_out}}{forward_out} = 
                        (exists $wan_stats{$router_id}{$traf_record->{snmp_out}}{forward_out} ? $wan_stats{$router_id}{$traf_record->{snmp_out}}{forward_out} : 0) + $traf_record->{octets};
                } else {
                    $wan_stats{$router_id}{$traf_record->{snmp_in}}{forward_in} = 
                        (exists $wan_stats{$router_id}{$traf_record->{snmp_in}}{forward_in} ? $wan_stats{$router_id}{$traf_record->{snmp_in}}{forward_in} : 0) + $traf_record->{octets};
                }
            }
        }

        #--- user statistics
        my $free = 0;

        if ($traf_record->{direction}) {
            if (exists $user_stats{$traf_record->{src_ip}}) {
                $user_ip  = $traf_record->{src_ip};
                $l_src_ip = $traf_record->{src_ip};
                $l_dst_ip = $traf_record->{dst_ip};
                $free = $free_networks->match_string($l_dst_ip);
                
                if (!$free) {
                    $user_stats{$user_ip}{$router_id}{out} = 
                        (exists $user_stats{$user_ip}{$router_id}{out} ? $user_stats{$user_ip}{$router_id}{out} : 0) + $traf_record->{octets};
                    $user_stats{$user_ip}{$router_id}{pkt_out} = 
                        (exists $user_stats{$user_ip}{$router_id}{pkt_out} ? $user_stats{$user_ip}{$router_id}{pkt_out} : 0) + $traf_record->{pkts};
                }
            }
            
            if (!$user_ip and $config_ref{add_unknown_user}) {
                next if (exists $routers_by_ip{$traf_record->{src_ip}});
                if (!$office_networks->match_string($traf_record->{src_ip})) {
                    log_debug("Unknown src network ".hash_to_kv_csv($traf_record));
                    next;
                }
                $user_ip = $traf_record->{src_ip};
                $auth_id = new_auth($hdb, $user_ip);
                next if (!$auth_id);
                
                $l_src_ip = $traf_record->{src_ip};
                $l_dst_ip = $traf_record->{dst_ip};
                $user_stats{$user_ip}{auth_id} = $auth_id;
                $user_stats{$user_ip}{$router_id}{in} = 0;
                $user_stats{$user_ip}{$router_id}{pkt_in} = 0;
                $user_stats{$user_ip}{$router_id}{out} = 0;
                $user_stats{$user_ip}{$router_id}{pkt_out} = 0;
                $user_stats{$user_ip}{save_traf} = $config_ref{save_detail};

                $free = $free_networks->match_string($l_dst_ip);
                if (!$free) {
                    $user_stats{$user_ip}{$router_id}{out} = $traf_record->{octets};
                    $user_stats{$user_ip}{$router_id}{pkt_out} = $traf_record->{pkts};
                }
            }
        } else {
            if (exists $user_stats{$traf_record->{xdst_ip}}) {
                $user_ip  = $traf_record->{xdst_ip};
                $l_src_ip = $traf_record->{src_ip};
                $l_dst_ip = $traf_record->{xdst_ip};

                $free = $free_networks->match_string($l_src_ip);
                if (!$free) {
                    $user_stats{$user_ip}{$router_id}{in} = 
                        (exists $user_stats{$user_ip}{$router_id}{in} ? $user_stats{$user_ip}{$router_id}{in} : 0) + $traf_record->{octets};
                    $user_stats{$user_ip}{$router_id}{pkt_in} = 
                        (exists $user_stats{$user_ip}{$router_id}{pkt_in} ? $user_stats{$user_ip}{$router_id}{pkt_in} : 0) + $traf_record->{pkts};
                }
            }
        }

        next unless ($user_ip);

        $last_time = $traf_record->{starttime} || time();
        $user_stats{$user_ip}{last_found} = $last_time;

        next unless ($config_ref{save_detail} and $user_stats{$user_ip}{save_traf});

        my $l_src_ip_aton = StrToIp($l_src_ip);
        my $l_dst_ip_aton = StrToIp($l_dst_ip);
        
        # IPv6 не рассматриваем
        next if (!defined $l_src_ip_aton || !defined $l_dst_ip_aton);
        
        my $d_auth_id = $user_stats{$user_ip}->{auth_id};
        my $d_proto = $traf_record->{proto} || 0;
        my $d_sport = $traf_record->{src_port} || 0;
        my $d_dport = $traf_record->{dst_port} || 0;
        my $agg_key;

        if ($detail_aggregation == 1 or $detail_aggregation == 2) {
            my ($d_src_ip, $d_dst_ip, $d_src_port, $d_dst_port, $svc_port);
            if ($traf_record->{direction}) {
                $d_src_ip = $l_src_ip_aton; $d_src_port = 0; $d_dst_port = $d_dport; $svc_port = $d_dport;
                $d_dst_ip = ($detail_aggregation == 2) ? 0 : $l_dst_ip_aton;
            } else {
                $d_dst_ip = $l_dst_ip_aton; $d_dst_port = 0; $d_src_port = $d_sport; $svc_port = $d_sport;
                $d_src_ip = ($detail_aggregation == 2) ? 0 : $l_src_ip_aton;
            }
            $agg_key = join('|', $d_auth_id, $router_id, $d_proto, $traf_record->{direction}, $d_src_ip, $d_dst_ip, $svc_port);
            
            if (exists $detail_agg{$agg_key}) {
                $detail_agg{$agg_key}[8] += $traf_record->{octets};
                $detail_agg{$agg_key}[9] += $traf_record->{pkts};
            } else {
                $detail_agg{$agg_key} = [ $d_auth_id, $router_id, $detail_ts, $d_proto, $d_src_ip, $d_dst_ip, $d_src_port, $d_dst_port, $traf_record->{octets}, $traf_record->{pkts} ];
            }
        } else {
            my ($sec,$min,$hour,$day,$month,$year,$zone) = (localtime($last_time))[0,1,2,3,4,5];
            my $full_time = sprintf "%04d-%02d-%02d %02d:%02d:%02d", $year+1900, $month+1, $day, $hour, $min, $sec;
            push @detail_traffic, [ $d_auth_id, $router_id, $full_time, $d_proto, $l_src_ip_aton, $l_dst_ip_aton, $d_sport, $d_dport, $traf_record->{octets}, $traf_record->{pkts} ];
        }
    }

    log_debug("The netflow statistics calculation is finished");
    @flush_table = ();

    my ($sec,$min,$hour,$day,$month,$year) = (localtime($last_time))[0,1,2,3,4,5];

    #save netflow
    if ($config_ref{save_detail}) {
        $save_path =~ s/\/$//;
        log_debug("Write netflow started");
        foreach my $dev_id (keys %saved_netflow) {
            my $netflow_file_path = $save_path.'/'.$dev_id.'/'.sprintf "%04d/%02d/%02d/%02d/", $year+1900, $month+1, $day, $hour;
            my $nmin = int($min/10)*10;
            my $netflow_file_name = $netflow_file_path.sprintf "%04d%02d%02d-%02d%02d.csv", $year+1900, $month+1, $day, $hour, $nmin;
            
            if ($saved_netflow{$dev_id} and scalar @{$saved_netflow{$dev_id}}) {
                use File::Path;
                File::Path::make_path($netflow_file_path);
                if (-e $netflow_file_name) {
                    open (my $nd_fh, ">>", $netflow_file_name) or die("Error open file $netflow_file_name!!! die...");
                    binmode($nd_fh, ':utf8');
                    foreach my $row (@{$saved_netflow{$dev_id}}) {
                        next if (!$row);
                        print $nd_fh $row."\n";
                    }
                    close $nd_fh;
                } else {
                    open (my $nd_fh, ">", $netflow_file_name) or die("Error open file $netflow_file_name!!! die...");
                    binmode($nd_fh, ':utf8');
                    print $nd_fh join(';',"time","proto","snmp_in","snmp_out","src_ip","dst_ip","xsrc_ip","xdst_ip","src_port","dst_port","octets","pkts")."\n";
                    foreach my $row (@{$saved_netflow{$dev_id}}) {
                        next if (!$row);
                        print $nd_fh $row."\n";
                    }
                    close $nd_fh;
                }
                @{$saved_netflow{$dev_id}} = ();
            }
        }
        log_debug("Write netflow is finished");
    }
    undef %saved_netflow;

    #save statistics
    my @batch_user_stats = ();
    my @batch_user_stats_update = ();
    my @batch_user_stats_full = ();
    my @batch_auth_status = ();
    my @batch_wan_stats = ();

    log_debug("The user statistics calculation started");

    my %hour_rows = ();
    my %hour_reads = ();

    foreach my $user_ip (keys %user_stats) {
        next if (!exists $user_stats{$user_ip} || !$user_stats{$user_ip}{last_found});
        my $auth_id = $user_stats{$user_ip}{auth_id};

        my ($sec,$min,$hour,$day,$month,$year) = (localtime($user_stats{$user_ip}{last_found}))[0,1,2,3,4,5];
        my $flow_date = sprintf "%04d-%02d-%02d %02d:%02d:%02d", $year+1900, $month+1, $day, $hour, $min, $sec;
        my $flow_hour_start = sprintf "%04d-%02d-%02d %02d:00:00", $year + 1900, $month + 1, $day, $hour;

        push @batch_auth_status, [ $flow_date, $flow_date, $auth_id ];

        foreach my $router_id (keys %routers_found) {
            next if (!exists $user_stats{$user_ip}{$router_id});
            
            my $in = $user_stats{$user_ip}{$router_id}{in} || 0;
            my $out = $user_stats{$user_ip}{$router_id}{out} || 0;
            
            next if ($in + $out == 0);
            
            my $pkt_in = $user_stats{$user_ip}{$router_id}{pkt_in} || 0;
            my $pkt_out = $user_stats{$user_ip}{$router_id}{pkt_out} || 0;
            
            push @batch_user_stats_full, [
                $flow_date, $auth_id, $router_id, $in, $out, $pkt_in, $pkt_out, $timeshift 
            ];
            
            $hour_rows{$auth_id}{$router_id} = [ $flow_hour_start, $in, $out, $pkt_in, $pkt_out ];
            $hour_reads{$flow_hour_start}{$auth_id} = 1;
        }
    }

    my %hour_stat_idx = ();
    foreach my $hour_start (keys %hour_reads) {
        my @auth_ids = sort { $a <=> $b } keys %{$hour_reads{$hour_start}};
        while (@auth_ids) {
            my @part = splice(@auth_ids, 0, DB_BATCH_SIZE);
            my $marks = join(',', ('?') x scalar(@part));
            my @rows = get_records_sql($hdb,
                "SELECT auth_id, router_id, byte_in, byte_out, pkt_in, pkt_out
                   FROM user_stats WHERE ts = ? AND auth_id IN ($marks)",
                $hour_start, @part);
            foreach my $row (@rows) {
                next if (!defined $row || !exists $row->{auth_id});
                next if (exists $hour_stat_idx{$hour_start}{$row->{auth_id}}{$row->{router_id}});
                $hour_stat_idx{$hour_start}{$row->{auth_id}}{$row->{router_id}} = $row;
            }
        }
    }

    %hour_reads = ();

    foreach my $auth_id (keys %hour_rows) {
        foreach my $router_id (keys %{$hour_rows{$auth_id}}) {
            my $period = $hour_rows{$auth_id}{$router_id};
            my $hour_stat = $hour_stat_idx{$period->[0]}{$auth_id}{$router_id};
            
            if (!$hour_stat) {
                push @batch_user_stats, [
                    $period->[0], $auth_id, $router_id,
                    $period->[1], $period->[2], $period->[3], $period->[4], 3600 
                ];
                next;
            }
            push @batch_user_stats_update, [
                ($hour_stat->{byte_in}  || 0) + $period->[1],
                ($hour_stat->{byte_out} || 0) + $period->[2],
                ($hour_stat->{pkt_in}   || 0) + $period->[3],
                ($hour_stat->{pkt_out}  || 0) + $period->[4],
                $period->[0], $auth_id, $router_id 
            ];
        }
    }

    log_debug("User calculation is finished");

    log_debug("Routers statistics started");
    foreach my $router_id (keys %wan_stats) {
        my ($sec,$min,$hour,$day,$month,$year) = (localtime($start_time))[0,1,2,3,4,5];
        my $flow_date = sprintf "%04d-%02d-%02d %02d:%02d:%02d", $year+1900, $month+1, $day, $hour, $min, $sec;
        
        foreach my $int_id (keys %{$wan_stats{$router_id}}) {
            my $in = $wan_stats{$router_id}{$int_id}{in} || 0;
            my $out = $wan_stats{$router_id}{$int_id}{out} || 0;
            my $f_in = $wan_stats{$router_id}{$int_id}{forward_in} || 0;
            my $f_out = $wan_stats{$router_id}{$int_id}{forward_out} || 0;

            next if ($in + $out + $f_in + $f_out == 0);

            push @batch_wan_stats, [
                $flow_date, $router_id, $int_id, $in, $out, $f_in, $f_out
            ];
        }
    }
    log_debug("Router statistics is finished");

    log_verbose("Try update user_auth table for ".scalar @batch_auth_status. " records");
    batch_db_sql_cached("UPDATE user_auth SET arp_found= ?, last_found= ? WHERE id= ?", \@batch_auth_status);
    log_verbose("Finished");

    log_verbose("Try update user_stats_full table for ".scalar @batch_user_stats_full. " records");
    batch_db_sql_cached("INSERT INTO user_stats_full (ts,auth_id,router_id,byte_in,byte_out,pkt_in,pkt_out,step) VALUES( ?, ?, ?, ?, ?, ?, ?, ?)", \@batch_user_stats_full);
    log_verbose("Finished");

    log_verbose("Try create new records in user_stats table for ".scalar @batch_user_stats. " records");
    batch_db_sql_cached("INSERT INTO user_stats (ts,auth_id,router_id,byte_in,byte_out,pkt_in,pkt_out,step) VALUES( ?, ?, ?, ?, ?, ?, ? ,?)", \@batch_user_stats);
    log_verbose("Finished");

    log_verbose("Try update user_stats table for ".scalar @batch_user_stats_update. " records");
    batch_db_sql_cached("UPDATE user_stats SET byte_in= ?, byte_out= ?, pkt_in = ?, pkt_out = ? WHERE ts = ? AND auth_id= ? AND router_id= ?", \@batch_user_stats_update);
    log_verbose("Finished");

    log_verbose("Try create new records in wan_stats table for ".scalar @batch_wan_stats. " records");
    batch_db_sql_cached("INSERT INTO wan_stats (ts,router_id,interface_id,bytes_in,bytes_out,forward_in,forward_out) VALUES( ?, ?, ?, ?, ?, ?, ?)", \@batch_wan_stats);
    log_verbose("Finished");

    if ($config_ref{enable_quotes}) {
        log_info("Recalc quotes started");
        foreach my $router_id (keys %routers_found) { recalc_quotes($hdb, $router_id); }
        log_info("Recalc quotes stopped");
    }

    if (%detail_agg) {
        log_verbose("Detail aggregation: ".scalar(keys %detail_agg)." aggregated detail records");
        push @detail_traffic, $_ for (values %detail_agg);
        undef %detail_agg;
    }

    if (scalar(@detail_traffic)) {
        log_verbose("Start write traffic detail to DB. ".scalar @detail_traffic." lines count");
        my $traffic_fields = ['auth_id', 'router_id', 'ts', 'proto', 'src_ip', 'dst_ip', 'src_port', 'dst_port', 'bytes', 'pkt'];
        unshift @detail_traffic, $traffic_fields;
        batch_db_sql_csv("traffic_detail", \@detail_traffic);
        undef @detail_traffic;
        log_verbose("Write traffic detail is finished");
    }

    $hdb->disconnect();
    log_verbose("Flush traffic to DB is finished");

    exit 0;
}

if (IsMyPID($pid_file)) { Remove_PID($pid_file); }
exit;
