package Foswiki::Plugins::UpsInfoPlugin;

use Net::SNMP;
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
$pluginName = 'UpsInfoPlugin';

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

    Foswiki::Func::registerTagHandler( 'UpsINFO', \&_UpsINFO );

    # Plugin correctly initialized
    return 1;
}

sub GetSNMPkeyValue {
my $session = shift;
my $key = shift;
my $value;
eval {
my $ret = $session->get_request( -varbindlist => [$key] );
if (!$ret) {
    #search in subtree
    $ret = $session->get_next_request( -varbindlist => [$key] );
    my $branch = $key.'.*';
    my @keys_next = keys %$ret;
    my $get_key = $keys_next[0];
    if ($get_key=~/^$branch$/) { $value = $ret->{$get_key}; }
    } else { $value = $ret->{$key}; }
};
return $value;
};

sub _UpsINFO {
    my($session, $params, $theTopic, $theWeb) = @_;
    # $session  - a reference to the Foswiki session object (if you don't know
    #             what this is, just ignore it)
    # $params=  - a reference to a Foswiki::Attrs object containing parameters.
    #             This can be used as a simple hash that maps parameter names
    #             to values, with _DEFAULT being the name for the default
    #             parameter.
    # $theTopic - name of the topic in the query
    # $theWeb   - name of the web in the query
    # Return: the result of processing the tag

    # For example, %EXAMPLETAG{'hamburger' sideorder="onions"}%
    # $params->{_DEFAULT} will be 'hamburger'
    # $params->{sideorder} will be 'onions'

my $OIDModel       = '.1.3.6.1.2.1.33.1.1.1.0';
my $SchneiderModel = '.1.3.6.1.2.1.1.1.0';
my $OIDidentBasicModel    = '1.3.6.1.4.1.318.1.1.1.1.1.1.0';

# APC OIDs
my $OIDAPC                = '1.3.6.1.4.1.318.';
my $OIDhardware           = '1.3.6.1.4.1.318.1.1.';
my $OIDups                = '1.3.6.1.4.1.318.1.1.1.';
my $OIDident              = '1.3.6.1.4.1.318.1.1.1.1.';
my $OIDidentBasic         = '1.3.6.1.4.1.318.1.1.1.1.1.';
my $OIDidentBasicName     = '1.3.6.1.4.1.318.1.1.1.1.1.2.0';
my $OIDidentAdv           = '1.3.6.1.4.1.318.1.1.1.1.2.';
my $OIDidentAdvFW         = '1.3.6.1.4.1.318.1.1.1.1.2.1.0';
my $OIDidentAdvManuf      = '1.3.6.1.4.1.318.1.1.1.1.2.2.0';
my $OIDidentAdvSerial     = '1.3.6.1.4.1.318.1.1.1.1.2.3.0';
my $OIDbattery            = '1.3.6.1.4.1.318.1.1.1.2.';
my $OIDbatteryBasic       = '1.3.6.1.4.1.318.1.1.1.2.1.';
my $OIDbatteryBasicStatus = '1.3.6.1.4.1.318.1.1.1.2.1.1.0';
my $OIDbatteryBasicTime   = '1.3.6.1.4.1.318.1.1.1.2.1.2.0';
my $OIDbatteryBasicRepl   = '1.3.6.1.4.1.318.1.1.1.2.1.3.0';
my $OIDbatteryAdv         = '1.3.6.1.4.1.318.1.1.1.2.2.';
my $OIDbatteryAdvTemp     = '1.3.6.1.4.1.318.1.1.1.2.2.2.0';
my $OIDbatteryAdvRuntime  = '1.3.6.1.4.1.318.1.1.1.2.2.3.0';
my $OIDbatteryAdvReplace  = '1.3.6.1.4.1.318.1.1.1.2.2.4.0';

my $apcUpsAdvBatteryStatus              ='1.3.6.1.4.1.318.1.1.1.4.1.1.0';
my $apcUpsAdvBatteryCapacity            ='1.3.6.1.4.1.318.1.1.1.2.2.1.0';
my $apcUpsAdvBatteryTemperature         ='1.3.6.1.4.1.318.1.1.1.2.2.2.0';
my $apcUpsAdvExtTemperature             ='1.3.6.1.4.1.318.1.1.10.2.3.2.1.4.1';
my $apcUpsAdvBatteryRunTimeRemaining    ='1.3.6.1.4.1.318.1.1.1.2.2.3.0';
my $apcUpsAdvOutputVoltage              ='1.3.6.1.4.1.318.1.1.1.4.2.1.0';
my $apcUpsAdvOutputFrequency            ='1.3.6.1.4.1.318.1.1.1.4.2.2.0';
my $apcUpsAdvOutputLoad                 ='1.3.6.1.4.1.318.1.1.1.4.2.3.0';
my $apcUpsAdvOutputCurrent              ='1.3.6.1.4.1.318.1.1.1.4.2.4.0';
my $apcUpsAdvInputLineVoltage           ='1.3.6.1.4.1.318.1.1.1.3.2.1.0';
my $apcUpsAdvInputFrequency             ='1.3.6.1.4.1.318.1.1.1.3.2.4.0';

my $apcUpsAdvInputLine3Voltage           ='1.3.6.1.4.1.318.1.1.1.3.2.3.0';
my $apcUpsAdvInputLine2Voltage           ='1.3.6.1.4.1.318.1.1.1.3.2.2.0';
my $apcUpsAdvInputLine1Voltage           ='1.3.6.1.4.1.318.1.1.1.3.2.1.0';

#EATON
my $EatonBatteryTemp =' 1.3.6.1.4.1.534.1.6.1.0';

#default UPS mib

my $BATTERYREPLACE = '2';

my $defaultModel          = '.1.3.6.1.2.1.33.1.1.2.0';
my $defaultBatteryReplace;
my $defaultBatteryStatus  = '.1.3.6.1.2.1.33.1.2.1';
my $defaultbatteryTemp    = '.1.3.6.1.2.1.33.1.2.4';
my $defaultInputAC        = '.1.3.6.1.2.1.33.1.3.3.1.3.1';
my $defaultInputHz        = '.1.3.6.1.2.1.33.1.3.3.1.2';
my $defaultOutputAC       = '.1.3.6.1.2.1.33.1.4.4.1.2';
my $defaultOutputCurrent  = '.1.3.6.1.2.1.33.1.4.4.1.3';
my $defaultOutputPower    = '.1.3.6.1.2.1.33.1.4.4.1.4';
my $defaultPercentLoad    = '.1.3.6.1.2.1.33.1.4.4.1.5';
my $defaultLiveTime       = '.1.3.6.1.2.1.33.1.2.3';
my $defaultUpsLoad        = '.1.3.6.1.2.1.33.1.4.4.1.5.1';
my $defaultUpsStatus      = '.1.3.6.1.2.1.33.1.6.3.2';
my $defaultOutputHz       = '.1.3.6.1.2.1.33.1.4.2';

my $TEMPCRIT = 55;
my $TEMPWARN = 50;

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

    my $snmp_session;
    my $error;

    ### open SNMP session
    eval {
    ($snmp_session, $error) = Net::SNMP->session( -hostname  => $host, -community => $community );
    };

    return "Ups is not available<BR>"  if (!defined($snmp_session));

    $snmp_session->translate([-timeticks]);

    my $vendor;
    my $vendor_string = GetSNMPkeyValue($snmp_session,$OIDModel);

    if (!$vendor_string) { $vendor_string = GetSNMPkeyValue($snmp_session,$OIDidentBasicModel); }
    if (!$vendor_string) { $vendor_string = GetSNMPkeyValue($snmp_session,$SchneiderModel); $vendor = 'Schneider'; }

    return "Ups model unknown!<BR>"  if (!defined($vendor_string));

    if (!$vendor and $vendor_string=~/Eaton/i) { $vendor='EATON'; }
    if (!$vendor and $vendor_string=~/APC/i) { $vendor='APC'; }
    if (!$vendor and $vendor_string=~/Symmetra/i) { $vendor='Symmetra'; }

    my $upsModel = GetSNMPkeyValue($snmp_session,$defaultModel) || GetSNMPkeyValue($snmp_session,$OIDidentBasicModel);

#    my $ret = "UPS Model: ".$upsModel." Vendor: ".$vendor." [ $vendor_string ]\n\n";
    my $ret = "UPS Model: ".$upsModel."\n\n";

    my $battery_status = GetSNMPkeyValue($snmp_session,$defaultBatteryStatus);
    my $input_voltage = GetSNMPkeyValue($snmp_session,$defaultInputAC);

    my $status = "%GREEN%ONLINE%ENDCOLOR%";
    $status = "%RED%AHEZ%ENDCOLOR%" if ($battery_status eq 1);
    $status = "%RED%ON BATTERY%ENDCOLOR%" if ($battery_status eq 3);
    $status = "%RED%On Smart Boost%ENDCOLOR%" if ($battery_status eq 4);
    $status = "%RED%Timed Sleeping%ENDCOLOR%" if ($battery_status eq 5);
    $status = "%RED%Software Bypass%ENDCOLOR%" if ($battery_status eq 6);
    $status = "%RED%OFF%ENDCOLOR%" if ($battery_status eq 7);
    $status = "%GREEN%REBOOTING%ENDCOLOR%" if ($battery_status eq 8);
    $status = "%RED%SWITCHED BYPASS%ENDCOLOR%" if ($battery_status eq 9);
    $status = "%RED%Hardware Failure Bypass%ENDCOLOR%" if ($battery_status eq 10);
    $status = "%RED%Sleeping until power return%ENDCOLOR%" if ($battery_status eq 11);
    $ret .= "| UPS Status | ".$status." |\n";

    if ($vendor eq 'APC' or $vendor eq 'Symmetra') {
        my $batteryAdvReplace = $snmp_session->get_request(-varbindlist => [$OIDbatteryAdvReplace])->{$OIDbatteryAdvReplace};
        if ($batteryAdvReplace && ($batteryAdvReplace eq $BATTERYREPLACE)) { $ret .= "| Battery Status | %RED%Battery requires replacement!%ENDCOLOR%|\n"; };
        $ret .= "| Battery Capacity | ".$snmp_session->get_request(-varbindlist => [$apcUpsAdvBatteryCapacity])->{$apcUpsAdvBatteryCapacity}."% |\n";
        }

    my $inputHz = GetSNMPkeyValue($snmp_session,$defaultInputHz);
    if ($inputHz) { $inputHz = int($inputHz/10); }


    my $runtime;
    if ($vendor eq 'APC' or $vendor eq 'Symmetra') {
        $runtime = GetSNMPkeyValue($snmp_session,$OIDbatteryAdvRuntime);
        $runtime=int($runtime/6000);
        }

    if ($vendor eq 'EATON') { $runtime = GetSNMPkeyValue($snmp_session,'.1.3.6.1.4.1.534.1.2.1'); }
    if (!$runtime) { $runtime = GetSNMPkeyValue($snmp_session,$defaultLiveTime); }
    if ($vendor eq 'EATON') { $runtime=int($runtime/60); }

    my $outputCurrent;
    if ($vendor eq 'APC' or $vendor eq 'Symmetra') { $outputCurrent = GetSNMPkeyValue($snmp_session,$apcUpsAdvOutputCurrent); }
    if (!$outputCurrent) { $outputCurrent = GetSNMPkeyValue($snmp_session,$defaultOutputCurrent); }
    if ($outputCurrent) { $outputCurrent=$outputCurrent/10; }

    my $batTemp;
    if ($vendor eq 'APC' or $vendor eq 'Symmetra') { $batTemp=GetSNMPkeyValue($snmp_session,$OIDbatteryAdvTemp); }
    if ($vendor eq 'EATON') { $batTemp=GetSNMPkeyValue($snmp_session,$EatonBatteryTemp); }
    if (!$batTemp) { $batTemp=GetSNMPkeyValue($snmp_session,$defaultbatteryTemp); }

    my $extTemp;
    if ($vendor eq 'APC' or $vendor eq 'Symmetra') { $extTemp=GetSNMPkeyValue($snmp_session,$apcUpsAdvExtTemperature); }

    my $outputVoltage;
    if ($vendor eq 'APC' or $vendor eq 'Symmetra') { $outputVoltage=GetSNMPkeyValue($snmp_session,$apcUpsAdvOutputVoltage); }
    if (!$outputVoltage) { $outputVoltage = GetSNMPkeyValue($snmp_session,$defaultOutputAC); }

    my $outputHz;
    if ($vendor eq 'APC' or $vendor eq 'Symmetra') { $outputHz=GetSNMPkeyValue($snmp_session,$apcUpsAdvOutputFrequency); }
    if (!$outputHz) { $outputHz = GetSNMPkeyValue($snmp_session,$defaultOutputHz); }
    if ($outputHz and $outputHz>100 ) { $outputHz = int($outputHz/10); }

    my $outputLoad;
    if ($vendor eq 'APC' or $vendor eq 'Symmetra') { $outputLoad=GetSNMPkeyValue($snmp_session,$apcUpsAdvOutputLoad); }
    if (!$outputLoad) { $outputLoad = GetSNMPkeyValue($snmp_session,$defaultUpsLoad); }

    $ret .= "| Runtime Remaining | ". $runtime." min. |\n";

    if ($extTemp) {
        my $TempStatus = "%GREEN%".$extTemp." C%ENDCOLOR%";
        $TempStatus = "%RED%".$extTemp." C%ENDCOLOR%" if ($extTemp>28);
        $ret .= "| Room Temperature | ".$TempStatus." |\n";
        }

    if ($batTemp) {
        my $TempStatus = "%GREEN%".$batTemp." C%ENDCOLOR%";
        $TempStatus = "%RED%".$batTemp." C%ENDCOLOR%" if ($batTemp>38);
        $ret .= "| Battery Temperature | ".$TempStatus." |\n";
        }

    if ($vendor eq 'Symmetra') {
        my $input1=GetSNMPkeyValue($snmp_session,$apcUpsAdvInputLine1Voltage);
        my $input2=GetSNMPkeyValue($snmp_session,$apcUpsAdvInputLine2Voltage);
        my $input3=GetSNMPkeyValue($snmp_session,$apcUpsAdvInputLine3Voltage);
        $ret .= "| Input Line1 Voltage | ".$input1." V |\n";
        $ret .= "| Input Line2 Voltage | ".$input2." V |\n";
        $ret .= "| Input Line3 Voltage | ".$input3." V |\n";
        }

    if ($vendor ne 'Symmetra') {
        $ret .= "| Input Voltage | ".$input_voltage." V |\n";
        $ret .= "| Input Frequency | ".$inputHz." Hz |\n";
        }

    $ret .= "| Output Voltage | ".$outputVoltage." V |\n";
    $ret .= "| Output Current | ".$outputCurrent."  A |\n";
    $ret .= "| Output Frequency | ".$outputHz." Hz |\n";
    $ret .= "| Output Load | ".$outputLoad." % |\n";


    $snmp_session->close;

    return $ret;
}


1;
