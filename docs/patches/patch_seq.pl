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

# debug disable force
$debug = 0;

# === Явное указание портов ===
my $PG_PORT    = 5432;

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

exit 0;
