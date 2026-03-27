package eyelib::logconfig;

use utf8;
use open ":encoding(utf8)";
use strict;
use English;
use FindBin '$Bin';
use lib "/opt/Eye/scripts";
use eyelib::config;
use Data::Dumper;
use Log::Log4perl;
use Fcntl qw(:mode);
use base 'Exporter';
use vars qw(@EXPORT @ISA);

our @ISA = qw(Exporter);
our @EXPORT = qw(
log_init
set_log_permissions
get_logger
log_info
log_error
log_warning
log_verbose
log_session
set_log_level
log_debug
log_die
wrlog
);

BEGIN
{

# === Инициализация ===
sub log_init {
    my ($logfile) = @_;
    $LOG_FILE = $logfile if $logfile;

    my $conf = <<"LOG4PERL";
log4perl.rootLogger = DEBUG, MainLog

log4perl.appender.MainLog = Log::Log4perl::Appender::File
log4perl.appender.MainLog.filename = $LOG_FILE
log4perl.appender.MainLog.mode = append
log4perl.appender.MainLog.umask = 0022
log4perl.appender.MainLog.utf8 = 1
log4perl.appender.MainLog.layout = Log::Log4perl::Layout::PatternLayout
log4perl.appender.MainLog.layout.ConversionPattern = %d{yyyy-MM-dd HH:mm:ss} [%p] [PID:%P] %m%n
log4perl.appender.MainLog.recreate = 1
log4perl.appender.MainLog.Threshold = DEBUG
LOG4PERL

    Log::Log4perl::init(\$conf);
    # === Устанавливаем права ПОСЛЕ создания файла ===
    _set_log_file_permissions();
    if (!$log_enable) {
        Log::Log4perl->get_logger()->level($Log::Log4perl::OFF);
    }
}

# === Установка прав и владельца ===
sub _set_log_file_permissions {
    return unless -e $LOG_FILE;  # Файл должен существовать
    # Если не root — проверяем, что мы владелец
    if ($< != 0) {
        my $stat = stat($LOG_FILE);
        my $file_uid = (stat($LOG_FILE))[4];
        if ($file_uid != $<) {
            warn "WARNING: Cannot change ownership of $LOG_FILE (not running as root)\n";
            return;
        }
    }
    # Владелец и группа
    my $uid = getpwnam($log_owner_user);
    my $gid = getgrnam($log_owner_group);
    # Fallback на root если пользователь не найден
    if (!defined $uid) {
        warn "WARNING: User '$log_owner_user' not found, using current UID\n";
        $uid = $<;
    }
    if (!defined $gid) {
        warn "WARNING: Group '$log_owner_group' not found, using current GID\n";
        $gid = $) ;
    }
    # chown
    if (!chown($uid, $gid, $LOG_FILE)) {
        warn "WARNING: chown failed for $LOG_FILE: $!\n";
    }
    # chmod
    if (!chmod(oct($log_file_mode), $LOG_FILE)) {
        warn "WARNING: chmod failed for $LOG_FILE: $!\n";
    }
    # Логгируем успех (если уже можно)
    my $log = get_logger();
    $log->debug("Log file permissions set: uid=$uid, gid=$gid, mode=$log_file_mode") if $debug;
}

# === Публичная функция для смены прав на лету (если нужно) ===
sub set_log_permissions {
    my (%opts) = @_;
    $log_owner_user = $opts{user} if defined $opts{user};
    $log_owner_group = $opts{group} if defined $opts{group};
    $log_file_mode = $opts{mode} if defined $opts{mode};
    _set_log_file_permissions();
}

# === Конвертер уровней ===
sub _lvl_to_log4perl {
    my ($lvl) = @_;
    my %map = (
        $L_ERROR   => 'ERROR',
        $L_WARNING => 'WARN',
        $L_INFO    => 'INFO',
        $L_VERBOSE => 'INFO',
        $L_DEBUG   => 'DEBUG',
    );
    return $map{$lvl} || 'INFO';
}

# === Логгер ===
sub get_logger {
    my ($category) = @_;
    return Log::Log4perl->get_logger($category || 'root');
}

# === Обёртки ===
sub log_info    { _log_wrapper('info',    @_); }
sub log_error   { _log_wrapper('error',   @_); }
sub log_warning { _log_wrapper('warn',    @_); }
sub log_verbose { _log_wrapper('info',    @_); }
sub log_session { _log_wrapper('info', "SESSION: $_[0]"); }

sub log_debug {
    return unless $log_enable;
    _log_wrapper('debug', @_) if ($debug);
}

sub log_die {
    _log_wrapper('error', @_);
    die ($_[0]);
}

sub _log_wrapper {
    return unless $log_enable;
    my ($method, @msgs) = @_;
    my $log = get_logger();
    for my $msg (@msgs) {
        next unless defined $msg && length $msg;
        for my $line (split /\n/, $msg) {
            next unless length $line;
            $log->$method($line);
        }
    }
}

# === Динамическая смена уровня логирования ===
sub set_log_level {
    my ($new_level) = @_;
    $log_level = $new_level;
    my $l4p_level = _lvl_to_log4perl($new_level);
    my %level_map = (
        'ERROR' => $Log::Log4perl::ERROR,
        'WARN'  => $Log::Log4perl::WARN,
        'INFO'  => $Log::Log4perl::INFO,
        'DEBUG' => $Log::Log4perl::DEBUG,
        'TRACE' => $Log::Log4perl::TRACE,
        'OFF'   => $Log::Log4perl::OFF,
    );
    my $numeric_level = $level_map{$l4p_level} || $Log::Log4perl::INFO;
    my $logger = Log::Log4perl->get_logger();
    $logger->level($numeric_level);
    my $appender = Log::Log4perl->appender_by_name('MainLog');
    if ($appender) {
        $appender->threshold($numeric_level);
    }
}

sub wrlog {
    return unless $log_enable;
    my ($level, $string) = @_;
    my %map = (
        $W_INFO  => 'info',
        $W_WARN  => 'warn',
        $W_ERROR => 'error',
        $W_DEBUG => 'debug',
    );
    my $method = $map{$level} || 'info';
    my $log = get_logger();
    for my $line (split /\n/, $string) {
        next unless length $line;
        $log->$method($line);
        if ($level == $W_ERROR) { print STDERR "[$method] $line\n"; } else { print "[$method] $line\n"; }
    }
}

log_init;

1;
}
