#!/usr/bin/perl

use strict;
use warnings;
use Encode;
no warnings 'utf8';
use English;
use LWP::UserAgent;
use JSON;
use File::Find;
use File::Path qw(make_path);
use File::Basename;
use Socket qw(inet_aton);
use Net::Patricia;
#use Data::Dumper;

my $api_user='USER';
my $api_key ='PASSWORD';
my $host    ='IP';
my $user_id = $ARGV[0];

exit if (!$user_id);

# Конфигурация логгирования
my $log_file = '/var/log/openvpn/sync_ccd_'.$user_id.'.log';
my $log_level = 'INFO'; # DEBUG, INFO, WARN, ERROR

# Конфигурационные параметры
my $api_url = 'https://'.$host.'/api.php?login='.$api_user.'&api_key='.$api_key.'&get=user&id='.$user_id;
my $ovpn_dir = '/etc/openvpn/server';

# Создаем backup и log директории если не существуют
make_path(dirname($log_file)) unless -d dirname($log_file);

# 1. Выполняем API запрос и получаем JSON
my $json_data = fetch_json($api_url);
unless ($json_data) {
    log_message("ERROR", "Не удалось получить данные от API");
    die "Не удалось получить данные от API\n";
}

# 2. Парсим JSON и извлекаем массив auth
my $auth_data = parse_auth_data($json_data);
if (!$auth_data and !scalar @{$auth_data}) {
    log_message("ERROR", "Не найдены данные auth в JSON ответе");
    die "Не найдены данные auth в JSON ответе\n";
    }

# 3. Ищем конфиги OpenVPN и анализируем их
my @configs = find_ovpn_configs($ovpn_dir);

# 4. Обрабатываем каждый конфиг
foreach my $config (@configs) {
    process_ovpn_config($config, $auth_data);
}

log_message("INFO", "Обработка завершена успешно!");
print "Обработка завершена успешно!\n";

# Функция логирования
sub log_message {
    my ($level, $message) = @_;
    
    # Уровни логирования: DEBUG < INFO < WARN < ERROR
    my %levels = (
        'DEBUG' => 1,
        'INFO'  => 2,
        'WARN'  => 3,
        'ERROR' => 4
    );
    
    return unless $levels{$level} >= $levels{$log_level};
    
    my $timestamp = strftime("%Y-%m-%d %H:%M:%S", localtime);
    my $log_entry = "[$timestamp] [$level] $message\n";
    
    open my $fh, '>>', $log_file or do {
        warn "Не могу открыть лог файл $log_file: $!\n";
        return;
    };
    print $fh $log_entry;
    close $fh;
}

# Функция для форматирования времени
sub strftime {
    my ($format, @time) = @_;
    my @tm = localtime($time[0] || time);
    my %formats = (
        '%Y' => sprintf("%04d", $tm[5] + 1900),
        '%m' => sprintf("%02d", $tm[4] + 1),
        '%d' => sprintf("%02d", $tm[3]),
        '%H' => sprintf("%02d", $tm[2]),
        '%M' => sprintf("%02d", $tm[1]),
        '%S' => sprintf("%02d", $tm[0]),
    );
    
    $format =~ s/(%\w)/$formats{$1} || $1/eg;
    return $format;
}

sub ip_and_mask_to_cidr {
    my ($ip, $mask) = @_;

    # Преобразуем IP и маску в 32-битные числа (в сетевом порядке)
    my $ip_n   = unpack("N", inet_aton($ip));
    my $mask_n = unpack("N", inet_aton($mask));

    # Проверка: маска должна быть корректной (последовательность 1, затем 0)
    my $binary_mask = sprintf("%032b", $mask_n);
    if ($binary_mask !~ /^1*0*$/) {
        log_message("ERROR", "Некорректная маска подсети: $mask");
        die "Некорректная маска подсети: $mask\n";
    }

    # Считаем количество единиц в маске — это /24, /16 и т.д.
    my $prefix_len = ($binary_mask =~ tr/1/1/);

    # Применяем маску к IP, чтобы получить сетевой адрес
    my $network_n = $ip_n & $mask_n;

    # Преобразуем обратно в dotted-decimal
    my $network_ip = join '.', map { ($network_n >> (24 - 8 * $_)) & 255 } 0..3;

    # Возвращаем в формате CIDR
    return "$network_ip/$prefix_len";
}

sub fetch_json {
    my $url = shift;
    
    my $ua = LWP::UserAgent->new;
    $ua->timeout(30);
    $ua->env_proxy;
    
    log_message("DEBUG", "Выполняем запрос к API: $url");
    my $response = $ua->get($url);

    if ($response->is_success) {
        log_message("DEBUG", "API запрос успешен");
        return $response->decoded_content;
    } else {
        my $error = "Ошибка запроса: " . $response->status_line;
        log_message("ERROR", $error);
        warn "$error\n";
        return undef;
    }
}

sub parse_auth_data {
    my $json_str = shift;
    my @result;

    eval {
        my $json = decode_json($json_str);
        @result = @{$json->{auth}} if $json && ref $json eq 'HASH' && $json->{auth};
    };

    if ($@) {
        my $error = "Ошибка парсинга JSON: $@";
        log_message("ERROR", $error);
        warn "$error\n";
        return undef;
    }

    log_message("DEBUG", "Найдено " . scalar(@result) . " записей auth");
    return \@result;
}

sub find_ovpn_configs {
    my $dir = shift;
    my @configs;
    
    find(sub {
        return unless -f && /\.conf$/;
        push @configs, $File::Find::name;
    }, $dir);
    
    log_message("DEBUG", "Найдено " . scalar(@configs) . " конфигурационных файлов OpenVPN");
    return @configs;
}

sub process_ovpn_config {
    my $config_file = shift;
    my $ip_list = shift;

    log_message("INFO", "Обрабатываем конфиг: $config_file");
    print "Обрабатываем конфиг: $config_file\n";

    # Читаем конфиг и находим ccd directory и сеть
    my ($ccd_dir, $network, $network_mask) = parse_ovpn_config($config_file);
    
    unless ($ccd_dir && $network) {
        log_message("WARN", "Не найдены ccd directory или network в $config_file");
        return;
    }

    my $ServerNet = new Net::Patricia;
    $ServerNet->add_string($network);
    log_message("INFO", "Found server network: $network (mask: $network_mask) and ccd: $ccd_dir");
    print "Found server network: $network (mask: $network_mask) and ccd: $ccd_dir\n";

    # Преобразуем относительный пути в абсолютный
    unless ($ccd_dir =~ m{^/}) {
        my $config_dir = dirname($config_file);
        $ccd_dir = "$config_dir/$ccd_dir";
    }
    
    # Создаем CCD директорию если не существует
    make_path($ccd_dir) unless -d $ccd_dir;

    # Обрабатываем каждый auth record
    foreach my $auth (@$ip_list) {
        next unless $auth->{comments} && $auth->{ip};
        next if (!$ServerNet->match_string($auth->{ip}));
        my $username = $auth->{comments};
        my $ip = $auth->{ip};
        process_ccd_file($ccd_dir, $username, $ip, $network_mask);
    }
}

sub parse_ovpn_config {
    my $config_file = shift;
    my ($ccd_dir, $network, $network_mask);
    
    open my $fh, '<', $config_file or do {
        my $error = "Не могу открыть $config_file: $!";
        log_message("ERROR", $error);
        warn "$error\n";
        return (undef, undef);
    };
    
    while (my $line = <$fh>) {
        chomp $line;
        
        # Ищем client-config-dir
        if ($line =~ /^\s*client-config-dir\s+(\S+)/i) {
            $ccd_dir = $1;
        }
        
        # Ищем server directive
        if ($line =~ /^\s*server\s+(\d+\.\d+\.\d+\.\d+)\s+(\d+\.\d+\.\d+\.\d+)/i) {
            $network = ip_and_mask_to_cidr($1,$2) if ($1 and $2);
            $network_mask = $2 if ($2);
        }
        
        last if $ccd_dir && $network;
    }
    
    close $fh;
    
    return ($ccd_dir, $network, $network_mask);
}

sub process_ccd_file {
    my $ccd_dir = shift;
    my $username = shift;
    my $ip = shift;
    my $network_mask = shift;

    return if (!$username or !$ip or !$network_mask);

    my $ccd_file = "$ccd_dir/$username";

    my $log_msg = "Обрабатываем пользователя: $username, IP: $ip";
    log_message("INFO", $log_msg);
    print "$log_msg ...";
    
    # Читаем существующий файл или создаем новый
    my @lines;
    if (-f $ccd_file) {
        open my $fh, '<', $ccd_file or do {
            my $error = "Не могу открыть $ccd_file: $!";
            log_message("ERROR", $error);
            warn "$error\n";
            return;
        };
        @lines = <$fh>;
        close $fh;
    }
    
    # Ищем или добавляем ifconfig-push
    my $found = 0;
    my $new_ifconfig = "ifconfig-push $ip $network_mask";
    my $changed = 0;

    for my $i (0..$#lines) {
        if ($lines[$i] =~ /^ifconfig-push/) {
            if ($lines[$i] !~ /^\s*$new_ifconfig\s*$/) {
                my $replace_msg = "Заменяем: $lines[$i] на: $new_ifconfig";
                log_message("INFO", $replace_msg);
                print "$replace_msg\n";
                $lines[$i] = "$new_ifconfig\n";
                $changed = 1;
            } else {
                my $current_msg = "ifconfig-push уже актуален для $username";
                log_message("INFO", $current_msg);
                print "$current_msg\n";
            }
            $found = 1;
            last;
        }
    }
    
    # Если не нашли, добавляем новую строку
    unless ($found) {
        my $add_msg = "Добавляем ifconfig-push для $username";
        log_message("INFO", $add_msg);
        print "$add_msg\n";
        $changed = 1;
        push @lines, "$new_ifconfig\n";
    }
    
    if ($changed) {
        # Записываем обратно в файл
        open my $fh, '>', $ccd_file or do {
            my $error = "Не могу записать в $ccd_file: $!";
            log_message("ERROR", $error);
            warn "$error\n";
            return;
        };
        print $fh @lines;
        close $fh;
        # Ставим права на конфиг
        chmod 0644, $ccd_file or do {
            my $error = "chmod failed: $!";
            log_message("ERROR", $error);
            die "$error\n";
        };
        my $ccd_user = 'nobody';
        my $ccd_group = 'www-data';
        my $uid = getpwnam($ccd_user) or do {
            my $error = "Пользователь $ccd_user не найден";
            log_message("ERROR", $error);
            die "$error\n";
        };
        my $gid = getgrnam($ccd_group) or do {
            my $error = "Группа $ccd_group не найдена";
            log_message("ERROR", $error);
            die "$error\n";
        };
        chown $uid, $gid, $ccd_file or do {
            my $error = "chown failed: $!";
            log_message("ERROR", $error);
            die "$error\n";
        };

        my $success_msg = "Файл $ccd_file успешно обновлен";
        log_message("INFO", $success_msg);
        print "$success_msg\n";
        }
}

# Обработка ошибок
$SIG{__DIE__} = sub {
    my $error = shift;
    log_message("ERROR", "Критическая ошибка: $error");
    warn "Критическая ошибка: $error\n";
    exit 1;
};

END {
    # Любая финальная очистка
    log_message("DEBUG", "Скрипт завершил работу");
}
