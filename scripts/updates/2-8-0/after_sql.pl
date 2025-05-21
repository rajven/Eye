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

STDOUT->autoflush(1);

my $upgrade_from = '2.7.9';
my $this_release = '2.8.0';

$dbh=init_db();
init_option($dbh);

if (!$config_ref{version}) {
    print "Current version unknown! Skip upgrade!\n";
    exit 100;
    }

if ($this_release eq $config_ref{version}) { print "Already updated!\n"; exit; }

if ($upgrade_from ne $config_ref{version}) { print "Illegal version. Needed $upgrade_from!\n"; exit; }

print 'Apply patch for version: '.$config_ref{version}.' upgrade to: '.$this_release."\n";

my @authlist_ref = get_records_sql($dbh,"SELECT * FROM User_auth WHERE dns_name>''" );

my $total = scalar @authlist_ref;

print "Stage 1: Fix dns name fields\n";

my $i = 0;
foreach my $row (@authlist_ref) {
my $new;
$i++;
my $dns_name = trim($row->{dns_name});
if ($dns_name and $dns_name =~ /\.\Q$domain_name\E$/i) {
    $dns_name =~ s/\.\Q$domain_name\E$//i;
    $dns_name =~s/\.$//g;
    $dns_name =~s/_/-/g;
    $dns_name =~s/ /-/g;
    $dns_name =~s/-$//g;
    $dns_name = trim($dns_name);
    if ($dns_name) { $new->{dns_name}=$dns_name; }
    } else {
    $dns_name =~s/_/-/g;
    $dns_name =~s/ /-/g;
    $dns_name =~s/-$//g;
    $dns_name = trim($dns_name);
    if ($dns_name and $dns_name=~/\./) {
        $dns_name = $dns_name.".";
        $new->{dns_name}=$dns_name;
        }
    }

my $percent = int(($i / $total) * 100);

if (exists $new->{dns_name} and $new->{dns_name}) {
    update_record($dbh,'User_auth',$new,'id='.$row->{id});
    }

print "\r::Progress: [$percent%] ";
}

print "Done!\n";

exit;
