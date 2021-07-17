package Foswiki::Plugins::SwitchInfoPlugin;

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
$pluginName = 'SwitchInfoPlugin';

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

    # register the _EXAMPLETAG function to handle %EXAMPLETAG{...}%
    Foswiki::Func::registerTagHandler( 'SWITCHINFO', \&_SWITCHINFO );

    # Plugin correctly initialized
    return 1;
}

sub extract_last_digit {
my $base_oid = shift;
my $key = shift;
$key =~s/^$base_oid\.//;
return $key;
}

sub expand_status {
my ($device) = @_;
my $ret ='';

my %ifOperStatus =  (
'1','up',
'2','down',
'3','testing',
'4','unknown',
'5','dormant',
'6','notPresent',
'7','lowerLayerDown'
);

foreach my $port (sort {$a <=> $b} keys %$device) {
    next if (!$port);
    next if (!$device->{$port}->{Index});
    if (!$device->{$port}->{Alias}) { $device->{$port}->{Alias}=''; }
    if (!$device->{$port}->{Descr}) { $device->{$port}->{Descr}=''; }
    if (!$device->{$port}->{AdminStatus}) { $device->{$port}->{AdminStatus}=''; }
    if (exists $ifOperStatus{$device->{$port}->{AdminStatus}}) { $device->{$port}->{AdminStatus} = $ifOperStatus{$device->{$port}->{AdminStatus}}; }
    if (!$device->{$port}->{OperStatus}) { $device->{$port}->{OperStatus}=''; }
    if (exists $ifOperStatus{$device->{$port}->{OperStatus}}) { $device->{$port}->{OperStatus} = $ifOperStatus{$device->{$port}->{OperStatus}}; }
    if (!$device->{$port}->{Speed}) { $device->{$port}->{Speed}=0; } else {
	my $speed = $device->{$port}->{Speed};
	if ($speed eq '10000000') { $device->{$port}->{Speed}='10M'; }
	if ($speed eq '100000000') { $device->{$port}->{Speed}='100M'; }
	if ($speed eq '1000000000') { $device->{$port}->{Speed}='1G'; }
	if ($speed eq '10000000000') { $device->{$port}->{Speed}='10G'; }
	if ($speed eq '4294967295') { $device->{$port}->{Speed}='10G'; }
	}
    if ($device->{$port}->{LastChange} and $device->{$port}->{LastChange}>0 ) {
	my $day; my $hour; my $min;
	my $value = int($device->{$port}->{LastChange}/100);
	$day = int($value/86400);
	$hour = int(($value - 86400*$day)/3600);
	$min = int (($value - 86400*$day - 3600*$hour)/60);
	$device->{$port}->{LastChange} = "$day days $hour:$min";
	}
    if (!$device->{$port}->{LastChange}) { $device->{$port}->{LastChange}=''; }
    if (!$device->{$port}->{Vlan}) { $device->{$port}->{Vlan}='1'; }
    if (!$device->{$port}->{POE}) { $device->{$port}->{POE}=''; }
    if (!$device->{$port}->{SFP}) { $device->{$port}->{SFP}=''; }
    $ret.='| '.$port.' | ';
    $ret.=$device->{$port}->{Index}.' | ';
    $ret.=$device->{$port}->{Alias}.' | ';
    $ret.=$device->{$port}->{Descr}.' | ';
    $ret.=$device->{$port}->{AdminStatus}.' | ';
    $ret.=$device->{$port}->{OperStatus}.' | ';
    $ret.=$device->{$port}->{Speed}.' | ';
    $ret.=$device->{$port}->{LastChange}.' | ';
    $ret.=$device->{$port}->{Vlan}.' | ';
    $ret.=$device->{$port}->{POE}.' | ';
    $ret.=$device->{$port}->{SFP}." |\n";
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
  if (!oid_base_match($OID_ifTable, $next)) { return; }
  $table->{$next} = $list->{$next};
}

my $result = $session->get_bulk_request( -varbindlist => [ $next ], -maxrepetitions => 10, );
if (!defined $result) { printf "ERROR: %s.\n", $session->error(); }
return;
}

sub _SWITCHINFO {
my($session, $params, $theTopic, $theWeb) = @_;

### parameters
my $vendor = $params->{vendor} || '';
my $host = $params->{_DEFAULT} || $params->{host};

### check host alive
my $p = Net::Ping->new("tcp",1,1);
my $ok= $p->ping($host);
$p->close();

if (!$ok) { return "Switch is not available<BR>"; }
undef($p);

my $port = $params->{port} || 161;
my $timeout = $params->{timeout} || 5;
my $community = $params->{community};
if (!$community) { $community =  'public'; }

my %bridgeOIDS=(
    'Index'      =>'.1.3.6.1.2.1.31.1.1.1.1',
    'Alias'      =>'.1.3.6.1.2.1.31.1.1.1.18',
    'Descr'      =>'.1.3.6.1.2.1.2.2.1.2',
    'Speed'      =>'.1.3.6.1.2.1.2.2.1.5',
    'AdminStatus'=>'.1.3.6.1.2.1.2.2.1.7',
    'OperStatus' =>'.1.3.6.1.2.1.2.2.1.8',
    'LastChange' =>'.1.3.6.1.2.1.2.2.1.9',
    'Vlan'       =>'.1.3.6.1.2.1.17.7.1.4.5.1.1',
    'PoeAdmin'   =>'.1.3.6.1.2.1.105.1.1.1.3.1',
    );

my %VendorOIDS = (
	"Mikrotik" => {
	    "Default"=> {
	        PoeAdmin   =>'.1.3.6.1.4.1.14988.1.1.15.1.1.3',
#		PoeInt     =>'.1.3.6.1.4.1.14988.1.1.15.1.1.1',
#		PoeIntName =>'.1.3.6.1.4.1.14988.1.1.15.1.1.2',
		PoeVolt    =>'.1.3.6.1.4.1.14988.1.1.15.1.1.4',
		PoeCurrent =>'.1.3.6.1.4.1.14988.1.1.15.1.1.5',
		PoePower   =>'.1.3.6.1.4.1.14988.1.1.15.1.1.6',
		},
	    },
	"Eltex"  => {
	    "Default" => {
		PoePower   =>'.1.3.6.1.4.1.89.108.1.1.5.1',
		PoeCurrent =>'.1.3.6.1.4.1.89.108.1.1.4.1',
		PoeVolt    =>'.1.3.6.1.4.1.89.108.1.1.3.1',
		SfpStatus  =>'.1.3.6.1.4.1.89.90.1.2.1.3',
		SfpVendor  =>'.1.3.6.1.4.1.35265.1.23.53.1.1.1.5',
		SfpSN      =>'.1.3.6.1.4.1.35265.1.23.53.1.1.1.6',
		SfpFreq    =>'.1.3.6.1.4.1.35265.1.23.53.1.1.1.4',
		SfpLength  =>'.1.3.6.1.4.1.35265.1.23.53.1.1.1.8',
		},
	    },
	"Huawei" => {
	    "Default"=> {
		PoeAdmin   =>'.1.3.6.1.4.1.2011.5.25.195.3.1.3',
		PoePower   =>'.1.3.6.1.4.1.2011.5.25.195.3.1.10',
		PoeCurrent =>'.1.3.6.1.4.1.4526.11.15.1.1.1.3.1',
		PoeVolt    =>'.1.3.6.1.4.1.2011.5.25.195.3.1.14',
		SfpVendor  =>'.1.3.6.1.4.1.2011.5.25.31.1.1.2.1.11',
		SfpSpeed   =>'.1.3.6.1.4.1.2011.5.25.31.1.1.2.1.2',
		SfpVolt    =>'.1.3.6.1.4.1.2011.5.25.31.1.1.3.1.6',
		SfpOptRx   =>'.1.3.6.1.4.1.2011.5.25.31.1.1.3.1.32',
		SfpOptTx   =>'.1.3.6.1.4.1.2011.5.25.31.1.1.3.1.33',
		SfpCurrent =>'.1.3.6.1.4.1.2011.5.25.31.1.1.3.1.31',
		SfpRx      =>'.1.3.6.1.4.1.2011.5.25.31.1.1.3.1.8',
		SfpTx      =>'.1.3.6.1.4.1.2011.5.25.31.1.1.3.1.9',
		},
	    },
	"Allied Telesis" => {
	    "Default" => {
		PoePower  =>'.1.3.6.1.4.1.89.108.1.1.5.1',
		PoeCurrent=>'.1.3.6.1.4.1.89.108.1.1.4.1',
		PoeVolt   =>'.1.3.6.1.4.1.89.108.1.1.3.1',
		},
	    },
	"NetGear" => {
	    "Deafult" =>{
		PoeAdmin   =>'.1.3.6.1.4.1.4526.11.15.1.1.1.6.1',
		PoePower   =>'.1.3.6.1.4.1.4526.11.15.1.1.1.2.1',
		PoeCurrent =>'.1.3.6.1.4.1.4526.11.15.1.1.1.3.1',
		PoeVolt    =>'.1.3.6.1.4.1.4526.11.15.1.1.1.4.1',
		},
	    },
	"HP" => {
	    "Default" => {
		PoePower   =>'.1.3.6.1.4.1.25506.2.14.1.1.4.1',
		PoeVolt    =>'.1.3.6.1.4.1.25506.2.14.1.1.3.1',
		},
	    },
    );

my %DefaultMibs=(
'Description'    => '.1.3.6.1.2.1.1.1.0',
'Uptime'         => '.1.3.6.1.2.1.1.3.0',
'Name'           => '.1.3.6.1.2.1.1.5.0',
);

my $snmp_session;
my $error;

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

if (!defined($snmp_session)) { return "Switch is not available<BR>"; }
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

my %switchTable;
foreach my $key (keys %bridgeOIDS) {
my $bridge_mib = $bridgeOIDS{$key};
my %table;
my $result = $snmp_session->get_bulk_request(
    -varbindlist    => [ $bridge_mib ],
    -callback       => [ \&table_callback, $bridge_mib, \%table ],
    -maxrepetitions => 10,
    );
if (!defined $result) {
    printf "ERROR: %s\n", $snmp_session->error();
    $snmp_session->close();
    exit 1;
    }
# Now initiate the SNMP message exchange.
snmp_dispatcher();
$switchTable{$key}=\%table;
}

my %switch_status;

my $poe_found;

my @keys = (keys %bridgeOIDS);
for (my $index=0; $index<scalar(@keys); $index++)  {
    my $key = $keys[$index];
    for my $oid (oid_lex_sort(keys %{$switchTable{$key}})) {
    next if (!$oid);
    my $num = extract_last_digit($bridgeOIDS{$key},$oid);
    my $value = $switchTable{$key}->{$oid};
    next if ($key ne "Index" and !exists $switch_status{$num}->{num});
    if ($key eq "Index") {
	next if ($value=~/^enet0/i);
	next if ($value=~/^po(\d)/i);
	next if ($value=~/^lo/);
	next if ($value=~/^IP/);
	next if ($value=~/^Vlan/i);
	next if ($value=~/^Null/i);
	next if ($value=~/^loop/i);
	next if ($value=~/^console/i);
	next if ($value=~/^tunnel/i);
	next if ($value=~/^MEth/);
	next if ($value=~/^bridge/i);
	next if ($value=~/^ppp/i);
        $switch_status{$num}->{num}=$value;
	}
    $switch_status{$num}->{$key}=$value;
    }
}

#my %ext_walk = %{$VendorOIDS{$vendor}{Default}};
#foreach my $key (keys %ext_walk) {
#my $bridge_mib = $ext_walk{$key};
#my %table;
#my $result = $snmp_session->get_bulk_request(
#	-varbindlist    => [ $bridge_mib ],
#        -callback       => [ \&table_callback, $bridge_mib, \%table ],
#	-maxrepetitions => 10,
#        );
#if (!defined $result) {
#	printf "ERROR: %s\n", $snmp_session->error();
#        $snmp_session->close();
#	exit 1;
#        }
## Now initiate the SNMP message exchange.
#snmp_dispatcher();
#$switchTable{$key}=\%table;
#}

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

$result.= "| *Index* | *Port* | *Alias* | *Description* | *Admin state* | *Oper state* | *Speed* | *Last change* | *Vlan* | *Poe* | *Sfp* |\n";

$result.=expand_status(\%switch_status);

return $result;
}

1;
