#!/usr/bin/perl

#
# Copyright (C) Roman Dmitriev, rnd@rajven.ru
#

# sync_zabbix.pl - export eye network devices and user ip records into zabbix.
#
# The script is the analog of docs/nagios/gen_nagios_config.pl for zabbix.
# It does not write any config files, it changes zabbix through the json rpc api.
#
# zabbix is used when the eye config options are set:
#   74 - monitoring system = zabbix
#   75 - zabbix api url (api_jsonrpc.php of the zabbix installation)
#   76 - zabbix api key (api token of the zabbix user)
#
# The api library eyelib::zabbix is installed with the other eye libraries
# into /opt/Eye/scripts/eyelib.
#
# What the script does:
#   - a record with monitoring=1 is created in zabbix with the templates and the
#     macros of its device model, in the host group bound to the organizational
#     unit of the record. without a group when the organizational unit has none
#   - the tag parent contains the name of the switch the device or the address is
#     connected to, the tag eye contains the eye record identifier
#   - a record already present in zabbix is checked by name, by the parent tag and
#     by the list of templates. a changed name or tag is updated, missing templates
#     are added, templates already linked to the host are ignored and never removed
#   - a record with monitoring_old=1 and monitoring=0 is disabled in zabbix.
#     hosts are never deleted
#   - renamed templates are detected: the template is searched in zabbix by its
#     identifier and by its name, the eye database gets the actual name
#
# The link_check parameter of the ip records is ignored, unlike the nagios
# configuration zabbix does not need the port dependency services.
#
# usage: sync_zabbix.pl [--dry-run] [--verbose] [--help]

use utf8;
use warnings;
use Encode;
use open qw(:std :encoding(UTF-8));
no warnings 'utf8';

use English;
use FindBin '$Bin';
use lib "/opt/Eye/scripts";
use strict;
use Getopt::Long;
use Data::Dumper;
use eyelib::config;
use eyelib::main;
use eyelib::database;
use eyelib::common;
use eyelib::logconfig;
use eyelib::snmp;
use eyelib::zabbix;
use Fcntl qw(:flock);

#tag of the eye record, it allows to find the host even after it was renamed
my $tag_eye = 'eye';
#tag with the name of the switch the record is connected to
my $tag_parent = 'parent';
#hosts are loaded from zabbix in batches
use constant ZABBIX_BATCH => 200;

open(SELF, "<", $0) or die "Cannot open $0 - $!";
flock(SELF, LOCK_EX | LOCK_NB) or exit 1;

my $opt_help = 0;
my $opt_dry = 0;
my $opt_verbose = 0;

GetOptions(
    'dry-run' => \$opt_dry,
    'verbose' => \$opt_verbose,
    'help|h' => \$opt_help,
) or exit 1;

if ($opt_help) {
    print "usage: $0 [options]\n";
    print "  --dry-run   show the planned actions without changing zabbix\n";
    print "  --verbose   write the detailed log\n";
    print "  --help      this help\n";
    exit 0;
    }

set_log_level(3) if ($opt_verbose);

#===============================================================
# zabbix must be selected as the monitoring system and configured

if (!zabbix_monitoring_enabled($dbh)) {
    log_error("Zabbix is not selected as the monitoring system, or the api url/key (options 74-76) are not set");
    do_exit(1);
    }

zabbix_api_init($config_ref{zabbix_api_url}, $config_ref{zabbix_api_key});

my $api_version = zabbix_api_version();
if (!$api_version) {
    log_error("Zabbix api is not available: " . $zabbix_error);
    do_exit(1);
    }

log_info("Zabbix api $api_version, dry run: " . ($opt_dry ? 'yes' : 'no'));

#===============================================================
# static data of eye

my %ou;
foreach my $row (get_records_sql($dbh, "SELECT * FROM ou")) {
    $ou{$row->{id}} = $row;
    }

my %models;
foreach my $row (get_records_sql($dbh, "SELECT * FROM device_models")) {
    $models{$row->{id}} = $row;
    }

#templates bound to the device models

my %model_templates;
foreach my $row (get_records_sql($dbh, "SELECT * FROM zabbix_templates ORDER BY template_name")) {
    push(@{ $model_templates{ $row->{device_model_id} } }, $row);
    }

#macros used by the device models with the defaults of the model macro list

my %model_macros;
my @macro_rows = get_records_sql($dbh,
    "SELECT m.*, l.default_value, l.macro_type FROM zabbix_macros m
     LEFT JOIN zabbix_macros_list l ON m.macros_list_id = l.id
     WHERE m.is_used = 1 ORDER BY m.macro_name");
foreach my $row (@macro_rows) {
    push(@{ $model_macros{ $row->{device_model_id} } }, $row);
    }

#===============================================================
# caches of the zabbix objects, the api is not asked twice for the same object

my %template_cache;
my %group_cache;

#===============================================================
# eye records to be monitored

my @hosts;
my %name_count;

##################################### Netdevices analyze ###########################

{
    my @netdev_list = get_records_sql($dbh,
        'SELECT * FROM devices WHERE deleted=0 and monitoring=1 ORDER BY id');

    foreach my $device (@netdev_list) {
        next if (!$device->{ip});

        my $name = host_base_name($device->{device_name}, 'netdev_' . $device->{id});

        my $host = {
            key => 'netdev_' . $device->{id},
            type => 'netdev',
            id => $device->{id},
            host => $name,
            #the description of the device becomes the visible name of the host
            display => host_display_name($device->{description}, $name),
            ip => $device->{ip},
            model_id => $device->{device_model_id} // 0,
            parent => '',
            };

        #snmp parameters of the device are used by the zabbix host interface
        setCommunity($device);
        $host->{snmp} = $device->{snmp};

        #a network device belongs to the ou of its owner, otherwise to the default ou of its type
        my $ou_id = 0;
        if ($device->{user_id}) {
            my $login = get_record_sql($dbh, "SELECT * FROM user_list WHERE id= ?", $device->{user_id});
            $ou_id = $login->{ou_id} if ($login and $login->{ou_id});
            }
        if (!$ou_id) { $ou_id = ($device->{device_type} eq 2) ? 10 : 7; }
        $host->{ou_id} = $ou_id;

        # the switch in the uplink of the device becomes the parent tag,
        # the parent is used even when its own monitoring is switched off
        my $uplink_port = get_record_sql($dbh,
            "SELECT * FROM device_ports WHERE uplink=1 AND device_id= ? AND target_port_id>0 ORDER BY port DESC", $device->{id});
        if ($uplink_port and $uplink_port->{target_port_id}) {
            my $parent_port = get_record_sql($dbh, "SELECT * FROM device_ports WHERE id= ?", $uplink_port->{target_port_id});
            if ($parent_port and $parent_port->{device_id}) {
                my $parent_device = get_record_sql($dbh,
                    "SELECT * FROM devices WHERE id= ? AND deleted=0", $parent_port->{device_id});
                $host->{parent} = $parent_device->{device_name} if ($parent_device);
                }
            }

        push(@hosts, $host);
        $name_count{ lc($host->{host}) }++;
        }
    }

##################################### User ip records analyze ######################

{
    my @auth_list = get_records_sql($dbh,
        'SELECT * FROM user_auth WHERE deleted=0 and monitoring=1 ORDER BY id');

    foreach my $auth (@auth_list) {
        next if (!$auth->{ip});

        my $login = get_record_sql($dbh, "SELECT * FROM user_list WHERE id= ?", $auth->{user_id});
        my $auth_device = get_record_sql($dbh, "SELECT * FROM devices WHERE user_id= ?", $auth->{user_id});

        #a user owning a monitored switch or router is exported as a network device
        if ($auth_device and $auth_device->{deleted} == 0
            and $auth_device->{device_type} <= 2 and $auth_device->{monitoring} == 1) {
            next;
            }

        my $model_id = $auth->{device_model_id} // 0;
        $model_id = $auth_device->{device_model_id} if (!$model_id and $auth_device);

        my $name = host_base_name(auth_host_name($auth, $login), 'auth_' . $auth->{id});

        my $host = {
            key => 'auth_' . $auth->{id},
            type => 'auth',
            id => $auth->{id},
            host => $name,
            #the description of the record becomes the visible name of the host
            display => host_display_name($auth->{description}, $name),
            ip => $auth->{ip},
            model_id => $model_id,
            parent => '',
            };

        #the customer equipment is polled over snmp when it is registered as a device
        if ($auth_device and $auth_device->{snmp_version}) {
            setCommunity($auth_device);
            $host->{snmp} = $auth_device->{snmp};
            }

        $host->{ou_id} = ($auth->{ou_id} && $auth->{ou_id} > 0) ? $auth->{ou_id} : ($login->{ou_id} // 0);

        # the switch the address is connected to becomes the parent tag
        my $connection = get_record_sql($dbh, "SELECT * FROM connections WHERE auth_id= ?", $auth->{id});
        if ($connection and $connection->{port_id}) {
            my $port = get_record_sql($dbh, "SELECT * FROM device_ports WHERE id= ?", $connection->{port_id});
            if ($port and $port->{device_id}) {
                my $parent_device = get_record_sql($dbh,
                    "SELECT * FROM devices WHERE id= ? AND deleted=0", $port->{device_id});
                $host->{parent} = $parent_device->{device_name} if ($parent_device);
                }
            }

        push(@hosts, $host);
        $name_count{ lc($host->{host}) }++;
        }
    }

##################################### Disabled records #############################

my @disable_list;

{
    foreach my $device (get_records_sql($dbh,
        'SELECT * FROM devices WHERE deleted=0 and monitoring=0 and monitoring_old=1')) {
        push(@disable_list, {
            key => 'netdev_' . $device->{id},
            type => 'netdev',
            id => $device->{id},
            host => host_base_name($device->{device_name}, 'netdev_' . $device->{id}),
            });
        }
    }

{
    foreach my $auth (get_records_sql($dbh,
        'SELECT * FROM user_auth WHERE deleted=0 and monitoring=0 and monitoring_old=1')) {
        my $login = get_record_sql($dbh, "SELECT * FROM user_list WHERE id= ?", $auth->{user_id});
        push(@disable_list, {
            key => 'auth_' . $auth->{id},
            type => 'auth',
            id => $auth->{id},
            host => host_base_name(auth_host_name($auth, $login), 'auth_' . $auth->{id}),
            });
        }
    }

#===============================================================
# doubled host names are suffixed with the eye record identifier

foreach my $host (@hosts) {
    if (($name_count{ lc($host->{host}) } // 0) > 1) {
        my $unique = $host->{host} . '_' . $host->{id};
        log_warning("Zabbix: the name " . $host->{host} . " is used by several eye records, $unique is used for " . $host->{key});
        $host->{host} = $unique;
        }
    }

log_info("Zabbix: " . scalar(@hosts) . " eye records with monitoring on, "
    . scalar(@disable_list) . " records with monitoring off");

#===============================================================
# hosts already known to zabbix, they are loaded in batches by their names

my %known_hosts;
my @check_names = map { $_->{host} } @hosts;
push(@check_names, map { $_->{host} } @disable_list);
@check_names = uniq(@check_names);

while (my @batch = splice(@check_names, 0, ZABBIX_BATCH)) {
    my $found = zabbix_get_hosts_by_names(@batch);
    foreach my $key (keys %$found) {
        $known_hosts{$key} = $found->{$key};
        }
    }

#hosts created by eye are also found by the eye tag, a single call is done for all of them
my %tagged_hosts = %{ zabbix_get_tagged_hosts($tag_eye) };

my $tagged_count = scalar(keys %tagged_hosts);
log_info("Zabbix: " . scalar(keys %known_hosts) . " hosts found by name, $tagged_count hosts created by eye");

#===============================================================
# export

my %stat = (created => 0, updated => 0, unchanged => 0, disabled => 0, failed => 0);

foreach my $host (@hosts) {
    $stat{ sync_host($host) }++;
    }

# records that stopped being monitored are switched off in zabbix

foreach my $item (@disable_list) {
    my $eye_tag = zabbix_host_tag_name($item->{type}, $item->{id});
    my $zbx_host = $tagged_hosts{$eye_tag};
    if (!$zbx_host) {
        # a host created before the eye tag appeared is searched by its name
        $zbx_host = $known_hosts{ zabbix_host_key($item->{host}) };
        next if ($zbx_host && zabbix_host_tag_value($zbx_host, $tag_eye) ne '');
        }
    next if (!$zbx_host);

    if ($zbx_host->{status} eq 1) {
        log_verbose("Zabbix: host " . $zbx_host->{host} . " is already disabled");
        next;
        }

    if ($opt_dry) {
        log_info("Zabbix dry run: host " . $zbx_host->{host} . " must be disabled");
        next;
        }

    $stat{disabled}++ if (zabbix_disable_host($zbx_host->{hostid}, $zbx_host->{host}));
    }

log_info("Zabbix sync summary: created $stat{created}, updated $stat{updated}, disabled $stat{disabled}, unchanged $stat{unchanged}, failed $stat{failed}");

do_exit(0);

#===============================================================
# One eye record

sub sync_host {
my $host = shift;

my $templates = host_templates($host);
my $macros = host_macros($host);
my $groups = host_groups($host);
my $eye_tag = zabbix_host_tag_name($host->{type}, $host->{id});

my $zbx_host = $known_hosts{ zabbix_host_key($host->{host}) };
if (!$zbx_host) {
    # the host keeps the eye tag, so it is found even after it was renamed in zabbix
    $zbx_host = $tagged_hosts{$eye_tag};
    }

if (!$zbx_host) {
    return create_host($host, $templates, $macros, $groups, $eye_tag);
    }

return update_host($host, $zbx_host, $templates, $macros, $eye_tag);
}

#===============================================================
# The host is created with the templates, the macros and the group of the model

sub create_host {
my ($host, $templates, $macros, $groups, $eye_tag) = @_;

log_info("Zabbix: host $host->{host} - " . (scalar(@$templates) ? scalar(@$templates) . ' template(s)' : 'no templates')
    . ', ' . (scalar(@$macros) ? scalar(@$macros) . ' macro(s)' : 'no macros')
    . ', ' . (scalar(@$groups) ? 'group ' . $groups->[0] : 'without group')
    . ', parent tag: ' . ($host->{parent} ? $host->{parent} : 'none'));

if ($opt_dry) {
    log_info("Zabbix dry run: host $host->{host} must be created");
    return 'unchanged';
    }

my $hostid = zabbix_create_host({
    host => $host->{host},
    display => $host->{display},
    ip => $host->{ip},
    snmp => $host->{snmp},
    groups => $groups,
    templates => $templates,
    macros => $macros,
    tags => {
        $tag_eye => $eye_tag,
        $tag_parent => $host->{parent} // '',
        },
    });

if (!$hostid) {
    log_error("Zabbix: cannot create host $host->{host}. "
        . ($zabbix_error =~ /group/i
            ? "Bind a zabbix host group to the organizational unit (options 74-76 must be set and the group must exist in zabbix)."
            : $zabbix_error));
    return 'failed';
    }

# the newly created host is remembered, the next records do not ask zabbix about it
$tagged_hosts{$eye_tag} = {
    hostid => $hostid,
    host => $host->{host},
    name => $host->{display},
    status => 0,
    templates => [],
    macros => $macros,
    tags => [
        { tag => $tag_eye, value => $eye_tag },
        { tag => $tag_parent, value => $host->{parent} // '' },
        ],
    };

$known_hosts{ zabbix_host_key($host->{host}) } = {
    hostid => $hostid,
    host => $host->{host},
    name => $host->{display},
    status => 0,
    templates => [ map { { templateid => $_ } } @$templates ],
    macros => $macros,
    tags => [
        { tag => $tag_eye, value => $eye_tag },
        { tag => $tag_parent, value => $host->{parent} // '' },
        ],
    };

return 'created';
}

#===============================================================
# name, parent tag and templates of the existing host are checked

sub update_host {
my ($host, $zbx_host, $templates, $macros, $eye_tag) = @_;

my %update;
my $change = 0;

# eye owns only the hosts it created itself
my $old_eyetag = zabbix_host_tag_value($zbx_host, $tag_eye);
if ($old_eyetag eq '') {
    log_warning("Zabbix: host $host->{host} exists in zabbix but was not created by eye, it is left untouched");
    return 'unchanged';
    }

if (zabbix_host_key($zbx_host->{host}) ne zabbix_host_key($host->{host})) {
    log_info("Zabbix: host " . $zbx_host->{host} . " must be renamed to $host->{host}");
    $update{host} = $host->{host};
    $change = 1;
    }

if (defined $zbx_host->{name} && $zbx_host->{name} ne $host->{display}) {
    log_info("Zabbix: host $host->{host} visible name changed from '" . $zbx_host->{name} . "' to '" . $host->{display} . "'");
    $update{display} = $host->{display};
    $change = 1;
    }

my $old_parent = zabbix_host_tag_value($zbx_host, $tag_parent);
my $new_parent = $host->{parent} // '';
if ($old_parent ne $new_parent) {
    log_info("Zabbix: host $host->{host} parent tag changed from '$old_parent' to '$new_parent'");
    # zabbix replaces the whole list of tags, the foreign tags of the host are kept
    my %tags;
    foreach my $tag (@{ $zbx_host->{tags} || [] }) {
        next if (!$tag->{tag});
        next if (lc($tag->{tag}) eq $tag_eye or lc($tag->{tag}) eq $tag_parent);
        $tags{ $tag->{tag} } = $tag->{value} // '';
        }
    $tags{$tag_eye} = $eye_tag;
    $tags{$tag_parent} = $new_parent;
    $update{tags} = \%tags;
    $change = 1;
    }

# only missing templates are added, existing templates of the host are ignored
my ($merged, $added) = zabbix_host_merge_templates($zbx_host, $templates);
if (scalar(@$added)) {
    log_info("Zabbix: host $host->{host} gets " . scalar(@$added) . " new template(s)");
    $update{templates} = $merged;
    $change = 1;
    }

# macros of the device model are applied to the host
if (macros_changed($zbx_host, $macros)) {
    log_info("Zabbix: host $host->{host} gets the macros of the device model");
    $update{macros} = zabbix_host_merge_macros($zbx_host, $macros);
    $change = 1;
    }

if ($zbx_host->{status} eq 1) {
    log_info("Zabbix: host $host->{host} monitoring is switched back on");
    $update{status} = 0;
    $change = 1;
    }

if (!$change) {
    log_verbose("Zabbix: host $host->{host} is up to date");
    return 'unchanged';
    }

if ($opt_dry) {
    log_info("Zabbix dry run: host $host->{host} must be updated");
    return 'unchanged';
    }

$update{logname} = $zbx_host->{host};
my $result = zabbix_update_host($zbx_host->{hostid}, \%update) ? 'updated' : 'failed';

if ($result eq 'updated') {
    $known_hosts{ zabbix_host_key($host->{host}) } = $zbx_host;
    }

return $result;
}

#===============================================================
# Only the macros of the device model are compared with the macros of the host

sub macros_changed {
my ($zbx_host, $wanted) = @_;

my %current;
foreach my $macro (@{ $zbx_host->{macros} || [] }) {
    next if (!$macro->{macro});
    $current{ uc($macro->{macro}) } = $macro->{value} // '';
    }

foreach my $macro (@$wanted) {
    my $key = uc($macro->{macro});
    return 1 if (!exists $current{$key});
    return 1 if ($current{$key} ne ($macro->{value} // ''));
    }

return 0;
}

#===============================================================
# Templates of the device model, each of them is searched in zabbix

sub host_templates {
my $host = shift;
my $model_id = $host->{model_id} // 0;
return [] if (!$model_id or !$model_templates{$model_id});

my @list;
foreach my $row (@{ $model_templates{$model_id} }) {
    my $template = template_find($row);
    if (!$template) {
        log_warning("Zabbix: template " . $row->{template_name} . " of the model "
            . model_name($model_id) . " is not found in zabbix, the template is skipped");
        next;
        }
    if ($template->{host} ne ($row->{template_name} // '') or $template->{id} ne ($row->{template_id} // '')) {
        # the template was renamed or recreated in zabbix, eye stores the actual name
        log_info("Zabbix: template of the model " . model_name($model_id) . ' changed: "'
            . ($row->{template_name} // '') . '" => "' . $template->{host} . '"');
        do_sql($dbh, "UPDATE zabbix_templates SET template_name=?, template_id=? WHERE id=?",
            $template->{host}, $template->{id}, $row->{id});
        $row->{template_name} = $template->{host};
        $row->{template_id} = $template->{id};
        }
    push(@list, $template->{id});
    }

return \@list;
}

#===============================================================
# The template is searched by the stored identifier, then by its name

sub template_find {
my $row = shift;

my $cache_key = 'tpl_' . ($row->{id} // $row->{template_id} // 'new');
return $template_cache{$cache_key} if (exists $template_cache{$cache_key});

my $template;
$template = zabbix_get_template_by_id($row->{template_id}) if ($row->{template_id});
$template = zabbix_get_template_by_name($row->{template_name}) if (!$template and $row->{template_name});

$template_cache{$cache_key} = $template;
return $template;
}

#===============================================================
# Macros used by the device model

sub host_macros {
my $host = shift;
my $model_id = $host->{model_id} // 0;
return [] if (!$model_id or !$model_macros{$model_id});

my @list;
foreach my $row (@{ $model_macros{$model_id} }) {
    my $name = zabbix_macro_name($row->{macro_name});
    next if ($name eq '');
    my $value = $row->{macro_value};
    $value = $row->{default_value} if (!defined $value or $value eq '');
    push(@list, {
        macro => $name,
        value => $value // '',
        type => $row->{macro_type} // 0,
        });
    }

return \@list;
}

#===============================================================
# Host group of the organizational unit, a record without a group is exported alone

sub host_groups {
my $host = shift;

my $ou_id = $host->{ou_id} // 0;
my $group_id = ($ou_id and $ou{$ou_id}) ? $ou{$ou_id}->{zabbix_group_id} : 0;
if (!$group_id) {
    log_verbose("Zabbix: ou '" . (($ou_id and $ou{$ou_id}) ? $ou{$ou_id}->{ou_name} : $ou_id)
        . "' has no zabbix host group, the host is created without group");
    return [];
    }

my $group = zabbix_get_group_cached($group_id);
if (!$group) {
    log_error("Zabbix: the host group $group_id of the ou '"
        . (($ou{$ou_id} && $ou{$ou_id}->{ou_name}) ? $ou{$ou_id}->{ou_name} : $ou_id)
        . "' is not found in zabbix, the host is created without group");
    return [];
    }

return [ $group->{groupid} ];
}

#===============================================================

sub zabbix_get_group_cached {
my $group_id = shift;
return if (!$group_id);
return $group_cache{$group_id} if (exists $group_cache{$group_id});
my $group = zabbix_get_group($group_id);
$group_cache{$group_id} = $group;
return $group;
}

#===============================================================
# The name of the zabbix host for the eye record

sub host_base_name {
my ($name, $fallback) = @_;
$name = translit(defined $name ? $name : '');
$name =~ s/^\s+|\s+$//g;
return $name if ($name);
return $fallback;
}

#===============================================================
# Visible name of the zabbix host

sub host_display_name {
my ($description, $fallback) = @_;
$description = translit(defined $description ? $description : '');
$description =~ s/^\s+|\s+$//g;
return $description if ($description);
return $fallback;
}

#===============================================================
# The name of the ip record host, the same rule as in the eye web interface

sub auth_host_name {
my ($auth, $login) = @_;

return $auth->{dns_name} if ($auth->{dns_name});
return $auth->{dhcp_hostname} if ($auth->{dhcp_hostname});

if ($auth->{description}) {
    my $description = translit($auth->{description});
    $description =~ s/\(/-/g;
    $description =~ s/\)/-/g;
    $description =~ s/--/-/g;
    return $description if ($description);
    }

my $user_login = ($login and $login->{login}) ? $login->{login} : 'host';
return $user_login . '_' . $auth->{id};
}

#===============================================================
# Macro name in the zabbix notation

sub zabbix_macro_name {
my $name = shift // '';
$name =~ s/^\s+|\s+$//g;
return '' if ($name eq '');
$name = uc($name);
$name =~ s/[^A-Z0-9_.\$\{\}]//g;
$name =~ s/^[\{\$]+//;
$name =~ s/[\}]+$//;
return '' if ($name eq '');
return '{$' . $name . '}';
}

#===============================================================

sub model_name {
my $model_id = shift;
return $models{$model_id}->{model_name} // $model_id;
}
