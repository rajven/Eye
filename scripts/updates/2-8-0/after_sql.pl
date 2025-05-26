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
use Data::Dumper;

STDOUT->autoflush(1);

my $upgrade_from = '2.7.9';
my $this_release = '2.8.0';

$dbh=init_db();
init_option($dbh);

my $force = 0;
if ($ARGV[0] and $ARGV[0] eq 'force') { $force = 1; }

if (!$config_ref{version}) {
    print "Current version unknown! Skip upgrade!\n";
    exit 100;
    }

if (!$force and $this_release eq $config_ref{version}) { print "Already updated!\n"; exit; }

if (!$force and $upgrade_from ne $config_ref{version}) { print "Illegal version. Needed $upgrade_from!\n"; exit; }

print 'Apply patch for version: '.$config_ref{version}.' upgrade to: '.$this_release."\n";

my @authlist_ref = get_records_sql($dbh,"SELECT * FROM User_auth WHERE dns_name>''" );

my $total = scalar @authlist_ref;

print "Stage 1: Fix dns name fields\n";

my $i = 0;
foreach my $row (@authlist_ref) {
    $i++;
    my $percent = int(($i / $total) * 100);
    print "\r::Progress: [$percent%] ";

    my $dns_name = trim($row->{dns_name});
    next unless $dns_name;
    my $original_name = $dns_name;

    $dns_name =~ s/\.$//g;
    $dns_name =~ s/_/-/g;
    $dns_name =~ s/ /-/g;
    $dns_name =~ s/-$//g;
    $dns_name = trim($dns_name);

    my $new;

    # --- Если имя заканчивается на домен, убираем его
    if ($dns_name =~ /\.\Q$domain_name\E$/i) {
        $dns_name =~ s/\.\Q$domain_name\E$//i;
        $dns_name = trim($dns_name);
        $new->{dns_name} = $dns_name if $dns_name;
    }

    # --- Если домен не указан в конце (возможно, уже очищен), обрабатываем точки
    if ($dns_name !~ /\.\Q$domain_name\E$/i) {
        $dns_name =~ s/\.\.$//g;
        my $dot_count = ($dns_name =~ tr/.//);
        if ($dot_count > 1) {
            $dns_name .= "." unless $dns_name =~ /\.$/;
        } else {
            $dns_name =~ s/\.$//g;
        }
        $new->{dns_name} = $dns_name if $dns_name;
    }

    # --- Обновляем, только если имя изменилось
    if (exists $new->{dns_name} && $new->{dns_name} ne $original_name) {
        do_sql($dbh, 'UPDATE User_auth SET dns_name = ? WHERE id = ?', $new->{dns_name}, $row->{id});
    }
}

print "Stage 2: Fix systemd units\n";

do_exec("2-8-0/udpate-services.sh");

print "Done!\n";

exit;
