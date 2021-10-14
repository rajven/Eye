package Rstat::mysql;

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use strict;
use English;
use FindBin '$Bin';
use lib "$Bin";
use base 'Exporter';
use vars qw(@EXPORT @ISA);
use Rstat::config;
use Rstat::main;
use Net::Patricia;
use Rstat::net_utils;
use Data::Dumper;
use DateTime;
use POSIX;
use DBI;

our @ISA = qw(Exporter);

our @EXPORT = qw(
batch_db_sql
db_log_warning
db_log_debug
db_log_error
db_log_info
db_log_verbose
delete_record
do_sql
Get_Variable
Set_Variable
Del_Variable
get_count_records
get_record_sql
get_records_sql
get_device_by_ip
get_diff_rec
get_id_record
get_new_user_id
is_hotspot
GetNowTime
GetUnixTimeByStr
GetTimeStrByUnixTime
get_option
get_subnets_ref
init_db
init_option
insert_record
IpToStr
resurrection_auth
new_auth
StrToIp
update_dns_record
update_ad_hostname
update_record
write_db_log
set_changed
recalc_quotes
clean_variables

$add_rules
$L_WARNING
$L_INFO
$L_DEBUG
$L_ERROR
$L_VERBOSE
);

BEGIN
{

#---------------------------------------------------------------------------------------------------------------

our $add_rules;

our $L_ERROR = 0;
our $L_WARNING = 1;
our $L_INFO = 2;
our $L_VERBOSE = 3;
our $L_DEBUG = 255;

our %acl_fields = (
'ip' => '1',
'ip_int' => '1',
'enabled'=>'1',
'dhcp'=>'1',
'filter_group_id'=>'1',
'deleted'=>'1',
'dhcp_acl'=>'1',
'queue_id'=>'1',
'mac'=>'1',
'blocked'=>'1'
);

#---------------------------------------------------------------------------------------------------------------

sub StrToIp{
return unpack('N',pack('C4',split(/\./,$_[0])));
}

#---------------------------------------------------------------------------------------------------------------

sub IpToStr{
my $nIP = shift;
my $res = (($nIP>>24) & 255) .".". (($nIP>>16) & 255) .".". (($nIP>>8) & 255) .".". ($nIP & 255);
return $res;
}

#---------------------------------------------------------------------------------------------------------------

sub batch_db_sql {
my $db=shift;
my $batch_sql=shift;
return if (!$db);
$db->{AutoCommit} = 0;
my $apply = 0;
my $sth;
if (ref($batch_sql) eq 'ARRAY') {
    foreach my $sSQL (@$batch_sql) {
        next if (!$sSQL);
        print "$sSQL\n";
        $sth = $db->prepare($sSQL);
        $sth->execute;
        $apply = 1;
        }
    } else {
    my @msg = split("\n",$batch_sql);
    foreach my $sSQL (@msg) {
        next if (!$sSQL);
        $sth = $db->prepare($sSQL);
        $sth->execute;
        $apply = 1;
        }
    }
if ($apply) { $sth->finish; }
$db->{AutoCommit} = 1;
}

#---------------------------------------------------------------------------------------------------------------

sub do_sql {
my $db=shift;
my $sql=shift;
return if (!$db);
return if (!$sql);
if ($sql!~/^select /i) { db_log_debug($db,$sql); }
my $sql_prep = $db->prepare($sql);
my $sql_ref;
if ( !defined $sql_prep ) { die "Cannot prepare statement: $DBI::errstr\n"; }
$sql_prep->execute;
if ($sql=~/^insert/i) { $sql_ref = $sql_prep->{mysql_insertid}; }
if ($sql=~/^select /i) { $sql_ref = $sql_prep->fetchall_arrayref(); };
$sql_prep->finish();
return $sql_ref;
}

#---------------------------------------------------------------------------------------------------------------

sub write_db_log {
my $db=shift;
my $msg=shift;
my $level = shift || $L_VERBOSE;
my $auth_id = shift || 0;
return if (!$db);
return if (!$msg);
$msg=~s/[\'\"]//g;
my $db_log = 0;
my $history_sql="INSERT INTO syslog(customer,message,level,auth_id) VALUES(".$db->quote($MY_NAME).",".$db->quote($msg).",$level,$auth_id)";
if ($level eq $L_ERROR and $log_level >= $L_ERROR) { log_error($msg); $db_log = 1; }
if ($level eq $L_WARNING and $log_level >= $L_WARNING) { log_warning($msg); $db_log = 1; }
if ($level eq $L_INFO and $log_level >= $L_INFO) { log_info($msg); $db_log = 1; }
if ($level eq $L_DEBUG and $log_level >= $L_DEBUG) { log_debug($msg); $db_log = 1; }
if ($db_log) {
    my $history_rf=$db->prepare($history_sql);
    $history_rf->execute;
    }
}

#---------------------------------------------------------------------------------------------------------------

sub db_log_debug {
my $db = shift;
my $msg = shift;
my $id = shift;
if ($debug) { write_db_log($db,$msg,$L_DEBUG,$id); }
}

#---------------------------------------------------------------------------------------------------------------

sub db_log_error {
my $db = shift;
my $msg = shift;
if ($log_level >= $L_ERROR) {
    sendEmail("ERROR! ".substr($msg,0,30),$msg,1);
    write_db_log($db,$msg,$L_ERROR);
    }
}

#---------------------------------------------------------------------------------------------------------------

sub db_log_info {
my $db = shift;
my $msg = shift;
if ($log_level >= $L_INFO) { write_db_log($db,$msg,$L_INFO); }
}

#---------------------------------------------------------------------------------------------------------------

sub db_log_verbose {
my $db = shift;
my $msg = shift;
if ($log_level >= $L_VERBOSE) { write_db_log($db,$msg,$L_VERBOSE); }
}

#---------------------------------------------------------------------------------------------------------------

sub db_log_warning {
my $db = shift;
my $msg = shift;
if ($log_level >= $L_WARNING) {
    sendEmail("WARN! ".substr($msg,0,30),$msg,1);
    write_db_log($db,$msg,$L_WARNING);
    }
}

#---------------------------------------------------------------------------------------------------------------

sub init_db {
# Create new database handle. If we can't connect, die()
my $db = DBI->connect("dbi:mysql:database=$DBNAME;host=$DBHOST","$DBUSER","$DBPASS");
if ( !defined $db ) { die "Cannot connect to mySQL server: $DBI::errstr\n"; }
$db->do('SET NAMES utf8');
$db->{mysql_auto_reconnect} = 1;
return $db;
}

#---------------------------------------------------------------------------------------------------------------

sub get_count_records {
my $db = shift;
my $table = shift;
my $filter = shift;
my $result = 0;
return $result if (!$db);
return $result if (!$table);
my $sSQL='SELECT COUNT(*) as rec_cnt FROM '.$table;
if ($filter) { $sSQL=$sSQL." where ".$filter; }
my $record = get_record_sql($db,$sSQL);
if ($record->{rec_cnt}) { $result = $record->{rec_cnt}; }
return $result;
}

#---------------------------------------------------------------------------------------------------------------

sub get_id_record {
my $db = shift;
my $table = shift;
my $filter = shift;
my $result = 0;
return $result if (!$db);
return $result if (!$table);
my $record = get_record_sql($db,"SELECT id FROM $table WHERE $filter");
if ($record->{id}) { $result = $record->{id}; }
return $result;
}

#---------------------------------------------------------------------------------------------------------------

sub get_records_sql {
my $db = shift;
my $table = shift;
my @result;
return @result if (!$db);
return @result if (!$table);
my $list = $db->prepare( $table );
if ( !defined $list ) { die "Cannot prepare statement: $DBI::errstr\n"; }
$list->execute;
while(my $row_ref = $list ->fetchrow_hashref) {
push(@result,$row_ref);
}
$list->finish();
return @result;
}

#---------------------------------------------------------------------------------------------------------------

sub get_record_sql {
my $db = shift;
my $tsql = shift;
my @result;
return @result if (!$db);
return @result if (!$tsql);
my $list = $db->prepare( $tsql . ' LIMIT 1' );
if ( !defined $list ) { die "Cannot prepare statement: $DBI::errstr\n"; }
$list->execute;
my $row_ref = $list ->fetchrow_hashref;
$list->finish();
return $row_ref;
}

#---------------------------------------------------------------------------------------------------------------

sub get_diff_rec {
my $db = shift;
my $table = shift;
my $value = shift;
my $filter = shift;
return if (!$db);
return if (!$table);
return if (!$filter);
my $old_value = get_record_sql($db,"SELECT * FROM $table WHERE $filter");
my $result='';
foreach my $field (keys %$value) {
    if (!$value->{$field}) { $value->{$field}=''; }
    if (!$old_value->{$field}) { $old_value->{$field}=''; }
    if ($value->{$field}!~/^$old_value->{$field}$/) { $result = $result." $field => $value->{$field} (old: $old_value->{$field}),"; }
    }
$result=~s/,$//;
return $result;
}

#---------------------------------------------------------------------------------------------------------------

sub update_record {
my $db = shift;
my $table = shift;
my $record = shift;
my $filter = shift;
return if (!$db);
return if (!$table);
return if (!$filter);
my $old_record = get_record_sql($db,"SELECT * FROM $table WHERE $filter");
my $diff='';
my $change_str='';
my $found_changed=0;
my $auth_id = 0;

if ($table eq "User_auth") {
    $auth_id = $old_record->{'id'};
    foreach my $field (keys %$record) {
        next if (!exists $acl_fields{$field});
        $record->{changed}="1";
        }
    }

foreach my $field (keys %$record) {
    if (!defined $record->{$field}) { $record->{$field}=''; }
    if (!defined $old_record->{$field}) { $old_record->{$field}=''; }
    my $old_value = quotemeta($old_record->{$field});
    my $new_value = $record->{$field};
    $new_value=~s/\'//g;
    $new_value=~s/\"//g;
    if ($new_value!~/^$old_value$/) {
	$diff = $diff." $field => $record->{$field} (old: $old_record->{$field}),";
	$change_str = $change_str." `$field`=".$db->quote($record->{$field}).",";
	$found_changed++;
	}
}

if ($found_changed) {
    $change_str=~s/\,$//;
    $diff=~s/\,$//;
    if ($table eq 'User_auth') { $change_str .= ", `changed_time`='".GetNowTime()."'"; }
    my $sSQL = "UPDATE $table SET $change_str WHERE $filter";
    db_log_debug($db,'Change table '.$table.' for '.$filter.' set: '.$diff,$auth_id);
    do_sql($db,$sSQL);
    } else {
    db_log_debug($db,'Nothing change. Skip update.');
    }
}

#---------------------------------------------------------------------------------------------------------------

sub insert_record {
my $db = shift;
my $table = shift;
my $record = shift;
return if (!$db);
return if (!$table);
my $change_str='';
my $fields='';
my $values='';
my $new_str='';

if ($table eq 'User_auth') {
    foreach my $field (keys %$record) {
        next if (!exists $acl_fields{$field});
        $record->{changed}="1";
        }
    }

foreach my $field (keys %$record) {
    if (!defined $record->{$field}) { $record->{$field}=''; }
    my $new_value = $record->{$field};
    $new_value=~s/\'//g;
    $new_value=~s/\"//g;
    $fields = $fields."`$field`,";
    $values = $values." ".$db->quote($new_value).",";
    $new_str = $new_str." $field => $new_value,";
    }
$fields=~s/,$//;
$values=~s/,$//;
$new_str=~s/,$//;
my $sSQL = "INSERT INTO $table($fields) VALUES($values)";
my $result = do_sql($db,$sSQL);
if ($result) { $new_str='id: '.$result.' '.$new_str; }
db_log_debug($db,'Add record to table '.$table.' '.$new_str);
return $result;
}

#---------------------------------------------------------------------------------------------------------------

sub delete_record {
my $db = shift;
my $table = shift;
my $filter = shift;
return if (!$db);
return if (!$table);
return if (!$filter);
my $old_record = get_record_sql($db,"SELECT * FROM $table WHERE $filter");
my $diff='';
foreach my $field (keys %$old_record) {
    if (!$old_record->{$field}) { $old_record->{$field}=''; }
    $diff = $diff." $field => $old_record->{$field},";
    }
$diff=~s/,$//;

db_log_debug($db,'Delete record from table  '.$table.' value: '.$diff);

#never delete user ip record!
if ($table eq 'User_auth') {
    my $sSQL = "UPDATE User_auth SET deleted=1, changed_time='".GetNowTime()."' WHERE ".$filter;
    do_sql($db,$sSQL);
    } else {
    my $sSQL = "DELETE FROM ".$table." WHERE ".$filter;
    do_sql($db,$sSQL);
    }
}

#---------------------------------------------------------------------------------------------------------------

sub GetNowTime {
my ($sec,$min,$hour,$day,$month,$year,$zone) = localtime(time());
$month += 1;
$year += 1900;
my $now_str=sprintf "%04d-%02d-%02d %02d:%02d:%02d",$year,$month,$day,$hour,$min,$sec;
return $now_str;
}

#---------------------------------------------------------------------------------------------------------------

sub is_hotspot {
my $db = shift;
my $ip  = shift;
my $users = new Net::Patricia;
#check hotspot
my @ip_rules = get_records_sql($db,'SELECT * FROM subnets WHERE hotspot=1 AND LENGTH(subnet)>0');
foreach my $row (@ip_rules) { $users->add_string($row->{subnet},$config_ref{hotspot_user_id}); }
if ($users->match_string($ip)) { return 1; }
return 0;
}

#---------------------------------------------------------------------------------------------------------------

sub get_new_user_id {
my $db = shift;
my $ip  = shift;
my $mac = shift;
my $hostname = shift;
#check ip
if (defined $ip and $ip) {
    my $users = new Net::Patricia;
    #check hotspot
    my @ip_rules = get_records_sql($db,'SELECT * FROM subnets WHERE hotspot=1 AND LENGTH(subnet)>0');
    foreach my $row (@ip_rules) { $users->add_string($row->{subnet},$config_ref{hotspot_user_id}); }
    if ($users->match_string($ip)) { return $users->match_string($ip); }
    #check ip rules
    @ip_rules = get_records_sql($db,'SELECT * FROM auth_rules WHERE type=1 and LENGTH(rule)>0');
    foreach my $row (@ip_rules) { $users->add_string($row->{rule},$row->{user_id}); }
    if ($users->match_string($ip)) { return $users->match_string($ip); }
    }

#check mac
if (defined $mac and $mac) {
    my @user_rules=get_records_sql($db,'SELECT * FROM auth_rules WHERE type=2 AND LENGTH(rule)>0');
    foreach my $user (@user_rules) {
        if ($mac=~/$user->{rule}/i) { return $user->{user_id}; }
        }
    }

#check hostname
if (defined $hostname and $hostname) {
    my @user_rules=get_records_sql($db,'SELECT * FROM auth_rules WHERE type=3 AND LENGTH(rule)>0');
    foreach my $user (@user_rules) {
        if ($hostname=~/$user->{rule}/i) { return $user->{user_id}; }
        }
    }
return $default_user_id;
}

#---------------------------------------------------------------------------------------------------------------

sub set_changed {
my $db = shift;
my $id = shift;
return if (!$db or !$id);
my $update_record;
$update_record->{changed}=1;
update_record($db,'User_auth',$update_record,"id=$id");
}

#---------------------------------------------------------------------------------------------------------------

sub update_dns_record {

my $hdb = shift;
my $dhcp_record = shift;
my $auth_record = shift;

my $ad_zone = get_option($hdb,33);
my $ad_dns = get_option($hdb,3);

$update_hostname_from_dhcp = get_option($hdb,46) || 0;
my $subnets_dhcp = get_subnets_ref($hdb);
my $enable_ad_dns_update = ($ad_zone and $ad_dns and $update_hostname_from_dhcp);

log_debug("Subnet: $dhcp_record->{network}");

log_debug("DNS update flags - zone: $ad_zone dns: $ad_dns config: $update_hostname_from_dhcp subnet: $subnets_dhcp->{$dhcp_record->{network}}->{dhcp_update_hostname}");

my $maybe_update_dns=(($dhcp_record->{type}=~/add/i or $dhcp_record->{type}=~/old/i) and $dhcp_record->{hostname_utf8} and $dhcp_record->{hostname_utf8} !~/UNDEFINED/i and $enable_ad_dns_update and $subnets_dhcp->{$dhcp_record->{network}}->{dhcp_update_hostname});

if (!$maybe_update_dns) {
    db_log_debug($hdb,"FOUND Auth_id: $auth_record->{id}. DNS update don't needed.");
    return 0;
    }

log_debug("DNS update enabled.");
#update dns block
my $fqdn_static;
if ($auth_record->{dns_name}) {
    $fqdn_static=lc($auth_record->{dns_name});
    if ($fqdn_static!~/$ad_zone$/i) {
            $fqdn_static=~s/\.$//;
            $fqdn_static=lc($fqdn_static.'.'.$ad_zone);
            }
    }

my $fqdn=lc(trim($dhcp_record->{hostname_utf8}));
if ($fqdn!~/$ad_zone$/i) {
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
    my @dns_record=ResolveNames($fqdn_static);
    $static_exists = (scalar @dns_record>0);
    if ($static_exists) {
            $static_ref = join(' ',@dns_record);
            foreach my $dns_a (@dns_record) {
                if ($dns_a=~/^$dhcp_record->{ip}$/) { $static_ok = $dns_a; }
                }
            }
    } else { $static_ok = 1; }

if ($fqdn ne '') {
    my @dns_record=ResolveNames($fqdn);
    $dynamic_exists = (scalar @dns_record>0);
    if ($dynamic_exists) {
            $dynamic_ref = join(' ',@dns_record);
            foreach my $dns_a (@dns_record) {
                if ($dns_a=~/^$dhcp_record->{ip}$/) { $dynamic_ok = $dns_a; }
                }
            }
    }

if ($fqdn_static ne '') {
    if (!$static_ok) {
        db_log_info($hdb,"Static record mismatch! Expected $fqdn_static => $dhcp_record->{ip}, recivied: $static_ref");
        if (!$static_exists) {
                db_log_info($hdb,"Static dns hostname defined but not found. Create it ($fqdn_static => $dhcp_record->{ip})!");
                update_ad_hostname($fqdn_static,$dhcp_record->{ip},$ad_zone,$ad_dns,$hdb);
                }
        } else { db_log_debug($hdb,"Static record for $fqdn_static [$static_ok] correct."); }
    }

if ($fqdn ne '' and $dynamic_ok ne '') { db_log_debug($hdb,"Dynamic record for $fqdn [$dynamic_ok] correct. No changes required."); }
if ($fqdn ne '' and !$dynamic_ok) {
    #log only to file!!!
    log_error($hdb,"Dynamic record mismatch! Expected: $fqdn => $dhcp_record->{ip}, recivied: $dynamic_ref. Checking the status.");
    #check exists hostname
    my $another_hostname_exists = 0;
    my $hostname_filter = ' LOWER(dns_name)="'.lc($dhcp_record->{hostname_utf8}).'"';
    if ($fqdn_static ne '' and $fqdn !~/$fqdn_static/) { $hostname_filter = $hostname_filter . ' or LOWER(dns_name)="'.lc($auth_record->{dns_name}).'"'; }
    #check exists another records with some static hostname
    my $name_record = get_record_sql($hdb,'SELECT * FROM User_auth WHERE id<>'.$auth_record->{id}.' and deleted=0 and ('.$hostname_filter.') ORDER BY last_found DESC');
    if ($name_record->{id}) { $another_hostname_exists = 1; }
    if (!$another_hostname_exists) {
            if ($fqdn_static and $fqdn_static ne '') {
                    if ($fqdn_static!~/$fqdn/) {
                        db_log_info($hdb,"Hostname from dhcp request $fqdn differs from static dns hostanme $fqdn_static. Ignore dynamic binding!");
#                        update_ad_hostname($fqdn,$dhcp_record->{ip},$ad_zone,$ad_dns,$hdb);
                        }
                    } else {
        	    db_log_info($hdb,"Static dns hostname not defined. Create dns record by dhcp request. $fqdn => $dhcp_record->{ip}");
        	    update_ad_hostname($fqdn,$dhcp_record->{ip},$ad_zone,$ad_dns,$hdb);
        	    }
	    } else {
            db_log_error($hdb,"Found another record with some hostname id: $name_record->{id} ip: $name_record->{ip} hostname: $name_record->{dns_hostname}. Skip update.");
            }
    }
#end update dns block
}

#------------------------------------------------------------------------------------------------------------

sub update_ad_hostname {
my $fqdn = shift;
my $ip = shift;
my $zone = shift;
my $server = shift;
my $db = shift;
if (!$db) { 
    log_info("DNS-UPDATE: Zone $zone Server: $server A: $fqdn IP: $ip"); 
    } else {
    db_log_info($db,"DNS-UPDATE: Zone $zone Server: $server A: $fqdn IP: $ip");
    }
my @add_dns=();
push(@add_dns,"gsstsig");
push(@add_dns,"server $server");
push(@add_dns,"zone $zone");
push(@add_dns,"update delete $fqdn A");
push(@add_dns,"update add $fqdn 3600 A $ip");
push(@add_dns,"send");
my $nsupdate_file = "/tmp/".$fqdn.".nsupdate";
write_to_file($nsupdate_file,\@add_dns);
do_exec('kinit -k -t /usr/local/scripts/cfg/dns_updater.keytab dns_updater@'.uc($zone).' && nsupdate "'.$nsupdate_file.'"');
if (-e "$nsupdate_file") { unlink "$nsupdate_file"; }
}

#---------------------------------------------------------------------------------------------------------------

sub resurrection_auth {
my $db = shift;
my $ip = shift;
my $mac = shift;
my $action = shift;
my $hostname = shift;

my $ip_aton=StrToIp($ip);

my $timestamp=GetNowTime();

my $record=get_record_sql($db,'SELECT * FROM User_auth WHERE `deleted`=0 AND `ip_int`='.$ip_aton.' AND `mac`="'.$mac.'"');

my $new_record;
$new_record->{last_found}=$timestamp;

if ($record->{user_id}) {
    if ($action!~/arp/i) {
	$new_record->{dhcp_action}=$action;
	$new_record->{dhcp_time}=$timestamp;
	update_record($db,'User_auth',$new_record,"id=$record->{id}");
	} else {
	update_record($db,'User_auth',$new_record,"id=$record->{id}");
	}
    return $record->{id};
    }

#default user
my $new_user_id=get_new_user_id($db,$ip,$mac,$hostname);

#search changed mac
$record=get_record_sql($db,'SELECT * FROM User_auth WHERE `ip_int`='.$ip_aton." and deleted=0");
if ($record->{id}) {
    if (!$record->{mac}) {
        db_log_verbose($db,"use empty auth record...");
        $new_record->{ip_int}=$ip_aton;
        $new_record->{ip}=$ip;
        $new_record->{mac}=$mac;
        $new_record->{user_id}=$new_user_id;
	if ($action!~/arp/i) {
	    $new_record->{dhcp_action}=$action;
	    $new_record->{dhcp_time}=$timestamp;
	    update_record($db,'User_auth',$new_record,"id=$record->{id}");
            } else {
	    update_record($db,'User_auth',$new_record,"id=$record->{id}");
	    }
        return $record->{id};
        }
    if ($record->{mac}) {
        db_log_warning($db,"For ip: $ip mac change detected! Old mac: [".$record->{mac}."] New mac: [".$mac."]. Disable old auth_id: $record->{id}");
        my $disable_record;
        $disable_record->{deleted}="1";
        update_record($db,'User_auth',$disable_record,"id=".$record->{id});
        }
    }
#seek old auth with same ip and mac
my $auth_exists=get_count_records($db,'User_auth',"ip_int=".$ip_aton." and mac='".$mac."'");
$new_record->{ip_int}=$ip_aton;
$new_record->{ip}=$ip;
$new_record->{mac}=$mac;
$new_record->{user_id}=$new_user_id;
$new_record->{save_traf}="$save_detail";
$new_record->{deleted}="0";
$new_record->{dhcp_action}=$action;
$new_record->{dhcp_time}=$timestamp;
if ($auth_exists) {
    #found ->Resurrection old record
    my $resurrection_id = get_id_record($db,'User_auth',"ip_int=".$ip_aton." and mac='".$mac."'");
    if (!is_hotspot($db,$ip)) { db_log_warning($db,"Resurrection auth_id: $resurrection_id with ip: $ip and mac: $mac"); }
	    else { db_log_info($db,"Resurrection auth_id: $resurrection_id with ip: $ip and mac: $mac"); }
    update_record($db,'User_auth',$new_record,"id=$resurrection_id");
    } else {
    #not found ->create new record
    if (!is_hotspot($db,$ip)) { db_log_warning($db,"New ip created! ip: $ip mac: $mac"); } else { db_log_info($db,"New ip created! ip: $ip mac: $mac"); }
    insert_record($db,'User_auth',$new_record);
    }
#filter and status
my $cur_auth_id=get_id_record($db,'User_auth',"ip='$ip' and mac='$mac' and deleted=0 ORDER BY last_found DESC");
if ($cur_auth_id) {
    $record=get_record_sql($db,"SELECT * FROM User_list WHERE id=".$new_user_id);
    if ($record) {
	$new_record->{filter_group_id}=$record->{filter_group_id};
	$new_record->{queue_id}=$record->{queue_id};
	$new_record->{enabled}="$record->{enabled}";
        update_record($db,'User_auth',$new_record,"id=$cur_auth_id");
	}
    } else { return; }
return $cur_auth_id;
}

#---------------------------------------------------------------------------------------------------------------

sub new_auth {
my $db = shift;
my $ip = shift;
my $ip_aton=StrToIp($ip);
my $record=get_record_sql($db,'SELECT id FROM User_auth WHERE `deleted`=0 AND `ip_int`='.$ip_aton);
if ($record->{id}) { return $record->{id}; }
#default user
my $new_user_id=get_new_user_id($db,$ip);
my $user_record=get_record_sql($db,"SELECT * FROM User_list WHERE id=".$new_user_id);
my $timestamp=GetNowTime();
my $new_record;
$new_record->{ip_int}=$ip_aton;
$new_record->{ip}=$ip;
$new_record->{user_id}=$new_user_id;
$new_record->{save_traf}="$save_detail";
$new_record->{deleted}="0";
$new_record->{dhcp_action}='netflow';
$new_record->{filter_group_id}=$user_record->{filter_group_id};
$new_record->{queue_id}=$user_record->{queue_id};
$new_record->{enabled}="$user_record->{enabled}";
my $cur_auth_id=insert_record($db,'User_auth',$new_record);
db_log_warning($db,"New ip created by netflow! ip: $ip") if (!$cur_auth_id);
return $cur_auth_id;
}

#---------------------------------------------------------------------------------------------------------------

sub get_option {
my $db=shift;
my $option_id=shift;
return if (!$option_id);
return if (!$db);
my $default_option = get_record_sql($db,'SELECT * FROM config_options WHERE id='.$option_id);
my $config_options = get_record_sql($db,'SELECT * FROM config WHERE option_id='.$option_id);
my $result;
if (!$config_options) {
    if ($default_option->{'type'}=~/int/i or $default_option->{'type'}=~/bool/i) {
	$result = $default_option->{'default_value'}*1;
	} else {
	$result = $default_option->{'default_value'};
	}
    return $result;
    }
$result = $config_options->{'value'};
return $result;
}

#---------------------------------------------------------------------------------------------------------------

sub init_option {
my $db=shift;

$last_refresh_config = time();

$config_ref{dbh}=$db;
$config_ref{save_detail}=get_option($db,23);
$config_ref{add_unknown_user}=get_option($db,22);
$config_ref{dns_server}=get_option($db,3);
$config_ref{dhcp_server}=get_option($db,5);
$config_ref{snmp_default_version}=get_option($db,9);
$config_ref{snmp_default_community}=get_option($db,11);
$config_ref{KB}=get_option($db,1);
$config_ref{mac_discovery}=get_option($db,17);
$config_ref{arp_discovery}=get_option($db,19);
$config_ref{default_user_id}=get_option($db,20);
$config_ref{admin_email}=get_option($db,21);
$config_ref{sender_email}=get_option($db,52);
$config_ref{send_email}=get_option($db,51);
$config_ref{history}=get_option($db,26);
$config_ref{history_dhcp}=get_option($db,27);
$config_ref{router_login}=get_option($db,28);
$config_ref{router_password}=get_option($db,29);
$config_ref{router_port}=get_option($db,30);
$config_ref{org_name}=get_option($db,32);
$config_ref{domain_name}=get_option($db,33);
$config_ref{connections_history}=get_option($db,35);
$config_ref{debug}=get_option($db,34);
$config_ref{log_level} = get_option($db,53);
if ($config_ref{debug}) { $config_ref{log_level} = 255; }
$config_ref{urgent_sync}=get_option($db,50);
$config_ref{ignore_hotspot_dhcp_log} = get_option($db,44);
$config_ref{ignore_update_dhcp_event} = get_option($db,45);
$config_ref{update_hostname_from_dhcp} = get_option($db,46);
$config_ref{hotspot_user_id}=get_option($db,43);
$config_ref{history_log_day}=get_option($db,47);
$config_ref{history_syslog_day} = get_option($db,48);
$config_ref{history_trafstat_day} = get_option($db,49);

$config_ref{enable_quotes} = get_option($db,54);
$config_ref{netflow_step} = get_option($db,55);
$config_ref{traffic_ipstat_history} = get_option($db,56);

$config_ref{nagios_url} = get_option($db,57);
$config_ref{cacti_url} = get_option($db,58);
$config_ref{torrus_url} = get_option($db,59);
$config_ref{wiki_url} = get_option($db,60);
$config_ref{stat_url} = get_option($db,62);

$config_ref{wiki_path} = get_option($db,61);

#$save_detail = 1; id=23
$save_detail=get_option($db,23);
#$add_unknown_user = 1; id=22
$add_unknown_user=get_option($db,22);
#$dns_server='192.168.2.12'; id=3
$dns_server=get_option($db,3);
#$dhcp_server='192.168.2.12'; id=5
$dhcp_server=get_option($db,5);
#$snmp_default_version='2'; id=9
$snmp_default_version=get_option($db,9);
#$snmp_default_community='public'; id=11
$snmp_default_community=get_option($db,11);
#$KB=1024; id=1
$KB=get_option($db,1);
#$mac_discovery; id=17
$mac_discovery=get_option($db,17);
#$arp_discovery; id=19
$arp_discovery=get_option($db,19);
#$default_user_id; id=20
$default_user_id=get_option($db,20);
#$admin_email; id=21
$admin_email=get_option($db,21);
#sender email
$sender_email=get_option($db,52);
#send email
$send_email=get_option($db,51);
#$history=15; id=26
$history=get_option($db,26);
#$history_dhcp=7; id=27
$history_dhcp=get_option($db,27);
#$router_login="admin"; id=28
$router_login=get_option($db,28);
#$router_password="admin"; id=29
$router_password=get_option($db,29);
#$router_port=23; id=30
$router_port=get_option($db,30);
#32
$org_name=get_option($db,32);
#33
$domain_name=get_option($db,33);
#35
$connections_history=get_option($db,35);
#debug
$debug=get_option($db,34);

#log level
$log_level = get_option($db,53);
if ($debug) { $log_level = 255; }

#urgent sync access
$urgent_sync=get_option($db,50);

$ignore_hotspot_dhcp_log = get_option($db,44);

$ignore_update_dhcp_event = get_option($db,45);

$update_hostname_from_dhcp = get_option($db,46);

#$hotspot_user_id; id=43
$hotspot_user_id=get_option($db,43);

$history_log_day=get_option($db,47);

$history_syslog_day = get_option($db,48);

$history_trafstat_day = get_option($db,49);

@subnets=get_records_sql($db,'SELECT * FROM subnets ORDER BY ip_int_start');

if (defined $office_networks) { undef $office_networks; }
if (defined $free_networks) { undef $free_networks; }
if (defined $vpn_networks) { undef $vpn_networks; }
if (defined $hotspot_networks) { undef $hotspot_networks; }
if (defined $all_networks) { undef $all_networks; }

$office_networks = new Net::Patricia;
$free_networks = new Net::Patricia;
$vpn_networks = new Net::Patricia;
$hotspot_networks = new Net::Patricia;
$all_networks = new Net::Patricia;

@office_network_list=();
@free_network_list=();
@free_network_list=();
@vpn_network_list=();
@hotspot_network_list=();
@all_network_list=();

foreach my $net (@subnets) {
next if (!$net->{subnet});
$subnets_ref{$net->{subnet}}=$net;
if ($net->{office}) {
	push(@office_network_list,$net->{subnet});
        $office_networks->add_string($net->{subnet});
        }

if ($net->{free}) {
	push(@free_network_list,$net->{subnet});
        $free_networks->add_string($net->{subnet});
        }

if ($net->{vpn}) {
	push(@vpn_network_list,$net->{subnet});
        $vpn_networks->add_string($net->{subnet});
        }

if ($net->{hotspot}) {
        push(@hotspot_network_list,$net->{subnet});
        push(@all_network_list,$net->{subnet});
        $hotspot_networks->add_string($net->{subnet});
        }
push(@all_network_list,$net->{subnet});
$all_networks->add_string($net->{subnet});
}

#remove all rules for default user id and hotspot subnet
#delete_record($db,"auth_rules","user_id=".$config_ref{default_user_id});
#delete_record($db,"auth_rules","user_id=".$config_ref{hotspot_user_id});
#foreach my $subnet (@hotspot_network_list) { delete_record($db,"auth_rules","rule='".$subnet."'"); }

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
my $auth_rec=get_record_sql($db,'SELECT user_id FROM User_auth WHERE ip="'.$ip.'" and deleted=0');
if ($auth_rec and $auth_rec->{user_id}>0) {
    $netdev=get_record_sql($db,'SELECT * FROM devices WHERE user_id='.$auth_rec->{user_id});
    return $netdev;
    }
return;
}

#---------------------------------------------------------------------------------------------------------------

sub GetUnixTimeByStr {
my $time_str = shift;
$time_str =~s/\//-/g;
$time_str = trim($time_str);
my ($sec,$min,$hour,$day,$mon,$year) = (localtime())[0,1,2,3,4,5];
$year+=1900;
$mon++;
if ($time_str =~/^([0-9]{2,4})\-([0-9]{1,2})-([0-9]{1,2})\s+/) {
    $year = $1; $mon = $2; $day = $3;
    }
if ($time_str =~/([0-9]{1,2})\:([0-9]{1,2})\:([0-9]{1,2})$/) {
    $hour = $1; $min = $2; $sec = $3;
    }
my $result = mktime($sec,$min,$hour,$day,$mon-1,$year-1900);
return $result;
}

#---------------------------------------------------------------------------------------------------------------

sub GetTimeStrByUnixTime {
my $time = shift || time();
my ($sec, $min, $hour, $mday, $mon, $year) = (localtime($time))[0,1,2,3,4,5];
my $result = strftime("%Y-%m-%d %H:%M:%S",$sec, $min, $hour, $mday, $mon, $year);
return $result;
}

#---------------------------------------------------------------------------------------------------------------

sub Set_Variable {
my $db = shift;
my $name = shift || $MY_NAME;
my $value = shift || $$;
my $timeshift = shift || 60;

Del_Variable($db,$name);
my $clean_variables = time() + $timeshift;
my ($sec,$min,$hour,$day,$month,$year,$zone) = localtime($clean_variables);
$month++;
$year += 1900;
my $clean_str=sprintf "%04d-%02d-%02d %02d:%02d:%02d",$year,$month,$day,$hour,$min,$sec;
my $clean_variables_date=$db->quote($clean_str);
do_sql($db,"INSERT INTO variables(name,value,clear_time) VALUES('".$name."','".$value."',".$clean_variables_date.");");
}

#---------------------------------------------------------------------------------------------------------------

sub Get_Variable {
my $db = shift;
my $name = shift || $MY_NAME;
my $variable=get_record_sql($db,'SELECT `value` FROM `variables` WHERE name="'.$name.'"');
if (!$variable and $variable->{'value'}) { return $variable->{'value'}; }
return;
}

#---------------------------------------------------------------------------------------------------------------

sub Del_Variable {
my $db = shift;
my $name = shift || $MY_NAME;
do_sql($db,"DELETE FROM `variables` WHERE name='".$name."';");
}

#---------------------------------------------------------------------------------------------------------------

sub recalc_quotes {

my $db = shift;
my $calc_id = shift || $$;

return if (!get_option($db,54));

clean_variables($db);

return if (Get_Variable($db,'RECALC'));

my $timeshift = get_option($db,55);
if ($timeshift >5 ) { $timeshift=$timeshift-1; }

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
my $user_auth_list_sql="SELECT A.id as auth_id, U.id, U.day_quota, U.month_quota, A.day_quota as auth_day, A.month_quota as auth_month FROM User_auth as A,User_list as U WHERE A.deleted=0 ORDER by user_id";
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
my $day_sql="SELECT User_stats.auth_id, SUM( byte_in + byte_out ) AS traf_all FROM User_stats
WHERE User_stats.`timestamp`>= $day_start AND User_stats.`timestamp`< $day_stop GROUP BY User_stats.auth_id";
my @day_stats = get_records_sql($db,$day_sql);
foreach my $row (@day_stats) {
    my $user_id=$auth_info{$row->{auth_id}}{user_id};
    $auth_info{$row->{auth_id}}{day}=$row->{traf_all};
    $user_stats{$user_id}{day}+=$row->{traf_all};
}

#month
my $month_sql="SELECT User_stats.auth_id, SUM( byte_in + byte_out ) AS traf_all FROM User_stats
WHERE User_stats.`timestamp`>= $month_start AND User_stats.`timestamp`< $month_stop GROUP BY User_stats.auth_id";
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
    do_sql($db,"UPDATE User_auth set blocked=1, changed=1 where id=$auth_id");
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
    do_sql($db,"UPDATE User_auth set blocked=1, changed=1 where user_id=$user_id");
    db_log_verbose($db,$history_msg);
    }
}
Del_Variable($db,'RECALC');
}

#---------------------------------------------------------------------------------------------------------------

sub clean_variables {
my $db = shift;
#clean temporary variables
my $clean_variables = time();
my ($sec,$min,$hour,$day,$month,$year,$zone) = localtime($clean_variables);
$month++;
$year += 1900;
my $now_str=sprintf "%04d-%02d-%02d %02d:%02d:%02d",$year,$month,$day,$hour,$min,$sec;
my $clean_variables_date=$db->quote($now_str);
do_sql($db,"DELETE FROM `variables` WHERE clear_time<=$clean_variables_date");
}

#---------------------------------------------------------------------------------------------------------------

$dbh=init_db();
init_option($dbh);
clean_variables($dbh);
Set_Variable($dbh);

1;
}
