package eyelib::zabbix;

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
use eyelib::config;
use eyelib::main;
use eyelib::database;
use eyelib::logconfig;
use HTTP::Tiny;
use JSON::PP;
use Data::Dumper;

#only core modules are used: HTTP::Tiny and JSON::PP are shipped with perl itself

our @ISA = qw(Exporter);
@EXPORT = qw(
zabbix_monitoring_enabled
zabbix_api_init
zabbix_api_version
zabbix_api_call
zabbix_get_templates
zabbix_get_template_by_id
zabbix_get_template_by_name
zabbix_get_groups
zabbix_get_group
zabbix_get_hosts_by_names
zabbix_get_host
zabbix_get_host_by_tag
zabbix_get_tagged_hosts
zabbix_host_key
zabbix_host_tag_name
zabbix_create_host
zabbix_update_host
zabbix_enable_host
zabbix_disable_host
zabbix_host_merge_templates
zabbix_host_merge_macros
zabbix_host_has_tag
zabbix_host_tag_value
zabbix_host_interface
$zabbix_error
);

BEGIN
{

our $zabbix_error = '';
our $zabbix_url = '';
our $zabbix_key = '';
our $zabbix_request_id = 0;
our $zabbix_version = '';
our $zabbix_new_groups = 0;

#tag of the host, that contains the eye record identifier
use constant ZABBIX_TAG_EYE => 'eye';
#tag of the host, that contains the name of the switch the record is connected to
use constant ZABBIX_TAG_PARENT => 'parent';

#---------------------------------------------------------------------------------
# zabbix is selected as the monitoring system and the api is configured

sub zabbix_monitoring_enabled {
my $db = shift;
return 0 if (!$db);
my $system = lc(get_option($db, 74) // '');
return 0 if ($system ne 'zabbix');
my $url = get_option($db, 75) // '';
my $key = get_option($db, 76) // '';
return 0 if ($url eq '' or $key eq '');
return 1;
}

#---------------------------------------------------------------------------------
# Remember the api endpoint and the access key

sub zabbix_api_init {
my $url = shift // '';
my $key = shift // '';
$url =~ s/\s+$//;
$key =~ s/\s+$//;
#if the site address was entered instead of the api file, fix it
if ($url ne '' && $url !~ m{/api_jsonrpc\.php$}) {
    $url =~ s{/+$}{};
    $url = $url . '/api_jsonrpc.php';
    }
$zabbix_url = $url;
$zabbix_key = $key;
$zabbix_version = '';
$zabbix_new_groups = 0;
$zabbix_error = '';
log_verbose("Zabbix api endpoint: $zabbix_url") if ($zabbix_url);
return $zabbix_url;
}

#---------------------------------------------------------------------------------
# Api version. zabbix 7.0 renamed the host groups parameter of host.get

sub zabbix_api_version {
return $zabbix_version if ($zabbix_version);
my $version = zabbix_api_call('apiinfo.version', {});
return '' if (!$version);
zabbix_store_version($version);
return $version;
}

#---------------------------------------------------------------------------------

sub zabbix_store_version {
my $version = shift;
$zabbix_version = $version;
my ($major) = split(/\./, $version);
$zabbix_new_groups = ($major && $major >= 7) ? 1 : 0;
log_info("Zabbix api version: $version");
}

#---------------------------------------------------------------------------------
# Single json rpc call. Returns the decoded result or undef on error

sub zabbix_api_call {
my $method = shift;
my $params = shift;

$zabbix_error = '';
if (!$zabbix_url or !$zabbix_key) {
    log_error("Zabbix api is not configured");
    return;
    }

$params = {} if (!defined $params);
$zabbix_request_id++;

my $payload = {
    jsonrpc => '2.0',
    method => $method,
    params => $params,
    id => $zabbix_request_id,
    };

# apiinfo.version strictly forbids the Authorization header in Zabbix 6.4+
my $auth_mode = ($method eq 'apiinfo.version') ? 'none' : 'bearer';
my $result = zabbix_rpc_request($payload, $auth_mode);

if (defined $result && ref $result eq 'HASH' && exists $result->{error}) {
    my $message = ($result->{error}->{message} // '') . ' ' . ($result->{error}->{data} // '');
    if ($message =~ /not authorised|session terminated|invalid session/i) {
        log_verbose("Zabbix rejected the bearer token, retrying with the auth member");
        my $auth_result = zabbix_rpc_request({ %$payload, auth => $zabbix_key }, 'auth');
        $result = $auth_result if (defined $auth_result);
        }
    }

if (!defined $result) {
    return;
    }

if (ref $result eq 'HASH' && exists $result->{error}) {
    $zabbix_error = ($result->{error}->{message} // '') . ' ' . ($result->{error}->{data} // '');
    $zabbix_error =~ s/\s+/ /g;
    log_error("Zabbix api call [$method] failed:" . $zabbix_error);
    return;
    }

if (ref $result eq 'HASH' && ref $result->{result} eq 'HASH'
    && $result->{result}->{api} && !$zabbix_version) {
    zabbix_store_version($result->{result}->{api});
    }

return ref $result eq 'HASH' ? $result->{result} : $result;
}

#---------------------------------------------------------------------------------
# json rpc transport

sub zabbix_rpc_request {
my $payload = shift;
my $auth_mode = shift // 'bearer';

my $headers = { 'Content-Type' => 'application/json-rpc' };
$headers->{'Authorization'} = 'Bearer ' . $zabbix_key if ($auth_mode eq 'bearer');

my $body = eval { JSON::PP->new->utf8(1)->canonical->encode($payload) };
if ($@ || !defined $body) {
    log_error("Zabbix cannot encode the api request");
    return;
    }

log_verbose("Zabbix api request: " . $body) if ($config_ref{debug});

my $ua = HTTP::Tiny->new(
    timeout => 60,
    #monitoring servers usally use self signed certificates
    verify_SSL => 0,
    agent => "Eye/1.0 zabbix-sync",
    );

my $response = $ua->request('POST', $zabbix_url, {
    headers => $headers,
    content => $body,
    });

if (!$response->{success}) {
    $zabbix_error = 'http ' . ($response->{status} // 'unknown') . ' ' . ($response->{reason} // '');
    log_error("Zabbix api request to $zabbix_url failed: $zabbix_error");
    return;
    }

my $result = eval { JSON::PP->new->utf8(1)->decode($response->{content}) };
if ($@ || !defined $result) {
    $zabbix_error = 'cannot decode the zabbix answer';
    log_error("Zabbix api returned not a json answer: " . substr($response->{content} // '', 0, 200));
    return;
    }

return $result;
}

#---------------------------------------------------------------------------------
# Name of the host groups parameter of host.get

sub zabbix_select_groups {
zabbix_api_version() if (!$zabbix_version);
return $zabbix_new_groups ? 'selectHostGroups' : 'selectGroups';
}

#---------------------------------------------------------------------------------
# Templates list: identifiers and names

sub zabbix_get_templates {
my $templates = zabbix_api_call('template.get', {
    output => ['templateid', 'host', 'name'],
    sortfield => 'host',
    });
return [] if (!defined $templates);
my @list;
foreach my $tpl (@$templates) {
    push(@list, zabbix_template_info($tpl));
    }
return \@list;
}

#---------------------------------------------------------------------------------

sub zabbix_get_template_by_id {
my $template_id = shift;
return if (!$template_id);
my $templates = zabbix_api_call('template.get', {
    output => ['templateid', 'host', 'name'],
    templateids => [$template_id],
    });
return if (!defined $templates or !scalar(@$templates));
return zabbix_template_info($templates->[0]);
}

#---------------------------------------------------------------------------------
# Search by the technical name of the template

sub zabbix_get_template_by_name {
my $name = shift;
return if (!defined $name or $name eq '');
my $templates = zabbix_api_call('template.get', {
    output => ['templateid', 'host', 'name'],
    filter => { host => $name },
    });
return if (!defined $templates or !scalar(@$templates));
return zabbix_template_info($templates->[0]);
}

#---------------------------------------------------------------------------------

sub zabbix_template_info {
my $tpl = shift;
return {
    id => $tpl->{templateid},
    #technical name of the template
    host => $tpl->{host} // '',
    #visible name of the template
    name => defined $tpl->{name} ? $tpl->{name} : ($tpl->{host} // ''),
    };
}

#---------------------------------------------------------------------------------
# Host groups, they are used to validate the group bound to the organizational unit

sub zabbix_get_groups {
my $groups = zabbix_api_call('hostgroup.get', {
    output => ['groupid', 'name'],
    sortfield => 'name',
    });
return [] if (!defined $groups);
return $groups;
}

#---------------------------------------------------------------------------------

sub zabbix_get_group {
my $group_id = shift;
return if (!$group_id);
my $groups = zabbix_api_call('hostgroup.get', {
    output => ['groupid', 'name'],
    groupids => [$group_id],
    });
return if (!defined $groups or !scalar(@$groups));
return $groups->[0];
}

#---------------------------------------------------------------------------------
# Hosts search by their technical names

sub zabbix_get_hosts_by_names {
my @names = @_;
return {} if (!scalar(@names));

my $hosts = zabbix_api_call('host.get', {
    output => ['hostid', 'host', 'name', 'status', 'display', 'description'],
    filter => { host => \@names },
    selectParentTemplates => ['templateid', 'host', 'name'],
    selectMacros => ['macro', 'value', 'type'],
    selectTags => ['tag', 'value'],
    zabbix_select_groups() => ['groupid', 'name'],
    });
return {} if (!defined $hosts);

my %found;
foreach my $host (@$hosts) {
    $found{ zabbix_host_key($host->{host}) } = zabbix_host_info($host);
    }
return \%found;
}

#---------------------------------------------------------------------------------
# One host by its technical name

sub zabbix_get_host {
my $name = shift;
return if (!defined $name or $name eq '');
my $found = zabbix_get_hosts_by_names($name);
return $found->{ zabbix_host_key($name) };
}

#---------------------------------------------------------------------------------
# Host search by the eye tag. It finds the host even after it was renamed in eye

sub zabbix_get_host_by_tag {
my $value = shift;
return if (!defined $value or $value eq '');

my $hosts = zabbix_api_call('host.get', {
    output => ['hostid', 'host', 'name', 'status', 'display', 'description'],
    tags => [{ tag => ZABBIX_TAG_EYE, value => $value, operator => 1 }],
    selectParentTemplates => ['templateid', 'host', 'name'],
    selectMacros => ['macro', 'value', 'type'],
    selectTags => ['tag', 'value'],
    zabbix_select_groups() => ['groupid', 'name'],
    });
return if (!defined $hosts or !scalar(@$hosts));
return zabbix_host_info($hosts->[0]);
}

#---------------------------------------------------------------------------------
# All hosts that have the given tag name. One call replaces a search per record,
# the eye tag value becomes the key of the result

sub zabbix_get_tagged_hosts {
my $tag_name = shift;
return {} if (!$tag_name);

my $names = zabbix_select_groups();
my $hosts = zabbix_api_call('host.get', {
    output => ['hostid', 'host', 'name', 'status', 'display', 'description'],
    #operator 4 - the host has a tag with this name, the value is not checked
    tags => [{ tag => $tag_name, value => '', operator => 4 }],
    selectParentTemplates => ['templateid', 'host', 'name'],
    selectMacros => ['macro', 'value', 'type'],
    selectTags => ['tag', 'value'],
    $names => ['groupid', 'name'],
    });
return {} if (!defined $hosts);

my %found;
foreach my $host (@$hosts) {
    my $info = zabbix_host_info($host);
    my $value = zabbix_host_tag_value($info, $tag_name);
    next if ($value eq '');
    $found{$value} = $info;
    }
return \%found;
}

#---------------------------------------------------------------------------------
# Normalized host structure

sub zabbix_host_info {
my $host = shift;
my @templates;
my @macros;
my @tags;
my @groups;

foreach my $tpl (@{ $host->{parentTemplates} || [] }) {
    push(@templates, {
        templateid => $tpl->{templateid},
        host => $tpl->{host} // '',
        name => defined $tpl->{name} ? $tpl->{name} : ($tpl->{host} // ''),
        });
    }

foreach my $macro (@{ $host->{macros} || [] }) {
    push(@macros, {
        macro => $macro->{macro},
        value => $macro->{value} // '',
        type => $macro->{type} // 0,
        });
    }

foreach my $tag (@{ $host->{tags} || [] }) {
    push(@tags, { tag => $tag->{tag}, value => $tag->{value} // '' });
    }

foreach my $group (@{ $host->{hostgroups} || $host->{groups} || [] }) {
    push(@groups, { groupid => $group->{groupid}, name => $group->{name} // '' });
    }

return {
    hostid => $host->{hostid},
    host => $host->{host} // '',
    name => $host->{name} // '',
    display => $host->{display} // '',
    description => $host->{description} // '',
    status => defined $host->{status} ? $host->{status} : 0,
    templates => \@templates,
    macros => \@macros,
    tags => \@tags,
    groups => \@groups,
    };
}

#---------------------------------------------------------------------------------
# zabbix host names are compared case insensitively

sub zabbix_host_key {
my $name = shift // '';
$name =~ s/\s+$//;
return lc($name);
}

#---------------------------------------------------------------------------------
# Value of the eye tag: <type>_<id> of the eye record

sub zabbix_host_tag_name {
my $type = shift;
my $id = shift;
return $type . '_' . $id;
}

#---------------------------------------------------------------------------------
# Tag value of the host

sub zabbix_host_tag_value {
my $zbx_host = shift // {};
my $tag_name = shift;
foreach my $tag (@{ $zbx_host->{tags} || [] }) {
    next if (!$tag->{tag});
    return $tag->{value} // '' if (lc($tag->{tag}) eq lc($tag_name));
    }
return '';
}

#---------------------------------------------------------------------------------

sub zabbix_host_has_tag {
my $zbx_host = shift // {};
my $tag_name = shift;
foreach my $tag (@{ $zbx_host->{tags} || [] }) {
    next if (!$tag->{tag});
    return 1 if (lc($tag->{tag}) eq lc($tag_name));
    }
return 0;
}

#---------------------------------------------------------------------------------
# Create the host. groups is an array ref of the zabbix group ids, it may be empty

sub zabbix_create_host {
my $host = shift // {};
my $name = $host->{host} // $host->{name};
return if (!$name);

my %params = (
    host => $name,
    name => $name,
    description => $host->{description} // '',
    status => 0,
    );

# a host without an interface can not be polled by zabbix
my $interface = zabbix_host_interface($host);
$params{interfaces} = [$interface] if ($interface);

# zabbix 7.0 renamed the host groups parameter of host.create
$params{groups} = [ map { { groupid => $_ } } @{ $host->{groups} } ]
    if ($host->{groups} && scalar(@{ $host->{groups} }));

$params{templates} = [ map { { templateid => $_ } } @{ $host->{templates} } ]
    if ($host->{templates} && scalar(@{ $host->{templates} }));

$params{macros} = zabbix_macro_list($host->{macros})
    if ($host->{macros} && scalar(@{ $host->{macros} }));

$params{tags} = zabbix_tag_list($host->{tags}) if ($host->{tags});

my $created = zabbix_api_call('host.create', \%params);

#if zabbix does not accept the host groups parameter under this name, the other name is tried
if (!defined $created && $host->{groups} && scalar(@{ $host->{groups} })
    && $zabbix_error =~ /groups|unexpected/i) {
    log_verbose("Zabbix: retrying host.create with the host_groups parameter");
    delete $params{groups};
    $params{host_groups} = [ map { { groupid => $_ } } @{ $host->{groups} } ];
    $created = zabbix_api_call('host.create', \%params);
    }

return if (!defined $created);
my $hostid = (ref $created eq 'HASH' && $created->{hostids}) ? $created->{hostids}->[0] : undef;
log_info("Zabbix: host $name created (hostid " . ($hostid // 'unknown') . ")");
return $hostid;
}

#---------------------------------------------------------------------------------
# Update the existing host. only the passed keys are touched,
# zabbix replaces the full lists of templates, macros and tags.
# the logname key is used for the log when the name of the host is not changed

sub zabbix_update_host {
my $hostid = shift;
my $host = shift // {};
return if (!$hostid);

my %params = ( hostid => $hostid );

$params{host} = $host->{host} if ($host->{host});
$params{description} = $host->{description} if (defined $host->{description});
$params{templates} = [ map { { templateid => $_ } } @{ $host->{templates} } ] if ($host->{templates});
$params{macros} = zabbix_macro_list($host->{macros}) if ($host->{macros});
$params{tags} = zabbix_tag_list($host->{tags}) if ($host->{tags});
$params{status} = $host->{status} if (defined $host->{status});

my $title = $host->{host} // $host->{logname} // $hostid;

my $updated = zabbix_api_call('host.update', \%params);
if (!defined $updated) {
    log_error("Zabbix: cannot update host $title (hostid $hostid)");
    return;
    }
log_info("Zabbix: host $title updated (hostid $hostid)");
return 1;
}

#---------------------------------------------------------------------------------
# Hosts are never removed from zabbix, monitoring is switched off instead

sub zabbix_disable_host {
my $hostid = shift;
my $name = shift // '';
return if (!$hostid);
my $result = zabbix_api_call('host.update', { hostid => $hostid, status => 1 });
return if (!defined $result);
log_info("Zabbix: host $name (hostid $hostid) monitoring disabled");
return 1;
}

#---------------------------------------------------------------------------------

sub zabbix_enable_host {
my $hostid = shift;
my $name = shift // '';
return if (!$hostid);
my $result = zabbix_api_call('host.update', { hostid => $hostid, status => 0 });
return if (!defined $result);
log_info("Zabbix: host $name (hostid $hostid) monitoring enabled");
return 1;
}

#---------------------------------------------------------------------------------
# Only missing templates are added, templates linked to the host by hand are kept

sub zabbix_host_merge_templates {
my $zbx_host = shift // {};
my $wanted = shift || [];

my %current;
my @merged;
foreach my $tpl (@{ $zbx_host->{templates} || [] }) {
    my $id = $tpl->{templateid};
    next if (!$id || $current{$id});
    $current{$id} = 1;
    push(@merged, $id);
    }

my @added;
foreach my $id (@$wanted) {
    next if (!$id || $current{$id});
    $current{$id} = 1;
    push(@merged, $id);
    push(@added, $id);
    }

return (\@merged, \@added);
}

#---------------------------------------------------------------------------------
# Macros are refreshed, foreign macros of the host are not removed

sub zabbix_host_merge_macros {
my $zbx_host = shift // {};
my $wanted = shift || [];

my %current;
my @merged;
foreach my $macro (@{ $zbx_host->{macros} || [] }) {
    next if (!$macro->{macro});
    my $key = uc($macro->{macro});
    next if ($current{$key});
    $current{$key} = 1;
    push(@merged, { macro => $macro->{macro}, value => $macro->{value} // '', type => $macro->{type} // 0 });
    }

foreach my $macro (@$wanted) {
    next if (!$macro->{macro});
    my $key = uc($macro->{macro});
    my $entry = {
        macro => $macro->{macro},
        value => $macro->{value} // '',
        type => defined $macro->{type} ? $macro->{type} : 0,
        };
    if ($current{$key}) {
        #the host already knows this macro, its value is refreshed
        @merged = map { (uc($_->{macro}) eq $key) ? $entry : $_ } @merged;
        } else {
        $current{$key} = 1;
        push(@merged, $entry);
        }
    }

return \@merged;
}

#---------------------------------------------------------------------------------
# Host interface of the eye record. Equipment with snmp gets a snmp interface, the
# rest of the hosts gets an agent interface. The names of the snmpv3 details
# parameters differ in zabbix 6.0 and zabbix 7.0

sub zabbix_host_interface {
my $host = shift // {};
my $ip = $host->{ip} // '';
$ip =~ s{/\d+$}{};
return if (!$ip);

my %interface = (
    ip => $ip,
    dns => '',
    useip => 1,
    main => 1,
    );

my $snmp = $host->{snmp} || {};
my $version = $snmp->{version} // 0;

if ($version >= 1) {
    $interface{type} = 2;
    $interface{port} = $snmp->{port} // 161;
    $interface{details} = zabbix_snmp_details($snmp);
    } else {
    $interface{type} = 1;
    $interface{port} = $host->{agent_port} // 10050;
    }

return \%interface;
}

#---------------------------------------------------------------------------------
# snmp parameters of the device in the zabbix representation

sub zabbix_snmp_details {
my $snmp = shift // {};
my $version = $snmp->{version} // 2;

my %details = (version => $version);

if ($version == 3) {
    my $password = $snmp->{'ro-password'} // '';
    #eye stores a single passphrase, it is used for the authorization and for the encryption
    my $level = ($password) ? 2 : 0;
    if ($zabbix_new_groups) {
        #zabbix 7.0 names
        $details{securityname} = $snmp->{'ro-user'} // '';
        $details{securitylevel} = $level;
        $details{authpassphrase} = $password;
        $details{privpassphrase} = $password;
        } else {
        #zabbix 6.0 names
        $details{username} = $snmp->{'ro-user'} // '';
        $details{authtype} = $level;
        $details{authpassphrase} = $password;
        $details{privpassphrase} = $password;
        }
    $details{authprotocol} = zabbix_snmp_auth_protocol($snmp->{'auth-proto'});
    $details{privprotocol} = zabbix_snmp_priv_protocol($snmp->{'priv-proto'});
    } else {
    $details{community} = $snmp->{'ro-community'} // '';
    $details{bulk} = 1;
    }

return \%details;
}

#---------------------------------------------------------------------------------
# snmp authorization protocol

sub zabbix_snmp_auth_protocol {
my $protocol = lc(shift // 'sha');
my %map = (
    md5 => 0,
    sha => 1,
    sha1 => 1,
    sha224 => 2,
    sha256 => 3,
    sha384 => 4,
    sha512 => 5,
    );
return $map{$protocol} // 1;
}

#---------------------------------------------------------------------------------
# snmp encryption protocol

sub zabbix_snmp_priv_protocol {
my $protocol = lc(shift // 'aes128');
my %map = (
    des => 0,
    aes => 1,
    aes128 => 1,
    aes192 => 2,
    aes256 => 3,
    );
return $map{$protocol} // 1;
}

#---------------------------------------------------------------------------------
# hash reference of tags => zabbix array reference

sub zabbix_tag_list {
my $tags = shift // {};
my @list;
foreach my $tag (sort keys %$tags) {
    my $value = $tags->{$tag};
    next if (!defined $value);
    $value =~ s/\s+/ /g;
    push(@list, { tag => $tag, value => "$value" });
    }
return \@list;
}

#---------------------------------------------------------------------------------
# Macro list in the zabbix representation

sub zabbix_macro_list {
my $macros = shift // [];
my @list;
foreach my $macro (@$macros) {
    next if (!$macro->{macro});
    push(@list, {
        macro => $macro->{macro},
        value => $macro->{value} // '',
        type => defined $macro->{type} ? $macro->{type} : 0,
        });
    }
return \@list;
}

1;
}
