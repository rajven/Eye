package Rstat::main;

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use strict;
use English;
use FindBin '$Bin';
use lib "$Bin";
use base 'Exporter';
use vars qw(@EXPORT @ISA);
use Rstat::config;
use Socket;
use IO::Select;
use IO::Handle;

our @ISA = qw(Exporter);
our @EXPORT = qw(
log_file
write_to_file
wrlog
log_session
log_warning
log_info
log_debug
log_error
log_die
timestamp
do_exec
do_exec_ref
do_exit
sendEmail
IsNotRun
IsMyPID
Add_PID
Remove_PID
IsNotLocked
IsMyLock
Add_Lock
Remove_Lock
DefHash
read_file
AddToConfig
RemoveFromConfig
uniq
strim
trim
is_integer
is_float
run_in_parallel
translit
);

BEGIN
{

#---------------------------------------------------------------------------------------------------------

sub log_file {
return if (!$_[0]);
return if (!$_[1]);
return if (!$_[2]);
open (LG,">>$_[0]") || die("Error open log file $_[0]!!! die...");
my ($sec,$min,$hour,$mday,$mon,$year) = (localtime())[0,1,2,3,4,5];
$mon += 1; $year += 1900;
my @msg = split("\n",$_[2]);
foreach my $row (@msg) {
	next if (!$row);
	printf LG "%04d%02d%02d-%02d%02d%02d %s [%d] %s\n",$year,$mon,$mday,$hour,$min,$sec,$_[1],$$,$row;
	}
close (LG);
if ($< ==0) {
    my $uid = getpwnam $log_owner_user;
    my $gid = getgrnam $log_owner_user;
    if (!$gid) { $gid=getgrnam "root"; }
    if (!$uid) { $uid=getpwnam "root"; }
    chown $uid, $gid, $_[0];
    chmod oct("0660"), $_[0];
    }
}

#---------------------------------------------------------------------------------------------------------

sub write_to_file {
return if (!$_[0]);
return if (!$_[1]);
my $f_name = shift;
my $cmd = shift;
my $append = shift;

if ($append) {
    open (LG,">>$f_name") || die("Error open file $f_name!!! die...");
    } else {
    open (LG,">$f_name") || die("Error open file $f_name!!! die...");
    }

if (ref($cmd) eq 'ARRAY') {
    foreach my $row (@$cmd) {
	next if (!$row);
	print LG $row."\n";
        }
    } else {
    my @msg = split("\n",$cmd);
    foreach my $row (@msg) {
	next if (!$row);
	print LG $row."\n";
	}
    }
close (LG);
}

#---------------------------------------------------------------------------------------------------------

sub wrlog {
my $level = shift;
my $string = shift;
my $PRN_LEVEL = 'INFO:';
if ($level == $W_INFO)  { log_info($string); }
if ($level == $W_ERROR) { $PRN_LEVEL = 'ERROR:'; log_error($string); }
if ($level == $W_DEBUG) { $PRN_LEVEL = 'DEBUG'; log_debug($string); }
my @msg = split("\n",$string);
foreach my $row (@msg) {
    next if (!$row);
    print $PRN_LEVEL.' '.$row."\n";
    }
}

#---------------------------------------------------------------------------------------------------------

sub log_session { log_file($LOG_COMMON,"SESSION:",$_[0]) if ($log_enable); }

#---------------------------------------------------------------------------------------------------------

sub log_info { log_file($LOG_COMMON,"INFO:",$_[0]) if ($log_enable); }

#---------------------------------------------------------------------------------------------------------

sub log_warning { log_file($LOG_COMMON,"WARN:",$_[0]) if ($log_enable); }

#---------------------------------------------------------------------------------------------------------

sub log_debug { log_file($LOG_DEBUG,"DEBUG:",$_[0]) if $debug; }

#---------------------------------------------------------------------------------------------------------

sub log_error { log_file($LOG_ERR,"ERROR:",$_[0]) if ($log_enable); }

#---------------------------------------------------------------------------------------------------------

sub log_die {
wrlog($W_ERROR,$_[0]);
my $worktime = time()-$BASETIME;
log_info("Script work $worktime sec.");
sendEmail("$HOSTNAME - $MY_NAME die! ","Process: $MY_NAME aborted with error:\n$_[0]");
die ($_[0]);
}

#---------------------------------------------------------------------------------------------------------

sub timestamp {
my $worktime = time()-$BASETIME;
log_info("TimeStamp: $worktime sec.");
}

#---------------------------------------------------------------------------------------------------------.

sub do_exec_ref {
my $ret = `$_[0]`;
my $res = $?;
my %result;
chomp($ret);
$result{output}=$ret;
$result{status}=$res;
log_debug("Run: $_[0] Output:\n$ret\nResult code: $res");
if ($res eq "0") { log_info("Run: $_[0] - $ret"); } else { log_error("Run: $_[0] - $ret"); }
return %result;
}

#--------------------------------------------------------------------------------------------------------- 

sub do_exec {
my $ret = `$_[0]`;
my $res = $?;
log_debug("Run: $_[0] Output:\n$ret\nResult code: $res");
if ($res eq "0") {
        log_info("Run: $_[0] - $ret");
        } else {
        $ret = "Error";
        log_error("Run: $_[0] - $ret");
        }
return $ret;
}

#---------------------------------------------------------------------------------------------------------

sub do_exit {
my $worktime = time()-$BASETIME;
my $code;
if ($_[0]) { $code = $_[0]; } else { $code = 0; }
log_info("Script work $worktime sec. Exit code: $code");
exit $code;
}

#---------------------------------------------------------------------------------------------------------

sub sendEmail {
my ($subject, $message, $crf) = @_;
return if (!$send_email);
my $sendmail = '/sbin/sendmail';
open(MAIL, "|$sendmail -oi -t");
print MAIL "From: $sender_email\n";
print MAIL "To: $admin_email\n";
print MAIL "Subject: $subject\nMIME-Version: 1.0\nContent-Language: ru\nContent-Type: text/html; charset=utf-8\nContent-Transfer-Encoding: 8bit\n\n";
print MAIL '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">'."\n";
print MAIL '<html xmlns="http://www.w3.org/1999/xhtml">'."\n";
print MAIL "<head><title>$subject </title></head><body>\n";
my @msg = split("\n",$message);
foreach my $row (@msg) {
if ($crf) { print MAIL "$row<br>"; } else { print MAIL "$row\n"; };
}
print MAIL "</body></html>\n";
close(MAIL);
log_info("Send email from $sender_email to $admin_email with subject: $subject");
log_debug("Body:\n$message");
}

#---------------------------------------------------------------------------------------------------------

### Check few run script
sub IsNotRun {
my $pname = shift;
my $lockfile = $pname.".pid";
# if pid file not exists - OK
log_debug("Check what pid file $lockfile exists.");
if (! -e $lockfile) { log_debug("pid file not found. Continue."); return 1; }
open (FF,"<$lockfile") or log_die("can't open file $lockfile: $!");
my $lockid = <FF>;
close(FF);
chomp($lockid);
# If the process ID belongs to the current program - OK
if ($lockid eq $$) { log_debug("pid file found, but owner is this process. Continue. "); return 1; }
# if owner of this process ID not exists - OK
my $process_count = `ps -p $lockid | grep \'$lockid\' | wc -l`;
chomp($process_count);
log_debug("Process count with id $lockid is $process_count");
if ($process_count==0) { log_debug("pid file found, but owner process not found. Remove lock file and continue. "); unlink $lockfile; return 1; }
log_debug("Another proceess with name $MY_NAME pid: $lockid already worked. ");
return 0;
}

#---------------------------------------------------------------------------------------------------------

sub IsMyPID {
my $pname = shift;
my $lockfile = $pname.".pid";
log_debug("Check what pid file $lockfile exists.");
if (! -e $lockfile) { log_debug("pid file not found. Continue."); return 1; }
open (FF,"<$lockfile") or log_die "can't open file $lockfile: $!";
my $lockid = <FF>;
close(FF);
chomp($lockid);
if ($lockid eq $$) { log_debug("pid file is my. continue."); return 1; }
log_debug("Another proceess with name $MY_NAME pid: $lockid already worked. ");
return 0;
}

#---------------------------------------------------------------------------------------------------------

sub Add_PID {
my $pname = shift;
my $lockfile = $pname.".pid";
log_debug("Try create lock file $lockfile");
open (FF,">$lockfile") or log_die "can't open file $lockfile: $!";
flock(FF,2) or log_die "can't flock $lockfile: $!";
print FF $$;
close(FF);
log_debug("Ok.");
return 1;
}

#---------------------------------------------------------------------------------------------------------

sub Remove_PID {
my $pname = shift;
my $lockfile = $pname.".pid";
log_debug("Check what pid file $lockfile exists.");
if (! -e $lockfile) { log_debug("pid file not exists. Continue."); return 1; }
unlink $lockfile or return 0;
log_debug("pid file $lockfile removed.");
return 1;
}

#---------------------------------------------------------------------------------------------------------

sub IsNotLocked {
my $lockfile = $_[0] . ".lock";
log_debug("Check what lock file $lockfile exists.");
if (! -e $lockfile) { log_debug("lock file not found. Continue."); return 1; }
open (FF,"<$lockfile") or log_die "can't open file $lockfile: $!";
my $lockid = <FF>;
close(FF);
chomp($lockid);
if ($lockid eq $$) { log_debug("lock file found, but it is owner is this process. Continue. "); return 1; }
my $process_count = `ps -p $lockid | grep \'$lockid\' | wc -l`;
if ($process_count lt 1) { log_debug("lock file found, but owner process not found. Remove lock file and continue. "); unlink $lockfile; return 1; }
log_debug("Another proceess with pid: $lockid already use $_[0]");
return 0;
}

#---------------------------------------------------------------------------------------------------------

sub IsMyLock {
my $lockfile = $_[0] . ".lock";
log_debug("Check what lock file $lockfile exists.");
if (! -e $lockfile) { log_debug("lock file not found. Continue."); return 0; }
open (FF,"<$lockfile") or log_die "can't open file $lockfile: $!";
my $lockid = <FF>;
close(FF);
chomp($lockid);
if ($lockid eq $$) { log_debug("lock file found, but it is owner is this process. Continue. "); return 1; }
log_debug("file $_[0] used by process with pid: $lockid");
return 0;
}

#---------------------------------------------------------------------------------------------------------

sub Add_Lock {
if (!IsNotLocked($_[0])) { return 0; }
my $lockfile = $_[0] . ".lock";
open (FF,">$lockfile") or log_die "can't open file $lockfile: $!";
flock(FF,2) or log_die "can't flock $lockfile: $!";
print FF $$;
close(FF);
log_debug("Create lock file for $_[0]");
return 1;
}

#---------------------------------------------------------------------------------------------------------

sub Remove_Lock {
if (!IsNotLocked($_[0])) { return 0; }
my $lockfile = $_[0] . ".lock";
if (! -e $lockfile) { return 1; }
unlink $lockfile or return 0;
log_debug("Lock file for $_[0] removed");
return 1;
}

#---------------------------------------------------------------------------------------------------------
sub DefHash {
my $hash=$_[0];
my $num_list = $_[1];
my %num_keys;
if ($num_list) {
    my @ret_num = split(' ',$num_list);
    %num_keys = map { $_, 1 } @ret_num;
    }
foreach my $key (keys %$hash) {
my $null_value = "";
$null_value = 0 if (defined $num_keys{$key});
$hash->{$key}=$null_value if (!defined($hash->{$key}));
}
return $hash;
}
#---------------------------------------------------------------------------------------------------------

sub read_file {
my $filename = shift;
return if (!$filename);
return if (!-e $filename);
open (FF,"<$filename") or die "unable to open file $filename!" ;
my @tmp=<FF>;
close(FF);
chomp(@tmp);
return @tmp;
}

#---------------------------------------------------------------------------------------------------------

sub AddToConfig {
my $conf_str = $_[0];
my $conf_file = $_[1];
my $res;
eval {
    $SIG{ALRM} = sub { die "time-alarm\n" };
    alarm $WAIT_TIME;
    while (!Add_Lock($conf_file)) {
    my $wait = int(rand($MAX_SLEEP));
    if ($wait lt $MIN_SLEEP) { $wait = $MIN_SLEEP; }
    sleep $wait;
    }
    my @conf_array=();
    @conf_array=read_conf($conf_file);
    my %list = map { $_, 1 } @conf_array;
    if (exists $list{$conf_str}) { Remove_Lock($conf_file); return 1; }
    open(FR,">$conf_file") or log_die "Unable to open config file $conf_file!" ;
    flock(FR,2);
    push(@conf_array,$conf_str);
    print FR "$WARN_MSG";
    foreach my $row (@conf_array) { print FR "$row\n"; };
    close(FR);
    Remove_Lock($conf_file);
    $SIG{ALRM} = 'DEFAULT';
};
if ( $@ eq "time-alarm\n" ) {
    log_die "Script aborted after timeout $WAIT_TIME sec. Unable to add $conf_str to config file $conf_file...\n";
    }
return 1;
}

#---------------------------------------------------------------------------------------------------------

sub RemoveFromConfig {
my $conf_str = $_[0];
my $conf_file = $_[1];
my $res;
eval {
    $SIG{ALRM} = sub { die "time-alarm\n" };
    alarm $WAIT_TIME;
    while (!Add_Lock($conf_file)) {
    my $wait = int(rand($MAX_SLEEP));
    if ($wait lt $MIN_SLEEP) { $wait = $MIN_SLEEP; }
    sleep $wait;
    }
    my @conf_array=();
    @conf_array=read_conf($conf_file);
    my %list = map { $_, 1 } @conf_array;
    if (!exists $list{$conf_str}) { Remove_Lock($conf_file); return 1; }
    my @tmp = grep {/!^$conf_str$/} @conf_array;
    open(FF,">$conf_file");
    flock(FF,2);
    print FF "$WARN_MSG";
    foreach my $row (@tmp) { print FF $conf_str ."\n"; };
    close(FF);
    Remove_Lock($conf_file);
    $SIG{ALRM} = 'DEFAULT';
};
if ( $@ eq "time-alarm\n" ) { log_die "Script aborted after timeout $WAIT_TIME sec. Unable to del $conf_str from config file $conf_file...\n"; }
return 1;
}

#---------------------------------------------------------------------------------------------------------

sub uniq (\@) {
my @tmp = @{(shift)};
if (scalar(@tmp) eq 0) { return @tmp; }
chomp(@tmp);
my %newlist = map { $_, 1 } @tmp;
return keys %newlist;
}

#---------------------------------------------------------------------------------------------------------

sub strim {
my $str=shift;
return if (!$str);
#$str =~ s/.*[^[:print:]]+//g;
#$str =~ s/[^[:print:]]+//g;
#$str =~ s/[^(a-z|A-Z|0-9|\:|\-|\s|\.)]//g;
#$str =~ s/[:^print:]//g;
$str =~ s/[^[:ascii:]]//g;
$str =~ s/^\s+//g;
$str =~ s/\s+$//g;
return $str;
}

#---------------------------------------------------------------------------------------------------------

sub trim {
my $str=shift;
return if (!$str);
$str =~ s/\n/ /g;
$str =~ s/^\s+//g;
$str =~ s/\s+$//g;
return $str;
}

#---------------------------------------------------------------------------------------------------------

sub is_integer {
defined $_[0] && $_[0] =~ /^[+-]?\d+$/;
}

#---------------------------------------------------------------------------------------------------------

sub is_float {
defined $_[0] && $_[0] =~ /^[+-]?\d+(\.\d+)?$/;
}
#---------------------------------------------------------------------------------------------------------

sub run_in_parallel(\@) {
my @commands = @{(shift)};
my @result = ();
return @result if (!@commands or !scalar(@commands));
my $count = scalar(@commands);
my $start = 0;

while ($start<=$count-1) {
    my @run_list=();
    my $select = IO::Select->new();
    my $stop = $start + $parallel_process_count;
    $stop=$count-1 if ($stop >=$count);

    for (my $index = $start; $index <=$stop; $index++) {
        next if (!$commands[$index]);
        my $cmd=$commands[$index];
        log_info("Starting ".$cmd);
        my ($hchild, $hparent, $childid);
        socketpair($hchild, $hparent, AF_UNIX, SOCK_STREAM, PF_UNSPEC) or die "socketpair: $!";
        $childid = fork;
        die "cannot fork" if($childid == -1);
        # redirect child Input|Output
        unless($childid) {
            # �������
            open STDIN, "<&", $hparent;
            open STDOUT, ">&", $hparent;
            open STDERR, ">&", $hparent;
            close $hparent;
            close $hchild;
            $select->remove($_) and close $_ for($select->handles);
            exec "/bin/nice -n 15 ".$cmd;
            }
        close $hparent;
        $select->add($hchild);
        }
    while (my @ready = $select->can_read) {
        next if (!@ready or !scalar(@ready));
        for my $read(@ready) {
            if($read->eof || $read->error) {
                # child exit
                $select->remove($read);
                close $read;
                next;
                }
            if(defined(my $str = <$read>)) {
                log_info("Read:".$str);
                push(@result,$str);
                }
            }
        }
    $start = $stop+1;
    }
return (@result);
}

sub translit {
my $textline=shift;
return if (!$textline);
$textline =~ s/А/A/g;		$textline =~ s/а/a/g;
$textline =~ s/Б/B/g;		$textline =~ s/б/b/g;
$textline =~ s/В/V/g;		$textline =~ s/в/v/g;
$textline =~ s/Г/G/g;		$textline =~ s/г/g/g;
$textline =~ s/Д/D/g;		$textline =~ s/д/d/g;
$textline =~ s/Е/E/g;		$textline =~ s/е/e/g;
$textline =~ s/Ё/E/g;		$textline =~ s/ё/e/g;
$textline =~ s/Ж/Zh/g;		$textline =~ s/ж/zh/g;
$textline =~ s/З/Z/g;		$textline =~ s/з/z/g;
$textline =~ s/И/I/g;		$textline =~ s/и/i/g;
$textline =~ s/Й/I/g;		$textline =~ s/й/i/g;
$textline =~ s/К/K/g;		$textline =~ s/к/k/g;
$textline =~ s/Л/L/g;		$textline =~ s/л/l/g;
$textline =~ s/М/M/g;		$textline =~ s/м/m/g;
$textline =~ s/Н/N/g;		$textline =~ s/н/n/g;
$textline =~ s/О/O/g;		$textline =~ s/о/o/g;
$textline =~ s/П/P/g;		$textline =~ s/п/p/g;
$textline =~ s/Р/R/g;		$textline =~ s/р/r/g;
$textline =~ s/ТС/T-S/g;	$textline =~ s/Тс/T-s/g;	$textline =~ s/тс/t-s/g;
$textline =~ s/С/S/g;		$textline =~ s/с/s/g;
$textline =~ s/Т/T/g;		$textline =~ s/т/t/g;
$textline =~ s/У/U/g;		$textline =~ s/у/u/g;
$textline =~ s/Ф/F/g;		$textline =~ s/ф/f/g;
$textline =~ s/Х/Kh/g;		$textline =~ s/х/kh/g;
$textline =~ s/Ц/Ts/g;		$textline =~ s/ц/ts/g;
$textline =~ s/Ч/Ch/g;		$textline =~ s/ч/ch/g;
$textline =~ s/Ш/Sh/g;		$textline =~ s/ш/sh/g;
$textline =~ s/Щ/Shch/g;	$textline =~ s/щ/shch/g;
$textline =~ s/Ь/'/g;		$textline =~ s/ь/'/g;
$textline =~ s/Ы/Y/g;		$textline =~ s/ы/y/g;
$textline =~ s/Ъ/''/g;		$textline =~ s/ъ/''/g;
$textline =~ s/Э/E/g;		$textline =~ s/э/e/g;
$textline =~ s/Ю/Yu/g;		$textline =~ s/ю/yu/g;
$textline =~ s/Я/Ya/g;		$textline =~ s/я/ya/g;
return $textline;
}

#log_file($LOG_COMMON,"INFO:","----------------------------------------------------------------------------------------");
#log_file($LOG_COMMON,"INFO:","Run script $0. Pid: $$ Pid file: $SPID.pid");
#log_file($LOG_COMMON,"INFO:","User uid: $< Effective uid: $>");
#log_file($LOG_COMMON,"INFO:","Status:");
#log_file($LOG_COMMON,"INFO:","Logging enabled: $log_enable");
#log_file($LOG_COMMON,"INFO:","Logging debug: $debug");

1;
}
