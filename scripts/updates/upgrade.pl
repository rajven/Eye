#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use utf8;
use Encode;
no warnings 'utf8';
use open ':encoding(utf-8)';

use FindBin '$Bin';
use lib "/opt/Eye/scripts";
use eyelib::config;
use eyelib::main;
use eyelib::database;
use strict;
use warnings;

my @old_releases = (
'2.6.1',
'2.6.2',
'2.6.3',
'2.7.0',
'2.7.1',
'2.7.2',
'2.7.3',
'2.7.4',
);

my $r_index = 0;
my %old_releases_h = map {$_ => $r_index++ } @old_releases;
my $eye_release = $old_releases[@old_releases - 1];

$dbh=init_db();
init_option($dbh);

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

my $old_version_index = $old_releases_h{$config_ref{version}} + 1;
my $stage = 1;

for (my $i=$old_version_index; $i < scalar @old_releases; $i++) {
    print "Stage $stage. Upgrade to $old_releases[$i]\n";
    $stage++;
    my $dir_name = $old_releases[$i];
    $dir_name =~s/\./-/g;
    next if (! -d $dir_name);
    #patch before change database schema
    my @perl_patches = glob($dir_name.'/before*.pl');
    if (@perl_patches and scalar @perl_patches) {
        foreach my $patch (@perl_patches) {
            next if (!$patch or ! -e $patch);
            open(my $pipe, "-|", "perl $patch") or die "Error in apply upgrade script $patch! Ошибка: $!";
            while (my $line = <$pipe>) { 
                if ($line =~ /::/) { print "\r"; $line =~s/\:\://; }
                print $line; 
                }
            close($pipe);
            }
        }
    #change database schema
    my @sql_patches = glob($dir_name.'/*.sql');
    if (@sql_patches and scalar @sql_patches) {
        foreach my $patch (@sql_patches) {
            next if (!$patch or ! -e $patch);
            next if ($patch=~/version.sql/);
            my @sql_cmd=read_file($patch);
            foreach my $sql (@sql_cmd) {
                my $sql_prep = $dbh->prepare($sql) or die "Unable to prepare $sql: " . $dbh->errstr."\n";
                my $sql_ref;
                my $rv = $sql_prep->execute();
                if (!$rv) { print "Unable to execute $sql: " . $dbh->errstr."\n"; }
                $sql_prep->finish();
            }
        }
    }
    #patch after change database schema
    @perl_patches = glob($dir_name.'/after*.pl');
    if (@perl_patches and scalar @perl_patches) {
        foreach my $patch (@perl_patches) {
            next if (!$patch or ! -e $patch);
            open(my $pipe, "-|", "perl $patch") or die "Error in apply upgrade script $patch! Ошибка: $!";
            while (my $line = <$pipe>) {
                if ($line =~ /::/) { print "\r"; $line =~s/\:\://; }
                print $line; 
                }
            close($pipe);
            }
        }
    #change version
    do_sql($dbh,'UPDATE version SET `version`="'.$old_releases[$i].'"');
}

print "Done!\n";

exit;
