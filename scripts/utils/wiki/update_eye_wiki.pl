#!/usr/bin/perl
#
# Синхронизация записей пользователей с файлами документации Wiki
# через вызовы к PHP API (без прямого доступа к БД)
#
# Запуск: perl /path/to/update_eye_wiki.pl
#

use utf8;
use warnings;
use Encode;
use open qw(:std :encoding(UTF-8));
no warnings 'utf8';

use strict;
use English;
use FindBin '$Bin';
use lib "/opt/Eye/scripts";
use eyelib::config;
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

# берём по коду параметра, а не id записи!
my $option_filter = encode_json({ option_id => '61' });

# === Получение пути к вики из таблицы config (id=61) ===
my $api_request = $api_request_url."&get=table_record&table=config&filter=".uri_escape($option_filter);

my $config_response = api_call($ua, 'GET', $api_request);
if ($config_response->{error}) {
    die "Ошибка: не удалось получить путь к вики из таблицы config (id=61): " . $config_response->{error} . "\n";
}

my $wiki_path = $config_response->{data}->{value};
if (!$wiki_path || !-d $wiki_path) {
    die "Ошибка: путь к вики не настроен или директория не существует: $wiki_path\n";
}

print "Путь к вики: $wiki_path\n\n";

# Поиск подходящих файлов
my %content;
find(\&wanted, $wiki_path);

my $updated = 0;
my $errors  = 0;

foreach my $fname (sort keys %content) {
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

    next unless $ip && is_valid_ipv4($ip);

    # Получение записи из БД через API
    my $auth_response = api_call($ua, 'GET', $api_request_url. "&get=user_auth&ip=" . uri_escape($ip));
    if ($auth_response->{error}) {
        print "Запись не найдена для IP $ip (файл: $fname): " . $auth_response->{error} . "\n";
        next;
    }

    my $auth = $auth_response->{data};

    # Обновление поля WikiName через метод обновления user_auth
    my $update_data = {
        wikiname => $fname
    };

    my $json_data = encode_json($update_data);
    my $update_url = $api_request_url. "&send=update_user_auth&id=$auth->{id}";
    my $update_req = HTTP::Request->new(POST => $update_url);
    $update_req->header('Content-Type' => 'application/json');
    $update_req->content($json_data);

    my $update_response = api_call($ua, 'POST', $update_url, $json_data);
    
    if ($update_response->{error}) {
        warn "Ошибка обновления id=$auth->{id}: " . $update_response->{error} . "\n";
        $errors++;
    } else {
        print "Обновлено: id=$auth->{id} IP=$ip => WikiName=$fname\n";
        $updated++;
    }
}

print "\n=== ИТОГИ ===\n";
print "Обработано файлов: " . scalar(keys %content) . "\n";
print "Успешно обновлено записей: $updated\n";
print "Ошибок: $errors\n";
print "Синхронизация завершена.\n";

exit 0;

sub api_call {
    my ($ua, $method, $url, $content) = @_;
    my $req = HTTP::Request->new($method => $url);
    if ($content) {
        $req->header('Content-Type' => 'application/json');
        $req->content($content);
    }
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

sub wanted {
    my $filename = $File::Find::name;
    my $dev_name = basename($filename);
    if ($dev_name =~ /\.txt$/ && $dev_name =~ /^(Device|Switch|Ups|Sensor|Gateway|Router|Server|Bras)/) {
        $dev_name =~ s/\.txt$//;
        $content{$dev_name} = $filename;
    }
    return;
}

sub is_valid_ipv4 {
    my ($ip) = @_;
    return $ip =~ /^([0-9]{1,3}\.){3}[0-9]{1,3}$/ && !grep { $_ > 255 } split(/\./, $ip);
}
