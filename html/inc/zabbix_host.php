<?php
// Export of a single eye record (a network device or an ip record) to zabbix.
// The rules of the host names, tags, groups, templates and macros are the same as
// in docs/zabbix/sync_zabbix.pl, so both ways give the same result in zabbix.
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
        'monitoring' => (int)($auth['monitoring'] ?? 0),
        'monitoring_old' => (int)($auth['monitoring_old'] ?? 0),
        'parent' => '',
        'snmp' => !empty($device) ? zabbix_record_snmp($device) : [],
    ];

    $ou_id = (int)($auth['ou_id'] ?? 0);
    if ($ou_id <= 0 && !empty($login['ou_id'])) {
        $ou_id = (int)$login['ou_id'];
    }
    $state['ou_id'] = $ou_id;

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

// Group of the organizational unit of the record, the default group is used as a fallback
function zabbix_record_groups($db, $state)
{
    $ou = get_record_sql($db, "SELECT * FROM ou WHERE id = ?", [(int)$state['ou_id']]);
    $group_id = !empty($ou['zabbix_group_id']) ? (int)$ou['zabbix_group_id'] : 0;

    if ($group_id === 0) {
        LOG_DEBUG($db, "Zabbix: ou '" . ($ou['ou_name'] ?? $state['ou_id']) . "' has no host group, the default group " . ZABBIX_DEFAULT_GROUP_ID . " is used");
        $group_id = ZABBIX_DEFAULT_GROUP_ID;
    }

    $group = zabbix_api_request('hostgroup.get', [
        'output' => ['groupid', 'name'],
        'groupids' => [$group_id],
    ]);
    if (empty($group[0]['groupid'])) {
        LOG_WARNING($db, "Zabbix: host group $group_id is not found in zabbix, the host is created without group");
        return [];
    }

    return [(int)$group[0]['groupid']];
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

// Macros used by the device model, the model value overrides the value of the macros list
function zabbix_record_macros($db, $model_id)
{
    if ($model_id <= 0) {
        return [];
    }

    $rows = get_records_sql($db,
        "SELECT m.macro_value, l.macro_name, l.macro_type, l.default_value
         FROM zabbix_macros m JOIN zabbix_macros_list l ON m.macros_list_id = l.id
         WHERE m.device_model_id = ? AND m.is_used = 1 ORDER BY l.macro_name", [$model_id]);

    $list = [];
    foreach ($rows as $row) {
        $name = normalizer_zabbix_macro_name($row['macro_name']);
        if ($name === '') {
            continue;
        }
        $value = trim((string)($row['macro_value'] ?? ''));
        if ($value === '') {
            $value = trim((string)($row['default_value'] ?? ''));
        }
        $list[] = [
            'macro' => $name,
            'value' => $value,
            'type' => (int)($row['macro_type'] ?? 0),
        ];
    }

    return $list;
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
    $macros = zabbix_record_macros($db, (int)$state['model_id']);
    $groups = zabbix_record_groups($db, $state);
    $eye_tag = $state['key'];

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
            'tags' => [
                ZABBIX_TAG_EYE => $eye_tag,
                ZABBIX_TAG_PARENT => $state['parent'],
            ],
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
        ];
    }

    return zabbix_record_update($db, $state, $zbx_host, $templates, $macros, $eye_tag);
}

// The name, the parent tag, the macros and the missing templates are updated
function zabbix_record_update($db, $state, $zbx_host, $templates, $macros, $eye_tag)
{
    $update = [];
    $notes = [];
    $note_types = [];

    if (!empty($zbx_host['host']) && strcasecmp(trim($zbx_host['host']), trim($state['host'])) !== 0) {
        $update['host'] = $state['host'];
        $notes[] = 'renamed to ' . $state['host'];
        $note_types[] = 'renamed';
    }
    if (($zbx_host['name'] ?? '') !== $state['description']) {
        $update['description'] = $state['description'];
        $notes[] = 'the visible name is updated';
        $note_types[] = 'visible';
    }

    $old_parent = zabbix_host_tag_value($zbx_host, ZABBIX_TAG_PARENT);
    $old_eye = zabbix_host_tag_value($zbx_host, ZABBIX_TAG_EYE);
    if ($old_parent !== (string)$state['parent'] || $old_eye !== $eye_tag) {
        // zabbix replaces the whole list of the tags, the foreign tags are kept
        $tags = [];
        foreach ($zbx_host['tags'] as $tag) {
            if (empty($tag['tag'])) {
                continue;
            }
            if (strcasecmp($tag['tag'], ZABBIX_TAG_EYE) === 0 || strcasecmp($tag['tag'], ZABBIX_TAG_PARENT) === 0) {
                continue;
            }
            $tags[$tag['tag']] = $tag['value'];
        }
        $tags[ZABBIX_TAG_EYE] = $eye_tag;
        $tags[ZABBIX_TAG_PARENT] = $state['parent'];
        $update['tags'] = $tags;
        $notes[] = 'tag ' . ZABBIX_TAG_PARENT . ' => ' . ($state['parent'] !== '' ? $state['parent'] : '""');
        $note_types[] = 'tags';
    }

    list($merged, $added) = zabbix_host_merge_templates($zbx_host, $templates);
    if (!empty($added)) {
        $update['templates'] = $merged;
        $notes[] = count($added) . ' template(s) added';
        $note_types[] = 'templates';
    }

    $merged_macros = zabbix_host_merge_macros($zbx_host, $macros);
    if ($merged_macros !== ($zbx_host['macros'] ?? [])) {
        $update['macros'] = $merged_macros;
        $notes[] = 'macros updated';
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
    $macros = zabbix_record_macros($db, (int)$state['model_id']);
    $groups = zabbix_record_groups($db, $state);

    return [
        'state' => $state,
        'host' => zabbix_find_host($db, $state),
        'groups' => $groups,
        'templates' => $templates,
        'macros' => $macros,
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
    echo count($info['templates']) . ' ' . mb_strtolower(WEB_zabbix_template)
        . ', ' . count($info['macros']) . ' ' . mb_strtolower(WEB_zabbix_macro)
        . ', ' . ZABBIX_TAG_PARENT . ': ' . htmlspecialchars($state['parent'] !== '' ? $state['parent'] : '-', ENT_QUOTES, 'UTF-8')
        . ', ' . (count($info['groups']) ? 'group ' . (int)$info['groups'][0] : 'no group');
    echo "</td></tr>\n";

    return true;
}


// Result of the export of one record, the text is built from the language constants
function zabbix_record_result_text($result)
{
    switch ($result['action'] ?? 'failed') {
        case 'created':
            return sprintf(WEB_zabbix_host_created, $result['host'] ?? '', (int)($result['templates'] ?? 0), (int)($result['macros'] ?? 0));
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
