#!/usr/bin/perl

#
# Copyright (C) Roman Dmitriev, rnd@rajven.ru
#

# One-shot maintenance script: brings the descriptions of the built-in filters
# in filter_list up to date in an existing installation.
#
# It is meant to be run once after an upgrade:
#
#   perl /opt/Eye/scripts/update_filter_descr.pl --dry-run   # inspect first
#   perl /opt/Eye/scripts/update_filter_descr.pl             # apply
#
# What it does:
#   * rewrites the description of every built-in filter into the language the
#     interface uses (HTML_LANG from /opt/Eye/html/cfg/config.php, --lang wins);
#   * normalizes the port range of the two "unprivileged ports" records. The
#     seeds shipped before this change disagreed there:
#       id 55 (tcp) - 1024-65500 in all of them
#       id 83 (udp) - 1024-65500 in mysql/ru, 1024-65000 in mysql/en and postgres
#     Both are stored as 1024-65535 now, in dstport and in the description, so
#     an old installation converges to what a fresh one gets from the seed;
#   * leaves alone a record whose protocol/destination/ports no longer match the
#     built-in definition (an operator reconfigured it) and a record with an id
#     above the built-in range (an operator created it), reporting both;
#   * is idempotent: a second run changes nothing.
#
# Only filter_list is touched. group_filters references filters by id, so the
# customers' rules are not affected; pushing the new port range to the devices
# is still done by the normal apply/sync of each customer.

use utf8;
use strict;
use warnings;
use open qw(:std :encoding(UTF-8));
no warnings 'utf8';
use Getopt::Long;

use lib "/opt/Eye/scripts";

use eyelib::database;
use eyelib::logconfig;

# Location of the generated PHP configuration; EYE_HTML_CONFIG lets the
# detection be pointed at another file without editing this script.
use constant HTML_CONFIG => '/opt/Eye/html/cfg/config.php';

# Exit codes of this script
use constant EXIT_OK      => 0;  # applied, or nothing to do
use constant EXIT_PENDING => 1;  # --check found pending changes
use constant EXIT_ERROR   => 2;  # no database, or a statement failed
use constant EXIT_USAGE   => 3;  # wrong command line

# Built-in filters accepted for rewriting.
#
#   proto, dst, dstport, srcport  - structural fields that must match the row;
#                                   dstport may be a list of accepted spellings;
#                                   srcport/ipset_id default to "must be empty"
#   filter_type                   - defaults to 0 (1 is a named ipset filter)
#   to_port                       - canonical dstport to store, when the range
#                                   itself has to be normalized
#   en, ru                        - description to store
my %FILTERS = (
    1 => { proto => 'tcp',  dst => '0/0', dstport => '110',
           en => 'POP3 mail retrieval (TCP 110)',
           ru => 'Получение почты POP3 (TCP 110)' },

    3 => { proto => 'tcp',  dst => '0/0', dstport => '80',
           en => 'HTTP web traffic (TCP 80)',
           ru => 'Веб-трафик HTTP (TCP 80)' },

    4 => { proto => 'tcp',  dst => '0/0', dstport => '443',
           en => 'HTTPS encrypted web traffic (TCP 443)',
           ru => 'Зашифрованный веб-трафик HTTPS (TCP 443)' },

    5 => { proto => 'tcp',  dst => '0/0', dstport => '5190',
           en => 'ICQ instant messaging (TCP 5190)',
           ru => 'Мгновенные сообщения ICQ (TCP 5190)' },

    6 => { proto => 'tcp',  dst => '0/0', dstport => '5222',
           en => 'Jabber/XMPP instant messaging (TCP 5222)',
           ru => 'Мгновенные сообщения Jabber/XMPP (TCP 5222)' },

    # "any" is the only built-in record that carries srcport 0 instead of NULL
    9 => { proto => 'all',  dst => '0/0', dstport => '0', srcport => '0',
           en => 'Allow all traffic (any protocol)',
           ru => 'любой трафик' },

    10 => { proto => 'icmp', dst => '0/0', dstport => '0',
            en => 'ICMP echo and control messages',
            ru => 'ICMP: эхо-запросы и служебные сообщения' },

    11 => { proto => 'tcp',  dst => '0/0', dstport => '20-21',
            en => 'FTP control and data channels (TCP 20-21)',
            ru => 'FTP: каналы управления и данных (TCP 20-21)' },

    15 => { proto => 'tcp',  dst => '0/0', dstport => '23',
            en => 'Telnet remote terminal (TCP 23)',
            ru => 'Telnet: удалённый терминал (TCP 23)' },

    16 => { proto => 'tcp',  dst => '0/0', dstport => '22',
            en => 'SSH secure remote shell (TCP 22)',
            ru => 'SSH: защищённая удалённая оболочка (TCP 22)' },

    28 => { proto => 'tcp',  dst => '0/0', dstport => '25',
            en => 'SMTP mail transfer (TCP 25)',
            ru => 'SMTP: передача почты (TCP 25)' },

    32 => { proto => 'tcp',  dst => '0/0', dstport => '3389',
            en => 'RDP remote desktop (TCP 3389)',
            ru => 'RDP: удалённый рабочий стол (TCP 3389)' },

    40 => { proto => 'udp',  dst => '0/0', dstport => '123',
            en => 'NTP time synchronization (UDP 123)',
            ru => 'NTP: синхронизация времени (UDP 123)' },

    44 => { proto => 'tcp',  dst => '0/0', dstport => '5800-5900',
            en => 'VNC remote desktop (TCP 5800-5900)',
            ru => 'VNC: удалённый рабочий стол (TCP 5800-5900)' },

    # unprivileged TCP ports: accept the seeded spelling, store the full range
    55 => { proto => 'tcp', dst => '0/0',
            dstport => ['1024-65500', '1024-65000'], to_port => '1024-65535',
            en => 'Unprivileged TCP ports (1024-65535)',
            ru => 'Непривилегированные TCP-порты (1024-65535)' },

    76 => { proto => 'udp',  dst => '0/0', dstport => '500',
            en => 'IPsec IKE key exchange (UDP 500)',
            ru => 'IPsec: обмен ключами IKE (UDP 500)' },

    77 => { proto => 'udp',  dst => '0/0', dstport => '4500',
            en => 'IPsec NAT-T / ISAKMP (UDP 4500)',
            ru => 'IPsec NAT-T / ISAKMP (UDP 4500)' },

    79 => { proto => 'tcp',  dst => '0/0', dstport => '995',
            en => 'POP3S secure mail retrieval (TCP 995)',
            ru => 'POP3S: защищённое получение почты (TCP 995)' },

    80 => { proto => 'tcp',  dst => '0/0', dstport => '465,587',
            en => 'SMTPS secure mail transfer (TCP 465, 587)',
            ru => 'SMTPS: защищённая передача почты (TCP 465, 587)' },

    81 => { proto => 'tcp',  dst => '0/0', dstport => '143',
            en => 'IMAP mail access (TCP 143)',
            ru => 'IMAP: доступ к почте (TCP 143)' },

    82 => { proto => 'tcp',  dst => '0/0', dstport => '993',
            en => 'IMAPS secure mail access (TCP 993)',
            ru => 'IMAPS: защищённый доступ к почте (TCP 993)' },

    # unprivileged UDP ports: both legacy spellings are accepted here
    83 => { proto => 'udp', dst => '0/0',
            dstport => ['1024-65000', '1024-65500', '1024-65000'], to_port => '1024-65535',
            en => 'Unprivileged UDP ports (1024-65535)',
            ru => 'Непривилегированные UDP-порты (1024-65535)' },

    84 => { proto => 'tcp',  dst => '0/0', dstport => '1723',
            en => 'PPTP VPN tunnel (TCP 1723)',
            ru => 'PPTP: VPN-туннель (TCP 1723)' },

    85 => { proto => 'udp',  dst => '0/0', dstport => '1194',
            en => 'OpenVPN over UDP (UDP 1194)',
            ru => 'OpenVPN поверх UDP (UDP 1194)' },

    90 => { proto => 'udp',  dst => '0/0', dstport => '53',
            en => 'DNS queries over UDP (UDP 53)',
            ru => 'DNS-запросы поверх UDP (UDP 53)' },

    91 => { proto => 'tcp',  dst => '0/0', dstport => '53',
            en => 'DNS queries over TCP (TCP 53)',
            ru => 'DNS-запросы поверх TCP (TCP 53)' },

    94 => { proto => 'tcp',  dst => '0/0', dstport => '3128',
            en => 'Squid HTTP proxy (TCP 3128)',
            ru => 'HTTP-прокси Squid (TCP 3128)' },

    101 => { proto => 'udp', dst => '0/0', dstport => '161',
             en => 'SNMP network management (UDP 161)',
             ru => 'SNMP: управление сетью (UDP 161)' },

    105 => { proto => 'udp', dst => '0/0', dstport => '80',
             en => 'HTTP over UDP (UDP 80)',
             ru => 'HTTP поверх UDP (UDP 80)' },

    106 => { proto => 'udp', dst => '0/0', dstport => '443',
             en => 'HTTPS over UDP (UDP 443)',
             ru => 'HTTPS поверх UDP (UDP 443)' },

    107 => { proto => 'udp', dst => '0/0', dstport => '1701,4500,500',
             en => 'L2TP/IPsec VPN (UDP 1701, 4500, 500)',
             ru => 'L2TP/IPsec VPN (UDP 1701, 4500, 500)' },

    108 => { proto => 'gre', dst => '0/0', dstport => undef,
             en => 'GRE generic routing encapsulation',
             ru => 'GRE: инкапсуляция общего маршрутизируемого трафика' },
);

my $opt_lang   = '';
my $opt_dry    = 0;
my $opt_check  = 0;
my $opt_help   = 0;

GetOptions(
    'lang=s'  => \$opt_lang,
    'dry-run' => \$opt_dry,
    'check'   => \$opt_check,
    'help|h'  => \$opt_help,
    'verbose' => sub { log_verbose() },
) or exit_report("bad command line\n", EXIT_USAGE);

if ($opt_help) {
    print usage_text();
    exit EXIT_OK;
}

sub usage_text {
    return <<"USAGE";
Usage: $0 [options]

  --lang=ru|en    description language; default: HTML_LANG from
                  /opt/Eye/html/cfg/config.php, russian if it is missing
  --dry-run       report what would change, write nothing
  --check         report only, exit 1 when something is out of date
  --verbose       per-line details in the log as well
  --help          this text

Exit codes: 0 ok, 1 changes pending (--check), 2 database error, 3 bad options.
USAGE
}

sub exit_report {
    my ($msg, $code) = @_;
    print STDERR $msg if defined $msg && length $msg;
    log_error($msg) if defined $msg && length $msg;
    exit($code // EXIT_ERROR);
}

# NULL and an empty string mean the same thing in filter_list, and the type of
# a column returned by the driver is not guaranteed.
sub field_ok {
    my ($db, $want) = @_;
    $db = '' unless defined $db;
    return $db eq '' unless defined $want;
    if (ref $want eq 'ARRAY') {
        for my $one (@$want) { return 1 if field_ok($db, $one) }
        return 0;
    }
    return "$db" eq "$want";
}

# A row matches a built-in definition only while every structural field is the
# factory one, so a filter that an operator reconfigured is never rewritten.
sub matches {
    my ($row, $tpl) = @_;
    return 0 unless field_ok($row->{proto},       $tpl->{proto});
    return 0 unless field_ok($row->{dst},         $tpl->{dst});
    return 0 unless field_ok($row->{dstport},     $tpl->{dstport});
    return 0 unless field_ok($row->{srcport},     exists $tpl->{srcport}     ? $tpl->{srcport}     : undef);
    return 0 unless field_ok($row->{ipset_id},    exists $tpl->{ipset_id}    ? $tpl->{ipset_id}    : undef);
    return 0 unless field_ok($row->{filter_type}, exists $tpl->{filter_type} ? $tpl->{filter_type} : 0);
    return 1;
}

# The interface language lives in the PHP config, there is no Perl counterpart.
# Only two language files exist (inc/languages/{russian,english}.php), and the
# installer default is russian, so anything unknown is treated as russian too.
sub detect_lang {
    my ($file) = @_;
    $file = HTML_CONFIG unless defined $file;
    unless (-f $file) {
        log_warning("$file does not exist, assuming russian");
        return 'ru';
    }
    open(my $fh, '<:encoding(UTF-8)', $file) or do {
        log_warning("Cannot read $file: $!, assuming russian");
        return 'ru';
    };
    my $lang;
    while (my $line = <$fh>) {
        next unless $line =~ /^\s*Define_And_Store\s*\(\s*["']HTML_LANG["']\s*,\s*["']([^"']+)["']/i
                 || $line =~ /^\s*define\s*\(\s*["']HTML_LANG["']\s*,\s*["']([^"']+)["']/i;
        $lang = lc($1);
        last;
    }
    close $fh;
    unless (defined $lang) {
        log_warning("HTML_LANG is not set in $file, assuming russian");
        return 'ru';
    }
    return 'ru' if $lang =~ /^(rus|russian|ru)$/;
    return 'en' if $lang =~ /^(eng|english|en)$/;
    log_warning("HTML_LANG='$lang' in $file is not supported, assuming russian");
    return 'ru';
}

my %LANG_ALIAS = (rus => 'ru', russian => 'ru', ru => 'ru',
                  eng => 'en', english => 'en', en => 'en');

if ($opt_lang) {
    my $want = $LANG_ALIAS{lc($opt_lang)};
    exit_report("Unknown --lang '$opt_lang', use ru or en\n", EXIT_USAGE) unless defined $want;
    $opt_lang = $want;
}
my $lang = $opt_lang ? $opt_lang : detect_lang($ENV{EYE_HTML_CONFIG});

my $dbh = init_db();
exit_report("No database connection\n", EXIT_ERROR) unless $dbh;

# get_records_sql returns a plain list of hash references, and it is empty both
# when the table is empty and when the query failed, so the DBI error text is
# the only way to tell the two apart.
my @records = get_records_sql($dbh,
    "SELECT id, name, description, proto, dst, ipset_id, dstport, srcport, filter_type FROM filter_list");
unless (@records) {
    my $why = DBI->errstr || 'filter_list is empty';
    exit_report("Cannot read filter_list: $why\n", EXIT_ERROR);
}

my $max_builtin = (sort { $b <=> $a } keys %FILTERS)[0];
my (@plan, @reconfigured, @foreign, %seen);

for my $row (@records) {
    my $id = defined $row->{id} ? 0 + $row->{id} : -1;
    my $tpl = exists $FILTERS{$id} ? $FILTERS{$id} : undef;

    unless ($tpl) {
        # An id above the built-in range belongs to a filter created inside the
        # web interface: report it once and move on.
        push @foreign, "$id ($row->{name})" if $id > $max_builtin;
        next;
    }
    $seen{$id} = 1;

    unless (matches($row, $tpl)) {
        push @reconfigured, "$id ($row->{name})";
        log_debug("filter $id keeps its own settings, not rewritten");
        next;
    }

    my $have_desc = defined $row->{description} ? $row->{description} : '';
    my $want_desc = $tpl->{$lang};
    my $have_port = defined $row->{dstport} ? $row->{dstport} : '';
    my $to_port   = $tpl->{to_port};

    my $port_change = (defined $to_port && "$to_port" ne "$have_port") ? 1 : 0;
    my $desc_change = ("$have_desc" ne "$want_desc") ? 1 : 0;
    next unless $desc_change || $port_change;

    # An empty dstport must stay NULL, not become an empty string (GRE has none).
    my $write_port = $port_change ? $to_port
                                 : (length $have_port ? $have_port : undef);

    push @plan, {
        id        => $id,
        name      => (defined $row->{name} ? $row->{name} : ''),
        old_desc  => $have_desc,
        new_desc  => $want_desc,
        old_port  => (length $have_port ? $have_port : 'NULL'),
        new_port  => (defined $write_port ? $write_port : 'NULL'),
        write_port=> $write_port,
        port_moved=> $port_change,
    };
}

my @missing = grep { !$seen{$_} } sort { $a <=> $b } keys %FILTERS;

printf("language: %s, filters in database: %d, built-in matched: %d, to update: %d\n",
       $lang, scalar @records, scalar(keys %seen) - scalar(@reconfigured), scalar @plan);

for my $item (@plan) {
    printf("  id=%-4s %s\n", $item->{id}, $item->{name});
    printf("        dstport: %s -> %s\n", $item->{old_port}, $item->{new_port}) if $item->{port_moved};
    printf("        old: %s\n", $item->{old_desc}) if length $item->{old_desc};
    printf("        new: %s\n", $item->{new_desc});
}

# Everything that was deliberately not changed has to be visible in the report,
# otherwise a silent no-op looks like a successful run.
print "not rewritten, reconfigured by an operator: ", join(', ', @reconfigured), "\n" if @reconfigured;
print "not rewritten, created by an operator: ", join(', ', @foreign), "\n" if @foreign;
print "built-in ids absent from the database: ", join(', ', @missing), "\n" if @missing;

if ($opt_check) {
    print scalar(@plan) ? "check: " . scalar(@plan) . " filter(s) are out of date\n" : "check: everything is up to date\n";
    exit(scalar(@plan) ? EXIT_PENDING : EXIT_OK);
}

if (!@plan) {
    print "nothing to update\n";
    exit EXIT_OK;
}

if ($opt_dry) {
    print "dry run, nothing written\n";
    exit EXIT_OK;
}

my $done = 0;
my @failed;
for my $item (@plan) {
    # Description and dstport are written in one statement: for the two
    # unprivileged-port records the stored range and the wording about it must
    # not diverge. write_port is undef when the column has to stay NULL (GRE).
    my $rows = do_sql($dbh,
        'UPDATE filter_list SET description = ?, dstport = ? WHERE id = ?',
        $item->{new_desc}, $item->{write_port}, $item->{id});

    # do_sql returns undef when the statement failed, and the number of changed
    # rows otherwise (it reports 1 even when nothing changed)
    if (!defined $rows) {
        push @failed, $item->{id};
        log_error("cannot update filter $item->{id}");
        next;
    }
    $done++;
    log_info("filter $item->{id} updated");
}

printf("updated: %d of %d\n", $done, scalar @plan);
if (@failed) {
    print "failed ids: ", join(', ', @failed), "\n";
    exit EXIT_ERROR;
}

exit EXIT_OK;
