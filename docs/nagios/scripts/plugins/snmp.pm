package snmp;

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use utf8;
use open ":encoding(utf8)";
use strict;
use English;
use base 'Exporter';
use vars qw(@EXPORT @ISA);
use Net::SNMP;

@ISA = qw(Exporter);
@EXPORT = qw(
snmp_set_int
snmp_get_request
snmp_get_oid
init_snmp
);


BEGIN
{

#---------------------------------------------------------------------------------

sub snmp_get_request {
my $ip = shift;
my $oid = shift;
my $snmp = shift;
my $session = init_snmp ($ip,$snmp);
return if (!defined($session) or !$session);
my $result = $session->get_request( -varbindlist => [$oid]);
$session->close;
return if (!$result->{$oid});
return $result->{$oid};
}

#---------------------------------------------------------------------------------

sub snmp_set_int {
my $ip = shift;
my $oid = shift;
my $value = shift;
my $snmp = shift;
my $session = init_snmp ($ip,$snmp,1);
return if (!defined($session) or !$session);
my $result = $session->set_request( -varbindlist => [$oid,INTEGER,$value]);
$session->close;
return $result->{$oid};
}

#-------------------------------------------------------------------------------------

sub snmp_get_oid {
my ($host,$snmp,$oid) = @_;
my $port = 161;
my $session = init_snmp ($host,$snmp,0);
return if (!defined($session) or !$session);
$session->translate([-timeticks]);
my $table = $session->get_table($oid);
$session->close();
return $table;
}

#-------------------------------------------------------------------------------------

sub init_snmp {

    my ($host,$snmp) = @_;

    return if (!$host);

    ### open SNMP session
    my ($session, $error);

    if ($snmp->{version} <=2) {
        ($session, $error) = Net::SNMP->session(
		-hostname  => $host,
		-community => $snmp->{'community'} ,
		-version   => $snmp->{'version'},
		-port      => 161,
		-timeout   => $snmp->{timeout},
		);
	} else {
	($session, $error) = Net::SNMP->session(
		-hostname     => $host,
		-version      => 'snmpv3',
		-username     => $snmp->{'user'},
		-authprotocol => $snmp->{'auth'},
		-privprotocol => $snmp->{'priv'},
		-authpassword => $snmp->{'community'},
		-privpassword => $snmp->{'community'},
		-port         => 161,
		-timeout      => $snmp->{timeout},
		);
	}

    if (!defined($session)) {
        printf("ERROR: %s.\n", $error);
	exit 0;
	}

    return $session;
}

#-------------------------------------------------------------------------------------

1;
}
