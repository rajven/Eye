package eyelib::main;

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use utf8;
use open ":encoding(utf8)";
use strict;
use English;
use FindBin '$Bin';
use lib "/opt/Eye/scripts";
use base 'Exporter';
use vars qw(@EXPORT @ISA);
use eyelib::config;
use Socket;
use IO::Select;
use IO::Handle;
use Crypt::CBC;
use MIME::Base64;

our @ISA = qw(Exporter);
our @EXPORT = qw(
eye_version
log_file
write_to_file
wrlog
log_session
log_warning
log_info
log_debug
log_error
log_verbose
log_die
in_array
timestamp
do_exec
do_exec_ref
do_exit
hash_to_kv_csv
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
uniq
strim
trim
is_integer
is_float
run_in_parallel
translit
crypt_string
decrypt_string
netdev_set_auth
);

BEGIN
{

our $eye_version = "2.4.14";

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

binmode(LG,':utf8');

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

sub log_verbose { log_file($LOG_COMMON,"VERBOSE:",$_[0]) if ($log_enable); }

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
#print "TimeStamp: $worktime sec.\n";
log_info("TimeStamp: $worktime sec.");
}

#---------------------------------------------------------------------------------------------------------

sub in_array {
my $arr = shift;
my @tmp = ();
if (ref($arr)=~'ARRAY') { @tmp = @{$arr}; } else { push(@tmp,$arr); }
my $value = shift;
my %num = map { $_, 1 } @tmp;
return $num{$value} || 0;
}


#---------------------------------------------------------------------------------------------------------.

sub hash_to_kv_csv {
    my ($hash_ref, $delimiter) = @_;
    $delimiter ||= ',';
    return '' unless $hash_ref && %$hash_ref;
    # Экранируем специальные символы
    my $escape = sub {
        my $value = shift;
        return '' unless defined $value;
        # Если значение содержит кавычки или запятые - заключаем в кавычки
        if ($value =~ /["$delimiter]/) {
            $value =~ s/"/""/g;
            return '"' . $value . '"';
        }
        return $value;
    };
    # Формируем пары ключ=>значение
    my @pairs;
    while (my ($key, $value) = each %$hash_ref) {
        push @pairs, $escape->($key) . '=>' . $escape->($value);
    }
    return join($delimiter, @pairs);
}

#---------------------------------------------------------------------------------------------------------.

sub do_exec_ref {
my $ret = `$_[0] 2>&1`;
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
    my ($subject, $message, $use_br) = @_;
    
    return unless $send_email;
    
    # Validate email addresses
    unless ($sender_email =~ /\A[^@\s]+@[^@\s]+\z/) {
        log_error("Invalid sender email address: $sender_email");
        return;
    }
    
    unless ($admin_email =~ /\A[^@\s]+@[^@\s]+\z/) {
        log_error("Invalid admin email address: $admin_email");
        return;
    }
    
    # Sanitize input
    $subject =~ s/[^\p{L}\p{N}\s\-\.\,\!\?]//g;
    $message =~ s/\r//g;  # Remove carriage returns
    
    my $sendmail = '/usr/sbin/sendmail';
    unless (-x $sendmail) {
        log_error("Sendmail not found or not executable at $sendmail");
        return;
    }
    
    # Build email headers
    my $headers = <<"END_HEADERS";
From: $sender_email
To: $admin_email
Subject: $subject
MIME-Version: 1.0
Content-Type: text/html; charset=utf-8
Content-Transfer-Encoding: 8bit
X-Mailer: Perl sendEmail

END_HEADERS

    # Build HTML email body
    my $html_message = <<"END_HTML";
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>$subject</title>
</head>
<body>
END_HTML

    # Process message lines
    my @lines = split("\n", $message);
    foreach my $line (@lines) {
        $line = htmlspecialchars($line);  # HTML escape
        $html_message .= $use_br ? "$line<br>\n" : "$line\n";
    }
    
    $html_message .= "</body></html>\n";
    
    # Send email
    unless (open(MAIL, "|$sendmail -oi -t")) {
        log_error("Failed to open sendmail: $!");
        return;
    }
    
    print MAIL $headers;
    print MAIL $html_message;
    
    unless (close(MAIL)) {
        log_error("Failed to send email: $!");
        return;
    }
    
    log_info("Sent email from $sender_email to $admin_email with subject: $subject");
    log_debug("Email body:\n$message");
}

#---------------------------------------------------------------------------------------------------------

# Helper function for HTML escaping
sub htmlspecialchars {
    my ($text) = @_;
    $text =~ s/&/&amp;/g;
    $text =~ s/</&lt;/g;
    $text =~ s/>/&gt;/g;
    $text =~ s/"/&quot;/g;
    $text =~ s/'/&#039;/g;
    return $text;
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

#---------------------------------------------------------------------------------

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
#$textline =~ s/Ь/'/g;		$textline =~ s/ь/'/g;
#$textline =~ s/Ъ/''/g;		$textline =~ s/ъ/''/g;
$textline =~ s/Ь//g;		$textline =~ s/ь//g;
$textline =~ s/Ъ//g;		$textline =~ s/ъ//g;
$textline =~ s/Ы/Y/g;		$textline =~ s/ы/y/g;
$textline =~ s/Э/E/g;		$textline =~ s/э/e/g;
$textline =~ s/Ю/Yu/g;		$textline =~ s/ю/yu/g;
$textline =~ s/Я/Ya/g;		$textline =~ s/я/ya/g;
return $textline;
}

#---------------------------------------------------------------------------------

sub netdev_set_auth {
my $device = shift;
$device->{login}=$config_ref{router_login} if (!$device->{login});
$device->{password}=$config_ref{router_password} if (!$device->{password});
$device->{password}=decrypt_string($device->{password});
$device->{enable_password}='';
#$device->{enable_password}=$device->{passowrd};
$device->{proto} = 'ssh';
$device->{proto} = 'telnet' if ($device->{protocol} eq '1');
#patch for ssh
if ($device->{proto} eq 'ssh' and exists $switch_auth{$device->{vendor_id}}{proto}) {
	#set specified ssh type
	if ($switch_auth{$device->{vendor_id}}{proto} =~/ssh/i) {
		$device->{proto} = $switch_auth{$device->{vendor_id}}{proto};
		}
	}
$device->{port} = $device->{control_port} if ($device->{control_port});
$device->{prompt} = qr/[\$#>]\s?$/;
if (exists $switch_auth{$device->{vendor_id}}) {
    $device->{prompt} = $switch_auth{$device->{vendor_id}}{prompt} if ($switch_auth{$device->{vendor_id}}{prompt});
    }
return $device;
}

#---------------------------------------------------------------------------------

sub decrypt_string {
    my $crypted_string = shift;
    return if (!$crypted_string);
    my $cipher_handle = Crypt::CBC->new(
    {
        'key'         => $config_ref{encryption_key},
        'cipher'      => 'Cipher::AES',
        'iv'          => $config_ref{encryption_iv},
        'literal_key' => 1,
        'header'      => 'none',
        keysize       => 128 / 8
    }
    );

my $result = $cipher_handle->decrypt(decode_base64($crypted_string));
return $result;
}

#---------------------------------------------------------------------------------

sub crypt_string {
    my $simple_string = shift;
    return if (!$simple_string);
    my $cipher_handle = Crypt::CBC->new(
    {
        'key'         => $config_ref{encryption_key},
        'cipher'      => 'Cipher::AES',
        'iv'          => $config_ref{encryption_iv},
        'literal_key' => 1,
        'header'      => 'none',
        keysize       => 128 / 8
    }
    );

my $result = encode_base64($cipher_handle->encrypt($simple_string));
return $result;
}

#---------------------------------------------------------------------------------

#log_file($LOG_COMMON,"INFO:","----------------------------------------------------------------------------------------");
#log_file($LOG_COMMON,"INFO:","Run script $0. Pid: $$ Pid file: $SPID.pid");
#log_file($LOG_COMMON,"INFO:","User uid: $< Effective uid: $>");
#log_file($LOG_COMMON,"INFO:","Status:");
#log_file($LOG_COMMON,"INFO:","Logging enabled: $log_enable");
#log_file($LOG_COMMON,"INFO:","Logging debug: $debug");

1;
}
