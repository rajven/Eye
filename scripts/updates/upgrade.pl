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
use eyelib::logconfig;
use Data::Dumper;

STDOUT->autoflush(1);

# ============================================================
# 1. Читает весь файл целиком
# ============================================================
sub read_file_content {
    my ($filename) = @_;
    open my $fh, '<:encoding(UTF-8)', $filename 
        or die "Cannot open file '$filename': $!\n";
    local $/; # Включаем slurp mode
    my $content = <$fh>;
    close $fh;
    return $content;
}

# ============================================================
# 2. Парсер SQL: разбивает текст на выражения по ';'
#    Игнорирует ';' внутри строк ('...') и комментариев
# ============================================================
sub parse_sql_statements {
    my ($content) = @_;
    my @statements;
    my $current_stmt = '';
    
    my $in_string = 0;
    my $in_line_comment = 0;
    my $in_block_comment = 0;

    my @chars = split //, $content;
    my $len = scalar @chars;

    for (my $i = 0; $i < $len; $i++) {
        my $c = $chars[$i];
        my $next = ($i + 1 < $len) ? $chars[$i + 1] : '';

        # 1. Внутри строкового комментария (-- ...)
        if ($in_line_comment) {
            if ($c eq "\n") {
                $in_line_comment = 0;
                $current_stmt .= $c;
            }
            next;
        }

        # 2. Внутри блочного комментария (/* ... */)
        if ($in_block_comment) {
            if ($c eq '*' && $next eq '/') {
                $in_block_comment = 0;
                $current_stmt .= $c . $next;
                $i++; # пропускаем '/'
            } else {
                $current_stmt .= $c;
            }
            next;
        }

        # 3. Обработка строковых литералов ('...')
        if ($c eq "'") {
            # Экранированная кавычка '' внутри строки
            if ($in_string && $next eq "'") {
                $current_stmt .= "''";
                $i++; # пропускаем вторую кавычку
                next;
            }
            $in_string = !$in_string;
            $current_stmt .= $c;
            next;
        }

        # Если мы внутри строки, просто копируем символ
        if ($in_string) {
            $current_stmt .= $c;
            next;
        }

        # 4. Начало комментариев (только если НЕ внутри строки)
        if ($c eq '-' && $next eq '-') {
            $in_line_comment = 1;
            $current_stmt .= $c . $next;
            $i++;
            next;
        }

        if ($c eq '/' && $next eq '*') {
            $in_block_comment = 1;
            $current_stmt .= $c . $next;
            $i++;
            next;
        }

        # 5. Разделитель выражений
        if ($c eq ';') {
            if ($current_stmt =~ /\S/) { # Если не пустая строка
                push @statements, $current_stmt;
            }
            $current_stmt = '';
            next;
        }

        $current_stmt .= $c;
    }

    # Добавляем последнее выражение, если файл не заканчивается на ';'
    if ($current_stmt =~ /\S/) {
        push @statements, $current_stmt;
    }

    return @statements;
}

# ============================================================
# Основная логика обновления
# ============================================================

my $update_dir = '/opt/Eye/scripts/updates';

opendir(my $dh, $update_dir) or die "Eror listing for $update_dir: $!";
my @old_releases = sort grep { -d "$update_dir/$_" && !/^\.\.?$/ && /^\d/ } readdir($dh);
closedir $dh;

s/-/./g for @old_releases;

my $r_index = 0;
my %old_releases_h = map { $_ => $r_index++ } @old_releases;
my $eye_release = $old_releases[@old_releases - 1];

my $dbh = init_db();

$config_ref{version} = '';
my $version_record = get_record_sql($dbh, "SELECT version FROM version WHERE version is NOT NULL");
if ($version_record) { $config_ref{version} = $version_record->{version}; }

if (!$config_ref{version} and !$ARGV[0]) {
    print "Current version unknown! Skip upgrade!\n";
    exit 100;
}

if ($ARGV[0]) {
    if (exists($old_releases_h{$ARGV[0]})) {
        $config_ref{version} = $ARGV[0];
    } else {
        print "Unknown version $ARGV[0]!\n";
    }
}

if (!exists($old_releases_h{$config_ref{version}})) {
    print "Unknown version $config_ref{version}!\n";
    exit 100;
}

if ($eye_release eq $config_ref{version}) {
    print "Already updated!\n";
    exit;
}

print 'Current version: ' . $config_ref{version} . ' upgrade to: ' . $eye_release . "\n";

do_sql($dbh, "DELETE FROM config WHERE option_id=68");

my $maintance;
$maintance->{'option_id'} = 68;
$maintance->{'value'} = 1;
insert_record($dbh, "config", $maintance);

# 1 - mysql, 0 - pgsql
my $db_type = ($config_ref{DBTYPE} eq 'mysql');

my $old_version_index = $old_releases_h{$config_ref{version}} + 1;
my $stage = 1;

for (my $i = $old_version_index; $i < scalar @old_releases; $i++) {
    print "Stage $stage. Upgrade to $old_releases[$i]\n";
    $stage++;

    my $version_dir = $old_releases[$i];
    $version_dir =~ s/\./-/g;

    $update_dir =~ s{/$}{};
    my $dir_name = "$update_dir/$version_dir";

    next if (!-d $dir_name);

    # === BEFORE patches (Perl) ===
    my @perl_patches = glob("$dir_name/before*.pl");
    if (@perl_patches) {
        foreach my $patch (@perl_patches) {
            next unless $patch && -e $patch;
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

    # === SQL patches  ===
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

            # 1. Читаем файл целиком
            my $file_content = eval { read_file_content($patch) };
            if ($@) {
                print "    ❌ ERROR reading file: $@\n";
                next;
            }

            # 2. Разбиваем на отдельные SQL-выражения
            my @statements = parse_sql_statements($file_content);
            my $stmt_num = 0;
            my $success_count = 0;

            # 3. Применяем каждое выражение
            for my $sql (@statements) {
                $sql =~ s/^\s+//s;
                $sql =~ s/\s+$//s;
                
                next if $sql eq '' || $sql =~ /^(--|#)/; # Пропускаем пустые или чистые комментарии

                $stmt_num++;

                # Делаем безопасный превью для лога (одна строка, макс 100 символов)
                my $preview = $sql;
                $preview =~ s/\s+/ /gs;
                $preview = (length($preview) > 100) ? substr($preview, 0, 97) . '...' : $preview;

                print "    [$stmt_num] Executing: $preview\n";

                eval {
                    my $sth = $dbh->prepare($sql);
                    if (!$sth) {
                        die "Prepare failed: " . $dbh->errstr;
                    }

                    my $rv = $sth->execute();
                    if (!defined $rv) {
                        die "Execute failed: " . $dbh->errstr;
                    }

                    if ($sql =~ /^\s*(INSERT|UPDATE|DELETE|TRUNCATE)/i) {
                        print "        → Affected rows: " . $sth->rows . "\n";
                    } elsif ($sql =~ /^\s*SELECT/i) {
                        my $rows = $sth->fetchall_arrayref({});
                        print "        → Selected " . scalar(@$rows) . " row(s)\n";
                    } else {
                        print "        → Command executed successfully\n";
                    }

                    $sth->finish();
                    $success_count++;
                    1;
                } or do {
                    my $err = $@;
                    chomp $err;
                    print "        ❌ ERROR: $err\n";
                };
            }
            print "  → Patch $patch applied ($success_count/$stmt_num statements successful).\n\n";
        }
    }

    # === AFTER patches (Perl) ===
    my @after_perl_patches = glob("$dir_name/after*.pl");
    if (@after_perl_patches) {
        foreach my $patch (@after_perl_patches) {
            next unless $patch && -e $patch;
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

    # Обновляем версию в БД
    do_sql($dbh, 'UPDATE version SET version=?', $old_releases[$i]);
}

do_sql($dbh, "DELETE FROM config WHERE option_id=68");

print "Done!\n";
exit;
