#!/usr/bin/perl

use utf8;
use warnings;
use Encode;
use open qw(:std :encoding(UTF-8));
no warnings 'utf8';

use English;
use FindBin '$Bin';
use lib "/opt/Eye/scripts";
use strict;
use DBI;
use File::Basename;
use File::Find;
use File::stat qw(:FIELDS);
use File::Spec::Functions;
use Sys::Hostname;
use DirHandle;
use Time::localtime;
use Fcntl;
use Tie::File;
use Data::Dumper;
use Net::Ping;
use eyelib::config;
use eyelib::main;
use eyelib::nagios;
use eyelib::logconfig;
use eyelib::snmp;
use eyelib::database;
use eyelib::common;
use Fcntl qw(:flock);

open(SELF,"<",$0) or die "Cannot open $0 - $!";
flock(SELF, LOCK_EX|LOCK_NB) or exit 1;

my %devices;
my %auths;

my %dependency;

my $nagios_devices = "/etc/snmp/devices.cfg";

my @OU_list = get_records_sql($dbh,"SELECT * FROM ou");
my %ou;
my @cfg_dirs = ();
foreach my $row (@OU_list) {
next if (!$row->{nagios_dir});
if ($row->{nagios_dir}!~/^$config_ref{nagios_dir}/) { $row->{nagios_dir}=$config_ref{nagios_dir}.'/'.$row->{nagios_dir}; }
$row->{nagios_dir}=~s/\/$//;
$ou{$row->{id}}=$row;
push(@cfg_dirs,$row->{nagios_dir});
}

@cfg_dirs = uniq(@cfg_dirs);

my @Model_list = get_records_sql($dbh,"SELECT * FROM device_models");
my %models;
foreach my $row (@Model_list) {
$models{$row->{id}}=$row;
}

#every monitored device of eye is a host of the monitoring system, an ip record is not
my @netdev_list=get_records_sql($dbh,'SELECT * FROM devices WHERE deleted=0 and monitoring=1');

##################################### Netdevices analyze ################################################
if (scalar(@netdev_list)>0) {
    foreach my $router (@netdev_list) {
        next if (!$router);
        my $ip = $router->{'ip'};
        $ip =~s/\/\d+$//g;
        my $device_id = 'netdev_'.$router->{'id'};
        $devices{$device_id}{ip}=$ip;

	setCommunity($router);
	$devices{$device_id}{snmp} = $router->{snmp};

        $devices{$device_id}{description}=translit($router->{'description'});
        $devices{$device_id}{name} = $router->{'device_name'};
        $devices{$device_id}{device_model_id} = $router->{'device_model_id'};
        if ($router->{'device_model_id'}) { $devices{$device_id}{device_model} = $models{$router->{'device_model_id'}};  }
        $devices{$device_id}{device_id} = $router->{'id'};
        $devices{$device_id}{vendor_id} = $router->{'vendor_id'};
        $devices{$device_id}{ou_id} = 0;
        #1 - switch; 2 - router; 3 - any other device of eye
        #NOT DEVICE TYPE IN DB!!!
        $devices{$device_id}{type}='1';
        $devices{$device_id}{ou_id}='7';
        #the parameters of the monitoring system are the parameters of this host
        $devices{$device_id}{link_check}=$router->{'link_check'};
        $devices{$device_id}{nagios_handler}=$router->{'nagios_handler'};
        $devices{$device_id}{login}=$router->{'login'} // '';
        $devices{$device_id}{password}=$router->{'password'} // '';
        if ($router->{'device_type'} > 2) {
            #the device of the user, a camera, a server and so on
            $devices{$device_id}{type}='3';
            }
        if ($router->{'device_type'} eq 2) {
            $devices{$device_id}{type}='2'; 
            $devices{$device_id}{ou_id}='10';
            }
	if ($router->{'user_id'}) {
            #get user
	    my $login = get_record_sql($dbh,"SELECT * FROM user_list WHERE id= ?", $router->{'user_id'});
	    if ($login and $login->{ou_id} and $ou{$login->{ou_id}}->{nagios_dir}) { $devices{$device_id}{ou_id} = $login->{ou_id}; }
            }
        $devices{$device_id}{ou}=$ou{$devices{$device_id}{ou_id}};

        if (!$devices{$device_id}{ou}->{nagios_dir}) {
    	    if ($devices{$device_id}{type} eq '1') { $devices{$device_id}{ou}->{nagios_dir}='switches'; }
    	    if ($devices{$device_id}{type} eq '2') { $devices{$device_id}{ou}->{nagios_dir}='routers'; }
    	    if ($devices{$device_id}{type} eq '3') { $devices{$device_id}{ou}->{nagios_dir}='devices'; }
    	    }
        $devices{$device_id}{user_id}=$router->{'user_id'};
        #get uplinks
        my $uplink_port = get_record_sql($dbh,"SELECT * FROM device_ports WHERE uplink=1 AND device_id= ? AND target_port_id>0 ORDER BY port DESC",$devices{$device_id}{device_id});
        if ($uplink_port and $uplink_port->{target_port_id}) {
            my $parent_uplink = get_record_sql($dbh,"SELECT * FROM device_ports WHERE id= ? ORDER BY id DESC",$uplink_port->{target_port_id});
            if ($parent_uplink and $parent_uplink->{device_id}) {
        	my $uplink_device = get_record_sql($dbh,"SELECT * FROM devices WHERE id= ? AND monitoring=1 AND deleted=0",$parent_uplink->{device_id});
        	if ($uplink_device) {
        	    $devices{$device_id}{parent}='netdev_'.$uplink_device->{'id'}; 
        	    $devices{$device_id}{parent_name}=$uplink_device->{'device_name'};
        	    }
        	}
            my $uplink = get_record_sql($dbh,"SELECT * FROM device_ports WHERE id=? ORDER BY id DESC",$uplink_port->{id});
    	    $devices{$device_id}{parent_downlink}=$parent_uplink;
    	    $devices{$device_id}{uplink}=$uplink;
            }
        #the port of the access switch, its state and its errors are checked over the
        #snmp of the switch itself
        if ($devices{$device_id}{parent} and $uplink_port and $uplink_port->{target_port_id}) {
            my $parent_port = get_record_sql($dbh,"SELECT * FROM device_ports WHERE id= ?",$uplink_port->{target_port_id});
            if ($parent_port) {
                $devices{$device_id}{parent_port}=$parent_port->{port};
                $devices{$device_id}{parent_port_snmp_index}=$parent_port->{snmp_index};
                $devices{$device_id}{parent_device_id}=$parent_port->{device_id};
                }
            }

        #equipment of the user has no ports of its own, the port of an address of the
        #owner shows the switch the equipment hangs on
        if (!$devices{$device_id}{parent_name} and $router->{'user_id'}) {
            my $auth_connection = get_record_sql($dbh,
                "SELECT C.port_id FROM connections AS C, user_auth AS A WHERE C.auth_id = A.id AND A.user_id = ? AND A.deleted = 0 ORDER BY A.id, C.port_id",
                $router->{'user_id'});
            if ($auth_connection and $auth_connection->{port_id}) {
                my $access_port = get_record_sql($dbh,"SELECT * FROM device_ports WHERE id= ?",$auth_connection->{port_id});
                if ($access_port and $access_port->{device_id}) {
                    my $access_device = get_record_sql($dbh,
                        "SELECT * FROM devices WHERE id= ? AND monitoring=1 AND deleted=0",$access_port->{device_id});
                    if ($access_device) {
                        $devices{$device_id}{parent}='netdev_'.$access_device->{'id'};
                        $devices{$device_id}{parent_name}=$devices{$devices{$device_id}{parent}}->{'name'} // $access_device->{'device_name'};
                        $devices{$device_id}{parent_port}=$access_port->{port};
                        $devices{$device_id}{parent_port_snmp_index}=$access_port->{snmp_index};
                        $devices{$device_id}{parent_device_id}=$access_port->{device_id};
                        }
                    }
                }
            }

        #downlinks
        my @downlinks = get_records_sql($dbh,"SELECT * FROM device_ports WHERE device_id= ? and target_port_id>0 and uplink=0", $devices{$device_id}{device_id});
        foreach my $downlink_port (@downlinks) {
    	    my $downlink = get_record_sql($dbh,"SELECT * FROM device_ports WHERE id= ?", $downlink_port->{target_port_id});
    	    if ($downlink) {
    		my $downlink_device = get_record_sql($dbh,"SELECT * FROM devices WHERE id= ?", $downlink->{device_id});
    		if ($downlink_device) { $downlink_port->{downlink_name}=$downlink_device->{device_name}; }
		}
	    #id,port,snmp_index
            push(@{$devices{$device_id}{downlinks}},$downlink_port);
    	    }
	#custom ports
        my @custom_ports = get_records_sql($dbh,"SELECT * FROM device_ports WHERE device_id= ? and target_port_id=0 and uplink=0 and monitoring=1", $devices{$device_id}{device_id});
        foreach my $downlink_port (@custom_ports) {
            #id,port,snmp_index,description
	    push(@{$devices{$device_id}{downlinks}},$downlink_port);
    	    }
        }
    }

#the access switch of the device is not necessarily analysed before the device itself,
#so the snmp parameters of the parent are taken when the list of the hosts is complete
foreach my $device_id (keys %devices) {
    my $parent_device_id = $devices{$device_id}{parent_device_id};
    next if (!$parent_device_id);
    my $parent = get_record_sql($dbh,"SELECT * FROM devices WHERE id= ? AND deleted=0",$parent_device_id);
    next if (!$parent);
    if (!$devices{$device_id}{parent_snmp}) {
        setCommunity($parent);
        $devices{$device_id}{parent_snmp}=$parent->{snmp};
        }
    }

foreach my $dir (@cfg_dirs) {
    next if ($dir eq '/');
    next if ($dir eq '/etc');
    next if ($dir eq $config_ref{nagios_dir});
    mkdir $dir unless (-d $dir);
    unlink glob "$dir/*.cfg";
}

##################################### Switches config ################################################

write_to_file($nagios_devices,"#lisf of device for nagios",0);

foreach my $device_id (keys %devices) {
my $device = $devices{$device_id};
next if (!$device->{ip});
if ($device->{parent_name}) { push(@{$dependency{$device->{parent_name}}},$device->{name}); }
print_nagios_cfg($device);
write_to_file($nagios_devices,'$devices{"'.$device->{'ip'}.'"}{"hostname"}="'.$device->{'name'}.'";',1);
}

####################### Dependency ###########################

open(FH,">",$config_ref{nagios_dir}."/dependency/dep_hosts.cfg");
foreach my $device_name (keys %dependency) {
my @dep_list=@{$dependency{$device_name}};
if (@dep_list and scalar(@dep_list)) {
    my $dep_hosts = '';
    foreach my $dep_host (@dep_list) {
	next if (!$dep_host);
	$dep_hosts = $dep_hosts.",".$dep_host;
	}
    next if (!$dep_hosts);
    $dep_hosts=~s/^,//;
    print(FH "define hostdependency {\n");
    print(FH "       host_name			$device_name\n");
    print(FH "       dependent_host_name	$dep_hosts\n");
    print(FH "       execution_failure_criteria      n,u\n");
    print(FH "       notification_failure_criteria   d,u\n");
    print(FH "       }\n");
    }
}
close(FH);

exit
