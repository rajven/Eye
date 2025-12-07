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
use DBD::Pg qw(:pg_types);

our @ISA = qw(Exporter);

our @EXPORT = qw(
StrToIp
IpToStr
batch_db_sql
batch_db_sql_cached
batch_db_sql_csv
reconnect_db
write_db_log
db_log_debug
db_log_error
db_log_info
db_log_verbose
db_log_warning
init_db
do_sql
_execute_param
do_sql_param
get_record_sql_param
get_records_sql_param
get_scalar_sql_param
get_count_sql_param
get_option_safe
get_count_records
get_id_record
get_records_sql
get_record_sql
get_diff_rec
update_record
insert_record
delete_record
get_option
init_option
Set_Variable
Get_Variable
Del_Variable
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
'dhcp_acl'=>'1',
'dhcp_option_set'=>'1',
'dhcp'=>'1',
'deleted'=>'1',
'mac'=>'1',
);

our %dns_fields = (
'ip' => '1',
'dns_name'=>'1',
'dns_ptr_only'=>'1',
'alias'=>'1',
);

#---------------------------------------------------------------------------------------------------------------

sub StrToIp {
return unpack('N',pack('C4',split(/\./,$_[0])));
}

#---------------------------------------------------------------------------------------------------------------

sub IpToStr {
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
my $db = DBI->connect("dbi:$config_ref{DBTYPE}:dbname=$DBNAME;host=$DBHOST","$DBUSER","$DBPASS", { RaiseError => 0, AutoCommit => 0 });
if ( !defined $db ) { die "Cannot connect to $config_ref{DBTYPE} server: $DBI::errstr\n"; }
if ($config_ref{DBTYPE} eq 'mysql') {
$db->do('SET NAMES utf8mb4');
$db->{'mysql_enable_utf8'} = 1;
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
$db = DBI->connect("dbi:$config_ref{DBTYPE}:dbname=$DBNAME;host=$DBHOST","$DBUSER","$DBPASS", { RaiseError => 1, mysql_local_infile=> 1 });
} else {
$db = DBI->connect("dbi:$config_ref{DBTYPE}:dbname=$DBNAME;host=$DBHOST","$DBUSER","$DBPASS", { RaiseError => 1 });
}
if ( !defined $db ) { die "Cannot connect to $config_ref{DBTYPE} server: $DBI::errstr\n"; }

return if (!$db);

if ($config_ref{DBTYPE} eq 'mysql') {
$db->do('SET NAMES utf8mb4');
$db->{'mysql_enable_utf8'} = 1;
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

if ($config_ref{DBTYPE} eq 'mysql') {
my $query = qq{ LOAD DATA LOCAL INFILE '$fname' INTO TABLE $table FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '"' LINES TERMINATED BY '\r\n'; };
$db->do($query);
} else {
# PostgreSQL использует COPY
my $query = qq{ COPY $table FROM STDIN WITH (FORMAT CSV, DELIMITER ',', QUOTE '"', NULL 'NULL'); };
$db->do($query, undef, $fh);
}
$db->disconnect;
File::Temp::cleanup();
}

#---------------------------------------------------------------------------------------------------------------

sub reconnect_db {
my $db_ref = shift;
# Если соединение активно, ничего не делаем
if ($$db_ref && $$db_ref->ping) {
return 1;
}
# Переподключаемся
eval {
# Закрываем старое соединение если есть
if ($$db_ref) {
$$db_ref->disconnect;
$$db_ref = undef;
}
# Создаем новое соединение
$$db_ref = init_db();
# Проверяем что соединение установлено
unless ($$db_ref && $$db_ref->ping) {
die "Failed to establish database connection";
}
1;  # возвращаем истину при успехе
} or do {
my $error = $@ || 'Unknown error';
warn "Database reconnection failed: $error";
$$db_ref = undef;
return 0;
};
return 1;
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

# Переподключение
unless (reconnect_db(\$db)) {
log_error("No database connection available");
$db_log = 0;
}

if ($level eq $L_ERROR and $log_level >= $L_ERROR) { log_error($msg); $db_log = 1; }
if ($level eq $L_WARNING and $log_level >= $L_WARNING) { log_warning($msg); $db_log = 1; }
if ($level eq $L_INFO and $log_level >= $L_INFO) { log_info($msg); $db_log = 1; }
if ($level eq $L_VERBOSE and $log_level >= $L_VERBOSE) { log_verbose($msg); $db_log = 1; }
if ($level eq $L_DEBUG and $log_level >= $L_DEBUG) { log_debug($msg); return; }

if ($db_log) {
#my $new_id = do_sql($dbh, 'INSERT INTO User_list (login) VALUES (?)', 'Ivan');
do_sql($db,'INSERT INTO worklog(customer,message,level,auth_id,ip) VALUES( ?, ?, ?, ?, ?)',$MY_NAME,$msg,$level,$auth_id,$config_ref{self_ip});
}
}

#---------------------------------------------------------------------------------------------------------------

sub db_log_debug {
my $db = shift;
my $msg = shift;
my $id = shift;
if ($debug) { log_debug($msg); }
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
if ($log_level >= $L_WARNING) { write_db_log($db,$msg,$L_WARNING,$id); }
}

#---------------------------------------------------------------------------------------------------------------

sub init_db {
# Create new database handle. If we can't connect, die()
my $db;
if ($config_ref{DBTYPE} eq 'mysql') {
$db = DBI->connect("dbi:mysql:database=$DBNAME;host=$DBHOST","$DBUSER","$DBPASS", 
    { RaiseError => 0, AutoCommit => 1, mysql_enable_utf8 => 1 });
if ( !defined $db ) { die "Cannot connect to MySQL server: $DBI::errstr\n"; }
$db->do('SET NAMES utf8mb4');
} else {
$db = DBI->connect("dbi:Pg:dbname=$DBNAME;host=$DBHOST","$DBUSER","$DBPASS",
    { RaiseError => 0, AutoCommit => 1, pg_enable_utf8 => 1, pg_server_prepare => 0 });
if ( !defined $db ) { die "Cannot connect to PostgreSQL server: $DBI::errstr\n"; }
}
return $db;
}

#---------------------------------------------------------------------------------------------------------------

sub do_sql {
my ($db, $sql, @bind_values) = @_;
return 0 unless $db;
return 0 unless $sql;

unless (reconnect_db(\$db)) {
log_error("No database connection available for SQL: $sql");
return 0;
}

# Логируем не-SELECT-запросы
log_debug( $sql . (@bind_values ? ' | bind: [' . join(', ', map { defined $_ ? $_ : 'undef' } @bind_values) . ']' : '')) unless $sql =~ /^select /i;
# Подготовка запроса
my $sth = $db->prepare($sql) or do {
log_error("Unable to prepare SQL [$sql]: " . $db->errstr);
return 0;
};
# Выполнение запроса
my $rv;
if (@bind_values) {
$rv = $sth->execute(@bind_values) or do {
log_error("Unable to execute SQL [$sql] with bind: [" . join(', ', map { defined $_ ? $_ : 'undef' } @bind_values) . "]: " . $sth->errstr);
return 0;
};
} else {
$rv = $sth->execute() or do {
log_error("Unable to execute SQL [$sql]: " . $sth->errstr);
return 0;
};
}
# Обработка результатов по типу запроса
if ($sql =~ /^insert/i) {
my $id;
if ($config_ref{DBTYPE} and $config_ref{DBTYPE} eq 'mysql') {
$id = $sth->{mysql_insertid};
} else {
($id) = $db->selectrow_array("SELECT lastval()");
}
$sth->finish();
return $id || 0;  # Возвращаем ID или 0 если ID нет
}
elsif ($sql =~ /^select /i) {
my $data = $sth->fetchall_arrayref({});
$sth->finish();
return $data;  # возвращаем ссылку на массив
}
else {
# UPDATE, DELETE, CREATE, ALTER и т.д.
$sth->finish();
return 1;
}
}

#---------------------------------------------------------------------------------------------------------------

# Внутренняя функция для выполнения параметризованных запросов
sub _execute_param {
    my ($db, $sql, $params, $options) = @_;
    
    return unless $db && $sql;
    
    # Логируем не-SELECT-запросы
    unless ($sql =~ /^\s*SELECT/i) {
        log_debug( $sql . ($params ? ' | params: [' . join(', ', map { defined $_ ? $_ : 'undef' } @$params) . ']' : ''));
    }
    
    # Переподключение
    unless (reconnect_db(\$db)) {
        log_error("No database connection available");
        return wantarray ? () : undef;
    }
    
    my $mode = $options->{mode} || 'execute';
    
    eval {
        my $sth = $db->prepare($sql) or die "Unable to prepare SQL [$sql]: " . $db->errstr;
        
        my $rv = $params ? $sth->execute(@$params) : $sth->execute();
        unless ($rv) {
            die "Unable to execute SQL [$sql]" . ($params ? " with params: [" . join(', ', @$params) . "]" : "") . ": " . $sth->errstr;
        }
        
        if ($mode eq 'single') {
            my $row = $sth->fetchrow_hashref();
            $sth->finish();
            return $row;
        }
        elsif ($mode eq 'array') {
            my @rows;
            while (my $row = $sth->fetchrow_hashref()) {
                push @rows, $row;
            }
            $sth->finish();
            return @rows;
        }
        elsif ($mode eq 'arrayref') {
            my $rows = $sth->fetchall_arrayref({});
            $sth->finish();
            return $rows;
        }
        elsif ($mode eq 'scalar') {
            my $row = $sth->fetchrow_arrayref();
            $sth->finish();
            return $row ? $row->[0] : undef;
        }
        elsif ($mode eq 'id') {
            if ($sql =~ /^\s*INSERT/i) {
                my $id;
                if ($config_ref{DBTYPE} and $config_ref{DBTYPE} eq 'mysql') {
                    $id = $sth->{mysql_insertid};
                } else {
                    ($id) = $db->selectrow_array("SELECT lastval()");
                }
                $sth->finish();
                return $id || 0;
            }
            $sth->finish();
            return 1;
        }
        else {
            $sth->finish();
            return 1;
        }
    };
    
    if ($@) {
        log_error("Error executing SQL [$sql]: " . $@);
        return wantarray ? () : undef;
    }
}

#---------------------------------------------------------------------------------------------------------------

# Выполнение SQL с параметрами (новая безопасная версия)
sub do_sql_param {
    my ($db, $sql, @params) = @_;
    return 0 unless $db && $sql;
    
    # Определяем режим по типу запроса
    my $mode = 'execute';
    if ($sql =~ /^\s*SELECT/i) {
        $mode = 'arrayref';
    } elsif ($sql =~ /^\s*INSERT/i) {
        $mode = 'id';
    }
    
    return _execute_param($db, $sql, \@params, { mode => $mode });
}

#---------------------------------------------------------------------------------------------------------------

# Получение одной записи с параметрами
sub get_record_sql_param {
    my ($db, $sql, @params) = @_;
    return unless $db && $sql;
    
    # Добавляем LIMIT только если его еще нет в запросе
    if ($sql !~ /\bLIMIT\s+\d+/i && $sql !~ /\bFETCH\s+FIRST\s+\d+/i) {
        $sql .= ' LIMIT 1';
    }
    
    return _execute_param($db, $sql, \@params, { mode => 'single' });
}

#---------------------------------------------------------------------------------------------------------------

# Получение нескольких записей с параметрами
sub get_records_sql_param {
    my ($db, $sql, @params) = @_;
    return unless $db && $sql;
    return _execute_param($db, $sql, \@params, { mode => 'array' });
}

#---------------------------------------------------------------------------------------------------------------

# Получение скалярного значения с параметрами
sub get_scalar_sql_param {
    my ($db, $sql, @params) = @_;
    return unless $db && $sql;
    
    # Добавляем LIMIT только если его еще нет в запросе
    if ($sql !~ /\bLIMIT\s+\d+/i && $sql !~ /\bFETCH\s+FIRST\s+\d+/i) {
        $sql .= ' LIMIT 1';
    }
    
    return _execute_param($db, $sql, \@params, { mode => 'scalar' });
}

#---------------------------------------------------------------------------------------------------------------

# Получение количества записей с параметрами
sub get_count_sql_param {
    my ($db, $sql, @params) = @_;
    return 0 unless $db && $sql;
    
    # Если это простой SELECT COUNT(*), используем скаляр
    if ($sql =~ /SELECT\s+COUNT\(/i) {
        return _execute_param($db, $sql, \@params, { mode => 'scalar' });
    }
    
    # Иначе получаем запись и извлекаем count
    my $record = get_record_sql_param($db, $sql, @params);
    return $record ? $record->{count} || $record->{cnt} || $record->{total} || 0 : 0;
}

#---------------------------------------------------------------------------------------------------------------

# Обновленная функция get_option с параметризованными запросами (опционально)
sub get_option_safe {
    my $db = shift;
    my $option_id = shift;
    return if (!$option_id);
    return if (!$db);
    
    # Один безопасный запрос вместо двух
    my $sql = q{
        SELECT 
            COALESCE(c.value, co.default_value) as value,
            co.type
        FROM config_options co
        LEFT JOIN config c ON c.option_id = co.id AND c.option_id = ?
        WHERE co.id = ?
        LIMIT 1
    };
    
    my $record = get_record_sql_param($db, $sql, $option_id, $option_id);
    
    unless ($record) {
        log_error("Option ID $option_id not found in config_options table");
        return;
    }
    
    my $result = $record->{value};
    
    # Приводим к правильному типу
    if ($record->{type} =~ /^(int|bool)/i) { 
        $result = $result * 1; 
    }
    
    return $result;
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
unless (reconnect_db(\$db)) {
log_error("No database connection available");
return 0;
}
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
# Переподключение
unless (reconnect_db(\$db)) {
log_error("No database connection available");
return;
}
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

sub get_diff_rec {
my $db = shift;
my $table = shift;
my $value = shift;
my $filter = shift;
return if (!$db);
return if (!$table);
return if (!$filter);
my $old_value = get_record_sql($db,"SELECT * FROM $table WHERE $filter");
my $result;
foreach my $field (keys %$value) {
if (!$value->{$field}) { $value->{$field}=''; }
if (!$old_value->{$field}) { $old_value->{$field}=''; }
if ($value->{$field}!~/^$old_value->{$field}$/) { $result->{$field} = "$value->{$field} [ old: " . $old_value->{$field} . "]"; }
}
return hast_to_txt($result);
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

# Переподключение
unless (reconnect_db(\$db)) {
log_error("No database connection available");
return;
}

my $old_record = get_record_sql($db,"SELECT * FROM $table WHERE $filter");
my $diff='';
my $change_str='';
my $found_changed=0;

my $rec_id = 0;
my $dns_changed = 0;

if ($table eq "User_auth") {
$rec_id = $old_record->{'id'} if ($old_record->{'id'});
#disable update field 'created_by'
if ($old_record->{'created_by'} and exists ($record->{'created_by'})) { delete $record->{'created_by'}; }
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
# Разные методы экранирования для разных БД
if ($config_ref{DBTYPE} eq 'mysql') {
$change_str = $change_str." `$field`=".$db->quote($record->{$field}).",";
} else {
$change_str = $change_str." \"$field\"=".$db->quote($record->{$field}).",";
}
$found_changed++;
}
}

if ($found_changed) {
$change_str=~s/\,$//;
$diff=~s/\,$//;
if ($table eq 'User_auth') {
$change_str .= ", changed_time='".GetNowTime()."'";
if ($dns_changed) {
my $del_dns;
if ($old_record->{'dns_name'} and $old_record->{'ip'} and !$old_record->{'dns_ptr_only'} and $old_record->{'dns_name'}!~/\.$/) {
$del_dns->{'name_type'}='A';
$del_dns->{'name'}=$old_record->{'dns_name'};
$del_dns->{'value'}=$old_record->{'ip'};
$del_dns->{'type'}='del';
if ($rec_id) { $del_dns->{'auth_id'}=$rec_id; }
insert_record($db,'dns_queue',$del_dns);
}
if ($old_record->{'dns_name'} and $old_record->{'ip'} and $old_record->{'dns_ptr_only'} and $old_record->{'dns_name'}!~/\.$/) {
$del_dns->{'name_type'}='PTR';
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
if ($dns_rec_name and $dns_rec_ip and !$record->{'dns_ptr_only'} and $record->{'dns_name'}!~/\.$/) {
$new_dns->{'name_type'}='A';
$new_dns->{'name'}=$dns_rec_name;
$new_dns->{'value'}=$dns_rec_ip;
$new_dns->{'type'}='add';
if ($rec_id) { $new_dns->{'auth_id'}=$rec_id; }
insert_record($db,'dns_queue',$new_dns);
}
if ($dns_rec_name and $dns_rec_ip and $record->{'dns_ptr_only'} and $record->{'dns_name'}!~/\.$/) {
$new_dns->{'name_type'}='PTR';
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
if ($old_record->{'alias'} and $old_record->{'alias'}!~/\.$/) {
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
if ($dns_rec_name and $record->{'alias'}!~/\.$/) {
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

my $rec_id = 0;
my $dns_changed = 0;

# Переподключение
unless (reconnect_db(\$db)) {
log_error("No database connection available");
return;
}

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
# Разные методы экранирования имен полей для разных БД
if ($config_ref{DBTYPE} eq 'mysql') {
$fields = $fields."`$field`,";
} else {
$fields = $fields."\"$field\",";
}
$values = $values." ".$db->quote($record->{$field}).",";
$new_str = $new_str." $field => $record->{$field},";
}

$fields=~s/,$//;
$values=~s/,$//;
$new_str=~s/,$//;

my $sSQL = "INSERT INTO $table($fields) VALUES($values)";
my $result = do_sql($db,$sSQL);
if ($result) {
$rec_id = $result if ($table eq "User_auth");
$new_str='id: '.$result.' '.$new_str;
if ($table eq 'User_auth_alias' and $dns_changed) {
if ($record->{'alias'} and $record->{'alias'}!~/\.$/) {
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
if ($record->{'dns_name'} and $record->{'ip'} and $dns_changed and !$record->{'dns_ptr_only'} and $record->{'dns_name'}!~/\.$/) {
my $add_dns;
$add_dns->{'name_type'}='A';
$add_dns->{'name'}=$record->{'dns_name'};
$add_dns->{'value'}=$record->{'ip'};
$add_dns->{'type'}='add';
$add_dns->{'auth_id'}=$result;
insert_record($db,'dns_queue',$add_dns);
}
if ($record->{'dns_name'} and $record->{'ip'} and $dns_changed and $record->{'dns_ptr_only'} and $record->{'dns_name'}!~/\.$/) {
my $add_dns;
$add_dns->{'name_type'}='PTR';
$add_dns->{'name'}=$record->{'dns_name'};
$add_dns->{'value'}=$record->{'ip'};
$add_dns->{'type'}='add';
$add_dns->{'auth_id'}=$result;
insert_record($db,'dns_queue',$add_dns);
}
}
}
db_log_debug($db,'Add record to table '.$table.' '.$new_str,$rec_id);
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
my $rec_id = 0;

# Переподключение
unless (reconnect_db(\$db)) {
log_error("No database connection available");
return;
}

my $old_record = get_record_sql($db,"SELECT * FROM $table WHERE $filter");

my $diff='';
foreach my $field (keys %$old_record) {
if (!$old_record->{$field}) { $old_record->{$field}=''; }
$diff = $diff." $field => $old_record->{$field},";
}
$diff=~s/,$//;

if ($table eq 'User_auth') {
$rec_id = $old_record->{'id'} if ($old_record->{'id'});
}

db_log_debug($db,'Delete record from table  '.$table.' value: '.$diff, $rec_id);
#never delete user ip record!
if ($table eq 'User_auth') {
my $sSQL = "UPDATE User_auth SET changed=1, deleted=1, changed_time='".GetNowTime()."' WHERE ".$filter;
my $ret = do_sql($db,$sSQL);
if ($old_record->{'dns_name'} and $old_record->{'ip'} and !$old_record->{'dns_ptr_only'} and $old_record->{'dns_name'}!~/\.$/) {
my $del_dns;
$del_dns->{'name_type'}='A';
$del_dns->{'name'}=$old_record->{'dns_name'};
$del_dns->{'value'}=$old_record->{'ip'};
$del_dns->{'type'}='del';
$del_dns->{'auth_id'}=$old_record->{'id'};
insert_record($db,'dns_queue',$del_dns);
}
if ($old_record->{'dns_name'} and $old_record->{'ip'} and $old_record->{'dns_ptr_only'} and $old_record->{'dns_name'}!~/\.$/) {
my $del_dns;
$del_dns->{'name_type'}='PTR';
$del_dns->{'name'}=$old_record->{'dns_name'};
$del_dns->{'value'}=$old_record->{'ip'};
$del_dns->{'type'}='del';
$del_dns->{'auth_id'}=$old_record->{'id'};
insert_record($db,'dns_queue',$del_dns);
}
return $ret;
}

if ($table eq 'User_list' and $old_record->{'permanent'}) { return; }

if ($table eq 'User_auth_alias') {
if ($old_record->{'alias'} and $old_record->{'auth_id'} and $old_record->{'alias'}!~/\.$/) {
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

$config_ref{self_ip} = '127.0.0.1';
if ($DBHOST ne '127.0.0.1') {
my $ip_route = qx(ip r get $DBHOST 2>&1 | head -1);
if ($? == 0) {
if ($ip_route =~ /src\s+(\d+\.\d+\.\d+\.\d+)/) { $config_ref{self_ip} = $1; }
}
}

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

$ou = get_record_sql($db,"SELECT id FROM OU WHERE default_hotspot = 1 ");
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
my $variable=get_record_sql($db,'SELECT value FROM variables WHERE name=\''.$name.'\'');
if ($variable and $variable->{'value'}) { return $variable->{'value'}; }
return;
}

#---------------------------------------------------------------------------------------------------------------

sub Del_Variable {
my $db = shift;
my $name = shift || $MY_NAME;
do_sql($db,"DELETE FROM variables WHERE name='".$name."';");
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
do_sql($db,"DELETE FROM variables WHERE clear_time<=$clean_variables_date");

#clean old AD computer cache
my $now = DateTime->now(time_zone=>'local');
my $day_dur = DateTime::Duration->new( days => 1 );
my $clean_date = $now - $day_dur;
my $clean_str = $db->quote($clean_date->ymd("-")." 00:00:00");
do_sql($db,"DELETE FROM ad_comp_cache WHERE last_found<=$clean_str");
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
