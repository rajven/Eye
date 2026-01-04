package eyelib::common;

#
# Copyright (C) Roman Dmitriev, rnd@rajven.ru
#

use utf8;
use open ":encoding(utf8)";
use strict;
use English;
use FindBin '$Bin';
use lib "/opt/Eye/scripts";
use base 'Exporter';
use vars qw(@EXPORT @ISA);
use eyelib::config;
use eyelib::main;
use Net::Patricia;
use eyelib::net_utils;
use Data::Dumper;
use eyelib::database;
use DateTime;
use POSIX qw(mktime ctime strftime);
use File::Temp qw(tempfile);
use DBI;

our @ISA = qw(Exporter);

our @EXPORT = qw(
apply_device_lock
create_dns_cname
create_dns_hostname
create_dns_ptr
delete_device
delete_dns_cname
delete_dns_hostname
delete_dns_ptr
delete_user
delete_user_auth
find_mac_in_subnet
get_default_ou
get_device_by_ip
get_dns_name
get_dynamic_ou
get_first_line
get_ip_subnet
get_new_user_id
get_notify_subnet
GetNowTime
get_office_subnet
get_subnets_ref
GetTimeStrByUnixTime
GetUnixTimeByStr
is_ad_computer
is_default_ou
is_dynamic_ou
is_hotspot
new_auth
new_user
process_dhcp_request
recalc_quotes
record_to_txt
resurrection_auth
set_changed
set_lock_discovery
unbind_ports
unblock_user
unset_lock_discovery
update_dns_record
update_dns_record_by_dhcp
);

BEGIN
{

#---------------------------------------------------------------------------------------------------------------

sub get_first_line {
my $msg = shift;
if (!$msg) { return; }
if ($msg=~ /(.*)(\n|\<br\>)/) {
    $msg = $1 if ($1);
    chomp($msg);
    }
return $msg;
}

#---------------------------------------------------------------------------------------------------------------

sub unbind_ports {
my $db = shift;
my $device_id = shift;
return if (!$db);
return if (!$device_id);
my @target = get_records_sql($db, "SELECT U.target_port_id,U.id FROM device_ports U WHERE U.device_id=".$device_id);
foreach my $row (@target) {
        do_sql($db, "UPDATE device_ports SET target_port_id=0 WHERE target_port_id=".$row->{id});
        do_sql($db, "UPDATE device_ports SET target_port_id=0 WHERE id=".$row->{id});
    }
}

#---------------------------------------------------------------------------------------------------------------

sub get_dns_name {
my $db = shift;
my $id = shift;
my $auth_record = get_record_sql($db,"SELECT dns_name FROM user_auth WHERE deleted=0 AND id=".$id);
if ($auth_record and $auth_record->{'dns_name'}) { return $auth_record->{'dns_name'}; }
return;
}

#---------------------------------------------------------------------------------------------------------------

sub record_to_txt {
my $db = shift;
my $table = shift;
my $id = shift;
my $record = get_record_sql($db,'SELECT * FROM '.$table.' WHERE id='.$id);
return hash_to_text($record);
}

#---------------------------------------------------------------------------------------------------------------

sub delete_user_auth {
my $db = shift;
my $id = shift;

my $record = get_record_sql($db,'SELECT * FROM user_auth WHERE id='.$id);
my $auth_ident = $record->{ip};
$auth_ident = $auth_ident . '['.$record->{dns_name} .']' if ($record->{dns_name});
$auth_ident = $auth_ident . ' :: '.$record->{description} if ($record->{dns_name});
my $msg = "";
my $txt_record = hash_to_text($record);
#remove aliases
my @t_user_auth_alias = get_records_sql($db,'SELECT * FROM user_auth_alias WHERE auth_id='.$id);
if (@t_user_auth_alias and scalar @t_user_auth_alias) {
    foreach my $row ( @t_user_auth_alias) {
        my $alias_txt = record_to_txt($db,'user_auth_alias','id='.$row->{'id'});
        if (delete_record($db,'user_auth_alias','id='.$row->{'id'})) {
            $msg = "Deleting an alias: ". $alias_txt . "\n::Success!\n" . $msg;
            } else {
            $msg = "Deleting an alias: ". $alias_txt . "\n::Fail!\n" . $msg;
            }
        }
    }
#remove connections
do_sql($db,'DELETE FROM connections WHERE auth_id='.$id);
#remove user auth record
my $changes = delete_record($db, "user_auth", "id=" . $id);
if ($changes) {
    $msg = "Deleting ip-record: ". $txt_record . "\n::Success!\n" . $msg;
    } else {
    $msg = "Deleting ip-record: ". $txt_record . "\n::Fail!\n" . $msg;
    }

$msg = "Deleting user ip record $auth_ident\n\n".$msg;
db_log_warning($db, $msg, $id);
my $send_alert = isNotifyDelete(get_notify_subnet($db,$record->{ip}));
sendEmail("WARN! ".get_first_line($msg),$msg,1) if ($send_alert);
return $changes;
}

#---------------------------------------------------------------------------------------------------------------

sub unblock_user {
my $db = shift;
my $user_id = shift;
my $user_record = get_record_sql($db,'SELECT * FROM user_list WHERE id='.$user_id);
my $user_ident = 'id:'. $user_record->{'id'} . ' '. $user_record->{'login'};
$user_ident = $user_ident . '[' . $user_record->{'fio'} . ']' if ($user_record->{'fio'});
my $msg = "Amnistuyemo blocked by traffic user $user_ident \nInternet access for the user's IP address has been restored:\n";
my @user_auth = get_records_sql($db,'SELECT * FROM user_auth WHERE deleted=0 AND user_id='.$user_id);
my $send_alert = 0;
if (@user_auth and scalar @user_auth) {
    foreach my $record (@user_auth) {
        $send_alert = ($send_alert or isNotifyUpdate(get_notify_subnet($db,$record->{ip})));
        my $auth_ident = $record->{ip};
        $auth_ident = $auth_ident . '['.$record->{dns_name} .']' if ($record->{dns_name});
        $auth_ident = $auth_ident . ' :: '.$record->{description} if ($record->{dns_name});
        my $new;
        $new->{'blocked'}=0;
        $new->{'changed'}=1;
        my $ret_id = update_record($db,'user_auth',$new,'id='.$record->{'id'});
        if ($ret_id) {
            $msg = $msg ."\n".$auth_ident;
            }
        }
    }
my $new;
$new->{'blocked'}=0;
my $ret_id = update_record($db,'user_list','id='.$user_id);
if ($ret_id) {
    db_log_info($db,$msg);
    sendEmail("WARN! ".get_first_line($msg),$msg,1) if ($send_alert);
    }
return $ret_id;
}

#---------------------------------------------------------------------------------------------------------------

sub delete_user {
my $db = shift;
my $id = shift;
#remove user record
my $changes = delete_record($db, "user_list", "id=" . $id);
#if fail - exit
if (!$changes) { return; }
#remove auth records
my @t_user_auth = get_records_sql($db,'SELECT * FROM user_auth WHERE user_id='.$id);
if (@t_user_auth and scalar @t_user_auth) {
    foreach my $row ( @t_user_auth ) { delete_user_auth($db,$row->{'id'}); }
    }
#remove device
my $device = get_record_sql($db, "SELECT * FROM devices WHERE user_id=".$id);
if ($device) { delete_device($db,$device->{'id'}); }
#remove auth assign rules
do_sql($db, "DELETE FROM auth_rules WHERE user_id=$id");
return $changes;
}

#---------------------------------------------------------------------------------------------------------------

sub delete_device {
my $db = shift;
my $id = shift;
#remove user record
my $changes = delete_record($db, "devices", "id=" . $id);
#if fail - exit
if (!$changes) { return; }
unbind_ports($db, $id);
do_sql($db, "DELETE FROM connections WHERE device_id=" . $id);
do_sql($db, "DELETE FROM device_l3_interfaces WHERE device_id=" . $id);
do_sql($db, "DELETE FROM device_ports WHERE device_id=" . $id);
do_sql($db, "DELETE FROM device_filter_instances WHERE device_id=" . $id);
do_sql($db, "DELETE FROM gateway_subnets WHERE device_id=".$id);
return $changes;
}

#---------------------------------------------------------------------------------------------------------------

sub is_hotspot {
my $db = shift;
my $ip  = shift;
my $users = new Net::Patricia;
#check hotspot
my @ip_rules = get_records_sql($db,'SELECT * FROM subnets WHERE hotspot=1 AND LENGTH(subnet)>0');
foreach my $row (@ip_rules) { $users->add_string($row->{subnet}); }
if ($users->match_string($ip)) { return 1; }
return 0;
}

#---------------------------------------------------------------------------------------------------------------

sub get_office_subnet {
my $db = shift;
my $ip  = shift;
my $subnets = new Net::Patricia;
my @ip_rules = get_records_sql($db,'SELECT * FROM subnets WHERE office=1 AND LENGTH(subnet)>0');
foreach my $row (@ip_rules) { $subnets->add_string($row->{subnet},$row); }
return $subnets->match_string($ip);
}

#---------------------------------------------------------------------------------------------------------------

sub get_notify_subnet {
my $db = shift;
my $ip  = shift;
my $notify_flag = get_office_subnet($db,$ip);
if ($notify_flag) { return $notify_flag->{notify}; }
return 0;
}

#---------------------------------------------------------------------------------------------------------------

sub get_new_user_id {
my $db = shift;
my $ip  = shift;
my $mac = shift;
my $hostname = shift;

my $result;
#check user rules
$mac = mac_simplify($mac);

$result->{ip} = $ip;
$result->{mac} = mac_splitted($mac);
$result->{dhcp_hostname} = $hostname;
$result->{ou_id}=undef;
$result->{user_id}=undef;

my $hotspot_users = new Net::Patricia;
#check hotspot
my @hotspot_rules = get_records_sql($db,'SELECT * FROM subnets WHERE hotspot=1 AND LENGTH(subnet)>0');
foreach my $row (@hotspot_rules) { $hotspot_users->add_string($row->{subnet},$default_hotspot_ou_id); }
if ($hotspot_users->match_string($ip)) { $result->{ou_id}=$hotspot_users->match_string($ip); return $result; }

#check ip
if (defined $ip and $ip) {
    my $users = new Net::Patricia;
    #check ip rules
    my @ip_rules = get_records_sql($db,'SELECT * FROM auth_rules WHERE rule_type=1 and LENGTH(rule)>0 AND user_id IS NOT NULL');
    foreach my $row (@ip_rules) { eval { $users->add_string($row->{rule},$row->{user_id}); }; }
    if ($users->match_string($ip)) { $result->{user_id}=$users->match_string($ip); return $result; }
    }

#check mac
if (defined $mac and $mac) {
    my @user_rules=get_records_sql($db,'SELECT * FROM auth_rules WHERE rule_type=2 AND LENGTH(rule)>0 AND user_id IS NOT NULL');
    foreach my $user (@user_rules) {
	my $rule = mac_simplify($user->{rule});
        if ($mac=~/$rule/i) { $result->{user_id}=$user->{user_id}; return $result; }
        }
    }
#check hostname
if (defined $hostname and $hostname) {
    my @user_rules=get_records_sql($db,'SELECT * FROM auth_rules WHERE rule_type=3 AND LENGTH(rule)>0 AND user_id IS NOT NULL');
    foreach my $user (@user_rules) {
        if ($hostname=~/$user->{rule}/i) { $result->{user_id}=$user->{user_id}; return $result; }
        }
    }

#check ou rules

#check ip
if (defined $ip and $ip) {
    my $users = new Net::Patricia;
    #check ip rules
    my @ip_rules = get_records_sql($db,'SELECT * FROM auth_rules WHERE rule_type=1 and LENGTH(rule)>0 AND ou_id IS NOT NULL');
    foreach my $row (@ip_rules) { eval { $users->add_string($row->{rule},$row->{ou_id}); }; }
    if ($users->match_string($ip)) { $result->{ou_id}=$users->match_string($ip); return $result; }
    }

#check mac
if (defined $mac and $mac) {
    my @user_rules=get_records_sql($db,'SELECT * FROM auth_rules WHERE rule_type=2 AND LENGTH(rule)>0 AND ou_id IS NOT NULL');
    foreach my $user (@user_rules) {
	my $rule = mac_simplify($user->{rule});
        if ($mac=~/$rule/i) { $result->{ou_id}=$user->{ou_id}; return $result; }
        }
    }

#check hostname
if (defined $hostname and $hostname) {
    my @user_rules=get_records_sql($db,'SELECT * FROM auth_rules WHERE rule_type=3 AND LENGTH(rule)>0 AND ou_id IS NOT NULL');
    foreach my $user (@user_rules) {
        if ($hostname=~/$user->{rule}/i) { $result->{ou_id}=$user->{ou_id}; return $result; }
        }
    }

if (!$result->{ou_id}) { $result->{ou_id}=$default_user_ou_id; }

return $result;
}

#---------------------------------------------------------------------------------------------------------------

sub set_changed {
my $db = shift;
my $id = shift;
return if (!$db or !$id);
my $update_record;
$update_record->{changed}=1;
update_record($db,'user_auth',$update_record,"id=$id");
}

#---------------------------------------------------------------------------------------------------------------

sub update_dns_record {

my $hdb = shift;
my $auth_id = shift;

return if (!$config_ref{enable_dns_updates});

# Переподключение
if (!$hdb or !$hdb->ping) { $hdb = init_db(); }

#get domain
my $ad_zone = get_option($hdb,33);

#get dns server
my $ad_dns = get_option($hdb,3);

my $enable_ad_dns_update = ($ad_zone and $ad_dns and $config_ref{enable_dns_updates});

log_debug("Auth id: ".$auth_id);
log_debug("enable_ad_dns_update: ".$enable_ad_dns_update);
log_debug("DNS update flags - zone: ".$ad_zone.", dns: ".$ad_dns.", enable_ad_dns_update: ".$enable_ad_dns_update);

my @dns_queue = get_records_sql($hdb,"SELECT * FROM dns_queue WHERE auth_id=".$auth_id." AND value>'' AND value NOT LIKE '%.'ORDER BY id ASC");

if (!@dns_queue or !scalar @dns_queue) { return; }

foreach my $dns_cmd (@dns_queue) {

my $fqdn = '';
my $fqdn_ip = '';
my $fqdn_parent = '';
my $static_exists = 0;
my $static_ref = '';
my $static_ok = 0;

eval {

if ($dns_cmd->{name_type}=~/^cname$/i) {
    #skip update unknown domain
    if ($dns_cmd->{name} =~/\.$/ or $dns_cmd->{value} =~/\.$/) { next; }

    $fqdn=lc($dns_cmd->{name});
    $fqdn=~s/\.$ad_zone$//i;
#    $fqdn=~s/\.$//;
    if ($dns_cmd->{value}) {
        $fqdn_parent=lc($dns_cmd->{value});
        $fqdn_parent=~s/\.$ad_zone$//i;
#        $fqdn_parent=~s/\.$//;
        }

    $fqdn = $fqdn.".".$ad_zone;
    $fqdn_parent = $fqdn_parent.".".$ad_zone;

    #remove cname
    if ($dns_cmd->{operation_type} eq 'del') {
        delete_dns_cname($fqdn_parent,$fqdn,$ad_zone,$ad_dns,$hdb);
        }
    #create cname
    if ($dns_cmd->{operation_type} eq 'add') {
        create_dns_cname($fqdn_parent,$fqdn,$ad_zone,$ad_dns,$hdb);
        }
    }

if ($dns_cmd->{name_type}=~/^a$/i) {
    #skip update unknown domain
    if ($dns_cmd->{name} =~/\.$/ or $dns_cmd->{value} =~/\.$/) { next; }
    $fqdn=lc($dns_cmd->{name});
    $fqdn=~s/\.$ad_zone$//i;
#    $fqdn=~s/\.$//;
    if (!$dns_cmd->{value}) { next; }
    $fqdn_ip=lc($dns_cmd->{value});
    $fqdn = $fqdn.".".$ad_zone;
    #dns update disabled?
    my $maybe_update_dns=( $enable_ad_dns_update and $office_networks->match_string($fqdn_ip) );
    if (!$maybe_update_dns) {
        db_log_info($hdb,"FOUND Auth_id: $auth_id. DNS update disabled.");
        next;
        }
    #get aliases
    my @aliases = get_records_sql($hdb,"SELECT * FROM user_auth_alias WHERE auth_id=".$auth_id);
    #remove A & PTR
    if ($dns_cmd->{operation_type} eq 'del') {
        #remove aliases
        if (@aliases and scalar @aliases) {
                foreach my $alias (@aliases) {
                    delete_dns_cname($fqdn,$alias->{alias},$ad_zone,$ad_dns,$hdb) if ($alias->{alias});
                    delete_dns_hostname($fqdn,$alias->{alias},$ad_zone,$ad_dns,$hdb) if ($alias->{alias});
                }
            }
        #remove main record
        delete_dns_hostname($fqdn,$fqdn_ip,$ad_zone,$ad_dns,$hdb);
        delete_dns_ptr($fqdn,$fqdn_ip,$ad_zone,$ad_dns,$hdb);
        }
    #create A & PTR
    if ($dns_cmd->{operation_type} eq 'add') {
        my @dns_record=ResolveNames($fqdn,$dns_server);
        $static_exists = (scalar @dns_record>0);
        if ($static_exists) {
            $static_ref = join(' ',@dns_record);
            foreach my $dns_a (@dns_record) {
                if ($dns_a=~/^$fqdn_ip$/) { $static_ok = 1; }
                }
            db_log_debug($hdb,"Dns record for static record $fqdn: $static_ref");
            }
        #skip update if already exists
        if ($static_ok) {
            db_log_debug($hdb,"Static record for $fqdn [$static_ok] correct.");
            next;
            }
        #create record
        create_dns_hostname($fqdn,$fqdn_ip,$ad_zone,$ad_dns,$hdb);
        create_dns_ptr($fqdn,$fqdn_ip,$ad_zone,$ad_dns,$hdb);
        #create aliases
        if (@aliases and scalar @aliases) {
                foreach my $alias (@aliases) {
                    create_dns_cname($fqdn,$alias->{alias},$ad_zone,$ad_dns,$hdb) if ($alias->{alias});
                }
            }
        }
    }
#PTR
if ($dns_cmd->{name_type}=~/^ptr$/i) {
    $fqdn=lc($dns_cmd->{name});
    $fqdn=~s/\.$ad_zone$//i;
#    $fqdn=~s/\.$//;
    if (!$dns_cmd->{value}) { next; }
    $fqdn_ip=lc($dns_cmd->{value});
    #skip update unknown domain
    if ($fqdn =~/\.$/) { next; }
    $fqdn = $fqdn.".".$ad_zone;
    #dns update disabled?
    my $maybe_update_dns=( $enable_ad_dns_update and $office_networks->match_string($fqdn_ip) );
    if (!$maybe_update_dns) {
        db_log_info($hdb,"FOUND Auth_id: $auth_id. DNS update disabled.");
        next;
        }
    #remove A & PTR
    if ($dns_cmd->{operation_type} eq 'del') {
        #remove main record
        delete_dns_ptr($fqdn,$fqdn_ip,$ad_zone,$ad_dns,$hdb);
        }
    #create A & PTR
    if ($dns_cmd->{operation_type} eq 'add') {
        #create record
        create_dns_ptr($fqdn,$fqdn_ip,$ad_zone,$ad_dns,$hdb);
        }
    }

};
if ($@) { log_error("Error dns commands: $@"); }
}

}

#---------------------------------------------------------------------------------------------------------------
sub is_ad_computer {

my $hdb = shift;
my $computer_name = shift;

if (!$computer_name or $computer_name =~/UNDEFINED/i) { return 0; }

my $ad_check = get_option($hdb,73);
if (!$ad_check) { return 1; }

my $ad_zone = get_option($hdb,33);

if ($computer_name =~/\./) {
    if ($computer_name!~/\.$ad_zone$/i) { 
        db_log_verbose($hdb,"The domain of the computer $computer_name does not match the domain of the organization $ad_zone. Skip update.");
        return 0;
        }
    }

if ($computer_name =~/^(.+)\./) {
    $computer_name = $1;
    }

my $ad_computer_name = trim($computer_name).'$';

my $name_in_cache = get_record_sql($hdb,"SELECT * FROM ad_comp_cache WHERE name='".$computer_name."'");
if ($name_in_cache) { return 1; }

my %name_found=do_exec_ref('/usr/bin/getent passwd '.$ad_computer_name);
if (!$name_found{output} or $name_found{status} ne 0) {
    db_log_verbose($hdb,"The computer ".uc($ad_computer_name)." was not found in the domain $ad_zone. Skip update.");
    return 0;
    }

do_sql($hdb,"INSERT INTO ad_comp_cache(name) VALUES('".$computer_name."') ON DUPLICATE KEY UPDATE name='".$computer_name."';");
return 1;
}

#---------------------------------------------------------------------------------------------------------------

sub update_dns_record_by_dhcp {

my $hdb = shift;
my $dhcp_record = shift;
my $auth_record = shift;

return if (!$config_ref{enable_dns_updates});

my $ad_zone = get_option($hdb,33);
my $ad_dns = get_option($hdb,3);

$update_hostname_from_dhcp = get_option($hdb,46) || 0;
my $subnets_dhcp = get_subnets_ref($hdb);
my $enable_ad_dns_update = ($ad_zone and $ad_dns and $update_hostname_from_dhcp);

log_debug("Dhcp record: ".Dumper($dhcp_record));
log_debug("Subnets: ".Dumper($subnets_dhcp->{$dhcp_record->{network}->{subnet}}));
log_debug("enable_ad_dns_update: ".$enable_ad_dns_update);
log_debug("DNS update flags - zone: ".$ad_zone.",dns: ".$ad_dns.", update_hostname_from_dhcp: ".$update_hostname_from_dhcp.", enable_ad_dns_update: ".$enable_ad_dns_update. ", network dns-update enabled: ".$subnets_dhcp->{$dhcp_record->{network}->{subnet}}->{dhcp_update_hostname});

my $maybe_update_dns=($enable_ad_dns_update and $subnets_dhcp->{$dhcp_record->{network}->{subnet}}->{dhcp_update_hostname} and (is_ad_computer($hdb,$dhcp_record->{hostname_utf8}) and ($dhcp_record->{type}=~/add/i or $dhcp_record->{type}=~/old/i)));
if (!$maybe_update_dns) {
    db_log_debug($hdb,"FOUND Auth_id: $auth_record->{id}. DNS update don't needed.");
    return 0;
    }

log_debug("DNS update enabled.");
#update dns block
my $fqdn_static;
if ($auth_record->{dns_name}) {
    $fqdn_static=lc($auth_record->{dns_name});
    if ($fqdn_static!~/\.$ad_zone$/i) {
            $fqdn_static=~s/\.$//;
            $fqdn_static=lc($fqdn_static.'.'.$ad_zone);
            }
    }

my $fqdn=lc(trim($dhcp_record->{hostname_utf8}));
if ($fqdn!~/\.$ad_zone$/i) {
    $fqdn=~s/\.$//;
    $fqdn=lc($fqdn.'.'.$ad_zone);
    }

db_log_debug($hdb,"FOUND Auth_id: $auth_record->{id} dns_name: $fqdn_static dhcp_hostname: $fqdn");

#check exists static dns name
my $static_exists = 0;
my $dynamic_exists = 0;
my $static_ok = 0;
my $dynamic_ok = 0;
my $static_ref;
my $dynamic_ref;

if ($fqdn_static ne '') {
    my @dns_record=ResolveNames($fqdn_static,$dns_server);
    $static_exists = (scalar @dns_record>0);
    if ($static_exists) {
            $static_ref = join(' ',@dns_record);
            foreach my $dns_a (@dns_record) {
                if ($dns_a=~/^$dhcp_record->{ip}$/) { $static_ok = $dns_a; }
                }
            }
    } else { $static_ok = 1; }

if ($fqdn ne '') {
    my @dns_record=ResolveNames($fqdn,$dns_server);
    $dynamic_exists = (scalar @dns_record>0);
    if ($dynamic_exists) {
            $dynamic_ref = join(' ',@dns_record);
            foreach my $dns_a (@dns_record) {
                if ($dns_a=~/^$dhcp_record->{ip}$/) { $dynamic_ok = $dns_a; }
                }
            }
    }

db_log_debug($hdb,"Dns record for static record $fqdn_static: $static_ok");
db_log_debug($hdb,"Dns record for dhcp-hostname $fqdn: $dynamic_ok");

if ($fqdn_static ne '') {
    if (!$static_ok) {
        db_log_info($hdb,"Static record mismatch! Expected $fqdn_static => $dhcp_record->{ip}, recivied: $static_ref");
        if (!$static_exists) {
                db_log_info($hdb,"Static dns hostname defined but not found. Create it ($fqdn_static => $dhcp_record->{ip})!");
                create_dns_hostname($fqdn_static,$dhcp_record->{ip},$ad_zone,$ad_dns,$hdb);
                }
        } else {
	db_log_debug($hdb,"Static record for $fqdn_static [$static_ok] correct.");
	}
    }

if ($fqdn ne '' and $dynamic_ok ne '') { db_log_debug($hdb,"Dynamic record for $fqdn [$dynamic_ok] correct. No changes required."); }

if ($fqdn ne '' and !$dynamic_ok) {
    log_error("Dynamic record mismatch! Expected: $fqdn => $dhcp_record->{ip}, recivied: $dynamic_ref. Checking the status.");
    #check exists hostname
    my $another_hostname_exists = 0;
    my $hostname_filter = ' LOWER(dns_name) REGEXP("^'.lc($dhcp_record->{hostname_utf8}).'\.*$")';
    if ($fqdn_static ne '' and $fqdn !~/$fqdn_static/) {
	    $hostname_filter = $hostname_filter . ' or LOWER(dns_name) REGEXP("^'.lc($auth_record->{dns_name}).'\.*$")';
	    }
    #check exists another records with some static hostname
    my $filter_sql = 'SELECT * FROM user_auth WHERE id<>'.$auth_record->{id}.' and deleted=0 and ('.$hostname_filter.') ORDER BY last_found DESC';
    db_log_debug($hdb,"Search dhcp hostname by: ".$filter_sql);
    my $name_record = get_record_sql($hdb,$filter_sql);
    if ($name_record->{dns_name} =~/^$fqdn$/i or $name_record->{dns_name} =~/^$dhcp_record->{hostname_utf8}$/i) {
	    $another_hostname_exists = 1;
	    }
    if (!$another_hostname_exists) {
            if ($fqdn_static and $fqdn_static ne '') {
                    if ($fqdn_static!~/$fqdn/) {
                        db_log_info($hdb,"Hostname from dhcp request $fqdn differs from static dns hostname $fqdn_static. Ignore dynamic binding!");
#                        delete_dns_hostname($fqdn,$dhcp_record->{ip},$ad_zone,$ad_dns,$hdb);
#                        create_dns_hostname($fqdn,$dhcp_record->{ip},$ad_zone,$ad_dns,$hdb);
                        }
                    } else {
        	    db_log_info($hdb,"Rewrite aliases if exists for $fqdn => $dhcp_record->{ip}");
                    #get and remove aliases
                    my @aliases = get_records_sql($hdb,"SELECT * FROM user_auth_alias WHERE auth_id=".$auth_record->{id});
                    if (@aliases and scalar @aliases) {
                            foreach my $alias (@aliases) {
                                delete_dns_cname($fqdn_static,$alias->{alias},$ad_zone,$ad_dns,$hdb) if ($alias->{alias});
                            }
                        }
        	    db_log_info($hdb,"Static dns hostname not defined. Create dns record by dhcp request. $fqdn => $dhcp_record->{ip}");
        	    create_dns_hostname($fqdn,$dhcp_record->{ip},$ad_zone,$ad_dns,$hdb);
                    if (@aliases and scalar @aliases) {
                            foreach my $alias (@aliases) {
                                create_dns_cname($fqdn_static,$alias->{alias},$ad_zone,$ad_dns,$hdb) if ($alias->{alias});
                            }
                        }
        	    }
	    } else {
            db_log_error($hdb,"Found another record with some hostname id: $name_record->{id} ip: $name_record->{ip} hostname: $name_record->{dns_name}. Skip update.");
            }
    }
#end update dns block
}

#------------------------------------------------------------------------------------------------------------

sub apply_device_lock {
    my $db = shift;
    my $device_id = shift;
    my $iteration = shift || 0;
    $iteration++;
    if ($iteration>2) { return 0; }
    my $dev = get_record_sql($db,"SELECT discovery_locked, locked_timestamp, UNIX_TIMESTAMP(locked_timestamp) as u_locked_timestamp  FROM devices WHERE id=".$device_id);
    if (!$dev) { return 0; }
    if (!$dev->{'discovery_locked'}) { return set_lock_discovery($db,$device_id); }
    #if timestamp undefined, set and return
    if (!$dev->{'locked_timestamp'}) { return set_lock_discovery($db,$device_id); }
    #wait for discovery
    my $wait_time = $dev->{'locked_timestamp'} + 30 - time();
    if ($wait_time<0) { return set_lock_discovery($db,$device_id); }
    sleep($wait_time);
    return apply_device_lock($db,$device_id,$iteration);
}

#------------------------------------------------------------------------------------------------------------

sub set_lock_discovery {
    my $db = shift;
    my $device_id = shift;
    my $new;
    $new->{'discovery_locked'} = 1;
    $new->{'locked_timestamp'} = GetNowTime();
    if (update_record($db,'devices',$new,'id='.$device_id)) { return 1; } 
    return 0;
}

#------------------------------------------------------------------------------------------------------------

sub unset_lock_discovery {
    my $db = shift;
    my $device_id = shift;
    my $new;
    $new->{'discovery_locked'} = 0;
    $new->{'locked_timestamp'} = GetNowTime();
    if (update_record($db,'devices',$new,'id='.$device_id)) { return 1; } 
    return 0;
}

#------------------------------------------------------------------------------------------------------------

sub create_dns_cname {
my $fqdn = shift;
my $alias = shift;
my $zone = shift;
my $server = shift;
my $db = shift;
#skip update domain controllers
if (!$db) {
    log_info("DNS-UPDATE: Add => Zone $zone Server: $server CNAME: $alias for $fqdn"); 
    } else {
    db_log_info($db,"DNS-UPDATE: Add => Zone $zone Server: $server CNAME: $alias for $fqdn ");
    }
my $ad_zone = get_option($db,33);
my $nsupdate_file = "/tmp/".$fqdn."-nsupdate";
my @add_dns;
if ($config_ref{dns_server_type}=~/windows/i) {
    push(@add_dns,"gsstsig");
    push(@add_dns,"server $server");
    push(@add_dns,"zone $zone");
    push(@add_dns,"update add $alias 3600 cname $fqdn.");
    push(@add_dns,"send");
    write_to_file($nsupdate_file,\@add_dns);
    do_exec('/usr/bin/kinit -k -t /opt/Eye/scripts/cfg/dns_updater.keytab dns_updater@'.uc($ad_zone).' && /usr/bin/nsupdate "'.$nsupdate_file.'"');
    }

if ($config_ref{dns_server_type}=~/bind/i) {
    push(@add_dns,"server $server");
    push(@add_dns,"zone $zone");
    push(@add_dns,"update add $alias 3600 cname $fqdn.");
    push(@add_dns,"send");
    write_to_file($nsupdate_file,\@add_dns);
    do_exec('/usr/bin/nsupdate -k /etc/bind/rndc.key "'.$nsupdate_file.'"');
    }

if (-e "$nsupdate_file") { unlink "$nsupdate_file"; }
}

#---------------------------------------------------------------------------------------------------------------

sub delete_dns_cname {
my $fqdn = shift;
my $alias = shift;
my $zone = shift;
my $server = shift;
my $db = shift;
if (!$db) {
    log_info("DNS-UPDATE: Delete => Zone $zone Server: $server CNAME: $alias for $fqdn ");
    } else {
    db_log_info($db,"DNS-UPDATE: Delete => Zone $zone Server: $server CNAME: $alias for $fqdn");
    }
my $ad_zone = get_option($db,33);
my $nsupdate_file = "/tmp/".$fqdn."-nsupdate";
my @add_dns;
if ($config_ref{dns_server_type}=~/windows/i) {
    push(@add_dns,"gsstsig");
    push(@add_dns,"server $server");
    push(@add_dns,"zone $zone");
    push(@add_dns,"update delete $alias cname ");
    push(@add_dns,"send");
    write_to_file($nsupdate_file,\@add_dns);
    do_exec('/usr/bin/kinit -k -t /opt/Eye/scripts/cfg/dns_updater.keytab dns_updater@'.uc($ad_zone).' && /usr/bin/nsupdate "'.$nsupdate_file.'"');
    }

if ($config_ref{dns_server_type}=~/bind/i) {
    push(@add_dns,"server $server");
    push(@add_dns,"zone $zone");
    push(@add_dns,"update delete $alias cname");
    push(@add_dns,"send");
    write_to_file($nsupdate_file,\@add_dns);
    do_exec('/usr/bin/nsupdate -k /etc/bind/rndc.key "'.$nsupdate_file.'"');
    }

if (-e "$nsupdate_file") { unlink "$nsupdate_file"; }
}

#------------------------------------------------------------------------------------------------------------

sub create_dns_hostname {
my $fqdn = shift;
my $ip = shift;
my $zone = shift;
my $server = shift;
my $db = shift;
#skip update domain controllers
if ($fqdn=~/^dc[0-9]{1,2}\./i) { return; }
if (!$db) {
    log_info("DNS-UPDATE: Add => Zone $zone Server: $server A: $fqdn IP: $ip"); 
    } else {
    db_log_info($db,"DNS-UPDATE: Add => Zone $zone Server: $server A: $fqdn IP: $ip");
    }
my $ad_zone = get_option($db,33);
my $nsupdate_file = "/tmp/".$fqdn."-nsupdate";
my @add_dns;
if ($config_ref{dns_server_type}=~/windows/i) {
    push(@add_dns,"gsstsig");
    push(@add_dns,"server $server");
    push(@add_dns,"zone $zone");
    push(@add_dns,"update add $fqdn 3600 A $ip");
    push(@add_dns,"send");
    write_to_file($nsupdate_file,\@add_dns);
    do_exec('/usr/bin/kinit -k -t /opt/Eye/scripts/cfg/dns_updater.keytab dns_updater@'.uc($ad_zone).' && /usr/bin/nsupdate "'.$nsupdate_file.'"');
    }

if ($config_ref{dns_server_type}=~/bind/i) {
    push(@add_dns,"server $server");
    push(@add_dns,"zone $zone");
    push(@add_dns,"update add $fqdn 3600 A $ip");
    push(@add_dns,"send");
    write_to_file($nsupdate_file,\@add_dns);
    do_exec('/usr/bin/nsupdate -k /etc/bind/rndc.key "'.$nsupdate_file.'"');
    }

if (-e "$nsupdate_file") { unlink "$nsupdate_file"; }
}

#---------------------------------------------------------------------------------------------------------------

sub delete_dns_hostname {
my $fqdn = shift;
my $ip = shift;
my $zone = shift;
my $server = shift;
my $db = shift;
#skip update domain controllers
if ($fqdn=~/^dc[0-9]{1,2}\./i) { return; }
if (!$db) {
    log_info("DNS-UPDATE: Delete => Zone $zone Server: $server A: $fqdn IP: $ip"); 
    } else {
    db_log_info($db,"DNS-UPDATE: Delete => Zone $zone Server: $server A: $fqdn IP: $ip");
    }
my $ad_zone = get_option($db,33);
my $nsupdate_file = "/tmp/".$fqdn."-nsupdate";
my @add_dns;
if ($config_ref{dns_server_type}=~/windows/i) {
    push(@add_dns,"gsstsig");
    push(@add_dns,"server $server");
    push(@add_dns,"zone $zone");
    push(@add_dns,"update delete $fqdn A");
    push(@add_dns,"send");
    write_to_file($nsupdate_file,\@add_dns);
    do_exec('/usr/bin/kinit -k -t /opt/Eye/scripts/cfg/dns_updater.keytab dns_updater@'.uc($ad_zone).' && /usr/bin/nsupdate "'.$nsupdate_file.'"');
    }

if ($config_ref{dns_server_type}=~/bind/i) {
    push(@add_dns,"server $server");
    push(@add_dns,"zone $zone");
    push(@add_dns,"update delete $fqdn A");
    push(@add_dns,"send");
    write_to_file($nsupdate_file,\@add_dns);
    do_exec('/usr/bin/nsupdate -k /etc/bind/rndc.key "'.$nsupdate_file.'"');
    }

if (-e "$nsupdate_file") { unlink "$nsupdate_file"; }
}

#---------------------------------------------------------------------------------------------------------------

sub create_dns_ptr {
my $fqdn = shift;
my $ip = shift;
my $ad_zone = shift;
my $server = shift;
my $db = shift;

my $radr;
my $zone;

#skip update domain controllers
if ($fqdn=~/^dc[0-9]{1,2}\./i) { return; }
if ($ip =~ /([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})(\/[0-9]{1,2}){0,1}/) {
    return 0 if($1 > 255 || $2 > 255 || $3 > 255 || $4 > 255);
    $radr = "$4.$3.$2.$1.in-addr.arpa";
    $zone = "$3.$2.$1.in-addr.arpa";
    }

if (!$radr or !$zone) { return 0; }

if (!$db) { return 0; }

db_log_info($db,"DNS-UPDATE: Zone $zone Server: $server A: $fqdn PTR: $ip");

my $nsupdate_file = "/tmp/".$radr."-nsupdate";

my @add_dns;

if ($config_ref{dns_server_type}=~/windows/i) {
    push(@add_dns,"gsstsig");
    push(@add_dns,"server $server");
    push(@add_dns,"zone $zone");
    push(@add_dns,"update add $radr 3600 PTR $fqdn.");
    push(@add_dns,"send");
    write_to_file($nsupdate_file,\@add_dns);
    my $run_cmd = '/usr/bin/kinit -k -t /opt/Eye/scripts/cfg/dns_updater.keytab dns_updater@'.uc($ad_zone).' && /usr/bin/nsupdate "'.$nsupdate_file.'"';
    do_exec($run_cmd);
    }

if ($config_ref{dns_server_type}=~/bind/i) {
    push(@add_dns,"server $server");
    push(@add_dns,"zone $zone");
    push(@add_dns,"update add $radr 3600 PTR $fqdn.");
    push(@add_dns,"send");
    write_to_file($nsupdate_file,\@add_dns);
    my $run_cmd = '/usr/bin/nsupdate -k /etc/bind/rndc.key "'.$nsupdate_file.'"';
    do_exec($run_cmd);
    }

if (-e "$nsupdate_file") { unlink "$nsupdate_file"; }
}

#---------------------------------------------------------------------------------------------------------------

sub delete_dns_ptr {
my $fqdn = shift;
my $ip = shift;
my $ad_zone = shift;
my $server = shift;
my $db = shift;

my $radr;
my $zone;

#skip update domain controllers
if ($fqdn=~/^dc[0-9]{1,2}\./i) { return; }
if ($ip =~ /([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})(\/[0-9]{1,2}){0,1}/) {
    return 0 if($1 > 255 || $2 > 255 || $3 > 255 || $4 > 255);
    $radr = "$4.$3.$2.$1.in-addr.arpa";
    $zone = "$3.$2.$1.in-addr.arpa";
    }
if (!$radr or !$zone) { return 0; }

if (!$db) { return 0 ; }

db_log_info($db,"DNS-UPDATE: Delete => Zone $zone Server: $server A: $fqdn PTR: $ip");

my $nsupdate_file = "/tmp/".$radr."-nsupdate";

my @add_dns;

if ($config_ref{dns_server_type}=~/windows/i) {
    push(@add_dns,"gsstsig");
    push(@add_dns,"server $server");
    push(@add_dns,"zone $zone");
    push(@add_dns,"update delete $radr PTR");
    push(@add_dns,"send");
    write_to_file($nsupdate_file,\@add_dns);
    my $run_cmd = '/usr/bin/kinit -k -t /opt/Eye/scripts/cfg/dns_updater.keytab dns_updater@'.uc($ad_zone).' && /usr/bin/nsupdate "'.$nsupdate_file.'"';
    do_exec($run_cmd);
    }

if ($config_ref{dns_server_type}=~/bind/i) {
    push(@add_dns,"server $server");
    push(@add_dns,"zone $zone");
    push(@add_dns,"update delete $radr PTR");
    push(@add_dns,"send");
    write_to_file($nsupdate_file,\@add_dns);
    my $run_cmd = '/usr/bin/nsupdate -k /etc/bind/rndc.key "'.$nsupdate_file.'"';
    do_exec($run_cmd);
    }

if (-e "$nsupdate_file") { unlink "$nsupdate_file"; }
}

#---------------------------------------------------------------------------------------------------------------

sub new_user {
my $db = shift;
my $user_info = shift;
my $user;
if ($user_info->{mac}) {
    $user->{login}=mac_splitted($user_info->{mac});
    } else {
    $user->{login}=$user_info->{ip};
    }

if ($user_info->{dhcp_hostname}) { $user->{fio}=$user_info->{dhcp_hostname}; } 
if (!$user->{fio}) { $user->{fio}=$user_info->{ip}; }

my $login_count = get_count_records($db,"user_list","(login LIKE '".$user->{login}."(%)') OR (login='".$user->{login}."')");
if ($login_count) { $login_count++; $user->{login} .="(".$login_count.")"; }

$user->{ou_id} = $user_info->{ou_id};
my $ou_info = get_record_sql($db,"SELECT * FROM OU WHERE id=".$user_info->{'ou_id'});
if ($ou_info) {
    $user->{'enabled'} = $ou_info->{'enabled'};
    $user->{'queue_id'} = $ou_info->{'queue_id'};
    $user->{'filter_group_id'} = $ou_info->{'filter_group_id'};
    }

my $result = insert_record($db,"user_list",$user);
if ($result and $config_ref{auto_mac_rule} and $user_info->{mac}) {
    my $auth_rule;
    $auth_rule->{user_id} = $result;
    $auth_rule->{rule_type} = 2;
    $auth_rule->{rule} = mac_splitted($user_info->{mac});
    insert_record($db,"auth_rules",$auth_rule);
    }
return $result;
}

#---------------------------------------------------------------------------------------------------------------

sub get_ip_subnet {
    my $db = shift;
    my $ip = shift;
    if (!$ip) { return; }
    my $ip_aton = StrToIp($ip);
    my $user_subnet = get_record_sql($db, "SELECT * FROM subnets WHERE hotspot=1 or office=1 and ( ".$ip_aton." >= ip_int_start and ".$ip_aton." <= ip_int_stop)");
    return $user_subnet;
}

#---------------------------------------------------------------------------------------------------------------

sub find_mac_in_subnet {
    my $db = shift;
    my $ip = shift;
    my $mac = shift;
    if (!$ip or !$mac) { return; }
    my $ip_subnet = get_ip_subnet($db, $ip);
    if (!$ip_subnet) { return; }
    my @t_auth = get_records_sql($db, "SELECT * FROM user_auth WHERE ip_int>=" . $ip_subnet->{'ip_int_start'} . " and ip_int<=" . $ip_subnet->{'ip_int_stop'} . " and mac='" . $mac . "' and deleted=0 ORDER BY id");
    my $auth_count = 0;
    my $result;
    $result->{'count'} = 0;
    foreach my $row (@t_auth) {
        next if (!$row);
        $auth_count++;
        $result->{'count'} = $auth_count;
        $result->{items}{$auth_count} = $row;
        }
    return $result;
}

#---------------------------------------------------------------------------------------------------------------

sub resurrection_auth {
my $db = shift;
my $ip_record = shift;

my $ip = $ip_record->{'ip'};
my $mac = $ip_record->{'mac'};
my $action = $ip_record->{'type'};
my $hostname = $ip_record->{'hostname_utf8'};
my $client_id = $ip_record->{'client_id'};

if (!exists $ip_record->{ip_aton}) { $ip_record->{ip_aton}=StrToIp($ip); }
if (!exists $ip_record->{hotspot}) { $ip_record->{hotspot}=is_hotspot($db,$ip); }

my $auth_ident = "Found new ip-address: " . $ip;
$auth_ident = $auth_ident . ' ['.$mac .']' if ($mac);
$auth_ident = $auth_ident . ' :: '.$hostname if ($hostname);

my $ip_aton=$ip_record->{ip_aton};

my $timestamp=GetNowTime();

my $record=get_record_sql($db,'SELECT * FROM user_auth WHERE deleted=0 AND ip_int='.$ip_aton.' AND mac="'.$mac.'"');

my $new_record;
$new_record->{last_found}=$timestamp;
$new_record->{arp_found}=$timestamp;

if ($client_id) { $new_record->{'client_id'} = $client_id; }

#auth found?
if ($record->{user_id}) {
    #update timestamp and return
    if ($action=~/^(add|old|del)$/i) {
	    $new_record->{dhcp_action}=$action;
	    $new_record->{created_by}='dhcp';
	    $new_record->{dhcp_time}=$timestamp;
	    if ($hostname) { $new_record->{dhcp_hostname} = $hostname; }
	    }
    update_record($db,'user_auth',$new_record,"id=$record->{id}");
    return $record->{id};
    }

my $user_subnet=$office_networks->match_string($ip);
if ($user_subnet->{static}) {
    db_log_warning($db,"Unknown ip+mac found in static subnet! Abort create record for ip: $ip mac: [".$mac."]");
    return 0;
    }

my $send_alert_update = isNotifyUpdate(get_notify_subnet($db,$ip));
my $send_alert_create = isNotifyCreate(get_notify_subnet($db,$ip));
#my $send_alert_delete = isNotifyDelete(get_notify_subnet($db,$ip));

my $mac_exists=find_mac_in_subnet($db,$ip,$mac);

my $msg = '';

#search changed mac
$record=get_record_sql($db,'SELECT * FROM user_auth WHERE ip_int='.$ip_aton." and deleted=0");
if ($record->{id}) {
    #if found record with same ip but without mac - update it
    if (!$record->{mac}) {
        $msg = $auth_ident. "\nUse auth record with no mac: " . hash_to_text($record);
        db_log_verbose($db,$msg);
        $new_record->{mac}=$mac;
        #disable dhcp for same mac in one ip subnet
        if ($mac_exists and $mac_exists->{'count'}) { $new_record->{dhcp}=0; }
        if ($action=~/^(add|old|del)$/i) {
	        $new_record->{dhcp_action}=$action;
	        $new_record->{dhcp_time}=$timestamp;
                $new_record->{created_by}='dhcp';
	        if ($hostname) { $new_record->{dhcp_hostname} = $hostname; }
                }
        update_record($db,'user_auth',$new_record,"id=$record->{id}");
        sendEmail("WARN! ".get_first_line($msg),$msg,1) if ($send_alert_update);
        return $record->{id};
        }
    #if found record with same ip but another mac - delete old record
    if ($record->{mac}) {
        if (!$ip_record->{hotspot}) {
            my $msg = "For ip: $ip mac change detected! Old mac: [".$record->{mac}."] New mac: [".$mac."]. Disable old auth_id: $record->{id}";
            db_log_warning($db,$msg,$record->{id});
            sendEmail("WARN! ".get_first_line($msg),$msg,1) if ($send_alert_update);
            }
        delete_user_auth($db,$record->{id});
        }
    }

#default user
my $new_user_info=get_new_user_id($db,$ip,$mac,$hostname);
my $new_user_id;
if ($new_user_info->{user_id}) { $new_user_id = $new_user_info->{user_id}; }
if (!$new_user_id) { $new_user_id = new_user($db,$new_user_info); }

if ($mac_exists) {
    #deleting the user's entry if the address belongs to a dynamic group
    foreach my $dup_record_id (keys %{$mac_exists->{items}}) {
        my $dup_record = $mac_exists->{items}{$dup_record_id};
        next if (!$dup_record);
        #remove old dynamic record with some mac
        if ($dup_record->{dynamic}) {
            delete_user_auth($db,$dup_record->{id});
            }
        }
    }

#recheck
$mac_exists=find_mac_in_subnet($db,$ip,$mac);

#disable dhcp for same mac in one ip subnet
if ($mac_exists and $mac_exists->{'count'}) { $new_record->{dhcp}=0; }

#seek old auth with same ip and mac
my $auth_exists=get_count_records($db,'user_auth',"ip_int=".$ip_aton." and mac='".$mac."'");

$new_record->{ip_int}=$ip_aton;
$new_record->{ip}=$ip;
$new_record->{mac}=$mac;
$new_record->{user_id}=$new_user_id;
$new_record->{save_traf}="$save_detail";
$new_record->{deleted}="0";
if ($action=~/^(add|old|del)$/i) {
    $new_record->{dhcp_action}=$action;
    $new_record->{dhcp_time}=$timestamp;
    $new_record->{created_by}='dhcp';
    } else {
    $new_record->{created_by}=$action;
    }

my $cur_auth_id= 0;
if ($auth_exists) {
    #found ->Resurrection old record
    my $resurrection_id = get_id_record($db,'user_auth',"ip_int=".$ip_aton." and mac='".$mac."'");
    $msg = $auth_ident . " Resurrection auth_id: $resurrection_id with ip: $ip and mac: $mac";
    if (!$ip_record->{hotspot}) { db_log_warning($db,$msg); } else { db_log_info($db,$msg); }
    if (update_record($db,'user_auth',$new_record,"id=$resurrection_id")) { $cur_auth_id = $resurrection_id; }
    } else {
    #not found ->create new record
    $msg = $auth_ident ."\n";
    $cur_auth_id = insert_record($db,'user_auth',$new_record);
    if ($cur_auth_id) {
        if (!$ip_record->{hotspot}) { db_log_warning($db,$msg); } else { db_log_info($db,$msg); }
        }
    }
#filter and status
$cur_auth_id=get_id_record($db,'user_auth',"ip='$ip' and mac='$mac' and deleted=0 ORDER BY last_found DESC") if (!$cur_auth_id);
if ($cur_auth_id) {
    my $user_record=get_record_sql($db,"SELECT * FROM user_list WHERE id=".$new_user_id);
    if ($user_record) {
	    my $ou_info = get_record_sql($db,'SELECT * FROM OU WHERE id='.$user_record->{ou_id});
	    if ($ou_info and $ou_info->{'dynamic'}) {
                    # Устанавливаем значение по умолчанию, если не задано
                    if (!$ou_info->{'life_duration'}) { 
                            $ou_info->{'life_duration'} = 24.0;  # Явно указываем дробное число
                            }
                    my $now = DateTime->now(time_zone => 'local');
                    # Разбиваем life_duration на часы и минуты (для дробного значения)
                    my $hours = int($ou_info->{'life_duration'});  # Целая часть (часы)
                    my $minutes = ($ou_info->{'life_duration'} - $hours) * 60;  # Дробная часть → минуты
                    # Создаём продолжительность с учётом дробных часов (в виде часов + минут)
                    my $duration = DateTime::Duration->new( hours   => $hours, minutes => $minutes);
                    my $end_life = $now + $duration;
                    $new_record->{'dynamic'} = 1;
                    $new_record->{'end_life'} = $end_life->strftime('%Y-%m-%d %H:%M:%S');
		    }
	    $new_record->{ou_id}=$user_record->{ou_id};
	    $new_record->{description}=$user_record->{fio};
	    $new_record->{filter_group_id}=$user_record->{filter_group_id};
	    $new_record->{queue_id}=$user_record->{queue_id};
	    $new_record->{enabled}="$user_record->{enabled}";
            update_record($db,'user_auth',$new_record,"id=$cur_auth_id");
	    }
    db_log_warning($db, $msg, $cur_auth_id);
    sendEmail("WARN! ".get_first_line($msg),$msg."\n".record_to_txt($db,'user_auth',$cur_auth_id),1) if ($send_alert_create);
    } else { return; }
return $cur_auth_id;
}

#---------------------------------------------------------------------------------------------------------------

sub new_auth {
my $db = shift;
my $ip = shift;
my $ip_aton=StrToIp($ip);
my $record=get_record_sql($db,'SELECT id FROM user_auth WHERE deleted=0 AND ip_int='.$ip_aton);
if ($record->{id}) { return $record->{id}; }
#default user
my $new_user_info=get_new_user_id($db,$ip,undef,undef);
my $new_user_id;
if ($new_user_info->{user_id}) { $new_user_id = $new_user_info->{user_id}; }
if ($new_user_info->{ou_id}) { $new_user_id = new_user($db,$new_user_info); }

if (is_dynamic_ou($db,$new_user_info->{ou_id})) {
    db_log_debug($db,"The ip-address $ip belongs to a dynamic group - ignore it.");
    return;
    }

my $send_alert = isNotifyCreate(get_notify_subnet($db,$ip));
my $user_record=get_record_sql($db,"SELECT * FROM user_list WHERE id=".$new_user_id);
my $timestamp=GetNowTime();
my $new_record;
$new_record->{ip_int}=$ip_aton;
$new_record->{ip}=$ip;
$new_record->{user_id}=$new_user_id;
$new_record->{save_traf}="$save_detail";
$new_record->{deleted}="0";
$new_record->{created_by}='netflow';
$new_record->{ou_id}=$user_record->{ou_id};
$new_record->{filter_group_id}=$user_record->{filter_group_id};
$new_record->{queue_id}=$user_record->{queue_id};
$new_record->{enabled}="$user_record->{enabled}";
if ($user_record->{fio}) { $new_record->{description}=$user_record->{fio}; }

my $cur_auth_id=insert_record($db,'user_auth',$new_record);
if ($cur_auth_id) {
    my $msg = "New ip created by netflow! ip: $ip";
    db_log_warning($db,$msg,$cur_auth_id);
    sendEmail("WARN! ".get_first_line($msg),$msg,1) if ($send_alert);
    }
return $cur_auth_id;
}

#--------------------------------------------------------------------------------------------------------------

sub get_dynamic_ou {
my $db = shift;
my @dynamic=();
my @ou_list = get_records_sql($db,"SELECT id FROM OU WHERE dynamic = 1");
foreach my $group (@ou_list) {
    next if (!$group);
    push(@dynamic,$group->{id});
    }
return wantarray ? @dynamic : \@dynamic;
}

#--------------------------------------------------------------------------------------------------------------

sub get_default_ou {
my $db = shift;
my @dynamic=();
my $ou = get_record_sql($db,"SELECT id FROM OU WHERE default_users = 1");
if (!$ou) { push(@dynamic,0); } else { push(@dynamic,$ou->{'id'}); }
$ou = get_record_sql($db,"SELECT id FROM OU WHERE default_hotspot = 1");
if ($ou) { push(@dynamic,$ou->{id}); }
return wantarray ? @dynamic : \@dynamic;
}

#--------------------------------------------------------------------------------------------------------------

sub is_dynamic_ou {
my $db = shift;
my $ou_id = shift;
my @dynamic=get_dynamic_ou($db);
if (in_array(\@dynamic,$ou_id)) { return 1; }
return 0;
}

#--------------------------------------------------------------------------------------------------------------

sub is_default_ou {
my $db = shift;
my $ou_id = shift;
my @dynamic=get_default_ou($db);
if (in_array(\@dynamic,$ou_id)) { return 1; }
return 0;
}

#---------------------------------------------------------------------------------------------------------------

sub get_subnets_ref {
my $db = shift;
my @list=get_records_sql($db,'SELECT * FROM subnets ORDER BY ip_int_start');
my $list_ref;
foreach my $net (@list) {
next if (!$net->{subnet});
$list_ref->{$net->{subnet}}=$net;
}
return $list_ref;
}

#---------------------------------------------------------------------------------------------------------------

sub get_device_by_ip {
my $db = shift;
my $ip = shift;
my $netdev=get_record_sql($db,'SELECT * FROM devices WHERE ip="'.$ip.'"');
if ($netdev and $netdev->{id}>0) { return $netdev; }
my $auth_rec=get_record_sql($db,'SELECT user_id FROM user_auth WHERE ip="'.$ip.'" and deleted=0');
if ($auth_rec and $auth_rec->{user_id}>0) {
    $netdev=get_record_sql($db,'SELECT * FROM devices WHERE user_id='.$auth_rec->{user_id});
    return $netdev;
    }
return;
}

#---------------------------------------------------------------------------------------------------------------

sub recalc_quotes {

my $db = shift;
my $calc_id = shift || $$;

return if (!get_option($db,54));

clean_variables($db);

return if (Get_Variable($db,'RECALC'));

my $timeshift = get_option($db,55);

Set_Variable($db,'RECALC',$calc_id,time()+$timeshift*60);

my $now = DateTime->now(time_zone=>'local');
my $day_start = $db->quote($now->ymd("-")." 00:00:00");
my $day_dur = DateTime::Duration->new( days => 1 );
my $tomorrow = $now+$day_dur;
my $day_stop = $db->quote($tomorrow->ymd("-")." 00:00:00");

$now->set(day=>1);
my $month_start=$db->quote($now->ymd("-")." 00:00:00");
my $month_dur = DateTime::Duration->new( months => 1 );
my $next_month = $now + $month_dur;
$next_month->set(day=>1);
my $month_stop = $db->quote($next_month->ymd("-")." 00:00:00");

#get user limits
my $user_auth_list_sql="SELECT A.id as auth_id, U.id, U.day_quota, U.month_quota, A.day_quota as auth_day, A.month_quota as auth_month FROM user_auth as A,user_list as U WHERE A.deleted=0 ORDER by user_id";
my @authlist_ref = get_records_sql($db,$user_auth_list_sql);
my %user_stats;
my %auth_info;
foreach my $row (@authlist_ref) {
    $auth_info{$row->{auth_id}}{user_id}=$row->{id};
    $auth_info{$row->{auth_id}}{day_limit}=$row->{auth_day};
    $auth_info{$row->{auth_id}}{month_limit}=$row->{auth_month};
    $auth_info{$row->{auth_id}}{day}=0;
    $auth_info{$row->{auth_id}}{month}=0;
    $user_stats{$row->{id}}{day_limit}=$row->{day_quota};
    $user_stats{$row->{id}}{month_limit}=$row->{month_quota};
    $user_stats{$row->{id}}{day}=0;
    $user_stats{$row->{id}}{month}=0;
}

#recalc quotes - global
#day
my $day_sql="SELECT user_stats.auth_id, SUM( byte_in + byte_out ) AS traf_all FROM user_stats
WHERE user_stats.ts>= $day_start AND user_stats.ts< $day_stop GROUP BY user_stats.auth_id";
my @day_stats = get_records_sql($db,$day_sql);
foreach my $row (@day_stats) {
    my $user_id=$auth_info{$row->{auth_id}}{user_id};
    $auth_info{$row->{auth_id}}{day}=$row->{traf_all};
    $user_stats{$user_id}{day}+=$row->{traf_all};
}

#month
my $month_sql="SELECT user_stats.auth_id, SUM( byte_in + byte_out ) AS traf_all FROM user_stats
WHERE user_stats.ts>= $month_start AND user_stats.ts< $month_stop GROUP BY user_stats.auth_id";
my @month_stats = get_records_sql($db,$month_sql);
foreach my $row (@month_stats) {
    my $user_id=$auth_info{$row->{auth_id}}{user_id};
    $auth_info{$row->{auth_id}}{month}=$row->{traf_all};
    $user_stats{$user_id}{month}+=$row->{traf_all};
}

foreach my $auth_id (keys %auth_info) {
next if (!$auth_info{$auth_id}{day_limit});
next if (!$auth_info{$auth_id}{month_limit});
my $day_limit=$auth_info{$auth_id}{day_limit}*$KB*$KB;
my $month_limit=$auth_info{$auth_id}{month_limit}*$KB*$KB;
my $blocked_d=($auth_info{$auth_id}{day}>$day_limit);
my $blocked_m=($auth_info{$auth_id}{month}>$month_limit);
if ($blocked_d or $blocked_m) {
    my $history_msg;
    if ($blocked_d) { $history_msg=printf "Day quota limit found for auth_id: $auth_id - Current: %d Max: %d",$auth_info{$auth_id}{day},$day_limit; }
    if ($blocked_m) { $history_msg=printf "Month quota limit found for auth_id: $auth_id - Current: %d Max: %d",$auth_info{$auth_id}{month},$month_limit; }
    do_sql($db,"UPDATE user_auth set blocked=1, changed=1 where id=$auth_id");
    db_log_verbose($db,$history_msg);
    }
}

foreach my $user_id (keys %user_stats) {
next if (!$user_stats{$user_id}{day_limit});
next if (!$user_stats{$user_id}{month_limit});
my $day_limit=$user_stats{$user_id}{day_limit}*$KB*$KB;
my $month_limit=$user_stats{$user_id}{month_limit}*$KB*$KB;
my $blocked_d=($user_stats{$user_id}{day}>$day_limit);
my $blocked_m=($user_stats{$user_id}{month}>$month_limit);
if ($blocked_d or $blocked_m) {
    my $history_msg;
    if ($blocked_d) { $history_msg=printf "Day quota limit found for user_id: $user_id - Current: %d Max: %d",$user_stats{$user_id}{day},$day_limit; }
    if ($blocked_m) { $history_msg=printf "Month quota limit found for user_id: $user_id - Current: %d Max: %d",$user_stats{$user_id}{month},$month_limit; }
    do_sql($db,"UPDATE User_user set blocked=1 where id=$user_id");
    do_sql($db,"UPDATE user_auth set blocked=1, changed=1 where user_id=$user_id");
    db_log_verbose($db,$history_msg);
    }
}
Del_Variable($db,'RECALC');
}

#--------------------------------------------------------------------------------

sub process_dhcp_request {

my ($db, $type, $mac, $ip, $hostname, $client_id, $circuit_id, $remote_id) = @_;

return if (!$type);
return if ($type!~/(old|add|del)/i);

my $client_hostname='';
if ($hostname and ($hostname ne "undef" or $hostname !~ /UNDEFINED/i)) { $client_hostname=$hostname; }

my $auth_network = $office_networks->match_string($ip);
if (!$auth_network) {
    log_error("Unknown network in dhcp request! IP: $ip");
    return;
    }

if (!$circuit_id) { $circuit_id=''; }
if (!$client_id) { $client_id = ''; }
if (!$remote_id) { $remote_id = ''; }

my $timestamp=time();

my $ip_aton=StrToIp($ip);
$mac=mac_splitted(isc_mac_simplify($mac));

my $dhcp_event_time = GetNowTime($timestamp);

my $dhcp_record;
$dhcp_record->{'mac'}=$mac;
$dhcp_record->{'ip'}=$ip;
$dhcp_record->{'ip_aton'}=$ip_aton;
$dhcp_record->{'hostname'}=$client_hostname;
$dhcp_record->{'network'}=$auth_network;
$dhcp_record->{'type'}=$type;
$dhcp_record->{'hostname_utf8'}=$client_hostname;
$dhcp_record->{'ts'} = $timestamp;
$dhcp_record->{'last_time'} = time();
$dhcp_record->{'circuit_id'} = $circuit_id;
$dhcp_record->{'client_id'} = $client_id;
$dhcp_record->{'remote_id'} = $remote_id;
$dhcp_record->{'hotspot'}=is_hotspot($dbh,$dhcp_record->{ip});

#search actual record
my $auth_record = get_record_sql($db,'SELECT * FROM user_auth WHERE ip="'.$dhcp_record->{ip}.'" and mac="'.$mac.'" and deleted=0 ORDER BY last_found DESC');

#if record not found and type del => next event
if (!$auth_record and $type eq 'del') { return; }

#if record not found - create it
if (!$auth_record and $type=~/(add|old)/i) {
#        db_log_warning($db,"Record for dhcp request type: ".$type." ip=".$dhcp_record->{ip}." and mac=".$mac." does not exists!");
        my $res_id = resurrection_auth($db,$dhcp_record);
        if (!$res_id) {  db_log_error($db,"Error creating an ip address record for ip=".$dhcp_record->{ip}." and mac=".$mac."!");  return; }
        $auth_record = get_record_sql($db,'SELECT * FROM user_auth WHERE id='.$res_id);
        db_log_info($db,"Check for new auth. Found id: $res_id",$res_id);
        }

my $auth_id = $auth_record->{id};
my $auth_ou_id = $auth_record->{ou_id};

$dhcp_record->{'auth_id'} = $auth_id;
$dhcp_record->{'auth_ou_id'} = $auth_ou_id;

log_debug(uc($type).">>");
log_debug("MAC:        ".$dhcp_record->{'mac'});
log_debug("IP:         ".$dhcp_record->{'ip'});
log_debug("CIRCUIT-ID: ".$dhcp_record->{circuit_id});
log_debug("REMOTE-ID:  ".$dhcp_record->{remote_id});
log_debug("HOSTNAME:   ".$dhcp_record->{'hostname'});
log_debug("TYPE:       ".$dhcp_record->{'type'});
log_debug("TIME:       ".$dhcp_event_time);
log_debug("AUTH_ID:    ".$auth_id);
log_debug("END GET");

update_dns_record_by_dhcp($db,$dhcp_record,$auth_record);

if ($type=~/add/i and $dhcp_record->{hostname_utf8}) {
                my $auth_rec;
                $auth_rec->{dhcp_hostname} = $dhcp_record->{hostname_utf8};
                $auth_rec->{dhcp_time}=$dhcp_event_time;
                $auth_rec->{arp_found}=$dhcp_event_time;
                $auth_rec->{created_by}='dhcp';
                db_log_verbose($db,"Add lease by dhcp event for dynamic clients id: $auth_id ip: $dhcp_record->{ip}",$auth_id);
                update_record($db,'user_auth',$auth_rec,"id=$auth_id");
                }

if ($type=~/old/i) {
                my $auth_rec;
                $auth_rec->{dhcp_action}=$type;
                $auth_rec->{dhcp_time}=$dhcp_event_time;
                $auth_rec->{created_by}='dhcp';
                $auth_rec->{arp_found}=$dhcp_event_time;
                db_log_verbose($db,"Update lease by dhcp event for dynamic clients id: $auth_id ip: $dhcp_record->{ip}",$auth_id);
                update_record($db,'user_auth',$auth_rec,"id=$auth_id");
                }

if ($type=~/del/i and $auth_id) {
                if ($auth_record->{dhcp_time} =~ /([0-9]{4})-([0-9]{2})-([0-9]{2}) ([0-9]{2}):([0-9]{2}):([0-9]{2})/) {
                    my $d_time = mktime($6,$5,$4,$3,$2-1,$1-1900);
                    if (time()-$d_time>60 and (is_dynamic_ou($db,$auth_ou_id) or is_default_ou($db,$auth_ou_id))) {
                        db_log_info($db,"Remove user ip record by dhcp release event for dynamic clients id: $auth_id ip: $dhcp_record->{ip}",$auth_id);
                        my $auth_rec;
                        $auth_rec->{dhcp_action}=$type;
                        $auth_rec->{dhcp_time}=$dhcp_event_time;
                        update_record($db,'user_auth',$auth_rec,"id=$auth_id");
                        #remove user auth record if it belongs to the default pool or it is dynamic
                        if (is_default_ou($db,$auth_ou_id) or (is_dynamic_ou($db,$auth_ou_id) and $auth_record->{dynamic})) {
                                delete_user_auth($db,$auth_id);
                                my $u_count=get_count_records($db,'user_auth','deleted=0 and user_id='.$auth_record->{'user_id'});
                                if (!$u_count) { delete_user($db,$auth_record->{'user_id'}); }
                                }
                        }
                    }
                }

if ($dhcp_record->{hotspot} and $ignore_hotspot_dhcp_log) { return $dhcp_record; }

if ($ignore_update_dhcp_event and $type=~/old/i) { return $dhcp_record; }

my $dhcp_log;
if (!$auth_id) { $auth_id=0; }
$dhcp_log->{'auth_id'} = $auth_id;
$dhcp_log->{'ip'} = $dhcp_record->{'ip'};
$dhcp_log->{'ip_int'} = $dhcp_record->{'ip_aton'};
$dhcp_log->{'mac'} = $dhcp_record->{'mac'};
$dhcp_log->{'action'} = $type;
$dhcp_log->{'dhcp_hostname'} = $dhcp_record->{'hostname_utf8'};
$dhcp_log->{'ts'} = $dhcp_event_time;
$dhcp_log->{'circuit_id'} = $circuit_id;
$dhcp_log->{'client_id'} = $client_id;
$dhcp_log->{'remote_id'} = $remote_id;

insert_record($db,'dhcp_log',$dhcp_log);

return $dhcp_record;
}

1;
}
