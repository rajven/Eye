#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use utf8;
use FindBin '$Bin';
use lib "/opt/Eye/scripts";
use Data::Dumper;
use eyelib::config;
use eyelib::main;
use eyelib::mysql;
use strict;
use warnings;

my @tables = get_recrods_sql($dbh,"SHOW TABLES");

print "Migrate tables to UTF8 format\n";
for $table (@tables) {
    print "Apply table $table\n";
    do_sql($dbh,"SET foreign_key_checks = 0; ALTER TABLE `".$table."` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; SET foreign_key_checks = 1; ");
    print "Done\n";
}

print "Migrate database\n"
do_sql($dbh,"ALTER DATABASE ".$DBNAME." CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
print "Done\n";

print "Revert filed type to TEXT\n";
for $table (@tables) {
my $create_table = do_sql($dbh,"show create table $table");
print Dumper($create_table);
# | sed 's/\\n/\n/g' | egrep -i "[[:space:]]MEDIUMTEXT[[:space:]]" | awk '{ print $1 }' | while read c_field; do
 #   echo "ALTER TABLE $table MODIFY $c_field TEXT;" >>migration_utf8
}
print "Done!\n";

exit;
