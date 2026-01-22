package eyelib::database;

#
# Copyright (C) Roman Dmitriev, rnd@rajven.ru
#

# commit example
# Начинаем транзакцию вручную
#$db->{AutoCommit} = 0;
#eval {
#    for my $row (@rows) {
#        insert_record($db, 'user_auth', $row);
#        insert_record($db, 'user_auth_alias', $row2);
#    }
#    $db->commit();
#};
#if ($@) {
#    eval { $db->rollback(); };
#    die "Migration failed: $@";
#}
#$db->{AutoCommit} = 1;

use warnings FATAL => 'all';
use feature ':5.20';

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
use DateTime;
use POSIX qw(mktime ctime strftime);
use File::Temp qw(tempfile);
use DBI;
use DBD::Pg qw(:pg_types);
use Text::CSV;

our @ISA = qw(Exporter);

our @EXPORT = qw(
get_office_subnet
get_notify_subnet
is_hotspot
get_queue
get_group
get_subnet_description
get_filter_instance_description
get_vendor_name
get_ou
get_device_name
get_device_model
get_device_model_name
get_building
get_filter
get_login
StrToIp
IpToStr
prepare_audit_message
batch_db_sql_cached
batch_db_sql_csv
reconnect_db
write_db_log
db_log_debug
db_log_error
db_log_info
db_log_verbose
db_log_warning
normalize_value
get_table_columns
init_db
do_sql
_execute_param
do_sql_param
get_option_safe
get_count_records
get_id_record
get_records_sql
get_record_sql
get_diff_rec
update_record
insert_record
delete_record
get_option
init_option
is_system_ou
Set_Variable
Get_Variable
Del_Variable
clean_variables
build_db_schema

$add_rules
$L_WARNING
$L_INFO
$L_DEBUG
$L_ERROR
$L_VERBOSE

%db_schema
);

BEGIN
{

#---------------------------------------------------------------------------------------------------------------

our $add_rules;

our $L_ERROR = 0;
our $L_WARNING = 1;
our $L_INFO = 2;
our $L_VERBOSE = 3;
our $L_DEBUG = 255;

our %acl_fields = (
    'ip' => '1',
    'ip_int' => '1',
    'enabled'=>'1',
    'dhcp'=>'1',
    'filter_group_id'=>'1',
    'deleted'=>'1',
    'dhcp_acl'=>'1',
    'queue_id'=>'1',
    'mac'=>'1',
    'blocked'=>'1'
);

our %dhcp_fields = (
    'ip' => '1',
    'dhcp_acl'=>'1',
    'dhcp_option_set'=>'1',
    'dhcp'=>'1',
    'deleted'=>'1',
    'mac'=>'1',
);

our %dns_fields = (
    'ip' => '1',
    'dns_name'=>'1',
    'dns_ptr_only'=>'1',
    'alias'=>'1',
);

our %db_schema;

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

# Вспомогательная функция для проверки "пустого" значения
sub _is_empty {
    my ($val) = @_;
    return !defined $val || $val eq '';
}

#---------------------------------------------------------------------------------------------------------------

sub get_queue {
    my ($dbh, $queue_value) = @_;
    return '' if _is_empty($queue_value);
    my $queue = get_record_sql($dbh, "SELECT queue_name FROM queue_list WHERE id = ?", $queue_value);
    return $queue->{queue_name} // '';
}

#---------------------------------------------------------------------------------------------------------------

sub get_group {
    my ($dbh, $group_id) = @_;
    return '' if _is_empty($group_id);
    my $group = get_record_sql($dbh, "SELECT group_name FROM group_list WHERE id = ?", $group_id);
    return $group->{group_name} // '';
}

#---------------------------------------------------------------------------------------------------------------

sub get_subnet_description {
    my ($dbh, $subnet_id) = @_;
    return '' if _is_empty($subnet_id);
    my $subnet = get_record_sql($dbh, "SELECT * FROM subnets WHERE id = ?", $subnet_id);
    return '' unless $subnet;
    my $desc = $subnet->{description} // '';
    return "$subnet->{subnet}&nbsp;($desc)";
}

#---------------------------------------------------------------------------------------------------------------

sub get_filter_instance_description {
    my ($dbh, $instance_id) = @_;
    return '' if _is_empty($instance_id);
    my $instance = get_record_sql($dbh, "SELECT * FROM filter_instances WHERE id = ?", $instance_id);
    return '' unless $instance;
    my $desc = $instance->{description} // '';
    return "$instance->{name}&nbsp;($desc)";
}

#---------------------------------------------------------------------------------------------------------------

sub get_vendor_name {
    my ($dbh, $v_id) = @_;
    return '' if _is_empty($v_id);
    my $vendor = get_record_sql($dbh, "SELECT name FROM vendors WHERE id = ?", $v_id);
    return $vendor->{name} // '';
}

#---------------------------------------------------------------------------------------------------------------

sub get_ou {
    my ($dbh, $ou_value) = @_;
    return undef if _is_empty($ou_value);
    my $ou_name = get_record_sql($dbh, "SELECT ou_name FROM ou WHERE id = ?", $ou_value);
    return $ou_name ? $ou_name->{ou_name} : undef;
}

#---------------------------------------------------------------------------------------------------------------

sub get_device_name {
    my ($dbh, $device_id) = @_;
    return undef if _is_empty($device_id);
    my $dev = get_record_sql($dbh, "SELECT device_name FROM devices WHERE id = ?", $device_id);
    return $dev ? $dev->{device_name} : undef;
}

#---------------------------------------------------------------------------------------------------------------

sub get_device_model {
    my ($dbh, $model_value) = @_;
    return undef if _is_empty($model_value);
    my $model_name = get_record_sql($dbh, "SELECT model_name FROM device_models WHERE id = ?", $model_value);
    return $model_name ? $model_name->{model_name} : undef;
}

#---------------------------------------------------------------------------------------------------------------

sub get_device_model_name {
    my ($dbh, $model_value) = @_;
    return '' if _is_empty($model_value);
    my $row = get_record_sql($dbh, "SELECT M.id, M.model_name, V.name FROM device_models M, vendors V  WHERE M.vendor_id = V.id AND M.id = ?", $model_value);
    return '' unless $row;
    my $vendor = $row->{name} // '';
    my $model = $row->{model_name} // '';
    return "$vendor $model";
}

#---------------------------------------------------------------------------------------------------------------

sub get_building {
    my ($dbh, $building_value) = @_;
    return undef if _is_empty($building_value);
    my $building_name = get_record_sql($dbh, "SELECT name FROM building WHERE id = ?", $building_value);
    return $building_name ? $building_name->{name} : undef;
}

#---------------------------------------------------------------------------------------------------------------

sub get_filter {
    my ($dbh, $filter_value) = @_;
    return '' if _is_empty($filter_value);
    my $filter = get_record_sql($dbh, "SELECT name FROM filter_list WHERE id = ?", $filter_value);
    return $filter->{name} // '';
}

#---------------------------------------------------------------------------------------------------------------

sub get_login {
    my ($dbh, $user_id) = @_;
    return '' if _is_empty($user_id);
    my $login = get_record_sql($dbh, "SELECT login FROM user_list WHERE id = ?", $user_id);
    return $login->{login} // '';
}

#---------------------------------------------------------------------------------------------------------------

sub prepare_audit_message {
    my ($dbh, $table, $old_data, $new_data, $record_id, $operation) = @_;

    # === 1. Конфигурация отслеживаемых таблиц ===
    my %audit_config = (
        'auth_rules' => {
            summary => ['rule'],
            fields  => ['user_id', 'ou_id', 'rule_type', 'rule', 'description']
        },
        'building' => {
            summary => ['name'],
            fields  => ['name', 'description']
        },
        'customers' => {
            summary => ['login'],
            fields  => ['login', 'description', 'rights']
        },
        'devices' => {
            summary => ['device_name'],
            fields  => [
                'device_type', 'device_model_id', 'vendor_id', 'device_name', 'building_id',
                'ip', 'login', 'protocol', 'control_port', 'port_count', 'sn',
                'description', 'snmp_version', 'snmp3_auth_proto', 'snmp3_priv_proto',
                'snmp3_user_rw', 'snmp3_user_ro', 'community', 'rw_community',
                'discovery', 'netflow_save', 'user_acl', 'dhcp', 'nagios',
                'active', 'queue_enabled', 'connected_user_only', 'user_id'
            ]
        },
        'device_filter_instances' => {
            summary => [],
            fields  => ['instance_id', 'device_id']
        },
        'device_l3_interfaces' => {
            summary => ['name'],
            fields  => ['device_id', 'snmpin', 'interface_type', 'name']
        },
        'device_models' => {
            summary => ['model_name'],
            fields  => ['model_name', 'vendor_id', 'poe_in', 'poe_out', 'nagios_template']
        },
        'device_ports' => {
            summary => ['port', 'ifname'],
            fields  => [
                'device_id', 'snmp_index', 'port', 'ifname', 'port_name', 'description',
                'target_port_id', 'auth_id', 'last_mac_count', 'uplink', 'nagios',
                'skip', 'vlan', 'tagged_vlan', 'untagged_vlan', 'forbidden_vlan'
            ]
        },
        'filter_instances' => {
            summary => ['name'],
            fields  => ['name', 'description']
        },
        'filter_list' => {
            summary => ['name'],
            fields  => ['name', 'description', 'proto', 'dst', 'dstport', 'srcport', 'filter_type']
        },
        'gateway_subnets' => {
            summary => [],
            fields  => ['device_id', 'subnet_id']
        },
        'group_filters' => {
            summary => [],
            fields  => ['group_id', 'filter_id', 'rule_order', 'action']
        },
        'group_list' => {
            summary => ['group_name'],
            fields  => ['instance_id', 'group_name', 'description']
        },
        'ou' => {
            summary => ['ou_name'],
            fields  => [
                'ou_name', 'description', 'default_users', 'default_hotspot',
                'nagios_dir', 'nagios_host_use', 'nagios_ping', 'nagios_default_service',
                'enabled', 'filter_group_id', 'queue_id', 'dynamic', 'life_duration', 'parent_id'
            ]
        },
        'queue_list' => {
            summary => ['queue_name'],
            fields  => ['queue_name', 'download', 'upload']
        },
        'subnets' => {
            summary => ['subnet'],
            fields  => [
                'subnet', 'vlan_tag', 'ip_int_start', 'ip_int_stop', 'dhcp_start', 'dhcp_stop',
                'dhcp_lease_time', 'gateway', 'office', 'hotspot', 'vpn', 'free', 'dhcp',
                'static', 'dhcp_update_hostname', 'discovery', 'notify', 'description'
            ]
        },
        'user_auth' => {
            summary => ['ip', 'dns_name'],
            fields  => [
                'user_id', 'ou_id', 'ip', 'save_traf', 'enabled', 'dhcp', 'filter_group_id',
                'dynamic', 'end_life', 'description', 'dns_name', 'dns_ptr_only', 'wikiname',
                'dhcp_acl', 'queue_id', 'mac', 'dhcp_option_set', 'blocked', 'day_quota',
                'month_quota', 'device_model_id', 'firmware', 'client_id', 'nagios',
                'nagios_handler', 'link_check', 'deleted'
            ]
        },
        'user_auth_alias' => {
            summary => ['alias'],
            fields  => ['auth_id', 'alias', 'description']
        },
        'user_list' => {
            summary => ['login'],
            fields  => [
                'login', 'description', 'enabled', 'blocked', 'deleted', 'ou_id',
                'device_id', 'filter_group_id', 'queue_id', 'day_quota', 'month_quota', 'permanent'
            ]
        },
        'vendors' => {
            summary => ['name'],
            fields  => ['name']
        }
    );

    return undef unless exists $audit_config{$table};

    my $summary_fields   = $audit_config{$table}{summary};
    my $monitored_fields = $audit_config{$table}{fields};

    # === 2. Нормализация данных и определение изменений ===
    my %changes;

    if ($operation eq 'insert') {
        for my $field (@$monitored_fields) {
            if (exists $new_data->{$field}) {
                $changes{$field} = { old => undef, new => $new_data->{$field} };
            }
        }
    }
    elsif ($operation eq 'delete') {
        for my $field (@$monitored_fields) {
            if (exists $old_data->{$field}) {
                $changes{$field} = { old => $old_data->{$field}, new => undef };
            }
        }
    }
    elsif ($operation eq 'update') {
        $old_data //= {};
        $new_data //= {};
        for my $field (@$monitored_fields) {
            next unless exists $new_data->{$field};  # частичное обновление
            my $old_val = exists $old_data->{$field} ? $old_data->{$field} : undef;
            my $new_val = $new_data->{$field};

            my $old_str = !defined($old_val) ? '' : "$old_val";
            my $new_str = !defined($new_val) ? '' : "$new_val";

            if ($old_str ne $new_str) {
                $changes{$field} = { old => $old_val, new => $new_val };
            }
        }
    }

    return undef unless %changes;

    # === 3. Краткое описание записи ===
    my @summary_parts;
    for my $field (@$summary_fields) {
        my $val = defined($new_data->{$field}) ? $new_data->{$field}
                : (defined($old_data->{$field}) ? $old_data->{$field} : undef);
        push @summary_parts, "$val" if defined $val && $val ne '';
    }

    my $summary_label = @summary_parts
        ? '"' . join(' | ', @summary_parts) . '"'
        : "ID=$record_id";

    # === 4. Расшифровка *_id полей ===
    my %resolved_changes;
    for my $field (keys %changes) {
        my $old_resolved = resolve_reference_value($dbh, $field, $changes{$field}{old});
        my $new_resolved = resolve_reference_value($dbh, $field, $changes{$field}{new});
        $resolved_changes{$field} = { old => $old_resolved, new => $new_resolved };
    }

    # === 5. Формирование сообщения ===
    my $op_label = 'Updated';
    if ($operation eq 'insert') {
        $op_label = 'Created';
    } elsif ($operation eq 'delete') {
        $op_label = 'Deleted';
    } else {
        $op_label = ucfirst($operation);
    }

    my $message = sprintf("[%s] %s (%s) in table `%s`:\n",
        $op_label,
        ucfirst($table),
        $summary_label,
        $table
    );

    for my $field (sort keys %resolved_changes) {
        my $change = $resolved_changes{$field};
        if ($operation eq 'insert') {
            if (defined $change->{new}) {
                $message .= sprintf("  %s: %s\n", $field, $change->{new});
            }
        } elsif ($operation eq 'delete') {
            if (defined $change->{old}) {
                $message .= sprintf("  %s: %s\n", $field, $change->{old});
            }
        } else { # update
            my $old_display = !defined($change->{old}) ? '[NULL]' : $change->{old};
            my $new_display = !defined($change->{new}) ? '[NULL]' : $change->{new};
            $message .= sprintf("  %s: \"%s\" → \"%s\"\n", $field, $old_display, $new_display);
        }
    }

    chomp $message;
    return $message;
}

#---------------------------------------------------------------------------------------------------------------

sub resolve_reference_value {
    my ($dbh, $field, $value) = @_;

    return undef if !defined $value || $value eq '';

    # Проверка на целое число (как в PHP)
    if ($value !~ /^[+-]?\d+$/) {
        return "$value";
    }
    my $as_int = int($value);
    if ("$as_int" ne "$value") {
        return "$value";
    }

    my $id = $as_int;

    if ($field eq 'device_id') {
        return get_device_name($dbh, $id) // "Device#$id";
    }
    elsif ($field eq 'building_id') {
        return get_building($dbh, $id) // "Building#$id";
    }
    elsif ($field eq 'user_id') {
        return get_login($dbh, $id) // "User#$id";
    }
    elsif ($field eq 'ou_id') {
        return get_ou($dbh, $id) // "OU#$id";
    }
    elsif ($field eq 'vendor_id') {
        return get_vendor_name($dbh, $id) // "Vendor#$id";
    }
    elsif ($field eq 'device_model_id') {
        return get_device_model_name($dbh, $id) // "Model#$id";
    }
    elsif ($field eq 'instance_id') {
        return get_filter_instance_description($dbh, $id) // "FilterInstance#$id";
    }
    elsif ($field eq 'subnet_id') {
        return get_subnet_description($dbh, $id) // "Subnet#$id";
    }
    elsif ($field eq 'group_id') {
        return get_group($dbh, $id) // "FilterGroup#$id";
    }
    elsif ($field eq 'filter_id') {
        return get_filter($dbh, $id) // "Filter#$id";
    }
    elsif ($field eq 'filter_group_id') {
        return get_group($dbh, $id) // "FilterGroup#$id";
    }
    elsif ($field eq 'queue_id') {
        return get_queue($dbh, $id) // "Queue#$id";
    }
    elsif ($field eq 'auth_id') {
        return 'None' if $id <= 0;
        my $sql = "
            SELECT
                COALESCE(ul.login, CONCAT('User#', ua.user_id)) AS login,
                ua.ip,
                ua.dns_name
            FROM user_auth ua
            LEFT JOIN user_list ul ON ul.id = ua.user_id
            WHERE ua.id = ?
        ";
        my $row = get_record_sql($dbh, $sql, $id);
        return "Auth#$id" unless $row;

        my @parts;
        push @parts, "login: $row->{login}" if $row->{login} && $row->{login} ne '';
        push @parts, "IP: $row->{ip}"       if $row->{ip} && $row->{ip} ne '';
        push @parts, "DNS: $row->{dns_name}" if $row->{dns_name} && $row->{dns_name} ne '';
        return @parts ? join(', ', @parts) : "Auth#$id";
    }
    elsif ($field eq 'target_port_id') {
        return 'None' if $id == 0;
        my $sql = "
            SELECT CONCAT(d.device_name, '[', dp.port, ']')
            FROM device_ports dp
            JOIN devices d ON d.id = dp.device_id
            WHERE dp.id = ?
        ";
        my $name = $dbh->selectrow_array($sql, undef, $id);
        return $name // "Port#$id";
    }
    else {
        return "$value";
    }
}

#---------------------------------------------------------------------------------------------------------------

sub build_db_schema {
    my ($dbh) = @_;

    # Определяем тип СУБД
    my $db_type = lc($dbh->{Driver}->{Name});
    die "Unsupported database driver: $db_type" 
        unless $db_type eq 'mysql' || $db_type eq 'pg';

    # Получаем имя базы данных
    my $db_name;
    if ($db_type eq 'mysql') {
        ($db_name) = $dbh->selectrow_array("SELECT DATABASE()");
    } elsif ($db_type eq 'pg') {
        ($db_name) = $dbh->selectrow_array("SELECT current_database()");
    }

    my $db_info;
    $db_info->{db_type}=$db_type;
    $db_info->{db_name}=$db_name;
    return $db_info if (exists $db_schema{$db_type}{$db_name});
    # Получаем список таблиц
    my @tables;
    if ($db_type eq 'mysql') {
        my $sth = $dbh->prepare("SHOW TABLES");
        $sth->execute();
        @tables = map { $_->[0] } @{$sth->fetchall_arrayref()};
    } elsif ($db_type eq 'pg') {
        my $sql = q{
            SELECT tablename 
            FROM pg_tables 
            WHERE schemaname = 'public'
        };
        my $sth = $dbh->prepare($sql);
        $sth->execute();
        @tables = map { $_->[0] } @{$sth->fetchall_arrayref()};
    }

    # Собираем схему
    for my $table (@tables) {
        my $sth = $dbh->column_info(undef, undef, $table, '%');
        while (my $col = $sth->fetchrow_hashref) {
            my $col_name = lc($col->{COLUMN_NAME});
            $db_schema{$db_type}{$db_name}{$table}{$col_name} = {
                type     => $col->{TYPE_NAME} // '',
                nullable => $col->{NULLABLE}  // 1,
                default  => $col->{COLUMN_DEF} // undef,
            };
        }
    }
    return $db_info;
}

#---------------------------------------------------------------------------------------------------------------

sub normalize_value {
    my ($value, $col_info) = @_;

    # Если значение пустое — обрабатываем по правилам колонки
    if (!defined $value || $value eq '' || $value =~ /^(?:NULL|\\N)$/i) {
        return $col_info->{nullable} ? undef : _default_for_type($col_info);
    }

    my $type = lc($col_info->{type});

    # --- Числовые типы: приводим к числу, если выглядит как число ---
    if ($type =~ /^(?:tinyint|smallint|mediumint|int|integer|bigint|serial|bigserial)$/i) {
        # Просто конвертируем строку в число (Perl сам обрежет мусор)
        # Например: "123abc" → 123, "abc" → 0
        return 0 + $value;
    }

    # --- Булевы: приводим к 0/1 ---
    if ($type =~ /^(?:bool|boolean|bit)$/i) {
        return $value ? 1 : 0;
    }

    # --- Временные типы: оставляем как есть, но фильтруем "нулевые" даты MySQL ---
    if ($type =~ /^(?:timestamp|datetime|date|time)$/i) {
        # Это частая проблема при миграции — '0000-00-00' ломает PostgreSQL
        return undef if $value =~ /^0000-00-00/;
        return $value;
    }

    # --- Все остальные типы (строки, inet, json и т.д.) — передаём как есть ---
    return $value;
}

# Вспомогательная: безопасное значение по умолчанию
sub _default_for_type {
    my ($col) = @_;

    # Используем DEFAULT, только если он простой литерал (не выражение)
    if (defined $col->{default}) {
        my $def = $col->{default};
        # Пропускаем выражения: nextval(), CURRENT_TIMESTAMP, NOW(), uuid() и т.п.
        if ($def !~ /(nextval|current_timestamp|now|uuid|auto_increment|::)/i) {
            # Убираем одинарные кавычки, если строка: 'value' → value
            if ($def =~ /^'(.*)'$/) {
                return $1;
            }
            # Если похоже на число — вернём как число
            if ($def =~ /^[+-]?\d+$/) {
                return 0 + $def;
            }
            return $def;
        }
    }

    # Фолбэк по типу
    my $type = lc($col->{type});
    if ($type =~ /^(?:tinyint|smallint|int|integer|bigint)/i) { return 0; }
    if ($type =~ /^(?:char|varchar|text)/i) { return ''; }
    if ($type =~ /^(?:timestamp|datetime)/i) { return GetNowTime(); }
    return undef;
}

#---------------------------------------------------------------------------------------------------------------

sub get_table_columns {
    my ($db, $table) = @_;
    my %columns;
    my $sth = $db->column_info(undef, undef, $table, '%');
    while (my $row = $sth->fetchrow_hashref) {
        my $name = lc($row->{COLUMN_NAME});  # ← приводим к нижнему регистру сразу!
        $columns{$name} = {
            type     => $row->{TYPE_NAME} // '',
            nullable => $row->{NULLABLE}  // 1,
            default  => $row->{COLUMN_DEF} // undef,
        };
    }
    return %columns;  # возвращает список: key1, val1, key2, val2...
}

#---------------------------------------------------------------------------------------------------------------

sub StrToIp {
return unpack('N',pack('C4',split(/\./,$_[0])));
}

#---------------------------------------------------------------------------------------------------------------

sub IpToStr {
my $nIP = shift;
my $res = (($nIP>>24) & 255) .".". (($nIP>>16) & 255) .".". (($nIP>>8) & 255) .".". ($nIP & 255);
return $res;
}

#---------------------------------------------------------------------------------------------------------------

sub batch_db_sql_cached {
    my ( $sql, $data) = @_;
    my $db=init_db();
    # Запоминаем исходное состояние AutoCommit
    my $original_autocommit = $db->{AutoCommit};
    eval {
        # Выключаем AutoCommit для транзакции
        $db->{AutoCommit} = 0;
        my $sth = $db->prepare_cached($sql)  or die "Unable to prepare SQL: " . $db->errstr;
        for my $params (@$data) {
            next unless @$params;
            $sth->execute(@$params) or die "Unable to execute with params [" . join(',', @$params) . "]: " . $sth->errstr;
        }
        $db->commit();
        1;
    } or do {
        my $err = $@ || 'Unknown error';
        eval { $db->rollback() };
        warn "batch_sql_cached failed: $err";
        # Восстанавливаем AutoCommit даже при ошибке
        $db->{AutoCommit} = $original_autocommit;
        return 0;
    };
    # Восстанавливаем исходный режим AutoCommit
    $db->{AutoCommit} = $original_autocommit;
    $db->disconnect();
    return 1;
}

#---------------------------------------------------------------------------------------------------------------

sub batch_db_sql_csv {
    my ($table, $data) = @_;
    return 0 unless @$data;

    # Первая строка — заголовки (имена столбцов)
    my $header_row = shift @$data;
    unless ($header_row && ref($header_row) eq 'ARRAY' && @$header_row) {
        log_error("First row must be column names (array reference)");
        return 0;
    }
    my @columns = @$header_row;

    # Теперь @$data содержит только строки данных
    my $data_rows = $data;

    # Если нет данных — только заголовок
    unless (@$data_rows) {
        log_debug("No data rows to insert, only header");
        return 1;
    }

    my $db = init_db();

    my $original_autocommit = $db->{AutoCommit};
    $db->{AutoCommit} = 0;

    if (get_db_type($db) eq 'mysql') {
        # --- MySQL: попытка LOAD DATA, fallback на INSERT ---
        log_debug("Using LOAD DATA LOCAL INFILE for MySQL");

        my $fh = File::Temp->new(UNLINK => 1);
        my $fname = $fh->filename;
        binmode($fh, ':utf8');

        my $csv = Text::CSV->new({
            binary         => 1,
            quote_char     => '"',
            escape_char    => '"',
            sep_char       => ',',
            eol            => "\r\n",
            always_quote   => 1,
        }) or do {
            my $err = "Cannot create Text::CSV: " . Text::CSV->error_diag();
            log_error($err);
            $db->{AutoCommit} = $original_autocommit;
            $db->disconnect();
            return 0;
        };
        # Пишем заголовок
        $csv->print($fh, \@columns);
        # Пишем данные
        for my $row (@$data_rows) {
            next unless $row && ref($row) eq 'ARRAY' && @$row == @columns;
            my @vals = map { defined($_) ? $_ : 'NULL' } @$row;
            $csv->print($fh, \@vals);
        }
        close $fh;
        my $col_list = join(', ', map { $db->quote_identifier($_) } @columns);
        my $query = qq{LOAD DATA LOCAL INFILE '$fname' INTO TABLE $table FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '"' LINES TERMINATED BY '\r\n' IGNORE 1 LINES ($col_list)};
        my $load_ok = eval { $db->do($query); 1 };
        if (!$load_ok) {
            my $err = "MySQL LOAD DATA failed: $@";
            log_error($err);
            log_debug("Falling back to bulk INSERT for MySQL");
            goto FALLBACK_INSERT_MYSQL;
        }
        $db->commit();
        $db->{AutoCommit} = $original_autocommit;

        $db->disconnect();
        return 1;

        # ========================
        # Fallback для MySQL
        # ========================
        FALLBACK_INSERT_MYSQL:
        {
            my $quoted_cols = join(', ', map { $db->quote_identifier($_) } @columns);
            my $placeholders = join(',', ('?') x @columns);
            my $sql = "INSERT INTO $table ($quoted_cols) VALUES ($placeholders)";
            my $sth = $db->prepare($sql);

            my $success = eval {
                for my $row (@$data_rows) {
                    next unless $row && ref($row) eq 'ARRAY' && @$row == @columns;
                    my @vals = map { defined($_) ? $_ : undef } @$row;
                    $sth->execute(@vals);
                }
                1;
            };

            if ($success) {
                $db->commit();
            } else {
                eval { $db->rollback(); };
                my $err = "MySQL bulk INSERT failed: $@";
                log_error($err);
                $db->{AutoCommit} = $original_autocommit;
                $db->disconnect();
                return 0;
            }
            $db->{AutoCommit} = $original_autocommit;
        }

    } elsif (get_db_type($db) eq 'pg') {

        if (!$db->can('pg_putcopydata') || !$db->can('pg_putcopyend')) {
            log_debug("pg_putcopydata/pg_putcopyend not available — falling back to bulk INSERT");
            goto FALLBACK_INSERT_PG;
        }

        my $col_list = join(', ', map { $db->quote_identifier($_) } @columns);
        my $copy_sql = "COPY $table ($col_list) FROM STDIN WITH (FORMAT CSV, HEADER true)";

        my $use_header_as_data;
        my $start_ok = eval { $db->do($copy_sql); 1 };

        if (!$start_ok) {
            log_debug("COPY with HEADER failed: $@ — trying without HEADER");
            $copy_sql = "COPY $table ($col_list) FROM STDIN WITH (FORMAT CSV)";
            $start_ok = eval { $db->do($copy_sql); 1 };
            if (!$start_ok) {
                log_debug("COPY failed entirely: $@ — falling back to bulk INSERT");
                goto FALLBACK_INSERT_PG;
            }
            $use_header_as_data = 1;
        } else {
            $use_header_as_data = 0;
        }

        log_debug("Using CSV COPY for PostgreSQL");

        my $csv = Text::CSV->new({
            binary         => 1,
            quote_char     => '"',
            escape_char    => '"',
            sep_char       => ',',
            eol            => "\n",
            always_quote   => 1,
        }) or do {
            my $err = "Cannot create Text::CSV: " . Text::CSV->error_diag();
            log_error($err);
            eval { $db->pg_putcopyend(); };
            $db->{AutoCommit} = $original_autocommit;
            $db->disconnect();
            return 0;
        };

        my $success = eval {
            if ($use_header_as_data) {
                $csv->combine(@columns);
                $db->pg_putcopydata($csv->string);
            }
            for my $row (@$data_rows) {
                next unless $row && ref($row) eq 'ARRAY' && @$row == @columns;
                my @vals = map { defined($_) ? $_ : undef } @$row;
                $csv->combine(@vals);
                $db->pg_putcopydata($csv->string);
            }
            $db->pg_putcopyend();
            1;
        };

        if ($success) {
            $db->commit();
        } else {
            eval { $db->rollback(); };
            my $err = "CSV COPY failed: $@";
            log_error($err);
            eval { $db->pg_putcopyend(); };
            goto FALLBACK_INSERT_PG;
        }
        # ========================
        # Fallback для PostgreSQL
        # ========================
        FALLBACK_INSERT_PG:
        {
            my $quoted_cols = join(', ', map { $db->quote_identifier($_) } @columns);
            my $placeholders = join(',', ('?') x @columns);
            my $sql = "INSERT INTO $table ($quoted_cols) VALUES ($placeholders)";
            my $sth = $db->prepare($sql);

            my $success = eval {
                for my $row (@$data_rows) {
                    next unless $row && ref($row) eq 'ARRAY' && @$row == @columns;
                    my @vals = map { defined($_) ? $_ : undef } @$row;
                    $sth->execute(@vals);
                }
                1;
            };

            if ($success) {
                $db->commit();
            } else {
                eval { $db->rollback(); };
                my $err = "PostgreSQL bulk INSERT failed: $@";
                log_error($err);
                $db->{AutoCommit} = $original_autocommit;
                $db->disconnect();
                return 0;
            }
        }

    } else {
        my $err = "Unsupported DBTYPE: ". get_db_type($db);
        log_error($err);
        $db->{AutoCommit} = $original_autocommit;
        $db->disconnect();
        return 0;
    }

    $db->{AutoCommit} = $original_autocommit;
    $db->disconnect();
    return 1;
}
#---------------------------------------------------------------------------------------------------------------

sub reconnect_db {
    my $db_ref = shift;

    # Если соединение активно — ничего не делаем
    if ($$db_ref && $$db_ref->ping) {
        return 1;
    }

    # Сохраняем AutoCommit из текущего соединения (если есть)
    my $original_autocommit = 1;
    if ($$db_ref) {
        $original_autocommit = $$db_ref->{AutoCommit};
        eval { $$db_ref->disconnect; };
        $$db_ref = undef;
    }

    # Пытаемся переподключиться
    eval {
        $$db_ref = init_db($original_autocommit);
        unless ($$db_ref && $$db_ref->ping) {
            log_die "Failed to establish database connection";
        }
        1;
    } or do {
        my $error = $@ || 'Unknown error';
        $$db_ref = undef;
        log_die "Database reconnection failed: $error";
        return 0;
    };

    return 1;
}

#---------------------------------------------------------------------------------------------------------------

sub write_db_log {
my $db=shift;
my $msg=shift;
my $level = shift || $L_VERBOSE;
my $auth_id = shift || 0;
return if (!$db);
return if (!$msg);
$msg=~s/[\'\"]//g;
my $db_log = 0;

# Переподключение
unless (reconnect_db(\$db)) {
log_error("No database connection available");
$db_log = 0;
}

if ($level eq $L_ERROR and $log_level >= $L_ERROR) { log_error($msg); $db_log = 1; }
if ($level eq $L_WARNING and $log_level >= $L_WARNING) { log_warning($msg); $db_log = 1; }
if ($level eq $L_INFO and $log_level >= $L_INFO) { log_info($msg); $db_log = 1; }
if ($level eq $L_VERBOSE and $log_level >= $L_VERBOSE) { log_verbose($msg); $db_log = 1; }
if ($level eq $L_DEBUG and $log_level >= $L_DEBUG) { log_debug($msg); return; }

if ($db_log) {
#my $new_id = do_sql($dbh, 'INSERT INTO user_list (login) VALUES (?)', 'Ivan');
do_sql($db,'INSERT INTO worklog(customer,message,level,auth_id,ip) VALUES( ?, ?, ?, ?, ?)',$MY_NAME,$msg,$level,$auth_id,$config_ref{self_ip});
}
}

#---------------------------------------------------------------------------------------------------------------

sub db_log_debug {
my $db = shift;
my $msg = shift;
my $id = shift;
if ($debug) { log_debug($msg); }
}

#---------------------------------------------------------------------------------------------------------------

sub db_log_error {
my $db = shift;
my $msg = shift;
if ($log_level >= $L_ERROR) {
sendEmail("ERROR! ".get_first_line($msg),$msg,1);
write_db_log($db,$msg,$L_ERROR);
}
}

#---------------------------------------------------------------------------------------------------------------

sub db_log_info {
my $db = shift;
my $msg = shift;
my $id = shift;
if ($log_level >= $L_INFO) { write_db_log($db,$msg,$L_INFO,$id); }
}

#---------------------------------------------------------------------------------------------------------------

sub db_log_verbose {
my $db = shift;
my $msg = shift;
my $id = shift;
if ($log_level >= $L_VERBOSE) { write_db_log($db,$msg,$L_VERBOSE,$id); }
}

#---------------------------------------------------------------------------------------------------------------

sub db_log_warning {
my $db = shift;
my $msg = shift;
my $id = shift;
if ($log_level >= $L_WARNING) { write_db_log($db,$msg,$L_WARNING,$id); }
}

#---------------------------------------------------------------------------------------------------------------

sub init_db {
    my $autocommit = shift;
    if (!defined $autocommit) { $autocommit = 1; }
    my $db;
    if ($config_ref{DBTYPE} eq 'mysql') {
        $db = DBI->connect(
            "dbi:mysql:database=$DBNAME;host=$DBHOST;port=3306;mysql_local_infile=1", $DBUSER, $DBPASS,
            { RaiseError => 0, AutoCommit => $autocommit, mysql_enable_utf8 => 1 }
        );
        if (!defined $db) {
            log_die "Cannot connect to MySQL server: $DBI::errstr\n";
        }
        $db->do('SET NAMES utf8mb4');
    } else {
        $db = DBI->connect(
            "dbi:Pg:dbname=$DBNAME;host=$DBHOST;port=5432", $DBUSER, $DBPASS,
            { RaiseError => 0, AutoCommit => $autocommit, pg_enable_utf8 => 1, pg_server_prepare => 0 }
        );
        if (!defined $db) {
            log_die "Cannot connect to PostgreSQL server: $DBI::errstr\n";
        }
    }
    return $db;
}

#---------------------------------------------------------------------------------------------------------------

# Обновленная функция get_option с параметризованными запросами
sub get_option {
    my $db = shift;
    my $option_id = shift;
    return if (!$option_id);
    return if (!$db);
    my $sql = q{
    SELECT
    COALESCE(c.value, co.default_value) AS value,
    co.option_type
    FROM config_options co
    LEFT JOIN config c ON c.option_id = co.id
    WHERE co.id = ?
    };
    my $record = get_record_sql($db, $sql, $option_id);
    unless ($record) {
        log_error("Option ID $option_id not found in config_options table");
        return;
    }
    return $record->{value};
}

#---------------------------------------------------------------------------------------------------------------

sub get_records_sql {
my ($db, $sql, @params) = @_;
my @result;
return @result if (!$db);
return @result if (!$sql);
unless (reconnect_db(\$db)) {
    log_error("No database connection available");
    return @result;
    }
my $result_ref = _execute_param($db, $sql, \@params, { mode => 'array' });
if (ref($result_ref) eq 'ARRAY') {
        @result = @$result_ref;
    }
return @result;
}

#---------------------------------------------------------------------------------------------------------------

sub get_record_sql {
my ($db, $sql, @params) = @_;
my @result;
return @result if (!$db);
return @result if (!$sql);
# Добавляем LIMIT только если его еще нет в запросе
if ($sql !~ /\bLIMIT\s+\d+/i && $sql !~ /\bFETCH\s+FIRST\s+\d+/i) {
        $sql .= ' LIMIT 1';
    }
# Переподключение
unless (reconnect_db(\$db)) {
    log_error("No database connection available");
    return;
    }
return _execute_param($db, $sql, \@params, { mode => 'single' });
}

#---------------------------------------------------------------------------------------------------------------

sub get_count_records {
my ($db, $table, $filter, @params) = @_;
my $result = 0;
return $result if (!$db);
return $result if (!$table);
my $sSQL='SELECT COUNT(*) as rec_cnt FROM '.$table;
if ($filter) { $sSQL=$sSQL." WHERE ".$filter; }
my $record = get_record_sql($db,$sSQL, @params);
if ($record->{rec_cnt}) { $result = $record->{rec_cnt}; }
return $result;
}

#---------------------------------------------------------------------------------------------------------------

sub get_id_record {
my ($db, $table, $filter, @params) = @_;
my $result = 0;
return $result if (!$db);
return $result if (!$table);
my $record = get_record_sql($db,"SELECT id FROM $table WHERE $filter", @params);
if ($record->{id}) { $result = $record->{id}; }
return $result;
}

#---------------------------------------------------------------------------------------------------------------

sub get_diff_rec {
my ($db, $table, $record, $filter_sql, @filter_params) = @_;
return unless $db && $table && $filter_sql;

unless (reconnect_db(\$db)) {
    log_error("No database connection available");
    return;
    }
my $old_record = get_record_sql($db,"SELECT * FROM $table WHERE $filter_sql",@filter_params);
return unless $old_record;
my $result;
foreach my $field (keys %$record) {
    if (!$record->{$field}) { $record->{$field}=''; }
    if (!$old_record->{$field}) { $old_record->{$field}=''; }
    if ($record->{$field}!~/^$old_record->{$field}$/) { $result->{$field} = "$record->{$field} [ old: " . $old_record->{$field} . "]"; }
    }
return hash_to_text($result);
}

#---------------------------------------------------------------------------------------------------------------

sub get_db_type {
my $db = shift;
return lc($db->{Driver}->{Name});
#'mysql', 'pg'
}

#---------------------------------------------------------------------------------------------------------------

# Внутренняя функция для выполнения параметризованных запросов
sub _execute_param {
    my ($db, $sql, $params, $options) = @_;
    return unless $db && $sql;

    my $mode = $options->{mode} || 'execute';

    # --- Автоматическая поддержка RETURNING для PostgreSQL ---
    my $was_modified = 0;
    my $original_sql = $sql;
    if ($mode eq 'id' && $sql =~ /^\s*INSERT\b/i) {
        if (get_db_type($db) eq 'pg') {
            unless ($sql =~ /\bRETURNING\b/i) {
                $sql .= ' RETURNING id';
                $was_modified = 1;
                $mode = 'scalar';
            }
        }
    }

    # Логируем не-SELECT
    unless ($original_sql =~ /^\s*SELECT/i) {
        log_debug($original_sql . ($params ? ' | params: [' . join(', ', map { defined $_ ? $_ : 'undef' } @$params) . ']' : ''));
    }

    # === не переподключаемся внутри транзакции ===
    my $autocommit_enabled = $db->{AutoCommit};
    unless ($autocommit_enabled) {
        # В транзакции: нельзя переподключаться!
        unless ($db->ping) {
            log_error("Database connection lost during transaction");
            return wantarray ? () : undef;
        }
    } else {
        # Вне транзакции: можно переподключиться
        unless (reconnect_db(\$db)) {
            log_error("No database connection available");
            return wantarray ? () : undef;
        }
    }

    my $sth = $db->prepare($sql) or do {
        log_error("Unable to prepare SQL [$original_sql]: " . $db->errstr);
        return wantarray ? () : undef;
    };

    my $rv = $params ? $sth->execute(@$params) : $sth->execute();

    unless ($rv) {
        log_error("Unable to execute SQL [$original_sql]" . ($params ? " with params: [" . join(', ', map { defined $_ ? $_ : 'undef' } @$params) . "]" : "") . ": " . $sth->errstr);
        $sth->finish();
        return wantarray ? () : undef;
    }

    # --- Обработка результатов ---
    if ($was_modified && $mode eq 'scalar') {
        my $row = $sth->fetchrow_arrayref();
        $sth->finish();
        my $id = $row ? $row->[0] : 0;
        return $id;
    }
    elsif ($mode eq 'single') {
        my $row = $sth->fetchrow_hashref();
        $sth->finish();
        return $row;
    }
    elsif ($mode eq 'array') {
        my @rows;
        while (my $row = $sth->fetchrow_hashref()) {
            push @rows, $row;
        }
        $sth->finish();
        return \@rows;
    }
    elsif ($mode eq 'arrayref') {
        my $rows = $sth->fetchall_arrayref({});
        $sth->finish();
        return $rows;
    }
    elsif ($mode eq 'scalar') {
        my $row = $sth->fetchrow_arrayref();
        $sth->finish();
        return $row ? $row->[0] : undef;
    }
    elsif ($mode eq 'id') {
        if ($original_sql =~ /^\s*INSERT/i) {
            my $id;
            if (get_db_type($db) eq 'mysql') {
                $id = $sth->{mysql_insertid};
            } else {
                ($id) = $db->selectrow_array("SELECT lastval()");
            }
            $sth->finish();
            return $id || 0;
        }
        $sth->finish();
        return 1;
    }
    else {
        $sth->finish();
        return 1;
    }
}

#---------------------------------------------------------------------------------------------------------------

sub do_sql {
    my ($db, $sql, @bind_values) = @_;
    return unless $db && $sql;  # Возвращаем undef при ошибке входных данных

    my $mode;
    if ($sql =~ /^\s*insert\b/i) {
        $mode = 'id';
    } elsif ($sql =~ /^\s*select\b/i) {
        $mode = 'arrayref';
    } else {
        $mode = 'execute';
    }

    my $result = _execute_param($db, $sql, \@bind_values, { mode => $mode });

    # Если _execute_param вернул undef/ложь — это ошибка
    unless (defined $result) {
        return;  # Возвращаем undef (лучше, чем 0)
    }

    if ($mode eq 'id') {
        return $result;  # число (возможно 0 — допустимо для ID)
    } elsif ($mode eq 'arrayref') {
        # _execute_param всегда возвращает ARRAYREF при успехе
        return $result;
    } else {
        # Для UPDATE/DELETE: возвращаем количество затронутых строк или 1
        return $result ? $result : 1;
    }
}

#---------------------------------------------------------------------------------------------------------------

sub insert_record {
my ($db, $table, $record) = @_;
return unless $db && $table && ref($record) eq 'HASH' && %$record;

# Переподключаемся ТОЛЬКО если не в транзакции
if ($db->{AutoCommit}) {
        unless (reconnect_db(\$db)) {
            log_error("No database connection available");
            return;
        }
    } else {
        unless ($db->ping) {
            log_error("Database connection lost during transaction");
            return;
        }
    }

my $db_info= build_db_schema($db);

my $dns_changed = 0;
my $rec_id = 0;

if ($table eq "user_auth") {
    foreach my $field (keys %$record) {
        if (exists $acl_fields{$field}) { $record->{changed}="1"; }
        if (exists $dhcp_fields{$field}) { $record->{dhcp_changed}="1"; }
        if (exists $dns_fields{$field}) { $dns_changed=1; }
        }
    }

my @insert_params;
my $fields = '';
my $values = '';

foreach my $field (keys %$record) {
    my $val =  normalize_value($record->{$field}, $db_schema{$db_info->{db_type}}{$db_info->{db_name}}{$table}{$field});
    # Экранируем имя поля в зависимости от СУБД
    my $quoted_field = get_db_type($db) eq 'mysql'
        ? '`' . $field . '`'
        : '"' . $field . '"';
    $fields .= "$quoted_field, ";
    $values .= "?, ";
    push @insert_params, $val;
}

$fields =~ s/,\s*$//;
$values =~ s/,\s*$//;

my $sSQL = "INSERT INTO $table($fields) VALUES($values)";
my $result = do_sql($db,$sSQL,@insert_params);

if ($result) {
    $rec_id = $result;

    my $changed_msg = prepare_audit_message($db, $table, undef, $record, $rec_id, 'insert');
    if ($table !~ /session/i) {
    if (defined $changed_msg && $changed_msg ne '') {
        if ($table !~ /user/i) {
            db_log_info($db, $changed_msg);
            } else {
            if ($table eq 'user_auth' && defined $record->{ip} && $record->{ip} ne '') {
                if (is_hotspot($db, $record->{ip})) {
                    db_log_info($db, $changed_msg, $rec_id);
                    } else {
                    db_log_warning($db, $changed_msg, $rec_id);
                    my $send_alert_create = isNotifyCreate(get_notify_subnet($db, $record->{ip}));
                    sendEmail("WARN! " . get_first_line($changed_msg), $changed_msg, 1) if $send_alert_create;
                    }
                } else {
                db_log_warning($db, $changed_msg);
                }
            }
        }

    if ($table eq 'user_auth_alias' and $dns_changed) {
        if ($record->{'alias'} and $record->{'alias'}!~/\.$/) {
            my $add_dns;
            $add_dns->{'name_type'}='CNAME';
            $add_dns->{'name'}=$record->{'alias'};
            $add_dns->{'value'}=get_dns_name($db,$record->{'auth_id'});
            $add_dns->{'operation_type'}='add';
            $add_dns->{'auth_id'}=$record->{'auth_id'};
            insert_record($db,'dns_queue',$add_dns);
            }
        }
    if ($table eq 'user_auth' and $dns_changed) {
        if ($record->{'dns_name'} and $record->{'ip'} and !$record->{'dns_ptr_only'} and $record->{'dns_name'}!~/\.$/) {
            my $add_dns;
            $add_dns->{'name_type'}='A';
            $add_dns->{'name'}=$record->{'dns_name'};
            $add_dns->{'value'}=$record->{'ip'};
            $add_dns->{'operation_type'}='add';
            $add_dns->{'auth_id'}=$result;
            insert_record($db,'dns_queue',$add_dns);
            }
        if ($record->{'dns_name'} and $record->{'ip'} and $record->{'dns_ptr_only'} and $record->{'dns_name'}!~/\.$/) {
            my $add_dns;
            $add_dns->{'name_type'}='PTR';
            $add_dns->{'name'}=$record->{'dns_name'};
            $add_dns->{'value'}=$record->{'ip'};
            $add_dns->{'operation_type'}='add';
            $add_dns->{'auth_id'}=$result;
            insert_record($db,'dns_queue',$add_dns);
            }
        }
    }
}
return $result;
}

#---------------------------------------------------------------------------------------------------------------

sub update_record {
my ($db, $table, $record, $filter_sql, @filter_params) = @_;
return unless $db && $table && $filter_sql;

# Переподключаемся ТОЛЬКО если не в транзакции
if ($db->{AutoCommit}) {
        unless (reconnect_db(\$db)) {
            log_error("No database connection available");
            return;
        }
    } else {
        unless ($db->ping) {
            log_error("Database connection lost during transaction");
            return;
        }
    }

my $db_info = build_db_schema($db);

my $select_sql = "SELECT * FROM $table WHERE $filter_sql";
my $old_record = get_record_sql($db, $select_sql, @filter_params);
return unless $old_record;

my @update_params;
my $set_clause = '';
my $dns_changed = 0;
my $rec_id = $old_record->{id} || 0;

if ($table eq "user_auth") {
    $rec_id = $old_record->{'id'} if ($old_record->{'id'});
    my $cur_ou_id = $old_record->{'ou_id'} if ($old_record->{'ou_id'});
    if (exists $record->{ou_id}) { $cur_ou_id = $record->{'ou_id'}; }
    #disable update field 'created_by'
    #if ($old_record->{'created_by'} and exists ($record->{'created_by'})) { delete $record->{'created_by'}; }
    foreach my $field (keys %$record) {
        if (exists $acl_fields{$field}) { $record->{changed}="1"; }
        if (exists $dhcp_fields{$field} and !is_system_ou($db,$cur_ou_id)) { $record->{dhcp_changed}="1"; }
        if (exists $dns_fields{$field}) { $dns_changed=1; }
        }
    }

for my $field (keys %$record) {
        my $old_val = defined $old_record->{$field} ? $old_record->{$field} : '';
        my $new_val =  normalize_value( $record->{$field}, $db_schema{$db_info->{db_type}}{$db_info->{db_name}}{$table}{$field});
        $new_val = defined $new_val ? $new_val : '';
        if ($new_val ne $old_val) {
            $set_clause .= " $field = ?, ";
            push @update_params, $new_val;
        }
    }

return 1 unless $set_clause;

# Добавляем служебные поля
if ($table eq 'user_auth') {
        $set_clause .= "changed_time = ?, ";
        push @update_params, GetNowTime();
    }

$set_clause =~ s/,\s*$//;

if ($table eq 'user_auth') {
        if ($dns_changed) {
            my $del_dns;
            if ($old_record->{'dns_name'} and $old_record->{'ip'} and !$old_record->{'dns_ptr_only'} and $old_record->{'dns_name'}!~/\.$/) {
                    $del_dns->{'name_type'}='A';
                    $del_dns->{'name'}=$old_record->{'dns_name'};
                    $del_dns->{'value'}=$old_record->{'ip'};
                    $del_dns->{'operation_type'}='del';
                    if ($rec_id) { $del_dns->{'auth_id'}=$rec_id; }
                    insert_record($db,'dns_queue',$del_dns);
                    }
            if ($old_record->{'dns_name'} and $old_record->{'ip'} and $old_record->{'dns_ptr_only'} and $old_record->{'dns_name'}!~/\.$/) {
                    $del_dns->{'name_type'}='PTR';
                    $del_dns->{'name'}=$old_record->{'dns_name'};
                    $del_dns->{'value'}=$old_record->{'ip'};
                    $del_dns->{'operation_type'}='del';
                    if ($rec_id) { $del_dns->{'auth_id'}=$rec_id; }
                    insert_record($db,'dns_queue',$del_dns);
                    }
            my $new_dns;
            my $dns_rec_ip = $old_record->{ip};
            my $dns_rec_name = $old_record->{dns_name};
            if ($record->{'dns_name'}) { $dns_rec_name = $record->{'dns_name'}; }
            if ($record->{'ip'}) { $dns_rec_ip = $record->{'ip'}; }
            if ($dns_rec_name and $dns_rec_ip and !$record->{'dns_ptr_only'} and $record->{'dns_name'}!~/\.$/) {
                $new_dns->{'name_type'}='A';
                $new_dns->{'name'}=$dns_rec_name;
                $new_dns->{'value'}=$dns_rec_ip;
                $new_dns->{'operation_type'}='add';
                if ($rec_id) { $new_dns->{'auth_id'}=$rec_id; }
                insert_record($db,'dns_queue',$new_dns);
                }
            if ($dns_rec_name and $dns_rec_ip and $record->{'dns_ptr_only'} and $record->{'dns_name'}!~/\.$/) {
                $new_dns->{'name_type'}='PTR';
                $new_dns->{'name'}=$dns_rec_name;
                $new_dns->{'value'}=$dns_rec_ip;
                $new_dns->{'operation_type'}='add';
                if ($rec_id) { $new_dns->{'auth_id'}=$rec_id; }
                insert_record($db,'dns_queue',$new_dns);
                }
            }
        }

if ($table eq 'user_auth_alias') {
        if ($dns_changed) {
            my $del_dns;
            if ($old_record->{'alias'} and $old_record->{'alias'}!~/\.$/) {
            $del_dns->{'name_type'}='CNAME';
            $del_dns->{'name'}=$old_record->{'alias'};
            $del_dns->{'operation_type'}='del';
            $del_dns->{'value'}=get_dns_name($db,$old_record->{auth_id});
            $del_dns->{'auth_id'}=$old_record->{auth_id};
            insert_record($db,'dns_queue',$del_dns);
            }
            my $new_dns;
            my $dns_rec_name = $old_record->{alias};
            if ($record->{'alias'}) { $dns_rec_name = $record->{'alias'}; }
            if ($dns_rec_name and $record->{'alias'}!~/\.$/) {
                $new_dns->{'name_type'}='CNAME';
                $new_dns->{'name'}=$dns_rec_name;
                $new_dns->{'operation_type'}='add';
                $new_dns->{'value'}=get_dns_name($db,$old_record->{auth_id});
                $new_dns->{'auth_id'}=$rec_id;
                insert_record($db,'dns_queue',$new_dns);
                }
            }
        }

my @all_params = (@update_params, @filter_params);
my $update_sql = "UPDATE $table SET $set_clause WHERE $filter_sql";
my $result = do_sql($db, $update_sql, @all_params);

if ($result) {
    my $changed_msg = prepare_audit_message($db, $table, $old_record, $record , $rec_id, 'update');
    if ($table !~ /session/i) {
        if (defined $changed_msg && $changed_msg ne '') {
            if ($table !~ /user/i) {
                db_log_info($db, $changed_msg);
                } else {
                if (is_hotspot($db, $old_record->{ip})) {
                    db_log_info($db, $changed_msg, $rec_id);
                    } else {
                    db_log_warning($db, $changed_msg, $rec_id);
                    if ($table eq 'user_auth' && defined $old_record->{ip} && $old_record->{ip} ne '') {
                        my $send_alert_update = isNotifyUpdate(get_notify_subnet($db, $old_record->{ip}));
                        sendEmail("WARN! " . get_first_line($changed_msg), $changed_msg, 1) if $send_alert_update;
                        }
                    }
                }
            }
        }
    }

return $result;
}

#---------------------------------------------------------------------------------------------------------------

sub delete_record {
my ($db, $table, $filter_sql, @filter_params) = @_;
return unless $db && $table && $filter_sql;

# Переподключаемся ТОЛЬКО если не в транзакции
if ($db->{AutoCommit}) {
        unless (reconnect_db(\$db)) {
            log_error("No database connection available");
            return;
        }
    } else {
        unless ($db->ping) {
            log_error("Database connection lost during transaction");
            return;
        }
    }

my $select_sql = "SELECT * FROM $table WHERE $filter_sql";
my $old_record = get_record_sql($db, $select_sql, @filter_params);
return unless $old_record;

my $rec_id = $old_record->{'id'};

#never delete user ip record!
if ($table eq 'user_auth') {
    my $sSQL = "UPDATE user_auth SET changed = 1, deleted = 1, changed_time = ? WHERE $filter_sql";
    my $ret = do_sql($db, $sSQL, GetNowTime(), @filter_params);
    if ($old_record->{'dns_name'} and $old_record->{'ip'} and !$old_record->{'dns_ptr_only'} and $old_record->{'dns_name'}!~/\.$/) {
	my $del_dns;
	$del_dns->{'name_type'}='A';
	$del_dns->{'name'}=$old_record->{'dns_name'};
	$del_dns->{'value'}=$old_record->{'ip'};
	$del_dns->{'operation_type'}='del';
	$del_dns->{'auth_id'}=$old_record->{'id'};
	insert_record($db,'dns_queue',$del_dns);
	}
    if ($old_record->{'dns_name'} and $old_record->{'ip'} and $old_record->{'dns_ptr_only'} and $old_record->{'dns_name'}!~/\.$/) {
	my $del_dns;
	$del_dns->{'name_type'}='PTR';
	$del_dns->{'name'}=$old_record->{'dns_name'};
	$del_dns->{'value'}=$old_record->{'ip'};
	$del_dns->{'operation_type'}='del';
	$del_dns->{'auth_id'}=$old_record->{'id'};
	insert_record($db,'dns_queue',$del_dns);
	}

    my $changed_msg = prepare_audit_message($db, $table, $old_record, undef , $rec_id, 'delete');
    if ($ret) {
        if (defined $changed_msg && $changed_msg ne '') {
            if (defined $old_record->{ip} && $old_record->{ip} ne '') {
                if (is_hotspot($db, $old_record->{ip})) {
                    db_log_info($db, $changed_msg, $rec_id);
                    } else {
                    db_log_warning($db, $changed_msg, $rec_id);
                    my $send_alert_delete = isNotifyDelete(get_notify_subnet($db, $old_record->{ip}));
                    sendEmail("WARN! " . get_first_line($changed_msg), $changed_msg, 1) if $send_alert_delete;
                    }
                }
            }
        }
    return $ret;
    }

if ($table eq 'user_list' and $old_record->{'permanent'}) { return; }

if ($table eq 'user_auth_alias') {
    if ($old_record->{'alias'} and $old_record->{'auth_id'} and $old_record->{'alias'}!~/\.$/) {
	my $del_dns;
	$del_dns->{'name_type'}='CNAME';
	$del_dns->{'name'}=$old_record->{'alias'};
	$del_dns->{'value'}=get_dns_name($db,$old_record->{'auth_id'});
	$del_dns->{'operation_type'}='del';
	$del_dns->{'auth_id'}=$old_record->{'auth_id'};
	insert_record($db,'dns_queue',$del_dns);
	}
    }

my $sSQL = "DELETE FROM ".$table." WHERE ".$filter_sql;
my $result = do_sql($db,$sSQL,@filter_params);

my $changed_msg = prepare_audit_message($db, $table, $old_record, undef , $rec_id, 'delete');
if ($result && $table !~ /session/i) {
    if (defined $changed_msg && $changed_msg ne '') {
        if ($table !~ /user/i) {
            db_log_info($db, $changed_msg);
            } else {
            db_log_warning($db, $changed_msg);
            }
        }
    }

return $result;
}

#---------------------------------------------------------------------------------------------------------------

sub is_system_ou {
    my ($db, $ou_id) = @_;
    return 0 if !defined $ou_id || $ou_id !~ /^\d+$/ || $ou_id <= 0;
    my $sql = "SELECT 1 FROM ou WHERE id = ? AND (default_users = 1 OR default_hotspot = 1)";
    my $record = get_record_sql($db, $sql, $ou_id);
    return $record ? 1 : 0;
}

#---------------------------------------------------------------------------------------------------------------

sub init_option {
my $db=shift;

$last_refresh_config = time();

$config_ref{version}='';
my $version_record = get_record_sql($db,"SELECT version FROM version WHERE version is NOT NULL");
if ($version_record) { $config_ref{version}=$version_record->{version}; }

$config_ref{self_ip} = '127.0.0.1';
if ($DBHOST ne '127.0.0.1') {
    my $ip_route = qx(ip r get $DBHOST 2>&1 | head -1);
    if ($? == 0) {
	if ($ip_route =~ /src\s+(\d+\.\d+\.\d+\.\d+)/) { $config_ref{self_ip} = $1; }
        }
    }

$config_ref{dbh}=$db;
$config_ref{save_detail}=get_option($db,23);
$config_ref{add_unknown_user}=get_option($db,22);
$config_ref{dhcp_server}=get_option($db,5);
$config_ref{snmp_default_version}=get_option($db,9);
$config_ref{snmp_default_community}=get_option($db,11);
$config_ref{KB}=get_option($db,1);
if ($config_ref{KB} ==0) { $config_ref{KB}=1000; }
if ($config_ref{KB} ==1) { $config_ref{KB}=1024; }
$config_ref{admin_email}=get_option($db,21);
$config_ref{sender_email}=get_option($db,52);
$config_ref{send_email}=get_option($db,51);
$config_ref{history}=get_option($db,26);
$config_ref{history_dhcp}=get_option($db,27);
$config_ref{router_login}=get_option($db,28);
$config_ref{router_password}=get_option($db,29);
$config_ref{router_port}=get_option($db,30);
$config_ref{org_name}=get_option($db,32);
$config_ref{domain_name}=get_option($db,33);
$config_ref{connections_history}=get_option($db,35);
$config_ref{debug}=get_option($db,34);
$config_ref{log_level} = get_option($db,53);
if ($config_ref{debug}) { $config_ref{log_level} = 255; }
$config_ref{urgent_sync}=get_option($db,50);
$config_ref{ignore_hotspot_dhcp_log} = get_option($db,44);
$config_ref{ignore_update_dhcp_event} = get_option($db,45);
$config_ref{update_hostname_from_dhcp} = get_option($db,46);
$config_ref{history_log_day}=get_option($db,47);
$config_ref{history_syslog_day} = get_option($db,48);
$config_ref{history_trafstat_day} = get_option($db,49);

$config_ref{enable_quotes} = get_option($db,54);
$config_ref{netflow_step} = get_option($db,55);
$config_ref{traffic_ipstat_history} = get_option($db,56);

$config_ref{nagios_url} = get_option($db,57);
$config_ref{cacti_url} = get_option($db,58);
$config_ref{torrus_url} = get_option($db,59);
$config_ref{wiki_url} = get_option($db,60);
$config_ref{stat_url} = get_option($db,62);

$config_ref{wiki_path} = get_option($db,61);

$config_ref{auto_mac_rule} = get_option($db,64);

#network configuration mode
$config_ref{config_mode}=get_option($db,68);

#auto clean old user record
$config_ref{clean_empty_user}=get_option($db,69);

#dns_server_type
$config_ref{dns_server}=get_option($db,3);
$config_ref{dns_server_type}=get_option($db,70);
$config_ref{enable_dns_updates}=get_option($db,71);

#$save_detail = 1; id=23
$save_detail=get_option($db,23);
#$add_unknown_user = 1; id=22
$add_unknown_user=get_option($db,22);
#$dns_server='192.168.2.12'; id=3
$dns_server=get_option($db,3);
#$dhcp_server='192.168.2.12'; id=5
$dhcp_server=get_option($db,5);
#$snmp_default_version='2'; id=9
$snmp_default_version=get_option($db,9);
#$snmp_default_community='public'; id=11
$snmp_default_community=get_option($db,11);
#$KB=1024; id=1
$KB=$config_ref{KB};
#$admin_email; id=21
$admin_email=get_option($db,21);
#sender email
$sender_email=get_option($db,52);
#send email
$send_email=get_option($db,51);
#$history=15; id=26
$history=get_option($db,26);
#$history_dhcp=7; id=27
$history_dhcp=get_option($db,27);
#$router_login="admin"; id=28
$router_login=get_option($db,28);
#$router_password="admin"; id=29
$router_password=get_option($db,29);
#$router_port=23; id=30
$router_port=get_option($db,30);
#32
$org_name=get_option($db,32);
#33
$domain_name=get_option($db,33);
#35
$connections_history=get_option($db,35);
#debug
$debug=get_option($db,34);

#log level
$log_level = get_option($db,53);
if ($debug) { $log_level = 255; }

#urgent sync access
$urgent_sync=get_option($db,50);

$ignore_hotspot_dhcp_log = get_option($db,44);

$ignore_update_dhcp_event = get_option($db,45);

$update_hostname_from_dhcp = get_option($db,46);

$history_log_day=get_option($db,47);

$history_syslog_day = get_option($db,48);

$history_trafstat_day = get_option($db,49);

my $ou = get_record_sql($db,"SELECT id FROM ou WHERE default_users = 1");
if (!$ou) { $default_user_ou_id = 0; } else { $default_user_ou_id = $ou->{'id'}; }

$ou = get_record_sql($db,"SELECT id FROM ou WHERE default_hotspot = 1 ");
if (!$ou) { $default_hotspot_ou_id = $default_user_ou_id; } else { $default_hotspot_ou_id = $ou->{'id'}; }

@subnets=get_records_sql($db,'SELECT * FROM subnets ORDER BY ip_int_start');

if (defined $office_networks) { undef $office_networks; }
if (defined $free_networks) { undef $free_networks; }
if (defined $vpn_networks) { undef $vpn_networks; }
if (defined $hotspot_networks) { undef $hotspot_networks; }
if (defined $all_networks) { undef $all_networks; }

$office_networks = new Net::Patricia;
$free_networks = new Net::Patricia;
$vpn_networks = new Net::Patricia;
$hotspot_networks = new Net::Patricia;
$all_networks = new Net::Patricia;

@office_network_list=();
@free_network_list=();
@free_network_list=();
@vpn_network_list=();
@hotspot_network_list=();
@all_network_list=();

foreach my $net (@subnets) {
    next if (!$net->{subnet});
    $subnets_ref{$net->{subnet}}=$net;
    if ($net->{office}) {
	push(@office_network_list,$net->{subnet});
	$office_networks->add_string($net->{subnet},$net);
	}
    if ($net->{free}) {
	push(@free_network_list,$net->{subnet});
	$free_networks->add_string($net->{subnet},$net);
	}
    if ($net->{vpn}) {
	push(@vpn_network_list,$net->{subnet});
	$vpn_networks->add_string($net->{subnet},$net);
	}
    if ($net->{hotspot}) {
	push(@hotspot_network_list,$net->{subnet});
	push(@all_network_list,$net->{subnet});
	$hotspot_networks->add_string($net->{subnet},$net);
	}
    push(@all_network_list,$net->{subnet});
    $all_networks->add_string($net->{subnet},$net);
    }
}

#---------------------------------------------------------------------------------------------------------------

sub Set_Variable {
    my ($db, $name, $value, $timeshift) = @_;
    $name //= $MY_NAME;
    $value //= $$;
    $timeshift //= 60;

    Del_Variable($db, $name);

    my $clean_time = time() + $timeshift;
    my ($sec, $min, $hour, $day, $month, $year) = localtime($clean_time);
    $month++;
    $year += 1900;
    my $clear_time_str = sprintf "%04d-%02d-%02d %02d:%02d:%02d", $year, $month, $day, $hour, $min, $sec;

    my $sql = "INSERT INTO variables (name, value, clear_time) VALUES (?, ?, ?)";
    do_sql($db, $sql, $name, $value, $clear_time_str);
}

#---------------------------------------------------------------------------------------------------------------

sub Get_Variable {
    my $db = shift;
    my $name = shift || $MY_NAME;
    my $variable = get_record_sql($db, 'SELECT value FROM variables WHERE name = ?', $name);
    if ($variable and $variable->{'value'}) { return $variable->{'value'}; }
    return;
}

#---------------------------------------------------------------------------------------------------------------

sub Del_Variable {
    my ($db, $name) = @_;
    $name //= $MY_NAME;
    do_sql($db, "DELETE FROM variables WHERE name = ?", $name);
}

#---------------------------------------------------------------------------------------------------------------

sub clean_variables {
    my ($db) = @_;

    # 1. Clean temporary variables
    my $now = time();
    my ($sec, $min, $hour, $day, $month, $year) = localtime($now);
    $month++;
    $year += 1900;
    my $now_str = sprintf "%04d-%02d-%02d %02d:%02d:%02d", $year, $month, $day, $hour, $min, $sec;

    do_sql($db, "DELETE FROM variables WHERE clear_time <= ?", $now_str);

    # 2. Clean old AD computer cache
    my $yesterday = DateTime->now(time_zone => 'local')->subtract(days => 1);
    my $clean_str = $yesterday->strftime("%Y-%m-%d 00:00:00");

    do_sql($db, "DELETE FROM ad_comp_cache WHERE last_found <= ?", $clean_str);
}

#---------------------------------------------------------------------------------------------------------------

#skip init for upgrade
if ($MY_NAME!~/upgrade.pl/) {
    $dbh=init_db();
    init_option($dbh);
    clean_variables($dbh);
    Set_Variable($dbh);
    warn "DBI driver name: ", $dbh->{Driver}->{Name}, "\n" if ($debug);
    warn "Full dbh class: ", ref($dbh), "\n" if ($debug);
    }

1;
}
