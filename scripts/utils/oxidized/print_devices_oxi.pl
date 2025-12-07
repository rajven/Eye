#!/usr/bin/perl 

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use utf8;
use FindBin '$Bin';
use lib "/opt/Eye/scripts";
use strict;
use DBI;
use Data::Dumper;
use Socket;
use eyelib::config;
use eyelib::main;
use eyelib::database;
use eyelib::common;

my @router_list = get_records_sql($dbh,"SELECT D.*, DM.model_name, B.name AS building_name FROM devices D
LEFT JOIN device_models DM ON D.device_model_id = DM.id
LEFT JOIN building B ON D.building_id = B.id
WHERE D.deleted = 0 and device_type<=2 ORDER BY building_name,ip");

foreach my $device (@router_list) {
next if (!$device->{password} or !$device->{login});
$device = netdev_set_auth($device);
my $oxi_model = 'dcnos';
my $comware_cmdline = '';
my $vendor = get_record_sql($dbh,"SELECT * FROM vendors WHERE id=".$device->{vendor_id});
my $model = get_record_sql($dbh,"SELECT * FROM `device_models` WHERE id=".$device->{device_model_id});
my $building = get_record_sql($dbh,"SELECT * FROM building WHERE id=".$device->{building_id});
if ($vendor->{name} =~/zyxel/i) { $oxi_model = 'zynoscli'; }
if ($vendor->{name} =~/snr/i) { $oxi_model = 'dcnos'; }
if ($vendor->{name} =~/huawei/i) { $oxi_model = 'vrp'; }
if ($vendor->{name} =~/eltex/i) { $oxi_model = 'eltex'; }
if ($vendor->{name} =~/raisecom/i) { $oxi_model = 'raisecom'; }
if ($vendor->{name} =~/mikrotik/i) { $oxi_model = 'routeros'; }
if ($vendor->{name} =~/maipu/i) { $oxi_model = 'maipu'; }
if ($vendor->{name} =~/d[\-*]link/i) { $oxi_model = 'dlink'; }
if ($vendor->{name} =~/tp[\-*]link/i) { $oxi_model = 'tplink'; }
if ($vendor->{name} =~/hp/i) { $oxi_model = 'comwarehpe'; $comware_cmdline = '512900'; }
if ($vendor->{name} =~/NetGear/i) { $oxi_model = 'netgear'; }
if ($vendor->{name} =~/Allied Telesis/i) { $oxi_model = 'awplus'; }
if ($oxi_model =~/awplus/ and $model->{model_name}=~/AT\-8000/i) { $oxi_model = 'powerconnect'; }
#web-smart
if ($oxi_model =~/awplus/ and $model->{model_name}=~/AT\-GS950/i) { next; }
if ($oxi_model =~/netgear/ and $model->{model_name}=~/GS110TP/i) { next; }

my $proto = 'telnet';
if ($device->{protocol} eq '0') { $proto = 'ssh'; }
my $location = $building->{name};
my $enable_password = '';
if ($oxi_model !~ /(routeros|awplus)/i) { $enable_password = $device->{password}; }
print $device->{device_name}.":".$device->{ip}.":".$device->{login}.":".$device->{password}.":".$oxi_model.":".$device->{control_port}.":".$proto.":".$location.":".$enable_password;
print ":$comware_cmdline\n";
}

$dbh->disconnect;

exit 0;
