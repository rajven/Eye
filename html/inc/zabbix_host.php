<?php
// Export of a single eye record (a network device or an ip record) to zabbix.
// The rules of the host names, tags, groups, templates and macros are the same as
// in scripts/utils/zabbix/sync_zabbix.pl, so both ways give the same result in zabbix.
if (!defined("CONFIG")) {
    die("Not defined");
}

// fallback host group of zabbix, the same identifier as in the sync script
define('ZABBIX_DEFAULT_GROUP_ID', 5);

// Zabbix is able to serve the record: the option 74 is zabbix and the api is set
function zabbix_record_supported($db)
{
    return is_zabbix_enabled();
}

// State of the eye record: what the zabbix host must look like
function zabbix_record_state($db, $type, $id)
{
    $id = (int)$id;
    if ($id <= 0) {
        return null;
    }

    // only a device of eye is a host of the monitoring system, an ip record of a user
    // is served by the device of its owner
    if ($type === 'netdev') {
        return zabbix_device_state($db, $id);
    }

    return null;
}

// State of a network device, the device also carries the model of its addresses
function zabbix_device_state($db, $device_id)
{
    $device = get_record_sql($db, "SELECT * FROM devices WHERE id = ? AND deleted = 0", [$device_id]);
    if (empty($device)) {
        return null;
    }

    $name = zabbix_device_host_name($device);
    $state = [
        'type' => 'netdev',
        'id' => (int)$device['id'],
        'key' => 'netdev_' . $device['id'],
        'host' => $name,
        'description' => $device['description'],
        'ip' => $device['ip'],
        'model_id' => (int)($device['device_model_id'] ?? 0),
        'device_id' => (int)$device['id'],
        'monitoring' => (int)($device['monitoring'] ?? 0),
        'monitoring_old' => (int)($device['monitoring_old'] ?? 0),
        'parent' => '',
        'snmp' => zabbix_record_snmp($device),
    ];

    // the device belongs to the ou of its owner, otherwise to the default ou of its type
    $ou_id = 0;
    if (!empty($device['user_id'])) {
        $login = get_record_sql($db, "SELECT * FROM user_list WHERE id = ?", [(int)$device['user_id']]);
        if (!empty($login['ou_id'])) {
            $ou_id = (int)$login['ou_id'];
        }
    }
    if ($ou_id === 0) {
        $ou_id = ((int)$device['device_type'] === 2) ? 10 : 7;
    }
    $state['ou_id'] = $ou_id;

    // the switch in the uplink of the device becomes the parent tag
    $uplink_port = get_record_sql($db,
        "SELECT * FROM device_ports WHERE uplink = 1 AND device_id = ? AND target_port_id > 0 ORDER BY port DESC",
        [(int)$device['id']]);
    if (!empty($uplink_port['target_port_id'])) {
        $parent_port = get_record_sql($db, "SELECT * FROM device_ports WHERE id = ?", [(int)$uplink_port['target_port_id']]);
        if (!empty($parent_port['device_id'])) {
            $parent = get_record_sql($db, "SELECT * FROM devices WHERE id = ? AND deleted = 0", [(int)$parent_port['device_id']]);
            if (!empty($parent)) {
                $state['parent'] = $parent['device_name'];
            }
        }
    }

    // the equipment of a user has no ports of its own: the port of an address of the
    // owner shows the access switch, the same rule as in sync_zabbix.pl
    if ($state['parent'] === '' && !empty($device['user_id'])) {
        $connection = get_record_sql($db,
            "SELECT * FROM connections WHERE auth_id IN (SELECT id FROM user_auth WHERE user_id = ? AND deleted = 0) ORDER BY auth_id, port_id LIMIT 1",
            [(int)$device['user_id']]);
        if (!empty($connection['port_id'])) {
            $access_port = get_record_sql($db, "SELECT * FROM device_ports WHERE id = ?", [(int)$connection['port_id']]);
            if (!empty($access_port['device_id'])) {
                $parent = get_record_sql($db, "SELECT * FROM devices WHERE id = ? AND deleted = 0", [(int)$access_port['device_id']]);
                if (!empty($parent)) {
                    $state['parent'] = $parent['device_name'];
                }
            }
        }
    }

    return $state;
}

// snmp parameters of the eye record in the representation of the sync script
function zabbix_record_snmp($device)
{
    global $config;

    $version = (int)($device['snmp_version'] ?? 0);
    $community = !empty($device['community']) ? $device['community'] : ($config['snmp_default_community'] ?? 'public');
    if ($version === 0) {
        $version = (int)($config['snmp_default_version'] ?? 2);
        if ($version < 1) {
            $version = 2;
        }
    }

    return [
        'version' => $version,
        'port' => 161,
        'ro-community' => $community,
        'auth-proto' => $device['snmp3_auth_proto'] ?? '',
        'priv-proto' => $device['snmp3_priv_proto'] ?? '',
        'ro-user' => $device['snmp3_user_ro'] ?? '',
        'ro-password' => !empty($device['snmp3_user_ro_password']) ? $device['snmp3_user_ro_password'] : $community,
    ];
}

// Host groups of the organizational unit of the record, an ou is bound to several
// groups. The default group is used when the ou has no group at all, a group which
// zabbix does not know is skipped
function zabbix_record_groups($db, $state)
{
    $ou = get_record_sql($db, "SELECT * FROM ou WHERE id = ?", [(int)$state['ou_id']]);
    $ou_name = $ou['ou_name'] ?? $state['ou_id'];

    $rows = get_records_sql($db, "SELECT * FROM ou_zabbix_groups WHERE ou_id = ? ORDER BY id", [(int)$state['ou_id']]);
    $group_ids = [];
    foreach ($rows as $row) {
        if ((int)$row['group_id'] > 0) {
            $group_ids[] = (int)$row['group_id'];
        }
    }

    if (empty($group_ids)) {
        LOG_DEBUG($db, "Zabbix: ou '$ou_name' has no host group, the default group " . ZABBIX_DEFAULT_GROUP_ID . " is used");
        $group_ids = [ZABBIX_DEFAULT_GROUP_ID];
    }

    $found = zabbix_api_request('hostgroup.get', [
        'output' => ['groupid', 'name'],
        'groupids' => $group_ids,
    ]);

    $known = [];
    foreach ((array)$found as $group) {
        if (!empty($group['groupid'])) {
            $known[(int)$group['groupid']] = 1;
        }
    }

    $groups = [];
    foreach ($group_ids as $group_id) {
        if (!isset($known[$group_id])) {
            LOG_WARNING($db, "Zabbix: host group $group_id of ou '$ou_name' is not found in zabbix, the group is skipped");
            continue;
        }
        $groups[] = $group_id;
    }

    return $groups;
}

// Templates of the device model, the names are refreshed when zabbix renamed them
function zabbix_record_templates($db, $model_id)
{
    if ($model_id <= 0) {
        return [];
    }

    $check = zabbix_check_model_templates($db, $model_id);
    $list = [];
    foreach ($check['ok'] as $row) {
        $list[] = (int)$row['template_id'];
    }
    return $list;
}

// Macros assigned to the eye network device of the record
function zabbix_record_macros($db, $device_id)
{
    $list = [];
    foreach (zabbix_record_device_rows($db, 'macros', $device_id) as $row) {
        $name = normalizer_zabbix_macro_name($row['macro_name']);
        if ($name === '') {
            continue;
        }
        // the value of a secret macro lies in the base ciphered and is opened only for
        // the export: zabbix keeps its own copy of the secret, the base of eye keeps
        // none in the plain text
        $secret = zabbix_macro_is_secret($row['macro_type'] ?? 0);
        $list[] = [
            'macro' => $name,
            'value' => zabbix_macro_open($row['macro_value'] ?? '', $secret),
            'type' => (int)($row['macro_type'] ?? 0),
        ];
    }

    return $list;
}

// Bindings of the eye network device: the macros and the tags look the same in the
// database, so one request serves both of them
function zabbix_record_device_rows($db, $kind, $device_id)
{
    // the rows of the bindings are kept for a request in the globals: the same request
    // usually writes the block of the device and exports its host after that, and the
    // value of a secret macro is read from the base, so a stale row would hand the
    // monitoring system an old text. every write of the bindings drops the cache
    if (!isset($GLOBALS['zabbix_device_rows']) || !is_array($GLOBALS['zabbix_device_rows'])) {
        $GLOBALS['zabbix_device_rows'] = [];
    }
    $cache = &$GLOBALS['zabbix_device_rows'];

    $device_id = (int)$device_id;
    if ($device_id <= 0) {
        return [];
    }
    if (isset($cache[$kind][$device_id])) {
        return $cache[$kind][$device_id];
    }

    if ($kind === 'macros') {
        $rows = get_records_sql($db,
            "SELECT m.macro_value, l.macro_name, l.macro_type
             FROM zabbix_macros m JOIN zabbix_macros_list l ON m.macros_list_id = l.id
             WHERE m.device_id = ? AND m.is_used = 1 ORDER BY l.macro_name", [$device_id]);
    } else {
        $rows = get_records_sql($db,
            "SELECT t.tag_value, l.tag_name
             FROM zabbix_tags t JOIN zabbix_tags_list l ON t.tags_list_id = l.id
             WHERE t.device_id = ? AND t.is_used = 1 ORDER BY l.tag_name", [$device_id]);
    }

    $cache[$kind][$device_id] = $rows;
    return $rows;
}

// the cache of the bindings falls away when the base of them changed: one device, a
// kind of the bindings, or everything the request remembers
function zabbix_record_device_rows_clear($device_id = null, $kind = null)
{
    if (!isset($GLOBALS['zabbix_device_rows']) || !is_array($GLOBALS['zabbix_device_rows'])) {
        return;
    }

    if ($device_id === null) {
        $GLOBALS['zabbix_device_rows'] = [];
        return;
    }

    $device_id = (int)$device_id;
    foreach (array_keys($GLOBALS['zabbix_device_rows']) as $one_kind) {
        if ($kind !== null && $one_kind !== $kind) {
            continue;
        }
        unset($GLOBALS['zabbix_device_rows'][$one_kind][$device_id]);
    }
}

// Tags which eye maintains on the host: the tag of the record, the parent tag and
// the tags of the eye network device
function zabbix_record_tags($db, $state, $eye_tag)
{
    $tags = [
        ZABBIX_TAG_EYE => $eye_tag,
        ZABBIX_TAG_PARENT => (string)$state['parent'],
    ];

    foreach (zabbix_record_device_rows($db, 'tags', (int)$state['device_id']) as $row) {
        $name = trim((string)($row['tag_name'] ?? ''));
        // the tag of the record and the parent tag are not overridden by the device
        if ($name === '' || strtolower($name) === ZABBIX_TAG_EYE || strtolower($name) === ZABBIX_TAG_PARENT) {
            continue;
        }
        $tags[$name] = (string)($row['tag_value'] ?? '');
    }

    return $tags;
}

// Names of the global tags list, eye removes its own tags which are unassigned.
// The macro names are collected the same way, the names of both lists are the
// names which belong to eye
function zabbix_record_owned_names($db, $kind)
{
    static $cache = [];

    if (isset($cache[$kind])) {
        return $cache[$kind];
    }

    $names = [];
    if ($kind === 'macros') {
        foreach (get_records_sql($db, "SELECT macro_name FROM zabbix_macros_list") as $row) {
            $name = normalizer_zabbix_macro_name($row['macro_name']);
            if ($name !== '') {
                $names[strtoupper($name)] = 1;
            }
        }
    } else {
        foreach (get_records_sql($db, "SELECT tag_name FROM zabbix_tags_list") as $row) {
            if (trim((string)$row['tag_name']) !== '') {
                $names[strtolower(trim((string)$row['tag_name']))] = 1;
            }
        }
    }

    $cache[$kind] = $names;
    return $names;
}

// The host of the record: the eye tag first, because the tag of the host survives the
// renaming in zabbix, then the technical name, then the address of the interface which
// zabbix already polls. host.create refuses the address which another host occupies, so
// a device put into zabbix by hand is bound to the record and updated, and is not
// created a second time above the address which is taken
function zabbix_find_host($db, $state, &$occupied_by = NULL)
{
    // the eye tag finds the host even after it was renamed in zabbix
    $zbx_host = zabbix_get_host_by_tag($state['key']);

    if ($zbx_host === null) {
        $zbx_host = zabbix_get_host_by_name($state['host']);
    }

    if ($zbx_host === null) {
        $zbx_host = zabbix_get_host_by_address($state['ip']);
        // a host of the same address which carries the eye tag of another record keeps
        // its owner: two records of one address must not fight for one host every run,
        // and the creation of the second host there is refused by zabbix anyway
        if ($zbx_host !== null) {
            $owner = zabbix_host_tag_value($zbx_host, ZABBIX_TAG_EYE);
            if ($owner !== '' && $owner !== $state['key']) {
                $occupied_by = $owner;
                return null;
            }
        }
    }

    return $zbx_host;
}

// Creation or update of the zabbix host of the eye record. An ordinary run only links
// the templates which the host has not got yet; a changed model of the device, or the
// will of the operator, is carried here as ZABBIX_TEMPLATES_RELINK or
// ZABBIX_TEMPLATES_UNLINK - the host of eye then follows its model with the whole list
// of its templates
function zabbix_record_apply($db, $state, $tpl_mode = ZABBIX_TEMPLATES_ADD)
{
    $templates = zabbix_record_templates($db, (int)$state['model_id']);
    $macros = zabbix_record_macros($db, (int)$state['device_id']);
    $groups = zabbix_record_groups($db, $state);
    $tags = zabbix_record_tags($db, $state, $state['key']);

    $occupied_by = null;
    $zbx_host = zabbix_find_host($db, $state, $occupied_by);

    if ($occupied_by !== null) {
        LOG_INFO($db, "Zabbix: address " . zabbix_host_ip($state['ip'])
            . " is polled by the host of the eye record $occupied_by allready, the record "
            . $state['key'] . " is left alone");
        return ['action' => 'occupied', 'host' => $state['host'], 'parent' => $occupied_by,
            'key' => $state['key']];
    }

    if ($zbx_host === null) {
        // there is nothing to unlink from a host which does not exist yet: the wish of
        // the operator is answered honestly, the host is not created for that
        if ($tpl_mode === ZABBIX_TEMPLATES_UNLINK) {
            return ['action' => 'missing', 'host' => $state['host']];
        }

        $hostid = zabbix_api_create_host([
            'host' => $state['host'],
            'description' => $state['description'],
            'ip' => $state['ip'],
            'snmp' => $state['snmp'],
            'groups' => $groups,
            'templates' => $templates,
            'macros' => $macros,
            'tags' => $tags,
        ]);
        if (!$hostid) {
            return ['action' => 'failed', 'host' => $state['host']];
        }
        // the address of the new host is kept: the next record of the page must not ask
        // zabbix for the second host at the address which is taken now
        zabbix_host_interface_remember($state['ip'], $hostid);
        LOG_INFO($db, "Zabbix: host " . $state['host'] . " created (hostid $hostid)");
        return [
            'action' => 'created',
            'hostid' => $hostid,
            'host' => $state['host'],
            'templates' => count($templates),
            'macros' => count($macros),
            'groups' => count($groups),
            'tags' => count($tags),
        ];
    }

    return zabbix_record_update($db, $state, $zbx_host, $templates, $macros, $groups, $tags,
        $tpl_mode);
}

// The name, the groups, the tags, the macros and the templates are updated.
// eye owns the objects of its own lists, the objects of the other integrations stay
function zabbix_record_update($db, $state, $zbx_host, $templates, $macros, $groups, $tags,
                              $tpl_mode = ZABBIX_TEMPLATES_ADD)
{
    $owned_macros = zabbix_record_owned_names($db, 'macros');
    $owned_tags = zabbix_record_owned_names($db, 'tags');

    $update = [];
    $notes = [];
    $note_types = [];

    if (!empty($zbx_host['host']) && strcasecmp(trim($zbx_host['host']), trim($state['host'])) !== 0) {
        $update['host'] = $state['host'];
        $notes[] = 'renamed to ' . $state['host'];
        $note_types[] = 'renamed';
    }
    // the description of the eye record becomes the description of the host, the
    // visible name of the host stays the technical name, as the sync script does it
    if (($zbx_host['description'] ?? '') !== (string)$state['description']) {
        $update['description'] = $state['description'];
        $notes[] = 'the description is updated';
        $note_types[] = 'visible';
    }

    // the groups of the ou which the host has not got yet are added. a group is never
    // removed by the reconciliation: it can be bound by hand in zabbix or belong to
    // another integration. the group which the form of the ou unbinds is taken away
    // there, as an explicit action of the administrator
    $current_groups = [];
    foreach ($zbx_host['groups'] ?? [] as $group) {
        if (!empty($group['groupid'])) {
            $current_groups[(int)$group['groupid']] = 1;
        }
    }

    $added_groups = [];
    foreach ($groups as $group_id) {
        if (!isset($current_groups[(int)$group_id])) {
            $added_groups[] = (int)$group_id;
        }
    }

    if (!empty($added_groups)) {
        $update['groups'] = array_merge(array_keys($current_groups), $added_groups);
        $notes[] = 'host group(s) ' . implode(', ', $added_groups);
        $note_types[] = 'groups';
    }

    if (zabbix_record_tags_changed($zbx_host, $tags, $owned_tags)) {
        $update['tags'] = zabbix_record_tags_merge($zbx_host, $tags, $owned_tags);
        $notes[] = 'tags of the eye record';
        $note_types[] = 'tags';
    }

    // The templates follow the model of the device. An ordinary run links only the
    // templates which the host has not got yet; a changed model, or the hand of the
    // operator, replaces the whole list of the host which eye manages itself - the tag of
    // eye says whose host it is. A host of another owner is never stripped, and the
    // screen is told so: the operation did not touch the templates there
    $tpl_owned = zabbix_host_is_owned($zbx_host, $state['key']);
    $plan = zabbix_host_templates_plan($zbx_host, $templates, $tpl_mode, $tpl_owned);
    if (!empty($plan['added'])) {
        $update['templates'] = $plan['templates'];
        $notes[] = count($plan['added']) . ' template(s) linked';
        $note_types[] = 'templates';
    }
    if (!empty($plan['removed'])) {
        $update['templates'] = $plan['templates'];
        $notes[] = count($plan['removed']) . ' template(s) unlinked';
        $note_types[] = 'templates_unlinked';
    }

    if (zabbix_record_macros_changed($zbx_host, $macros, $owned_macros)) {
        $update['macros'] = zabbix_host_merge_macros($zbx_host, $macros, $owned_macros);
        $notes[] = 'macros of the device';
        $note_types[] = 'macros';
    }

    if ((int)$zbx_host['status'] === 1) {
        $update['status'] = 0;
        $notes[] = 'monitoring enabled again';
        $note_types[] = 'status';
    }

    // the host must be polled at the address of the record: the device moved to another
    // address, or the host was found by its address and carries the parameters of the
    // other poll. host.update does not touch the interfaces, so they are corrected apart
    $wanted_interface = zabbix_host_interface([
        'ip' => $state['ip'],
        'snmp' => $state['snmp'],
        'agent_port' => $state['agent_port'] ?? null,
    ]);
    $move_interface = zabbix_host_interface_changed($zbx_host, $wanted_interface);

    if (empty($update) && !$move_interface) {
        return ['action' => 'unchanged', 'hostid' => $zbx_host['hostid'], 'host' => $zbx_host['host'],
            'templates' => 0, 'unlinked' => 0, 'managed' => $tpl_owned];
    }

    if (!empty($update) && !zabbix_api_update_host($zbx_host['hostid'], $update)) {
        return ['action' => 'failed', 'host' => $zbx_host['host']];
    }

    if ($move_interface) {
        if (!zabbix_api_sync_host_interface($zbx_host, $wanted_interface)) {
            return ['action' => 'failed', 'host' => $zbx_host['host']];
        }
        $notes[] = 'the interface moves to ' . $wanted_interface['ip'];
        $note_types[] = 'address';
    }

    LOG_INFO($db, "Zabbix: host " . $zbx_host['host'] . " updated: " . implode(', ', $notes));
    return [
        'action' => 'updated',
        'hostid' => $zbx_host['hostid'],
        'host' => $zbx_host['host'],
        'notes' => $note_types,
        'templates' => count($plan['added']),
        'unlinked' => count($plan['removed']),
        'managed' => $tpl_owned,
        'parent' => (string)$state['parent'],
    ];
}

// Hosts of the organizational unit which zabbix shows in the given host group and
// which eye created itself: the eye tag of the host points to the device of eye, and
// the organizational unit of that device is the ou of the form
function zabbix_ou_group_hosts($db, $ou_id, $group_id)
{
    $params = [
        'output' => ['hostid', 'host', 'name', 'description', 'status'],
        'groupids' => [(int)$group_id],
        // operator 4 - the host carries the tag of eye, its value is not checked
        'tags' => [['tag' => ZABBIX_TAG_EYE, 'value' => '', 'operator' => 4]],
        'selectTags' => ['tag', 'value'],
        'selectHostGroups' => ['groupid', 'name'],
    ];

    $found = [];
    foreach ((array)zabbix_api_request('host.get', $params) as $zbx_host) {
        if (empty($zbx_host['hostid'])) {
            continue;
        }

        // a host created for an ip record before is not managed by eye anymore
        $key = zabbix_host_tag_value(zabbix_host_normalize($zbx_host), ZABBIX_TAG_EYE);
        if (!preg_match('/^netdev_(\d+)$/i', $key, $match)) {
            continue;
        }

        $state = zabbix_record_state($db, 'netdev', (int)$match[1]);
        if ($state !== null && (int)$state['ou_id'] === (int)$ou_id) {
            $found[] = $zbx_host;
        }
    }

    return $found;
}

// A host group unbound in the form of the organizational unit is taken away from the
// hosts of this ou. The reconciliation of a host never removes a group: it can be
// bound by hand in zabbix or belong to another integration, so the unbind is the only
// place where eye touches the groups which the host has got
function zabbix_ou_group_detach($db, $ou_id, $group_id)
{
    $removed = 0;

    foreach (zabbix_ou_group_hosts($db, $ou_id, $group_id) as $zbx_host) {
        $groups = [];
        foreach ($zbx_host['groups'] ?? [] as $group) {
            $current_id = (int)($group['groupid'] ?? 0);
            if ($current_id > 0 && $current_id !== (int)$group_id) {
                $groups[] = $current_id;
            }
        }

        // zabbix does not keep a host without a host group
        if (empty($groups)) {
            $groups = [ZABBIX_DEFAULT_GROUP_ID];
        }

        if (!zabbix_api_update_host($zbx_host['hostid'], ['groups' => $groups])) {
            LOG_WARNING($db, "Zabbix: cannot take the host group " . (int)$group_id . " away from the host " . $zbx_host['host']);
            continue;
        }

        LOG_INFO($db, "Zabbix: host " . $zbx_host['host'] . " leaves the host group " . (int)$group_id);
        $removed++;
    }

    return $removed;
}

// Are the tags of the eye record in sync with the host, the tags of the other
// integrations do not matter
function zabbix_record_tags_changed($zbx_host, $tags, $owned)
{
    $wanted = [];
    foreach ($tags as $name => $value) {
        $wanted[strtolower((string)$name)] = (string)$value;
    }

    $current = [];
    foreach ($zbx_host['tags'] ?? [] as $tag) {
        if (empty($tag['tag']) || !zabbix_record_tag_is_owned($tag['tag'], $owned)) {
            continue;
        }
        $current[strtolower((string)$tag['tag'])] = (string)($tag['value'] ?? '');
    }

    foreach ($wanted as $key => $value) {
        if (!array_key_exists($key, $current) || $current[$key] !== $value) {
            return true;
        }
    }

    foreach (array_keys($current) as $key) {
        if (!array_key_exists($key, $wanted)) {
            return true;
        }
    }

    return false;
}

// zabbix replaces the whole list of tags, so the tags of the other integrations are
// kept and the eye tags which are not assigned anymore are removed
function zabbix_record_tags_merge($zbx_host, $tags, $owned)
{
    $merged = [];
    foreach ($zbx_host['tags'] ?? [] as $tag) {
        if (empty($tag['tag']) || zabbix_record_tag_is_owned($tag['tag'], $owned)) {
            continue;
        }
        $merged[(string)$tag['tag']] = (string)($tag['value'] ?? '');
    }

    foreach ($tags as $name => $value) {
        $merged[(string)$name] = (string)$value;
    }

    return $merged;
}

// eye owns the tag of the record, the parent tag, the mark of a deleted device and
// the names of its global list
function zabbix_record_tag_is_owned($name, $owned)
{
    $key = strtolower(trim((string)$name));
    if ($key === ZABBIX_TAG_EYE || $key === ZABBIX_TAG_PARENT || $key === ZABBIX_TAG_DELETED) {
        return true;
    }

    return isset($owned[$key]);
}

// Are the macros of the device in sync with the host, the macros of the other
// integrations do not matter
function zabbix_record_macros_changed($zbx_host, $macros, $owned)
{
    $wanted = [];
    foreach ($macros as $macro) {
        if (empty($macro['macro'])) {
            continue;
        }
        $wanted[strtoupper((string)$macro['macro'])] = (string)($macro['value'] ?? '');
    }

    $current = [];
    foreach ($zbx_host['macros'] ?? [] as $macro) {
        if (empty($macro['macro'])) {
            continue;
        }
        $key = strtoupper((string)$macro['macro']);
        if (!isset($owned[$key])) {
            continue;
        }
        $current[$key] = (string)($macro['value'] ?? '');
    }

    foreach ($wanted as $key => $value) {
        if (!array_key_exists($key, $current) || $current[$key] !== $value) {
            return true;
        }
    }

    foreach (array_keys($current) as $key) {
        if (!array_key_exists($key, $wanted)) {
            return true;
        }
    }

    return false;
}

// Monitoring of the host is switched off, the host is not removed from zabbix
function zabbix_record_switch_off($db, $state)
{
    $eye_tag = $state['key'];
    $zbx_host = zabbix_find_host($db, $state);
    if ($zbx_host === null) {
        return ['action' => 'missing'];
    }
    if ((int)$zbx_host['status'] === 1) {
        return ['action' => 'unchanged', 'hostid' => $zbx_host['hostid'], 'host' => $zbx_host['host'], 'disabled' => 1];
    }

    if (!zabbix_api_set_host_status($zbx_host['hostid'], 1)) {
        return ['action' => 'failed', 'host' => $zbx_host['host']];
    }

    LOG_INFO($db, "Zabbix: host " . $zbx_host['host'] . ' monitoring disabled');
    return ['action' => 'disabled', 'hostid' => $zbx_host['hostid'], 'host' => $zbx_host['host']];
}

// The host of the deleted device: monitoring is switched off and the host is marked
// with the eye_deleted tag. eye never deletes the hosts of zabbix: the record of the
// device disappears from the base, and the mark tells why the host stays here turned
// off. The call must happen before delete_device(), afterwards no script would find
// the device, the same rule as in sync_zabbix.pl for the host of a switched off device
function zabbix_record_mark_deleted($db, $state)
{
    $occupied_by = null;
    $zbx_host = zabbix_find_host($db, $state, $occupied_by);

    if ($occupied_by !== null) {
        LOG_WARNING($db, "Zabbix: the address of the deleted record " . $state['key']
            . " is polled by the host of the eye record " . $occupied_by . " already, the host is left alone");
        return ['action' => 'occupied', 'host' => $state['host'], 'parent' => $occupied_by];
    }

    if ($zbx_host === null) {
        return ['action' => 'missing', 'host' => $state['host']];
    }

    $notes = [];

    if ((int)$zbx_host['status'] !== 1) {
        if (!zabbix_api_set_host_status($zbx_host['hostid'], 1)) {
            LOG_WARNING($db, "Zabbix: cannot switch the host " . $zbx_host['host'] . " off");
            return ['action' => 'failed', 'host' => $zbx_host['host']];
        }
        LOG_INFO($db, "Zabbix: host " . $zbx_host['host'] . " monitoring disabled");
        $notes[] = 'disabled';
    }

    if (!zabbix_host_has_tag($zbx_host, ZABBIX_TAG_DELETED)) {
        // host.update replaces the whole list of tags: the current tags are sent back
        // with the mark of the deletion added to them
        $tags = [];
        foreach ($zbx_host['tags'] ?? [] as $tag) {
            if (empty($tag['tag'])) {
                continue;
            }
            $tags[(string)$tag['tag']] = (string)($tag['value'] ?? '');
        }
        $tags[ZABBIX_TAG_DELETED] = '';

        if (!zabbix_api_update_host($zbx_host['hostid'], ['tags' => $tags])) {
            LOG_WARNING($db, "Zabbix: cannot mark the host " . $zbx_host['host'] . " with the " . ZABBIX_TAG_DELETED . " tag");
            return ['action' => 'failed', 'host' => $zbx_host['host']];
        }
        LOG_INFO($db, "Zabbix: host " . $zbx_host['host'] . " is marked with the " . ZABBIX_TAG_DELETED . " tag");
        $notes[] = 'marked';
    }

    if (empty($notes)) {
        return ['action' => 'unchanged', 'hostid' => $zbx_host['hostid'], 'host' => $zbx_host['host'], 'disabled' => 1];
    }

    return ['action' => 'marked', 'hostid' => $zbx_host['hostid'], 'host' => $zbx_host['host'], 'notes' => $notes];
}

// The hosts which poll the address and still carry the mark of a deleted device lose
// the mark: a new device of eye stands on this address now. Only the mark is taken
// away, the state of the hosts is not touched
function zabbix_hosts_clear_deleted_mark($db, $ip)
{
    $ip = zabbix_host_ip($ip);
    if ($ip === '') {
        return 0;
    }

    $found = zabbix_api_request('host.get', [
        'output' => ['hostid', 'host', 'status'],
        // the address of the interface of the host
        'filter' => ['ip' => [$ip]],
        // operator 4 - the host carries the mark of a deleted device, the value is not checked
        'tags' => [['tag' => ZABBIX_TAG_DELETED, 'value' => '', 'operator' => 4]],
        'selectTags' => ['tag', 'value'],
    ]);
    if (!is_array($found) || empty($found)) {
        return 0;
    }

    $cleared = 0;
    foreach ($found as $one) {
        $zbx_host = zabbix_host_normalize($one);
        if (empty($zbx_host['hostid'])) {
            continue;
        }

        // host.update replaces the whole list of tags: the current tags are sent back
        // without the mark of the deletion
        $tags = [];
        foreach ($zbx_host['tags'] ?? [] as $tag) {
            if (empty($tag['tag']) || strtolower((string)$tag['tag']) === ZABBIX_TAG_DELETED) {
                continue;
            }
            $tags[(string)$tag['tag']] = (string)($tag['value'] ?? '');
        }

        if (!zabbix_api_update_host($zbx_host['hostid'], ['tags' => $tags])) {
            LOG_WARNING($db, "Zabbix: cannot take the " . ZABBIX_TAG_DELETED . " tag away from the host " . $zbx_host['host']);
            continue;
        }

        LOG_INFO($db, "Zabbix: host " . $zbx_host['host'] . " is not the host of a deleted device anymore, the " . ZABBIX_TAG_DELETED . " tag is taken away");
        $cleared++;
    }

    return $cleared;
}

// The record is exported to zabbix after the changes made by the web interface.
// The mode of the templates is asked here because the caller is the only one who knows
// that the model of the device has just changed, or that the operator has asked for the
// templates of the hosts on purpose
function zabbix_record_sync($db, $type, $id, $tpl_mode = ZABBIX_TEMPLATES_ADD)
{
    if (!zabbix_record_supported($db)) {
        return ['action' => 'unsupported'];
    }

    $state = zabbix_record_state($db, $type, $id);
    if ($state === null) {
        return ['action' => 'skipped'];
    }

    if ((int)$state['monitoring'] === 1) {
        return zabbix_record_apply($db, $state, $tpl_mode);
    }

    // monitoring was switched off, the host is switched off too
    if ((int)$state['monitoring_old'] === 1) {
        return zabbix_record_switch_off($db, $state);
    }

    return ['action' => 'skipped'];
}

// Description of the zabbix host of the record, it is shown in the forms
function zabbix_record_info($db, $type, $id)
{
    if (!zabbix_record_supported($db)) {
        return null;
    }

    $state = zabbix_record_state($db, $type, $id);
    if ($state === null) {
        return null;
    }

    $templates = zabbix_record_templates($db, (int)$state['model_id']);
    $macros = zabbix_record_macros($db, (int)$state['device_id']);
    $groups = zabbix_record_groups($db, $state);
    $tags = zabbix_record_tags($db, $state, $state['key']);

    return [
        'state' => $state,
        'host' => zabbix_find_host($db, $state),
        'groups' => $groups,
        'templates' => $templates,
        'macros' => $macros,
        'tags' => $tags,
    ];
}

// Block of the form: state of the zabbix host of the record and the state which
// eye wants to see in zabbix. The buttons of the manual export are optional
function print_zabbix_record_block($db, $record_type, $record_id, $with_buttons = false, $colspan = 3)
{
    if (!zabbix_record_supported($db)) {
        return false;
    }

    $state = zabbix_record_state($db, $record_type, $record_id);
    // records which take no part in the monitoring do not pay for the api calls
    if ($state !== null && (int)$state['monitoring'] === 0 && (int)$state['monitoring_old'] === 0) {
        return false;
    }

    $info = zabbix_record_info($db, $record_type, $record_id);
    if ($info === null) {
        print '<tr><td>' . WEB_zabbix_host_title . '</td><td colspan="' . $colspan . '">' . WEB_zabbix_record_skip . "</td></tr>\n";
        return false;
    }

    $state = $info['state'];
    $host = $info['host'];
    $text_colspan = $colspan - ($with_buttons ? 1 : 0);

    print '<tr><td>' . WEB_zabbix_host_title . '</td>';
    if ($host === null) {
        echo '<td colspan="' . $text_colspan . '">' . WEB_zabbix_host_none . '</td>';
    } else {
        $status = ((int)$host['status'] === 1) ? WEB_monitoring_host_down : WEB_monitoring_host_up;
        echo '<td colspan="' . $text_colspan . '">'
            . htmlspecialchars($host['host'], ENT_QUOTES, 'UTF-8') . ' [' . $status . '], hostid ' . (int)$host['hostid'] . '</td>';
    }

    if ($with_buttons) {
        echo "<td class='data' align=right><button name='zbx_sync' value='1'>" . WEB_zabbix_sync_now . '</button>';
        if ($host !== null && (int)$host['status'] !== 1) {
            echo " <button name='zbx_off' value='1'>" . WEB_zabbix_sync_off . '</button>';
        }
        echo '</td>';
    }
    echo "</tr>\n";

    // the state of the eye record which is exported to zabbix
    print '<tr><td>' . WEB_zabbix_templates . '</td><td colspan="' . $colspan . '">';
    // the names of the device tags are more useful in the form than their count
    $tag_names = [];
    foreach (array_keys($info['tags']) as $tag_name) {
        if (strtolower((string)$tag_name) !== ZABBIX_TAG_EYE && strtolower((string)$tag_name) !== ZABBIX_TAG_PARENT) {
            $tag_names[] = $tag_name;
        }
    }

    echo count($info['templates']) . ' ' . mb_strtolower(WEB_zabbix_template)
        . ', ' . count($info['macros']) . ' ' . mb_strtolower(WEB_zabbix_macro)
        . ', ' . (count($tag_names) ? count($tag_names) . ' ' . mb_strtolower(WEB_zabbix_tag) : WEB_zabbix_tag_none)
        . ', ' . ZABBIX_TAG_PARENT . ': ' . htmlspecialchars($state['parent'] !== '' ? $state['parent'] : '-', ENT_QUOTES, 'UTF-8')
        . ', ' . (count($info['groups'])
            ? count($info['groups']) . ' ' . mb_strtolower(WEB_zabbix_host_groups) . ' (' . implode(', ', $info['groups']) . ')'
            : WEB_zabbix_group_none);
    echo "</td></tr>\n";

    return true;
}


// Result of the export of one record, the text is built from the language constants
function zabbix_record_result_text($result)
{
    switch ($result['action'] ?? 'failed') {
        case 'created':
            return sprintf(WEB_zabbix_host_created, $result['host'] ?? '',
                (int)($result['templates'] ?? 0), (int)($result['macros'] ?? 0),
                (int)($result['tags'] ?? 0), (int)($result['groups'] ?? 0));
        case 'updated':
            $notes = [];
            foreach ($result['notes'] ?? [] as $note) {
                $constant = 'WEB_zabbix_host_note_' . $note;
                if (defined($constant)) {
                    $notes[] = constant($constant);
                }
            }
            return sprintf(WEB_zabbix_host_updated, $result['host'] ?? '', implode(', ', $notes));
        case 'unchanged':
            if (isset($result['disabled'])) {
                return sprintf(WEB_zabbix_host_disabled, $result['host'] ?? '');
            }
            return sprintf(WEB_zabbix_host_unchanged, $result['host'] ?? '');
        case 'disabled':
            return sprintf(WEB_zabbix_host_switched_off, $result['host'] ?? '');
        case 'missing':
            return WEB_zabbix_host_missing;
        case 'occupied':
            return sprintf(WEB_zabbix_host_occupied, $result['parent'] ?? '', $result['key'] ?? '');
        case 'skipped':
            return WEB_zabbix_record_skip;
        case 'unsupported':
            return WEB_zabbix_disabled;
        default:
            return sprintf(WEB_zabbix_host_failed, $result['host'] ?? '');
    }
}

// Result of the export turned into the message of the page. The change of the
// monitoring system state is written to the journal regardless of the log level
function zabbix_record_sync_message($db, $result)
{
    $text = zabbix_record_result_text($result);
    if (($result['action'] ?? '') === 'failed') {
        LOG_WARNING($db, $text);
    } else {
        write_log($db, $text, L_INFO);
    }
    return $text;
}

// Export of the record with the silent skipping of the cases which are not zabbix errors
function zabbix_record_sync_report($db, $type, $id, $tpl_mode = ZABBIX_TEMPLATES_ADD)
{
    $result = zabbix_record_sync($db, $type, $id, $tpl_mode);
    if (in_array($result['action'] ?? '', ['unsupported', 'skipped'], true)) {
        return '';
    }

    return zabbix_record_sync_message($db, $result);
}

// The message of the export appended to the messages of the page
function zabbix_record_report_message($db, $type, $id, $current_message = '',
                                      $tpl_mode = ZABBIX_TEMPLATES_ADD)
{
    $message = zabbix_record_sync_report($db, $type, $id, $tpl_mode);
    if ($message === '') {
        return $current_message;
    }
    if ($current_message !== '') {
        return $current_message . '<br>' . $message;
    }
    return $message;
}
