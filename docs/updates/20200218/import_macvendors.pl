#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use FindBin '$Bin';
use lib "$Bin";
use strict;
use Time::Local;
use FileHandle;
use Rstat::config;
use Rstat::main;
use Rstat::mysql;
use Rstat::net_utils;
use Data::Dumper;
use DBI;
use Time::Local;
use Date::Parse;
use JSON;

my @batch_sql=();

open(MACS, "<./updates/20200218/macaddress.io-db.json") || die "./updates/20200218/macaddress.io-db.json not found!";
while (my $line = <MACS>) {
next unless defined $line;
chomp($line);
$line=~s/false/0/;
$line=~s/true/1/;

my $hashRef = decode_json($line);
next if (!$hashRef);

my $oui=$hashRef->{oui};
my $private=$hashRef->{isPrivate};
my $name=$hashRef->{companyName};
my $addr=$hashRef->{companyAddress};
my $country=$hashRef->{countryCode};
my $block=$hashRef->{assignmentBlockSize};
my $created=$hashRef->{dateCreated};
my $updated=$hashRef->{dateUpdated};

my $mac=mac_simplify($oui);
$name=~s/\"//g;
$addr=~s/\"//g;
my $found=get_count_records($dbh,'mac_vendors','oui=".$mac."');
if ($found) {
    push(@batch_sql,'UPDATE TABLE mac_vendors set oui="'.$mac.'", isprivate='.$private.', companyName="'.$name.'", companyAddress="'.$addr.'", countryCode="'.$country.'", assignmentBlockSize="'.$block.'", dateCreated="'.$created.' 00-00-00", dateUpdated="'.$updated.' 00-00-00" where oui="'.$mac.'"');
    } else {
    push(@batch_sql,'INSERT INTO mac_vendors (oui,isprivate,companyName,companyAddress,countryCode,assignmentBlockSize,dateCreated,dateUpdated) VALUES("'.$mac.'",'.$private.',"'.$name.'","'.$addr.'","'.$country.'","'.$block.'","'.$created.' 00-00-00","'.$updated.' 00-00-00")');
    }
}
close(MACS);

$dbh->{AutoCommit} = 0;
my $sth;
foreach my $sSQL(@batch_sql) {
$sth = $dbh->prepare($sSQL);
$sth->execute;
}

$sth->finish;
$dbh->{AutoCommit} = 1;

exit;
