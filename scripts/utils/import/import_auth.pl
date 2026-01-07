#!/usr/bin/perl

#
# Copyright (C) Roman Dmitriev, rnd@rajven.ru
#

use utf8;
use open ":encoding(utf8)";
use open ':std', ':encoding(UTF-8)';
use Encode;
no warnings 'utf8';
use English;
use FindBin '$Bin';
use lib "/opt/Eye/scripts";
use Data::Dumper;
use eyelib::config;
use eyelib::main;
use eyelib::database;
use eyelib::common;
use eyelib::net_utils;
use Net::Patricia;
use strict;
use warnings;

sub process_file {
    my $fh = shift;
    my @data;
    my @headers;
    my $line_num = 0;

    while (my $line = <$fh>) {
        chomp $line;
        $line_num++;

        next if $line =~ /^\s*$/;

        my @fields = split(/[\t,;]+/, $line);

        if ($line_num == 1) {
            @headers = @fields;
            print "Заголовки: " . join(" | ", map { "'$_'" } @headers) . "\n\n";
            next;
        }

        if (@fields != @headers) {
            warn "Предупреждение: строка $line_num: количество полей (" . scalar(@fields) .
                 ") не совпадает с количеством заголовков (" . scalar(@headers) . ")\n";
            @fields = @fields[0 .. $#headers] if @fields > @headers;
            push @fields, ('') x (@headers - @fields);
        }

        my %row;
        @row{@headers} = @fields;
        push @data, \%row;
    }

    return @data;
}

# === Основная логика ===
my @rows = ();
if (@ARGV) {
    for my $filename (@ARGV) {
        open(my $fh, '<', $filename) or die "Не могу открыть файл '$filename': $!";
        print "Обработка файла: $filename\n";
        push @rows, process_file($fh);
        close $fh;
    }
} else {
    @rows = process_file(\*STDIN);
}

for my $record (@rows) {
    next unless exists $record->{ip};

    my $ip = trim($record->{ip});
    next if !$ip;

    # Проверка сети
    my $auth_network = $office_networks->match_string($ip);
    if (!$auth_network) {
        log_error("Unknown network in request! IP: $ip");
        next;
    }

    # Подготовка записи
    my $ip_int = StrToIp($ip);
    $record->{ip} = $ip;
    $record->{ip_int} = $ip_int;

    if (exists $record->{mac} && defined $record->{mac} && $record->{mac} ne '') {
        $record->{mac} = mac_splitted(isc_mac_simplify($record->{mac}));
    } else {
        delete $record->{mac};
    }

    # Обработка dns_name
    if (exists $record->{dns_name} && defined $record->{dns_name}) {
        my $auth_dns_name = lc(trim($record->{dns_name}));
        $auth_dns_name =~ s/[.\/-]+/-/g;  # заменяем . и / на -
        $record->{dns_name} = $auth_dns_name;
    }

    print "Импортируем:\n";
    for my $key (sort keys %$record) {
        print "\t\t$key => $record->{$key}\n";
    }
    print "\n";

    # === Безопасный поиск записи ===
    my $auth_record;
    if (exists $record->{mac}) {
        $auth_record = get_record_sql(
            $dbh,
            'SELECT * FROM user_auth WHERE ip = ? AND mac = ? AND deleted = 0 ORDER BY last_found DESC',
            $ip, $record->{mac}
        );
    } else {
        $auth_record = get_record_sql(
            $dbh,
            'SELECT * FROM user_auth WHERE ip = ? AND deleted = 0 ORDER BY last_found DESC',
            $ip
        );
    }

    if ($auth_record) {
        # Обновление user_auth
        update_record($dbh, 'user_auth', $record, 'id = ?', $auth_record->{id});
        print "URL: <a href='$config_ref{stat_url}/admin/users/edituser.php?id=$auth_record->{user_id}'>$auth_record->{user_id}</a><br>\n";

        my $user_id = $auth_record->{user_id};

        # Обновление user_list и devices
        if (exists $record->{dns_name}) {
            update_record($dbh, 'user_list', { login => $record->{dns_name} }, 'id = ?', $user_id);
            update_record($dbh, 'devices', { device_name => $record->{dns_name} }, 'user_id = ?', $user_id);
        }

        if (exists $record->{description}) {
            update_record($dbh, 'user_list', { fio => $record->{description} }, 'id = ?', $user_id);
        }

        next;
    }

    # === Создание новой записи ===
    my $dhcp_record = { %$record, type => 'add' };
    my $res_id = resurrection_auth($dbh, $dhcp_record);

    if (!$res_id) {
        db_log_error($dbh, "Error creating an ip address record for:\t\t" . Dumper($dhcp_record));
        next;
    }

    update_record($dbh, 'user_auth', $record, 'id = ?', $res_id);
    $auth_record = get_record_sql($dbh, 'SELECT * FROM user_auth WHERE id = ?', $res_id);

    if ($auth_record) {
        print "URL: <a href='$config_ref{stat_url}/admin/users/edituser.php?id=$auth_record->{user_id}'>$auth_record->{user_id}</a><br>\n";
        my $user_id = $auth_record->{user_id};

        if (exists $record->{dns_name}) {
            update_record($dbh, 'user_list', { login => $record->{dns_name} }, 'id = ?', $user_id);
            update_record($dbh, 'devices', { device_name => $record->{dns_name} }, 'user_id = ?', $user_id);
        }

        if (exists $record->{description}) {
            update_record($dbh, 'user_list', { fio => $record->{description} }, 'id = ?', $user_id);
        }
    }
}

exit;
