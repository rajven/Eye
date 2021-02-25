package Rstat::mfi;

#use v5.28;
use utf8;
use open ":encoding(utf8)";

use strict;
use English;
use FindBin '$Bin';
use lib "$Bin";
use base 'Exporter';
use vars qw(@EXPORT @ISA);
use Rstat::config;
use Rstat::main;
use Rstat::cmd;
use Net::Telnet;
use Data::Dumper;

@ISA = qw(Exporter);
@EXPORT = qw(
get_mfi_config
);

BEGIN
{

sub get_mfi_config {

my $ip = shift;
my $login = 'admin';
my $password = 'werkraft';
my $result;

my $ROUTER_PROMPT   = qr/[-\w]+\#.$/;

eval {
my $t = new Net::Telnet (Timeout => 30, Port => 23, Prompt =>"/$ROUTER_PROMPT/");
#MFI02dc9fdb144bd3 login: admin
#Password:
#MF.v2.1.11#
$t->open($ip);
$t->waitfor('/login\:/');
$t->print($login);
$t->waitfor('/Password\:/');
$t->print($password);
$t->waitfor('/[#>\?]/');
my @mca_status=log_cmd($t,'cat /etc/board.info');
sleep(1);
my @tmp = log_cmd($t,'grep 1 /proc/analog/enabled*');
push(@mca_status,@tmp);
sleep(1);
@tmp = log_cmd($t,'cat /etc/persistent/cfg/config_file');
$t->close();

push(@mca_status,@tmp);

@tmp = grep (/board.name/,@mca_status);
my $board_type;
if ($tmp[0]=~/=(.*)/) {
    $board_type = $1 if ($1);
    }

return if (!$board_type);
return if (!($board_type=~/mPort/i));

my $sens_list;
@tmp = grep (/enabled/,@mca_status);

foreach my $sens (@tmp) {
next if (!$sens);
my $sens_id;
my $index;
if ($sens=~/enabled(\d):1/) {
    $index = $1;
    $sens_id = $index - 1;
    }
next if(!defined $sens_id);
my @sens_info = grep (/AI.$sens_id.label/,@mca_status);
my $sens_name = $sens_info[0];
next if (!$sens_name);
if ($sens_name=~/label=(.*)/) { $sens_name=$1; } else { undef $sens_name; }
next if (!$sens_name);
my $type = 'temp';
if ($sens_name=~/HUM/i) { $type = 'hum'; }
$result->{$sens_name}->{'name'}=$sens_name;
$result->{$sens_name}->{'type'}=$type;
$result->{$sens_name}->{'index'}=$index;
}
};

return $result;
}

1;
}
