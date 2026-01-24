#!/usr/bin/perl

use strict;
use warnings;
use File::Path qw(remove_tree);

my $dir = '/opt/Eye/html/sessions';

if (-d $dir) {
    eval {
        remove_tree($dir, { safe => 0 });
    };
    if ($@) {
        die "Failed to remove '$dir': $@";
    }
    print "Directory '$dir' successfully removed.\n";
} else {
    print "Directory '$dir' does not exist.\n";
}

exit 0;
