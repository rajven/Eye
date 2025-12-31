#!/usr/bin/perl

#
# Автор: Roman Dmitriev <rnd@rajven.ru>
# Назначение: Скрипт для обработки DHCP-логов, определения подключений клиентов
#             через коммутаторы по данным из DHCP Option 82 (remote-id / circuit-id)
#             и записи соединений в базу данных.
#

use utf8;
use open ":encoding(utf8)";
use Encode;
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
use warnings;
use Getopt::Long;
use Proc::Daemon;
use POSIX;
use Net::Netmask;
use Text::Iconv;
use File::Tail;
use Fcntl qw(:flock);

# === БЛОКИРОВКА И ИНИЦИАЛИЗАЦИЯ ===

# Блокировка запуска нескольких экземпляров скрипта
open(SELF, "<", $0) or die "Cannot open $0 - $!";
flock(SELF, LOCK_EX | LOCK_NB) or exit 1;

# Установка низкого приоритета процесса (nice = 19)
setpriority(0, 0, 19);

# === ГЛОБАЛЬНЫЕ ПЕРЕМЕННЫЕ ===

my $mute_time = 300;            # Время (в секундах) для подавления дублирующих DHCP-событий
my $log_file = '/var/log/dhcp.log';

# Определяем имя процесса и PID-файл
my $proc_name = $MY_NAME;
$proc_name =~ s/\.[^.]+$//;
my $pid_file = '/run/eye/' . $proc_name;
my $pf = $pid_file . '.pid';

# Настройка демона
my $daemon = Proc::Daemon->new(
    pid_file => $pf,
    work_dir => $HOME_DIR
);

# Проверяем, запущен ли уже процесс
my $pid = $daemon->Status($pf);

my $daemonize = 1;  # По умолчанию — запуск в фоне

# === ОБРАБОТКА АРГУМЕНТОВ КОМАНДНОЙ СТРОКИ ===

GetOptions(
    'daemon!' => \$daemonize,
    "help"    => \&usage,
    "reload"  => \&reload,
    "restart" => \&restart,
    "start"   => \&run,
    "status"  => \&status,
    "stop"    => \&stop  # опечатка в оригинале — исправлено
) or &usage;

exit(0);

# === ФУНКЦИИ УПРАВЛЕНИЯ ДЕМОНОМ ===

sub stop {
    log_info("Запрошена остановка демона...");
    if ($pid) {
        print "Stopping pid $pid...";
        if ($daemon->Kill_Daemon($pf)) {
            print "Successfully stopped.\n";
            log_info("Демон успешно остановлен (PID $pid).");
        } else {
            print "Could not find $pid. Was it running?\n";
            log_warning("Не удалось остановить процесс PID $pid — возможно, он уже завершён.");
        }
    } else {
        print "Not running, nothing to stop.\n";
        log_info("Демон не запущен — останавливать нечего.");
    }
}

sub status {
    if ($pid) {
        print "Running with pid $pid.\n";
        log_info("Статус: демон запущен (PID $pid).");
    } else {
        print "Not running.\n";
        log_info("Статус: демон не запущен.");
    }
}

sub run {
    log_info("Запуск основного цикла обработки DHCP-логов...");

    if ($pid) {
        print "Already Running with pid $pid\n";
        log_warning("Попытка запуска уже работающего демона (PID $pid).");
        return;
    }

    print "Starting...\n";
    log_info("Инициализация демона...");

    if ($daemonize) {
        # Инициализация демона: закрытие дескрипторов, смена директории и т.п.
        $daemon->Init;
        log_debug("Демон инициализирован в фоновом режиме.");
    }

    setpriority(0, 0, 19);  # Убедимся, что приоритет установлен и в дочернем процессе

    # Конвертер для перекодирования из cp866 в UTF-8 (для старых логов)
    my $converter = Text::Iconv->new("cp866", "utf8");

    # Основной бесконечный цикл обработки логов
    while (1) {
        eval {
            log_debug("Начало нового цикла обработки DHCP-логов.");

            my %leases;  # кэш для подавления дублей

            # Создаём новое подключение к БД
            my $hdb = init_db();
            log_debug("Подключение к БД установлено.");

            # Открываем лог-файл для "хвостового" чтения (tail -f)
            my $dhcp_log = File::Tail->new(
                name              => $log_file,
                maxinterval       => 5,
                interval          => 1,
                ignore_nonexistent => 1
            ) || die "$log_file not found!";

            log_info("Начинаю чтение логов из $log_file...");

            while (my $logline = $dhcp_log->read) {
                next unless $logline;
                chomp($logline);

                log_verbose("Получена строка из лога: $logline");

                # Удаляем непечатаемые символы (кроме букв, цифр, пунктуации и пробелов)
                $logline =~ s/[^\p{L}\p{N}\p{P}\p{Z}]//g;
                log_debug("Строка после фильтрации: $logline");

                # Разбираем строку по точке с запятой
                my (
                    $type, $mac, $ip, $hostname, $timestamp,
                    $tags, $sup_hostname, $old_hostname,
                    $circuit_id, $remote_id, $client_id,
                    $decoded_circuit_id, $decoded_remote_id
                ) = split(/;/, $logline);

                # Пропускаем строки без типа или не относящиеся к DHCP-событиям
                next unless $type && $type =~ /^(old|add|del)$/i;

                log_debug("Обрабатываем DHCP-событие: тип='$type', MAC='$mac', IP='$ip'");

                # Подавление дублей с одинаковым IP и типом в течение $mute_time секунд
                if (exists $leases{$ip} && $leases{$ip}{type} eq $type && (time() - $leases{$ip}{last_time} <= $mute_time)) {
                    log_debug("Пропускаем дубликат: IP=$ip, тип=$type (в пределах $mute_time сек)");
                    next;
                }

                # Обновляем конфиг каждые 60 секунд
                if (time() - $last_refresh_config >= 60) {
                    log_debug("Обновление конфигурации...");
                    init_option($hdb);
                }

                # Обрабатываем DHCP-запрос: обновление/создание записи в базе
                my $dhcp_record = process_dhcp_request($hdb, $type, $mac, $ip, $hostname, $client_id, $decoded_circuit_id, $decoded_remote_id);
                next unless $dhcp_record;

                # Сохраняем в кэш для подавления дублей
                $leases{$ip} = {
                    type => $type,
                    last_time => time()
                };
                my $auth_id = $dhcp_record->{auth_id};

                # === ЛОГИКА ОПРЕДЕЛЕНИЯ КОММУТАТОРА И ПОРТА ===

                my ($switch, $switch_port);
                my ($t_remote_id, $t_circuit_id) = ($remote_id, $circuit_id);

                # Обрабатываем только события подключения (add/old)
                if ($type =~ /^(add|old)$/i) {
                    log_debug("Пытаемся определить коммутатор по данным Option 82...");

                    # 1. Пытаемся определить по декодированному remote-id как MAC
                    if ($decoded_remote_id) {
                        $t_remote_id = $decoded_remote_id;
                        $t_remote_id .= "0" x (12 - length($t_remote_id)) if length($t_remote_id) < 12;
                        $t_remote_id = mac_splitted(isc_mac_simplify($t_remote_id));

                        my $devSQL = "SELECT D.id, D.device_name, D.ip, A.mac " .
                                     "FROM `devices` AS D, `User_auth` AS A " .
                                     "WHERE D.user_id = A.User_id AND D.ip = A.ip AND A.deleted = 0 " .
                                     "AND A.mac = '$t_remote_id'";
                        log_debug("SQL (по decoded_remote_id): $devSQL");
                        $switch = get_record_sql($hdb, $devSQL);

                        if ($switch) {
                            $remote_id = $t_remote_id;
                            $circuit_id = $decoded_circuit_id;
                            $dhcp_record->{'circuit-id'} = $circuit_id;
                            $dhcp_record->{'remote-id'} = $remote_id;
                            log_debug("Коммутатор найден по decoded_remote_id: " . $switch->{device_name});
                        }
                    }

                    # 2. Если не нашли — пробуем по оригинальному remote-id
                    if (!$switch && $remote_id) {
                        $t_remote_id = $remote_id;
                        $t_remote_id .= "0" x (12 - length($t_remote_id)) if length($t_remote_id) < 12;
                        $t_remote_id = mac_splitted(isc_mac_simplify($t_remote_id));

                        my $devSQL = "SELECT D.id, D.device_name, D.ip, A.mac " .
                                     "FROM `devices` AS D, `User_auth` AS A " .
                                     "WHERE D.user_id = A.User_id AND D.ip = A.ip AND A.deleted = 0 " .
                                     "AND A.mac = '$t_remote_id'";
                        log_debug("SQL (по remote_id): $devSQL");
                        $switch = get_record_sql($hdb, $devSQL);

                        if ($switch) {
                            $remote_id = $t_remote_id;
                            $dhcp_record->{'circuit-id'} = $circuit_id;
                            $dhcp_record->{'remote-id'} = $remote_id;
                            log_debug("Коммутатор найден по remote_id: " . $switch->{device_name});
                        }
                    }

                    # 3. Если не нашли — пробуем по имени устройства (remote_id как строка)
                    if (!$switch && $remote_id) {
                        my @id_words = split(/ /, $remote_id);
                        if ($id_words[0]) {
                            my $devSQL = "SELECT D.id, D.device_name, D.ip, A.mac " .
                                         "FROM `devices` AS D, `User_auth` AS A " .
                                         "WHERE D.user_id = A.User_id AND D.ip = A.ip AND A.deleted = 0 " .
                                         "AND D.device_name LIKE '$id_words[0]%'";
                            log_debug("SQL (по имени устройства из remote_id): $devSQL");
                            $switch = get_record_sql($hdb, $devSQL);
                            if ($switch) {
                                log_debug("Коммутатор найден по имени: " . $switch->{device_name});
                            }
                        }
                    }

                    # 4. Специальный случай: MikroTik (circuit-id может содержать имя)
                    if (!$switch && $circuit_id) {
                        my @id_words = split(/ /, $circuit_id);
                        if ($id_words[0]) {
                            my $devSQL = "SELECT D.id, D.device_name, D.ip, A.mac " .
                                         "FROM `devices` AS D, `User_auth` AS A " .
                                         "WHERE D.user_id = A.User_id AND D.ip = A.ip AND A.deleted = 0 " .
                                         "AND D.device_name LIKE '$id_words[0]%'";
                            log_debug("SQL (по имени из circuit_id — MikroTik?): $devSQL");
                            $switch = get_record_sql($hdb, $devSQL);
                            if ($switch) {
                                # MikroTik часто путает remote-id и circuit-id — меняем местами
                                ($circuit_id, $remote_id) = ($remote_id, $t_circuit_id);
                                $dhcp_record->{'circuit-id'} = $circuit_id;
                                $dhcp_record->{'remote-id'} = $remote_id;
                                log_debug("Обнаружен MikroTik — поменяли местами circuit-id и remote-id");
                            }
                        }
                    }

                    # === ОПРЕДЕЛЕНИЕ ПОРТА ===
                    if ($switch) {
                        # Нормализуем circuit_id для поиска порта
                        $t_circuit_id =~ s/[\+\-\s]+/ /g;

                        # Загружаем порты коммутатора
                        my @device_ports = get_records_sql($hdb, "SELECT * FROM device_ports WHERE device_id = " . $switch->{id});
                        my %device_ports_h;
                        foreach my $port_data (@device_ports) {
                            $port_data->{snmp_index} //= $port_data->{port};
                            $device_ports_h{$port_data->{port}} = $port_data;
                        }

                        # Пробуем найти порт по имени интерфейса (ifName)
                        $switch_port = undef;
                        foreach my $port_data (@device_ports) {
                            if ($t_circuit_id =~ /\s*$port_data->{ifName}$/i ||
                                $t_circuit_id =~ /^$port_data->{ifName}\s+/i) {
                                $switch_port = $port_data;
                                last;
                            }
                        }

                        # Если не нашли по имени — пробуем hex-код (последние 2 байта)
                        if (!$switch_port && $decoded_circuit_id) {
                            my $hex_port = substr($decoded_circuit_id, -2);
                            if ($hex_port && $hex_port =~ /^[0-9a-fA-F]{2}$/) {
                                my $t_port = hex($hex_port);
                                $switch_port = $device_ports_h{$t_port} if exists $device_ports_h{$t_port};
                                log_debug("Порт определён по hex: $t_port") if $switch_port;
                            }
                        }

                        # Запись лога и обновление подключения
                        if ($switch_port) {
                            db_log_verbose($hdb, "DHCP $type: IP=$ip, MAC=$mac " . $switch->{device_name} . " / " . $switch_port->{ifName});

                            # Проверяем, существует ли уже соединение
                            my $connection = get_records_sql($hdb, "SELECT * FROM connections WHERE auth_id = $auth_id");
                            if (!$connection || !@{$connection}) {
                                my $new_connection = {
                                    port_id    => $switch_port->{id},
                                    device_id  => $switch->{id},
                                    auth_id    => $auth_id
                                };
                                insert_record($hdb, 'connections', $new_connection);
                                log_debug("Создано новое соединение: auth_id=$auth_id");
                            }
                        } else {
                            db_log_verbose($hdb, "DHCP $type: IP=$ip, MAC=$mac " . $switch->{device_name} . " (порт не определён)");
                            log_warning("Не удалось определить порт для IP=$ip, коммутатор=" . $switch->{device_name});
                        }
                    }

                    log_debug("Определён коммутатор: " . ($switch ? $switch->{device_name} : "НЕТ")) if $switch;
                    log_debug("Определён порт: " . ($switch_port ? $switch_port->{ifName} : "НЕТ")) if $switch_port;
                }
            } # конец while чтения лога

        }; # конец eval

        # Обработка исключений
        if ($@) {
            log_error("Критическая ошибка в основном цикле: $@");
            sleep(60);  # пауза перед повторной попыткой
        }
    } # конец while(1)
}

# === ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ===

sub usage {
    print "usage: $MY_NAME (start|stop|status|restart)\n";
    exit(0);
}

sub reload {
    print "reload process not implemented.\n";
    log_warning("Команда 'reload' не поддерживается.");
}

sub restart {
    log_info("Запрошена перезагрузка демона...");
    stop();
    sleep(2);
    run();
}
