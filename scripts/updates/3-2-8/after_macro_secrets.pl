#!/usr/bin/perl

#
# Copyright (C) Roman Dmitriev, rnd@rajven.ru
#

# One-shot maintenance script: the values of the zabbix macros of the secret type are
# kept in the base ciphered, as the passwords of the equipment are. A base which got
# them while the values were stored in the plain text must follow the rule, or the
# plain text of a secret stays in the dump of the base for ever.
#
# It is meant to be run once after an upgrade, and upgrade.pl runs it itself as the
# patch of the version 3-2-8:
#
#   perl after_macro_secrets.pl --dry-run     # inspect first
#   perl after_macro_secrets.pl               # apply
#
# What it does:
#   * takes every binding of zabbix_macros whose name in zabbix_macros_list has the
#     type of a secret, and stores the cipher of its value instead of the value;
#   * leaves a binding alone when its value is empty, when it already carries a cipher
#     which opens into a readable text, and when the name disappeared from the list;
#   * is idempotent: a second run changes nothing, because the cipher of the first run
#     opens and re-opens into the same text only by this script, and the check of the
#     stored cipher is exactly the check which the export of a host makes;
#   * reports the counts, and the --check answer for upgrade.pl.
#
# Only zabbix_macros.macro_value is touched. The names, the types and the tags of the
# hosts stay where they were, and the hosts of zabbix are not asked for anything: the
# next export of each device brings the same secret it brought before the change.

use utf8;
use strict;
use warnings;
use open qw(:std :encoding(UTF-8));
no warnings 'utf8';
use Getopt::Long;

use lib "/opt/Eye/scripts";

use eyelib::config;
use eyelib::main;
use eyelib::database;
use eyelib::logconfig;
use eyelib::zabbix;

my $opt_dry   = 0;
my $opt_check = 0;

GetOptions('dry-run' => \$opt_dry, 'check' => \$opt_check, 'help|h' => \&usage) or exit 3;
if (!$opt_dry && !$opt_check) {
    print "::ciphering the values of the secret macros of zabbix\n";
}

my $dbh = init_db();
if (!$dbh) {
    print "ERR! Cannot connect to the base of eye\n";
    exit 2;
}

# the bindings of the names which ask for a secret value
my @rows = get_records_sql($dbh,
    'SELECT M.id, M.macro_value, L.macro_name, L.macro_type
     FROM zabbix_macros M JOIN zabbix_macros_list L ON M.macros_list_id = L.id
     WHERE L.macro_type = 1 AND M.macro_value IS NOT NULL AND TRIM(M.macro_value) <> ?
     ORDER BY M.id', ['']);

my $done = 0;
my $kept = 0;

foreach my $row (@rows) {
    my $id = int($row->{id});
    my $stored = defined $row->{macro_value} ? $row->{macro_value} : '';
    # there is nothing to cipher in an empty value, whatever the request left here
    if (trim_text($stored) eq '') {
        $kept++;
        next;
    }

    # a value which this base opens as a cipher is stored as it must be: the row was
    # ciphered by an earlier save of the interface or by a first run of this patch
    if (looks_ciphered($stored)) {
        print "  - $id: the value of " . $row->{macro_name} . " is ciphered allready\n" if $opt_dry;
        $kept++;
        next;
    }

    my $cipher = zabbix_macro_store($stored, 1);
    if (!defined $cipher || $cipher eq '') {
        print "  ! $id: the base did not take the cipher of " . $row->{macro_name} . "\n";
        next;
    }

    if ($opt_dry || $opt_check) {
        print "  + $id: " . $row->{macro_name} . " waits for the cipher (" . length($stored) . " signs)\n";
        $done++;
        next;
    }

    my $rows_done = do_sql($dbh, 'UPDATE zabbix_macros SET macro_value = ? WHERE id = ?', $cipher, $id);
    if (!defined $rows_done) {
        print "  ! $id: the base refused the update of " . $row->{macro_name} . "\n";
        next;
    }
    $done++;
}

print "::secret macros: ciphered $done, kept $kept\n";
if ($opt_check) {
    exit($done > 0 ? 1 : 0);
}
exit 0;

#---------------------------------------------------------------------------------
# 1 when the text is a cipher which this base opens into a readable text: the same rule
# which the export of a host applies to a value of a secret macro, so the patch and the
# interface can not disagree about what is stored

sub looks_ciphered {
my $value = shift;
return '' if (!defined $value || $value eq '');

my $plain = eval { decrypt_string($value) };
return '' if (!$plain);
return '' if ($plain =~ /[\0-\x08\x0b\x0c\x0e-\x1f]/);
return 1;
}

#---------------------------------------------------------------------------------

sub trim_text {
my $text = shift;
return '' if (!defined $text);
$text =~ s/^\s+|\s+$//g;
return $text;
}

#---------------------------------------------------------------------------------

sub usage {
print <<'TEXT';
after_macro_secrets.pl - the values of the secret zabbix macros into the cipher of eye

  --dry-run   show what waits for the cipher, change nothing
  --check     the same, the exit code 1 when something waits
TEXT
exit 0;
}
