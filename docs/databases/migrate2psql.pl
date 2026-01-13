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

print "\n=== Starting migration of $total_tables tables ===\n\n";

# === Миграция по таблицам с прогрессом ===
for my $idx (0 .. $#tables_to_migrate) {
    my $table = $tables_to_migrate[$idx];
    my $table_num = $idx + 1;

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
    print "  → Using BATCH mode (500 records per chunk)\n";

    # Получаем список колонок один раз
    my @columns = get_table_columns($mysql_db, $table);
    my $quoted_columns = '"' . join('", "', @columns) . '"';
    my $placeholders = join(', ', ('?') x @columns);
    my $insert_sql = "INSERT INTO \"$table\" ($quoted_columns) VALUES ($placeholders)";

    my @batch_buffer;
    my $chunk_size = 500;

    while (my $row = $select_sth->fetchrow_hashref) {
	my @values;
        for my $key (@columns) {
	    my $value = $row->{$key};
    	    if (lc($key) eq 'ip') {
        	$value = undef if !defined($value) || $value eq '';
    		}
            push @values, $value;
	    }
        push @batch_buffer, \@values;
        if (@batch_buffer >= 500) {
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
