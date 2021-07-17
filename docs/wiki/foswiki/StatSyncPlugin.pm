package Foswiki::Plugins::StatSyncPlugin;

# Always use strict to enforce variable scoping
use strict;
use utf8;

use DBI;
use Data::Dumper;

# $VERSION is referred to by Foswiki, and is the only global variable that
# *must* exist in this package
use vars qw( $VERSION $RELEASE $debug $dbstat $wiki_user $pluginName );

use Foswiki::Func    ();    # The plugins API
use Foswiki::Plugins ();    # For the API version

# This should always be $Rev: 8713$ so that Foswiki can determine the checked-in
# status of the plugin. It is used by the build automation tools, so
# you should leave it alone.
$VERSION = '$Rev: 8713$';

# This is a free-form string you can use to "name" your own plugin version.
# It is *not* used by the build automation tools, but is reported as part
# of the version number in PLUGINDESCRIPTIONS.
$RELEASE = '1.01';

# Name of this Plugin, only used in this module
$pluginName = 'StatSyncPlugin';

=pod

---++ initPlugin($topic, $web, $user, $installWeb) -> $boolean
   * =$topic= - the name of the topic in the current CGI query
   * =$web= - the name of the web in the current CGI query
   * =$user= - the login name of the user
   * =$installWeb= - the name of the web the plugin is installed in

REQUIRED

Called to initialise the plugin. If everything is OK, should return
a non-zero value. On non-fatal failure, should write a message
using Foswiki::Func::writeWarning and return 0. In this case
%FAILEDPLUGINS% will indicate which plugins failed.

In the case of a catastrophic failure that will prevent the whole
installation from working safely, this handler may use 'die', which
will be trapped and reported in the browser.

You may also call =Foswiki::Func::registerTagHandler= here to register
a function to handle tags that have standard Foswiki syntax - for example,
=%MYTAG{"my param" myarg="My Arg"}%. You can also override internal
Foswiki tag handling functions this way, though this practice is unsupported
and highly dangerous!

=cut

sub initPlugin {
    my( $topic, $web, $user ) = @_;

    $wiki_user = "wiki: ".$user;

    # check for Plugins.pm versions
    if( $Foswiki::Plugins::VERSION < 1.026 ) {
        Foswiki::Func::writeWarning( "Version mismatch between $pluginName and Plugins.pm" );
        return 0;
        }

    return 0 unless $Foswiki::cfg{Plugins}{DatabasePlugin}{ConfigSource};
    if ( $Foswiki::cfg{Plugins}{DatabasePlugin}{ConfigSource} eq 'Local' ) {
        foreach  my $info ( @{ $Foswiki::cfg{Plugins}{DatabasePlugin}{Databases} } ) {
#            next if ($info->{description} ne "stat");
#            $dbstat = $info;
#            last;
            if ($info->{description} eq "stat") { $dbstat = $info; next; }
            }
      }

    return 0 if (!$dbstat);

    # register the _EXAMPLETAG function to handle %EXAMPLETAG{...}%
    Foswiki::Func::registerTagHandler( 'STATSYNC', \&_STATSYNC );

    # Plugin correctly initialized
    return 1;
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

sub StrToIp{
return unpack('N',pack('C4',split(/\./,$_[0])));
}

#---------------------------------------------------------------------------------------------------------------

sub write_db_log {
my $dbh=shift;
my $msg=shift;
return if (!$dbh);
return if (!$msg);
$msg=~s/[\'\"]//g;
if (!$wiki_user) { $wiki_user = 'wiki'; }
my $history_sql="INSERT INTO syslog(customer,message,level,auth_id) VALUES('".$wiki_user."',".$dbh->quote($msg).",3,0)";
my $history_rf=$dbh->prepare($history_sql);
$history_rf->execute;
}

#---------------------------------------------------------------------------------------------------------------

sub do_sql {
my $dbh=shift;
my $sql=shift;
return if (!$dbh);
return if (!$sql);
my $sql_prep = $dbh->prepare($sql);
my $sql_ref;
if ( !defined $sql_prep ) { die "Cannot prepare statement: $DBI::errstr\n"; }
$sql_prep->execute;
if ($sql!~/^select /i) {  write_db_log($dbh,$sql); }
if ($sql=~/^insert/i) { $sql_ref = $sql_prep->{mysql_insertid}; }
if ($sql=~/^select /i) { $sql_ref = $sql_prep->fetchall_arrayref(); };
$sql_prep->finish();
return $sql_ref;
}

#---------------------------------------------------------------------------------------------------------------

sub get_record_sql {
my $dbh = shift;
my $tsql = shift;
my @result;
return @result if (!$dbh);
return @result if (!$tsql);
my $list = $dbh->prepare( $tsql . ' LIMIT 1' );
if ( !defined $list ) { die "Cannot prepare statement: $DBI::errstr\n"; }
$list->execute;
my $row_ref = $list ->fetchrow_hashref;
$list->finish();
return $row_ref;
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
my $old_record = get_record_sql($dbh,"SELECT * FROM $table WHERE $filter");
my $diff='';
my $change_str='';
my $found_changed=0;
my $auth_id = 0;

foreach my $field (keys %$record) {
    if (!defined $record->{$field}) { $record->{$field}=''; }
    if (!defined $old_record->{$field}) { $old_record->{$field}=''; }
    my $old_value = quotemeta($old_record->{$field});
    my $new_value = $record->{$field};
    $new_value=~s/\'//g;
    $new_value=~s/\"//g;
    if ($new_value!~/^$old_value$/) {
        $diff = $diff." $field => $record->{$field} (old: $old_record->{$field}),";
        $change_str = $change_str." `$field`=".$dbh->quote($record->{$field}).",";
        $found_changed++;
        }
}

if ($found_changed) {
    $change_str=~s/\,$//;
    $diff=~s/\,$//;
    if ($table eq 'User_auth') { $change_str .= ", `changed_time`='".GetNowTime()."'"; }
    my $sSQL = "UPDATE $table SET $change_str WHERE $filter";
    do_sql($dbh,$sSQL);
    }
}

sub _STATSYNC {
my($session, $params, $theTopic, $theWeb) = @_;

### parameters
my $host = $params->{_DEFAULT} || $params->{host};
my $dnsname = $params->{dnsname};
my $comment = $params->{comment};
my $wikiname = $params->{wikiname};

#my $result="Runned: $host $dnsname $comment $wikiname<br>";

my $result="";

return $result if (!$host);

my $host_aton=StrToIp($host);
my $SQL = "SELECT * FROM User_auth WHERE ip_int=".$host_aton." and deleted=0 LIMIT 1";

my $connect_options = "dbi:$dbstat->{driver}:database=$dbstat->{database};host=$dbstat->{hostname}";
my $connect_user = "$dbstat->{username}";
my $connect_password = "$dbstat->{password}";

my $dbh = DBI->connect($connect_options,$connect_user,$connect_password);

eval {
if ( !defined $dbh ) { return "Cannot connect to mySQL server: $DBI::errstr\n"; }
$dbh->do('SET NAMES utf8');
$dbh->{'mysql_enable_utf8'} = 1;

my $sth = $dbh->prepare($SQL);
$sth->execute;
my $res = $sth->fetchrow_hashref();

if ($res) {
    if ($dnsname and $res->{dns_name} ne $dnsname) {
        my $new_record;
        $new_record->{dns_name} = $dnsname;
        update_record($dbh,"User_auth",$new_record,"id=".$res->{id});
        }
    if ($comment and $res->{comments} ne $comment) {
        my $new_record;
        $new_record->{comments} = $comment;
        update_record($dbh,"User_auth",$new_record,"id=".$res->{id});
        }
    if ($wikiname and $res->{WikiName} ne $wikiname) {
        my $new_record;
        $new_record->{WikiName} = $wikiname;
        update_record($dbh,"User_auth",$new_record,"id=".$res->{id});
        }
    }
};
if ($@) { return "DBI error: $@"; }
return $result;
}

1;
