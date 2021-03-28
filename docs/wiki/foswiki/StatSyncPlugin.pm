package Foswiki::Plugins::StatSyncPlugin;

# Always use strict to enforce variable scoping
use strict;
use utf8;

use DBI;
use Data::Dumper;

# $VERSION is referred to by Foswiki, and is the only global variable that
# *must* exist in this package
use vars qw( $VERSION $RELEASE $debug $dbstat $pluginName );

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
    my( $topic, $web ) = @_;

    # check for Plugins.pm versions
    if( $Foswiki::Plugins::VERSION < 1.026 ) {
        Foswiki::Func::writeWarning( "Version mismatch between $pluginName and Plugins.pm" );
        return 0;
        }

    return 0 unless $Foswiki::cfg{Plugins}{DatabasePlugin}{ConfigSource};
    if ( $Foswiki::cfg{Plugins}{DatabasePlugin}{ConfigSource} eq 'Local' ) {
        foreach  my $info ( @{ $Foswiki::cfg{Plugins}{DatabasePlugin}{Databases} } ) {
            next if ($info->{description} ne "stat");
            $dbstat = $info;
            last;
            }
      }

    return 0 if (!$dbstat);

    # register the _EXAMPLETAG function to handle %EXAMPLETAG{...}%
    Foswiki::Func::registerTagHandler( 'STATSYNC', \&_STATSYNC );

    # Plugin correctly initialized
    return 1;
}

sub StrToIp{
return unpack('N',pack('C4',split(/\./,$_[0])));
}

sub _STATSYNC {
my($session, $params, $theTopic, $theWeb) = @_;

### parameters
my $host = $params->{_DEFAULT} || $params->{host};
my $dnsname = $params->{dnsname};
my $comment = $params->{comment};
my $wikiname = $params->{wikiname};

return "" if (!$host);

my $host_aton=StrToIp($host);
my $SQL = "SELECT id,dns_name,WikName,comments FROM User_auth WHERE ip_int=".$host_aton." and deleted=0 LIMIT 1";
my $dbh = DBI->connect("dbi:$dbstat->{driver}:database=$dbstat->{database};host=$dbstat->{hostname}","$dbstat->{username}","$dbstat->{password}");
eval {
if ( !defined $dbh ) { return "Cannot connect to mySQL server: $DBI::errstr\n"; }
$dbh->do('SET NAMES utf8');
$dbh->{'mysql_enable_utf8'} = 1;
my $sth = $dbh->prepare($SQL);
$sth->execute;
my $res = $sth->fetchrow_hashref();
if ($res) {
    if ($dnsname and $res->{dns_name} ne $dnsname) {
        $sth = $dbh->prepare("UPDATE User_auth SET dns_name='".$dnsname."' WHERE id=".$res->{id});
        $sth->execute;
        }
    if ($comment and $res->{comments} ne $comment) {
        $sth = $dbh->prepare("UPDATE User_auth SET comments='".$comment."' WHERE id=".$res->{id});
        $sth->execute;
        }
    if ($wikiname and $res->{WikiName} ne $wikiname) {
        $sth = $dbh->prepare("UPDATE User_auth SET WikiName='".$wikiname."' WHERE id=".$res->{id});
        $sth->execute;
        }
    }
};
if ($@) { return "DBI error: $@"; }
return "";
}

1;
