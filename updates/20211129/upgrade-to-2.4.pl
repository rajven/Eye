#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use utf8;
use FindBin '$Bin';
use lib "$Bin/";
use Data::Dumper;
use Rstat::config;
use Rstat::main;
use Rstat::mysql;
use Rstat::net_utils;
use strict;
use warnings;

#$default_user_id; id=20
my $default_user_id=get_option($dbh,20) || 1;
my $hotspot_user_id=get_option($dbh,43) || 1;

print "Stage 1: Migrate users\n";

#find user with few ip
my @user_list = get_records_sql($dbh,"SELECT L.id, COUNT(A.id) as a_count FROM User_list AS L, User_auth AS A WHERE L.id = A.user_id and A.deleted=0 GROUP BY A.user_id");
#create user for 
foreach my $row (@user_list) {
next if ($row->{a_count} <=1);
my @auth_list = get_records_sql($dbh,"SELECT * FROM User_auth WHERE user_id=$row->{id} and deleted=0 GROUP BY mac");
next if (scalar(@auth_list)<=1);
for (my $i=1; $i < scalar(@auth_list); $i++) {
    my $new_user;
    $new_user->{ou_id}=$auth_list[$i]->{ou_id};
    if ($default_user_ou_id = $default_hotspot_ou_id) {
	if ($auth_list[$i]->{user_id} == $default_user_id or $auth_list[$i]->{user_id} == $hotspot_user_id) { $new_user->{ou_id}=$default_user_ou_id; }
	} else {
	if ($default_user_id == $hotspot_user_id) {
	    if ($auth_list[$i]->{user_id} == $default_user_id) { $new_user->{ou_id}=$default_user_ou_id; }
	    } else {
	    if ($auth_list[$i]->{user_id} == $default_user_id) { $new_user->{ou_id}=$default_user_ou_id; }
	    if ($auth_list[$i]->{user_id} == $hotspot_user_id) { $new_user->{ou_id}=$default_hotspot_ou_id; }
	    }
	}
    $new_user->{login}=mac_splitted($auth_list[$i]->{mac});
    $new_user->{enabled}=$auth_list[$i]->{enabled};
    $new_user->{filter_group_id}=$auth_list[$i]->{filter_group_id};
    $new_user->{queue_id}=$auth_list[$i]->{queue_id};
    $new_user->{day_quota}=$auth_list[$i]->{day_quota};
    $new_user->{month_quota}=$auth_list[$i]->{month_quota};
    if (!$auth_list[$i]->{comments}) {
	$auth_list[$i]->{comments}=$auth_list[$i]->{ip};
	my $user_info = get_record_sql($dbh,"SELECT * FROM User_list WHERE id=".$auth_list[$i]->{user_id});
	if ($user_info and $user_info->{fio}) { $auth_list[$i]->{comments} = $user_info->{fio}; }
	}
    if (!$auth_list[$i]->{dns_name}) { $auth_list[$i]->{dns_name}=''; } else {
        my $name_count = get_count_records($dbh,'User_list',"login='".$auth_list[$i]->{dns_name}."'");
        if ($name_count>0) { $name_count++; $auth_list[$i]->{dns_name}.="-".$name_count; }
	$new_user->{login}=$auth_list[$i]->{dns_name};
        }
    $new_user->{fio}=$auth_list[$i]->{dns_name}." ".$auth_list[$i]->{comments};
    my $new_id = insert_record($dbh,"User_list",$new_user);
    if ($new_id) {
        do_sql($dbh,"UPDATE User_auth SET user_id=$new_id WHERE mac='".$auth_list[$i]->{mac}."' and deleted=0");
	print "Created user for mac $auth_list[$i]->{mac} : $new_user->{login} and move all auth records for this mac to new user id: $new_id\n";
	} else {
	print "Error create user for ".Dumper($auth_list[$i])."\n";
	}
    }
}
print "Done!\n";

print "Stage 2: Migrate devices\n";

my @auth_devices = get_records_sql($dbh,"SELECT * FROM User_auth WHERE device_model_id>0 and deleted=0");
foreach my $row (@auth_devices) {
my $device = get_record_sql($dbh,"SELECT * FROM devices WHERE user_id=".$row->{user_id});
next if ($device);
my $device_model = get_record_sql($dbh,"SELECT * FROM device_models WHERE id=".$row->{device_model_id});
next if ($device_model->{vendor_id} == 1);
my $user_info = get_record_sql($dbh,"SELECT * FROM User_list WHERE id=".$row->{user_id});
next if (!$user_info);
my $new_dev;
$new_dev->{device_name} = $user_info->{login};
$new_dev->{device_type} = 5;
$new_dev->{user_id} = $row->{user_id};
$new_dev->{ip} = $row->{ip};
$new_dev->{device_model_id} = $row->{device_model_id};
$new_dev->{vendor_id}=$device_model->{vendor_id};
if ($row->{comments}) { $new_dev->{comment} = $row->{comments}; }
my $new_dev_id = insert_record($dbh,"devices",$new_dev);
if ($new_dev_id) {
    print "Create device: $new_dev->{device_name} $new_dev->{ip} id: $new_dev_id\n";
    } else {
    print "Error create device: $new_dev->{device_name} $new_dev->{ip}!!!\n";
    }
}

do_sql($dbh,"UPDATE User_list SET ou_id=".$default_user_ou_id." WHERE id=".$default_user_id);
do_sql($dbh,"UPDATE User_auth SET ou_id=".$default_user_ou_id." WHERE user_id=".$default_user_id);
if ($default_user_id != $hotspot_user_id) {
    do_sql($dbh,"UPDATE User_list SET ou_id=".$default_hotspot_ou_id." WHERE id=".$hotspot_user_id);
    do_sql($dbh,"UPDATE User_auth SET ou_id=".$default_hotspot_ou_id." WHERE user_id=".$hotspot_user_id);
    }

do_sql($dbh,"DELETE FROM `config_options` WHERE `config_options`.`id` = 20");
do_sql($dbh,"DELETE FROM `config_options` WHERE `config_options`.`id` = 43");
do_sql($dbh,"DELETE FROM `config` WHERE `config`.`option_id` = 20");
do_sql($dbh,"DDELETE FROM `config` WHERE `config`.`option_id` = 43");

print "Done!\n";

exit;
