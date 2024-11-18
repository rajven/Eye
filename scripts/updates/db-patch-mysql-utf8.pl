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
use eyelib::database;
use strict;
use warnings;

my @tables = get_records_sql($dbh,"SHOW TABLES");
my @db_tables=();
foreach my $table_ref (@tables) {
push(@db_tables,$table_ref->{Tables_in_stat});
}

print "Stage1: Migrate tables to UTF8 format\n";
for my $table (@db_tables) {
    print "Apply table $table\n";
    $dbh->do("SET foreign_key_checks = 0;");
    do_sql($dbh,"ALTER TABLE `".$table."` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    $dbh->do("SET foreign_key_checks = 1;");
}
print "Done\n";

print "Stage2: Migrate database\n";
do_sql($dbh,"ALTER DATABASE ".$DBNAME." CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
print "Done\n";

print "Stage3: Revert filed type to TEXT\n";
for my $table (@db_tables) {
    my $sql = "select * from $table LIMIT 1";
    my $sth = $dbh->prepare( $sql );
    $sth->execute();
    print "\tStructure of $table \n\n";
    my $num_fields = $sth->{NUM_OF_FIELDS};
    for ( my $i=0; $i< $num_fields; $i++ ) {
        my $field = $sth->{NAME}->[$i];
        my $type = $sth->{TYPE}->[$i];
        my $precision = $sth->{PRECISION}->[$i];
        print "\t\tField: $field is of type: $type precision:  $precision\n";
        if ($type == "-4" and $precision>262140) { 
            print "\t\tMigrate field $field to type TEXT\n";
            do_sql($dbh,"ALTER TABLE `".$table."` MODIFY `".$field."` TEXT"); 
            }
    }
    $sth->finish();
}

print "Done!\n";

exit;
