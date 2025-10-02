#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
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

        # Пропускаем пустые строки
        next if $line =~ /^\s*$/;

        # Разбиваем строку по разделителям: пробелы, табы, запятые, точки с запятой
        my @fields = split(/[\t,;]+/, $line);

        if ($line_num == 1) {
            # Первая непустая строка — заголовки
            @headers = @fields;
            print "Заголовки: " . join(" | ", map { "'$_'" } @headers) . "\n\n";
            next;
        }

        # Проверяем, совпадает ли количество полей с количеством заголовков
        if (@fields != @headers) {
            warn "Предупреждение: строка $line_num: количество полей (" . scalar(@fields) .
                 ") не совпадает с количеством заголовков (" . scalar(@headers) . ")\n";
            # Дополняем или обрезаем массив, чтобы избежать ошибок
            @fields = @fields[0 .. $#headers] if @fields > @headers;
            while (@fields < @headers) {
                push @fields, '';
            }
        }

        # Создаём ассоциативный массив (hash) для текущей строки
        my %row;
        for my $i (0 .. $#headers) {
            $row{$headers[$i]} = $fields[$i];
        }

        # Добавляем в общий результат
        push @data, \%row;
    }

    return @data;  # возвращаем список ссылок на хеши
}

my @rows=();

# === Основная логика ===
if (@ARGV) {
    foreach my $filename (@ARGV) {
        open(my $fh, '<', $filename) or die "Не могу открыть файл '$filename': $!";
        print "Обработка файла: $filename\n";
        @rows = process_file($fh);
        close($fh);
    }
} else {
    @rows = process_file(\*STDIN);
}

foreach my $record (@rows) {

next if (!exists($record->{ip}));

my $auth_network = $office_networks->match_string($record->{ip});
if (!$auth_network) {
    log_error("Unknown network in request! IP: $record->{ip}");
    next;
    }

my $search_sql = 'SELECT * FROM User_auth WHERE ip="'.$record->{ip}.'" and deleted=0 ORDER BY last_found DESC';
$record->{ip_int}=StrToIp($record->{ip});
if (!exists($record->{'mac'})) {
    delete $record->{'mac'};
    } else {
    $record->{mac}=mac_splitted(isc_mac_simplify($record->{mac}));
    $search_sql = 'SELECT * FROM User_auth WHERE ip="'.$record->{ip}.'" and mac="'.$record->{mac}.'" and deleted=0 ORDER BY last_found DESC';
    }

print "Импортируем:\n";
for my $key (keys %{$record}) {
    print "\t\t$key => $record->{$key}\n";
}
print "\n";

if (exists $record->{dns_name}) {
        my $auth_dns_name = lc(trim($record->{dns_name}));
        $auth_dns_name=~s/\./-/g;
        $auth_dns_name=~s/\//-/g;
        $record->{dns_name} = $auth_dns_name;
        }

#search actual record
my $auth_record = get_record_sql($dbh,$search_sql);
if ($auth_record) {
    update_record($dbh,'User_auth',$record,"id=".$auth_record->{id});
    print "URL: <a href='".$config_ref{stat_url}."/admin/users/edituser.php?id=".$auth_record->{user_id}."'>".$auth_record->{user_id}."</a><br>\n";
    if (exists $record->{dns_name}) {
        my $user_info;
        $user_info->{login}=$record->{dns_name};
        update_record($dbh,'User_list',$user_info,"id=".$auth_record->{user_id});
        my $device;
        $device->{device_name}=$record->{dns_name};
        update_record($dbh,'devices',$device,"user_id=".$auth_record->{user_id});
        }
    if (exists $record->{comments}) {
        my $user_info;
        $user_info->{fio}=$record->{comments};
        update_record($dbh,'User_list',$user_info,"id=".$auth_record->{user_id});
        }
    next;
    }

my $dhcp_record =  {%{$record || {}}};
$dhcp_record->{'type'}='add';

my $res_id = resurrection_auth($dbh,$dhcp_record);
if (!$res_id) {
    db_log_error($dbh,"Error creating an ip address record for:\t\t".Dumper($dhcp_record));
    next;
    }
update_record($dbh,'User_auth',$record,"id=".$res_id);
$auth_record = get_record_sql($dbh,'SELECT * FROM User_auth where id='.$res_id);
if ($auth_record) {
    print "URL: <a href='".$config_ref{stat_url}."/admin/users/edituser.php?id=".$auth_record->{user_id}."'>".$auth_record->{user_id}."</a><br>\n";
    if (exists $record->{dns_name}) {
        my $user_info;
        $user_info->{login}=$record->{dns_name};
        update_record($dbh,'User_list',$user_info,"id=".$auth_record->{user_id});
        my $device;
        $device->{device_name}=$record->{dns_name};
        update_record($dbh,'devices',$device,"user_id=".$auth_record->{user_id});
        }
    if (exists $record->{comments}) {
        my $user_info;
        $user_info->{fio}=$record->{comments};
        update_record($dbh,'User_list',$user_info,"id=".$auth_record->{user_id});
        }
    }
}

exit;
