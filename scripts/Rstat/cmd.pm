package Rstat::cmd;

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

@ISA = qw(Exporter);
@EXPORT = qw( log_cmd log_cmd2 log_cmd3 log_cmd4 flush_telnet run_command);

BEGIN
{


#---------------------------------------------------------------------------------
# Execute command and wait answer from device
# Args: t - telnet session, $command - command string, $sleep - pause after execute command (enabled by default)
#
sub log_cmd {
my $t = shift;
my $command = shift;
my $sleep = shift || 1;
if (!$t) { die "Telnet session not exists!"; }
$t->binmode(0);
$t->errmode('return');

log_session('Send:'.$command);

my @ret=$t->cmd(String => $command);

my @a=();

foreach my $row (@ret) {
    next if (!$row);
    #zyxel patch
    $row=~ s/\x1b\x37//g;
    #mikrotik patch
    $row=~ s/\x0d\x0d\x0d\x1b\x5b\x39\x39\x39\x39\x42//g;
    #new line
    $row=~ s/\n//g;
    $row=trim($row);
    if ($row) {
        my @tmp=split("\n",$row);
        foreach  my $line (@tmp) {
		next if (!$line);
		$line=trim($line);
		next if (!$line);
		push(@a,$line);
		log_session('Get:'.$line);
		}
	}
}

select(undef, undef, undef, 0.15) if ($sleep);

if (scalar(@a)) {  return @a; }

$t->cmd(String => "\n");
my @tmp=flush_telnet($t);
foreach my $line (@tmp) {
    next if (!$line);
    push(@a,$line);
    }

return @a;
}

#---------------------------------------------------------------------------------
# Execute command list array without confirmation from device
# Args: t - telnet session, $command - array of command string, $sleep - pause after execute command (enabled by default)
#
sub log_cmd2 {
my $t = shift;
my $command = shift;
my $sleep = shift || 1;
if (!$t) { die "Telnet session not exists!"; }
$t->binmode(0);
$t->errmode("return");
$t->cmd(String => "\n");
$t->buffer_empty;
my @a;
foreach my $out (split("\n",$command)){
        if ($out =~ /SLEEP/) {
            if ($out =~ /SLEEP\s+(\d+)/) { sleep($1); } else { sleep(5); };
            next;
            }
        chomp($out);
        log_session('Send:'.$out);
        $t->print($out);
        #sleep 250 ms
        select(undef, undef, undef, 0.25) if ($sleep);
        foreach my $str ($t->waitfor($t->prompt)) {
	    $str=trim($str);
	    if ($str) {
	            my @tmp=split("\n",$str);
		    foreach  my $line (@tmp) {
			next if (!$line);
			$line=trim($line);
			next if (!$line);
			push(@a,$line);
			log_session('Get:'.$line);
			}
		    }
            }
    }
chomp(@a);
return @a;
}

#---------------------------------------------------------------------------------
# Execute command list array without confirmation from device and press any key by device prompt
# Args: t - telnet session, $command - array of command string, $sleep - pause after execute command (enabled by default)
#
sub log_cmd3 {
my $t = shift;
my $lines = shift;
my $sleep = shift || 1;
if (!$t) { die "Telnet session not exists!"; }
$t->errmode("return");
$t->buffer_empty;
$t->binmode(0);
my @result=();
foreach my $out (split("\n",$lines)) {
        if ($out =~ /SLEEP/i) {
            if ($out =~ /SLEEP\s+(\d+)/i) { log_session('WAIT:'." $1 sec."); sleep($1); } else { log_session('WAIT:'." 10 sec."); sleep(10); };
            next;
            }
        chomp($out);
        log_session('Send:'.$out);
        $t->print($out);
        #sleep 250 ms
        select(undef, undef, undef, 0.25) if ($sleep);
        my $end = 0;
        my $get;
        while ($end == 0) {
            foreach my $str ($t->waitfor('/[(#)(\>)(\:)(press)(sure)(Please input)(next page)(continue)(quit)(-- more --)(Confirm)(ESC)(^C$)]/')) {
                $t->print("\n") if $str =~ /ENTER/i;
                $t->print(" ") if $str =~ /ESC/i;
                $t->print(" ") if $str =~ /^C$/i;
                $t->print(" ") if $str =~ /(-- more --)/i;
                $t->print(" ") if $str =~ /SPACE/i;
                $t->print("y\n") if $str =~ /sure/i;
                $t->print("\n") if $str =~ /continue/i;
                $t->print("y\n") if $str =~ /Please input/i;
                $t->print("Y\n") if $str =~ /Confirm/i;
                $t->print("Y\n") if $str =~ /\:/;
                #last line!!!
                $end = 1 if $str =~ /\>/;
                $get .= $str;
                }
            }
        log_debug('Get:'.$get) if ($get);
        push(@result,split(/\n/,$get));
    }
log_session('Get:'.Dumper(\@result));
return @result;
}

#---------------------------------------------------------------------------------
# Execute command list array without confirmation from device and press any key by device prompt
# Args: t - telnet session, $command - array of command string, $sleep - pause after execute command (enabled by default)
#
sub log_cmd4 {
my $t = shift;
my $lines = shift;
if (!$t) { die "Telnet session not exists!"; }
$t->errmode("return");
$t->buffer_empty;
$t->binmode(0);
my @result=();
log_session('Send:'.$lines);
$t->print($lines);
#sleep 250 ms
select(undef, undef, undef, 0.25);
my ($prematch, $match)=$t->waitfor('/\[.*\] >/');
log_debug("Get: $prematch, $match");
push(@result,split(/\n/,$prematch));
log_session('Get:'.Dumper(\@result));
return @result;
}

#--------------------------------------------------------------------------------- 

sub flush_telnet {
my $t = shift;
return if (!$t);
my @a=();
$t->buffer_empty;
$t->print("\n");
foreach my $str ($t->waitfor($t->prompt)) {
    next if (!$str);
    my @tmp=split("\n",$str);
    foreach my $row (@tmp) {
        $row=trim($row);
        next if (!$row);
        log_session('Flush:'.$row);
        push(@a,$row);
    }
}
$t->buffer_empty;
return(@a);
}

#---------------------------------------------------------------------------------

sub run_command {
my $t=shift;
my $cmd=shift;
my $cmd_id = shift || 1;
my @tmp=();
if (ref($cmd) eq 'ARRAY') {
    @tmp = @{$cmd};
    } else {
    push(@tmp,$cmd);
    }
eval {
foreach my $run_cmd (@tmp) {
    next if (!$run_cmd);
    log_cmd($t,$run_cmd) if ($cmd_id == 1);
    log_cmd2($t,$run_cmd) if ($cmd_id == 2);
    log_cmd3($t,$run_cmd) if ($cmd_id == 3);
    }
};
if ($@) { log_error("Abort: $@"); return 0; };
return 1;
}

1;
}
