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
use Data::Dumper;
use eyelib::config;
use eyelib::main;
use eyelib::database;
use eyelib::common;
use eyelib::net_utils;
use strict;
use Getopt::Long;
use Proc::Daemon;
use Cwd;
use Net::Netmask;
use DateTime;

my $mute_time=300;

my $pf = '/run/eye/stat-sync.pid';

my $daemon = Proc::Daemon->new(
        pid_file => $pf,
        work_dir => $HOME_DIR
);

# are you running?  Returns 0 if not.
my $pid = $daemon->Status($pf);

my $daemonize = 1;

GetOptions(
    'daemon!' => \$daemonize,
    "help"    => \&usage,
    "reload"  => \&reload,
    "restart" => \&restart,
    "start"   => \&run,
    "status"  => \&status,
    "stop"    => \&stop
) or &usage;

exit(0);

### Analyze DHCP requests

# === 1. Получение событий из очереди DHCP ===
sub _fetch_dhcp_queue {
    my ($dbh) = @_;
    my @events = get_records_sql($dbh, "SELECT * FROM dhcp_queue");
    log_debug("Fetched " . scalar(@events) . " DHCP event(s) from queue") if @events;
    return @events;
}

# === 2. Проверка, нужно ли подавлять событие (mute logic) ===
sub _should_mute_dhcp_event {
    my ($ip, $new_action, $leases_ref, $mute_time) = @_;
    return 0 unless exists $leases_ref->{$ip};
    my $last_event = $leases_ref->{$ip};
    my $time_diff = time() - ($last_event->{last_time} // 0);
    # Подавляем, если:
    # - действие не отличается от предыдущего
    # - и прошло меньше $mute_time секунд
    if ($last_event->{action} eq $new_action && $time_diff <= $mute_time) {
        log_debug("Muting DHCP event for IP $ip: same as recent opposite action (diff: ${time_diff}s)");
        return 1;
    }
    return 0;
}

# === 3. Обработка одного DHCP-события ===
sub _process_single_dhcp_event {
    my ($dbh, $event, $leases_ref, $mute_time) = @_;
    my $ip = $event->{ip};
    my $action = $event->{action};
    # Проверка на подавление
    if (_should_mute_dhcp_event($ip, $action, $leases_ref, $mute_time)) {
        # Всё равно обновляем last_time, чтобы не накапливать старые записи
        $leases_ref->{$ip} = $event;
        $leases_ref->{$ip}->{last_time} //= time();
        return;
    }
    # Обработка запроса
    log_debug("Processing DHCP event: action=$action, ip=$ip, mac=$event->{mac}");
    my $dhcp_record = process_dhcp_request(
        $dbh,
        $action,
        $event->{mac},
        $ip,
        $event->{dhcp_hostname},
        '', '', ''
    );
    # Удаляем из очереди
    my $rows = do_sql($dbh, "DELETE FROM dhcp_queue WHERE id = ?", $event->{id});
    log_debug("Deleted DHCP event ID $event->{id} from queue (affected rows: $rows)");
    # Проверяем, что запись создана/обновлена
    if (!$dhcp_record or !$dhcp_record->{auth_id} ){
        log_error("User ip auth record not created by DHCP request for ip: $ip mac: $event->{mac}!");
        return;
        }
    # Обновляем кэш последних событий
    $leases_ref->{$ip} = $event;
    $leases_ref->{$ip}->{last_time} //= time();
}

# === 4. Основная функция обработки очереди DHCP ===
sub process_dhcp_queue {
    my ($hdb, $leases_ref, $mute_time) = @_;
    # Получаем все события
    my @dhcp_events = _fetch_dhcp_queue($hdb);
    return unless @dhcp_events;
    log_info("Processing " . scalar(@dhcp_events) . " DHCP event(s) from queue");
    # Обрабатываем каждое событие
    foreach my $dhcp (@dhcp_events) {
        eval {
            _process_single_dhcp_event($hdb, $dhcp, $leases_ref, $mute_time);
        };
        if ($@) {
            log_error("Failed to process DHCP event ID $dhcp->{id}: $@");
            # Не прерываем остальные события
        }
    }
    log_info("DHCP queue processing completed");
}

### UPDATE user state

# === 1. Обнуление флагов changed для динамических/хостспот-пользователей ===
sub _reset_changed_flags_for_default_ous {
    my ($dbh, $default_user_ou_id, $default_hotspot_ou_id) = @_;
    if (!defined $default_user_ou_id || !defined $default_hotspot_ou_id) {
        log_warning("Skipping reset of changed flags: default OU IDs not set");
        return;
    }
    my $rows1 = do_sql($dbh, "UPDATE user_auth SET changed = 0 WHERE ou_id = ? OR ou_id = ?", $default_user_ou_id, $default_hotspot_ou_id);
    my $rows2 = do_sql($dbh, "UPDATE user_auth SET dhcp_changed = 0 WHERE ou_id = ? OR ou_id = ?", $default_user_ou_id, $default_hotspot_ou_id);
    log_debug("Reset 'changed' flags for $rows1 records, 'dhcp_changed' for $rows2 records (default OUs)");
}

# === 2. Сброс флагов changed для IP вне офисных сетей ===
sub _clear_changed_flags_for_non_office_ips {
    my ($dbh, $office_networks) = @_;
    my @all_changed = get_records_sql($dbh, "SELECT id, ip FROM user_auth WHERE changed = 1 OR dhcp_changed = 1");
    return unless @all_changed;
    my $cleared = 0;
    for my $row (@all_changed) {
        next if !$row->{ip};
        next if $office_networks->match_string($row->{ip});  # IP в офисной сети — оставляем
        my $rows = do_sql($dbh, "UPDATE user_auth SET changed = 0, dhcp_changed = 0 WHERE id = ?", $row->{id});
        $cleared += $rows;
    }
    if ($cleared) {
        log_info("Cleared 'changed' flags for $cleared records with non-office IPs");
    }
}

# === 3. Обработка DHCP-изменений ===
sub _process_dhcp_changes {
    my ($dbh) = @_;
    my $changed = get_record_sql($dbh, "SELECT COUNT(*) AS c_count FROM user_auth WHERE dhcp_changed = 1");
    my $count = $changed ? ($changed->{c_count} // 0) : 0;
    return if $count == 0;
    log_info("Found $count record(s) with dhcp_changed=1");
    # Сбрасываем флаги
    do_sql($dbh, "UPDATE user_auth SET dhcp_changed = 0");
    # Запускаем внешний скрипт
    my $dhcp_exec = get_option($dbh, 38);
    if (!$dhcp_exec) {
        log_warning("DHCP sync script (opt 38) not configured");
        return;
    }
    my %result = do_exec_ref("/usr/bin/sudo $dhcp_exec");
    if ($result{status} != 0) {
        log_error("DHCP config sync failed: " . ($result{stderr} // 'no error output'));
    } else {
        log_info("DHCP config synced successfully");
    }
}

# === 4. Обработка ACL-изменений ===
sub _process_acl_changes {
    my ($dbh) = @_;
    my $changed = get_record_sql($dbh, "SELECT COUNT(*) AS c_count FROM user_auth WHERE changed = 1");
    my $count = $changed ? ($changed->{c_count} // 0) : 0;
    return if $count == 0;
    log_info("Found $count record(s) with changed=1 (ACL/DHCP)");
    my $acl_exec = get_option($dbh, 37);
    if (!$acl_exec) {
        log_warning("ACL sync script (opt 37) not configured");
        return;
    }
    my %result = do_exec_ref("$acl_exec --changes-only");
    if ($result{status} != 0) {
        log_error("Gateway ACL sync failed: " . ($result{stderr} // 'no error output'));
    } else {
        log_info("Gateway ACL synced successfully");
    }
}

# === 5. Обработка DNS-очереди ===
sub _process_dns_queue {
    my ($dbh) = @_;
    my @dns_changed = get_records_sql($dbh, "SELECT DISTINCT auth_id FROM dns_queue");
    return unless @dns_changed;
    log_info("Processing DNS queue for " . scalar(@dns_changed) . " auth_id(s)");
    for my $auth (@dns_changed) {
        my $auth_id = $auth->{auth_id};
        eval {
            update_dns_record($dbh, $auth_id);
            do_sql($dbh, "DELETE FROM dns_queue WHERE auth_id = ?", $auth_id);
            log_info("DNS processed and cleared for auth_id: $auth_id");
        };
        if ($@) {
            log_error("Failed to process DNS for auth_id=$auth_id: $@");
        }
    }
}

# === 6. Очистка временных записей user_auth ===
sub _cleanup_expired_dynamic_users {
    my ($dbh) = @_;
    # Используем параметризованный запрос вместо quote()
    my $now_str = DateTime->now(time_zone => 'local')->strftime('%Y-%m-%d %H:%M:%S');
    my @users_auth = get_records_sql($dbh, 
        "SELECT id, user_id, end_life FROM user_auth WHERE deleted = 0 AND dynamic = 1 AND end_life <= ?", 
        $now_str
    );
    return unless @users_auth;
    log_info("Cleaning up " . scalar(@users_auth) . " expired dynamic user_auth records");
    for my $row (@users_auth) {
        eval {
            delete_user_auth($dbh, $row->{id});
            db_log_info($dbh, "Removed dynamic user auth record for auth_id: $row->{id} by end_life time: $row->{end_life}", $row->{id});
            # Удаляем пользователя, если больше нет активных auth-записей
            my $u_count = get_count_records($dbh, 'user_auth', 'deleted = 0 AND user_id = ?', $row->{user_id});
            if ($u_count == 0) {
                delete_user($dbh, $row->{user_id});
                log_info("Deleted orphaned user_id: $row->{user_id}");
            }
        };
        if ($@) {
            log_error("Error cleaning up auth_id $row->{id}: $@");
        }
    }
}

# === 7. Основная функция обновления конфигурации ===
sub refresh_config_if_needed {
    my ($hdb, $last_refresh_ref, $default_user_ou_id, $default_hotspot_ou_id, $office_networks) = @_;
    return if time() - $$last_refresh_ref < 60;
    log_debug("Starting config refresh cycle");
    # Обновляем опции
    init_option($hdb);
    my $urgent_sync = get_option($hdb, 50);
    if ($urgent_sync) {
        log_info("Urgent sync triggered (option 50)");
        _reset_changed_flags_for_default_ous($hdb, $default_user_ou_id, $default_hotspot_ou_id);
        _clear_changed_flags_for_non_office_ips($hdb, $office_networks);
        _process_dhcp_changes($hdb);
        _process_acl_changes($hdb);
    }
    _process_dns_queue($hdb);
    _cleanup_expired_dynamic_users($hdb);
    $$last_refresh_ref = time();
    log_debug("Config refresh cycle completed");
}

sub stop {
        if ($pid) {
                print "Stopping pid $pid...";
                if ($daemon->Kill_Daemon($pf)) {
                        print "Successfully stopped.\n";
                } else {
                        print "Could not find $pid.  Was it running?\n";
                }
         } else {
                print "Not running, nothing to stop.\n";
         }
}

sub status {
        if ($pid) {
                print "Running with pid $pid.\n";
        } else {
                print "Not running.\n";
        }
}

sub run {
if (!$pid) {
    print "Starting...";
    if ($daemonize) {
        # when Init happens, everything under it runs in the child process.
        # this is important when dealing with file handles, due to the fact
        # Proc::Daemon shuts down all open file handles when Init happens.
        # Keep this in mind when laying out your program, particularly if
        # you use filehandles.
        $daemon->Init;
        }

    setpriority(0,0,19);

    my %leases;

    while (1) {

        eval {

        # Create new database handle. If we can't connect, die()
        my $hdb = init_db();

        # Process DHCP queue every 10 seconds
        process_dhcp_queue($hdb, \%leases, $mute_time);


        # Update state every 60 seconds
        refresh_config_if_needed(
            $hdb, 
            \$last_refresh_config, 
            $default_user_ou_id, 
            $default_hotspot_ou_id, 
            $office_networks
            );
        sleep(10);

        };
        if ($@) { log_error("Exception found: $@"); sleep(300); }
        }
    } else {
        print "Already Running with pid $pid\n";
    }
}

sub usage {
    print "usage: stat-sync.pl (start|stop|restart)\n";
    exit(0);
}

sub reload {
    print "reload process not implemented.\n";
}

sub restart {
    stop;
    run;
}
