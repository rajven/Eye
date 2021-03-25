#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use FindBin '$Bin';
use lib "$Bin/";
use Data::Dumper;
use Rstat::config;
use Rstat::main;
use Rstat::mysql;
use Rstat::net_utils;
use strict;
use warnings;

my @users = get_records_sql($dbh,"SELECT * FROM User_list");

foreach my $row (@users) {
#hostname rule = 3
#mac rule = 2
#ip_rule = 1
if ($row->{default_subnet}) {
    my $new_rule;
    $new_rule->{user_id} = $row->{id};
    $new_rule->{type}=1;
    $new_rule->{rule}=$row->{default_subnet};
    my $ret = insert_record($dbh,"auth_rules",$new_rule);
    if (!$ret) { die ("Error insert record!"); }
    }
if ($row->{mac_rule}) {
    my $new_rule;
    $new_rule->{user_id} = $row->{id};
    $new_rule->{type}=2;
    $new_rule->{rule}=$row->{mac_rule};
    my $ret = insert_record($dbh,"auth_rules",$new_rule);
    if (!$ret) { die ("Error insert record!"); }
    }
if ($row->{hostname_rule}) {
    my $new_rule;
    $new_rule->{user_id} = $row->{id};
    $new_rule->{type}=3;
    $new_rule->{rule}=$row->{hostname_rule};
    my $ret = insert_record($dbh,"auth_rules",$new_rule);
    if (!$ret) { die ("Error insert record!"); }
    }
}

do_sql($dbh,"ALTER TABLE `User_list` DROP `default_subnet`, DROP `hostname_rule`, DROP `mac_rule`");

print "Done!\n";

exit;
