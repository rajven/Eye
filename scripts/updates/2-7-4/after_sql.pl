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

my $upgrade_from = '2.7.3';
my $this_release = '2.7.4';

$dbh=init_db();
init_option($dbh);

if (!$config_ref{version}) {
    print "Current version unknown! Skip upgrade!\n";
    exit 100;
    }

if ($this_release eq $config_ref{version}) { print "Already updated!\n"; exit; }

if ($upgrade_from ne $config_ref{version}) { print "Illegal version. Needed $upgrade_from!\n"; exit; }

print 'Apply patch for version: '.$config_ref{version}.' upgrade to: '.$this_release."\n";

my @authlist_ref = get_records_sql($dbh,"SELECT * FROM User_auth WHERE `created_by` IS NULL" );

my $total = scalar @authlist_ref;

print "Stage 1: Fill field created_by\n";

my $i = 0;
foreach my $row (@authlist_ref) {
my $new;
$i++;
if (!$row->{dhcp_action}) { $new->{created_by} = 'manual'; }
if ($row->{dhcp_action}=~/^(add|old|del)$/i) { 
    $new->{created_by}='dhcp';
    } else { 
    $new->{created_by}=$row->{dhcp_action};
    $new->{dhcp_action}='';
    }
my $percent = int(($i / $total) * 100);
update_record($dbh,'User_auth',$new,'id='.$row->{id});
print "\r::Progress: [$percent%] ";
}

print "Done!\n";

exit;
