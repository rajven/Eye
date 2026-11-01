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

    if ($type === 'netdev') {
        return zabbix_device_state($db, $id);
    }
    if ($type === 'auth') {
        return zabbix_auth_state($db, $id);
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

    return $state;
}

// State of an ip record, the device model of the record is taken from the bound device
function zabbix_auth_state($db, $auth_id)
{
    $auth = get_record_sql($db, "SELECT * FROM user_auth WHERE id = ? AND deleted = 0", [$auth_id]);
    if (empty($auth)) {
        return null;
    }

    $login = get_record_sql($db, "SELECT * FROM user_list WHERE id = ?", [(int)$auth['user_id']]);
    $device = get_record_sql($db, "SELECT * FROM devices WHERE user_id = ?", [(int)$auth['user_id']]);

    // a monitored switch or a monitored router is exported as a network device
    if (!empty($device) && (int)$device['deleted'] === 0
        && (int)$device['device_type'] <= 2 && (int)$device['monitoring'] === 1) {
        return null;
    }

    $model_id = !empty($device) ? (int)($device['device_model_id'] ?? 0) : 0;

    // the name of the host is built by the rule of the export script
    $name = zabbix_auth_host_name($auth, $login);

    $state = [
        'type' => 'auth',
        'id' => (int)$auth['id'],
        'key' => 'auth_' . $auth['id'],
        'host' => $name,
        'description' => $auth['description'],
        'ip' => $auth['ip'],
        // the model of the addresses is the model of the device bound to them
        'model_id' => $model_id,
        // macros and tags are bound to the device of the user, when it is registered
        'device_id' => !empty($device) ? (int)$device['id'] : 0,
        'monitoring' => (int)($auth['monitoring'] ?? 0),
        'monitoring_old' => (int)($auth['monitoring_old'] ?? 0),
        'parent' => '',
        'snmp' => !empty($device) ? zabbix_record_snmp($device) : [],
    ];

    // the address belongs to the ou of the user, as the export script does
    $state['ou_id'] = (int)($login['ou_id'] ?? 0);

    // the switch the address is connected to becomes the parent tag
    $connection = get_record_sql($db, "SELECT * FROM connections WHERE auth_id = ?", [(int)$auth['id']]);
    if (!empty($connection['port_id'])) {
        $port = get_record_sql($db, "SELECT * FROM device_ports WHERE id = ?", [(int)$connection['port_id']]);
        if (!empty($port['device_id'])) {
            $parent = get_record_sql($db, "SELECT * FROM devices WHERE id = ? AND deleted = 0", [(int)$port['device_id']]);
            if (!empty($parent)) {
                $state['parent'] = $parent['device_name'];
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
        $list[] = [
            'macro' => $name,
            'value' => trim((string)($row['macro_value'] ?? '')),
            'type' => (int)($row['macro_type'] ?? 0),
        ];
    }

    return $list;
}

// Bindings of the eye network device: the macros and the tags look the same in the
// database, so one request serves both of them
function zabbix_record_device_rows($db, $kind, $device_id)
{
    static $cache = [];

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

// The host of the record: by its name, then by the eye tag of the record
function zabbix_find_host($db, $state)
{
    $zbx_host = zabbix_get_host_by_name($state['host']);
    if ($zbx_host === null) {
        // the eye tag finds the host even after it was renamed in zabbix
        $zbx_host = zabbix_get_host_by_tag($state['key']);
    }

    return $zbx_host;
}

// Creation or update of the zabbix host of the eye record
function zabbix_record_apply($db, $state)
{
    $templates = zabbix_record_templates($db, (int)$state['model_id']);
    $macros = zabbix_record_macros($db, (int)$state['device_id']);
    $groups = zabbix_record_groups($db, $state);
    $tags = zabbix_record_tags($db, $state, $state['key']);

    $zbx_host = zabbix_find_host($db, $state);

    if ($zbx_host === null) {
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

    return zabbix_record_update($db, $state, $zbx_host, $templates, $macros, $groups, $tags);
}

// The name, the groups, the tags, the macros and the missing templates are updated.
// eye owns the objects of its own lists, the objects of the other integrations stay
function zabbix_record_update($db, $state, $zbx_host, $templates, $macros, $groups, $tags)
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

    // the missing host groups of the ou are added, the groups of the other
    // integrations are never removed
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

    list($merged, $added) = zabbix_host_merge_templates($zbx_host, $templates);
    if (!empty($added)) {
        $update['templates'] = $merged;
        $notes[] = count($added) . ' template(s) added';
        $note_types[] = 'templates';
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

    if (empty($update)) {
        return ['action' => 'unchanged', 'hostid' => $zbx_host['hostid'], 'host' => $zbx_host['host']];
    }

    if (!zabbix_api_update_host($zbx_host['hostid'], $update)) {
        return ['action' => 'failed', 'host' => $zbx_host['host']];
    }

    LOG_INFO($db, "Zabbix: host " . $zbx_host['host'] . " updated: " . implode(', ', $notes));
    return [
        'action' => 'updated',
        'hostid' => $zbx_host['hostid'],
        'host' => $zbx_host['host'],
        'notes' => $note_types,
        'templates' => count($added),
        'parent' => (string)$state['parent'],
    ];
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

// eye owns the tag of the record, the parent tag and the names of its global list
function zabbix_record_tag_is_owned($name, $owned)
{
    $key = strtolower(trim((string)$name));
    if ($key === ZABBIX_TAG_EYE || $key === ZABBIX_TAG_PARENT) {
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

// The record is exported to zabbix after the changes made by the web interface
function zabbix_record_sync($db, $type, $id)
{
    if (!zabbix_record_supported($db)) {
        return ['action' => 'unsupported'];
    }

    $state = zabbix_record_state($db, $type, $id);
    if ($state === null) {
        return ['action' => 'skipped'];
    }

    if ((int)$state['monitoring'] === 1) {
        return zabbix_record_apply($db, $state);
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
function zabbix_record_sync_report($db, $type, $id)
{
    $result = zabbix_record_sync($db, $type, $id);
    if (in_array($result['action'] ?? '', ['unsupported', 'skipped'], true)) {
        return '';
    }

    return zabbix_record_sync_message($db, $result);
}

// The message of the export appended to the messages of the page
function zabbix_record_report_message($db, $type, $id, $current_message = '')
{
    $message = zabbix_record_sync_report($db, $type, $id);
    if ($message === '') {
        return $current_message;
    }
    if ($current_message !== '') {
        return $current_message . '<br>' . $message;
    }
    return $message;
}
