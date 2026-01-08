package eyelib::database;

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
use DateTime;
use POSIX qw(mktime ctime strftime);
use File::Temp qw(tempfile);
use DBI;
use DBD::Pg qw(:pg_types);

our @ISA = qw(Exporter);

our @EXPORT = qw(
StrToIp
IpToStr
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
is_system_ou
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
sub batch_db_sql_cached {
    my ($sql, $data) = @_;

    my $db=init_db();

    eval {
        my $sth = $db->prepare_cached($sql)
            or die "Unable to prepare SQL: " . $db->errstr;

        for my $params (@$data) {
            next unless @$params;
            $sth->execute(@$params)
                or die "Unable to execute with params [" . join(',', @$params) . "]: " . $sth->errstr;
        }

        $db->commit();
        1;
    } or do {
        my $err = $@ || 'Unknown error';
        eval { $db->rollback() };
        $db->disconnect();
        die "batch_db_sql_cached failed: $err";
    };

    $db->disconnect();
    return 1;
}

#---------------------------------------------------------------------------------------------------------------

sub batch_db_sql_csv {
    my ($table, $data) = @_;
    my $db = init_db();
    if ($config_ref{DBTYPE} eq 'mysql') {
        my $fh = File::Temp->new(UNLINK => 1);
        binmode($fh, ':utf8');
        for my $row (@$data) {
            next unless $row && @$row;
            my $line = 'NULL';  # автоинкремент
            for my $val (@$row) {
                $line .= defined($val) ? ',' . $val : ',NULL';
            }
            print $fh $line . "\r\n";
        }
        close $fh;
        my $query = "LOAD DATA LOCAL INFILE '" . $fh->filename . "' INTO TABLE `$table` FIELDS TERMINATED BY ',' LINES TERMINATED BY '\r\n'";
        $db->do($query);
    } else {
        # PostgreSQL: используем COPY ... FROM STDIN
        my $copy_sql = "COPY $table FROM STDIN WITH (FORMAT CSV, DELIMITER ',', NULL 'NULL')";
        $db->do($copy_sql);  # Переключает соединение в режим копирования
        for my $row (@$data) {
            next unless $row && @$row;
            my $line = 'NULL';  # автоинкремент
            for my $val (@$row) {
                $line .= defined($val) ? ',' . $val : ',NULL';
            }
            $line .= "\n";
            $db->pg_put_copy_data($line);
        }
        $db->pg_put_copy_end();  # Завершаем копирование

    }
    $db->disconnect();
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
#my $new_id = do_sql($dbh, 'INSERT INTO user_list (login) VALUES (?)', 'Ivan');
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

# Обновленная функция get_option с параметризованными запросами
sub get_option {
    my $db = shift;
    my $option_id = shift;
    return if (!$option_id);
    return if (!$db);
    my $sql = q{
    SELECT
    COALESCE(c.value, co.default_value) AS value,
    co.option_type
    FROM config_options co
    LEFT JOIN config c ON c.option_id = co.id
    WHERE co.id = ?
    };
    my $record = get_record_sql($db, $sql, $option_id);
    unless ($record) {
        log_error("Option ID $option_id not found in config_options table");
        return;
    }
    return $record->{value};
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
    
    my $sth = $db->prepare($sql) or do {
        log_error("Unable to prepare SQL [$sql]: " . $db->errstr);
        return wantarray ? () : undef;
    };
    
    my $rv = $params ? $sth->execute(@$params) : $sth->execute();
    
    unless ($rv) {
        log_error("Unable to execute SQL [$sql]" . ($params ? " with params: [" . join(', ', @$params) . "]" : "") . ": " . $sth->errstr);
        $sth->finish();
        return wantarray ? () : undef;
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
        return \@rows;
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
}

#---------------------------------------------------------------------------------------------------------------

sub get_records_sql {
my ($db, $sql, @params) = @_;
my @result;
return @result if (!$db);
return @result if (!$sql);
unless (reconnect_db(\$db)) {
    log_error("No database connection available");
    return @result;
    }
my $result_ref = _execute_param($db, $sql, \@params, { mode => 'array' });
if (ref($result_ref) eq 'ARRAY') {
        @result = @$result_ref;
    }
return @result;
}

#---------------------------------------------------------------------------------------------------------------

sub get_record_sql {
my ($db, $sql, @params) = @_;
my @result;
return @result if (!$db);
return @result if (!$sql);
# Добавляем LIMIT только если его еще нет в запросе
if ($sql !~ /\bLIMIT\s+\d+/i && $sql !~ /\bFETCH\s+FIRST\s+\d+/i) {
        $sql .= ' LIMIT 1';
    }
# Переподключение
unless (reconnect_db(\$db)) {
    log_error("No database connection available");
    return;
    }
return _execute_param($db, $sql, \@params, { mode => 'single' });
}

#---------------------------------------------------------------------------------------------------------------

sub get_count_records {
my ($db, $table, $filter, @params) = @_;
my $result = 0;
return $result if (!$db);
return $result if (!$table);
my $sSQL='SELECT COUNT(*) as rec_cnt FROM '.$table;
if ($filter) { $sSQL=$sSQL." WHERE ".$filter; }
my $record = get_record_sql($db,$sSQL, @params);
if ($record->{rec_cnt}) { $result = $record->{rec_cnt}; }
return $result;
}

#---------------------------------------------------------------------------------------------------------------

sub get_id_record {
my ($db, $table, $filter, @params) = @_;
my $result = 0;
return $result if (!$db);
return $result if (!$table);
my $record = get_record_sql($db,"SELECT id FROM $table WHERE $filter", @params);
if ($record->{id}) { $result = $record->{id}; }
return $result;
}

#---------------------------------------------------------------------------------------------------------------

sub get_diff_rec {
my ($db, $table, $record, $filter_sql, @filter_params) = @_;
return unless $db && $table && $filter_sql;

unless (reconnect_db(\$db)) {
    log_error("No database connection available");
    return;
    }
my $old_record = get_record_sql($db,"SELECT * FROM $table WHERE $filter_sql",@filter_params);
return unless $old_record;
my $result;
foreach my $field (keys %$record) {
    if (!$record->{$field}) { $record->{$field}=''; }
    if (!$old_record->{$field}) { $old_record->{$field}=''; }
    if ($record->{$field}!~/^$old_record->{$field}$/) { $result->{$field} = "$record->{$field} [ old: " . $old_record->{$field} . "]"; }
    }
return hast_to_txt($result);
}

#---------------------------------------------------------------------------------------------------------------

sub insert_record {
my ($db, $table, $record) = @_;
return unless $db && $table;

unless (reconnect_db(\$db)) {
    log_error("No database connection available");
    return;
    }

my $dns_changed = 0;
my $rec_id = 0;

if ($table eq "user_auth") {
    foreach my $field (keys %$record) {
	if (exists $acl_fields{$field}) { $record->{changed}="1"; }
	if (exists $dhcp_fields{$field}) { $record->{dhcp_changed}="1"; }
	if (exists $dns_fields{$field}) { $dns_changed=1; }
	}
    }

my @insert_params;
my $fields = '';
my $values = '';
my $new_str = '';

foreach my $field (keys %$record) {
    my $val = defined $record->{$field} ? $record->{$field} : undef;
    # Экранируем имя поля в зависимости от СУБД
    my $quoted_field = $config_ref{DBTYPE} eq 'mysql'
        ? '`' . $field . '`'
        : '"' . $field . '"';
    $fields .= "$quoted_field, ";
    $values .= "?, ";
    push @insert_params, $val;
    # Для лога — безопасное представление
    my $log_val = defined $val ? substr($val, 0, 200) : 'NULL';
    $log_val =~ s/[^[:print:]]/_/g;
    $new_str .= " $field => $log_val,";
}

$fields =~ s/,\s*$//;
$values =~ s/,\s*$//;

my $sSQL = "INSERT INTO $table($fields) VALUES($values)";
my $result = do_sql($db,$sSQL,@insert_params);
if ($result) {
    $rec_id = $result if ($table eq "user_auth");
    $new_str='id: '.$result.' '.$new_str;
    if ($table eq 'user_auth_alias' and $dns_changed) {
	if ($record->{'alias'} and $record->{'alias'}!~/\.$/) {
	    my $add_dns;
	    $add_dns->{'name_type'}='CNAME';
	    $add_dns->{'name'}=$record->{'alias'};
	    $add_dns->{'value'}=get_dns_name($db,$record->{'auth_id'});
	    $add_dns->{'operation_type'}='add';
	    $add_dns->{'auth_id'}=$record->{'auth_id'};
	    insert_record($db,'dns_queue',$add_dns);
	    }
	}
    if ($table eq 'user_auth' and $dns_changed) {
	if ($record->{'dns_name'} and $record->{'ip'} and !$record->{'dns_ptr_only'} and $record->{'dns_name'}!~/\.$/) {
	    my $add_dns;
	    $add_dns->{'name_type'}='A';
	    $add_dns->{'name'}=$record->{'dns_name'};
	    $add_dns->{'value'}=$record->{'ip'};
	    $add_dns->{'operation_type'}='add';
	    $add_dns->{'auth_id'}=$result;
	    insert_record($db,'dns_queue',$add_dns);
	    }
	if ($record->{'dns_name'} and $record->{'ip'} and $record->{'dns_ptr_only'} and $record->{'dns_name'}!~/\.$/) {
	    my $add_dns;
	    $add_dns->{'name_type'}='PTR';
	    $add_dns->{'name'}=$record->{'dns_name'};
	    $add_dns->{'value'}=$record->{'ip'};
	    $add_dns->{'operation_type'}='add';
	    $add_dns->{'auth_id'}=$result;
	    insert_record($db,'dns_queue',$add_dns);
	    }
	}
    }
db_log_debug($db,'Add record to table '.$table.' '.$new_str,$rec_id);
return $result;
}

#---------------------------------------------------------------------------------------------------------------

sub update_record {
my ($db, $table, $record, $filter_sql, @filter_params) = @_;

return unless $db && $table && $filter_sql;

unless (reconnect_db(\$db)) {
    log_error("No database connection available");
    return;
    }

my $select_sql = "SELECT * FROM $table WHERE $filter_sql";
my $old_record = get_record_sql($db, $select_sql, @filter_params);
return unless $old_record;

my @update_params;
my $set_clause = '';
my $dns_changed = 0;
my $rec_id = $old_record->{id} || 0;

if ($table eq "user_auth") {
    $rec_id = $old_record->{'id'} if ($old_record->{'id'});
    my $cur_ou_id = $old_record->{'ou_id'} if ($old_record->{'ou_id'});
    if (exists $record->{ou_id}) { $cur_ou_id = $record->{'ou_id'}; }
    #disable update field 'created_by'
    if ($old_record->{'created_by'} and exists ($record->{'created_by'})) { delete $record->{'created_by'}; }
    foreach my $field (keys %$record) {
	if (exists $acl_fields{$field}) { $record->{changed}="1"; }
        if (exists $dhcp_fields{$field} and !is_system_ou($db,$cur_ou_id)) { $record->{dhcp_changed}="1"; }
	if (exists $dns_fields{$field}) { $dns_changed=1; }
        }
    }

my $diff = '';
for my $field (keys %$record) {
        my $old_val = defined $old_record->{$field} ? $old_record->{$field} : '';
        my $new_val = defined $record->{$field} ? $record->{$field} : '';
        if ($new_val ne $old_val) {
            $diff .= " $field => $new_val (old: $old_val),";
            $set_clause .= " $field = ?, ";
            push @update_params, $new_val;
        }
    }

return 1 unless $set_clause;

# Добавляем служебные поля
if ($table eq 'user_auth') {
        $set_clause .= "changed_time = ?, ";
        push @update_params, GetNowTime();
    }

$set_clause =~ s/,\s*$//;
$diff =~ s/,\s*$//;

if ($table eq 'user_auth') {
	if ($dns_changed) {
	    my $del_dns;
	    if ($old_record->{'dns_name'} and $old_record->{'ip'} and !$old_record->{'dns_ptr_only'} and $old_record->{'dns_name'}!~/\.$/) {
		    $del_dns->{'name_type'}='A';
		    $del_dns->{'name'}=$old_record->{'dns_name'};
		    $del_dns->{'value'}=$old_record->{'ip'};
		    $del_dns->{'operation_type'}='del';
		    if ($rec_id) { $del_dns->{'auth_id'}=$rec_id; }
		    insert_record($db,'dns_queue',$del_dns);
		    }
	    if ($old_record->{'dns_name'} and $old_record->{'ip'} and $old_record->{'dns_ptr_only'} and $old_record->{'dns_name'}!~/\.$/) {
		    $del_dns->{'name_type'}='PTR';
		    $del_dns->{'name'}=$old_record->{'dns_name'};
		    $del_dns->{'value'}=$old_record->{'ip'};
		    $del_dns->{'operation_type'}='del';
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
		$new_dns->{'operation_type'}='add';
		if ($rec_id) { $new_dns->{'auth_id'}=$rec_id; }
		insert_record($db,'dns_queue',$new_dns);
		}
	    if ($dns_rec_name and $dns_rec_ip and $record->{'dns_ptr_only'} and $record->{'dns_name'}!~/\.$/) {
		$new_dns->{'name_type'}='PTR';
		$new_dns->{'name'}=$dns_rec_name;
		$new_dns->{'value'}=$dns_rec_ip;
		$new_dns->{'operation_type'}='add';
		if ($rec_id) { $new_dns->{'auth_id'}=$rec_id; }
		insert_record($db,'dns_queue',$new_dns);
		}
	    }
	}

if ($table eq 'user_auth_alias') {
	if ($dns_changed) {
	    my $del_dns;
	    if ($old_record->{'alias'} and $old_record->{'alias'}!~/\.$/) {
	    $del_dns->{'name_type'}='CNAME';
	    $del_dns->{'name'}=$old_record->{'alias'};
	    $del_dns->{'operation_type'}='del';
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
		$new_dns->{'operation_type'}='add';
		$new_dns->{'value'}=get_dns_name($db,$old_record->{auth_id});
		$new_dns->{'auth_id'}=$rec_id;
		insert_record($db,'dns_queue',$new_dns);
		}
	    }
	}

# Формируем полный список параметров: сначала SET, потом WHERE
my @all_params = (@update_params, @filter_params);
my $update_sql = "UPDATE $table SET $set_clause WHERE $filter_sql";
db_log_debug($db, "Change table $table for $filter_sql set: $diff", $rec_id);
return do_sql($db, $update_sql, @all_params);
}


#---------------------------------------------------------------------------------------------------------------

sub delete_record {
my ($db, $table, $filter_sql, @filter_params) = @_;
return unless $db && $table && $filter_sql;

unless (reconnect_db(\$db)) {
    log_error("No database connection available");
    return;
    }

my $select_sql = "SELECT * FROM $table WHERE $filter_sql";
my $old_record = get_record_sql($db, $select_sql, @filter_params);
return unless $old_record;

my $rec_id = 0;

my $diff='';
foreach my $field (keys %$old_record) {
    next if (!$old_record->{$field});
    $diff = $diff." $field => $old_record->{$field},";
    }
$diff=~s/,\s*$//;

if ($table eq 'user_auth') {
    $rec_id = $old_record->{'id'} if ($old_record->{'id'});
    }

db_log_debug($db,'Delete record from table '.$table.' value: '.$diff, $rec_id);

#never delete user ip record!
if ($table eq 'user_auth') {
    my $sSQL = "UPDATE user_auth SET changed = 1, deleted = 1, changed_time = ? WHERE $filter_sql";
    my $ret = do_sql($db, $sSQL, GetNowTime(), @filter_params);
    if ($old_record->{'dns_name'} and $old_record->{'ip'} and !$old_record->{'dns_ptr_only'} and $old_record->{'dns_name'}!~/\.$/) {
	my $del_dns;
	$del_dns->{'name_type'}='A';
	$del_dns->{'name'}=$old_record->{'dns_name'};
	$del_dns->{'value'}=$old_record->{'ip'};
	$del_dns->{'operation_type'}='del';
	$del_dns->{'auth_id'}=$old_record->{'id'};
	insert_record($db,'dns_queue',$del_dns);
	}
    if ($old_record->{'dns_name'} and $old_record->{'ip'} and $old_record->{'dns_ptr_only'} and $old_record->{'dns_name'}!~/\.$/) {
	my $del_dns;
	$del_dns->{'name_type'}='PTR';
	$del_dns->{'name'}=$old_record->{'dns_name'};
	$del_dns->{'value'}=$old_record->{'ip'};
	$del_dns->{'operation_type'}='del';
	$del_dns->{'auth_id'}=$old_record->{'id'};
	insert_record($db,'dns_queue',$del_dns);
	}
    return $ret;
    }

if ($table eq 'user_list' and $old_record->{'permanent'}) { return; }

if ($table eq 'user_auth_alias') {
    if ($old_record->{'alias'} and $old_record->{'auth_id'} and $old_record->{'alias'}!~/\.$/) {
	my $del_dns;
	$del_dns->{'name_type'}='CNAME';
	$del_dns->{'name'}=$old_record->{'alias'};
	$del_dns->{'value'}=get_dns_name($db,$old_record->{'auth_id'});
	$del_dns->{'operation_type'}='del';
	$del_dns->{'auth_id'}=$old_record->{'auth_id'};
	insert_record($db,'dns_queue',$del_dns);
	}
    }

my $sSQL = "DELETE FROM ".$table." WHERE ".$filter_sql;
return do_sql($db,$sSQL,@filter_params);
}

#---------------------------------------------------------------------------------------------------------------

sub is_system_ou {
    my ($db, $ou_id) = @_;
    return 0 if !defined $ou_id || $ou_id !~ /^\d+$/ || $ou_id <= 0;
    my $sql = "SELECT 1 FROM ou WHERE id = ? AND (default_users = 1 OR default_hotspot = 1)";
    my $record = get_record_sql($db, $sql, $ou_id);
    return $record ? 1 : 0;
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

my $ou = get_record_sql($db,"SELECT id FROM ou WHERE default_users = 1");
if (!$ou) { $default_user_ou_id = 0; } else { $default_user_ou_id = $ou->{'id'}; }

$ou = get_record_sql($db,"SELECT id FROM ou WHERE default_hotspot = 1 ");
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
    my ($db, $name, $value, $timeshift) = @_;
    $name //= $MY_NAME;
    $value //= $$;
    $timeshift //= 60;

    Del_Variable($db, $name);

    my $clean_time = time() + $timeshift;
    my ($sec, $min, $hour, $day, $month, $year) = localtime($clean_time);
    $month++;
    $year += 1900;
    my $clear_time_str = sprintf "%04d-%02d-%02d %02d:%02d:%02d", $year, $month, $day, $hour, $min, $sec;

    my $sql = "INSERT INTO variables (name, value, clear_time) VALUES (?, ?, ?)";
    do_sql($db, $sql, $name, $value, $clear_time_str);
}

#---------------------------------------------------------------------------------------------------------------

sub Get_Variable {
    my $db = shift;
    my $name = shift || $MY_NAME;
    my $variable = get_record_sql($db, 'SELECT value FROM variables WHERE name = ?', $name);
    if ($variable and $variable->{'value'}) { return $variable->{'value'}; }
    return;
}

#---------------------------------------------------------------------------------------------------------------

sub Del_Variable {
    my ($db, $name) = @_;
    $name //= $MY_NAME;
    do_sql($db, "DELETE FROM variables WHERE name = ?", $name);
}

#---------------------------------------------------------------------------------------------------------------

sub clean_variables {
    my ($db) = @_;

    # 1. Clean temporary variables
    my $now = time();
    my ($sec, $min, $hour, $day, $month, $year) = localtime($now);
    $month++;
    $year += 1900;
    my $now_str = sprintf "%04d-%02d-%02d %02d:%02d:%02d", $year, $month, $day, $hour, $min, $sec;

    do_sql($db, "DELETE FROM variables WHERE clear_time <= ?", $now_str);

    # 2. Clean old AD computer cache
    my $yesterday = DateTime->now(time_zone => 'local')->subtract(days => 1);
    my $clean_str = $yesterday->strftime("%Y-%m-%d 00:00:00");

    do_sql($db, "DELETE FROM ad_comp_cache WHERE last_found <= ?", $clean_str);
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
