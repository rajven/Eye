#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use utf8;
use strict;
use warnings;
use Encode;
use open qw(:std :encoding(UTF-8));
no warnings 'utf8';

use FindBin '$Bin';
use lib "/opt/Eye/scripts";
use eyelib::config;
use eyelib::main;
use eyelib::database;
use eyelib::common;
use Data::Dumper;
use strict;
use warnings;

STDOUT->autoflush(1);

my $update_dir = '/opt/Eye/scripts/updates';

opendir(my $dh, $update_dir) or die "Eror listing for $update_dir: $!";
my @old_releases = sort grep { -d "$update_dir/$_" && !/^\.\.?$/ && /^\d/ } readdir($dh);
closedir $dh;

s/-/./g for @old_releases;

my $r_index = 0;
my %old_releases_h = map {$_ => $r_index++ } @old_releases;
my $eye_release = $old_releases[@old_releases - 1];

$dbh=init_db();

$config_ref{version}='';
my $version_record = get_record_sql($dbh,"SELECT version FROM version WHERE version is NOT NULL");
if ($version_record) { $config_ref{version}=$version_record->{version}; }

if (!$config_ref{version} and !$ARGV[0]) {
    print "Current version unknown! Skip upgrade!\n";
    exit 100;
    }

if ($ARGV[0]) {
    if (exists($old_releases_h{$ARGV[0]})) { $config_ref{version}=$ARGV[0]; } else { print "Unknown version $ARGV[0]!\n"; }
    }

if (!exists($old_releases_h{$config_ref{version}})) { print "Unknown version $config_ref{version}!\n"; exit 100; }

if ($eye_release eq $config_ref{version}) { print "Already updated!\n"; exit; }

print 'Current version: '.$config_ref{version}.' upgrade to: '.$eye_release."\n";

#1 - mysql
#0 - pgsql
my $db_type = ($config_ref{DBTYPE} eq 'mysql');

my $old_version_index = $old_releases_h{$config_ref{version}} + 1;
my $stage = 1;

for (my $i=$old_version_index; $i < scalar @old_releases; $i++) {
    print "Stage $stage. Upgrade to $old_releases[$i]\n";
    $stage++;

    my $version_dir = $old_releases[$i];
    $version_dir =~ s/\./-/g;

    # Убираем завершающий слэш из $update_dir, если есть
    $update_dir =~ s{/$}{};

    my $dir_name = "$update_dir/$version_dir";

    next if (! -d $dir_name);

    # patch before change database schema
    my @perl_patches = glob("$dir_name/before*.pl");
    if (@perl_patches) {
        foreach my $patch (@perl_patches) {
            next unless $patch && -e $patch;
        
            # Выводим полный путь к патчу
            print "  → Applying Perl patch: $patch\n";
        
            open(my $pipe, "-|", "$^X $patch") or die "Error applying upgrade script $patch: $!";
            while (my $line = <$pipe>) {
                chomp $line;
                if ($line =~ s/^:://) {
                    printf "\r%-80s", $line;
                    $| = 1;
                } else {
                    print "$line\n";
                }
            }
            close($pipe);
            print "\n";
        }
    }
    @perl_patches = ();

    #change database schema
    # === Apply SQL patches ===
    my @sql_patches;
    if ($db_type) {
        push @sql_patches, glob("$dir_name/*.sql"), glob("$dir_name/*.msql");
    } else {
        @sql_patches = glob("$dir_name/*.psql");
    }

    if (@sql_patches) {
        my @sorted_patches = sort @sql_patches;
        for my $patch (@sorted_patches) {
            next if !$patch || !-e $patch;
            next if $patch =~ /version\.sql$/;

            print "  → Applying SQL patch: $patch\n";

            my @sql_lines = read_file($patch);
            my $stmt_num = 0;

            for my $raw_line (@sql_lines) {
                # Убираем комментарии и пустые строки
                my $sql = $raw_line;
                $sql =~ s/\s+$//;  # trim
                next if $sql eq '' || $sql =~ /^(--|#)/;

                $stmt_num++;

                # Логируем команду
                print "    [$stmt_num] Executing: $sql\n";

                eval {
                    my $sth = $dbh->prepare($sql);
                    if (!$sth) {
                        die "Prepare failed: " . $dbh->errstr;
                    }

                    my $rv = $sth->execute();
                    if (!defined $rv) {
                        die "Execute failed: " . $dbh->errstr;
                    }

                    # Показываем результат (если есть)
                    if ($sql =~ /^\s*(INSERT|UPDATE|DELETE|TRUNCATE)/i) {
                        my $rows = $sth->rows;
                        print "        → Affected rows: $rows\n";
                    } elsif ($sql =~ /^\s*SELECT/i) {
                        my $rows = $sth->fetchall_arrayref({});
                        my $count = @$rows;
                        print "        → Selected $count row(s)\n";
                    } else {
                        print "        → Command executed successfully\n";
                    }

                    $sth->finish();
                    1;
                } or do {
                    my $err = $@;
                    chomp $err;
                    print "        ❌ ERROR: $err\n";
                # Не прерываем — продолжаем, как в оригинале
                };
            }
            print "  → Patch $patch applied.\n\n";
        }
    }

    # patch after change database schema
    @perl_patches = glob("$dir_name/after*.pl");
    if (@perl_patches) {
        foreach my $patch (@perl_patches) {
            next unless $patch && -e $patch;
        
            # Выводим полный путь к патчу
            print "  → Applying Perl patch: $patch\n";
        
            open(my $pipe, "-|", "$^X $patch") or die "Error applying upgrade script $patch: $!";
            while (my $line = <$pipe>) {
                chomp $line;
                if ($line =~ s/^:://) {
                    printf "\r%-80s", $line;
                    $| = 1;
                } else {
                    print "$line\n";
                }
            }
            close($pipe);
            print "\n";
        }
    }
    @perl_patches = ();

#change version
do_sql($dbh,'UPDATE version SET version="'.$old_releases[$i].'"');
}

print "Done!\n";

exit;
