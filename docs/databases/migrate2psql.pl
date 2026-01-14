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

sub batch_sql_cached {
    my ($db, $sql, $data) = @_;
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

# === Сбор полной схемы из обеих БД ===
print "Fetching schema from MySQL and PostgreSQL...\n";

# === Сбор схем ===
my %schema;
for my $table (@tables_to_migrate) {
    next if $table =~ /(traffic_detail|sessions)/i;
    $schema{mysql}{$table} = { get_table_columns($mysql_db, $table) };
}

my @pg_tables = map { $_->{tablename} } get_records_sql($pg_db, "SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
for my $table (@pg_tables) {
    next if $table =~ /(traffic_detail|sessions)/i;
    $schema{pg}{$table} = { get_table_columns($pg_db, $table) };
}

# === Флаг ошибки ===
my $has_critical_error = 0;

# === 1. Проверка: всё ли из PostgreSQL есть в MySQL? ===
for my $table (keys %{ $schema{pg} }) {
    if (!exists $schema{mysql}{$table}) {
        print "❗ ERROR: Table '$table' exists in PostgreSQL but not in MySQL!\n";
        $has_critical_error = 1;
        next;
    }

    for my $col (keys %{ $schema{pg}{$table} }) {
        if (!exists $schema{mysql}{$table}{$col}) {
            print "❗ ERROR: Column '$col' in table '$table' exists in PostgreSQL but not in MySQL!\n";
            $has_critical_error = 1;
        }
    }
}

# === 2. Проверка: есть ли лишнее в MySQL? ===
for my $table (keys %{ $schema{mysql} }) {
    if (!exists $schema{pg}{$table}) {
        print "⚠️  WARNING: Table '$table' exists in MySQL but not in PostgreSQL — will be skipped.\n";
        next;
    }

    for my $col (keys %{ $schema{mysql}{$table} }) {
        if (!exists $schema{pg}{$table}{$col}) {
            print "⚠️  WARNING: Column '$col' in table '$table' exists in MySQL but not in PostgreSQL — will be ignored.\n";
        }
    }
}

if ($has_critical_error) {
    print "\nSchema validation failed: missing required tables/columns in source MySQL database.\n";
    exit 103;
}

print "✅ Schema validation passed.\n\n";

print "\n=== Starting migration of $total_tables tables ===\n\n";

# === Миграция по таблицам с прогрессом ===
for my $idx (0 .. $#tables_to_migrate) {
    my $table = $tables_to_migrate[$idx];
    my $table_num = $idx + 1;

    if (!exists $schema{pg}->{$table}) { next; }

    print "[$table_num/$total_tables] Processing table: $table\n";

    my $rec_count = get_count_records($mysql_db, $table);
    print "  → Expected records: $rec_count\n";

    if ($rec_count == 0) {
        print "  → Empty table. Skipping.\n\n";
        next;
    }


    my $inserted = 0;
    my $errors   = 0;

    # === Построчное чтение ===
    my $select_sth = $mysql_db->prepare("SELECT * FROM `$table`");
    $select_sth->execute();

# === Режим вставки: построчный или пакетный ===
if ($opt_batch) {
    print "  → Using BATCH mode ($chunk_count records per chunk)\n";

    # Берём колонки напрямую из PostgreSQL-схемы — они все есть в MySQL
    my @valid_columns = sort keys %{ $schema{pg}{$table} };

    my $quoted_columns = '"' . join('", "', @valid_columns) . '"';
    my $placeholders   = join(', ', ('?') x @valid_columns);
    my $insert_sql     = "INSERT INTO \"$table\" ($quoted_columns) VALUES ($placeholders)";

    my @batch_buffer;
    my $chunk_size = $chunk_count;

    my $processed = 0;
    my $report_every = 10_000;


    while (my $row = $select_sth->fetchrow_hashref) {
        my @values;
        for my $col (@valid_columns) {
            my $raw_value = $row->{$col};
            my $norm_value = normalize_value($raw_value, $schema{pg}{$table}{$col});
            push @values, $norm_value;
        }
        push @batch_buffer, \@values;
        $processed++;

        if (@batch_buffer >= $chunk_count) {
            my $insert_status = batch_sql_cached($pg_db, $insert_sql, \@batch_buffer);
            if ($insert_status) {
                $inserted += @batch_buffer;
            } else {
                $errors += @batch_buffer;
            }
            @batch_buffer = ();

            # Прогресс
            if ($processed % $report_every == 0) {
                my $pct = int($processed * 100 / $rec_count);
                printf "  → Processed: %d / %d (%d%%)\r", $processed, $rec_count, $pct;
                $| = 1;  # flush STDOUT
            }
        }
    }

    # Остаток
    if (@batch_buffer) {
        my $insert_status = batch_sql_cached($pg_db, $insert_sql, \@batch_buffer);
        if ($insert_status) {
            $inserted += @batch_buffer;
        } else {
            $errors += @batch_buffer;
        }
        $processed += @batch_buffer;
    }

    # Финальная строка
    printf "  → Processed: %d / %d (100%%)\n", $processed, $rec_count;

    } else {

    # === Построчный режим ===
    my $processed = 0;
    my $report_every = 10_000;

    while (my $row = $select_sth->fetchrow_hashref) {
        my %row_normalized;
        for my $col (keys %{ $schema{pg}{$table} }) {
            my $raw_value = $row->{$col};
            my $norm_value = normalize_value($raw_value, $schema{pg}{$table}{$col});
            $row_normalized{$col} = $norm_value;
        }

        my $ret_id = insert_record($pg_db, $table, \%row_normalized);
        if ($ret_id > 0) {
            $inserted++;
        } else {
            $errors++;
            print Dumper(\%row_normalized) if ($debug);
        }

        $processed++;

        # Прогресс каждые N строк
        if ($rec_count > 0 && $processed % $report_every == 0) {
            my $pct = int($processed * 100 / $rec_count);
            printf "  → Processed: %d / %d (%d%%)\r", $processed, $rec_count, $pct;
            $| = 1;  # flush
        }
    }
    $select_sth->finish();

    # Финальная строка
    if ($rec_count > 0) {
        printf "  → Processed: %d / %d (100%%)\n", $processed, $rec_count;
        } else {
        print "  → Processed: $processed records\n";
        }
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

print "\n=== Resetting all table sequences ===\n";

# Получаем список всех таблиц из целевой схемы (PostgreSQL)
my $tables_sql = "SELECT tablename FROM pg_tables WHERE schemaname = 'public'";
my $sth = $pg_db->prepare($tables_sql);
$sth->execute();

while (my ($table) = $sth->fetchrow_array) {
    # Формируем имя последовательности
    my $seq_name = "${table}_id_seq";
    # Проверяем, существует ли такая последовательность
    my ($exists) = $pg_db->selectrow_array(
        "SELECT 1 FROM pg_class WHERE relname = ? AND relkind = 'S'",
        undef, $seq_name
    );
    if ($exists) {
        # Получаем MAX(id)
        my ($max_id) = $pg_db->selectrow_array("SELECT MAX(id) FROM \"$table\"");
        $max_id //= 1;
        # Сбрасываем последовательность
        $pg_db->do("SELECT setval('$seq_name', $max_id)");
        print "  → $table: sequence reset to $max_id\n";
    }
}

print "✅ All sequences updated.\n";

print "🎉 Migration completed! Processed $total_tables tables.\n";
exit 0;
