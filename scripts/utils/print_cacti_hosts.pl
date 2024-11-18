#!/usr/bin/perl -CS
#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use FindBin '$Bin';
use lib "/opt/Eye/scripts";
use strict;
use DBI;
use Date::Parse;
use eyelib::config;
use eyelib::database;
use utf8;

##### unknown mac clean ############

my $db_sql = "Select device_name,ip,comment,snmp_version,community from devices";
$dbh->do("set character set utf8");
$dbh->do("set names utf8");

my $db = $dbh->prepare($db_sql);
$db->execute;
my $db_list=$db->fetchall_arrayref();
$db->finish;

foreach my $row (@$db_list) {
my ($device_name,$ip,$comment,$snmp_version,$community)=@$row;
next if (!$ip);
my $notes='';
if ($comment) { $notes="--notes='".$comment."'"; }
print "php add_device.php --description='".$device_name."' $notes --ip='".$ip."' --template=2 --site=1 --version=$snmp_version --community='".$community."'\n";
}

#add_graphs.php --graph-type=ds --graph-template-id=2 --host-id=[ID]

exit 0;
