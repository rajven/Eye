package eyelib::rest_api;

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use utf8;
use strict;
use English;
use FindBin qw($Bin);
use lib "$Bin";
use base 'Exporter';
use vars qw(@EXPORT @ISA);
use HTTP::Request::Common;
use HTTP::Request;
use LWP;
use URI::Encode;
use eyelib::main;
use Data::Dumper;
use IO::Socket::SSL;
use JSON;

#libwww-perl
#libhttp-message-perl
#libhttp-request-params-perl
#liburi-encode-perl
#libjson-perl

our @ISA = qw(Exporter);
our @EXPORT = qw(
rest_get_request
rest_patch_request
rest_put_request
rest_delete_request
);

BEGIN
{

#search data

sub rest_get_request {
my $uri = shift;
my $login = shift;
my $pass = shift;
#disable check cert
my $ua = LWP::UserAgent->new(protocols_allowed => ['http', 'https'], ssl_opts=> { SSL_verify_mode => SSL_VERIFY_NONE(), verify_hostname =>0 } );
#get request
my $req = HTTP::Request->new('GET', $uri);
#basic authorize
$req->authorization_basic($login,$pass);
#send request
log_info("Send request: ".$uri);
my $resp = $ua->request($req);
my $ret = $resp->is_success;
if ($ret) {
    my $result = decode_json($resp->decoded_content);
    log_debug("Received reply: ".Dumper($result));
    return $result;
    } else {
    log_error("HTTP GET error code: ".$resp->code);
    log_error("HTTP GET error message: ".$resp->message);
    return;
    }
}

#change data

sub rest_patch_request {
my $uri = shift;
my $data = shift;
my $login = shift;
my $pass = shift;
#disable check cert
my $ua = LWP::UserAgent->new(protocols_allowed => ['http', 'https'], ssl_opts=> { SSL_verify_mode => SSL_VERIFY_NONE(), verify_hostname =>0 } );
#header
my $hr = [ 'Content-Type' => 'application/json' ];
#encode
my $encoded_data = encode_json($data);
#patch request
my $req = HTTP::Request->new('PATCH','$uri', $hr, $encoded_data);
#basic authorize
$req->authorization_basic($login,$pass);
#run patch
my $resp = $ua->request($req,$uri);
my $ret = $resp->is_success;
if ($ret) {
    my $result = decode_json($resp->decoded_content);
    log_debug("Received reply: ".Dumper($result));
    return $result;
    } else {
    log_error("HTTP PATCH error code: ".$resp->code);
    log_error("HTTP PATCH error message: ".$resp->message);
    return;
    }
}

#add new data

sub rest_put_request {
my $uri = shift;
my $data = shift;
my $login = shift;
my $pass = shift;
#disable check cert
my $ua = LWP::UserAgent->new(protocols_allowed => ['http', 'https'], ssl_opts=> { SSL_verify_mode => SSL_VERIFY_NONE(), verify_hostname =>0 } );
#header
my $hr = [ 'Content-Type' => 'application/json' ];
#encode
my $encoded_data = encode_json($data);
#patch request
my $req = HTTP::Request->new('PUT','$uri', $hr, $encoded_data);
#basic authorize
$req->authorization_basic($login,$pass);
#put
my $resp = $ua->request($req,$uri);
my $ret = $resp->is_success;
if ($ret) {
    my $result = decode_json($resp->decoded_content);
    log_debug("Received reply: ".Dumper($result));
    return $result;
    } else {
    log_error("HTTP PUT error code: ".$resp->code);
    log_error("HTTP PUT error message: ".$resp->message);
    return;
    }
}

#delete data

sub rest_delete_request {
my $uri = shift;
my $login = shift;
my $pass = shift;
#disable check cert
my $ua = LWP::UserAgent->new(protocols_allowed => ['http', 'https'], ssl_opts=> { SSL_verify_mode => SSL_VERIFY_NONE(), verify_hostname =>0 } );
#delete request
my $req = HTTP::Request->new('DELETE','$uri');
#basic authorize
$req->authorization_basic($login,$pass);
#delete
my $resp = $ua->request($req,$uri);
my $ret = $resp->is_success;
if ($ret) {
    my $result = decode_json($resp->decoded_content);
    log_debug("Received reply: ".Dumper($result));
    return $result;
    } else {
    log_error("HTTP DELETE error code: ".$resp->code);
    log_error("HTTP DELETE error message: ".$resp->message);
    return;
    }
}

1;
}
