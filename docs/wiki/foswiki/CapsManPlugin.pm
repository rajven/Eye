package Foswiki::Plugins::CapsManPlugin;

use utf8;
use Net::SNMP qw(:snmp ticks_to_time TRANSLATE_NONE);
use Net::Ping;

# Always use strict to enforce variable scoping
use strict;

# $VERSION is referred to by Foswiki, and is the only global variable that
# *must* exist in this package
use vars qw( $VERSION $RELEASE $debug $pluginName );

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
$pluginName = 'CapsManPlugin';

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
    my( $topic, $web, $user, $installWeb ) = @_;

    # check for Plugins.pm versions
    if( $Foswiki::Plugins::VERSION < 1.026 ) {
        Foswiki::Func::writeWarning( "Version mismatch between $pluginName and Plugins.pm" );
        return 0;
    }

    Foswiki::Func::registerTagHandler( 'CapsManINFO', \&_CapsManINFO );

    # Plugin correctly initialized
    return 1;
}

sub table_callback {
my ($session, $OID_ifTable, $table) = @_;

my $list = $session->var_bind_list();
if (!defined $list) {
    printf "ERROR: %s\n", $session->error();
    return;
    }

my @names = $session->var_bind_names();
my $next  = undef;

while (@names) {
   $next = shift @names;
   if (!oid_base_match($OID_ifTable, $next)) {
      return; # Table is done.
      }
   $table->{$next} = $list->{$next};
}

my $result = $session->get_bulk_request( -varbindlist => [ $next ], -maxrepetitions => 10, );
if (!defined $result) { printf "ERROR: %s.\n", $session->error(); }
return;
}

sub StrToIp{
return unpack('N',pack('C4',split(/\./,$_[0])));
}

sub get_last_dec {
my $oid = shift;
if ($oid=~/\.(\d*)$/) { return $1; }
return '';
}

sub get_first_dec {
my $oid = shift;
if ($oid=~/(.*)\.(\d*)$/) { return $1; }
return $oid;
}

sub extract_dec_mac {
my $base_oid = shift;
my $key = shift;
$key =~s/^$base_oid\.//;
return $key;
}

sub mac_splitted{
my $mac=shift;
return if (!$mac);
my $ch=shift || ":";
$mac=~s/(\S{2})(\S{2})(\S{2})(\S{2})(\S{2})(\S{2})/$1:$2:$3:$4:$5:$6/g;
if ($ch ne ":") { $mac=~s/\:/$ch/g; }
return $mac;
}

sub dec2mac {
my $key = shift;
my @dec_mac = split(/\./,$key);
my @mac=();
for (my $i = 0; $i < scalar(@dec_mac); $i++) { $mac[$i]=sprintf("%02x",$dec_mac[$i]); }
$key = join(':',@mac);
return $key;
}

sub expand_status {
my $client = shift;
my $ret ='';
foreach my $id (sort keys %$client) {
        $ret.='| '.$client->{$id}->{int_name}.' | ';
        $ret.=$client->{$id}->{SSID}.' | ';
        $ret.=$client->{$id}->{mac}.' | ';
        $ret.=$client->{$id}->{rx}.' dBm / '.$client->{$id}->{tx}.'dBm | ';
        $ret.=$client->{$id}->{rxrate}.'M / '.$client->{$id}->{txrate}.'M | ';
        $ret.=$client->{$id}->{uptime}." |\n";
        }
return $ret;
};

sub _CapsManINFO {
my($session, $params, $theTopic, $theWeb) = @_;

my $host = $params->{_DEFAULT} || $params->{host};

### check host alive
my $p = Net::Ping->new("tcp",1,1);
my $ok= $p->ping($host);
$p->close();
if (!$ok) {  return "Ups $host is not available<BR>"; }

undef($p);

my $port = $params->{port} || 161;
my $timeout = $params->{timeout} || 5;

my $community = $params->{community};
$community =  'public' if (!$community);

#1 - client list
#2 - mac list
#3 - ssid list

my $mode = $params->{mode} || '3';

my $snmp_session;
my $error;

my %DefaultMibs=(
'Description'    => '.1.3.6.1.2.1.1.1.0',
'Uptime'         => '.1.3.6.1.2.1.1.3.0',
'Name'           => '.1.3.6.1.2.1.1.5.0',
);

my %MikrotikCapsManMibs=(
'ClientMac'=>'.1.3.6.1.4.1.14988.1.1.1.5.1.1',
'ClientUptime'=>'.1.3.6.1.4.1.14988.1.1.1.5.1.3',
'ClientTxRate'=>'.1.3.6.1.4.1.14988.1.1.1.5.1.8',
'ClientRxRate'=>'.1.3.6.1.4.1.14988.1.1.1.5.1.9',
'ClientTx'=>'.1.3.6.1.4.1.14988.1.1.1.5.1.10',
'ClientRx'=>'.1.3.6.1.4.1.14988.1.1.1.5.1.11',
'ClientSSID'=>'.1.3.6.1.4.1.14988.1.1.1.5.1.12',
'InterfaceList'=>'.1.3.6.1.4.1.14988.1.1.14.1.1.2',
#'APNames'=>'.1.3.6.1.4.1.14988.1.1.1.11.1.2',
#'APStatus'=>'.1.3.6.1.4.1.14988.1.1.1.11.1.3',
#'APAddress'=>'.1.3.6.1.4.1.14988.1.1.1.11.1.4',
#'APRadioCount'=>'.1.3.6.1.4.1.14988.1.1.1.11.1.5',
);

### open SNMP session
eval {
    ($snmp_session, $error) = Net::SNMP->session(
    -hostname  => $host,
    -community => $community,
    -nonblocking => 1,
    -translate   => [-octetstring => 0],
    -version     => 'snmpv2c',
    );
};

return "CapsMan is not available<BR>"  if (!defined($snmp_session));

$snmp_session->translate([-timeticks]);

my %defaultTable;
my $ret = $snmp_session->get_bulk_request(
    -varbindlist    => [ '.1.3.6.1.2.1.1' ],
    -callback       => [ \&table_callback, '.1.3.6.1.2.1.1', \%defaultTable ],
    -maxrepetitions => 10,
    );

if (!defined $ret) {
    $snmp_session->close();
    return "CapsMan not answer!<BR>";
    }
# Now initiate the SNMP message exchange.
snmp_dispatcher();

my %CapsManTable;

foreach my $key (keys %MikrotikCapsManMibs) {
my $Mikrotik_CapsMan_mib = $MikrotikCapsManMibs{$key};
my %table;
my $result = $snmp_session->get_bulk_request(
    -varbindlist    => [ $Mikrotik_CapsMan_mib ],
    -callback       => [ \&table_callback, $Mikrotik_CapsMan_mib, \%table ],
    -maxrepetitions => 10,
    );
if (!defined $result) {
    printf "ERROR: %s\n", $snmp_session->error();
    $snmp_session->close();
    exit 1;
    }
# Now initiate the SNMP message exchange.
snmp_dispatcher();
$CapsManTable{$key}=\%table;
}

$snmp_session->close();

my %interfaces;
foreach my $interface_oid (keys %{$CapsManTable{InterfaceList}}) {
$interfaces{extract_dec_mac($MikrotikCapsManMibs{InterfaceList},$interface_oid)}=$CapsManTable{InterfaceList}->{$interface_oid};
}

my $result=$defaultTable{$DefaultMibs{Name}}."\n<br>";
$result.=$defaultTable{$DefaultMibs{Description}}."\n<br>";
my $uptime = int($defaultTable{$DefaultMibs{Uptime}}/100);
my $day; my $hour; my $min;
$day = int($uptime/86400);
$hour = int(($uptime - 86400*$day)/3600);
$min = int (($uptime - 86400*$day - 3600*$hour)/60);
my $s_uptime = "$day days $hour:$min";
$result.= "$s_uptime\n<br>\n";

my %client_status;

my $key = 'ClientMac';
for my $oid (oid_lex_sort(keys %{$CapsManTable{$key}})) {
my $dec_oid = extract_dec_mac($MikrotikCapsManMibs{$key},$oid);
my $dec_mac = get_first_dec($dec_oid);
my $int_id = get_last_dec($dec_oid);
$client_status{$dec_mac}->{mac}=dec2mac($dec_mac);
$client_status{$dec_mac}->{int_id}=$int_id;
$client_status{$dec_mac}->{int_name}=$interfaces{$int_id};
}

$key = 'ClientTxRate';
for my $oid (oid_lex_sort(keys %{$CapsManTable{$key}})) {
my $dec_oid = extract_dec_mac($MikrotikCapsManMibs{$key},$oid);
my $dec_mac = get_first_dec($dec_oid);
my $rate = $CapsManTable{$key}->{$oid};
$client_status{$dec_mac}->{txrate}=$rate/1000000;
}

$key = 'ClientRxRate';
for my $oid (oid_lex_sort(keys %{$CapsManTable{$key}})) {
my $dec_oid = extract_dec_mac($MikrotikCapsManMibs{$key},$oid);
my $dec_mac = get_first_dec($dec_oid);
my $rate = $CapsManTable{$key}->{$oid};
$client_status{$dec_mac}->{rxrate}=$rate/1000000;
}

$key = 'ClientTx';
for my $oid (oid_lex_sort(keys %{$CapsManTable{$key}})) {
my $dec_oid = extract_dec_mac($MikrotikCapsManMibs{$key},$oid);
my $dec_mac = get_first_dec($dec_oid);
my $rate = $CapsManTable{$key}->{$oid};
$client_status{$dec_mac}->{tx}=$rate;
}

$key = 'ClientRx';
for my $oid (oid_lex_sort(keys %{$CapsManTable{$key}})) {
my $dec_oid = extract_dec_mac($MikrotikCapsManMibs{$key},$oid);
my $dec_mac = get_first_dec($dec_oid);
my $rate = $CapsManTable{$key}->{$oid};
$client_status{$dec_mac}->{rx}=$rate;
}

$key = 'ClientSSID';
for my $oid (oid_lex_sort(keys %{$CapsManTable{$key}})) {
my $dec_oid = extract_dec_mac($MikrotikCapsManMibs{$key},$oid);
my $dec_mac = get_first_dec($dec_oid);
my $ssid = $CapsManTable{$key}->{$oid};
$client_status{$dec_mac}->{SSID}=$ssid;
}

$key = 'ClientUptime';
for my $oid (oid_lex_sort(keys %{$CapsManTable{$key}})) {
my $dec_oid = extract_dec_mac($MikrotikCapsManMibs{$key},$oid);
my $dec_mac = get_first_dec($dec_oid);
my $uptime = $CapsManTable{$key}->{$oid};
my $day; my $hour; my $min;
$day = int($uptime/86400);
$hour = int(($uptime - 86400*$day)/3600);
$min = int (($uptime - 86400*$day - 3600*$hour)/60);
my $s_uptime = "$day days $hour:$min";
$client_status{$dec_mac}->{uptime}=$s_uptime;
}

my %CapsMan_status;
my $all_clients=0;
foreach my $dec_mac (sort keys %client_status) {
my $ssid = $client_status{$dec_mac}->{SSID};
if (!exists($CapsMan_status{$ssid})) { $CapsMan_status{$ssid}{count}=0; }
$CapsMan_status{$ssid}{count}++;
$all_clients++;
}

$result.="\n<br>Wireless counters:\n<br>\n";
$result.="| *SSID* | *Count* |\n";
foreach my $ssid (sort keys %CapsMan_status) {
$result.= "| $ssid | $CapsMan_status{$ssid}{count} |\n";
}
$result.= "\n<br>Total: $all_clients\n<br>\n";
$result.= "| *Interface* | *SSID* | *Client* | *Rx/Tx* | *Bitr* | *Uptime* |\n";

$result.=expand_status(\%client_status);

return $result;
}

1;
