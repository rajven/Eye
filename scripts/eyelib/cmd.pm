package eyelib::cmd;

#
# Copyright (C) Roman Dmitriev, rnd@rajven.ru
#

use utf8;
use open ":encoding(utf8)";
use strict;
use English;
use FindBin '$Bin';
use lib "/opt/Eye/scripts";
use base 'Exporter';
use vars qw(@EXPORT @ISA);
use Data::Dumper;
use eyelib::config;
use eyelib::main;
use Net::Telnet;
use Net::OpenSSH;
use Expect;

@ISA = qw(Exporter);
@EXPORT = qw(
log_cmd
log_cmd2
log_cmd3
log_cmd4
flush_telnet
run_command
netdev_login
netdev_cmd
netdev_backup
netdev_set_port_descr
netdev_set_hostname
netdev_set_enable
netdev_wr_mem
);

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
# Args: t - telnet session, $command - array of command string
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
    log_cmd4($t,$run_cmd) if ($cmd_id == 4);
    }
};
if ($@) { log_error("Abort: $@"); return 0; };
return 1;
}

#---------------------------------------------------------------------------------

sub netdev_login {
my $device = shift;

#skip unknown vendor
if (!$switch_auth{$device->{vendor_id}}) { return 0; }

my $dev_ident = $device->{device_name}." [$device->{ip}]:: ";

my $t;

#open my $out, '>', "/tmp/debug-$device->{ip}.txt" or warn $!;
#$Net::OpenSSH::debug_fh = $out;
#$Net::OpenSSH::debug = -1;

if ($device->{proto} eq 'telnet') {
    if (!$device->{port}) { $device->{port} = '23'; }
    log_info($dev_ident. "Try login $device->{ip}:$device->{port} by telnet...");
    #zyxel patch
    if ($device->{vendor_id} eq '4') {
        eval {
            my $t1 = new Net::Telnet (Timeout => 5, Port => $device->{port}, Max_buffer_length=>10240000, Prompt =>"/$switch_auth{$device->{vendor_id}}{prompt}/");
            $t1->open($device->{ip}) or return 0;
            if (exists $switch_auth{$device->{vendor_id}}{login}) { $t1->waitfor("/$switch_auth{$device->{vendor_id}}{login}/"); }
            $t1->print($device->{login});
            if (exists $switch_auth{$device->{vendor_id}}{password}) { $t1->waitfor("/$switch_auth{$device->{vendor_id}}{password}/"); }
            $t1->print($device->{password});
            $t1->waitfor("/$switch_auth{$device->{vendor_id}}{prompt}/");
            $t1->cmd("exit");
            $t1->close;
            };
        }

    eval {
#        $t = new Net::Telnet (Timeout => 10, Port => $device->{port}, Max_buffer_length=>10240000, Prompt =>"/$switch_auth{$device->{vendor_id}}{prompt}/", Dump_Log=>'/tmp/1');
        $t = new Net::Telnet (Timeout => 30, Port => $device->{port}, Max_buffer_length=>10240000, Prompt =>"/$switch_auth{$device->{vendor_id}}{prompt}/");
        $t->open($device->{ip}) or return;
        if (exists $switch_auth{$device->{vendor_id}}{login}) { $t->waitfor("/$switch_auth{$device->{vendor_id}}{login}/"); }
        if ($device->{vendor_id} eq '9') { $t->print($device->{login}.'+ct400w'); } else { $t->print($device->{login}); }
        if (exists $switch_auth{$device->{vendor_id}}{password}) { $t->waitfor("/$switch_auth{$device->{vendor_id}}{password}/"); }
        $t->print($device->{password});
        $t->waitfor("/$switch_auth{$device->{vendor_id}}{prompt}/");
        };
    if ($@) { log_error($dev_ident."Login by telnet aborted: $@"); return 0; } else { log_info($dev_ident."Login by telnet success!"); }
    }

if ($device->{proto} eq 'ssh') {
    if (!$device->{port}) { $device->{port} = '22'; }
    log_info($dev_ident."Try login to $device->{ip}:$device->{port} by OpenSSH...");
	$t = Net::OpenSSH->new($device->{ip},
	    user=>$device->{login},
	    password=>$device->{password},
	    port=>$device->{port},
	    timeout=>30,
	    strict_mode=>0,
	    master_opts => [ 
	    -o => "StrictHostKeyChecking=no", 
	    -o => "PubkeyAcceptedKeyTypes=+ssh-dss", 
	    -o => "KexAlgorithms=+diffie-hellman-group-exchange-sha1,diffie-hellman-group14-sha1",
	    -o => "HostKeyAlgorithms=+ssh-dss",
	    -o => "LogLevel=quiet",
	    -o => "UserKnownHostsFile=/dev/null"
	    ]
	    );

        if ($t->error) {  log_error($dev_ident."Login by ssh aborted: ".$t->error); return 0; }
    }

if ($device->{proto} eq 'essh') {
	if (!$device->{port}) { $device->{port} = '22'; }
	log_info($dev_ident."Try login to $device->{ip}:$device->{port} by ssh::expect...");

	$t = Expect->spawn("ssh -o StrictHostKeyChecking=no -o PubkeyAcceptedKeyTypes=+ssh-dss -o KexAlgorithms=+diffie-hellman-group-exchange-sha1,diffie-hellman-group14-sha1 -o HostKeyAlgorithms=+ssh-dss -o LogLevel=quiet -o UserKnownHostsFile=/dev/null $device->{login}\@$device->{ip}");
	$t->log_stdout(0);  # Disable logging to stdout

	$t->expect(30,
	[ qr/(?i)password:/ => sub {
    	    my $exp = shift;
    	    $exp->send("$device->{password}\n");
    	    exp_continue;
	}],
	[ qr/(?i)yes\/no/ => sub {
        my $exp = shift;
        $exp->send("yes\n");
        exp_continue;
	}]
	);
    }

if ($t) {
    log_info($dev_ident."Login by ssh success!");
    } else {
    log_error($dev_ident."Login by ssh failed!");
    return 0;
    }

netdev_set_enable($t,$device);

my @init_cmd=();

if ($device->{vendor_id} eq '2') {
        push(@init_cmd,"terminal datadump");
        push(@init_cmd,"no logging console");
        }
if ($device->{vendor_id} eq '5') {
        push(@init_cmd,"terminal page-break disable");
        }
if ($device->{vendor_id} eq '6') {
        push(@init_cmd,"terminal length 0");
        }
if ($device->{vendor_id} eq '9') {
        push(@init_cmd,"/system note set show-at-login=no");
        }
if ($device->{vendor_id} eq '16') {
        push(@init_cmd,"terminal width 0");
        }
if ($device->{vendor_id} eq '17') {
        push(@init_cmd,"more displine 50");
        push(@init_cmd,"more off");
        }
if ($device->{vendor_id} eq '38') {
        push(@init_cmd,"disable cli prompting");
        push(@init_cmd,"disable clipaging");
        }
netdev_cmd($device,$t,\@init_cmd,3);

return $t;
}

#---------------------------------------------------------------------------------

sub netdev_set_enable {
my $session = shift;
my $device = shift;
return if (!exists $switch_auth{$device->{vendor_id}}{enable});
my $cmd = $switch_auth{$device->{vendor_id}}{enable};
netdev_cmd($device,$session,$cmd,3);
if ($device->{enable_password}) { netdev_cmd($device,$session,$device->{enable_password},3); }
}

#---------------------------------------------------------------------------------

sub netdev_cmd {
my ($device,$session,$cmd,$telnet_version)=@_;
my @result=();
my @tmp=();
if (ref($cmd) eq 'ARRAY') { @tmp = @{$cmd}; } else { @tmp = split(/\n/,$cmd); }

my $dev_ident = $device->{device_name}." [$device->{ip}]:: ";

if ($device->{proto} eq 'ssh') {
    eval {
    foreach my $run_cmd (@tmp) {
        next if (!$run_cmd);
        if ($run_cmd =~ /SLEEP/i) {
            if ($run_cmd =~ /SLEEP\s+(\d+)/i) { log_session($dev_ident.'WAIT:'." $1 sec."); sleep($1); } else { log_session($dev_ident.'WAIT:'." 10 sec."); sleep(10); };
            next;
            }
        log_session($dev_ident.'Send: '.$run_cmd);
        select(undef, undef, undef, 0.25);
        my @row = $session->capture($run_cmd."\r\n");
	chomp(@row);
        push(@result,@row);
#	my ($output, $errput) = $session->capture2({timeout => 5}, $run_cmd);
#	$session->error and die "ssh failed: " . $session->error;
#	chomp($output);
#        push(@result,$output);
        }
    log_session($dev_ident.'Get: '.Dumper(\@result));
    };
    if ($@) { log_error("Abort: $@"); return 0; };
    }

if ($device->{proto} eq 'essh') {
    eval {
    foreach my $run_cmd (@tmp) {
        next if (!$run_cmd);
        if ($run_cmd =~ /SLEEP/i) {
            if ($run_cmd =~ /SLEEP\s+(\d+)/i) { log_session($dev_ident.'WAIT:'." $1 sec."); sleep($1); } else { log_session($dev_ident.'WAIT:'." 10 sec."); sleep(10); };
            next;
            }
        log_session($dev_ident.'Send: '.$run_cmd);
        $session->send("$run_cmd\n");
        select(undef, undef, undef, 0.25);
        $session->expect(10, -re => qr/$device->{prompt}/);
	push(@result,$session->before());
        }
    log_session($dev_ident.'Get: '.Dumper(\@result));
    };
    if ($@) { log_error($dev_ident."Abort: $@"); return 0; };
    }

if ($device->{proto} eq 'telnet') {
    if (!$telnet_version) { $telnet_version = 1; }
    eval {
    foreach my $run_cmd (@tmp) {
        next if (!$run_cmd);
        my @ret=();
        @ret=log_cmd($session,$run_cmd) if ($telnet_version == 1);
        @ret=log_cmd2($session,$run_cmd) if ($telnet_version == 2);
        @ret=log_cmd3($session,$run_cmd) if ($telnet_version == 3);
        @ret=log_cmd4($session,$run_cmd) if ($telnet_version == 4);
        if (scalar @ret) { push(@result,@ret); }
        select(undef, undef, undef, 0.25);
        }
    };
    if ($@) { log_error($dev_ident."Abort: $@"); return 0; };
    }
return @result;
}

#---------------------------------------------------------------------------------

sub netdev_backup {
my $device = shift;
my $tftp_ip = shift;

#eltex
if ($device->{vendor_id} eq '2') {
    eval {
        my $session = netdev_login($device);
        my $cmd = "upload startup-config tftp $tftp_ip $device->{device_name}.cfg";
        netdev_cmd($device,$session,$cmd,1);
        };
    }

#huawei
if ($device->{vendor_id} eq '3') {
    eval {
        my $cmd = "quit\ntftp $tftp_ip put vrpcfg.zip $device->{device_name}.zip\nSLEEP 5\n";
        netdev_cmd($device,undef,$cmd,3);
        };
    }

#zyxel
if ($device->{vendor_id} eq '4') {
    eval {
        my $session = netdev_login($device);
        my $cmd = "copy running-config tftp $tftp_ip $device->{device_name}.cfg";
        netdev_cmd($device,$session,$cmd,1);
        };
    }

#raisecom
if ($device->{vendor_id} eq '5') {
    eval {
        my $session = netdev_login($device);
        my $cmd = "upload startup-config tftp $tftp_ip $device->{device_name}.cfg";
        netdev_cmd($device,$session,$cmd,1);
        };
    }

#SNR
if ($device->{vendor_id} eq '6') {
    eval {
        my $session = netdev_login($device);
my $cmd = "copy running-config tftp://$tftp_ip/$device->{device_name}.cfg
Y
";
        netdev_cmd($device,$session,$cmd,3);
        };
    }

#Dlink
if ($device->{vendor_id} eq '7') {
    eval {
        my $session = netdev_login($device);
        my $cmd = "upload cfg_toTFTP $tftp_ip dest_file $device->{device_name}.cfg src_file config.cfg";
        netdev_cmd($device,$session,$cmd,1);
        };
    }

#allied telesys x210,x610
if (in_array([50..53],$device->{device_model_id})) {
    eval {
        my $session = netdev_login($device);
my $cmd = "copy running-config tftp
SLEEP 2
$tftp_ip
SLEEP 2
$device->{device_name}.cfg
SLEEP 5
";
    netdev_cmd($device,$session,$cmd,2);
    };
    }
#allied telesys 8000
if ($device->{device_model_id} eq '3') {
    eval {
        my $session = netdev_login($device);
        my $cmd = "copy running-config tftp://$tftp_ip/$device->{device_name}.cfg";
        netdev_cmd($device,$session,$cmd,2);
        };
    }
#allied telesys 8100
if ($device->{device_model_id} eq '4') {
    eval {
        my $session = netdev_login($device);
        my $cmd = "copy flash tftp $tftp_ip boot.cfg";
        netdev_cmd($device,$session,$cmd,2);
        rename $tftp_dir."/boot.cfg",$tftp_dir."/$device->{device_name}".".cfg";
        };
    }

#mikrotik
if ($device->{vendor_id} eq '9') {
    eval {
        my $session = netdev_login($device);
        log_cmd($session,"/system note set show-at-login=no",1,$session->prompt);
        my $cmd = "/export";
        my @netdev_cfg = netdev_cmd($device,$session,$cmd,4);
        write_to_file($tftp_dir."/$device->{device_name}.cfg","Config for $device->{device_name}",0);
        foreach my $row (@netdev_cfg) { write_to_file($tftp_dir."/$device->{device_name}.cfg",$row,1); }
        };
    }

#cisco
if ($device->{vendor_id} eq '16') {
    eval {
        my $session = netdev_login($device);
my $cmd = "
copy system:/running-config tftp:
SLEEP 2
$tftp_ip
SLEEP 2
$device->{device_name}.cfg
SLEEP 5
";
    netdev_cmd($device,$session,$cmd,2);
    };
    }

#maipu
if ($device->{vendor_id} eq '17') {
    eval {
        my $session = netdev_login($device);
my $cmd = "
filesystem
copy running-config tftp $tftp_ip $device->{device_name}.cfg
SLEEP 5
exit
";
    netdev_cmd($device,$session,$cmd,1);
    };
    }

#Qtech
if ($device->{vendor_id} eq '38') {
    eval {
        my $session = netdev_login($device);
        my $cmd = "upload configuration tftp $tftp_ip $device->{device_name}.cfg";
        netdev_cmd($device,$session,$cmd,1);
        };
    }

#Extreme
if ($device->{vendor_id} eq '39') {
    eval {
        my $session = netdev_login($device);
        my $cmd = "upload configuration $tftp_ip $device->{device_name}.cfg vr \"VR-Default\"";
        netdev_cmd($device,$session,$cmd,1);
        };
    }
}

#---------------------------------------------------------------------------------

sub netdev_set_port_descr {
my $session = shift;
my $device = shift;
my $port = shift;
my $port_num = shift;
my $descr = shift;

my $cmd;
my $telnet_cmd_mode = 4;

return if (!$session);

#eltex
if ($device->{vendor_id} eq '2') {
    if (!$descr) { $descr = "no description"; } else { $descr = "description $descr"; }
$cmd = "
conf t
interface $port
$descr
exit
exit";
    }

#huawei
if ($device->{vendor_id} eq '3') {
    if (!$descr) { $descr = "undo description"; } else { $descr = "description $descr"; }
$cmd = "
interface $port
$descr
quit";
    }

#zyxel
if ($device->{vendor_id} eq '4') {
    $telnet_cmd_mode = 1;
    if (!$descr) { $descr = "name ''"; } else { $descr = "name $descr"; }
$cmd = "
conf t
interface port-channel $port_num
$descr
exit
exit";
    }

#raisecom
if ($device->{vendor_id} eq '5') {
    if (!$descr) { $descr = "no description"; } else { $descr = "description $descr"; }
$cmd = "
conf t
interface $port_num
$descr
exit
exit";
    }

#SNR
if ($device->{vendor_id} eq '6') {
    $telnet_cmd_mode = 1;
    if (!$descr) { $descr = "no description"; } else { $descr = "description $descr"; }
$cmd = "
conf t
interface $port
$descr
exit
exit";
    }

#Dlink
if ($device->{vendor_id} eq '7') {
    $telnet_cmd_mode = 1;
    if (!$descr) { $descr = "clear_description"; } else { $descr = "description $descr"; }
    $cmd = "config ports $port_num $descr";
    }

#allied telesys x210,x610
if ($device->{vendor_id} eq '8') {
    if (!$descr) { $descr = "no description"; } else { $descr = "description $descr"; }
    $telnet_cmd_mode = 2;
$cmd = "
conf t
interface $port
$descr
exit
exit";
    }
#allied telesys 8000
if ($device->{device_model_id} eq '3') {
    if (!$descr) { $descr = "no description"; } else { $descr = "description $descr"; }
    $telnet_cmd_mode = 2;
$cmd = "
conf
interface ethernet $port
$descr
exit
exit";
    }
#allied telesys 8100
if ($device->{device_model_id} eq '4') {
    if (!$descr) { $descr = "no description"; } else { $descr = "description $descr"; }
    $telnet_cmd_mode = 2;
$cmd = "
conf t
interface $port
$descr
exit
exit";
    }

#mikrotik
if ($device->{vendor_id} eq '9') {
    $telnet_cmd_mode = 4;
    if (!$descr) { $descr='""'; } else { $descr='"'.$descr.'"'; }
    $cmd = "/interface ethernet set [ find default-name=$port ] description=".$descr;
    }

#cisco
if ($device->{vendor_id} eq '16') {
    if (!$descr) { $descr = 'description ""'; } else { $descr = "description $descr"; }
$cmd = "
conf t
interface $port
$descr
exit
exit";
    }

#maipu
if ($device->{vendor_id} eq '17') {
    if (!$descr) { $descr = "no description"; } else { $descr = "description $descr"; }
$cmd = "
conf t
port $port
$descr
exit
exit";
    }

#Qtech
if ($device->{vendor_id} eq '38') {
    if (!$descr) { $descr = "no description"; } else { $descr = "description $descr"; }
$cmd = "
conf t
interface $port
$descr
exit
exit";
    }

#Extreme
if ($device->{vendor_id} eq '39') {
    if ($descr) {
        $cmd = "configure port $port_num display $descr";
        } else {
        $cmd = "unconfigure port $port_num display";
        }
    }

netdev_cmd($device,$session,$cmd,$telnet_cmd_mode);
}

#---------------------------------------------------------------------------------

sub netdev_set_hostname {
my $session = shift;
my $device = shift;

my $cmd;
my $telnet_cmd_mode = 4;

return if (!$session);

#eltex
if ($device->{vendor_id} eq '2') {
$cmd = "
conf t
hostname $device->{device_name}
exit";
}

#huawei
if ($device->{vendor_id} eq '3') {
    $cmd = "sysname $device->{device_name}";
    }

#zyxel
if ($device->{vendor_id} eq '4') {
$telnet_cmd_mode = 1;
$cmd = "
conf t
hostname $device->{device_name}
exit";
    }

#raisecom
if ($device->{vendor_id} eq '5') {
    $cmd = "hostname $device->{device_name}";
    }

#SNR
if ($device->{vendor_id} eq '6') {
$telnet_cmd_mode = 1;
$cmd = "
conf t
hostname $device->{device_name}
exit";
}

#Dlink
if ($device->{vendor_id} eq '7') {
    $telnet_cmd_mode = 1;
    $cmd = "config hostname $device->{device_name}";
    }

#allied telesys x210,x610 - default
if ($device->{vendor_id} eq '8') {
$telnet_cmd_mode = 2;
$cmd = "
conf t
hostname $device->{device_name}
exit";
}

#allied telesys 8000
if ($device->{device_model_id} eq '3') {
$telnet_cmd_mode = 2;
$cmd = "
conf
hostname $device->{device_name}
exit";
}

#allied telesys 8100
if ($device->{device_model_id} eq '4') {
$telnet_cmd_mode = 2;
$cmd = "
conf t
hostname $device->{device_name}
exit";
}

#mikrotik
if ($device->{vendor_id} eq '9') {
    $telnet_cmd_mode = 4;
    $cmd = "/system identity set name=$device->{device_name}";
    }

#cisco
if ($device->{vendor_id} eq '16') {
$cmd = "
conf t
hostname $device->{device_name}
exit";
    }

#maipu
if ($device->{vendor_id} eq '17') {
$cmd = "
conf t
hostname $device->{device_name}
exit";
    }

#Qtech
if ($device->{vendor_id} eq '38') {
$cmd = "
conf t
hostname $device->{device_name}
exit";
    }

#Extreme
if ($device->{vendor_id} eq '39') {
    $cmd = "configure snmp sysName $device->{device_name}";
    }

netdev_cmd($device,$session,$cmd,$telnet_cmd_mode);
}

#---------------------------------------------------------------------------------

sub netdev_wr_mem {
my $session = shift;
my $device = shift;

my $cmd;
my $telnet_cmd_mode = 4;

return if (!$session);

#eltex
if ($device->{vendor_id} eq '2') {
$cmd = "wr
Y";
}

#huawei
if ($device->{vendor_id} eq '3') {
$cmd = "quit
save
Y
";
}

#zyxel
if ($device->{vendor_id} eq '4') { $cmd = "wr mem"; }

#raisecom
if ($device->{vendor_id} eq '5') { $cmd = "wr"; }

#SNR
if ($device->{vendor_id} eq '6') {
$cmd="copy running-config startup-config
Y";
}

#Dlink
if ($device->{vendor_id} eq '7') { $cmd="save"; }

#allied telesys x210,x610
if ($device->{vendor_id} eq '8') {
$cmd = "wr
Y";
    }
#allied telesys 8000
if ($device->{device_model_id} eq '3') {
$telnet_cmd_mode=2;
$cmd = "copy running-config startup-config
Y
";
    }
#allied telesys 8100
if ($device->{device_model_id} eq '4') {
$cmd = "wr
Y";
    }

#cisco
if ($device->{vendor_id} eq '16') { $cmd="wr_mem"; }

#maipu
if ($device->{vendor_id} eq '17') {
$cmd = "wr
Yes
";
    }

#Qtech
if ($device->{vendor_id} eq '38') {
$cmd = "copy running-config startup-config
Y
";
    }

#Extreme
if ($device->{vendor_id} eq '39') { $cmd="save configuration primary"; }

netdev_cmd($device,$session,$cmd,$telnet_cmd_mode);
}

#---------------------------------------------------------------------------------

1;
}
