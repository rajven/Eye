#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

#Import ip to user

use utf8;
use open ":encoding(utf8)";
use FindBin '$Bin';
use lib "/opt/Eye/scripts";
use Data::Dumper;
use eyelib::config;
use eyelib::main;
use eyelib::database;
use eyelib::net_utils;
use strict;
use warnings;

sub add_auth {
my $db = shift;
my $comment = shift;
my $ip = shift;
my $user_id = shift;

my $ip_aton=StrToIp($ip);
my $record=get_record_sql($db,'SELECT id FROM User_auth WHERE `deleted`=0 AND `ip_int`='.$ip_aton);
if ($record->{id}) { return $record->{id}; }
my $user_record=get_record_sql($db,"SELECT * FROM User_list WHERE id=$user_id");
my $new_record;
$new_record->{ip_int}=$ip_aton;
$new_record->{ip}=$ip;
$new_record->{user_id}=$user_id;
$new_record->{save_traf}="$save_detail";
$new_record->{deleted}="0";
$new_record->{created_by}='manual';
$new_record->{ou_id}=$user_record->{ou_id};
$new_record->{filter_group_id}=$user_record->{filter_group_id};
$new_record->{queue_id}=$user_record->{queue_id};
$new_record->{enabled}="$user_record->{enabled}";
$new_record->{comments}=$comment;

my $cur_auth_id=insert_record($db,'User_auth',$new_record);
return $cur_auth_id;
}


my $user_id =$ARGV[0];

exit if (!$user_id);

print "Stage 0: Read ip list for user_id: $user_id\n";

binmode(STDOUT,':utf8');

if (-e "1") {
    my @nSQL=read_file("1");
    foreach my $row (@nSQL) {
        my ($user_name,$auth_ip) = split(/[\,|\;|\s]/,$row);
        next if (!$auth_ip);
        print "Add: $user_name $auth_ip";
        my $ret = add_auth($dbh,$user_name,$auth_ip,$user_id);
        if ($ret) { print "...OK\n"; } else { print "...Fail\n"; }
        }
    }
print "Done!\n";

exit;
