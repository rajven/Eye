#!/usr/bin/perl -CS

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use utf8;
use open ":encoding(utf8)";
use strict;
use warnings;
use English;
use FindBin '$Bin';
use lib "/opt/Eye/scripts";
use Time::Local;
use FileHandle;
use Data::Dumper;
use eyelib::config;
use eyelib::main;
use eyelib::net_utils;
use eyelib::database;
use eyelib::snmp;
use eyelib::cmd;
use Net::SNMP qw(:snmp);
use Fcntl qw(:flock);

# Блокировка от повторного запуска
open(my $self_fh, "<", $0) or die "Cannot open $0 - $!";
flock($self_fh, LOCK_EX|LOCK_NB) or exit 1;

my @all_devices = get_records_sql($dbh, "SELECT * FROM devices");
my %devices_by_user_id;
foreach my $dev (@all_devices) {
    $devices_by_user_id{$dev->{user_id}} = $dev;
}

# ==============================================================================
# Обработка пользователей (user_auth)
# ==============================================================================
my @auth_list = get_records_sql($dbh, "
    SELECT A.id, A.user_id, A.ip, A.mac, A.dns_name, A.description,
           A.dhcp_hostname, A.WikiName, K.login, K.ou_id
    FROM user_auth AS A
    INNER JOIN user_list AS K ON K.id = A.user_id
    WHERE A.deleted = 0
    ORDER BY A.id
");

my %auth_ref;
foreach my $auth (@auth_list) {
    my $id = $auth->{id};

    $auth_ref{$id} = {
        id            => $id,
        ou_id         => $auth->{ou_id},
        ip            => $auth->{ip},
        mac           => $auth->{mac},
        dns_name      => $auth->{dns_name},
        description   => $auth->{description},
        dhcp_hostname => $auth->{dhcp_hostname},
        WikiName      => $auth->{WikiName},
        login         => $auth->{login},
        device        => $devices_by_user_id{$auth->{user_id}},
    };

    # Логика формирования описания (fallback)
    if ($auth->{dns_name}) {
        $auth_ref{$id}{description} = $auth->{dns_name};
    } elsif (!$auth_ref{$id}{description} && $auth->{WikiName}) {
        $auth_ref{$id}{description} = $auth->{WikiName};
    } elsif (!$auth_ref{$id}{description} && $auth->{description}) {
        $auth_ref{$id}{description} = $auth->{description};
    } elsif (!$auth_ref{$id}{description}) {
        $auth_ref{$id}{description} = $auth->{ip};
    }

}

# ==============================================================================
# Обработка портов устройств (device_ports)
# ==============================================================================
my %port_info;

# device_type <= 1: предполагаем, что 0=Switch, 1=Router
my $d_sql = "
    SELECT DP.id, D.ip, D.device_name, D.device_model_id, DP.port,
           DP.snmp_index, DP.description, DP.target_port_id,
           D.vendor_id, D.device_type
    FROM devices AS D
    INNER JOIN device_ports AS DP ON D.id = DP.device_id
    WHERE D.device_type <= 1
      AND D.deleted = 0
    ORDER BY D.device_name, DP.port
";

my @port_list = get_records_sql($dbh, $d_sql);

foreach my $port (@port_list) {
    my $pid = $port->{id};
    $port_info{$pid} = {
        id               => $pid,
        device_name      => lc($port->{device_name}),
        ip               => $port->{ip},
        device_model_id  => $port->{device_model_id},
        port             => $port->{port},
        snmp_index       => $port->{snmp_index},
        description      => $port->{description},
        port_description => $port->{description},
        target_port_id   => $port->{target_port_id},
        vendor_id        => $port->{vendor_id},
        device_type      => $port->{device_type},
    };
}

# ==============================================================================
# Обработка подключений (connections)
# ==============================================================================
my %conn_info;

$d_sql = "
    SELECT C.id, C.port_id, C.auth_id
    FROM connections AS C
    INNER JOIN user_auth AS A ON A.id = C.auth_id
    WHERE A.deleted = 0
    ORDER BY C.id
";
my @conn_list = get_records_sql($dbh, $d_sql);

foreach my $conn (@conn_list) {
    my $cid = $conn->{id};
    $conn_info{$cid} = {
        id      => $cid,
        port_id => $conn->{port_id},
    };

    if (my $aid = $conn->{auth_id}) {
        $conn_info{$cid}{auth_id}     = $aid;
        $conn_info{$cid}{description} = $auth_ref{$aid}{description};
        $conn_info{$cid}{ou_id}       = $auth_ref{$aid}{ou_id};
        $conn_info{$cid}{device}      = $auth_ref{$aid}{device};
    }
}

# Приоритет именования портов по типу устройств
my %device_priority = (
    0 => 1,  # Router
    1 => 2,  # Switch
    2 => 3,  # Gateway
    4 => 4,  # WiFi Access Point
    3 => 5,  # Server
    5 => 6,  # Network device
);

# Назначаем описания портам на основе подключений устройств
foreach my $conn_id (keys %conn_info) {
    my $pid = $conn_info{$conn_id}{port_id};
    next unless exists $port_info{$pid};
    if ($conn_info{$conn_id}{device} && defined $conn_info{$conn_id}{device}{device_type}) {
        my $current_device_type = $conn_info{$conn_id}{device}{device_type};
        my $current_device_name = $conn_info{$conn_id}{device}{device_name} || '';
        my $current_priority = $device_priority{$current_device_type};
        # Если описание порта еще не назначено или новое устройство имеет более высокий приоритет (меньшее число)
        if (!defined $port_info{$pid}{priority} || $current_priority < $port_info{$pid}{priority}) {
            $port_info{$pid}{priority} = $current_priority;
            $port_info{$pid}{device_type} = $current_device_type;
            $port_info{$pid}{description} = $current_device_name;
        }
    }
}

# Назначаем описания портам на основе подключений ip-адресов
foreach my $conn_id (keys %conn_info) {
    my $pid = $conn_info{$conn_id}{port_id};
    next unless exists $port_info{$pid};
    next if ($port_info{$pid}{description});
    $port_info{$pid}{description} = $conn_info{$conn_id}{description};
}

# ==============================================================================
# Формирование финального хеша устройств и портов для прошивки
# ==============================================================================
my %devices;

foreach my $port_id (keys %port_info) {
    if (my $target_id = $port_info{$port_id}{target_port_id}) {
        if (exists $port_info{$target_id}) {
            my $t_dev  = $port_info{$target_id}{device_name} // 'Unknown';
            my $t_port = $port_info{$target_id}{port}        // '?';
            $port_info{$port_id}{description} = "$t_dev [$t_port]";
        }
    }
    next if (!$port_info{$port_id});

    my $dev_name = $port_info{$port_id}{device_name};
    my $port_num = $port_info{$port_id}{port};

    # Переопредеям описаниме по описанию порта свича
    if ($port_info{$port_id}{port_description}) { $port_info{$port_id}{description} = $port_info{$port_id}{port_description}; }

    # транслитерация
    $port_info{$port_id}{description} = translit($port_info{$port_id}{description}) // '';
    # Оставить только латиницу, цифры и базовые символы
    $port_info{$port_id}{description} =~ s/[^a-zA-Z0-9\s\.\,\-\_]//g;
    # Схлопнуть множественные пробелы
    $port_info{$port_id}{description} =~ s/\s+/ /g;
    # Убрать пробелы по краям
    $port_info{$port_id}{description} =~ s/^\s+|\s+$//g;

    $devices{$dev_name}{ports}{$port_num}{description} = $port_info{$port_id}{description};
    $devices{$dev_name}{ports}{$port_num}{snmp_index}  = $port_info{$port_id}{snmp_index};
    $devices{$dev_name}{device_name}                   = $dev_name;
    $devices{$dev_name}{ip}                            = $port_info{$port_id}{ip};
    $devices{$dev_name}{device_model_id}               = $port_info{$port_id}{device_model_id};
    $devices{$dev_name}{vendor_id}                     = $port_info{$port_id}{vendor_id};
    $devices{$dev_name}{device_type}                   = $port_info{$port_id}{device_type};
}

# ==============================================================================
# Применение описаний на устройства по SNMP
# ==============================================================================

foreach my $device_name (sort keys %devices) {
    my $device = $devices{$device_name};

    # skip unknown vendor
    next if (!$switch_auth{$device->{vendor_id}});

    my $ip = $device->{ip};

    my $safe_ip = $dbh->quote($ip);
    my $netdev = get_record_sql($dbh, "SELECT * FROM devices WHERE ip = $safe_ip");

    next if (!$netdev);

    print "Device: $device_name IP: $ip ";

    if (!HostIsLive($ip)) {
        print "... Down! Skip.\n";
        next;
    }

    print "... Programming:\n";

    setCommunity($netdev);

    eval {
        # get interface names
        my $int = get_interfaces($ip, $netdev->{snmp}, 0);

        $netdev = netdev_set_auth($netdev);

        $device->{login}           = $netdev->{login};
        $device->{password}        = $netdev->{password};
        $device->{enable_password} = '';
        $device->{proto}           = $netdev->{proto};
        $device->{port}            = $netdev->{port};

        my $session = netdev_login($device);

        if ($session) {
            netdev_set_hostname($session, $device);
            foreach my $port (sort { $a <=> $b } keys %{$device->{ports}}) {
                next if (!$device->{ports}{$port}{description});

                my $descr =$device->{ports}{$port}{description};

                # Очистка описания от спецсимволов
                $descr =~ s/\./-/g;
                $descr =~ s/\(/_/g;
                $descr =~ s/\)/_/g;

                next if (!$descr);
                next if ($descr =~ /^-port-$/);

                my $index = $device->{ports}{$port}{snmp_index};

                if (!defined $index || !exists $int->{$index}) {
                    print "  Port: $port index: " . ($index // 'undef') . " -> Skipped (No SNMP data)\n";
                    next;
                }

                my $if_name = $int->{$index}->{name};
                print "  Port: $port index: $index Descr: $descr\n";

                netdev_set_port_descr($session, $device, $if_name, $port, $descr);
            }
            netdev_wr_mem($session, $device);
        } else {
            print "Login error!\n";
            next;
        }
    };
    if ($@) {
        print "Error! Apply failed: $@\n";
        next;
    }

    print "Programming finished.\n";
}

exit 0;
