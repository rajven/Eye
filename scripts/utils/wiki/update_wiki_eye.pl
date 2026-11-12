#!/usr/bin/perl
#
# Синхронизация метаданных устройств в Wiki через API
#
# Запуск: perl /path/to/update_wiki_eye.pl
#

use utf8;
use warnings;
use Encode;
use open qw(:std :encoding(UTF-8));
no warnings 'utf8';

use strict;
use English;
use lib "/opt/Eye/scripts";
use FindBin '$Bin';
use eyelib::config;
use eyelib::database;
use Data::Dumper;
use File::Find;
use File::Basename;
use Fcntl qw(:flock);
use LWP::UserAgent;
use HTTP::Request;
use JSON::XS;
use URI::Escape;
use Encode qw(decode_utf8 encode_utf8);

# Блокировка для предотвращения параллельных запусков
open(SELF, "<", $0) or die "Cannot open $0 - $!";
flock(SELF, LOCK_EX | LOCK_NB) or exit 1;

# Настройка API - аутентификация через параметры
my $api_base = $config_ref{api_base} || 'http://localhost/api.php';
my $api_key  = $config_ref{api_key}  || die "Ошибка: не настроен параметр api_key в конфигурации\n";
my $api_login = $config_ref{api_login} || die "Ошибка: не настроен параметр api_login в конфигурации\n";

# Базовые параметры аутентификации для всех запросов
my $auth_params = "api_key=" . uri_escape($api_key) . "&api_login=" . uri_escape($api_login);

# Инициализация HTTP-клиента
my $ua = LWP::UserAgent->new(
    timeout => 30,
    agent   => 'EyeWikiSync/1.0'
);

my $api_request_url = $api_base."?".$auth_params;

# === Получение пути к вики ===
# Таблица config не отдается через api (она вне белого списка), путь читается
# напрямую: скрипт и так работает на самом сервере eye и читает файлы вики локально
my $wiki_path = get_option($dbh,61);
if (!$wiki_path || !-d $wiki_path) {
    die "Ошибка: путь к вики не настроен или директория не существует: $wiki_path\n";
}

print "Путь к вики: $wiki_path\n\n";

# Поиск подходящих файлов
my %content;
find(\&wanted, $wiki_path);

my $processed = 0;
my $errors    = 0;

foreach my $fname (sort keys %content) {
    print "Analyze $content{$fname}...";
    # Чтение файла
    open(my $fh, '<:encoding(UTF-8)', $content{$fname}) or do {
        warn "Не удалось открыть файл $content{$fname}: $!\n";
        $errors++;
        next;
    };
    my @lines = <$fh>;
    close($fh);
    chomp(@lines);

    # Извлечение IP из метаданных
    my $ip;
    foreach my $line (@lines) {
        if ($line =~ /\%META\:FIELD\{name="DeviceIP"/) {
            if ($line =~ /value="([0-9]{1,3}(?:\.[0-9]{1,3}){3})"/) {
                $ip = $1;
                last;
            }
        }
    }
    print "IP not found!\n" and next unless $ip && is_valid_ipv4($ip);

    print "$ip \n";

    # The wiki page describes a device of eye, so the device row carries the name of
    # the page and the port of the access switch
    my $device_filter = encode_json({ ip => $ip, deleted => 0 });
    my $device_response = api_call($ua, 'GET',
        $api_request_url . "&get=table_record&table=devices&filter=" . uri_escape($device_filter));
    if ($device_response->{error} || !$device_response->{data}) {
        print "Device for ip $ip not found! Err: " . ($device_response->{error} // 'no record') . "\n";
        next;
        }

    my $device = $device_response->{data};
    if (!$device->{wikiname}) {
        print "Device $ip has no wiki page name!\n";
        next;
        }

    # the gateways are described in their own pages
    next if ($device->{wikiname} =~ /^Gateway/);

    my $mac = '';
    my ($device_name, $device_port);
    my $error_msg;

    print "$ip \n";

    # === The port of the parent switch: the uplink port of the device ===
    eval {
        my $uplink_response = api_call($ua, 'GET', $api_request_url . "&get=table_record&table=device_ports&filter="
            . uri_escape(encode_json({ device_id => $device->{id}, uplink => 1 })));
        my $uplink = $uplink_response->{data};

        if ($uplink and $uplink->{target_port_id}) {
            my $parent_port_response = api_call($ua, 'GET', $api_request_url . "&get=table_record&table=device_ports&id=" . $uplink->{target_port_id});
            my $parent_port = $parent_port_response->{data};
            die "Unknown port connection" unless $parent_port;

            my $parent_response = api_call($ua, 'GET', $api_request_url . "&get=table_record&table=devices&id=" . $parent_port->{device_id});
            my $parent_device = $parent_response->{data};
            die "Unknown parent device" unless $parent_device;

            $device_name = $parent_device->{wikiname} || $parent_device->{device_name};
            $device_port = $parent_port->{port};
            }

        # === The equipment of the user has no ports of its own, the address of the
        # owner shows the port of the switch it hangs on ===
        if (!$device_name and $device->{user_id}) {
            my $auth_list_response = api_call($ua, 'GET', $api_request_url . "&get=table_list&table=user_auth&filter="
                . uri_escape(encode_json({ user_id => $device->{user_id}, deleted => 0 })));
            die "no addresses of the user" unless ref($auth_list_response->{data}) eq 'ARRAY';

            foreach my $auth_row (@{ $auth_list_response->{data} }) {
                $mac = $auth_row->{mac} if (!$mac and $auth_row->{mac});
                my $conn_response = api_call($ua, 'GET', $api_request_url . "&get=table_record&table=connections&filter="
                    . uri_escape(encode_json({ auth_id => $auth_row->{id} })));
                next unless $conn_response->{data};

                my $port_response = api_call($ua, 'GET', $api_request_url . "&get=table_record&table=device_ports&id=" . $conn_response->{data}->{port_id});
                my $access_port = $port_response->{data};
                next unless $access_port;

                my $switch_response = api_call($ua, 'GET', $api_request_url . "&get=table_record&table=devices&id=" . $access_port->{device_id});
                my $switch = $switch_response->{data};
                next unless $switch;

                # the address of the device itself carries the mac of the equipment
                $mac = $auth_row->{mac} if ($auth_row->{ip} and $auth_row->{ip} eq $device->{ip} and $auth_row->{mac});

                $device_name = $switch->{wikiname} || $switch->{device_name};
                $device_port = $access_port->{port};
                last;
                }
            }

        die "Unknown connection" unless $device_name;
    };

    if ($@) {
        $error_msg = $@;
        chomp($error_msg);
        print "Error: $error_msg\n";
        next;
    }

    # Подготовка обновленного содержимого файла
    my @wiki_dev;
    my %empty_fields = (parent => 1, parent_port => 1, mac => 1);

    foreach my $line (@lines) {
        if ($line =~ /\%META\:FIELD\{name="Parent"/) {
            $empty_fields{parent} = 0;
            if ($device_name) {
                push(@wiki_dev, '%META:FIELD{name="Parent" title="Parent" value="' . $device_name . '"}%');
                next;
            }
        }
        elsif ($line =~ /\%META\:FIELD\{name="ParentPort"/) {
            $empty_fields{parent_port} = 0;
            if ($device_port) {
                push(@wiki_dev, '%META:FIELD{name="ParentPort" title="Parent Port" value="' . $device_port . '"}%');
                next;
            }
        }
        elsif ($line =~ /\%META\:FIELD\{name="Mac"/) {
            $empty_fields{mac} = 0;
            if ($mac) {
                push(@wiki_dev, '%META:FIELD{name="Mac" title="Mac" value="' . $mac . '"}%');
                next;
            }
        }
        push(@wiki_dev, $line);
    }

    # Добавление отсутствующих полей
    if ($empty_fields{parent} && $device_name) {
        push(@wiki_dev, '%META:FIELD{name="Parent" title="Parent" value="' . $device_name . '"}%');
    }
    if ($empty_fields{parent_port} && $device_port) {
        push(@wiki_dev, '%META:FIELD{name="ParentPort" title="Parent Port" value="' . $device_port . '"}%');
    }
    if ($empty_fields{mac} && $mac) {
        push(@wiki_dev, '%META:FIELD{name="Mac" title="Mac" value="' . $mac . '"}%');
    }

    $device_name  ||= 'None';
    $device_port  ||= 'None';
    print "at $device_name $device_port\n";

    # Запись обновленного файла
    open(my $out_fh, '>:encoding(UTF-8)', $content{$fname}) or do {
        warn "Ошибка записи файла $content{$fname}: $!\n";
        $errors++;
        next;
    };
    print $out_fh $_ . "\n" for @wiki_dev;
    close($out_fh);

    $processed++;
}

print "\n=== ИТОГИ ===\n";
print "Обработано файлов: $processed\n";
print "Ошибок: $errors\n";
print "Синхронизация завершена.\n";

exit 0;

sub api_call {
    my ($ua, $method, $url) = @_;
    my $req = HTTP::Request->new($method => $url);
    my $res = $ua->request($req);
    my $result = {};
    if (!$res->is_success) {
        $result->{error} = $res->status_line;
        return $result;
    }
    eval {
        $result->{data} = decode_json($res->decoded_content);
    };
    if ($@) {
        $result->{error} = "JSON parse error: $@";
    }
    return $result;
}

sub is_valid_ipv4 {
    my ($ip) = @_;
    return $ip =~ /^([0-9]{1,3}\.){3}[0-9]{1,3}$/ &&
           !grep { $_ > 255 } split(/\./, $ip);
}

sub wanted {
    my $filename = $File::Find::name;
    my $dev_name = basename($filename);
    if ($dev_name =~ /\.txt$/ && $dev_name =~ /^(Device|Switch|Ups|Sensor|Gateway|Router|Server|Bras)/) {
        $dev_name =~ s/\.txt$//;
        $content{$dev_name} = $filename;
    }
    return;
}
