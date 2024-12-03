package eyelib::database;

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
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
use DateTime;
use POSIX qw(mktime ctime strftime);
use File::Temp qw(tempfile);
use DBI;

our @ISA = qw(Exporter);

our @EXPORT = qw(
batch_db_sql
batch_db_sql_cached
batch_db_sql_csv
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
apply_device_lock
set_lock_discovery
unset_lock_discovery
IpToStr
unbind_ports
resurrection_auth
new_auth
StrToIp
get_first_line
is_ad_computer
update_dns_record
update_dns_record_by_dhcp
create_dns_cname
delete_dns_cname
create_dns_hostname
delete_dns_hostname
create_dns_ptr
delete_dns_ptr
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

our %dhcp_fields = (
'ip' => '1',
'dhcp'=>'1',
'deleted'=>'1',
'mac'=>'1',
);

our %dns_fields = (
'ip' => '1',
'dns_name'=>'1',
'alias'=>'1',
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
my @msg = ();
if (ref($batch_sql) eq 'ARRAY') { @msg = @$batch_sql; } else { @msg = split("\n",$batch_sql); }
foreach my $sSQL (@msg) {
    next if (!$sSQL);
    $sth = $db->prepare($sSQL) or die "Unable to prepare $sSQL" . $db->errstr;
    $sth->execute() or die "Unable to prepare $sSQL" . $db->errstr;
    $apply = 1;
    }
if ($apply) { $sth->finish(); }
$db->{AutoCommit} = 1;
}

#---------------------------------------------------------------------------------------------------------------

sub batch_db_sql_cached {
my $db = DBI->connect("dbi:$config_ref{DBTYPE}:database=$DBNAME;host=$DBHOST","$DBUSER","$DBPASS", { RaiseError => 0, AutoCommit => 0 });
if ( !defined $db ) { die "Cannot connect to $config_ref{DBTYPE} server: $DBI::errstr\n"; }
if ($config_ref{DBTYPE} eq 'mysql') {
    $db->do('SET NAMES utf8mb4');
    $db->{'mysql_enable_utf8'} = 1;
    $db->{'mysql_auto_reconnect'} = 1;
    }
my $table= shift;
my $batch_sql=shift;
return if (!$db);
my @msg = ();
if (ref($batch_sql) eq 'ARRAY') { @msg = @$batch_sql; } else { @msg = split("\n",$batch_sql); }
my $sth = $db->prepare_cached($table) or die "Unable to prepare:" . $db->errstr;
foreach my $sSQL (@msg) {
    next if (!$sSQL);
    $sth->execute(@$sSQL) or die "Unable to execute:" . $db->errstr;
    }
$db->commit();
$db->disconnect();
}

#---------------------------------------------------------------------------------------------------------------

sub batch_db_sql_csv {
my $db;
if ($config_ref{DBTYPE} eq 'mysql') {
    $db = DBI->connect("dbi:$config_ref{DBTYPE}:database=$DBNAME;host=$DBHOST","$DBUSER","$DBPASS", { RaiseError => 1, mysql_local_infile=> 1 });
    } else {
    $db = DBI->connect("dbi:$config_ref{DBTYPE}:database=$DBNAME;host=$DBHOST","$DBUSER","$DBPASS", { RaiseError => 1 });
    }
if ( !defined $db ) { die "Cannot connect to $config_ref{DBTYPE} server: $DBI::errstr\n"; }

return if (!$db);

if ($config_ref{DBTYPE} eq 'mysql') {
    $db->do('SET NAMES utf8mb4');
    $db->{'mysql_enable_utf8'} = 1;
    $db->{'mysql_auto_reconnect'} = 1;
    }

my $table= shift;
my $data = shift;
my $fh = File::Temp->new(UNLINK=>1);
my $fname = $fh->filename;
binmode($fh,':utf8');
foreach my $row (@$data) {
    next if (!$row);
    my @tmp = @$row;
    my $values = 'NULL';
    for (my $i = 0; $i <@tmp ; $i++) {
	$values.=',"'.$tmp[$i].'"';
	}
    $values =~s/,$//;
    print $fh $values."\r\n";
    }
close $fh;
my $query = qq{ LOAD DATA LOCAL INFILE '$fname' INTO TABLE $table FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '"' LINES TERMINATED BY '\r\n'; };
$db->do($query);
$db->disconnect;
File::Temp::cleanup();
}

#---------------------------------------------------------------------------------------------------------------

sub do_sql {
my $db=shift;
my $sql=shift;
return if (!$db);
return if (!$sql);
if ($sql!~/^select /i) { log_debug($sql); }
my $sql_prep = $db->prepare($sql) or die "Unable to prepare $sql: " . $db->errstr;
my $sql_ref;
my $rv = $sql_prep->execute() or die "Unable to execute $sql: " . $db->errstr;
if ($sql=~/^insert/i) {
    if ($config_ref{DBTYPE} eq 'mysql') {
        $sql_ref = $sql_prep->{mysql_insertid};
	} else {
        ($sql_ref) = $db->selectrow_array("SELECT lastval()");
	}
    }
if ($sql=~/^select /i) { $sql_ref = $sql_prep->fetchall_arrayref() or die "Unable to select $sql: " . $db->errstr; };
$sql_prep->finish();
return $sql_ref;
}


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

sub write_db_log {
my $db=shift;
my $msg=shift;
my $level = shift || $L_VERBOSE;
my $auth_id = shift || 0;
return if (!$db);
return if (!$msg);
$msg=~s/[\'\"]//g;
my $db_log = 0;
if (!$db) { $db_log = 0; }

if ($level eq $L_ERROR and $log_level >= $L_ERROR) { log_error($msg); $db_log = 1; }
if ($level eq $L_WARNING and $log_level >= $L_WARNING) { log_warning($msg); $db_log = 1; }
if ($level eq $L_INFO and $log_level >= $L_INFO) { log_info($msg); $db_log = 1; }
if ($level eq $L_VERBOSE and $log_level >= $L_VERBOSE) { log_verbose($msg); $db_log = 1; }
if ($level eq $L_DEBUG and $log_level >= $L_DEBUG) { log_debug($msg); $db_log = 1; }

if ($db_log) {
    my $history_sql="INSERT INTO worklog(customer,message,level,auth_id) VALUES(".$db->quote($MY_NAME).",".$db->quote($msg).",$level,$auth_id)";
    my $history_rf=$db->prepare($history_sql) or die "Unable to prepare $history_sql:" . $db->errstr;
    $history_rf->execute() or die "Unable to execute $history_sql: " . $db->errstr;
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
    sendEmail("ERROR! ".get_first_line($msg),$msg,1);
    write_db_log($db,$msg,$L_ERROR);
    }
}

#---------------------------------------------------------------------------------------------------------------

sub db_log_info {
my $db = shift;
my $msg = shift;
my $id = shift;
if ($log_level >= $L_INFO) { write_db_log($db,$msg,$L_INFO,$id); }
}

#---------------------------------------------------------------------------------------------------------------

sub db_log_verbose {
my $db = shift;
my $msg = shift;
my $id = shift;
if ($log_level >= $L_VERBOSE) { write_db_log($db,$msg,$L_VERBOSE,$id); }
}

#---------------------------------------------------------------------------------------------------------------

sub db_log_warning {
my $db = shift;
my $msg = shift;
my $id = shift;
if ($log_level >= $L_WARNING) {
    sendEmail("WARN! ".get_first_line($msg),$msg,1);
    write_db_log($db,$msg,$L_WARNING,$id);
    }
}

#---------------------------------------------------------------------------------------------------------------

sub init_db {
# Create new database handle. If we can't connect, die()
my $db = DBI->connect("dbi:$config_ref{DBTYPE}:database=$DBNAME;host=$DBHOST","$DBUSER","$DBPASS", { RaiseError => 0, AutoCommit => 1 });
if ( !defined $db ) { die "Cannot connect to mySQL server: $DBI::errstr\n"; }
if ($config_ref{DBTYPE} eq 'mysql') {
    $db->do('SET NAMES utf8mb4');
    $db->{'mysql_enable_utf8'} = 1;
    $db->{'mysql_auto_reconnect'} = 1;
    }
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
if ($filter) { $sSQL=$sSQL." WHERE ".$filter; }
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
my $list = $db->prepare( $table ) or die "Unable to prepare $table:" . $db->errstr;
$list->execute() or die "Unable to execute $table: " . $db->errstr;
while(my $row_ref = $list->fetchrow_hashref()) { push(@result,$row_ref); }
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
$tsql.=' LIMIT 1';
my $row_ref;
eval {
    my $list = $db->prepare($tsql) or die "Unable to prepare $tsql: " . $db->errstr;
    $list->execute() or die "Unable to execute $tsql: " . $db->errstr;
    $row_ref = $list->fetchrow_hashref();
    $list->finish();
};
if ($@) {
    log_error("Error apply sql: $tsql err:".$@);
    die "Error apply sql: $tsql";
    }
return $row_ref;
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

sub get_dns_name {
my $db = shift;
my $id = shift;
my $auth_record = get_record_sql($db,"SELECT dns_name FROM User_auth WHERE deleted=0 AND id=".$id);
if ($auth_record and $auth_record->{'dns_name'}) { return $auth_record->{'dns_name'}; }
return;
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

my $rec_id = 0;
my $dns_changed = 0;

$rec_id = $old_record->{'id'} if ($old_record->{'id'});

if ($table eq "User_auth") {
    foreach my $field (keys %$record) {
        if (exists $acl_fields{$field}) { $record->{changed}="1"; }
        if (exists $dhcp_fields{$field}) { $record->{dhcp_changed}="1"; }
        if (exists $dns_fields{$field}) { $dns_changed=1; }
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
    if ($table eq 'User_auth') {
        $change_str .= ", `changed_time`='".GetNowTime()."'"; 
        if ($dns_changed) {
                my $del_dns;
                if ($old_record->{'dns_name'} and $old_record->{'ip'}) {
                    $del_dns->{'name_type'}='A';
                    $del_dns->{'name'}=$old_record->{'dns_name'};
                    $del_dns->{'value'}=$old_record->{'ip'};
                    $del_dns->{'type'}='del';
                    if ($rec_id) { $del_dns->{'auth_id'}=$rec_id; }
                    insert_record($db,'dns_queue',$del_dns);
                    }
                my $new_dns;
                my $dns_rec_ip = $old_record->{ip};
                my $dns_rec_name = $old_record->{dns_name};
                if ($record->{'dns_name'}) { $dns_rec_name = $record->{'dns_name'}; }
                if ($record->{'ip'}) { $dns_rec_ip = $record->{'ip'}; }
                if ($dns_rec_name and $dns_rec_ip) {
                    $new_dns->{'name_type'}='A';
                    $new_dns->{'name'}=$dns_rec_name;
                    $new_dns->{'value'}=$dns_rec_ip;
                    $new_dns->{'type'}='add';
                    if ($rec_id) { $new_dns->{'auth_id'}=$rec_id; }
                    insert_record($db,'dns_queue',$new_dns);
                    }
                }
        }
    if ($table eq 'User_auth_alias') {
        if ($dns_changed) {
                my $del_dns;
                if ($old_record->{'alias'}) {
                    $del_dns->{'name_type'}='CNAME';
                    $del_dns->{'name'}=$old_record->{'alias'};
                    $del_dns->{'type'}='del';
                    $del_dns->{'value'}=get_dns_name($db,$old_record->{auth_id});
                    $del_dns->{'auth_id'}=$old_record->{auth_id};
                    insert_record($db,'dns_queue',$del_dns);
                    }
                my $new_dns;
                my $dns_rec_name = $old_record->{alias};
                if ($record->{'alias'}) { $dns_rec_name = $record->{'alias'}; }
                if ($dns_rec_name) {
                    $new_dns->{'name_type'}='CNAME';
                    $new_dns->{'name'}=$dns_rec_name;
                    $new_dns->{'type'}='add';
                    $new_dns->{'value'}=get_dns_name($db,$old_record->{auth_id});
                    $new_dns->{'auth_id'}=$rec_id; 
                    insert_record($db,'dns_queue',$new_dns);
                    }
                }
        }
    my $sSQL = "UPDATE $table SET $change_str WHERE $filter";
    db_log_debug($db,'Change table '.$table.' for '.$filter.' set: '.$diff, $rec_id);
    do_sql($db,$sSQL);
    } else {
    db_log_debug($db,'Nothing change. Skip update.');
    }
return 1;
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

my $dns_changed = 0;

if ($table eq "User_auth") {
    foreach my $field (keys %$record) {
        if (exists $acl_fields{$field}) { $record->{changed}="1"; }
        if (exists $dhcp_fields{$field}) { $record->{dhcp_changed}="1"; }
        if (exists $dns_fields{$field}) { $dns_changed=1; }
        }
    }

foreach my $field (keys %$record) {
    if (!defined $record->{$field}) { $record->{$field}=''; }
    my $new_value = $record->{$field};
    $new_value=~s/\'//g;
    $new_value=~s/\"//g;
    $record->{$field} = $new_value;
    $fields = $fields."`$field`,";
    $values = $values." ".$db->quote($record->{$field}).",";
    $new_str = $new_str." $field => $record->{$field},";
    }

$fields=~s/,$//;
$values=~s/,$//;
$new_str=~s/,$//;

my $sSQL = "INSERT INTO $table($fields) VALUES($values)";
my $result = do_sql($db,$sSQL);
if ($result) {
    $new_str='id: '.$result.' '.$new_str;
    if ($table eq 'User_auth_alias' and $dns_changed) {
        if ($record->{'alias'}) {
                    my $add_dns;
                    $add_dns->{'name_type'}='CNAME';
                    $add_dns->{'name'}=$record->{'alias'};
                    $add_dns->{'value'}=get_dns_name($db,$record->{'auth_id'});
                    $add_dns->{'type'}='add';
                    $add_dns->{'auth_id'}=$record->{'auth_id'};
                    insert_record($db,'dns_queue',$add_dns);
                    }
        }
    if ($table eq 'User_auth' and $dns_changed) {
        if ($record->{'dns_name'} and $record->{'ip'} and $dns_changed) {
                    my $add_dns;
                    $add_dns->{'name_type'}='A';
                    $add_dns->{'name'}=$record->{'dns_name'};
                    $add_dns->{'value'}=$record->{'ip'};
                    $add_dns->{'type'}='add';
                    $add_dns->{'auth_id'}=$result;
                    insert_record($db,'dns_queue',$add_dns);
                    }
        }
    }
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
    my $sSQL = "UPDATE User_auth SET changed=1, deleted=1, changed_time='".GetNowTime()."' WHERE ".$filter;
    do_sql($db,$sSQL);
    if ($old_record->{'dns_name'} and $old_record->{'ip'}) {
            my $del_dns;
            $del_dns->{'name_type'}='A';
            $del_dns->{'name'}=$old_record->{'dns_name'};
            $del_dns->{'value'}=$old_record->{'ip'};
            $del_dns->{'type'}='del';
            $del_dns->{'auth_id'}=$old_record->{'id'};
            insert_record($db,'dns_queue',$del_dns);
            }
    }

if ($table eq 'User_auth_alias') {
    if ($old_record->{'alias'} and $old_record->{'auth_id'}) {
            my $del_dns;
            $del_dns->{'name_type'}='CNAME';
            $del_dns->{'name'}=$old_record->{'alias'};
            $del_dns->{'value'}=get_dns_name($db,$old_record->{'auth_id'});
            $del_dns->{'type'}='del';
            $del_dns->{'auth_id'}=$old_record->{'auth_id'};
            insert_record($db,'dns_queue',$del_dns);
            }
    }

my $sSQL = "DELETE FROM ".$table." WHERE ".$filter;
return do_sql($db,$sSQL);
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
foreach my $row (@ip_rules) { $users->add_string($row->{subnet}); }
if ($users->match_string($ip)) { return 1; }
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

#check ip
if (defined $ip and $ip) {
    my $users = new Net::Patricia;
    #check ip rules
    my @ip_rules = get_records_sql($db,'SELECT * FROM auth_rules WHERE type=1 and LENGTH(rule)>0 AND user_id IS NOT NULL');
    foreach my $row (@ip_rules) { eval { $users->add_string($row->{rule},$row->{user_id}); }; }
    if ($users->match_string($ip)) { $result->{user_id}=$users->match_string($ip); }
    }

#check mac
if (defined $mac and $mac) {
    my @user_rules=get_records_sql($db,'SELECT * FROM auth_rules WHERE type=2 AND LENGTH(rule)>0 AND user_id IS NOT NULL');
    foreach my $user (@user_rules) {
	my $rule = mac_simplify($user->{rule});
        if ($mac=~/$rule/i) { $result->{user_id}=$user->{user_id}; }
        }
    }
#check hostname
if (defined $hostname and $hostname) {
    my @user_rules=get_records_sql($db,'SELECT * FROM auth_rules WHERE type=3 AND LENGTH(rule)>0 AND user_id IS NOT NULL');
    foreach my $user (@user_rules) {
        if ($hostname=~/$user->{rule}/i) { $result->{user_id}=$user->{user_id}; }
        }
    }

#
if ($result->{user_id}) { return $result; }

#check ou rules

#check ip
if (defined $ip and $ip) {
    my $users = new Net::Patricia;
    #check hotspot
    my @ip_rules = get_records_sql($db,'SELECT * FROM subnets WHERE hotspot=1 AND LENGTH(subnet)>0');
    foreach my $row (@ip_rules) { $users->add_string($row->{subnet},$default_hotspot_ou_id); }
    if ($users->match_string($ip)) { $result->{ou_id}=$users->match_string($ip); }
    #check ip rules
    @ip_rules = get_records_sql($db,'SELECT * FROM auth_rules WHERE type=1 and LENGTH(rule)>0 AND ou_id IS NOT NULL');
    foreach my $row (@ip_rules) { eval { $users->add_string($row->{rule},$row->{ou_id}); }; }
    if ($users->match_string($ip)) { $result->{ou_id}=$users->match_string($ip); }
    }

#check mac
if (defined $mac and $mac) {
    my @user_rules=get_records_sql($db,'SELECT * FROM auth_rules WHERE type=2 AND LENGTH(rule)>0 AND ou_id IS NOT NULL');
    foreach my $user (@user_rules) {
	my $rule = mac_simplify($user->{rule});
        if ($mac=~/$rule/i) { $result->{ou_id}=$user->{ou_id}; }
        }
    }

#check hostname
if (defined $hostname and $hostname) {
    my @user_rules=get_records_sql($db,'SELECT * FROM auth_rules WHERE type=3 AND LENGTH(rule)>0 AND ou_id IS NOT NULL');
    foreach my $user (@user_rules) {
        if ($hostname=~/$user->{rule}/i) { $result->{ou_id}=$user->{ou_id}; }
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
update_record($db,'User_auth',$update_record,"id=$id");
}

#---------------------------------------------------------------------------------------------------------------

sub update_dns_record {

my $hdb = shift;
my $auth_id = shift;

return if (!$config_ref{enable_dns_updates});

#get domain
my $ad_zone = get_option($hdb,33);

#get dns server
my $ad_dns = get_option($hdb,3);

my $enable_ad_dns_update = ($ad_zone and $ad_dns and $config_ref{enable_dns_updates});

log_debug("Auth id: ".$auth_id);
log_debug("enable_ad_dns_update: ".$enable_ad_dns_update);
log_debug("DNS update flags - zone: ".$ad_zone.", dns: ".$ad_dns.", enable_ad_dns_update: ".$enable_ad_dns_update);

my @dns_queue = get_records_sql($hdb,"SELECT * FROM dns_queue WHERE auth_id=".$auth_id." ORDER BY id ASC");

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
    $fqdn=lc($dns_cmd->{name});
    $fqdn=~s/\.$ad_zone$//i;
    $fqdn=~s/\.$//;
    if ($dns_cmd->{value}) {
        $fqdn_parent=lc($dns_cmd->{value});
        $fqdn_parent=~s/\.$ad_zone$//i;
        $fqdn_parent=~s/\.$//;
        }
    #skip update unknown domain
    if ($fqdn =~/\./ or $fqdn_parent =~/\./) { next; }

    $fqdn = $fqdn.".".$ad_zone;
    $fqdn_parent = $fqdn_parent.".".$ad_zone;

    #remove cname
    if ($dns_cmd->{type} eq 'del') {
        delete_dns_cname($fqdn_parent,$fqdn,$ad_zone,$ad_dns,$hdb);
        }
    #create cname
    if ($dns_cmd->{type} eq 'add') {
        create_dns_cname($fqdn_parent,$fqdn,$ad_zone,$ad_dns,$hdb);
        }
    }

if ($dns_cmd->{name_type}=~/^a$/i) {
    $fqdn=lc($dns_cmd->{name});
    $fqdn=~s/\.$ad_zone$//i;
    $fqdn=~s/\.$//;

    if (!$dns_cmd->{value}) { next; }
    $fqdn_ip=lc($dns_cmd->{value});
    #skip update unknown domain
    if ($fqdn =~/\./) { next; }

    $fqdn = $fqdn.".".$ad_zone;

    #dns update disabled?
    my $maybe_update_dns=( $enable_ad_dns_update and $office_networks->match_string($fqdn_ip) );
    if (!$maybe_update_dns) {
        db_log_info($hdb,"FOUND Auth_id: $auth_id. DNS update disabled.");
        next;
        }

    #get aliases
    my @aliases = get_records_sql($hdb,"SELECT * FROM User_auth_alias WHERE auth_id=".$auth_id);

    #remove A & PTR
    if ($dns_cmd->{type} eq 'del') {
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
    if ($dns_cmd->{type} eq 'add') {
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
log_debug("DNS update flags - zone: ".$ad_zone.",dns: ".$ad_dns.", update_hostname_from_dhcp: ".$update_hostname_from_dhcp.", enable_ad_dns_update: ".$enable_ad_dns_update);

my $maybe_update_dns=(is_ad_computer($hdb,$dhcp_record->{hostname_utf8}) and ($dhcp_record->{type}=~/add/i or $dhcp_record->{type}=~/old/i) and $enable_ad_dns_update and $subnets_dhcp->{$dhcp_record->{network}->{subnet}}->{dhcp_update_hostname});
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
    #log event without email alert
    log_error("Dynamic record mismatch! Expected: $fqdn => $dhcp_record->{ip}, recivied: $dynamic_ref. Checking the status.");
    #check exists hostname
    my $another_hostname_exists = 0;
    my $hostname_filter = ' LOWER(dns_name) REGEXP("^'.lc($dhcp_record->{hostname_utf8}).'\.*$")';
    if ($fqdn_static ne '' and $fqdn !~/$fqdn_static/) {
	    $hostname_filter = $hostname_filter . ' or LOWER(dns_name) REGEXP("^'.lc($auth_record->{dns_name}).'\.*$")';
	    }
    #check exists another records with some static hostname
    my $filter_sql = 'SELECT * FROM User_auth WHERE id<>'.$auth_record->{id}.' and deleted=0 and ('.$hostname_filter.') ORDER BY last_found DESC';
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
                    my @aliases = get_records_sql($hdb,"SELECT * FROM User_auth_alias WHERE auth_id=".$auth_record->{id});
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
    my $dev = get_record_sql($db,"SELECT `discovery_locked`, `locked_timestamp`, UNIX_TIMESTAMP(`locked_timestamp`) as u_locked_timestamp  FROM devices WHERE id=".$device_id);
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
    log_info("DNS-UPDATE: Zone $zone Server: $server CNAME: $alias for $fqdn"); 
    } else {
    db_log_info($db,"DNS-UPDATE: Zone $zone Server: $server CNAME: $alias for $fqdn ");
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
    log_info("DNS-UPDATE: Zone $zone Server: $server A: $fqdn IP: $ip"); 
    } else {
    db_log_info($db,"DNS-UPDATE: Zone $zone Server: $server A: $fqdn IP: $ip");
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

my $login_count = get_count_records($db,"User_list","(login LIKE '".$user->{login}."(%)') OR (login='".$user->{login}."')");
if ($login_count) { $login_count++; $user->{login} .="(".$login_count.")"; }

$user->{ou_id} = $user_info->{ou_id};
my $ou_info = get_record_sql($db,"SELECT * FROM OU WHERE id=".$user_info->{'ou_id'});
if ($ou_info) {
    $user->{'enabled'} = $ou_info->{'enabled'};
    $user->{'queue_id'} = $ou_info->{'queue_id'};
    $user->{'filter_group_id'} = $ou_info->{'filter_group_id'};
    }
my $result = insert_record($db,"User_list",$user);
if ($result and $config_ref{auto_mac_rule} and $user_info->{mac}) {
    my $auth_rule;
    $auth_rule->{user_id} = $result;
    $auth_rule->{type} = 2;
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
    my $user_subnet = get_record_sql($db, "SELECT * FROM `subnets` WHERE hotspot=1 or office=1 and ( ".$ip_aton." >= ip_int_start and ".$ip_aton." <= ip_int_stop)");
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
    my @t_auth = get_records_sql($db, "SELECT id,mac,user_id FROM User_auth WHERE ip_int>=" . $ip_subnet->{'ip_int_start'} . " and ip_int<=" . $ip_subnet->{'ip_int_stop'} . " and mac='" . $mac . "' and deleted=0 ORDER BY id");
    my $auth_count = 0;
    my $result;
    $result->{'count'} = 0;
    foreach my $row (@t_auth) {
        next if (!$row);
        $auth_count++;
        $result->{'count'} = $auth_count;
        $result->{$auth_count} = $row->{'id'};
        push(@{$result->{'users_id'}}, $row->{'user_id'});
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
my $client_id = $ip_record->{'client-id'};

if (!exists $ip_record->{ip_aton}) { $ip_record->{ip_aton}=StrToIp($ip); }
if (!exists $ip_record->{hotspot}) { $ip_record->{hotspot}=is_hotspot($db,$ip); }

my $ip_aton=$ip_record->{ip_aton};

my $timestamp=GetNowTime();

my $record=get_record_sql($db,'SELECT * FROM User_auth WHERE `deleted`=0 AND `ip_int`='.$ip_aton.' AND `mac`="'.$mac.'"');

my $new_record;
$new_record->{last_found}=$timestamp;
if ($client_id) { $new_record->{'client-id'} = $client_id; }

#auth found?
if ($record->{user_id}) {
    #update timestamp and return
    if ($action!~/arp/i) {
	    $new_record->{dhcp_action}=$action;
	    $new_record->{dhcp_time}=$timestamp;
	    if ($hostname) { $new_record->{dhcp_hostname} = $hostname; }
	    update_record($db,'User_auth',$new_record,"id=$record->{id}");
	    } else {
	    update_record($db,'User_auth',$new_record,"id=$record->{id}");
	    }
    return $record->{id};
    }

my $user_subnet=$office_networks->match_string($ip);
if ($user_subnet->{static}) {
    db_log_warning($db,"Unknown ip+mac found in static subnet! Abort create record for ip: $ip mac: [".$mac."]");
    return 0;
    }

#search changed mac
$record=get_record_sql($db,'SELECT * FROM User_auth WHERE `ip_int`='.$ip_aton." and deleted=0");
if ($record->{id}) {
    #if found record with same ip but another mac
    if (!$record->{mac}) {
        db_log_verbose($db,"use empty auth record...");
        $new_record->{mac}=$mac;
	    if ($action!~/arp/i) {
	        $new_record->{dhcp_action}=$action;
	        $new_record->{dhcp_time}=$timestamp;
	        if ($hostname) { $new_record->{dhcp_hostname} = $hostname; }
	            update_record($db,'User_auth',$new_record,"id=$record->{id}");
                } else {
	            update_record($db,'User_auth',$new_record,"id=$record->{id}");
	            }
        return $record->{id};
        }
    if ($record->{mac}) {
        db_log_warning($db,"For ip: $ip mac change detected! Old mac: [".$record->{mac}."] New mac: [".$mac."]. Disable old auth_id: $record->{id}") if (!$ip_record->{hotspot});
        my $disable_record;
        $disable_record->{deleted}="1";
        update_record($db,'User_auth',$disable_record,"id=".$record->{id});
        }
    }

#default user
my $new_user_info=get_new_user_id($db,$ip,$mac,$hostname);
my $new_user_id;
if ($new_user_info->{user_id}) { $new_user_id = $new_user_info->{user_id}; }
if (!$new_user_id) { $new_user_id = new_user($db,$new_user_info); }

my $mac_exists=find_mac_in_subnet($db,$ip,$mac);
#disable dhcp for same mac in one ip subnet
if ($mac_exists and $mac_exists->{'count'}) { $new_record->{dhcp}=0; }

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
    if (!$ip_record->{hotspot}) { db_log_warning($db,"Resurrection auth_id: $resurrection_id with ip: $ip and mac: $mac"); }
	    else { db_log_info($db,"Resurrection auth_id: $resurrection_id with ip: $ip and mac: $mac"); }
    update_record($db,'User_auth',$new_record,"id=$resurrection_id");
    } else {
    #not found ->create new record
    if (!$ip_record->{hotspot}) { db_log_warning($db,"New ip created! ip: $ip mac: $mac"); } else { db_log_info($db,"New ip created! ip: $ip mac: $mac"); }
    insert_record($db,'User_auth',$new_record);
    }
#filter and status
my $cur_auth_id=get_id_record($db,'User_auth',"ip='$ip' and mac='$mac' and deleted=0 ORDER BY last_found DESC");
if ($cur_auth_id) {
    my $user_record=get_record_sql($db,"SELECT * FROM User_list WHERE id=".$new_user_id);
    if ($user_record) {
	    $new_record->{ou_id}=$user_record->{ou_id};
	    $new_record->{comments}=$user_record->{fio};
	    $new_record->{filter_group_id}=$user_record->{filter_group_id};
	    $new_record->{queue_id}=$user_record->{queue_id};
	    $new_record->{enabled}="$user_record->{enabled}";
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
my $new_user_info=get_new_user_id($db,$ip,undef,undef);
my $new_user_id;
if ($new_user_info->{user_id}) { $new_user_id = $new_user_info->{user_id}; }
if ($new_user_info->{ou_id}) { $new_user_id = new_user($db,$new_user_info); }
my $user_record=get_record_sql($db,"SELECT * FROM User_list WHERE id=".$new_user_id);
my $timestamp=GetNowTime();
my $new_record;
$new_record->{ip_int}=$ip_aton;
$new_record->{ip}=$ip;
$new_record->{user_id}=$new_user_id;
$new_record->{save_traf}="$save_detail";
$new_record->{deleted}="0";
$new_record->{dhcp_action}='netflow';
$new_record->{ou_id}=$user_record->{ou_id};
$new_record->{filter_group_id}=$user_record->{filter_group_id};
$new_record->{queue_id}=$user_record->{queue_id};
$new_record->{enabled}="$user_record->{enabled}";
if ($user_record->{fio}) { $new_record->{comments}=$user_record->{fio}; }

my $cur_auth_id=insert_record($db,'User_auth',$new_record);
db_log_warning($db,"New ip created by netflow! ip: $ip") if ($cur_auth_id);
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
    if ($default_option->{'type'}=~/^(int|bool)/i) { $result = $default_option->{'default_value'}*1; };
    if ($default_option->{'type'}=~/^(string|text)/i) { $result = $default_option->{'default_value'}; }
    if ($default_option->{'type'}=~/^list/i) { $result = $default_option->{'default_value'}; }
    return $result;
    }
$result = $config_options->{'value'};
return $result;
}

#---------------------------------------------------------------------------------------------------------------

sub init_option {
my $db=shift;

$last_refresh_config = time();

$config_ref{version}='';
my $version_record = get_record_sql($db,"SELECT version FROM version WHERE version is NOT NULL");
if ($version_record) { $config_ref{version}=$version_record->{version}; }

$config_ref{dbh}=$db;
$config_ref{save_detail}=get_option($db,23);
$config_ref{add_unknown_user}=get_option($db,22);
$config_ref{dhcp_server}=get_option($db,5);
$config_ref{snmp_default_version}=get_option($db,9);
$config_ref{snmp_default_community}=get_option($db,11);
$config_ref{KB}=get_option($db,1);
if ($config_ref{KB} ==0) { $config_ref{KB}=1000; }
if ($config_ref{KB} ==1) { $config_ref{KB}=1024; }
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

$config_ref{auto_mac_rule} = get_option($db,64);

#network configuration mode
$config_ref{config_mode}=get_option($db,68);

#auto clean old user record
$config_ref{clean_empty_user}=get_option($db,69);

#dns_server_type
$config_ref{dns_server}=get_option($db,3);
$config_ref{dns_server_type}=get_option($db,70);
$config_ref{enable_dns_updates}=get_option($db,71);

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
$KB=$config_ref{KB};
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

$history_log_day=get_option($db,47);

$history_syslog_day = get_option($db,48);

$history_trafstat_day = get_option($db,49);

my $ou = get_record_sql($db,"SELECT id FROM OU WHERE default_users = 1");
if (!$ou) { $default_user_ou_id = 0; } else { $default_user_ou_id = $ou->{'id'}; }

$ou = get_record_sql($db,"SELECT id FROM OU WHERE default_hotspot = 1");
if (!$ou) { $default_hotspot_ou_id = $default_user_ou_id; } else { $default_hotspot_ou_id = $ou->{'id'}; }

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
        $office_networks->add_string($net->{subnet},$net);
        }

if ($net->{free}) {
	push(@free_network_list,$net->{subnet});
        $free_networks->add_string($net->{subnet},$net);
        }

if ($net->{vpn}) {
	push(@vpn_network_list,$net->{subnet});
        $vpn_networks->add_string($net->{subnet},$net);
        }

if ($net->{hotspot}) {
        push(@hotspot_network_list,$net->{subnet});
        push(@all_network_list,$net->{subnet});
        $hotspot_networks->add_string($net->{subnet},$net);
        }
push(@all_network_list,$net->{subnet});
$all_networks->add_string($net->{subnet},$net);
}

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

#clean old AD computer cache
my $now = DateTime->now(time_zone=>'local');
my $day_dur = DateTime::Duration->new( days => 1 );
my $clean_date = $now - $day_dur;
my $clean_str = $dbh->quote($clean_date->ymd("-")." 00:00:00");
do_sql($db,"DELETE FROM `ad_comp_cache` WHERE last_found<=$clean_str");
}

#---------------------------------------------------------------------------------------------------------------

#skip init for upgrade
if ($MY_NAME!~/upgrade.pl/) {
    $dbh=init_db();
    init_option($dbh);
    clean_variables($dbh);
    Set_Variable($dbh);
    }

1;
}
