package Foswiki::Plugins::WlcInfoPlugin;

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
$pluginName = 'WlcInfoPlugin';

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

    Foswiki::Func::registerTagHandler( 'WlcINFO', \&_WlcINFO );

    # Plugin correctly initialized
    return 1;
}

sub StrToIp{
return unpack('N',pack('C4',split(/\./,$_[0])));
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
my ($client,$filter) = @_;
my $ret ='';
foreach my $id (sort keys %$client) {
        next if ($filter and $client->{$id}->{mac} ne $filter);
        $ret.='| '.$client->{$id}->{ap_id}.' | ';
        $ret.=$client->{$id}->{ap_mac}.' | ';
        $ret.=$client->{$id}->{sid}.' | ';
        $ret.=$client->{$id}->{mac}.' | ';
        if (!$client->{$id}->{ip}) { $client->{$id}->{ip}='0.0.0.0'; }
        $ret.=$client->{$id}->{ip}.' | ';
        $ret.=$client->{$id}->{Hz}.' | ';
        $ret.=$client->{$id}->{ch}.' | ';
        $ret.=$client->{$id}->{rx}.'/'.$client->{$id}->{tx}.' | ';
        $ret.=$client->{$id}->{band}.' | ';
        $ret.=$client->{$id}->{uptime}." |\n";
        }
return $ret;
};

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

sub _WlcINFO {
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
#4 - ip list

my $mode = $params->{mode} || '3';
my $filter = $params->{filter};

my $snmp_session;
my $error;

my %DefaultMibs=(
'Description'    => '.1.3.6.1.2.1.1.1.0',
'Uptime'         => '.1.3.6.1.2.1.1.3.0',
'Name'           => '.1.3.6.1.2.1.1.5.0',
);

my %HuaweiWlcMibs=(
#'ClientList'     =>'.1.3.6.1.4.1.2011.6.139.18.1.2.1.2',
'ClientAPHexList'=>'.1.3.6.1.4.1.2011.6.139.18.1.2.1.3',
#'ClientAPList'   =>'.1.3.6.1.4.1.2011.6.139.18.1.2.1.4',
#dec-mac = string
#'APVap'=>'.1.3.6.1.4.1.2011.6.139.18.1.2.1.5',
#dec-mac = на какой диапазон подкючен клиент 1 - 2.4G 2 - 5G
'ClientHz'       =>'.1.3.6.1.4.1.2011.6.139.18.1.2.1.7',
'ClientChannel'  =>'.1.3.6.1.4.1.2011.6.139.18.1.2.1.9',
# - полоса - INTEGER{invalid(1),ht40(2),ht20(3),vht80(4)}
'ClientBand'     =>'.1.3.6.1.4.1.2011.6.139.18.1.2.1.11',
'ClientRxRate'   =>'.1.3.6.1.4.1.2011.6.139.18.1.2.1.14',
'ClientTxRate'   =>'.1.3.6.1.4.1.2011.6.139.18.1.2.1.15',
'ClientSSID'     =>'.1.3.6.1.4.1.2011.6.139.18.1.2.1.16',
'ClientIp'       =>'.1.3.6.1.4.1.2011.6.139.18.1.2.1.25',
'ClientUptime'   =>'.1.3.6.1.4.1.2011.6.139.18.1.2.1.30',
'ClientApId'     =>'.1.3.6.1.4.1.2011.6.139.18.1.2.1.53',
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

return "Wlc is not available<BR>"  if (!defined($snmp_session));

$snmp_session->translate([-timeticks]);

my %defaultTable;
my $ret = $snmp_session->get_bulk_request(
    -varbindlist    => [ '.1.3.6.1.2.1.1' ],
    -callback       => [ \&table_callback, '.1.3.6.1.2.1.1', \%defaultTable ],
    -maxrepetitions => 10,
    );

if (!defined $ret) {
    $snmp_session->close();
    return "Wlc not answer!<BR>";
    }
# Now initiate the SNMP message exchange.
snmp_dispatcher();

my %wlcTable;

foreach my $key (keys %HuaweiWlcMibs) {
my $huawei_wlc_mib = $HuaweiWlcMibs{$key};
my %table;
my $result = $snmp_session->get_bulk_request(
    -varbindlist    => [ $huawei_wlc_mib ],
    -callback       => [ \&table_callback, $huawei_wlc_mib, \%table ],
    -maxrepetitions => 10,
    );
if (!defined $result) {
    printf "ERROR: %s\n", $snmp_session->error();
    $snmp_session->close();
    exit 1;
    }
# Now initiate the SNMP message exchange.
snmp_dispatcher();
$wlcTable{$key}=\%table;
}

$snmp_session->close();

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

my $key = 'ClientApId';
for my $oid (oid_lex_sort(keys %{$wlcTable{$key}})) {
my $dec_mac = extract_dec_mac($HuaweiWlcMibs{$key},$oid);
my $ap_id = $wlcTable{$key}->{$oid};
$client_status{$dec_mac}->{mac}=dec2mac($dec_mac);
$client_status{$dec_mac}->{ap_id}=$ap_id;
}
$key = 'ClientAPHexList';
for my $oid (oid_lex_sort(keys %{$wlcTable{$key}})) {
my $dec_mac = extract_dec_mac($HuaweiWlcMibs{$key},$oid);
my $ap_mac = mac_splitted(unpack('H*',$wlcTable{$key}->{$oid}));
my $ap_id = $client_status{$dec_mac}->{ap_id};
$client_status{$dec_mac}->{ap_mac}=$ap_mac;
}
$key = 'ClientHz';
for my $oid (oid_lex_sort(keys %{$wlcTable{$key}})) {
my $dec_mac = extract_dec_mac($HuaweiWlcMibs{$key},$oid);
my $Hz = $wlcTable{$key}->{$oid};
my $ap_id = $client_status{$dec_mac}->{ap_id};
if ($Hz eq '1') { $Hz='2.4G'; }
if ($Hz eq '2') { $Hz='5G'; }
$client_status{$dec_mac}->{Hz}=$Hz;
}
$key = 'ClientChannel';
for my $oid (oid_lex_sort(keys %{$wlcTable{$key}})) {
my $dec_mac = extract_dec_mac($HuaweiWlcMibs{$key},$oid);
my $ch = $wlcTable{$key}->{$oid};
my $ap_id = $client_status{$dec_mac}->{ap_id};
$client_status{$dec_mac}->{ch}=$ch;
}
$key = 'ClientBand';
for my $oid (oid_lex_sort(keys %{$wlcTable{$key}})) {
my $dec_mac = extract_dec_mac($HuaweiWlcMibs{$key},$oid);
my $band = $wlcTable{$key}->{$oid};
my $ap_id = $client_status{$dec_mac}->{ap_id};
if ($band eq '1') { $band = '10M'; }
if ($band eq '2') { $band = '40M'; }
if ($band eq '3') { $band = '20M'; }
if ($band eq '4') { $band = '80M'; }
$client_status{$dec_mac}->{band}=$band;
}
$key = 'ClientRxRate';
for my $oid (oid_lex_sort(keys %{$wlcTable{$key}})) {
my $dec_mac = extract_dec_mac($HuaweiWlcMibs{$key},$oid);
my $rx = $wlcTable{$key}->{$oid};
if ($rx eq '65535') { $rx = 0; }
my $ap_id = $client_status{$dec_mac}->{ap_id};
$client_status{$dec_mac}->{rx}=$rx;
}
$key = 'ClientTxRate';
for my $oid (oid_lex_sort(keys %{$wlcTable{$key}})) {
my $dec_mac = extract_dec_mac($HuaweiWlcMibs{$key},$oid);
my $tx = $wlcTable{$key}->{$oid};
if ($tx eq '65535') { $tx = 0; }
my $ap_id = $client_status{$dec_mac}->{ap_id};
$client_status{$dec_mac}->{tx}=$tx;
}
$key = 'ClientSSID';
for my $oid (oid_lex_sort(keys %{$wlcTable{$key}})) {
my $dec_mac = extract_dec_mac($HuaweiWlcMibs{$key},$oid);
my $SSID = $wlcTable{$key}->{$oid};
my $ap_id = $client_status{$dec_mac}->{ap_id};
$client_status{$dec_mac}->{sid}=$SSID;
}
$key = 'ClientIp';
for my $oid (oid_lex_sort(keys %{$wlcTable{$key}})) {
my $dec_mac = extract_dec_mac($HuaweiWlcMibs{$key},$oid);
my $ip = $wlcTable{$key}->{$oid};
my $ap_id = $client_status{$dec_mac}->{ap_id};
$client_status{$dec_mac}->{ip}=$ip;
}
$key = 'ClientUptime';
for my $oid (oid_lex_sort(keys %{$wlcTable{$key}})) {
my $dec_mac = extract_dec_mac($HuaweiWlcMibs{$key},$oid);
my $uptime = $wlcTable{$key}->{$oid};
my $ap_id = $client_status{$dec_mac}->{ap_id};
my $day; my $hour; my $min;
$day = int($uptime/86400);
$hour = int(($uptime - 86400*$day)/3600);
$min = int (($uptime - 86400*$day - 3600*$hour)/60);
my $s_uptime = "$day days $hour:$min";
$client_status{$dec_mac}->{uptime}=$s_uptime;
}

my %wlc_status;
my $all_clients=0;
foreach my $dec_mac (sort keys %client_status) {
my $ssid = $client_status{$dec_mac}->{sid};
if (!exists($wlc_status{$ssid})) { $wlc_status{$ssid}{count}=0; $wlc_status{$ssid}{count2}=0; $wlc_status{$ssid}{count5}=0; }
$wlc_status{$ssid}{count}++;
$all_clients++;
if ($client_status{$dec_mac}->{Hz} eq '5G') { $wlc_status{$ssid}{count5}++; } else { $wlc_status{$ssid}{count2}++; }
}

$result.="\n<br>Wireless counters:\n<br>\n";
$result.="| *SSID* | *Count* | *2.4G* | *5G* |\n";
foreach my $ssid (sort keys %wlc_status) {
$result.= "| $ssid | $wlc_status{$ssid}{count} | $wlc_status{$ssid}{count2} | $wlc_status{$ssid}{count5} |\n";
}
$result.= "\n<br>Total: $all_clients\n<br>\n";

$result.= "| *AP id* | *AP* | *SSID* | *Client* | *IP* | *Hz* | *Channel* | *Rx/Tx* | *Band* | *Uptime* |\n";

if ($mode eq '1') { $result.=expand_status(\%client_status,$filter); }

if ($mode eq '2') {
    my %ap_status;
    foreach my $dec_mac (sort keys %client_status) {
        if (!$client_status{$dec_mac}->{ip}) { $client_status{$dec_mac}->{ip}='0.0.0.0'; }
        my $ap_id = $client_status{$dec_mac}->{ap_id};
        $ap_status{$ap_id}->{$dec_mac}->{ap_id}=$ap_id;
        $ap_status{$ap_id}->{$dec_mac}->{ap_mac}=$client_status{$dec_mac}->{ap_mac};
        $ap_status{$ap_id}->{$dec_mac}->{sid}=$client_status{$dec_mac}->{sid};
        $ap_status{$ap_id}->{$dec_mac}->{mac}=$client_status{$dec_mac}->{mac};
        $ap_status{$ap_id}->{$dec_mac}->{ip}=$client_status{$dec_mac}->{ip};
        $ap_status{$ap_id}->{$dec_mac}->{Hz}=$client_status{$dec_mac}->{Hz};
        $ap_status{$ap_id}->{$dec_mac}->{ch}=$client_status{$dec_mac}->{ch};
        $ap_status{$ap_id}->{$dec_mac}->{rx}=$client_status{$dec_mac}->{rx};
        $ap_status{$ap_id}->{$dec_mac}->{tx}=$client_status{$dec_mac}->{tx};
        $ap_status{$ap_id}->{$dec_mac}->{band}=$client_status{$dec_mac}->{band};
        $ap_status{$ap_id}->{$dec_mac}->{uptime}=$client_status{$dec_mac}->{uptime};
        }
    foreach my $ap_id (sort {$a <=> $b} keys %ap_status) {
        next if ($filter and $ap_id ne $filter);
        $result.=expand_status($ap_status{$ap_id});
        }
    }

if ($mode eq '3') {
    my %ssid_status;
    foreach my $dec_mac (sort keys %client_status) {
        if (!$client_status{$dec_mac}->{ip}) { $client_status{$dec_mac}->{ip}='0.0.0.0'; }
        my $ssid = $client_status{$dec_mac}->{sid};
        $ssid_status{$ssid}->{$dec_mac}->{ap_id}=$client_status{$dec_mac}->{ap_id};
        $ssid_status{$ssid}->{$dec_mac}->{ap_mac}=$client_status{$dec_mac}->{ap_mac};
        $ssid_status{$ssid}->{$dec_mac}->{sid}=$ssid;
        $ssid_status{$ssid}->{$dec_mac}->{mac}=$client_status{$dec_mac}->{mac};
        $ssid_status{$ssid}->{$dec_mac}->{ip}=$client_status{$dec_mac}->{ip};
        $ssid_status{$ssid}->{$dec_mac}->{Hz}=$client_status{$dec_mac}->{Hz};
        $ssid_status{$ssid}->{$dec_mac}->{ch}=$client_status{$dec_mac}->{ch};
        $ssid_status{$ssid}->{$dec_mac}->{rx}=$client_status{$dec_mac}->{rx};
        $ssid_status{$ssid}->{$dec_mac}->{tx}=$client_status{$dec_mac}->{tx};
        $ssid_status{$ssid}->{$dec_mac}->{band}=$client_status{$dec_mac}->{band};
        $ssid_status{$ssid}->{$dec_mac}->{uptime}=$client_status{$dec_mac}->{uptime};
        }
    foreach my $ssid (sort {$a <=> $b} keys %ssid_status) {
        next if ($filter and $ssid ne $filter);
        $result.=expand_status($ssid_status{$ssid});
        }
    }

if ($mode eq '4') {
    my %ip_status;
    my $unknown_count=0;
    foreach my $dec_mac (sort keys %client_status) {
        my $ip;
        if (!$client_status{$dec_mac}->{ip}) { $ip=$unknown_count; $unknown_count++; } else { $ip = StrToIp($client_status{$dec_mac}->{ip}); }
        $ip_status{$ip}->{$dec_mac}->{ap_id}=$client_status{$dec_mac}->{ap_id};;
        $ip_status{$ip}->{$dec_mac}->{ap_mac}=$client_status{$dec_mac}->{ap_mac};
        $ip_status{$ip}->{$dec_mac}->{sid}=$client_status{$dec_mac}->{sid};
        $ip_status{$ip}->{$dec_mac}->{mac}=$client_status{$dec_mac}->{mac};
        $ip_status{$ip}->{$dec_mac}->{ip}=$client_status{$dec_mac}->{ip};
        $ip_status{$ip}->{$dec_mac}->{Hz}=$client_status{$dec_mac}->{Hz};
        $ip_status{$ip}->{$dec_mac}->{ch}=$client_status{$dec_mac}->{ch};
        $ip_status{$ip}->{$dec_mac}->{rx}=$client_status{$dec_mac}->{rx};
        $ip_status{$ip}->{$dec_mac}->{tx}=$client_status{$dec_mac}->{tx};
        $ip_status{$ip}->{$dec_mac}->{band}=$client_status{$dec_mac}->{band};
        $ip_status{$ip}->{$dec_mac}->{uptime}=$client_status{$dec_mac}->{uptime};
        }
    foreach my $ip (sort {$a <=> $b} keys %ip_status) {
        next if ($filter and $ip ne $filter);
        $result.=expand_status($ip_status{$ip});
        }
    }

return $result;
}

1;
