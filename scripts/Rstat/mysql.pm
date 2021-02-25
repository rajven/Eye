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
use Data::Dumper;
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
get_count_records
get_custom_record
get_custom_records
get_device_by_ip
get_diff_rec
get_id_record
get_new_user_id
GetNowTime
get_option
get_record
get_records
get_subnets_ref
init_db
init_option
init_traf_db
insert_record
IpToStr
refresh_add_rules
resurrection_auth
StrToIp
update_record
write_db_log
set_changed

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
'ip_int_end'=>'1',
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
my $dbh=shift;
my @batch_sql=shift;
return if (!$dbh);
return if (!@batch_sql or !scalar(@batch_sql));
$dbh->{AutoCommit} = 0;
my $sth;
foreach my $sSQL(@batch_sql) {
#log_debug($sSQL);
$sth = $dbh->prepare($sSQL);
$sth->execute;
}
$sth->finish;
$dbh->{AutoCommit} = 1;
}

#---------------------------------------------------------------------------------------------------------------

sub do_sql {
my $dbh=shift;
my $sql=shift;
return if (!$dbh);
return if (!$sql);
if ($sql!~/^select /i) { db_log_debug($dbh,$sql); }
my $sql_prep = $dbh->prepare($sql);
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
my $dbh=shift;
my $msg=shift;
my $level = shift || $L_VERBOSE;
my $auth_id = shift || 0;
return if (!$dbh);
return if (!$msg);
$msg=~s/[\'\"]//g;
my $db_log = 0;
my $history_sql="INSERT INTO syslog(customer,message,level,auth_id) VALUES(".$dbh->quote($MY_NAME).",".$dbh->quote($msg).",$level,$auth_id)";
if ($level eq $L_ERROR and $log_level >= $L_ERROR) { log_error($msg); $db_log = 1; }
if ($level eq $L_WARNING and $log_level >= $L_WARNING) { log_warning($msg); $db_log = 1; }
if ($level eq $L_INFO and $log_level >= $L_INFO) { log_info($msg); $db_log = 1; }
if ($level eq $L_DEBUG and $log_level >= $L_DEBUG) { log_debug($msg); $db_log = 1; }
if ($db_log) {
    my $history_rf=$dbh->prepare($history_sql);
    $history_rf->execute;
    }
}

#---------------------------------------------------------------------------------------------------------------

sub db_log_debug {
my $dbh = shift;
my $msg = shift;
my $id = shift;
if ($debug) { write_db_log($dbh,$msg,$L_DEBUG,$id); }
}

#---------------------------------------------------------------------------------------------------------------

sub db_log_error {
my $dbh = shift;
my $msg = shift;
if ($log_level >= $L_ERROR) {
    sendEmail("ERROR! ".substr($msg,0,30),$msg,1);
    write_db_log($dbh,$msg,$L_ERROR);
    }
}

#---------------------------------------------------------------------------------------------------------------

sub db_log_info {
my $dbh = shift;
my $msg = shift;
if ($log_level >= $L_INFO) { write_db_log($dbh,$msg,$L_INFO); }
}

#---------------------------------------------------------------------------------------------------------------

sub db_log_verbose {
my $dbh = shift;
my $msg = shift;
if ($log_level >= $L_VERBOSE) { write_db_log($dbh,$msg,$L_VERBOSE); }
}

#---------------------------------------------------------------------------------------------------------------

sub db_log_warning {
my $dbh = shift;
my $msg = shift;
if ($log_level >= $L_WARNING) {
    sendEmail("WARN! ".substr($msg,0,30),$msg,1);
    write_db_log($dbh,$msg,$L_WARNING);
    }
}

#---------------------------------------------------------------------------------------------------------------

sub init_db {
# Create new database handle. If we can't connect, die()
my $db = DBI->connect("dbi:mysql:database=$DBNAME;host=$DBHOST","$DBUSER","$DBPASS");
if ( !defined $db ) { die "Cannot connect to mySQL server: $DBI::errstr\n"; }
$db->do('SET NAMES utf8');
return $db;
}

#---------------------------------------------------------------------------------------------------------------

sub init_traf_db {
# Create new database handle. If we can't connect, die()
my $db = DBI->connect("dbi:mysql:database=$DBNAME;host=$TRAF_HOST","$DBUSER","$DBPASS");
if ( !defined $db ) { die "Cannot connect to mySQL server: $DBI::errstr\n"; }
$db->do('SET NAMES utf8');
return $db;
}

#---------------------------------------------------------------------------------------------------------------

sub get_count_records {
my $dbh = shift;
my $table = shift;
my $filter = shift;
my $result = 0;
return $result if (!$dbh);
return $result if (!$table);
my $sSQL='Select count(*) as rec_cnt from '.$table;
if ($filter) { $sSQL=$sSQL." where ".$filter; }
my $record = get_custom_record($dbh,$sSQL);
if ($record->{rec_cnt}) { $result = $record->{rec_cnt}; }
return $result;
}

#---------------------------------------------------------------------------------------------------------------

sub get_id_record {
my $dbh = shift;
my $table = shift;
my $filter = shift;
my $result = 0;
return $result if (!$dbh);
return $result if (!$table);
my %fields=('id'=>'1');
my $record = get_record($dbh,$table,\%fields,$filter);
if ($record->{id}) { $result = $record->{id}; }
return $result;
}

#---------------------------------------------------------------------------------------------------------------

sub get_record {
my $dbh = shift;
my $table = shift;
my $field_list = shift;
my $filter = shift;
my $result;
return $result if (!$dbh);
return $result if (!$table);
if ($filter) { $filter = 'where '.$filter; }
my $fields='';
foreach my $field (keys %$field_list) {
next if (!$field);
$fields=$fields.",`".$field."`";
}
$fields=~s/^,//;
$fields=~s/,$//;
if (!$fields) { $fields='*'; }
my $sSQL="SELECT $fields FROM $table $filter LIMIT 1";
my $list = $dbh->prepare( $sSQL );
if ( !defined $list ) { die "Cannot prepare statement: $DBI::errstr\n"; }
$list->execute;
my $list_ref = $list->fetchrow_hashref();
$list->finish();
if (!$list_ref) { return $result; }
return $list_ref;
}

#---------------------------------------------------------------------------------------------------------------

sub get_records {
my $dbh = shift;
my $table = shift;
my $field_list = shift;
my $filter = shift;
my @result;
return @result if (!$dbh);
return @result if (!$table);
if ($filter) { $filter = 'where '.$filter; }
my $fields='';
my %field_order;
my $order_index=0;
foreach my $field (keys %$field_list) {
    next if (!$field);
    $fields=$fields.",`".$field."`";
    $field_order{$order_index}=$field;
    $order_index++;
    }
$fields=~s/^,//;
$fields=~s/,$//;
if (!$fields) { $fields='*'; }
my $sSQL="SELECT $fields FROM $table $filter";
my $list = $dbh->prepare( $sSQL );
if ( !defined $list ) { die "Cannot prepare statement: $DBI::errstr\n"; }
$list->execute;
my @list_ref = @{$list->fetchall_arrayref()};
$list->finish();
if (!@list_ref or !scalar @list_ref) { return @result; }
foreach my $row (@list_ref) {
    my %record;
    foreach my $index (keys %field_order) {
        $record{$field_order{$index}}=@{$row}[$index];
    }
    push(@result,\%record);
}
return @result;
}

#---------------------------------------------------------------------------------------------------------------

sub get_custom_records {
my $dbh = shift;
my $table = shift;
my @result;
return @result if (!$dbh);
return @result if (!$table);
my $list = $dbh->prepare( $table );
if ( !defined $list ) { die "Cannot prepare statement: $DBI::errstr\n"; }
$list->execute;
while(my $row_ref = $list ->fetchrow_hashref) {
push(@result,$row_ref);
}
$list->finish();
return @result;
}

#---------------------------------------------------------------------------------------------------------------

sub get_custom_record {
my $dbh = shift;
my $table = shift;
my @result;
return @result if (!$dbh);
return @result if (!$table);
my $list = $dbh->prepare( $table . ' LIMIT 1' );
if ( !defined $list ) { die "Cannot prepare statement: $DBI::errstr\n"; }
$list->execute;
my $row_ref = $list ->fetchrow_hashref;
$list->finish();
return $row_ref;
}

#---------------------------------------------------------------------------------------------------------------

sub get_diff_rec {
my $dbh = shift;
my $table = shift;
my $value = shift;
my $filter = shift;
return if (!$dbh);
return if (!$table);
return if (!$filter);
my $old_value = get_custom_record($dbh,"SELECT * FROM $table WHERE $filter");
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
my $dbh = shift;
my $table = shift;
my $record = shift;
my $filter = shift;
return if (!$dbh);
return if (!$table);
return if (!$filter);
my $old_record = get_custom_record($dbh,"SELECT * FROM $table WHERE $filter");
my $diff='';
my $change_str='';
my $found_changed=0;
my $auth_id = 0;
my $network_changed = 0;
if ($table=~/User_auth/i) { $auth_id = $old_record->{'id'}; }
foreach my $field (keys %$record) {
    if (!defined $record->{$field}) { $record->{$field}=''; }
    if (!defined $old_record->{$field}) { $old_record->{$field}=''; }
    my $old_value = $old_record->{$field};
    my $new_value = $record->{$field};
    $new_value=~s/\'//g;
    $new_value=~s/\"//g;
    if ($new_value!~/^$old_value$/) {
	if ($table eq 'User_auth' and exists $acl_fields{$field}) { $network_changed = 1; }
	$diff = $diff." $field => $record->{$field} (old: $old_record->{$field}),";
	$change_str = $change_str." `$field`=".$dbh->quote($record->{$field}).",";
	$found_changed++;
	}
    }
if ($found_changed) {
    if ($network_changed) { $diff = $diff." `changed`='1',"; }
    $change_str=~s/\,$//;
    $diff=~s/\,$//;
    if ($table eq 'User_auth') { $change_str .= ", `changed_time`='".GetNowTime()."'"; }
    my $sSQL = "UPDATE $table SET $change_str WHERE $filter";
    db_log_debug($dbh,'Change table '.$table.' for '.$filter.' set: '.$diff,$auth_id);
    do_sql($dbh,$sSQL);
    } else {
    db_log_debug($dbh,'Request update:'.Dumper($record));
    db_log_debug($dbh,'Nothing change. Skip update.');
    }
}

#---------------------------------------------------------------------------------------------------------------

sub insert_record {
my $dbh = shift;
my $table = shift;
my $record = shift;
return if (!$dbh);
return if (!$table);
my $change_str='';
my $fields='';
my $values='';
my $new_str='';
foreach my $field (keys %$record) {
    if (!defined $record->{$field}) { $record->{$field}=''; }
    my $new_value = $record->{$field};
    $new_value=~s/\'//g;
    $new_value=~s/\"//g;
    $fields = $fields."`$field`,";
    $values = $values." ".$dbh->quote($new_value).",";
    $new_str = $new_str." $field => $new_value,";
    }
$fields=~s/,$//;
$values=~s/,$//;
$new_str=~s/,$//;
my $sSQL = "INSERT INTO $table($fields) VALUES($values)";
my $result = do_sql($dbh,$sSQL);
if ($result) { $new_str='id: '.$result.' '.$new_str; }
db_log_debug($dbh,'Add record to table '.$table.' '.$new_str);
return $result;
}

#---------------------------------------------------------------------------------------------------------------

sub delete_record {
my $dbh = shift;
my $table = shift;
my $filter = shift;
return if (!$dbh);
return if (!$table);
return if (!$filter);
my $old_record = get_custom_record($dbh,"SELECT * FROM $table WHERE $filter");
my $diff='';
foreach my $field (keys %$old_record) {
    if (!$old_record->{$field}) { $old_record->{$field}=''; }
    $diff = $diff." $field => $old_record->{$field},";
    }
$diff=~s/,$//;

db_log_debug($dbh,'Delete record from table  '.$table.' value: '.$diff);

#never delete user ip record!
if ($table eq 'User_auth') {
    my $sSQL = "UPDATE User_auth SET deleted=1, changed_time='".GetNowTime()."' WHERE ".$filter;
    do_sql($dbh,$sSQL);
    } else {
    my $sSQL = "DELETE FROM ".$table." WHERE ".$filter;
    do_sql($dbh,$sSQL);
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

sub refresh_add_rules {
my $dbh = shift;
if (defined $add_rules) { undef $add_rules; }
$add_rules = new Net::Patricia;
#custom rules
my @user_rules=get_custom_records($dbh,'select id,default_subnet from User_list where deleted=0 and LENGTH(default_subnet)>0');
foreach my $subnet (@user_rules) {
    next if (!$subnet);
    next if (!$subnet->{default_subnet});
    next if (!$subnet->{id});
    eval {
	$add_rules->add_string($subnet->{default_subnet},$subnet->{id});
	};
    }
#hotspot nets
foreach my $subnet (@hotspot_network_list) {
    next if (!$subnet);
    $add_rules->add_string($subnet,$hotspot_user_id);
    }
}

#---------------------------------------------------------------------------------------------------------------

sub get_new_user_id {
my $dbh = shift;
my $ip = shift;
if (!defined $add_rules) { refresh_add_rules($dbh); }
my $user_id=$add_rules->match_string($ip);
if (!$user_id) { return $default_user_id; }
return $user_id;
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

sub resurrection_auth {
my $db = shift;
my $ip = shift;
my $mac = shift;
my $action = shift;

my $ip_aton=StrToIp($ip);
my %fields=( 'user_id'=>'1', 'id'=>'2' );
my $timestamp=GetNowTime();

my $record=get_record($db,'User_auth',\%fields,"ip_int=$ip_aton and mac='".$mac."' and deleted=0");

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
    return;
    }

#default user
my $new_user_id=get_new_user_id($db,$ip);

#search changed mac
%fields=( 'id'=>'1', 'mac'=>'2' );
$record=get_record($db,'User_auth',\%fields,"ip_int=$ip_aton and deleted=0");
if ($record->{id}) {
    if (!$record->{mac}) {
        db_log_verbose($db,"use empty auth record...");
        $new_record->{ip_int}=$ip_aton;
        $new_record->{ip_int_end}=$ip_aton;
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
        return;
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
$new_record->{ip_int_end}=$ip_aton;
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
    db_log_info($db,"Resurrection auth_id: $resurrection_id with ip: $ip and mac: $mac");
    update_record($db,'User_auth',$new_record,"id=$resurrection_id");
    } else {
    #not found ->create new record
    db_log_info($db,"New ip created! ip: $ip mac: $mac");
    insert_record($db,'User_auth',$new_record);
    }
#filter and status
my $cur_auth_id=get_id_record($db,'User_auth',"ip='$ip' and mac='$mac' and deleted=0 ORDER BY last_found DESC");
if ($cur_auth_id) {
    %fields=( 'enabled'=>'1', 'filter_group_id'=>'2', 'queue_id'=>'3' );
    $record=get_record($db,'User_list',\%fields,"id=".$new_user_id);
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

sub get_option {
my $dbh=shift;
my $option_id=shift;
return if (!$option_id);
return if (!$dbh);
my $default_option = get_custom_record($dbh,'SELECT * FROM config_options WHERE id='.$option_id);
my $config_options = get_custom_record($dbh,'SELECT * FROM config WHERE option_id='.$option_id);
my $result;
if (!$config_options) {
    if ($default_option->{type}=~/int/i or $default_option->{type}=~/bool/i) {
	$result = $default_option->{default_value}*1;
	} else {
	$result = $default_option->{default_value};
	}
    return $result;
    }
$result = $config_options->{value};
return $result;
}

#---------------------------------------------------------------------------------------------------------------

sub init_option {
my $dbh=shift;

$last_refresh_config = time();

$config_ref{dbh}=$dbh;
$config_ref{save_detail}=get_option($dbh,23);
$config_ref{add_unknown_user}=get_option($dbh,22);
$config_ref{dns_server}=get_option($dbh,3);
$config_ref{dhcp_server}=get_option($dbh,5);
$config_ref{snmp_default_version}=get_option($dbh,9);
$config_ref{snmp_default_community}=get_option($dbh,11);
$config_ref{KB}=get_option($dbh,1);
$config_ref{mac_discovery}=get_option($dbh,17);
$config_ref{arp_discovery}=get_option($dbh,19);
$config_ref{default_user_id}=get_option($dbh,20);
$config_ref{admin_email}=get_option($dbh,21);
$config_ref{sender_email}=get_option($dbh,52);
$config_ref{send_email}=get_option($dbh,51);
$config_ref{history}=get_option($dbh,26);
$config_ref{history_dhcp}=get_option($dbh,27);
$config_ref{router_login}=get_option($dbh,28);
$config_ref{router_password}=get_option($dbh,29);
$config_ref{router_port}=get_option($dbh,30);
$config_ref{org_name}=get_option($dbh,32);
$config_ref{domain_name}=get_option($dbh,33);
$config_ref{connections_history}=get_option($dbh,35);
$config_ref{auth_clear}=get_option($dbh,36);
$config_ref{debug}=get_option($dbh,34);
$config_ref{log_level} = get_option($dbh,53);
if ($config_ref{debug}) { $config_ref{log_level} = 255; }
$config_ref{urgent_sync}=get_option($dbh,50);
$config_ref{ignore_hotspot_dhcp_log} = get_option($dbh,44);
$config_ref{ignore_update_dhcp_event} = get_option($dbh,45);
$config_ref{update_hostname_from_dhcp} = get_option($dbh,46);
$config_ref{hotspot_user_id}=get_option($dbh,43);
$config_ref{history_log_day}=get_option($dbh,47);
$config_ref{history_syslog_day} = get_option($dbh,48);
$config_ref{history_trafstat_day} = get_option($dbh,49);

#$save_detail = 1; id=23
$save_detail=get_option($dbh,23);
#$add_unknown_user = 1; id=22
$add_unknown_user=get_option($dbh,22);
#$dns_server='192.168.2.12'; id=3
$dns_server=get_option($dbh,3);
#$dhcp_server='192.168.2.12'; id=5
$dhcp_server=get_option($dbh,5);
#$snmp_default_version='2'; id=9
$snmp_default_version=get_option($dbh,9);
#$snmp_default_community='public'; id=11
$snmp_default_community=get_option($dbh,11);
#$KB=1024; id=1
$KB=get_option($dbh,1);
#$mac_discovery; id=17
$mac_discovery=get_option($dbh,17);
#$arp_discovery; id=19
$arp_discovery=get_option($dbh,19);
#$default_user_id; id=20
$default_user_id=get_option($dbh,20);
#$admin_email; id=21
$admin_email=get_option($dbh,21);
#sender email
$sender_email=get_option($dbh,52);
#send email
$send_email=get_option($dbh,51);
#$history=15; id=26
$history=get_option($dbh,26);
#$history_dhcp=7; id=27
$history_dhcp=get_option($dbh,27);
#$router_login="admin"; id=28
$router_login=get_option($dbh,28);
#$router_password="admin"; id=29
$router_password=get_option($dbh,29);
#$router_port=23; id=30
$router_port=get_option($dbh,30);
#32
$org_name=get_option($dbh,32);
#33
$domain_name=get_option($dbh,33);
#35
$connections_history=get_option($dbh,35);
#36
$auth_clear=get_option($dbh,36);
#debug
$debug=get_option($dbh,34);

#log level
$log_level = get_option($dbh,53);
if ($debug) { $log_level = 255; }

#urgent sync access
$urgent_sync=get_option($dbh,50);

$ignore_hotspot_dhcp_log = get_option($dbh,44);

$ignore_update_dhcp_event = get_option($dbh,45);

$update_hostname_from_dhcp = get_option($dbh,46);

#$hotspot_user_id; id=43
$hotspot_user_id=get_option($dbh,43);

$history_log_day=get_option($dbh,47);

$history_syslog_day = get_option($dbh,48);

$history_trafstat_day = get_option($dbh,49);

@subnets=get_custom_records($dbh,'SELECT * FROM subnets ORDER BY ip_int_start');

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

}

#---------------------------------------------------------------------------------------------------------------

sub get_subnets_ref {
my $dbh = shift;
my @list=get_custom_records($dbh,'SELECT * FROM subnets ORDER BY ip_int_start');
my $list_ref;
foreach my $net (@list) {
next if (!$net->{subnet});
$list_ref->{$net->{subnet}}=$net;
}
return $list_ref;
}

#---------------------------------------------------------------------------------------------------------------

sub get_device_by_ip {
my $dbh = shift;
my $ip = shift;
my $netdev=get_custom_record($dbh,'SELECT * FROM devices WHERE ip="'.$ip.'"');
if ($netdev and $netdev->{id}>0) { return $netdev; }
my $auth_rec=get_custom_record($dbh,'SELECT user_id FROM User_auth WHERE ip="'.$ip.'" and deleted=0');
if ($auth_rec and $auth_rec->{user_id}>0) {
    $netdev=get_custom_record($dbh,'SELECT * FROM devices WHERE user_id='.$auth_rec->{user_id});
    return $netdev;
    }
return;
}

#---------------------------------------------------------------------------------------------------------------

$dbh=init_db();
init_option($dbh);

1;
}
