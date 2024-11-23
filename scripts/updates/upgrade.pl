#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use utf8;
use FindBin '$Bin';
use lib "/opt/Eye/scripts";
use eyelib::config;
use eyelib::main;
use eyelib::database;
use strict;
use warnings;

my @old_releases = (
'2.4.0',
'2.4.1',
'2.4.2',
'2.4.3',
'2.4.4',
'2.4.5',
'2.4.6',
'2.4.7',
'2.4.8',
'2.4.9',
'2.4.10',
'2.4.11',
'2.4.12',
'2.4.14',
'2.5.1',
'2.5.2',
'2.5.3',
'2.6.1',
'2.6.2',
'2.6.3',
'2.7.0'
);

my $r_index = 0;
my %old_releases_h = map {$_ => $r_index++ } @old_releases;
my $eye_release = $old_releases[@old_releases - 1];

$dbh=init_db();
init_option($dbh);

if (!$config_ref{version}) { $config_ref{version}='2.4.12'; }

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
    my @sql_patches = glob($dir_name.'/*.sql');
    if (@sql_patches and scalar @sql_patches) {
        foreach my $patch (@sql_patches) {
            next if (!$patch or ! -e $patch);
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
    my @perl_patches = glob($dir_name.'/*.pl');
    if (@perl_patches and scalar @perl_patches) {
        foreach my $patch (@perl_patches) {
            next if (!$patch or ! -e $patch);
            my $ret = do_exec_ref($patch);
            print $ret->{output}."\n";
            if ($ret->{status}>0) {
                die "Error in apply upgrade script $patch! Abort."; 
                }
            }
        }
}

print "Done!";

exit;
