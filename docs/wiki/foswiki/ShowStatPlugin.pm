package Foswiki::Plugins::ShowStatPlugin;

# Always use strict to enforce variable scoping
use strict;

use utf8;
use DBI;
use Data::Dumper;

# $VERSION is referred to by Foswiki, and is the only global variable that
# *must* exist in this package
use vars qw( $VERSION $RELEASE $debug $dbstat $dbcacti $pluginName );

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
$pluginName = 'ShowStatPlugin';

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
    my( $topic, $web ) = @_;

    # check for Plugins.pm versions
    if( $Foswiki::Plugins::VERSION < 1.026 ) {
        Foswiki::Func::writeWarning( "Version mismatch between $pluginName and Plugins.pm" );
        return 0;
        }

    return 0 unless $Foswiki::cfg{Plugins}{DatabasePlugin}{ConfigSource};
    if ( $Foswiki::cfg{Plugins}{DatabasePlugin}{ConfigSource} eq 'Local' ) {
        foreach  my $info ( @{ $Foswiki::cfg{Plugins}{DatabasePlugin}{Databases} } ) {
            if ($info->{description} eq "stat") { $dbstat = $info; next; }
            if ($info->{description} eq "cacti") { $dbcacti = $info; next; }
            }
      }

    return 0 if (!$dbstat);

    # register the _EXAMPLETAG function to handle %EXAMPLETAG{...}%
    Foswiki::Func::registerTagHandler( 'SHOWSTAT', \&_ShowStat );

    # Plugin correctly initialized
    return 1;
}

sub StrToIp{
return unpack('N',pack('C4',split(/\./,$_[0])));
}

sub _ShowStat {
my($session, $params, $theTopic, $theWeb) = @_;

### parameters
my $host = $params->{_DEFAULT} || $params->{host};
return "" if (!$host);

my $host_aton=StrToIp($host);
my $SQL = "SELECT A.id, A.ip, A.mac, L.login, A.nagios, A.dhcp_hostname, A.enabled, G.group_name, Q.queue_name,
A.last_found, A.comments FROM User_auth as A, User_list as L, Group_list as G, Queue_list As Q
WHERE A.user_id = L.id and A.filter_group_id = G.id and Q.id = A.queue_id AND A.deleted =0 and A.ip_int=".$host_aton." LIMIT 1";

#wait for statsync
sleep(2);

my $dbh = DBI->connect("dbi:$dbstat->{driver}:database=$dbstat->{database};host=$dbstat->{hostname}","$dbstat->{username}","$dbstat->{password}");
my $status = '';
eval {
if ( !defined $dbh ) { return "Cannot connect to mySQL server: $DBI::errstr\n"; }
$dbh->do('SET NAMES utf8');
$dbh->{'mysql_enable_utf8'} = 1;
my $sth = $dbh->prepare($SQL);
$sth->execute;
my $res = $sth->fetchrow_hashref();
if ($res) {

    my $cSQL = "SELECT * FROM `config` WHERE option_id=";
    my $sth1 = $dbh->prepare($cSQL."57");
    $sth1->execute;
    my $nagios_row = $sth1->fetchrow_hashref();
    my $nagios_url;
    if ($nagios_row) { $nagios_url = $nagios_row->{value}."/cgi-bin/status.cgi?navbarsearch=1&host=".$host; }

    $sth1 = $dbh->prepare($cSQL."62");
    $sth1->execute;
    my $stat_row = $sth1->fetchrow_hashref();
    my $stat_url;
    if ($stat_row) { $stat_url = $stat_row->{value}."/admin/users/editauth.php?id=".$res->{id}; }

    $status.='<div style="margin: 0 auto;">';
    $status.='<div style="float: left;">';
    $status.='Ссылки на внешние ресурсы<br>';
    $status.='<a href="'.$nagios_url.'">Nagios</a><br>' if ($nagios_url and $res->{nagios});
    $status.='<a href="'.$stat_url.'">Stat</a><br>' if ($stat_url);

    $status.='</div>';
    $status.='<div style="float: right; width: 200px;">';
    $status.='Login: '.$res->{login}.'<br>';
    if ($res->{enabled}) { $status.='Включен: Да<br>'; } else { $status.='Включен: Нет<br>'; }
    if ($res->{nagios}) { $status.='Nagios: Да<br>'; } else { $status.='Nagios: Нет<br>'; }
    $status.='Dhcp hostname: '.$res->{dhcp_hostname}.'<br>' if ($res->{dhcp_hostname});
    $status.='Фильтр: '.$res->{group_name}.'<br>';
    $status.='Шейпер: '.$res->{queue_name}.'<br>';
    $status.='Комментарий: '.$res->{comments}.'<br>';
    $status.='last found: '.$res->{last_found}.'<br>' if ($res->{last_found});
    $status.='</div>';
    $status.='</div>';
    $status.='<div style="clear: left;"><p></p></div>';
    }
};
if ($@) { return "DBI error: $@"; }
return $status;
}

1;
