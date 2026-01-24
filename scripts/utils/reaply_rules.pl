#!/usr/bin/perl 

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#
# The script moves user records to the requested group if it matches the rules of membership of this group
#

use utf8;
use warnings;
use Encode;
use open qw(:std :encoding(UTF-8));
no warnings 'utf8';

use English;
use base;
use FindBin '$Bin';
use lib "/opt/Eye/scripts";
use strict;
use DBI;
use Time::Local;
use Net::Patricia;
use NetAddr::IP;
use Data::Dumper;
use eyelib::config;
use eyelib::main;
use eyelib::database;
use eyelib::common;
use eyelib::net_utils;
use File::Basename;
use File::Path;

setpriority(0,0,19);

my $group_id = $ARGV[0];

exit if (!$group_id);

my $group = get_record_sql($dbh,"SELECT * FROM ou WHERE id=?",$group_id);

print "Analyzed rules for group id: $group_id name: $group->{ou_name}\n";

#get userid list
my $sSQL="SELECT * FROM user_auth WHERE ip IS NOT NULL and mac IS NOT NULL and deleted=0";

my @users = get_records_sql($dbh,$sSQL);
foreach my $row (@users) {
next if (!$row);
my $new_user=get_new_user_id($dbh,$row->{'ip'},$row->{'mac'});
if ($new_user->{ou_id} ne $group_id) { next; }
if ($new_user->{ou_id} ne $row->{ou_id}) {
    print "MOVED: $row->{ip} $row->{mac} $row->{description} to $new_user->{ou_id}\n";
    my $auth->{ou_id}=$new_user->{ou_id};
    update_record($dbh,"user_auth",$auth,"id=".$row->{id});
    my $user->{ou_id}=$new_user->{ou_id};
    update_record($dbh,"user_list",$user,"id=".$row->{user_id});
    }
}

exit
