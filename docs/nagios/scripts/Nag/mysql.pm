package Nag::mysql;

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use utf8;
use strict;
use English;
use FindBin '$Bin';
use lib "$Bin";
use base 'Exporter';
use vars qw(@EXPORT @ISA);
use Net::Patricia;
use Data::Dumper;
use POSIX;
use DBI;

our @ISA = qw(Exporter);

our @EXPORT = qw(
StrToIp
IpToStr
do_sql
init_db
get_records_sql
get_record_sql
update_record
insert_record
delete_record
GetNowTime
GetUnixTimeByStr
GetTimeStrByUnixTime
);

BEGIN
{

#mysql
our $DBNAME = 'nagios';
our $DBHOST = '127.0.0.1';
our $DBUSER = 'nagios';
our $DBPASS = 'nagios';

#---------------------------------------------------------------------------------------------------------------

#id
#device_id
#ip
#changed - lsat changed unix timestamp
#data_id - port number
#data_type - 0 => bandwidth; 1 => crc
#data_value1
#data_value2
#data_value3
#data_value4

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

sub do_sql {
my $db=shift;
my $sql=shift;
return if (!$db);
return if (!$sql);
my $sql_prep = $db->prepare($sql) or die "Unable to prepare $sql: " . $db->errstr;
my $sql_ref;
$sql_prep->execute() or die "Unable to execute $sql: " . $db->errstr;
if ($sql=~/^insert/i) { $sql_ref = $sql_prep->{mysql_insertid}; }
if ($sql=~/^select /i) { $sql_ref = $sql_prep->fetchall_arrayref() or die "Unable to select $sql: " . $db->errstr; };
$sql_prep->finish();
return $sql_ref;
}

#---------------------------------------------------------------------------------------------------------------

sub init_db {
# Create new database handle. If we can't connect, die()
my $db = DBI->connect("dbi:mysql:database=$DBNAME;host=$DBHOST","$DBUSER","$DBPASS", { RaiseError => 0, AutoCommit => 1 });
if ( !defined $db ) { die "Cannot connect to mySQL server: $DBI::errstr\n"; }
$db->do('SET NAMES utf8mb4');
$db->{'mysql_enable_utf8'} = 1;
$db->{'mysql_auto_reconnect'} = 1;
return $db;
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
my $list = $db->prepare($tsql) or die "Unable to prepare $tsql: " . $db->errstr;
$list->execute() or die "Unable to execute $tsql: " . $db->errstr;
my $row_ref = $list->fetchrow_hashref();
$list->finish();
return $row_ref;
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
my $change_str='';
foreach my $field (keys %$record) {
    if (!defined $record->{$field}) { $record->{$field}=''; }
    my $new_value = $record->{$field};
    $new_value=~s/\'//g;
    $new_value=~s/\"//g;
    $change_str = $change_str." `$field`=".$db->quote($record->{$field}).",";
    }

$change_str=~s/\,$//;
my $sSQL = "UPDATE $table SET $change_str WHERE $filter";
do_sql($db,$sSQL);
}

#---------------------------------------------------------------------------------------------------------------

sub insert_record {
my $db = shift;
my $table = shift;
my $record = shift;
return if (!$db);
return if (!$table);
my $fields='';
my $values='';
foreach my $field (keys %$record) {
    if (!defined $record->{$field}) { $record->{$field}=''; }
    my $new_value = $record->{$field};
    $new_value=~s/\'//g;
    $new_value=~s/\"//g;
    $fields = $fields."`$field`,";
    $values = $values." ".$db->quote($new_value).",";
    }
$fields=~s/,$//;
$values=~s/,$//;
my $sSQL = "INSERT INTO $table($fields) VALUES($values)";
my $result = do_sql($db,$sSQL);
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
my $sSQL = "DELETE FROM ".$table." WHERE ".$filter;
do_sql($db,$sSQL);
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

sub GetUnixTimeByStr {
my $time_str = shift;
$time_str =~s/\//-/g;
$time_str =~s/^\s+//g;
$time_str =~s/\s+$//g;
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

1;
}
