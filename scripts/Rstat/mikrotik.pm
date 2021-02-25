package Rstat::mikrotik;

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use strict;
use English;
use FindBin '$Bin';
use lib "$Bin";
use base 'Exporter';
use vars qw(@EXPORT @ISA);
use Data::Dumper;
use Rstat::config;
use Rstat::main;
use Net::Telnet;
use Rstat::cmd;
use Rstat::net_utils;
use NetAddr::IP;
use Net::IPv4Addr qw( :all );
use Rstat::mysql;

@ISA = qw(Exporter);
@EXPORT = qw(
Login_Mikrotik
backup_Mikrotik
get_arp_cache_Mikrotik
get_fdb_table_Mikrotik
ping_Mikrotik
run_cmd_Mikrotik
telnet_stringMi
telnet_string_verboseMi
);




BEGIN
{

#------------------------------- Mikrotik switch -----------------------------------

sub Login_Mikrotik {
my $ip=shift;
my $switch_type='mikrotik';
log_session("Connect to switch $ip");

my $timeout = $def_timeout;
my $prompt=qr/\[(.*)+\@(.*)+\]\s+> $/;

my $t;
#for connect to session with timeout
if ($debug) {
    $t = new Net::Telnet (Timeout => $timeout, Max_buffer_length=>10240000, Port => $router_port, Prompt => "/$prompt/", Dump_Log=>"$LOG_DIR/telnet-$ip.log") or die;
    } else {
    $t = new Net::Telnet (Timeout => $timeout, Max_buffer_length=>10240000, Port => $router_port, Prompt => "/$prompt/") or die;
    }
$t->open($ip);
$t->login($router_login.'+ct400w',$router_password);
log_cmd($t,"/system note set show-at-login=no",1,$t->prompt);
return $t;
}

#---------------------------------------------------------------------------------------------------------

sub telnet_stringMi {
my ($ip,$lines) = @_;
eval {
my $t = Login_Mikrotik($ip);
log_cmd3($t,$lines);
};
if ($@) { log_error("Switch $ip:\n $@"); return 0; };
return 1;
}

#---------------------------------------------------------------------------------------------------------

sub telnet_string_verboseMi {
my ($ip,$lines) = @_;
my $blabla;
eval {
my $t = Login_Mikrotik($ip);
$blabla=log_cmd3($t,$lines);
};
if ($@) { log_error("Switch $ip:\n $@"); return $@; };
return $blabla;
}

#---------------------------------------------------------------------------------

sub run_cmd_Mikrotik {
my $t=shift;
my $cmd=shift;
my $cmd_id = shift || 1;
run_command($t,$cmd,$cmd_id);
}

#---------------------------------------------------------------------------------

sub backup_Mikrotik {
my $t=shift;
my @config=log_cmd($t,"/export");
return \@config;
}

#---------------------------------------------------------------------------------

sub get_arp_cache_Mikrotik {
my $t=shift;
my $interface = shift;
my %arp_cache;
my @arp_info=log_cmd($t,"/ip arp print without-paging");
#Flags: X - disabled, I - invalid, H - DHCP, D - dynamic, P - published
# #   ADDRESS         MAC-ADDRESS       INTERFACE
# 0 D 10.170.200.251  00:13:49:9C:CD:D3 vlan1000-managment
# 1 D 10.170.200.250  00:15:6D:60:CD:CA vlan1000-managment
chomp(@arp_info);
foreach my $arp_str (@arp_info) {
    next if (!$arp_str);
    $arp_str=trim($arp_str);
    next if (!($arp_str=~/^\d/));
    next if ($interface and ($arp_str!~/$interface/));
    my @values = split(/\s+/,$arp_str);
    next if (!$values[2]);
    $arp_cache{$values[2]}=$values[3];
    }
return \%arp_cache;
}

#---------------------------------------------------------------------------------

sub get_fdb_table_Mikrotik {
my $t=shift;
my @fdb_info=log_cmd($t,"/interface bridge host print without-paging");
chomp(@fdb_info);
return @fdb_info;
}

#---------------------------------------------------------------------------------

sub ping_Mikrotik {
my $t=shift;
my $ip = shift;
my @ping_info=log_cmd($t,"/ping count=5 $ip");
chomp(@ping_info);
return @ping_info;
}

1;
}
