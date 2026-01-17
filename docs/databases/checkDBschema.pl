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

# === Получаем имя чистой БД ===
my $clear_db_name = $DBNAME . "_test";

# === Подключаемся к рабочей БД ===
my $work_db = init_db();

# === Подключаемся к чистой БД ===
my $clear_db;
if ($config_ref{DBTYPE} eq 'mysql') {
    my $dsn = "dbi:mysql:database=$clear_db_name;host=$DBHOST;port=3306;mysql_local_infile=1";
    $clear_db = DBI->connect($dsn, $DBUSER, $DBPASS, {
        RaiseError => 0,
        AutoCommit => 1,
        mysql_enable_utf8 => 1
    });
    if (!defined $clear_db) {
        die "Cannot connect to MySQL database '$clear_db_name': $DBI::errstr\n";
    }
    $clear_db->do('SET NAMES utf8mb4');
} else {
    my $dsn = "dbi:Pg:dbname=$clear_db_name;host=$DBHOST;port=5432;";
    $clear_db = DBI->connect($dsn, $DBUSER, $DBPASS, {
        RaiseError => 0,
        AutoCommit => 1,
        pg_enable_utf8 => 1,
        pg_server_prepare => 0
    });
    if (!defined $clear_db) {
        die "Cannot connect to PostgreSQL database '$clear_db_name': $DBI::errstr\n";
    }
}

# === Функция нормализации значения по умолчанию ===
sub normalize_default {
    my ($default, $db_type) = @_;
    return undef unless defined $default;

    if ($db_type eq 'mysql') {
        # Убираем кавычки, если строка
        $default =~ s/^'(.*)'$/$1/;
        # NULL → undef
        return undef if lc($default) eq 'null';
    } else {
        # PostgreSQL: уже в нормальном виде
        return undef if lc($default) eq 'null';
    }
    return $default;
}

# === Сбор схемы для БД ===
sub get_schema {
    my ($db, $db_type, $db_name) = @_;
    my %schema;

    my @tables;
    if ($db_type eq 'mysql') {
        my @rows = get_records_sql($db, 'SHOW TABLES');
        my $idx = 'Tables_in_' . $db_name;
        @tables = map { $_->{$idx} } grep { $_ && exists $_->{$idx} } @rows;
    } else {
        my @rows = get_records_sql($db, "SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
        @tables = map { $_->{tablename} } @rows;
    }

    for my $table (@tables) {
        my %cols = get_table_columns($db, $table);
        # get_table_columns уже возвращает lowercase имена
        for my $col (keys %cols) {
            my $info = $cols{$col};
            $schema{$table}{$col} = {
                name     => $info->{name} // '',
                type     => $info->{type}     // '',
                nullable => $info->{nullable} // 1,
                default  => normalize_default($info->{default}, $db_type),
            };
        }
    }
    return %schema;
}

# === Сбор схем ===
print "Fetching schema from working database '$DBNAME'...\n";
my %work_schema = get_schema($work_db, $config_ref{DBTYPE}, $DBNAME);

print "Fetching schema from clean database '$clear_db_name'...\n";
my %clear_schema = get_schema($clear_db, $config_ref{DBTYPE}, $clear_db_name);

print "\n=== Comparing DB schemas ===\n\n";

my $has_critical_error = 0;

# === 1. Проверка: всё ли из чистой БД есть в рабочей? ===
for my $table (keys %clear_schema) {
    if (!exists $work_schema{$table}) {
        print "❗ ERROR: Table '$table' exists in clean DB but not in working DB!\n";
        $has_critical_error = 1;
        next;
    }

    for my $col (keys %{ $clear_schema{$table} }) {
        if (!exists $work_schema{$table}{$col}) {
            print "❗ ERROR: Column '$col' in table '$table' exists in clean DB but not in working DB!\n";
            $has_critical_error = 1;
            next;
        }
        my $clean_name = $clear_schema{$table}{$col}{name} // '';
        my $work_name  = $work_schema{$table}{$col}{name} // '';
        if ($clean_name ne $work_name) {
            print "❗ ERROR: Column '$col' in table '$table' has different name case:\n";
            print "      Clean: '$clean_name', Working: '$work_name'\n";
            $has_critical_error = 1;
        }

        # === Сравнение типов ===
        my $clean_type = $clear_schema{$table}{$col}{type} // '';
        my $work_type  = $work_schema{$table}{$col}{type} // '';

        # Нормализуем типы для сравнения (MySQL vs PG)
#        if ($config_ref{DBTYPE} eq 'mysql') {
            # Пример: TINYINT(1) → boolean-like, но у нас SMALLINT
            # Для простоты сравниваем как строки
#        }

        if ($clean_type ne $work_type) {
            print "❗ ERROR: Column '$col' in table '$table' has different type:\n";
            print "      Clean: '$clean_type', Working: '$work_type'\n";
            $has_critical_error = 1;
        }

        # === Сравнение NULL ===
        my $clean_null = $clear_schema{$table}{$col}{nullable} // 1;
        my $work_null  = $work_schema{$table}{$col}{nullable} // 1;

        if ($clean_null != $work_null) {
            my $clean_str = $clean_null ? "NULL" : "NOT NULL";
            my $work_str  = $work_null  ? "NULL" : "NOT NULL";
            print "❗ ERROR: Column '$col' in table '$table' has different NULL setting:\n";
            print "      Clean: $clean_str, Working: $work_str\n";
            $has_critical_error = 1;
        }

        # === Сравнение DEFAULT ===
        my $clean_def = $clear_schema{$table}{$col}{default};
        my $work_def  = $work_schema{$table}{$col}{default};

        if (!defined $clean_def && !defined $work_def) {
            # ok
        } elsif (!defined $clean_def || !defined $work_def) {
            print "❗ ERROR: Column '$col' in table '$table' has different DEFAULT (one is NULL):\n";
            print "      Clean: ", defined $clean_def ? "'$clean_def'" : "NULL", "\n";
            print "      Working: ", defined $work_def ? "'$work_def'" : "NULL", "\n";
            $has_critical_error = 1;
        } elsif ($clean_def ne $work_def) {
            print "❗ ERROR: Column '$col' in table '$table' has different DEFAULT:\n";
            print "      Clean: '$clean_def', Working: '$work_def'\n";
            $has_critical_error = 1;
        }
    }
}

# === 2. Проверка: есть ли лишнее в рабочей БД? ===
for my $table (keys %work_schema) {
    if (!exists $clear_schema{$table}) {
        print "⚠  WARNING: Table '$table' exists in working DB but not in clean DB — will be skipped.\n";
        next;
    }

    for my $col (keys %{ $work_schema{$table} }) {
        if (!exists $clear_schema{$table}{$col}) {
            print "⚠  WARNING: Column '$col' in table '$table' exists in working DB but not in clean DB — will be ignored.\n";
        }
    }
}

if ($has_critical_error) {
    print "\nSchema validation failed: structural differences found.\n";
    exit 103;
}

print "✅ Schema validation passed.\n\n";
exit 0;
