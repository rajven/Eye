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
use lib "$Bin/";
use Data::Dumper;
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
my $auth_params = "api_key=" . uri_escape($api_key) . "&login=" . uri_escape($api_login);

# Инициализация HTTP-клиента
my $ua = LWP::UserAgent->new(
    timeout => 30,
    agent   => 'WikiSync/1.0'
);

# === Получение пути к вики из таблицы config (id=61) ===
my $config_record = api_call('GET', "$api_base?get_table_record&table=config&id=61&$auth_params");
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
    my $auth = api_call('GET', "$api_base/user_auth.php?get=user_auth&ip=" . uri_escape($ip) . "&$auth_params");
    if (!$auth || $auth->{error}) {
        print "Запись не найдена для IP $ip (файл: $fname)\n";
        next;
    }

    # Обновление поля WikiName через метод обновления user_auth
    my $update_data = {
        wiki_name => $fname  # Используем нижний регистр как принято в БД
    };

    my $json_data = encode_json($update_data);
    my $update_url = "$api_base/user_auth.php?send=update_user_auth&id=$auth->{id}&$auth_params";
    
    my $update_req = HTTP::Request->new(POST => $update_url);
    $update_req->header('Content-Type' => 'application/json');
    $update_req->content($json_data);
    
    my $update_res = $ua->request($update_req);
    
    if ($update_res->is_success) {
        my $result = decode_json(decode_utf8($update_res->decoded_content));
        if (!$result->{error}) {
            print "Обновлено: id=$auth->{id} IP=$ip => WikiName=$fname\n";
            $updated++;
        } else {
            warn "Ошибка обновления id=$auth->{id}: $result->{error}\n";
            $errors++;
        }
    } else {
        warn "HTTP ошибка при обновлении id=$auth->{id}: " . $update_res->status_line . "\n";
        $errors++;
    }
}

print "\n=== ИТОГИ ===\n";
print "Обработано файлов: " . scalar(keys %content) . "\n";
print "Успешно обновлено записей: $updated\n";
print "Ошибок: $errors\n";
print "Синхронизация завершена.\n";

exit 0;
