package eyelib::common;

#
# Copyright (C) Roman Dmitriev, rnd@rajven.ru
#

use utf8;
use open ":encoding(utf8)";
use strict;
use English;
use FindBin '$Bin';
use lib "/opt/Eye/scripts";
use base 'Exporter';
use vars qw(@EXPORT @ISA);
use eyelib::config;
use eyelib::main;
use Net::Patricia;
use eyelib::net_utils;
use Data::Dumper;
use eyelib::database;
use DateTime;
use POSIX qw(mktime ctime strftime);
use File::Temp qw(tempfile);
use DBI;

our @ISA = qw(Exporter);

our @EXPORT = qw(
apply_device_lock
create_dns_cname
create_dns_hostname
create_dns_ptr
delete_device
delete_dns_cname
delete_dns_hostname
delete_dns_ptr
delete_user
delete_user_auth
find_mac_in_subnet
get_default_ou
get_device_by_ip
get_dns_name
get_dynamic_ou
get_first_line
get_ip_subnet
get_new_user_id
get_notify_subnet
GetNowTime
get_office_subnet
get_subnets_ref
GetTimeStrByUnixTime
GetUnixTimeByStr
is_ad_computer
is_default_ou
is_dynamic_ou
is_hotspot
new_auth
new_user
process_dhcp_request
recalc_quotes
record_to_txt
resurrection_auth
set_changed
set_lock_discovery
unbind_ports
unblock_user
unset_lock_discovery
update_dns_record
update_dns_record_by_dhcp
get_creation_method
);

BEGIN
{

#---------------------------------------------------------------------------------------------------------------

sub get_first_line {
my $msg = shift;
if (!$msg) { return; }
if ($msg=~ /(.*)(\n|\<br\>)/) {
    $msg = $1 if ($1);
    chomp($msg);
    }
return $msg;
}

#---------------------------------------------------------------------------------------------------------------

sub unbind_ports {
    my ($db, $device_id) = @_;
    return unless $db && defined $device_id && $device_id =~ /^\d+$/;  # защита от нечисловых ID
    # Получаем все порты устройства
    my @target = get_records_sql($db, 
        "SELECT target_port_id, id FROM device_ports WHERE device_id = ?", 
        $device_id
    );
    foreach my $row (@target) {
        # Обнуляем ссылки НА этот порт (кто ссылается на него)
        do_sql($db, "UPDATE device_ports SET target_port_id = 0 WHERE target_port_id = ?", $row->{id});
        # Обнуляем ссылку С этого порта (куда он ссылался)
        do_sql($db, "UPDATE device_ports SET target_port_id = 0 WHERE id = ?", $row->{id});
    }
}

#---------------------------------------------------------------------------------------------------------------

sub get_dns_name {
    my ($db, $id) = @_;
    return unless $db && defined $id;

    # Защита: убедимся, что $id — положительное целое число
    return unless $id =~ /^\d+$/ && $id > 0;

    my $auth_record = get_record_sql(
        $db,
        "SELECT dns_name FROM user_auth WHERE deleted = 0 AND id = ?",
        $id
    );

    return $auth_record && $auth_record->{dns_name}
        ? $auth_record->{dns_name}
        : undef;
}

#---------------------------------------------------------------------------------------------------------------

sub record_to_txt {
    my ($db, $table, $id) = @_;
    return unless $db && defined $table && defined $id;
    # Валидация имени таблицы: только буквы, цифры, подчёркивания
    return unless $table =~ /^[a-zA-Z_][a-zA-Z0-9_]*$/;
    # Валидация ID: должно быть положительное целое число
    return unless $id =~ /^\d+$/ && $id > 0;
    my $record = get_record_sql(
        $db,
        "SELECT * FROM $table WHERE id = ?",
        $id
    );
    return hash_to_text($record);
}

#---------------------------------------------------------------------------------------------------------------

sub delete_user_auth {
    my ($db, $id) = @_;
    return 0 unless $db && defined $id;

    # Валидация ID
    return 0 unless $id =~ /^\d+$/ && $id > 0;

    # Получаем основную запись
    my $record = get_record_sql($db, "SELECT * FROM user_auth WHERE id = ?", $id);
    return 0 unless $record;  # если записи нет — выходим

    # Формируем идентификатор для лога
    my $auth_ident = $record->{ip} // '';
    if ($record->{dns_name}) {
        $auth_ident .= ' [' . $record->{dns_name} . ']';
    }
    if ($record->{description}) {
        $auth_ident .= ' :: ' . $record->{description};
    }

    my $txt_record = hash_to_text($record) // '';
    my $msg = "";

    # --- Удаляем алиасы ---
    my @aliases = get_records_sql($db, "SELECT * FROM user_auth_alias WHERE auth_id = ?", $id);
    foreach my $alias (@aliases) {
        my $alias_id = $alias->{id};
        next unless defined $alias_id && $alias_id =~ /^\d+$/;

        # Правильный вызов: таблица + ID (число)
        my $alias_txt = record_to_txt($db, 'user_auth_alias', $alias_id) // '';

        if (delete_record($db, 'user_auth_alias', 'id = ?', $alias_id)) {
            $msg = "Deleting an alias: $alias_txt\n::Success!\n" . $msg;
        } else {
            $msg = "Deleting an alias: $alias_txt\n::Fail!\n" . $msg;
        }
    }

    # --- Удаляем соединения ---
    do_sql($db, "DELETE FROM connections WHERE auth_id = ?", $id);

    # --- Удаляем основную запись ---
    my $changes = delete_record($db, "user_auth", "id = ?", $id);

    if ($changes) {
        $msg = "Deleting ip-record: $txt_record\n::Success!\n" . $msg;
    } else {
        $msg = "Deleting ip-record: $txt_record\n::Fail!\n" . $msg;
    }

    $msg = "Deleting user ip record $auth_ident\n\n" . $msg;
    db_log_warning($db, $msg, $id);

    # Отправка уведомления
    my $send_alert = isNotifyDelete(get_notify_subnet($db, $record->{ip}));
    sendEmail("WARN! " . get_first_line($msg), $msg, 1) if $send_alert;

    return $changes;
}

#---------------------------------------------------------------------------------------------------------------

sub unblock_user {
    my ($db, $user_id) = @_;
    return 0 unless $db && defined $user_id;

    # Валидация ID
    return 0 unless $user_id =~ /^\d+$/ && $user_id > 0;

    # Получаем пользователя
    my $user_record = get_record_sql($db, "SELECT * FROM user_list WHERE id = ?", $user_id);
    return 0 unless $user_record;  # если нет — выходим

    # Формируем идентификатор
    my $user_ident = 'id:' . ($user_record->{id} // '') . ' ' . ($user_record->{login} // '');
    if ($user_record->{description}) {
        $user_ident .= '[' . $user_record->{description} . ']';
    }

    my $msg = "Amnistuyemo blocked by traffic user $user_ident\nInternet access for the user's IP address has been restored:\n";
    my $send_alert = 0;
    my $any_updated = 0;

    # Разблоковываем все активные IP-записи пользователя
    my @user_auth = get_records_sql($db, "SELECT * FROM user_auth WHERE deleted = 0 AND user_id = ?", $user_id);
    foreach my $record (@user_auth) {
        next unless $record->{id} && $record->{id} =~ /^\d+$/;

        $send_alert ||= isNotifyUpdate(get_notify_subnet($db, $record->{ip}));

        my $auth_ident = $record->{ip} // '';
        if ($record->{dns_name}) {
            $auth_ident .= '[' . $record->{dns_name} . ']';
        }
        if ($record->{description}) {
            $auth_ident .= ' :: ' . $record->{description};
        }

        my $new = {
            blocked => 0,
            changed => 1,
        };

        if (update_record($db, 'user_auth', $new, 'id = ?', $record->{id})) {
            $msg .= "\n" . $auth_ident;
            $any_updated = 1;
        }
    }

    # Разблоковываем самого пользователя в user_list
    my $user_update = { blocked => 0 };
    my $ret_id = update_record($db, 'user_list', $user_update, 'id = ?', $user_id);

    if ($ret_id) {
        # Логируем даже если нет IP-записей
        db_log_info($db, $msg);
        sendEmail("WARN! " . get_first_line($msg), $msg, 1) if $send_alert;
    }

    return $ret_id;
}

#---------------------------------------------------------------------------------------------------------------

sub delete_user {
    my ($db, $id) = @_;
    return 0 unless $db && defined $id;

    # Валидация ID: должно быть положительное целое число
    return 0 unless $id =~ /^\d+$/ && $id > 0;

    # Удаляем основную запись пользователя
    my $changes = delete_record($db, "user_list", "permanent = 0 AND id = ?", $id);
    return 0 unless $changes;  # если не удалось — выходим

    # Удаляем все IP-записи (user_auth)
    my @user_auth_records = get_records_sql($db, "SELECT id FROM user_auth WHERE user_id = ?", $id);
    foreach my $row (@user_auth_records) {
        next unless defined $row->{id} && $row->{id} =~ /^\d+$/;
        delete_user_auth($db, $row->{id});
    }

    # Удаляем устройство, привязанное к пользователю
    my $device = get_record_sql($db, "SELECT id FROM devices WHERE user_id = ?", $id);
    if ($device && defined $device->{id} && $device->{id} =~ /^\d+$/) {
        delete_device($db, $device->{id});
    }

    # Удаляем правила авторизации
    do_sql($db, "DELETE FROM auth_rules WHERE user_id = ?", $id);

    return $changes;
}

#---------------------------------------------------------------------------------------------------------------

sub delete_device {
    my ($db, $id) = @_;
    return 0 unless $db && defined $id;

    # Валидация: ID должен быть положительным целым числом
    return 0 unless $id =~ /^\d+$/ && $id > 0;

    # Удаляем запись устройства
    my $changes = delete_record($db, "devices", "id = ?", $id);
    return 0 unless $changes;  # если не удалось — выходим

    # Отвязываем порты
    unbind_ports($db, $id);

    # Удаляем связанные данные
    do_sql($db, "DELETE FROM connections WHERE device_id = ?", $id);
    do_sql($db, "DELETE FROM device_l3_interfaces WHERE device_id = ?", $id);
    do_sql($db, "DELETE FROM device_ports WHERE device_id = ?", $id);
    do_sql($db, "DELETE FROM device_filter_instances WHERE device_id = ?", $id);
    do_sql($db, "DELETE FROM gateway_subnets WHERE device_id = ?", $id);

    return $changes;
}

#---------------------------------------------------------------------------------------------------------------

sub is_hotspot {
    my ($db, $ip) = @_;
    return 0 unless $db && defined $ip;

    my @subnets = get_records_sql(
        $db,
        "SELECT subnet FROM subnets WHERE hotspot = 1 AND LENGTH(subnet) > 0"
    );

    my $pat = Net::Patricia->new;
    for my $row (@subnets) {
        $pat->add_string($row->{subnet}) if defined $row->{subnet};
    }

    return $pat->match_string($ip) ? 1 : 0;
}

#---------------------------------------------------------------------------------------------------------------

sub get_office_subnet {
    my ($db, $ip) = @_;
    return undef unless $db && defined $ip;

    my @rows = get_records_sql(
        $db,
        "SELECT * FROM subnets WHERE office = 1 AND LENGTH(subnet) > 0"
    );

    return undef unless @rows;

    my $pat = Net::Patricia->new;
    for my $row (@rows) {
        next unless defined $row->{subnet};
        # Защита от некорректных подсетей в БД
        eval { $pat->add_string($row->{subnet}, $row); 1 } or next;
    }

    return $pat->match_string($ip);
}

#---------------------------------------------------------------------------------------------------------------

sub get_notify_subnet {
my $db = shift;
my $ip  = shift;
my $notify_flag = get_office_subnet($db,$ip);
if ($notify_flag) { return $notify_flag->{notify}; }
return 0;
}

#---------------------------------------------------------------------------------------------------------------

sub get_new_user_id {
    my ($db, $ip, $mac, $hostname) = @_;
    return unless $db;

    my $result = {
        ip              => $ip,
        mac             => defined $mac ? mac_splitted(mac_simplify($mac)) : undef,
        dhcp_hostname   => $hostname,
        ou_id           => undef,
        user_id         => undef,
    };

    # --- Hotspot ---
    my @hotspot_rules = get_records_sql($db,
        "SELECT * FROM subnets WHERE hotspot = 1 AND LENGTH(subnet) > 0"
    );
    if (@hotspot_rules) {
        my $hotspot_pat = Net::Patricia->new;
        foreach my $row (@hotspot_rules) {
            next unless defined $row->{subnet};
            eval { $hotspot_pat->add_string($row->{subnet}, $default_hotspot_ou_id); 1 } or next;
            }
        if (defined $ip) {
            my $ou_id = $hotspot_pat->match_string($ip);
            if ($ou_id) { $result->{ou_id} = $ou_id; return $result; }
            }
        }

    # --- Поиск user_id по IP/MAC/hostname ---
    if (defined $ip && $ip) {
        my @rules = get_records_sql($db,
            "SELECT rule, user_id FROM auth_rules WHERE rule_type = 1 AND LENGTH(rule) > 0 AND user_id IS NOT NULL"
        );
        my $pat = Net::Patricia->new;
        foreach my $r (@rules) {
            next unless defined $r->{rule};
            eval { $pat->add_string($r->{rule}, $r->{user_id}); 1 } or next;
        }
        if (my $user_id = $pat->match_string($ip)) {
            $result->{user_id} = $user_id;
            return $result;
        }
    }

    if (defined $mac && $mac) {
        my @rules = get_records_sql($db,
            "SELECT rule, user_id FROM auth_rules WHERE rule_type = 2 AND LENGTH(rule) > 0 AND user_id IS NOT NULL"
        );
        foreach my $r (@rules) {
            next unless defined $r->{rule};
            my $rule_clean = mac_simplify($r->{rule});
            # Защита от битых регулярок
            eval {
                if ($mac =~ /\Q$rule_clean\E/i) {  # \Q...\E — экранируем спецсимволы!
                    $result->{user_id} = $r->{user_id};
                    return $result;
                }
                1;
            } or do {
                log_debug("Invalid MAC rule: '$r->{rule}'");
                next;
            };
        }
    }

    if (defined $hostname && $hostname) {
        my @rules = get_records_sql($db,
            "SELECT rule, user_id FROM auth_rules WHERE rule_type = 3 AND LENGTH(rule) > 0 AND user_id IS NOT NULL"
        );
        foreach my $r (@rules) {
            next unless defined $r->{rule};
            eval {
                if ($hostname =~ /$r->{rule}/i) {
                    $result->{user_id} = $r->{user_id};
                    return $result;
                }
                1;
            } or do {
                log_debug("Invalid hostname rule: '$r->{rule}'");
                next;
            };
        }
    }

    # --- Поиск ou_id по IP/MAC/hostname ---
    if (defined $ip && $ip) {
        my @rules = get_records_sql($db,
            "SELECT rule, ou_id FROM auth_rules WHERE rule_type = 1 AND LENGTH(rule) > 0 AND ou_id IS NOT NULL"
        );
        my $pat = Net::Patricia->new;
        foreach my $r (@rules) {
            next unless defined $r->{rule};
            eval { $pat->add_string($r->{rule}, $r->{ou_id}); 1 } or next;
        }
        if (my $ou_id = $pat->match_string($ip)) {
            $result->{ou_id} = $ou_id;
            return $result;
        }
    }

    if (defined $mac && $mac) {
        my @rules = get_records_sql($db,
            "SELECT rule, ou_id FROM auth_rules WHERE rule_type = 2 AND LENGTH(rule) > 0 AND ou_id IS NOT NULL"
        );
        foreach my $r (@rules) {
            next unless defined $r->{rule};
            my $rule_clean = mac_simplify($r->{rule});
            eval {
                if ($mac =~ /\Q$rule_clean\E/i) {
                    $result->{ou_id} = $r->{ou_id};
                    return $result;
                }
                1;
            } or do {
                log_debug("Invalid MAC rule: '$r->{rule}'");
                next;
            };
        }
    }

    if (defined $hostname && $hostname) {
        my @rules = get_records_sql($db,
            "SELECT rule, ou_id FROM auth_rules WHERE rule_type = 3 AND LENGTH(rule) > 0 AND ou_id IS NOT NULL"
        );
        foreach my $r (@rules) {
            next unless defined $r->{rule};
            eval {
                if ($hostname =~ /$r->{rule}/i) {
                    $result->{ou_id} = $r->{ou_id};
                    return $result;
                }
                1;
            } or do {
                log_debug("Invalid hostname rule: '$r->{rule}'");
                next;
            };
        }
    }

    # --- Значение по умолчанию ---
    $result->{ou_id} //= $default_user_ou_id;

    return $result;
}
#---------------------------------------------------------------------------------------------------------------

sub set_changed {
    my ($db, $id) = @_;
    return unless $db && defined $id;

    # Опционально: валидация ID как числа
    return unless $id =~ /^\d+$/;

    my $update_record = { changed => 1 };
    update_record($db, 'user_auth', $update_record, 'id = ?', $id);
}

#---------------------------------------------------------------------------------------------------------------

sub update_dns_record {
my ($hdb, $auth_id) = @_;
return unless $config_ref{enable_dns_updates};
return unless defined $auth_id;

# Валидация: auth_id должен быть положительным целым числом
return unless $auth_id =~ /^\d+$/ && $auth_id > 0;

# Переподключение
if (!$hdb || !$hdb->ping) { $hdb = init_db(); }
return unless $hdb;

# Получаем настройки
my $ad_zone = get_option($hdb, 33);
my $ad_dns  = get_option($hdb, 3);
my $enable_ad_dns_update = ($ad_zone && $ad_dns && $config_ref{enable_dns_updates});

log_debug("Auth id: $auth_id");
log_debug("enable_ad_dns_update: " . ($enable_ad_dns_update ? '1' : '0'));
log_debug("DNS update flags - zone: $ad_zone, dns: $ad_dns, enable_ad_dns_update: " . ($enable_ad_dns_update ? '1' : '0'));

# Получаем задачи из очереди
my @dns_queue = get_records_sql(
        $hdb,
        "SELECT * FROM dns_queue WHERE auth_id = ? AND value > '' AND value NOT LIKE '%.' ORDER BY id ASC",
        $auth_id
    );

return unless @dns_queue;

foreach my $dns_cmd (@dns_queue) {

my $fqdn = '';
my $fqdn_ip = '';
my $fqdn_parent = '';
my $static_exists = 0;
my $static_ref = '';
my $static_ok = 0;

eval {

if ($dns_cmd->{name_type}=~/^cname$/i) {
    #skip update unknown domain
    if ($dns_cmd->{name} =~/\.$/ or $dns_cmd->{value} =~/\.$/) { next; }

    $fqdn=lc($dns_cmd->{name});
    $fqdn=~s/\.$ad_zone$//i;
#    $fqdn=~s/\.$//;
    if ($dns_cmd->{value}) {
        $fqdn_parent=lc($dns_cmd->{value});
        $fqdn_parent=~s/\.$ad_zone$//i;
#        $fqdn_parent=~s/\.$//;
        }

    $fqdn = $fqdn.".".$ad_zone;
    $fqdn_parent = $fqdn_parent.".".$ad_zone;

    #remove cname
    if ($dns_cmd->{operation_type} eq 'del') {
        delete_dns_cname($fqdn_parent,$fqdn,$ad_zone,$ad_dns,$hdb);
        }
    #create cname
    if ($dns_cmd->{operation_type} eq 'add') {
        create_dns_cname($fqdn_parent,$fqdn,$ad_zone,$ad_dns,$hdb);
        }
    }

if ($dns_cmd->{name_type}=~/^a$/i) {
    #skip update unknown domain
    if ($dns_cmd->{name} =~/\.$/ or $dns_cmd->{value} =~/\.$/) { next; }
    $fqdn=lc($dns_cmd->{name});
    $fqdn=~s/\.$ad_zone$//i;
#    $fqdn=~s/\.$//;
    if (!$dns_cmd->{value}) { next; }
    $fqdn_ip=lc($dns_cmd->{value});
    $fqdn = $fqdn.".".$ad_zone;
    #dns update disabled?
    my $maybe_update_dns=( $enable_ad_dns_update and $office_networks->match_string($fqdn_ip) );
    if (!$maybe_update_dns) {
        db_log_info($hdb,"FOUND Auth_id: $auth_id. DNS update disabled.");
        next;
        }
    #get aliases
    my @aliases = get_records_sql($hdb, "SELECT * FROM user_auth_alias WHERE auth_id = ?", $auth_id);
    #remove A & PTR
    if ($dns_cmd->{operation_type} eq 'del') {
        #remove aliases
        if (@aliases and scalar @aliases) {
                foreach my $alias (@aliases) {
                    delete_dns_cname($fqdn,$alias->{alias},$ad_zone,$ad_dns,$hdb) if ($alias->{alias});
                    delete_dns_hostname($fqdn,$alias->{alias},$ad_zone,$ad_dns,$hdb) if ($alias->{alias});
                }
            }
        #remove main record
        delete_dns_hostname($fqdn,$fqdn_ip,$ad_zone,$ad_dns,$hdb);
        delete_dns_ptr($fqdn,$fqdn_ip,$ad_zone,$ad_dns,$hdb);
        }
    #create A & PTR
    if ($dns_cmd->{operation_type} eq 'add') {
        my @dns_record=ResolveNames($fqdn,$dns_server);
        $static_exists = (scalar @dns_record>0);
        if ($static_exists) {
            $static_ref = join(' ',@dns_record);
            foreach my $dns_a (@dns_record) {
                if ($dns_a=~/^$fqdn_ip$/) { $static_ok = 1; }
                }
            db_log_debug($hdb,"Dns record for static record $fqdn: $static_ref");
            }
        #skip update if already exists
        if ($static_ok) {
            db_log_debug($hdb,"Static record for $fqdn [$static_ok] correct.");
            next;
            }
        #create record
        create_dns_hostname($fqdn,$fqdn_ip,$ad_zone,$ad_dns,$hdb);
        create_dns_ptr($fqdn,$fqdn_ip,$ad_zone,$ad_dns,$hdb);
        #create aliases
        if (@aliases and scalar @aliases) {
                foreach my $alias (@aliases) {
                    create_dns_cname($fqdn,$alias->{alias},$ad_zone,$ad_dns,$hdb) if ($alias->{alias});
                }
            }
        }
    }
#PTR
if ($dns_cmd->{name_type}=~/^ptr$/i) {
    $fqdn=lc($dns_cmd->{name});
    $fqdn=~s/\.$ad_zone$//i;
#    $fqdn=~s/\.$//;
    if (!$dns_cmd->{value}) { next; }
    $fqdn_ip=lc($dns_cmd->{value});
    #skip update unknown domain
    if ($fqdn =~/\.$/) { next; }
    $fqdn = $fqdn.".".$ad_zone;
    #dns update disabled?
    my $maybe_update_dns=( $enable_ad_dns_update and $office_networks->match_string($fqdn_ip) );
    if (!$maybe_update_dns) {
        db_log_info($hdb,"FOUND Auth_id: $auth_id. DNS update disabled.");
        next;
        }
    #remove A & PTR
    if ($dns_cmd->{operation_type} eq 'del') {
        #remove main record
        delete_dns_ptr($fqdn,$fqdn_ip,$ad_zone,$ad_dns,$hdb);
        }
    #create A & PTR
    if ($dns_cmd->{operation_type} eq 'add') {
        #create record
        create_dns_ptr($fqdn,$fqdn_ip,$ad_zone,$ad_dns,$hdb);
        }
    }

};
if ($@) { log_error("Error dns commands: $@"); }
}

}

#---------------------------------------------------------------------------------------------------------------

sub is_ad_computer {
    my ($hdb, $computer_name) = @_;
    return 0 unless $hdb;
    return 0 if !$computer_name || $computer_name =~ /UNDEFINED/i;

    my $ad_check = get_option($hdb, 73);
    return 1 unless $ad_check;

    my $ad_zone = get_option($hdb, 33);

    # Проверка домена (если указан)
    if (defined $ad_zone && $ad_zone ne '' && $computer_name =~ /\./) {
        if ($computer_name !~ /\Q$ad_zone\E$/i) {
            db_log_verbose($hdb, "The domain of the computer $computer_name does not match the domain of the organization $ad_zone. Skip update.");
            return 0;
        }
    }

    # Извлекаем NetBIOS-имя (до первой точки)
    my $netbios_name = $computer_name;
    $netbios_name = $1 if $computer_name =~ /^([^\.]+)/;

    # Валидация NetBIOS-имени
    if (!$netbios_name || $netbios_name !~ /^[a-zA-Z0-9][a-zA-Z0-9_\-\$]{0,14}$/) {
        db_log_verbose($hdb, "Invalid computer name format: '$computer_name'");
        return 0;
    }

    # Проверяем кэш
    my $name_in_cache = get_record_sql($hdb, "SELECT * FROM ad_comp_cache WHERE name = ?", $netbios_name);
    return 1 if $name_in_cache;

    # Ищем в AD
    my $ad_computer_name = $netbios_name . '$';
    my $safe_name = quotemeta($ad_computer_name);
    my %name_found = do_exec_ref("/usr/bin/getent passwd $safe_name");

    if (!$name_found{output} || $name_found{status} ne 0) {
        db_log_verbose($hdb, "The computer " . uc($ad_computer_name) . " was not found in the domain $ad_zone. Skip update.");
        return 0;
    }

    # Кэшируем
    do_sql($hdb, "INSERT INTO ad_comp_cache (name) VALUES (?) ON DUPLICATE KEY UPDATE name = ?", 
           $netbios_name, $netbios_name);

    return 1;
}

#---------------------------------------------------------------------------------------------------------------

sub escape_like {
    my ($str) = @_;
    return '' unless defined $str;
    $str =~ s/([%_\\])/\\$1/g;
    return $str;
}

#---------------------------------------------------------------------------------------------------------------

sub update_dns_record_by_dhcp {

my $hdb = shift;
my $dhcp_record = shift;
my $auth_record = shift;

return if (!$config_ref{enable_dns_updates});

my $ad_zone = get_option($hdb,33);
my $ad_dns = get_option($hdb,3);

$update_hostname_from_dhcp = get_option($hdb,46) || 0;
my $subnets_dhcp = get_subnets_ref($hdb);
my $enable_ad_dns_update = ($ad_zone and $ad_dns and $update_hostname_from_dhcp);

log_debug("Dhcp record: ".Dumper($dhcp_record));
log_debug("Subnets: ".Dumper($subnets_dhcp->{$dhcp_record->{network}->{subnet}}));
log_debug("enable_ad_dns_update: ".$enable_ad_dns_update);
log_debug("DNS update flags - zone: ".$ad_zone.",dns: ".$ad_dns.", update_hostname_from_dhcp: ".$update_hostname_from_dhcp.", enable_ad_dns_update: " .
$enable_ad_dns_update. ", network dns-update enabled: ".$subnets_dhcp->{$dhcp_record->{network}->{subnet}}->{dhcp_update_hostname});

my $maybe_update_dns=($enable_ad_dns_update and $subnets_dhcp->{$dhcp_record->{network}->{subnet}}->{dhcp_update_hostname} and 
(is_ad_computer($hdb,$dhcp_record->{hostname_utf8}) and ($dhcp_record->{type}=~/add/i or $dhcp_record->{type}=~/old/i)));
if (!$maybe_update_dns) {
    db_log_debug($hdb,"FOUND Auth_id: $auth_record->{id}. DNS update don't needed.");
    return 0;
    }

log_debug("DNS update enabled.");
#update dns block
my $fqdn_static;
if ($auth_record->{dns_name}) {
    $fqdn_static=lc($auth_record->{dns_name});
    if ($fqdn_static!~/\.$ad_zone$/i) {
            $fqdn_static=~s/\.$//;
            $fqdn_static=lc($fqdn_static.'.'.$ad_zone);
            }
    }

my $fqdn=lc(trim($dhcp_record->{hostname_utf8}));
if ($fqdn!~/\.$ad_zone$/i) {
    $fqdn=~s/\.$//;
    $fqdn=lc($fqdn.'.'.$ad_zone);
    }

db_log_debug($hdb,"FOUND Auth_id: $auth_record->{id} dns_name: $fqdn_static dhcp_hostname: $fqdn");

#check exists static dns name
my $static_exists = 0;
my $dynamic_exists = 0;
my $static_ok = 0;
my $dynamic_ok = 0;
my $static_ref;
my $dynamic_ref;

if ($fqdn_static ne '') {
    my @dns_record=ResolveNames($fqdn_static,$dns_server);
    $static_exists = (scalar @dns_record>0);
    if ($static_exists) {
            $static_ref = join(' ',@dns_record);
            foreach my $dns_a (@dns_record) {
                if ($dns_a=~/^$dhcp_record->{ip}$/) { $static_ok = $dns_a; }
                }
            }
    } else { $static_ok = 1; }

if ($fqdn ne '') {
    my @dns_record=ResolveNames($fqdn,$dns_server);
    $dynamic_exists = (scalar @dns_record>0);
    if ($dynamic_exists) {
            $dynamic_ref = join(' ',@dns_record);
            foreach my $dns_a (@dns_record) {
                if ($dns_a=~/^$dhcp_record->{ip}$/) { $dynamic_ok = $dns_a; }
                }
            }
    }

db_log_debug($hdb,"Dns record for static record $fqdn_static: $static_ok");
db_log_debug($hdb,"Dns record for dhcp-hostname $fqdn: $dynamic_ok");

if ($fqdn_static ne '') {
    if (!$static_ok) {
        db_log_info($hdb,"Static record mismatch! Expected $fqdn_static => $dhcp_record->{ip}, recivied: $static_ref");
        if (!$static_exists) {
                db_log_info($hdb,"Static dns hostname defined but not found. Create it ($fqdn_static => $dhcp_record->{ip})!");
                create_dns_hostname($fqdn_static,$dhcp_record->{ip},$ad_zone,$ad_dns,$hdb);
                }
        } else {
	db_log_debug($hdb,"Static record for $fqdn_static [$static_ok] correct.");
	}
    }

if ($fqdn ne '' and $dynamic_ok ne '') { db_log_debug($hdb,"Dynamic record for $fqdn [$dynamic_ok] correct. No changes required."); }

if ($fqdn ne '' and !$dynamic_ok) {
    log_error("Dynamic record mismatch! Expected: $fqdn => $dhcp_record->{ip}, recivied: $dynamic_ref. Checking the status.");
    #check exists hostname
    my $another_hostname_exists = 0;
    my @conditions;
    my @params;
    # Первое условие: по hostname_utf8
    my $prefix1 = lc($dhcp_record->{hostname_utf8} // '');
    if ($prefix1 ne '') {
        push @conditions, 'LOWER(dns_name) LIKE ? ';
        push @params, $prefix1 . '%';
        }
    # Второе условие: по dns_name (если нужно)
    if ($fqdn_static ne '' && $fqdn !~ /\Q$fqdn_static\E/) {
        my $prefix2 = lc($auth_record->{dns_name} // '');
        if ($prefix2 ne '') {
            push @conditions, 'LOWER(dns_name) LIKE ? ';
            push @params, $prefix2 . '%';
        }
    }
    return unless @conditions;
    my $cond_sql = join(' OR ', @conditions);
    my $filter_sql = "SELECT * FROM user_auth WHERE id <> ? AND deleted = 0 AND ($cond_sql) ORDER BY last_found DESC";
    unshift @params, $auth_record->{id};
    db_log_debug($hdb, "Search by DNS name prefixes: $cond_sql with params: [" . join(', ', map { "'$_'" } @params) . "]");
    my $name_record = get_record_sql($hdb, $filter_sql, @params);
    if ($name_record->{dns_name} =~/^$fqdn$/i or $name_record->{dns_name} =~/^$dhcp_record->{hostname_utf8}$/i) {
	    $another_hostname_exists = 1;
	    }
    if (!$another_hostname_exists) {
            if ($fqdn_static and $fqdn_static ne '') {
                    if ($fqdn_static!~/$fqdn/) {
                        db_log_info($hdb,"Hostname from dhcp request $fqdn differs from static dns hostname $fqdn_static. Ignore dynamic binding!");
#                        delete_dns_hostname($fqdn,$dhcp_record->{ip},$ad_zone,$ad_dns,$hdb);
#                        create_dns_hostname($fqdn,$dhcp_record->{ip},$ad_zone,$ad_dns,$hdb);
                        }
                    } else {
        	    db_log_info($hdb,"Rewrite aliases if exists for $fqdn => $dhcp_record->{ip}");
                    #get and remove aliases
                    my @aliases = get_records_sql($hdb, "SELECT * FROM user_auth_alias WHERE auth_id = ?", $auth_record->{id});
                    if (@aliases and scalar @aliases) {
                            foreach my $alias (@aliases) {
                                delete_dns_cname($fqdn_static,$alias->{alias},$ad_zone,$ad_dns,$hdb) if ($alias->{alias});
                            }
                        }
        	    db_log_info($hdb,"Static dns hostname not defined. Create dns record by dhcp request. $fqdn => $dhcp_record->{ip}");
        	    create_dns_hostname($fqdn,$dhcp_record->{ip},$ad_zone,$ad_dns,$hdb);
                    if (@aliases and scalar @aliases) {
                            foreach my $alias (@aliases) {
                                create_dns_cname($fqdn_static,$alias->{alias},$ad_zone,$ad_dns,$hdb) if ($alias->{alias});
                            }
                        }
        	    }
	    } else {
            db_log_error($hdb,"Found another record with some hostname id: $name_record->{id} ip: $name_record->{ip} hostname: $name_record->{dns_name}. Skip update.");
            }
    }
#end update dns block
}

#------------------------------------------------------------------------------------------------------------

use DateTime::Format::Strptime;

sub apply_device_lock {
    my $db = shift;
    my $device_id = shift;
    my $iteration = shift || 0;
    $iteration++;
    if ($iteration > 2) { return 0; }

    my $dev = get_record_sql($db, "SELECT discovery_locked, locked_timestamp FROM devices WHERE id = ?", $device_id);

    if (!$dev) { return 0; }

    if (!$dev->{'discovery_locked'}) {
        return set_lock_discovery($db, $device_id);
    }

    my $ts_str = $dev->{'locked_timestamp'};

    # Если locked_timestamp NULL или пустой — устанавливаем блокировку
    if (!defined $ts_str || $ts_str eq '' || $ts_str eq '0000-00-00 00:00:00') {
        return set_lock_discovery($db, $device_id);
    }

    # Удаляем микросекунды (PostgreSQL) для совместимости с форматом
    $ts_str =~ s/\.\d+$//;

    # Парсим строку в DateTime
    my $parser = DateTime::Format::Strptime->new(
        pattern   => '%Y-%m-%d %H:%M:%S',
        on_error  => 'croak',
    );

    my $dt;
    eval {
        $dt = $parser->parse_datetime($ts_str);
    };

    if ($@ || !$dt) {
        # Ошибка парсинга — считаем блокировку недействительной
        return set_lock_discovery($db, $device_id);
    }

    # Получаем Unix timestamp
    my $u_locked_timestamp = $dt->epoch;

    # Ждём окончания блокировки (30 секунд)
    my $wait_time = ($u_locked_timestamp + 30) - time();
    if ($wait_time <= 0) {
        return set_lock_discovery($db, $device_id);
    }

    sleep($wait_time);
    return apply_device_lock($db, $device_id, $iteration);
}

#------------------------------------------------------------------------------------------------------------

sub set_lock_discovery {
    my $db = shift;
    my $device_id = shift;
    my $new;
    $new->{'discovery_locked'} = 1;
    $new->{'locked_timestamp'} = GetNowTime();
    if (update_record($db,'devices',$new,'id=?', $device_id)) { return 1; }
    return 0;
}

#------------------------------------------------------------------------------------------------------------

sub unset_lock_discovery {
    my $db = shift;
    my $device_id = shift;
    my $new;
    $new->{'discovery_locked'} = 0;
    $new->{'locked_timestamp'} = GetNowTime();
    if (update_record($db,'devices',$new,'id=?',$device_id)) { return 1; }
    return 0;
}

#------------------------------------------------------------------------------------------------------------

sub create_dns_cname {
my $fqdn = shift;
my $alias = shift;
my $zone = shift;
my $server = shift;
my $db = shift;
#skip update domain controllers
if (!$db) {
    log_info("DNS-UPDATE: Add => Zone $zone Server: $server CNAME: $alias for $fqdn"); 
    } else {
    db_log_info($db,"DNS-UPDATE: Add => Zone $zone Server: $server CNAME: $alias for $fqdn ");
    }
my $ad_zone = get_option($db,33);
my $nsupdate_file = "/tmp/".$fqdn."-nsupdate";
my @add_dns;
if ($config_ref{dns_server_type}=~/windows/i) {
    push(@add_dns,"gsstsig");
    push(@add_dns,"server $server");
    push(@add_dns,"zone $zone");
    push(@add_dns,"update add $alias 3600 cname $fqdn.");
    push(@add_dns,"send");
    write_to_file($nsupdate_file,\@add_dns);
    do_exec('/usr/bin/kinit -k -t /opt/Eye/scripts/cfg/dns_updater.keytab dns_updater@'.uc($ad_zone).' && /usr/bin/nsupdate "'.$nsupdate_file.'"');
    }

if ($config_ref{dns_server_type}=~/bind/i) {
    push(@add_dns,"server $server");
    push(@add_dns,"zone $zone");
    push(@add_dns,"update add $alias 3600 cname $fqdn.");
    push(@add_dns,"send");
    write_to_file($nsupdate_file,\@add_dns);
    do_exec('/usr/bin/nsupdate -k /etc/bind/rndc.key "'.$nsupdate_file.'"');
    }

if (-e "$nsupdate_file") { unlink "$nsupdate_file"; }
}

#---------------------------------------------------------------------------------------------------------------

sub delete_dns_cname {
my $fqdn = shift;
my $alias = shift;
my $zone = shift;
my $server = shift;
my $db = shift;
if (!$db) {
    log_info("DNS-UPDATE: Delete => Zone $zone Server: $server CNAME: $alias for $fqdn ");
    } else {
    db_log_info($db,"DNS-UPDATE: Delete => Zone $zone Server: $server CNAME: $alias for $fqdn");
    }
my $ad_zone = get_option($db,33);
my $nsupdate_file = "/tmp/".$fqdn."-nsupdate";
my @add_dns;
if ($config_ref{dns_server_type}=~/windows/i) {
    push(@add_dns,"gsstsig");
    push(@add_dns,"server $server");
    push(@add_dns,"zone $zone");
    push(@add_dns,"update delete $alias cname ");
    push(@add_dns,"send");
    write_to_file($nsupdate_file,\@add_dns);
    do_exec('/usr/bin/kinit -k -t /opt/Eye/scripts/cfg/dns_updater.keytab dns_updater@'.uc($ad_zone).' && /usr/bin/nsupdate "'.$nsupdate_file.'"');
    }

if ($config_ref{dns_server_type}=~/bind/i) {
    push(@add_dns,"server $server");
    push(@add_dns,"zone $zone");
    push(@add_dns,"update delete $alias cname");
    push(@add_dns,"send");
    write_to_file($nsupdate_file,\@add_dns);
    do_exec('/usr/bin/nsupdate -k /etc/bind/rndc.key "'.$nsupdate_file.'"');
    }

if (-e "$nsupdate_file") { unlink "$nsupdate_file"; }
}

#------------------------------------------------------------------------------------------------------------

sub create_dns_hostname {
my $fqdn = shift;
my $ip = shift;
my $zone = shift;
my $server = shift;
my $db = shift;
#skip update domain controllers
if ($fqdn=~/^dc[0-9]{1,2}\./i) { return; }
if (!$db) {
    log_info("DNS-UPDATE: Add => Zone $zone Server: $server A: $fqdn IP: $ip"); 
    } else {
    db_log_info($db,"DNS-UPDATE: Add => Zone $zone Server: $server A: $fqdn IP: $ip");
    }
my $ad_zone = get_option($db,33);
my $nsupdate_file = "/tmp/".$fqdn."-nsupdate";
my @add_dns;
if ($config_ref{dns_server_type}=~/windows/i) {
    push(@add_dns,"gsstsig");
    push(@add_dns,"server $server");
    push(@add_dns,"zone $zone");
    push(@add_dns,"update add $fqdn 3600 A $ip");
    push(@add_dns,"send");
    write_to_file($nsupdate_file,\@add_dns);
    do_exec('/usr/bin/kinit -k -t /opt/Eye/scripts/cfg/dns_updater.keytab dns_updater@'.uc($ad_zone).' && /usr/bin/nsupdate "'.$nsupdate_file.'"');
    }

if ($config_ref{dns_server_type}=~/bind/i) {
    push(@add_dns,"server $server");
    push(@add_dns,"zone $zone");
    push(@add_dns,"update add $fqdn 3600 A $ip");
    push(@add_dns,"send");
    write_to_file($nsupdate_file,\@add_dns);
    do_exec('/usr/bin/nsupdate -k /etc/bind/rndc.key "'.$nsupdate_file.'"');
    }

if (-e "$nsupdate_file") { unlink "$nsupdate_file"; }
}

#---------------------------------------------------------------------------------------------------------------

sub delete_dns_hostname {
my $fqdn = shift;
my $ip = shift;
my $zone = shift;
my $server = shift;
my $db = shift;
#skip update domain controllers
if ($fqdn=~/^dc[0-9]{1,2}\./i) { return; }
if (!$db) {
    log_info("DNS-UPDATE: Delete => Zone $zone Server: $server A: $fqdn IP: $ip"); 
    } else {
    db_log_info($db,"DNS-UPDATE: Delete => Zone $zone Server: $server A: $fqdn IP: $ip");
    }
my $ad_zone = get_option($db,33);
my $nsupdate_file = "/tmp/".$fqdn."-nsupdate";
my @add_dns;
if ($config_ref{dns_server_type}=~/windows/i) {
    push(@add_dns,"gsstsig");
    push(@add_dns,"server $server");
    push(@add_dns,"zone $zone");
    push(@add_dns,"update delete $fqdn A");
    push(@add_dns,"send");
    write_to_file($nsupdate_file,\@add_dns);
    do_exec('/usr/bin/kinit -k -t /opt/Eye/scripts/cfg/dns_updater.keytab dns_updater@'.uc($ad_zone).' && /usr/bin/nsupdate "'.$nsupdate_file.'"');
    }

if ($config_ref{dns_server_type}=~/bind/i) {
    push(@add_dns,"server $server");
    push(@add_dns,"zone $zone");
    push(@add_dns,"update delete $fqdn A");
    push(@add_dns,"send");
    write_to_file($nsupdate_file,\@add_dns);
    do_exec('/usr/bin/nsupdate -k /etc/bind/rndc.key "'.$nsupdate_file.'"');
    }

if (-e "$nsupdate_file") { unlink "$nsupdate_file"; }
}

#---------------------------------------------------------------------------------------------------------------

sub create_dns_ptr {
my $fqdn = shift;
my $ip = shift;
my $ad_zone = shift;
my $server = shift;
my $db = shift;

my $radr;
my $zone;

#skip update domain controllers
if ($fqdn=~/^dc[0-9]{1,2}\./i) { return; }
if ($ip =~ /([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})(\/[0-9]{1,2}){0,1}/) {
    return 0 if($1 > 255 || $2 > 255 || $3 > 255 || $4 > 255);
    $radr = "$4.$3.$2.$1.in-addr.arpa";
    $zone = "$3.$2.$1.in-addr.arpa";
    }

if (!$radr or !$zone) { return 0; }

if (!$db) { return 0; }

db_log_info($db,"DNS-UPDATE: Zone $zone Server: $server A: $fqdn PTR: $ip");

my $nsupdate_file = "/tmp/".$radr."-nsupdate";

my @add_dns;

if ($config_ref{dns_server_type}=~/windows/i) {
    push(@add_dns,"gsstsig");
    push(@add_dns,"server $server");
    push(@add_dns,"zone $zone");
    push(@add_dns,"update add $radr 3600 PTR $fqdn.");
    push(@add_dns,"send");
    write_to_file($nsupdate_file,\@add_dns);
    my $run_cmd = '/usr/bin/kinit -k -t /opt/Eye/scripts/cfg/dns_updater.keytab dns_updater@'.uc($ad_zone).' && /usr/bin/nsupdate "'.$nsupdate_file.'"';
    do_exec($run_cmd);
    }

if ($config_ref{dns_server_type}=~/bind/i) {
    push(@add_dns,"server $server");
    push(@add_dns,"zone $zone");
    push(@add_dns,"update add $radr 3600 PTR $fqdn.");
    push(@add_dns,"send");
    write_to_file($nsupdate_file,\@add_dns);
    my $run_cmd = '/usr/bin/nsupdate -k /etc/bind/rndc.key "'.$nsupdate_file.'"';
    do_exec($run_cmd);
    }

if (-e "$nsupdate_file") { unlink "$nsupdate_file"; }
}

#---------------------------------------------------------------------------------------------------------------

sub delete_dns_ptr {
my $fqdn = shift;
my $ip = shift;
my $ad_zone = shift;
my $server = shift;
my $db = shift;

my $radr;
my $zone;

#skip update domain controllers
if ($fqdn=~/^dc[0-9]{1,2}\./i) { return; }
if ($ip =~ /([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})(\/[0-9]{1,2}){0,1}/) {
    return 0 if($1 > 255 || $2 > 255 || $3 > 255 || $4 > 255);
    $radr = "$4.$3.$2.$1.in-addr.arpa";
    $zone = "$3.$2.$1.in-addr.arpa";
    }
if (!$radr or !$zone) { return 0; }

if (!$db) { return 0; }

db_log_info($db,"DNS-UPDATE: Delete => Zone $zone Server: $server A: $fqdn PTR: $ip");

my $nsupdate_file = "/tmp/".$radr."-nsupdate";

my @add_dns;

if ($config_ref{dns_server_type}=~/windows/i) {
    push(@add_dns,"gsstsig");
    push(@add_dns,"server $server");
    push(@add_dns,"zone $zone");
    push(@add_dns,"update delete $radr PTR");
    push(@add_dns,"send");
    write_to_file($nsupdate_file,\@add_dns);
    my $run_cmd = '/usr/bin/kinit -k -t /opt/Eye/scripts/cfg/dns_updater.keytab dns_updater@'.uc($ad_zone).' && /usr/bin/nsupdate "'.$nsupdate_file.'"';
    do_exec($run_cmd);
    }

if ($config_ref{dns_server_type}=~/bind/i) {
    push(@add_dns,"server $server");
    push(@add_dns,"zone $zone");
    push(@add_dns,"update delete $radr PTR");
    push(@add_dns,"send");
    write_to_file($nsupdate_file,\@add_dns);
    my $run_cmd = '/usr/bin/nsupdate -k /etc/bind/rndc.key "'.$nsupdate_file.'"';
    do_exec($run_cmd);
    }

if (-e "$nsupdate_file") { unlink "$nsupdate_file"; }
}

#---------------------------------------------------------------------------------------------------------------

sub new_user {
    my ($db, $user_info) = @_;
    return 0 unless $db && $user_info;

    my $user = {};

    # Формируем login: MAC приоритетен, иначе IP
    if ($user_info->{mac}) {
        $user->{login} = mac_splitted($user_info->{mac});
    } else {
        $user->{login} = $user_info->{ip} || 'unknown';
    }

    # Формируем description с указанием источника
    my $base_desc = $user_info->{dhcp_hostname} || $user->{login};
    if ($debug) {
        $user->{description} = $base_desc. '['. get_creation_method() . ']';
        } else {
        $user->{description} = $base_desc;
        }

    # Генерация уникального логина
    my $base_login = $user->{login};
    my $like_pattern = $base_login . '%';
    my $count_sql = "SELECT COUNT(*) AS rec_cnt FROM user_list WHERE login LIKE ?  OR login = ?";
    my $count_record = get_record_sql($db, $count_sql, $like_pattern, $base_login);
    my $login_count = $count_record ? ($count_record->{rec_cnt} || 0) : 0;

    if ($login_count > 0) {
        $user->{login} = $base_login . '(' . ($login_count + 1) . ')';
    }

    # OU info
    $user->{ou_id} = $user_info->{ou_id};
    my $ou_info;
    if ($user_info->{ou_id}) {
        $ou_info = get_record_sql($db, "SELECT * FROM ou WHERE id = ?", $user_info->{ou_id});
    }

    if ($ou_info) {
        $user->{enabled}         = $ou_info->{enabled};
        $user->{queue_id}        = $ou_info->{queue_id};
        $user->{filter_group_id} = $ou_info->{filter_group_id};
    }

    # Создаём пользователя
    my $result = insert_record($db, "user_list", $user);

    # Авто-правило по MAC
    if ($result && $config_ref{auto_mac_rule} && $user_info->{mac}) {
        insert_record($db, "auth_rules", {
            user_id   => $result,
            rule_type => 2,
            rule      => mac_splitted($user_info->{mac}),
        });
    }

    return $result;
}

#---------------------------------------------------------------------------------------------------------------

sub get_ip_subnet {
    my ($db, $ip) = @_;
    return unless $db && defined $ip && $ip ne '';

    my $ip_aton = StrToIp($ip);
    return unless defined $ip_aton && $ip_aton > 0;

    my $user_subnet = get_record_sql($db,
        "SELECT * FROM subnets WHERE (hotspot = 1 OR office = 1) AND ? BETWEEN ip_int_start AND ip_int_stop",
        $ip_aton
    );

    return $user_subnet;
}

#---------------------------------------------------------------------------------------------------------------

sub find_mac_in_subnet {
    my ($db, $ip, $mac) = @_;
    return unless $db && defined $ip && defined $mac && $ip ne '' && $mac ne '';

    my $ip_subnet = get_ip_subnet($db, $ip);
    return unless $ip_subnet && defined $ip_subnet->{ip_int_start} && defined $ip_subnet->{ip_int_stop};

    # Безопасный параметризованный запрос
    my @t_auth = get_records_sql($db,
        "SELECT * FROM user_auth 
         WHERE ip_int BETWEEN ? AND ? 
           AND mac = ? 
           AND deleted = 0 
         ORDER BY id",
        $ip_subnet->{ip_int_start},
        $ip_subnet->{ip_int_stop},
        $mac
    );

    return unless @t_auth;

    my $result = { count => 0, items => {} };
    for my $i (0 .. $#t_auth) {
        $result->{count}++;
        $result->{items}{$result->{count}} = $t_auth[$i];
    }

    return $result;
}

#---------------------------------------------------------------------------------------------------------------

sub get_creation_method {
    my $script_name = $config_ref{my_name} // (split('/', $0))[-1];
    return 'dhcp'   if $script_name eq 'dhcp-log.pl';
    return 'netflow' if $script_name eq 'eye-statd.pl';
    return 'arp'    if $script_name eq 'fetch_new_arp.pl';
    # По умолчанию — имя скрипта без расширения
    $script_name =~ s/\.pl$//;
    return $script_name || 'unknown';
}

#---------------------------------------------------------------------------------------------------------------

sub resurrection_auth {
    my ($db, $ip_record) = @_;
    return 0 unless $db && ref $ip_record eq 'HASH';

    my $ip      = $ip_record->{ip}      // '';
    my $mac     = $ip_record->{mac}     // '';
    my $action  = $ip_record->{type}    // '';
    my $hostname= $ip_record->{hostname_utf8} // '';
    my $client_id = $ip_record->{client_id} // '';

    return 0 if !$ip || !$mac;

    # Подготавливаем ip_aton и hotspot
    $ip_record->{ip_aton} //= StrToIp($ip);
    $ip_record->{hotspot} //= is_hotspot($db, $ip);

    my $auth_ident = "Found new ip-address: $ip";
    $auth_ident .= " [$mac]" if $mac;
    $auth_ident .= " :: $hostname" if $hostname;

    my $ip_aton = $ip_record->{ip_aton};
    return 0 unless defined $ip_aton;

    my $timestamp = GetNowTime();

    # --- Ищем существующую запись по IP+MAC ---
    my $record = get_record_sql($db,
        "SELECT * FROM user_auth WHERE deleted = 0 AND ip_int = ? AND mac = ?",
        $ip_aton, $mac
    );

    my $new_record = {
        last_found   => $timestamp,
        arp_found    => $timestamp,
        client_id    => $client_id // undef,
    };

    # Если нашли — обновляем
    if ($record && $record->{user_id}) {
        if ($action =~ /^(add|old|del)$/i) {
            $new_record->{dhcp_action} = $action;
            $new_record->{created_by}  = 'dhcp';
            $new_record->{dhcp_time}   = $timestamp;
            $new_record->{dhcp_hostname} = $hostname if $hostname;
        } else {
            $new_record->{created_by}  = $action // get_creation_method();
        }
        update_record($db, 'user_auth', $new_record, 'id = ?', $record->{id});
        return $record->{id};
    }

    # --- Проверка статической подсети ---
    my $user_subnet = $office_networks->match_string($ip);
    if ($user_subnet && $user_subnet->{static}) {
        db_log_warning($db, "Unknown ip+mac found in static subnet! Abort create record for ip: $ip mac: [$mac]");
        return 0;
    }

    my $send_alert_update = isNotifyUpdate(get_notify_subnet($db, $ip));
    my $send_alert_create = isNotifyCreate(get_notify_subnet($db, $ip));

    # --- Ищем другие записи с этим MAC в той же подсети ---
    my $mac_exists = find_mac_in_subnet($db, $ip, $mac);

    # --- Ищем запись с тем же IP (но другим MAC) ---
    my $ip_record_same = get_record_sql($db,
        "SELECT * FROM user_auth WHERE ip_int = ? AND deleted = 0",
        $ip_aton
    );

    my $msg = '';

    if ($ip_record_same && $ip_record_same->{id}) {
        if (!$ip_record_same->{mac}) {
            # Обновляем запись без MAC
            $msg = "$auth_ident\nUse auth record with no mac: " . hash_to_text($ip_record_same);
            db_log_verbose($db, $msg);
            $new_record->{mac} = $mac;
            $new_record->{dhcp} = 0 if $mac_exists && $mac_exists->{count};
            if ($action =~ /^(add|old|del)$/i) {
                $new_record->{dhcp_action} = $action;
                $new_record->{dhcp_time}   = $timestamp;
                $new_record->{created_by}  = 'dhcp';
                $new_record->{dhcp_hostname} = $hostname if $hostname;
                } else {
                $new_record->{created_by}  = $action // get_creation_method();
                }
            update_record($db, 'user_auth', $new_record, 'id = ?', $ip_record_same->{id});
            sendEmail("WARN! " . get_first_line($msg), $msg, 1) if $send_alert_update;
            return $ip_record_same->{id};
        } elsif ($ip_record_same->{mac}) {
            # MAC изменился — удаляем старую запись
            if (!$ip_record->{hotspot}) {
                $msg = "For ip: $ip mac change detected! Old mac: [$ip_record_same->{mac}] New mac: [$mac]. Disable old auth_id: $ip_record_same->{id}";
                db_log_warning($db, $msg, $ip_record_same->{id});
                sendEmail("WARN! " . get_first_line($msg), $msg, 1) if $send_alert_update;
            }
            delete_user_auth($db, $ip_record_same->{id});
        }
    }

    # --- Создаём нового пользователя, если нужно ---
    my $new_user_info = get_new_user_id($db, $ip, $mac, $hostname);
    my $new_user_id = $new_user_info->{user_id} // new_user($db, $new_user_info);

    # --- Удаляем дубли с dynamic=1 ---
    if ($mac_exists && $mac_exists->{items}) {
        for my $dup_id (keys %{$mac_exists->{items}}) {
            my $dup = $mac_exists->{items}{$dup_id};
            next unless $dup && $dup->{dynamic};
            delete_user_auth($db, $dup->{id});
        }
    }

    # --- Повторная проверка ---
    $mac_exists = find_mac_in_subnet($db, $ip, $mac);
    $new_record->{dhcp} = 0 if $mac_exists && $mac_exists->{count};

    # --- Готовим полную запись ---
    $new_record->{ip_int}      = $ip_aton;
    $new_record->{ip}          = $ip;
    $new_record->{mac}         = $mac;
    $new_record->{user_id}     = $new_user_id;
    $new_record->{save_traf}   = $save_detail;
    $new_record->{deleted}     = 0;

    if ($action =~ /^(add|old|del)$/i) {
        $new_record->{dhcp_action} = $action;
        $new_record->{dhcp_time}   = $timestamp;
        $new_record->{created_by}  = 'dhcp';
    } else {
        $new_record->{created_by}  = $action // get_creation_method();
    }

    # --- Проверяем, существует ли уже такая запись ---
    my $auth_exists = get_record_sql($db,
        "SELECT id FROM user_auth WHERE ip_int = ? AND mac = ? LIMIT 1",
        $ip_aton, $mac
    );

    my $cur_auth_id;
    if ($auth_exists && $auth_exists->{id}) {
        # Воскрешаем старую запись
        $cur_auth_id = $auth_exists->{id};
        $msg = "$auth_ident Resurrection auth_id: $cur_auth_id with ip: $ip and mac: $mac";
        if (!$ip_record->{hotspot}) { db_log_warning($db, $msg); } else { db_log_info($db, $msg); }
        update_record($db, 'user_auth', $new_record, 'id = ?', $cur_auth_id);
    } else {
        # Создаём новую
        $cur_auth_id = insert_record($db, 'user_auth', $new_record);
        if ($cur_auth_id) {
            $msg = $auth_ident;
            if (!$ip_record->{hotspot}) { db_log_warning($db, $msg); } else { db_log_info($db, $msg); }
        }
    }

    return 0 unless $cur_auth_id;

    # --- Дополняем данными из user_list и OU ---
    my $user_record = get_record_sql($db, "SELECT * FROM user_list WHERE id = ?", $new_user_id);
    if ($user_record) {
        my $ou_info = get_record_sql($db, "SELECT * FROM ou WHERE id = ?", $user_record->{ou_id});
        if ($ou_info && $ou_info->{dynamic}) {
            my $life_duration = $ou_info->{life_duration} // 24;
            my $hours   = int($life_duration);
            my $minutes = ($life_duration - $hours) * 60;
            my $now = DateTime->now(time_zone => 'local');
            my $end_life = $now->clone->add(hours => $hours, minutes => $minutes);
            $new_record->{dynamic}   = 1;
            $new_record->{end_life}  = $end_life->strftime('%Y-%m-%d %H:%M:%S');
        }
        $new_record->{ou_id}           = $user_record->{ou_id};
        $new_record->{description}     = $user_record->{description};
        $new_record->{filter_group_id} = $user_record->{filter_group_id};
        $new_record->{queue_id}        = $user_record->{queue_id};
        $new_record->{enabled}         = $user_record->{enabled} // 0;
        update_record($db, 'user_auth', $new_record, 'id = ?', $cur_auth_id);
    }

    db_log_warning($db, $msg, $cur_auth_id);
    sendEmail("WARN! " . get_first_line($msg), $msg . "\n" . record_to_txt($db, 'user_auth', $cur_auth_id), 1)  if $send_alert_create;
    return $cur_auth_id;
}

#---------------------------------------------------------------------------------------------------------------

sub new_auth {
    my ($db, $ip) = @_;
    return 0 unless $db && defined $ip && $ip ne '';

    my $ip_aton = StrToIp($ip);
    return 0 unless defined $ip_aton;

    # Проверяем, существует ли уже запись с таким IP
    my $record = get_record_sql($db,
        "SELECT id FROM user_auth WHERE deleted = 0 AND ip_int = ?",
        $ip_aton
    );
    return $record->{id} if $record && $record->{id};

    # Получаем информацию о пользователе/OU
    my $new_user_info = get_new_user_id($db, $ip, undef, undef);
    return 0 unless $new_user_info;

    my $new_user_id;
    if ($new_user_info->{user_id}) {
        $new_user_id = $new_user_info->{user_id};
    } elsif ($new_user_info->{ou_id}) {
        $new_user_id = new_user($db, $new_user_info);
    }

    return 0 unless $new_user_id;

    my $send_alert = isNotifyCreate(get_notify_subnet($db, $ip));

    # Получаем данные пользователя БЕЗОПАСНО
    my $user_record = get_record_sql($db,
        "SELECT * FROM user_list WHERE id = ?",
        $new_user_id
    );
    return 0 unless $user_record;

    my $timestamp = GetNowTime();
    my $new_record = {
        ip_int           => $ip_aton,
        ip               => $ip,
        user_id          => $new_user_id,
        save_traf        => $save_detail,
        deleted          => 0,
        created_by       => get_creation_method(),
        ou_id            => $user_record->{ou_id},
        filter_group_id  => $user_record->{filter_group_id},
        queue_id         => $user_record->{queue_id},
        enabled          => $user_record->{enabled} // 0,
    };
    $new_record->{description} = $user_record->{description} if $user_record->{description};

    my $cur_auth_id = insert_record($db, 'user_auth', $new_record);
    if ($cur_auth_id) {
        my $msg = "New ip created by ".get_creation_method()."! ip: $ip";
        db_log_warning($db, $msg, $cur_auth_id);
        sendEmail("WARN! " . get_first_line($msg), $msg, 1) if $send_alert;
    }

    return $cur_auth_id;
}

#--------------------------------------------------------------------------------------------------------------

sub get_dynamic_ou {
my $db = shift;
my @dynamic=();
my @ou_list = get_records_sql($db,"SELECT id FROM ou WHERE dynamic = 1");
foreach my $group (@ou_list) {
    next if (!$group);
    push(@dynamic,$group->{id});
    }
return wantarray ? @dynamic : \@dynamic;
}

#--------------------------------------------------------------------------------------------------------------

sub get_default_ou {
my $db = shift;
my @dynamic=();
my $ou = get_record_sql($db,"SELECT id FROM ou WHERE default_users = 1");
if (!$ou) { push(@dynamic,0); } else { push(@dynamic,$ou->{'id'}); }
$ou = get_record_sql($db,"SELECT id FROM ou WHERE default_hotspot = 1");
if ($ou) { push(@dynamic,$ou->{id}); }
return wantarray ? @dynamic : \@dynamic;
}

#--------------------------------------------------------------------------------------------------------------

sub is_dynamic_ou {
my $db = shift;
my $ou_id = shift;
my @dynamic=get_dynamic_ou($db);
if (in_array(\@dynamic,$ou_id)) { return 1; }
return 0;
}

#--------------------------------------------------------------------------------------------------------------

sub is_default_ou {
my $db = shift;
my $ou_id = shift;
my @dynamic=get_default_ou($db);
if (in_array(\@dynamic,$ou_id)) { return 1; }
return 0;
}

#---------------------------------------------------------------------------------------------------------------

sub get_subnets_ref {
my $db = shift;
my @list=get_records_sql($db,'SELECT * FROM subnets ORDER BY ip_int_start');
my $list_ref;
foreach my $net (@list) {
next if (!$net->{subnet});
$list_ref->{$net->{subnet}}=$net;
}
return $list_ref;
}

#---------------------------------------------------------------------------------------------------------------

sub get_device_by_ip {
    my ($db, $ip) = @_;
    return unless $db && defined $ip && $ip ne '';

    # Ищем устройство напрямую по IP
    my $netdev = get_record_sql($db, "SELECT * FROM devices WHERE ip = ?", $ip);
    return $netdev if $netdev && $netdev->{id} > 0;

    # Ищем user_id по IP в user_auth
    my $auth_rec = get_record_sql($db,
        "SELECT user_id FROM user_auth WHERE ip = ? AND deleted = 0",
        $ip
    );

    if ($auth_rec && defined $auth_rec->{user_id} && $auth_rec->{user_id} > 0) {
        $netdev = get_record_sql($db,
            "SELECT * FROM devices WHERE user_id = ?",
            $auth_rec->{user_id}
        );
        return $netdev;
    }

    return;
}

#---------------------------------------------------------------------------------------------------------------

sub recalc_quotes {
    my ($db, $calc_id) = @_;
    $calc_id //= $$;

    return unless get_option($db, 54);

    clean_variables($db);
    return if Get_Variable($db, 'RECALC');

    my $timeshift = get_option($db, 55);
    Set_Variable($db, 'RECALC', $calc_id, time() + $timeshift * 60);

    # --- Готовим временные метки ---
    my $now = DateTime->now(time_zone => 'local');

    my $day_start   = $now->clone->truncate(to => 'day');
    my $day_stop    = $day_start->clone->add(days => 1);

    my $month_start = $now->clone->truncate(to => 'month');
    my $month_stop  = $month_start->clone->add(months => 1);

    # --- Получаем список авторизаций ---
    my $user_auth_list_sql = q{
        SELECT A.id as auth_id, U.id, U.day_quota, U.month_quota,
               A.day_quota as auth_day, A.month_quota as auth_month
        FROM user_auth AS A
        JOIN user_list AS U ON A.user_id = U.id
        WHERE A.deleted = 0
        ORDER BY user_id
    };
    my @authlist_ref = get_records_sql($db, $user_auth_list_sql);

    my %user_stats;
    my %auth_info;

    foreach my $row (@authlist_ref) {
        $auth_info{$row->{auth_id}}{user_id}      = $row->{id};
        $auth_info{$row->{auth_id}}{day_limit}    = $row->{auth_day} // 0;
        $auth_info{$row->{auth_id}}{month_limit}  = $row->{auth_month} // 0;
        $auth_info{$row->{auth_id}}{day}          = 0;
        $auth_info{$row->{auth_id}}{month}        = 0;

        $user_stats{$row->{id}}{day_limit}        = $row->{day_quota} // 0;
        $user_stats{$row->{id}}{month_limit}      = $row->{month_quota} // 0;
        $user_stats{$row->{id}}{day}              = 0;
        $user_stats{$row->{id}}{month}            = 0;
    }

    # --- Считаем дневной трафик ---
    my @day_stats = get_records_sql($db,
        "SELECT auth_id, SUM(byte_in + byte_out) AS traf_all
         FROM user_stats
         WHERE ts >= ? AND ts < ?
         GROUP BY auth_id",
        $day_start->strftime('%Y-%m-%d %H:%M:%S'),
        $day_stop->strftime('%Y-%m-%d %H:%M:%S')
    );

    foreach my $row (@day_stats) {
        my $user_id = $auth_info{$row->{auth_id}}{user_id};
        $auth_info{$row->{auth_id}}{day} = $row->{traf_all} // 0;
        $user_stats{$user_id}{day} += $row->{traf_all} // 0;
    }

    # --- Считаем месячный трафик ---
    my @month_stats = get_records_sql($db,
        "SELECT auth_id, SUM(byte_in + byte_out) AS traf_all
         FROM user_stats
         WHERE ts >= ? AND ts < ?
         GROUP BY auth_id",
        $month_start->strftime('%Y-%m-%d %H:%M:%S'),
        $month_stop->strftime('%Y-%m-%d %H:%M:%S')
    );

    foreach my $row (@month_stats) {
        my $user_id = $auth_info{$row->{auth_id}}{user_id};
        $auth_info{$row->{auth_id}}{month} = $row->{traf_all} // 0;
        $user_stats{$user_id}{month} += $row->{traf_all} // 0;
    }

    # --- Блокировка по квотам для auth_id ---
    foreach my $auth_id (keys %auth_info) {
        next unless $auth_info{$auth_id}{day_limit} || $auth_info{$auth_id}{month_limit};

        my $day_limit   = ($auth_info{$auth_id}{day_limit} // 0) * $KB * $KB;
        my $month_limit = ($auth_info{$auth_id}{month_limit} // 0) * $KB * $KB;
        my $blocked_d   = $auth_info{$auth_id}{day} > $day_limit;
        my $blocked_m   = $auth_info{$auth_id}{month} > $month_limit;

        if ($blocked_d || $blocked_m) {
            my $history_msg;
            if ($blocked_d) {
                $history_msg = sprintf "Day quota limit found for auth_id: %d - Current: %d Max: %d",
                    $auth_id, $auth_info{$auth_id}{day}, $day_limit;
            }
            if ($blocked_m) {
                $history_msg = sprintf "Month quota limit found for auth_id: %d - Current: %d Max: %d",
                    $auth_id, $auth_info{$auth_id}{month}, $month_limit;
            }
            do_sql($db, "UPDATE user_auth SET blocked = 1, changed = 1 WHERE id = ?", $auth_id);
            db_log_verbose($db, $history_msg);
        }
    }

    # --- Блокировка по квотам для user_id ---
    foreach my $user_id (keys %user_stats) {
        next unless $user_stats{$user_id}{day_limit} || $user_stats{$user_id}{month_limit};

        my $day_limit   = ($user_stats{$user_id}{day_limit} // 0) * $KB * $KB;
        my $month_limit = ($user_stats{$user_id}{month_limit} // 0) * $KB * $KB;
        my $blocked_d   = $user_stats{$user_id}{day} > $day_limit;
        my $blocked_m   = $user_stats{$user_id}{month} > $month_limit;

        if ($blocked_d || $blocked_m) {
            my $history_msg;
            if ($blocked_d) {
                $history_msg = sprintf "Day quota limit found for user_id: %d - Current: %d Max: %d",
                    $user_id, $user_stats{$user_id}{day}, $day_limit;
            }
            if ($blocked_m) {
                $history_msg = sprintf "Month quota limit found for user_id: %d - Current: %d Max: %d",
                    $user_id, $user_stats{$user_id}{month}, $month_limit;
            }
            # Исправлено: user_list вместо User_user
            do_sql($db, "UPDATE user_list SET blocked = 1 WHERE id = ?", $user_id);
            do_sql($db, "UPDATE user_auth SET blocked = 1, changed = 1 WHERE user_id = ?", $user_id);
            db_log_verbose($db, $history_msg);
        }
    }

    Del_Variable($db, 'RECALC');
}

#--------------------------------------------------------------------------------

sub process_dhcp_request {
    my ($db, $type, $mac, $ip, $hostname, $client_id, $circuit_id, $remote_id) = @_;

    return unless $type && $type =~ /^(old|add|del)$/i;

    my $client_hostname = '';
    if ($hostname && $hostname ne 'undef' && $hostname !~ /UNDEFINED/i) {
        $client_hostname = $hostname;
    }

    my $auth_network = $office_networks->match_string($ip);
    if (!$auth_network) {
        log_error("Unknown network in dhcp request! IP: $ip");
        return;
    }

    $circuit_id //= '';
    $client_id  //= '';
    $remote_id  //= '';

    my $timestamp = time();
    my $ip_aton = StrToIp($ip);
    return unless defined $ip_aton;

    $mac = mac_splitted($mac);

    my $dhcp_event_time = GetNowTime($timestamp);

    my $dhcp_record = {
        mac           => $mac,
        ip            => $ip,
        ip_aton       => $ip_aton,
        hostname      => $client_hostname,
        network       => $auth_network,
        type          => $type,
        hostname_utf8 => $client_hostname,
        ts            => $timestamp,
        last_time     => time(),
        circuit_id    => $circuit_id,
        client_id     => $client_id,
        remote_id     => $remote_id,
        hotspot       => is_hotspot($db, $ip),
    };

    # --- Ищем существующую запись ---
    my $auth_record = get_record_sql($db,"SELECT * FROM user_auth WHERE ip = ? AND mac = ? AND deleted = 0 ORDER BY last_found DESC", $ip, $mac );

    # Если запись не найдена и тип 'del' — выходим
    if (!$auth_record && $type eq 'del') {
        db_log_info($db, "Auth recrod for ip: $ip mac: $mac not found. Dhcp request type: $type");
        return;
    }

    # Если запись не найдена и тип 'add'/'old' — создаём
    if (!$auth_record && ($type eq 'add' || $type eq 'old')) {
        my $res_id = resurrection_auth($db, $dhcp_record);
        if (!$res_id) {
            db_log_error($db, "Error creating an ip address record for ip=$ip and mac=$mac!");
            return;
        }
        $auth_record = get_record_sql($db, "SELECT * FROM user_auth WHERE id = ?", $res_id);
        db_log_info($db, "Check for new auth. Found id: $res_id", $res_id);
    }

    if (!$auth_record || !$auth_record->{id}) {
        db_log_error($db, "Record not found/created for ip: $ip mac: $mac not found. Dhcp request type: $type!");
        return;
        }

    my $auth_id = $auth_record->{id};
    my $auth_ou_id = $auth_record->{ou_id};

    $dhcp_record->{auth_id} = $auth_id;
    $dhcp_record->{auth_ou_id} = $auth_ou_id;

    update_dns_record_by_dhcp($db, $dhcp_record, $auth_record);

    # --- Обработка ADD ---
    if ($type eq 'add' && $dhcp_record->{hostname_utf8}) {
        my $auth_rec = {
            dhcp_hostname => $dhcp_record->{hostname_utf8},
            dhcp_time     => $dhcp_event_time,
            arp_found     => $dhcp_event_time,
            created_by    => 'dhcp',
        };
        db_log_verbose($db, "Add lease by dhcp event for dynamic clients id: $auth_id ip: $ip", $auth_id);
        update_record($db, 'user_auth', $auth_rec, 'id = ?', $auth_id);
    }

    # --- Обработка OLD ---
    if ($type eq 'old') {
        my $auth_rec = {
            dhcp_action => $type,
            dhcp_time   => $dhcp_event_time,
            created_by  => 'dhcp',
            arp_found   => $dhcp_event_time,
        };
        db_log_verbose($db, "Update lease by dhcp event for dynamic clients id: $auth_id ip: $ip", $auth_id);
        update_record($db, 'user_auth', $auth_rec, 'id = ?', $auth_id);
    }

    # --- Обработка DEL ---
    if ($type eq 'del' && $auth_id) {
        if ($auth_record->{dhcp_time} =~ /^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$/) {
            my $d_time = mktime($6, $5, $4, $3, $2 - 1, $1 - 1900);
            if (time() - $d_time > 60 && (is_dynamic_ou($db, $auth_ou_id) || is_default_ou($db, $auth_ou_id))) {
                db_log_info($db, "Remove user ip record by dhcp release event for dynamic clients id: $auth_id ip: $ip", $auth_id);
                my $auth_rec = {
                    dhcp_action => $type,
                    dhcp_time   => $dhcp_event_time,
                };
                update_record($db, 'user_auth', $auth_rec, 'id = ?', $auth_id);

                # Удаляем запись, если она из динамического или дефолтного пула
                if (is_default_ou($db, $auth_ou_id) || (is_dynamic_ou($db, $auth_ou_id) && $auth_record->{dynamic})) {
                    delete_user_auth($db, $auth_id);

                    # Проверяем, остались ли другие записи у пользователя
                    my $u_count = get_record_sql($db,
                        "SELECT COUNT(*) AS cnt FROM user_auth WHERE deleted = 0 AND user_id = ?",
                        $auth_record->{user_id}
                    );
                    if ($u_count && $u_count->{cnt} == 0) {
                        delete_user($db, $auth_record->{user_id});
                    }
                }
            }
        }
    }

    # --- Пропуск логирования для hotspot или ignore_update_dhcp_event ---
    if ($dhcp_record->{hotspot} && $ignore_hotspot_dhcp_log) {
        return $dhcp_record;
    }
    if ($ignore_update_dhcp_event && $type eq 'old') {
        return $dhcp_record;
    }

    # --- Логируем событие ---
    my $dhcp_log = {
        auth_id        => $auth_id // 0,
        ip             => $ip,
        ip_int         => $ip_aton,
        mac            => $mac,
        action         => $type,
        dhcp_hostname  => $client_hostname,
        ts             => $dhcp_event_time,
        circuit_id     => $circuit_id,
        client_id      => $client_id,
        remote_id      => $remote_id,
    };

    insert_record($db, 'dhcp_log', $dhcp_log);

    return $dhcp_record;
}

1;
}
