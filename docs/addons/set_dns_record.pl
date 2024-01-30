#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use utf8;
use English;
use base;
use FindBin '$Bin';
use lib "$Bin/";
use strict;
use DBI;
use Time::Local;
use Net::Patricia;
use NetAddr::IP;
use Data::Dumper;
use eyelib::config;
use eyelib::main;
use eyelib::mysql;
use eyelib::net_utils;
use File::Basename;
use File::Path;

my $auth_id = $ARGV[0];

update_dns_record($dbh,$auth->{auth_id});

exit;
