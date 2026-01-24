#!/usr/bin/perl
#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use utf8;
use warnings;
use Encode;
use open qw(:std :encoding(UTF-8));
no warnings 'utf8';

use FindBin '$Bin';
use lib "/opt/Eye/scripts";
use strict;
use DBI;
use Date::Parse;
use eyelib::config;
use eyelib::database;
use eyelib::common;


##### unknown mac clean ############
my $db_sql = "Select device_name,ip,description,snmp_version,community from devices";
$dbh->do("set character set utf8");
$dbh->do("set names utf8");

my $db = $dbh->prepare($db_sql);
$db->execute;
my $db_list=$db->fetchall_arrayref();
$db->finish;

foreach my $row (@$db_list) {
my ($device_name,$ip,$description,$snmp_version,$community)=@$row;
next if (!$ip);
my $notes='';
if ($description) { $notes="--notes='".$description."'"; }
print "php add_device.php --description='".$device_name."' $notes --ip='".$ip."' --template=2 --site=1 --version=$snmp_version --community='".$community."'\n";
}

#add_graphs.php --graph-type=ds --graph-template-id=2 --host-id=[ID]

exit 0;
