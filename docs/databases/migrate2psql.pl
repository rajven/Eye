#!/usr/bin/perl

#
# Copyright (C) Roman Dmitriev, rnd@rajven.ru
#

use utf8;
use open ":encoding(utf8)";
use open ':std', ':encoding(UTF-8)';
use Encode;
no warnings 'utf8';
use English;
use FindBin '$Bin';
use lib "/opt/Eye/scripts";
use Getopt::Long qw(GetOptions);
use Data::Dumper;
use eyelib::config;
use eyelib::main;
use eyelib::database;
use eyelib::common;
use eyelib::net_utils;
use strict;
use warnings;

my $chunk_count = 1000;

my %pg_schema = (
    'acl' => {
        'id' => 'SERIAL',
        'name' => 'VARCHAR(30)',
        'description_english' => 'VARCHAR(250)',
        'description_russian' => 'VARCHAR(250)',
    },
    'ad_comp_cache' => {
        'id' => 'SERIAL',
        'name' => 'VARCHAR(63)',
        'last_found' => 'TIMESTAMP',
    },
    'auth_rules' => {
        'id' => 'SERIAL',
        'user_id' => 'INTEGER',
        'ou_id' => 'INTEGER',
        'rule_type' => 'SMALLINT',
        'rule' => 'VARCHAR(40)',
        'description' => 'VARCHAR(250)',
    },
    'building' => {
        'id' => 'SERIAL',
        'name' => 'VARCHAR(50)',
        'description' => 'VARCHAR(250)',
    },
    'config' => {
        'id' => 'SERIAL',
        'option_id' => 'INTEGER',
        'value' => 'VARCHAR(250)',
    },
    'config_options' => {
        'id' => 'SERIAL',
        'option_name' => 'VARCHAR(50)',
        'description_russian' => 'TEXT',
        'description_english' => 'TEXT',
        'draft' => 'SMALLINT',
        'uniq' => 'SMALLINT',
        'option_type' => 'VARCHAR(100)',
        'default_value' => 'VARCHAR(250)',
        'min_value' => 'INTEGER',
        'max_value' => 'INTEGER',
    },
    'connections' => {
        'id' => 'BIGSERIAL',
        'device_id' => 'BIGINT',
        'port_id' => 'BIGINT',
        'auth_id' => 'BIGINT',
        'last_found' => 'TIMESTAMP',
    },
    'customers' => {
        'id' => 'SERIAL',
        'login' => 'VARCHAR(20)',
        'description' => 'VARCHAR(100)',
        'password' => 'VARCHAR(255)',
        'api_key' => 'VARCHAR(255)',
        'rights' => 'SMALLINT',
    },
    'devices' => {
        'id' => 'SERIAL',
        'device_type' => 'INTEGER',
        'device_model_id' => 'INTEGER',
        'firmware' => 'VARCHAR(100)',
        'vendor_id' => 'INTEGER',
        'device_name' => 'VARCHAR(50)',
        'building_id' => 'INTEGER',
        'ip' => 'INET',
        'ip_int' => 'BIGINT',
        'login' => 'VARCHAR(50)',
        'password' => 'VARCHAR(255)',
        'protocol' => 'SMALLINT',
        'control_port' => 'INTEGER',
        'port_count' => 'INTEGER',
        'sn' => 'VARCHAR(80)',
        'description' => 'VARCHAR(255)',
        'snmp_version' => 'SMALLINT',
        'snmp3_auth_proto' => 'VARCHAR(10)',
        'snmp3_priv_proto' => 'VARCHAR(10)',
        'snmp3_user_rw' => 'VARCHAR(20)',
        'snmp3_user_rw_password' => 'VARCHAR(20)',
        'snmp3_user_ro' => 'VARCHAR(20)',
        'snmp3_user_ro_password' => 'VARCHAR(20)',
        'community' => 'VARCHAR(50)',
        'rw_community' => 'VARCHAR(50)',
        'fdb_snmp_index' => 'SMALLINT',
        'discovery' => 'SMALLINT',
        'netflow_save' => 'SMALLINT',
        'user_acl' => 'SMALLINT',
        'dhcp' => 'SMALLINT',
        'nagios' => 'SMALLINT',
        'active' => 'SMALLINT',
        'nagios_status' => 'VARCHAR(10)',
        'queue_enabled' => 'SMALLINT',
        'connected_user_only' => 'SMALLINT',
        'user_id' => 'INTEGER',
        'deleted' => 'SMALLINT',
        'discovery_locked' => 'SMALLINT',
        'locked_timestamp' => 'TIMESTAMP',
    },
    'device_filter_instances' => {
        'id' => 'SERIAL',
        'instance_id' => 'INTEGER',
        'device_id' => 'INTEGER',
    },
    'device_l3_interfaces' => {
        'id' => 'SERIAL',
        'device_id' => 'INTEGER',
        'snmpin' => 'INTEGER',
        'interface_type' => 'SMALLINT',
        'name' => 'VARCHAR(100)',
    },
    'device_models' => {
        'id' => 'SERIAL',
        'model_name' => 'VARCHAR(200)',
        'vendor_id' => 'INTEGER',
        'poe_in' => 'SMALLINT',
        'poe_out' => 'SMALLINT',
        'nagios_template' => 'VARCHAR(200)',
    },
    'device_ports' => {
        'id' => 'BIGSERIAL',
        'device_id' => 'INTEGER',
        'snmp_index' => 'INTEGER',
        'port' => 'INTEGER',
        'ifname' => 'VARCHAR(40)',
        'port_name' => 'VARCHAR(40)',
        'description' => 'VARCHAR(50)',
        'target_port_id' => 'INTEGER',
        'auth_id' => 'BIGINT',
        'last_mac_count' => 'INTEGER',
        'uplink' => 'SMALLINT',
        'nagios' => 'SMALLINT',
        'skip' => 'SMALLINT',
        'vlan' => 'INTEGER',
        'tagged_vlan' => 'VARCHAR(250)',
        'untagged_vlan' => 'VARCHAR(250)',
        'forbidden_vlan' => 'VARCHAR(250)',
    },
    'device_types' => {
        'id' => 'SERIAL',
        'name_russian' => 'VARCHAR(50)',
        'name_english' => 'VARCHAR(50)',
    },
    'dhcp_log' => {
        'id' => 'BIGSERIAL',
        'mac' => 'MACADDR',
        'ip_int' => 'BIGINT',
        'ip' => 'INET',
        'action' => 'VARCHAR(10)',
        'ts' => 'TIMESTAMP',
        'auth_id' => 'BIGINT',
        'dhcp_hostname' => 'VARCHAR(250)',
        'circuit_id' => 'VARCHAR(255)',
        'remote_id' => 'VARCHAR(255)',
        'client_id' => 'VARCHAR(250)',
    },
    'dhcp_queue' => {
        'id' => 'BIGSERIAL',
        'mac' => 'MACADDR',
        'ip' => 'INET',
        'action' => 'VARCHAR(10)',
        'ts' => 'TIMESTAMP',
        'dhcp_hostname' => 'VARCHAR(250)',
    },
    'dns_cache' => {
        'id' => 'BIGSERIAL',
        'dns' => 'VARCHAR(250)',
        'ip' => 'BIGINT',
        'ts' => 'TIMESTAMP',
    },
    'dns_queue' => {
        'id' => 'SERIAL',
        'auth_id' => 'INTEGER',
        'name_type' => 'VARCHAR(10)',
        'name' => 'VARCHAR(200)',
        'operation_type' => 'VARCHAR(10)',
        'value' => 'VARCHAR(100)',
    },
    'filter_instances' => {
        'id' => 'SERIAL',
        'name' => 'VARCHAR(50)',
        'description' => 'VARCHAR(200)',
    },
    'filter_list' => {
        'id' => 'SERIAL',
        'name' => 'VARCHAR(50)',
        'description' => 'VARCHAR(250)',
        'proto' => 'VARCHAR(10)',
        'dst' => 'TEXT',
        'dstport' => 'VARCHAR(20)',
        'srcport' => 'VARCHAR(20)',
        'filter_type' => 'SMALLINT',
    },
    'gateway_subnets' => {
        'id' => 'SERIAL',
        'device_id' => 'INTEGER',
        'subnet_id' => 'INTEGER',
    },
    'group_filters' => {
        'id' => 'SERIAL',
        'group_id' => 'INTEGER',
        'filter_id' => 'INTEGER',
        'rule_order' => 'INTEGER',
        'action' => 'SMALLINT',
    },
    'group_list' => {
        'id' => 'SERIAL',
        'instance_id' => 'INTEGER',
        'group_name' => 'VARCHAR(50)',
        'description' => 'VARCHAR(250)',
    },
    'mac_history' => {
        'id' => 'BIGSERIAL',
        'mac' => 'VARCHAR(12)',
        'ts' => 'TIMESTAMP',
        'device_id' => 'BIGINT',
        'port_id' => 'BIGINT',
        'ip' => 'INET',
        'auth_id' => 'BIGINT',
        'dhcp_hostname' => 'VARCHAR(250)',
    },
    'mac_vendors' => {
        'id' => 'SERIAL',
        'oui' => 'VARCHAR(20)',
        'companyname' => 'VARCHAR(255)',
        'companyaddress' => 'VARCHAR(255)',
    },
    'ou' => {
        'id' => 'SERIAL',
        'ou_name' => 'VARCHAR(40)',
        'description' => 'VARCHAR(250)',
        'default_users' => 'SMALLINT',
        'default_hotspot' => 'SMALLINT',
        'nagios_dir' => 'VARCHAR(255)',
        'nagios_host_use' => 'VARCHAR(50)',
        'nagios_ping' => 'SMALLINT',
        'nagios_default_service' => 'VARCHAR(100)',
        'enabled' => 'SMALLINT',
        'filter_group_id' => 'INTEGER',
        'queue_id' => 'INTEGER',
        'dynamic' => 'SMALLINT',
        'life_duration' => 'DECIMAL(10,2)',
        'parent_id' => 'INTEGER',
    },
    'queue_list' => {
        'id' => 'SERIAL',
        'queue_name' => 'VARCHAR(20)',
        'download' => 'INTEGER',
        'upload' => 'INTEGER',
    },
    'remote_syslog' => {
        'id' => 'BIGSERIAL',
        'ts' => 'TIMESTAMP',
        'device_id' => 'BIGINT',
        'ip' => 'INET',
        'message' => 'TEXT',
    },
    'sessions' => {
        'id' => 'VARCHAR(128)',
        'data' => 'TEXT',
        'last_accessed' => 'INTEGER',
    },
    'subnets' => {
        'id' => 'SERIAL',
        'subnet' => 'VARCHAR(18)',
        'vlan_tag' => 'INTEGER',
        'ip_int_start' => 'BIGINT',
        'ip_int_stop' => 'BIGINT',
        'dhcp_start' => 'BIGINT',
        'dhcp_stop' => 'BIGINT',
        'dhcp_lease_time' => 'INTEGER',
        'gateway' => 'BIGINT',
        'office' => 'SMALLINT',
        'hotspot' => 'SMALLINT',
        'vpn' => 'SMALLINT',
        'free' => 'SMALLINT',
        'dhcp' => 'SMALLINT',
        'static' => 'SMALLINT',
        'dhcp_update_hostname' => 'SMALLINT',
        'discovery' => 'SMALLINT',
        'notify' => 'SMALLINT',
        'description' => 'VARCHAR(250)',
    },
    'traffic_detail' => {
        'id' => 'BIGSERIAL',
        'auth_id' => 'BIGINT',
        'router_id' => 'INTEGER',
        'ts' => 'TIMESTAMP',
        'proto' => 'SMALLINT',
        'src_ip' => 'BIGINT',
        'dst_ip' => 'BIGINT',
        'src_port' => 'INTEGER',
        'dst_port' => 'INTEGER',
        'bytes' => 'BIGINT',
        'pkt' => 'BIGINT',
    },
    'unknown_mac' => {
        'id' => 'BIGSERIAL',
        'mac' => 'VARCHAR(12)',
        'port_id' => 'BIGINT',
        'device_id' => 'INTEGER',
        'ts' => 'TIMESTAMP',
    },
    'user_auth' => {
        'id' => 'SERIAL',
        'user_id' => 'BIGINT',
        'ou_id' => 'INTEGER',
        'ip' => 'INET',
        'ip_int' => 'BIGINT',
        'save_traf' => 'SMALLINT',
        'enabled' => 'SMALLINT',
        'dhcp' => 'SMALLINT',
        'filter_group_id' => 'SMALLINT',
        'dynamic' => 'SMALLINT',
        'end_life' => 'TIMESTAMP',
        'deleted' => 'SMALLINT',
        'description' => 'VARCHAR(250)',
        'dns_name' => 'VARCHAR(253)',
        'dns_ptr_only' => 'SMALLINT',
        'wikiname' => 'VARCHAR(250)',
        'dhcp_acl' => 'TEXT',
        'queue_id' => 'INTEGER',
        'mac' => 'VARCHAR(20)',
        'dhcp_action' => 'VARCHAR(10)',
        'dhcp_option_set' => 'VARCHAR(50)',
        'dhcp_time' => 'TIMESTAMP',
        'dhcp_hostname' => 'VARCHAR(60)',
        'last_found' => 'TIMESTAMP',
        'arp_found' => 'TIMESTAMP',
        'mac_found' => 'TIMESTAMP',
        'blocked' => 'SMALLINT',
        'day_quota' => 'INTEGER',
        'month_quota' => 'INTEGER',
        'device_model_id' => 'INTEGER',
        'firmware' => 'VARCHAR(100)',
        'ts' => 'TIMESTAMP',
        'client_id' => 'VARCHAR(250)',
        'nagios' => 'SMALLINT',
        'nagios_status' => 'VARCHAR(10)',
        'nagios_handler' => 'VARCHAR(50)',
        'link_check' => 'SMALLINT',
        'changed' => 'SMALLINT',
        'dhcp_changed' => 'SMALLINT',
        'changed_time' => 'TIMESTAMP',
        'created_by' => 'VARCHAR(10)',
    },
    'user_auth_alias' => {
        'id' => 'SERIAL',
        'auth_id' => 'INTEGER',
        'alias' => 'VARCHAR(100)',
        'description' => 'VARCHAR(100)',
        'ts' => 'TIMESTAMP',
    },
    'user_list' => {
        'id' => 'BIGSERIAL',
        'ts' => 'TIMESTAMP',
        'login' => 'VARCHAR(255)',
        'description' => 'VARCHAR(255)',
        'enabled' => 'SMALLINT',
        'blocked' => 'SMALLINT',
        'deleted' => 'SMALLINT',
        'ou_id' => 'INTEGER',
        'device_id' => 'INTEGER',
        'filter_group_id' => 'INTEGER',
        'queue_id' => 'INTEGER',
        'day_quota' => 'INTEGER',
        'month_quota' => 'INTEGER',
        'permanent' => 'SMALLINT',
    },
    'user_sessions' => {
        'id' => 'SERIAL',
        'session_id' => 'VARCHAR(128)',
        'user_id' => 'INTEGER',
        'ip_address' => 'VARCHAR(45)',
        'user_agent' => 'TEXT',
        'created_at' => 'INTEGER',
        'last_activity' => 'INTEGER',
        'is_active' => 'SMALLINT',
    },
    'user_stats' => {
        'id' => 'BIGSERIAL',
        'router_id' => 'BIGINT',
        'auth_id' => 'BIGINT',
        'ts' => 'TIMESTAMP',
        'byte_in' => 'BIGINT',
        'byte_out' => 'BIGINT',
        'pkt_in' => 'INTEGER',
        'pkt_out' => 'INTEGER',
        'step' => 'SMALLINT',
    },
    'user_stats_full' => {
        'id' => 'BIGSERIAL',
        'router_id' => 'BIGINT',
        'auth_id' => 'BIGINT',
        'ts' => 'TIMESTAMP',
        'byte_in' => 'BIGINT',
        'byte_out' => 'BIGINT',
        'pkt_in' => 'INTEGER',
        'pkt_out' => 'INTEGER',
        'step' => 'SMALLINT',
    },
    'variables' => {
        'id' => 'SERIAL',
        'name' => 'VARCHAR(30)',
        'value' => 'VARCHAR(255)',
        'clear_time' => 'TIMESTAMP',
        'created' => 'TIMESTAMP',
    },
    'vendors' => {
        'id' => 'SERIAL',
        'name' => 'VARCHAR(40)',
    },
    'version' => {
        'id' => 'INTEGER',
        'version' => 'VARCHAR(10)',
    },
    'wan_stats' => {
        'id' => 'BIGSERIAL',
        'ts' => 'TIMESTAMP',
        'router_id' => 'INTEGER',
        'interface_id' => 'INTEGER',
        'bytes_in' => 'BIGINT',
        'bytes_out' => 'BIGINT',
        'forward_in' => 'BIGINT',
        'forward_out' => 'BIGINT',
    },
    'worklog' => {
        'id' => 'BIGSERIAL',
        'ts' => 'TIMESTAMP',
        'auth_id' => 'BIGINT',
        'customer' => 'VARCHAR(50)',
        'ip' => 'INET',
        'message' => 'TEXT',
        'level' => 'SMALLINT',
    },
);

sub get_table_columns {
    my ($db, $table) = @_;
    my $sth = $db->column_info(undef, undef, $table, '%');
    my @cols;
    while (my $row = $sth->fetchrow_hashref) {
        push @cols, $row->{COLUMN_NAME};
    }
    return @cols;
}

sub batch_sql_cached {
    my ($db, $sql, $data) = @_;
    eval {
        my $sth = $db->prepare_cached($sql) or die "Unable to prepare SQL: " . $db->errstr;
        for my $params (@$data) {
            next unless @$params;
            $sth->execute(@$params) or die "Unable to execute with params [" . join(',', @$params) . "]: " . $sth->errstr;
        }
        $db->commit() if (!$db->{AutoCommit});
        1;
    } or do {
        my $err = $@ || 'Unknown error';
        eval { $db->rollback() };
        print "batch_db_sql_cached failed: $err";
	return 0;
    };
    return 1;
}

# debug disable force
$debug = 0;

# === Разбор аргументов командной строки ===
my $opt_clear = 0;
my $opt_batch = 0;
GetOptions(
    'clear' => \$opt_clear,
    'batch' => \$opt_batch,
) or die "Usage: $0 [--clear] [--batch]\n";

# === Явное указание портов ===
my $MYSQL_PORT = 3306;
my $PG_PORT    = 5432;

# === Подключение к MySQL (источник) ===
my $mysql_dsn = "dbi:mysql:database=$DBNAME;host=$DBHOST;port=$MYSQL_PORT;mysql_local_infile=1";
my $mysql_db = DBI->connect($mysql_dsn, $DBUSER, $DBPASS, {
    RaiseError => 0,
    AutoCommit => 1,
    mysql_enable_utf8 => 1
});
if (!defined $mysql_db) {
    die "Cannot connect to MySQL server: $DBI::errstr\n";
}
$mysql_db->do('SET NAMES utf8mb4');

# === Подключение к PostgreSQL (цель) ===
my $pg_dsn = "dbi:Pg:dbname=$DBNAME;host=$DBHOST;port=$PG_PORT;";
my $pg_db = DBI->connect($pg_dsn, $DBUSER, $DBPASS, {
    RaiseError => 0,
    AutoCommit => 1,
    pg_enable_utf8 => 1,
    pg_server_prepare => 0
});
if (!defined $pg_db) {
    print "Cannot connect to PostgreSQL server: $DBI::errstr\n";
    print "For install/configure PostgreSQL server please run migrate2psql.sh!\n";
    exit 100;
}

# === Получение списка таблиц ===
print "Fetching table list from MySQL...\n";
my @migration_tables = get_records_sql($mysql_db, 'SHOW TABLES');
my %tables;
my $table_index = 'Tables_in_' . $DBNAME;

foreach my $row (@migration_tables) {
    next unless $row && exists $row->{$table_index};
    my $table_name = $row->{$table_index};
    # Пропускаем traffic_detail (слишком большая)
    $tables{$table_name} = ($table_name !~ /(traffic_detail|sessions)/) ? 1 : 0;
}

# Фильтруем только те, что будем мигрировать
my @tables_to_migrate = sort grep { $tables{$_} } keys %tables;
my $total_tables = scalar @tables_to_migrate;

if ($total_tables == 0) {
    print "No tables to migrate!\n";
    exit 0;
}

# === Опционально: очистка всех таблиц перед импортом ===
if ($opt_clear) {
    print "\n⚠️  --clear mode: Truncating all target tables before import...\n";
    for my $table (@tables_to_migrate) {
        eval {
            $pg_db->do("TRUNCATE TABLE \"$table\" RESTART IDENTITY");
        };
        if ($@) {
            chomp $@;
            print "  ⚠️  Failed to truncate table '$table': $@\n";
        } else {
            print "  → Truncated: $table\n";
        }
    }
    print "\n";
}

print "\n=== Check DB schema ===\n\n";

my $mysql_schema_status = 1;
my %mysql_tables;

# --- Этап 1: Проверяем, что все таблицы и колонки MySQL есть в PG-схеме ---
for my $idx (0 .. $#tables_to_migrate) {
    my $table = $tables_to_migrate[$idx];
    my $table_num = $idx + 1;

    if ($table =~ /(traffic_detail|sessions)/) { next; }

    print "[$table_num/$total_tables] Processing table: $table\n";

    if (!exists $pg_schema{$table}) {
        print "    ❗ WARNING: Table $table not found in Postgres DB schema! Will be skip for migration.\n";
        # Не считаем критичной ошибкой
        next;
    }

    my @columns = get_table_columns($mysql_db, $table);
    foreach my $column_name (@columns) {
        my $col_lower = lc($column_name);  # Приводим к нижнему регистру
        if (!exists $pg_schema{$table}->{$col_lower}) {
            print "    ❗ WARNING: Column $column_name in table $table not in PG schema.  Will be skip for migration. \n";
            # Не считаем критичной ошибкой
        } else {
            $mysql_tables{$table}->{$col_lower} = 1;
        }
    }
}

# --- Этап 2: Проверяем, что все таблицы и колонки PG-схемы есть в MySQL ---
for my $table (keys %pg_schema) {

    if ($table =~ /(traffic_detail|sessions)/) { next; }

    if (!exists $mysql_tables{$table}) {
        print "    ❗ ERROR: Table $table from PG schema not found in source MySQL database!\n";
        $mysql_schema_status = 0;
        next;
    }

    for my $column_name (keys %{ $pg_schema{$table} }) {
        if (!exists $mysql_tables{$table}->{$column_name}) {
            print "    ❗ ERROR: Column $column_name in table $table missing in MySQL!\n";
            $mysql_schema_status = 0;
        }
    }
}

if (!$mysql_schema_status) {
    print "\nSchema validation failed. Check database and try again.\n";
    exit 103;
}

print "\n=== Starting migration of $total_tables tables ===\n\n";

# === Миграция по таблицам с прогрессом ===
for my $idx (0 .. $#tables_to_migrate) {
    my $table = $tables_to_migrate[$idx];
    my $table_num = $idx + 1;

    if (!exists $pg_schema{$table}) { next; }

    print "[$table_num/$total_tables] Processing table: $table\n";

    my $rec_count = get_count_records($mysql_db, $table);
    print "  → Expected records: $rec_count\n";

    if ($rec_count == 0) {
        print "  → Empty table. Skipping.\n\n";
        next;
    }

    # === Построчное чтение ===
    my $select_sth = $mysql_db->prepare("SELECT * FROM `$table`");
    $select_sth->execute();

    my $inserted = 0;
    my $errors   = 0;

# === Режим вставки: построчный или пакетный ===
if ($opt_batch) {
    print "  → Using BATCH mode ($chunk_count records per chunk)\n";

    # Получаем список колонок один раз
    my @columns = get_table_columns($mysql_db, $table);
    my $quoted_columns = '"' . join('", "', @columns) . '"';
    my $placeholders = join(', ', ('?') x @columns);
    my $insert_sql = "INSERT INTO \"$table\" ($quoted_columns) VALUES ($placeholders)";

    my @batch_buffer;
    my $chunk_size = $chunk_count;

    while (my $row = $select_sth->fetchrow_hashref) {
	my @values;
        for my $key (@columns) {
            if (!exists $pg_schema{$table}->{lc($key)}) { next; }
	    my $value = $row->{$key};
    	    if (lc($key) eq 'ip') {
        	$value = undef if !defined($value) || $value eq '';
    		}
            push @values, $value;
	    }
        push @batch_buffer, \@values;
        if (@batch_buffer >= $chunk_count) {
	    my $insert_status = batch_sql_cached($pg_db, $insert_sql, \@batch_buffer);
	    if ($insert_status) { $inserted += @batch_buffer; } else { $errors+=@batch_buffer; }
            @batch_buffer = ();
	    }
    }
    # Остаток
    if (@batch_buffer) {
	my $insert_status = batch_sql_cached($pg_db, $insert_sql, \@batch_buffer);
        if ($insert_status) { $inserted += @batch_buffer; } else { $errors+=@batch_buffer; }
	}
    } else {
    # === построчный режим ===
    while (my $row = $select_sth->fetchrow_hashref) {
        # === Приведение имён полей к нижнему регистру ===
        my %row_normalized;
        while (my ($key, $value) = each %$row) {
	    my $n_key = lc($key);
	    if ($n_key eq 'ip') {
		if (!defined $value || $value eq '') { $value = undef; }
		}
            $row_normalized{$n_key} = $value;
        }

        my $ret_id = insert_record($pg_db, $table, \%row_normalized);
	if ($ret_id>0) { $inserted++; } else {
            $errors++;
	    print Dumper(\%row_normalized) if ($debug);
    	    }
    }
    $select_sth->finish();
    }

    # === Итог по таблице ===
    my $status = ($errors == 0) ? "✅ SUCCESS" : "⚠️  COMPLETED WITH ERRORS";
    print "  → Result: $status\n";
    print "     Inserted: $inserted | Errors: $errors | Expected: $rec_count\n";
    
    if ($inserted + $errors != $rec_count) {
        print "    ❗ WARNING: Record count mismatch! (source: $rec_count, processed: " . ($inserted + $errors) . ")\n";
    }
    
    print "\n";
}

print "🎉 Migration completed! Processed $total_tables tables.\n";
exit 0;
