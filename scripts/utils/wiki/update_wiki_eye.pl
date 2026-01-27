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

my $option_filter = encode_json({ option_id => '61' });

# === Получение пути к вики из таблицы config (id=61) ===
my $api_request = $api_request_url."&get=table_record&table=config&filter=".uri_escape($option_filter);

my $config_record = api_call('GET', $api_request);
if (!$config_record || $config_record->{error}) {
    die "Ошибка: не удалось получить путь к вики из таблицы config (id=61)\n";
}

my $wiki_path = $config_record->{value};
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

    # Получение записи из user_auth по IP через API
    my $auth = api_call('GET', $api_request_url. "&get=user_auth&ip=" . uri_escape($ip));
    if (!$auth || $auth->{error} || !$auth->{wikiname}) {
        next;
    }

    # Пропускаем шлюзы
    next if $auth->{wikiname} =~ /^Gateway/;

    print "Found: $auth->{ip} $auth->{mac} ";

    my ($device_name, $device_port);
    my $error_msg;

    # Определение типа устройства и получение родительских данных
    eval {
        if ($auth->{wikiname} =~ /^(Switch|Router)/) {
            # Для коммутаторов/маршрутизаторов: получаем данные через цепочку устройств
            my $device = api_call('GET', $api_request_url. "&get=table_record&table=devices&filter=" . uri_escape(encode_json({ ip => $ip })));
            die "Unknown device" unless $device;
            # Получаем аплинк-порт
            my $uplink_ports = api_call('GET', $api_request_url . "&get=table_record&table=device_ports&filter=" . uri_escape(encode_json({ device_id => $device->{id}, uplink => 1 })));
            die "Unknown connection" unless $uplink_ports;
            my $parent_connect = $uplink_ports;
            # Получаем целевой порт
            my $parent_port = api_call('GET', $api_request_url . "&get=table_record&table=device_ports&id=" . $parent_connect->{target_port_id});
            die "Unknown port connection" unless $parent_port;
            # Получаем родительское устройство
            my $device_parent = api_call('GET', $api_request_url . "&get=table_record&table=devices&id=" . $parent_port->{device_id});
            die "Unknown parent device" unless $device_parent;
            # Получаем авторизацию родительского устройства
            my $auth_parent = api_call('GET', $api_request_url . "&get=user_auth&ip=" . uri_escape($device_parent->{ip}));
            die "Unknown auth for device" unless $auth_parent && $auth_parent->{wikiname};
            $device_name  = $auth_parent->{wikiname};
            $device_port  = $parent_port->{port};
        }
        else {
            # Для других устройств: получаем данные через соединения
            my $conn = api_call('GET', $api_request_url . "&get=table_record&table=connections&filter=" . uri_escape(encode_json({ auth_id => $auth->{id} })));
            die "Unknown connection" unless $conn;
            # Получаем порт устройства
            my $device_port_rec = api_call('GET', $api_request_url . "&get=table_record&table=device_ports&id=" . $conn->{port_id});
            die "Unknown device port" unless $device_port_rec;
            # Получаем устройство
            my $device = api_call('GET', $api_request_url . "&get=table_record&table=devices&id=" . $device_port_rec->{device_id});
            die "Unknown device" unless $device && $device->{user_id};
            # Получаем авторизацию устройства по user_id и IP
            my $device_auth_list = api_call('GET', $api_request_url . "&get=table_list&table=user_auth&filter=" . uri_escape(encode_json({ user_id => $device->{user_id}, ip => $device->{ip} })));
            die "Unknown device auth" unless $device_auth_list && ref($device_auth_list) eq 'ARRAY' && @{$device_auth_list} > 0;
            my $device_auth = $device_auth_list->[0];
            die "Device auth has no wikiname" unless $device_auth->{wikiname};
            $device_name = $device_auth->{wikiname};
            $device_port = $device_port_rec->{port};
        }
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
            if ($auth->{mac}) {
                push(@wiki_dev, '%META:FIELD{name="Mac" title="Mac" value="' . $auth->{mac} . '"}%');
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
    if ($empty_fields{mac} && $auth->{mac}) {
        push(@wiki_dev, '%META:FIELD{name="Mac" title="Mac" value="' . $auth->{mac} . '"}%');
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

# === Вспомогательные функции ===

sub api_call {
    my ($method, $url) = @_;
    my $req = HTTP::Request->new($method => $url);
    my $res = $ua->request($req);
    return undef unless $res->is_success;
    return decode_json($res->decoded_content);
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
