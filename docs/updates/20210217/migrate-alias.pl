#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use FindBin '$Bin';
use lib "$Bin/";
use strict;
use DBI;
use Time::Local;
use Net::Patricia;
use NetAddr::IP;
use Data::Dumper;
use Rstat::config;
use Rstat::main;
use Rstat::mysql;
use Rstat::net_utils;
use File::Basename;
use File::Path;

print "Start migration: ";
#get userid list
my $sSQL="SELECT id,ip,ip_int,mac,comments,dns_name FROM User_auth where deleted=0 ORDER by ip_int";
my @users = get_custom_records($dbh,$sSQL);
foreach my $row (@users) {
next if (!$row);
next if (!$row->{ip});
next if (!$row->{dns_name} or $row->{dns_name}!~/;/);
my @aliases=split(/;/,$row->{dns_name});
my $auth;
$auth->{dns_name}=trim($aliases[0]);
foreach my $alias (@aliases) {
    next if ($auth->{dns_name} eq $alias);
    my $new_alias;
    $new_alias->{auth_id}=$row->{id};
    $new_alias->{alias}=trim($alias);
    $new_alias->{description}=trim($alias);
    insert_record($dbh,'User_auth_alias',$new_alias);
    }
update_record($dbh,'User_auth',$auth,"id=$row->{id}");
print ".";
}

print "\n";

exit 0;
